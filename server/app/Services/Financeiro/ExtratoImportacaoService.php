<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\DTO\ExtratoArquivo;
use App\DTO\MovimentoExtrato;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\Financeiro\Extrato\ImportacaoEmAndamentoException;
use App\Services\Financeiro\Extrato\ImportacaoRequerConfirmacaoException;
use App\Services\Financeiro\Extrato\OfxParser;
use App\Services\Financeiro\Extrato\ParserExtratoInterface;
use RuntimeException;
use Throwable;

/**
 * Importação idempotente de extratos bancários.
 *
 * Importar o mesmo movimento duas vezes nunca cria um segundo registro: a chave
 * (ChaveMovimentoExtrato) é única por conta e o índice único do banco é a
 * garantia final. O arquivo bruto não é guardado, só o hash do conteúdo.
 *
 * Os logs trazem apenas contagens e IDs, nunca memo ou nome da contraparte.
 */
class ExtratoImportacaoService
{
    /** Fração do arquivo com FITID novo mas igual (data+valor+memo) a movimento existente que dispara o alerta. */
    public const LIMITE_REEMISSAO_FITID = 0.5;

    public const ALERTA_ARQUIVO_JA_ENVIADO = 'arquivo_ja_enviado';
    public const ALERTA_REEMISSAO_FITID    = 'possivel_reemissao_de_fitid';
    public const ALERTA_PERIODO_COBERTO    = 'periodo_ja_coberto';
    public const ALERTA_MES_FECHADO        = 'mes_fechado';
    public const ALERTA_CONFLITOS          = 'conflitos';

    private const ALERTAS_QUE_EXIGEM_CONFIRMACAO = [self::ALERTA_REEMISSAO_FITID];

    private ExtratosImportacoesRepository $importacoes;
    private ExtratosMovimentosRepository $movimentos;
    private ContasRepository $contas;
    private SaldoMensalService $saldoMensal;
    private ParserExtratoInterface $parser;
    /** Conexão com transBegin/transCommit/transRollback (padrão: a conexão do CodeIgniter). */
    private ?object $db;

    public function __construct(
        ?ExtratosImportacoesRepository $importacoes = null,
        ?ExtratosMovimentosRepository $movimentos = null,
        ?ContasRepository $contas = null,
        ?SaldoMensalService $saldoMensal = null,
        ?ParserExtratoInterface $parser = null,
        ?object $db = null
    ) {
        $this->importacoes = $importacoes ?? new ExtratosImportacoesRepository();
        $this->movimentos  = $movimentos ?? new ExtratosMovimentosRepository();
        $this->contas      = $contas ?? new ContasRepository();
        $this->saldoMensal = $saldoMensal ?? new SaldoMensalService();
        $this->parser      = $parser ?? new OfxParser((new ExtratoConfig())->tamanhoMaxBytes());
        $this->db          = $db;
    }

    /**
     * Mostra o que seria importado, sem gravar nada.
     *
     * @return array<string,mixed>
     * @throws ExtratoInvalidoException Arquivo inválido ou de outra conta.
     * @throws ExtratoNaoEncontradoException Conta inexistente.
     */
    public function previa(int $contaId, string $nomeOriginal, ?string $mime, string $conteudo): array
    {
        [$arquivo, $hash] = $this->lerArquivo($contaId, $nomeOriginal, $mime, $conteudo);
        $analise = $this->analisar($contaId, $arquivo, $hash);

        return $this->montarResposta($analise, null, true);
    }

    /**
     * Grava os movimentos novos do arquivo.
     *
     * @return array<string,mixed> Resumo (importacao_id, lidos, novos, duplicados, conflitos, periodo, alertas, ...).
     * @throws ExtratoInvalidoException              Arquivo inválido ou de outra conta (nada é gravado).
     * @throws ImportacaoRequerConfirmacaoException  Há alerta que exige `confirmar=true`.
     * @throws ImportacaoEmAndamentoException        Outra importação da mesma conta está em curso.
     */
    public function importar(
        int $contaId,
        string $nomeOriginal,
        ?string $mime,
        string $conteudo,
        bool $confirmar = false,
        ?int $userId = null
    ): array {
        [$arquivo, $hash] = $this->lerArquivo($contaId, $nomeOriginal, $mime, $conteudo);

        if (!$this->importacoes->obterLockConta($contaId)) {
            throw new ImportacaoEmAndamentoException('Já existe uma importação em andamento para esta conta. Tente novamente em instantes.');
        }

        try {
            // A análise é refeita dentro do lock: enxerga o que outra importação acabou de gravar.
            $analise = $this->analisar($contaId, $arquivo, $hash);

            $exigem = array_values(array_filter(
                $analise['alertas'],
                static fn (array $a): bool => in_array($a['codigo'], self::ALERTAS_QUE_EXIGEM_CONFIRMACAO, true)
            ));
            if ($exigem && !$confirmar) {
                throw new ImportacaoRequerConfirmacaoException(
                    'Este arquivo parece ser uma reemissão do extrato com identificadores diferentes. Confirme para importar mesmo assim.',
                    $analise['alertas']
                );
            }

            $resultado = $this->gravar($contaId, $arquivo, $hash, $nomeOriginal, $analise, $userId);
        } finally {
            $this->importacoes->liberarLockConta($contaId);
        }

        log_message('info', sprintf(
            '[ExtratoImportacaoService::importar] conta=%d importacao=%d lidos=%d novos=%d duplicados=%d conflitos=%d',
            $contaId,
            $resultado['importacao_id'],
            $resultado['lidos'],
            $resultado['novos'],
            $resultado['duplicados'],
            $resultado['conflitos']
        ));

        return $resultado;
    }

    /**
     * Histórico de importações.
     *
     * @return array{itens:array,total:int}
     */
    public function listarImportacoes(?int $contaId, int $pagina = 1, int $porPagina = 20): array
    {
        return $this->importacoes->pesquisar($contaId, $pagina, $porPagina);
    }

    /**
     * Detalhe de uma importação: totais e movimentos criados por ela.
     * Duplicados e conflitos não são guardados linha a linha; ficam só nos totais.
     *
     * @throws ExtratoNaoEncontradoException Importação inexistente.
     */
    public function detalharImportacao(int $id): array
    {
        $importacao = $this->importacoes->findById($id);
        if (!$importacao) {
            throw new ExtratoNaoEncontradoException('Importação não encontrada.');
        }

        return [
            'importacao'          => $importacao,
            'movimentos_criados'  => $this->movimentos->listarPorImportacao($id),
        ];
    }

    /**
     * Lista de movimentos com filtros (conta_id, data_inicio, data_fim, status, texto).
     *
     * @return array{itens:array,total:int}
     */
    public function listarMovimentos(array $filtros, int $pagina = 1, int $porPagina = 50): array
    {
        return $this->movimentos->pesquisar($filtros, $pagina, $porPagina);
    }

    // ------------------------------------------------------------------
    // Leitura e conferência do arquivo
    // ------------------------------------------------------------------

    /**
     * @return array{0:ExtratoArquivo,1:string} Arquivo lido e hash do conteúdo.
     */
    private function lerArquivo(int $contaId, string $nomeOriginal, ?string $mime, string $conteudo): array
    {
        $conta = $this->contas->findById($contaId);
        if (!$conta) {
            throw new ExtratoNaoEncontradoException('Conta não encontrada.');
        }

        $this->parser->validarArquivo($nomeOriginal, $mime, strlen($conteudo));
        $arquivo = $this->parser->parse($conteudo);

        $this->conferirConta($conta, $arquivo);

        return [$arquivo, hash('sha256', $conteudo)];
    }

    /**
     * Banco, agência e conta do arquivo precisam bater com a conta escolhida.
     * Só se compara o que o cadastro da conta tem em dígitos (o campo banco pode ser um nome).
     * Sem número de conta cadastrado não dá para conferir, e a importação é recusada.
     */
    private function conferirConta(array $conta, ExtratoArquivo $arquivo): void
    {
        $norm = [ChaveMovimentoExtrato::class, 'normalizarIdentificador'];

        $numeroCadastro = $norm($conta['numero_conta'] ?? '');
        if ($numeroCadastro === '') {
            throw new ExtratoInvalidoException('A conta escolhida não tem número cadastrado, então não dá para conferir com o arquivo. Preencha agência e número da conta no cadastro.');
        }

        $bancoCadastro = $norm($conta['banco'] ?? '');
        if ($bancoCadastro !== '' && $bancoCadastro !== $norm($arquivo->banco)) {
            throw new ExtratoInvalidoException('O banco do arquivo não confere com a conta escolhida. Nada foi importado.');
        }

        // Agência e conta toleram o dígito verificador (separado, colado ou ausente), mas não números diferentes.
        $agenciaCadastro = $norm($conta['agencia'] ?? '');
        if ($agenciaCadastro !== '' && !ConferenciaContaExtrato::confere((string) $conta['agencia'], $arquivo->agencia)) {
            throw new ExtratoInvalidoException('A agência do arquivo não confere com a conta escolhida. Nada foi importado.');
        }

        if (!ConferenciaContaExtrato::confere((string) $conta['numero_conta'], $arquivo->conta)) {
            throw new ExtratoInvalidoException('O número da conta do arquivo não confere com a conta escolhida. Nada foi importado.');
        }
    }

    // ------------------------------------------------------------------
    // Análise (usada pela prévia e pela importação)
    // ------------------------------------------------------------------

    /**
     * @return array{
     *   arquivo:ExtratoArquivo, hash:string, itens:list<array<string,mixed>>,
     *   lidos:int, novos:int, duplicados:int, conflitos:int, alertas:list<array<string,mixed>>
     * }
     */
    private function analisar(int $contaId, ExtratoArquivo $arquivo, string $hash): array
    {
        $chaves = ChaveMovimentoExtrato::gerarLote(
            $arquivo->banco,
            $arquivo->agencia,
            $arquivo->conta,
            array_map(static fn (MovimentoExtrato $m): array => [
                'fitid' => $m->fitidConfiavel ? $m->fitid : null,
                'data'  => $m->data,
                'valor' => $m->valorCentavos,
                'memo'  => $m->memo,
            ], $arquivo->movimentos)
        );

        $existentes = $this->movimentos->buscarPorChaves($contaId, array_column($chaves, 'chave'));

        $itens = [];
        $novos = $duplicados = $conflitos = 0;
        $vistas = [];

        foreach ($arquivo->movimentos as $i => $mov) {
            $chave = $chaves[$i]['chave'];
            $item  = [
                'movimento'  => $mov,
                'chave'      => $chave,
                'chave_tipo' => $chaves[$i]['chave_tipo'],
                'situacao'   => 'novo',
            ];

            $existente = $existentes[$chave] ?? null;
            if ($existente !== null || isset($vistas[$chave])) {
                $mesmo = $existente === null
                    || ((string) $existente['data'] === $mov->data && (int) $existente['valor_centavos'] === $mov->valorCentavos);
                $item['situacao'] = $mesmo ? 'duplicado' : 'conflito';
            }
            $vistas[$chave] = true;

            match ($item['situacao']) {
                'novo'      => $novos++,
                'duplicado' => $duplicados++,
                default     => $conflitos++,
            };
            $itens[] = $item;
        }

        return [
            'arquivo'    => $arquivo,
            'hash'       => $hash,
            'itens'      => $itens,
            'lidos'      => count($itens),
            'novos'      => $novos,
            'duplicados' => $duplicados,
            'conflitos'  => $conflitos,
            'alertas'    => $this->montarAlertas($contaId, $arquivo, $hash, $itens, $conflitos),
        ];
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @return list<array<string,mixed>>
     */
    private function montarAlertas(int $contaId, ExtratoArquivo $arquivo, string $hash, array $itens, int $conflitos): array
    {
        $alertas = [];

        $anteriores = $this->importacoes->buscarPorHash($contaId, $hash);
        if ($anteriores) {
            $alertas[] = [
                'codigo'   => self::ALERTA_ARQUIVO_JA_ENVIADO,
                'mensagem' => 'Este mesmo arquivo já foi enviado antes. Nenhum movimento será repetido.',
                'enviado_em' => array_values(array_filter(array_map(static fn (array $i) => $i['created_at'] ?? null, $anteriores))),
            ];
        }

        if ($conflitos > 0) {
            $alertas[] = [
                'codigo'   => self::ALERTA_CONFLITOS,
                'mensagem' => "{$conflitos} movimento(s) têm o mesmo identificador de um já importado, mas data ou valor diferentes. Foram mantidos como estavam; revise.",
                'total'    => $conflitos,
            ];
        }

        $reemissao = $this->detectarReemissaoFitid($contaId, $arquivo, $itens);
        if ($reemissao !== null) {
            $alertas[] = $reemissao;
        }

        if ($arquivo->periodoInicio && $arquivo->periodoFim && $this->periodoJaCoberto($contaId, $arquivo->periodoInicio, $arquivo->periodoFim)) {
            $alertas[] = [
                'codigo'   => self::ALERTA_PERIODO_COBERTO,
                'mensagem' => 'Todo o período deste arquivo já tinha sido importado antes.',
            ];
        }

        $fechados = $this->mesesFechados($contaId, $itens);
        if ($fechados) {
            $alertas[] = [
                'codigo'   => self::ALERTA_MES_FECHADO,
                'mensagem' => 'Há movimentos novos em mês já fechado. Entram só como histórico (pendentes) e não poderão ser lançados.',
                'meses'    => array_keys($fechados),
                'total'    => array_sum($fechados),
            ];
        }

        return $alertas;
    }

    /**
     * FITID instável: itens com FITID novo cujo (data, valor, memo) coincide com movimento
     * já existente de outra chave. Se a fração do arquivo passa do limite, é provável que o
     * banco tenha reemitido o extrato com identificadores diferentes.
     *
     * @param list<array<string,mixed>> $itens
     */
    private function detectarReemissaoFitid(int $contaId, ExtratoArquivo $arquivo, array $itens): ?array
    {
        $candidatos = array_filter($itens, static fn (array $i): bool => $i['situacao'] === 'novo' && $i['chave_tipo'] === ChaveMovimentoExtrato::TIPO_FITID);
        if (!$candidatos) {
            return null;
        }

        $datas = array_map(static fn (MovimentoExtrato $m): string => $m->data, $arquivo->movimentos);
        $existentes = $this->movimentos->listarPorPeriodo($contaId, min($datas), max($datas));
        if (!$existentes) {
            return null;
        }

        $chavesDoArquivo = array_flip(array_column($itens, 'chave'));
        $disponiveis     = [];
        foreach ($existentes as $e) {
            if (isset($chavesDoArquivo[$e['chave']])) {
                continue;
            }
            $trio = $this->trio((string) $e['data'], (int) $e['valor_centavos'], (string) ($e['memo'] ?? ''));
            $disponiveis[$trio] = ($disponiveis[$trio] ?? 0) + 1;
        }

        $coincidem = 0;
        foreach ($candidatos as $c) {
            /** @var MovimentoExtrato $m */
            $m    = $c['movimento'];
            $trio = $this->trio($m->data, $m->valorCentavos, $m->memo);
            if (($disponiveis[$trio] ?? 0) > 0) {
                $disponiveis[$trio]--;
                $coincidem++;
            }
        }

        if ($coincidem === 0 || $coincidem / max(1, count($itens)) < self::LIMITE_REEMISSAO_FITID) {
            return null;
        }

        return [
            'codigo'   => self::ALERTA_REEMISSAO_FITID,
            'mensagem' => "{$coincidem} movimento(s) do arquivo têm identificador novo, mas data, valor e descrição iguais a movimentos já importados. O banco pode ter reemitido o extrato. Confirme para importar mesmo assim (pode duplicar).",
            'total'    => $coincidem,
            'exige_confirmacao' => true,
        ];
    }

    private function trio(string $data, int $centavos, string $memo): string
    {
        return $data . '|' . $centavos . '|' . ChaveMovimentoExtrato::normalizarMemo($memo);
    }

    /** O período [inicio, fim] está inteiro dentro da união das importações concluídas? */
    private function periodoJaCoberto(int $contaId, string $inicio, string $fim): bool
    {
        $faixas = [];
        foreach ($this->importacoes->listarConcluidasPorConta($contaId) as $imp) {
            if (!empty($imp['periodo_inicio']) && !empty($imp['periodo_fim'])) {
                $faixas[] = [(string) $imp['periodo_inicio'], (string) $imp['periodo_fim']];
            }
        }
        usort($faixas, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $cobertoAte = null;
        foreach ($faixas as [$i, $f]) {
            if ($cobertoAte === null) {
                if ($i > $inicio) {
                    continue;
                }
                $cobertoAte = $f;
            } elseif ($i <= date('Y-m-d', strtotime($cobertoAte . ' +1 day'))) {
                $cobertoAte = max($cobertoAte, $f);
            }
            if ($cobertoAte !== null && $cobertoAte >= $fim) {
                return true;
            }
        }

        return false;
    }

    /**
     * Meses fechados com movimentos novos.
     *
     * @param list<array<string,mixed>> $itens
     * @return array<string,int> 'AAAA-MM' => total de movimentos novos
     */
    private function mesesFechados(int $contaId, array $itens): array
    {
        $porMes = [];
        foreach ($itens as $item) {
            if ($item['situacao'] === 'novo') {
                $mes = substr($item['movimento']->data, 0, 7);
                $porMes[$mes] = ($porMes[$mes] ?? 0) + 1;
            }
        }

        $fechados = [];
        foreach ($porMes as $mes => $total) {
            try {
                $this->saldoMensal->verificarBloqueioMensal('CONTA', $contaId, $mes . '-01');
            } catch (\Exception) {
                $fechados[$mes] = $total;
            }
        }

        return $fechados;
    }

    // ------------------------------------------------------------------
    // Gravação
    // ------------------------------------------------------------------

    /**
     * Tudo numa transação: qualquer erro desfaz a importação inteira.
     *
     * @param array<string,mixed> $analise
     * @return array<string,mixed>
     */
    private function gravar(int $contaId, ExtratoArquivo $arquivo, string $hash, string $nomeOriginal, array $analise, ?int $userId): array
    {
        $db = $this->db ?? \Config\Database::connect();
        $db->transBegin();

        try {
            $importacaoId = $this->importacoes->create([
                'conta_id'            => $contaId,
                'formato'             => 'ofx',
                'arquivo_nome'        => mb_substr(basename($nomeOriginal), 0, 255),
                'arquivo_hash'        => $hash,
                'periodo_inicio'      => $arquivo->periodoInicio,
                'periodo_fim'         => $arquivo->periodoFim,
                'saldo_final_arquivo' => $arquivo->saldoFinalCentavos,
                'saldo_final_data'    => $arquivo->saldoData,
                'status'              => 'processando',
                'enviado_por'         => $userId,
            ]);
            if ($importacaoId <= 0) {
                throw new RuntimeException('Não foi possível registrar a importação.');
            }

            $novos      = 0;
            $duplicados = $analise['duplicados'];
            foreach ($analise['itens'] as $item) {
                if ($item['situacao'] !== 'novo') {
                    continue;
                }
                /** @var MovimentoExtrato $m */
                $m  = $item['movimento'];
                $id = $this->movimentos->inserirSeNovo([
                    'conta_id'         => $contaId,
                    'importacao_id'    => $importacaoId,
                    'chave'            => $item['chave'],
                    'chave_tipo'       => $item['chave_tipo'],
                    'fitid'            => $m->fitidConfiavel ? mb_substr((string) $m->fitid, 0, 255) : null,
                    'data'             => $m->data,
                    'valor_centavos'   => $m->valorCentavos,
                    'tipo_banco'       => $m->tipoBanco !== '' ? mb_substr($m->tipoBanco, 0, 20) : null,
                    'memo'             => $m->memo !== '' ? mb_substr($m->memo, 0, 500) : null,
                    'nome_contraparte' => $m->nome !== '' ? mb_substr($m->nome, 0, 255) : null,
                    'status'           => 'pendente',
                ], false);

                $id === null ? $duplicados++ : $novos++;
            }

            if (!$this->importacoes->update($importacaoId, [
                'total_lidos'      => $analise['lidos'],
                'total_novos'      => $novos,
                'total_duplicados' => $duplicados,
                'total_conflitos'  => $analise['conflitos'],
                'status'           => 'concluida',
            ])) {
                throw new RuntimeException('Não foi possível concluir a importação.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[ExtratoImportacaoService::gravar] conta=' . $contaId . ' ' . get_class($e) . ': ' . $e->getMessage());

            throw $e;
        }

        return $this->montarResposta($analise, $importacaoId, false, $novos, $duplicados);
    }

    /**
     * @param array<string,mixed> $analise
     * @return array<string,mixed>
     */
    private function montarResposta(array $analise, ?int $importacaoId, bool $incluirMovimentos, ?int $novos = null, ?int $duplicados = null): array
    {
        /** @var ExtratoArquivo $arquivo */
        $arquivo = $analise['arquivo'];

        $resposta = [
            'importacao_id' => $importacaoId,
            'lidos'         => $analise['lidos'],
            'novos'         => $novos ?? $analise['novos'],
            'duplicados'    => $duplicados ?? $analise['duplicados'],
            'conflitos'     => $analise['conflitos'],
            'periodo'       => [$arquivo->periodoInicio, $arquivo->periodoFim],
            'alertas'       => $analise['alertas'],
            'conflitos_detalhe' => array_values(array_map(
                fn (array $i): array => $this->itemParaArray($i, false),
                array_filter($analise['itens'], static fn (array $i): bool => $i['situacao'] === 'conflito')
            )),
        ];

        if ($incluirMovimentos) {
            $resposta['movimentos'] = array_map(fn (array $i): array => $this->itemParaArray($i, true), $analise['itens']);
        }

        return $resposta;
    }

    private function itemParaArray(array $item, bool $comTexto): array
    {
        /** @var MovimentoExtrato $m */
        $m = $item['movimento'];

        $linha = [
            'situacao'       => $item['situacao'],
            'data'           => $m->data,
            'valor_centavos' => $m->valorCentavos,
            'fitid'          => $m->fitidConfiavel ? $m->fitid : null,
        ];
        if ($comTexto) {
            $linha['memo']             = $m->memo;
            $linha['nome_contraparte'] = $m->nome;
        }

        return $linha;
    }
}
