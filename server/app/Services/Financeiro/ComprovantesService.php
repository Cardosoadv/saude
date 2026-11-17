<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ComprovantesRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Services\BaseService;
use App\Services\ConfiguracoesService;
use App\Services\GoogleDriveService;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Comprovantes de pagamento guardados no Google Drive, na pasta do financeiro,
 * em subpastas mensais (AAAA-MM, pela data do pagamento).
 *
 * @property ComprovantesRepository $repository
 */
class ComprovantesService extends BaseService
{
    public const TIPOS = ['despesa', 'receita'];

    /** Tipos aceitos (MIME real do arquivo) e a extensão usada no nome salvo no Drive. */
    public const MIMES_PERMITIDOS = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    public const MAX_MB_PADRAO       = 10;
    public const MAX_ARQUIVOS_PADRAO = 5;

    /** Máximo de pagamentos ligados aos mesmos arquivos em um envio em lote. */
    public const MAX_PAGAMENTOS_LOTE = 50;

    protected ?GoogleDriveService $drive;
    protected PagamentoDespesasRepository $pagamentoDespesasRepository;
    protected PagamentoReceitasRepository $pagamentoReceitasRepository;
    protected ContasRepository $contasRepository;
    protected SaldoMensalService $saldoMensalService;
    protected ?ConfiguracoesService $configuracoesService;

    public function __construct(
        ?ComprovantesRepository $repository = null,
        ?GoogleDriveService $drive = null,
        ?PagamentoDespesasRepository $pagamentoDespesasRepository = null,
        ?PagamentoReceitasRepository $pagamentoReceitasRepository = null,
        ?ContasRepository $contasRepository = null,
        ?SaldoMensalService $saldoMensalService = null,
        ?ConfiguracoesService $configuracoesService = null
    ) {
        $this->repository                  = $repository ?? new ComprovantesRepository();
        $this->drive                       = $drive;
        $this->pagamentoDespesasRepository = $pagamentoDespesasRepository ?? new PagamentoDespesasRepository();
        $this->pagamentoReceitasRepository = $pagamentoReceitasRepository ?? new PagamentoReceitasRepository();
        $this->contasRepository            = $contasRepository ?? new ContasRepository();
        $this->saldoMensalService          = $saldoMensalService ?? new SaldoMensalService();
        $this->configuracoesService        = $configuracoesService;
    }

    /**
     * Cria o cliente do Drive só quando necessário (o construtor renova o token OAuth).
     */
    protected function drive(): GoogleDriveService
    {
        return $this->drive ??= new GoogleDriveService();
    }

    /**
     * Verifica a configuração da pasta do financeiro e se o sistema consegue gravar nela.
     *
     * @return array{pasta_id:string}
     * @throws Exception Quando a pasta não está configurada ou não aceita gravação.
     */
    public function testarPasta(): array
    {
        $pastaId = $this->drive()->getPastaFinanceiraId();
        $this->drive()->testarGravacao($pastaId);

        return ['pasta_id' => $pastaId];
    }

    /**
     * Lista os comprovantes de um pagamento.
     *
     * @throws RuntimeException Quando o pagamento não existe.
     */
    public function listarPorPagamento(string $tipo, int $pagamentoId): array
    {
        $this->buscarPagamento($tipo, $pagamentoId);

        return $this->repository->listarPorPagamento($tipo, $pagamentoId);
    }

    /**
     * Conta os comprovantes de vários pagamentos (pagamento_id => total).
     *
     * @param int[] $pagamentoIds
     */
    public function contarPorPagamentos(string $tipo, array $pagamentoIds): array
    {
        return $this->repository->contarPorPagamentos($tipo, $pagamentoIds);
    }

    /**
     * Acrescenta `total_comprovantes` a cada linha de uma listagem de pagamentos,
     * com uma única consulta (sem N+1).
     *
     * @param array[] $linhas  Linhas da listagem.
     * @param string  $chave   Campo com o ID do pagamento (id_pgto_despesa / id_pgto_receita).
     */
    public function anexarTotais(string $tipo, array $linhas, string $chave): array
    {
        $totais = $this->contarPorPagamentos($tipo, array_column($linhas, $chave));

        foreach ($linhas as &$linha) {
            $linha['total_comprovantes'] = $totais[(int) ($linha[$chave] ?? 0)] ?? 0;
        }

        return $linhas;
    }

    /**
     * Valida e envia comprovantes para a subpasta do mês do pagamento.
     *
     * @param array<int,array{path:string,nome:string}> $arquivos Arquivos recebidos (caminho temporário e nome original).
     * @return array{enviados:array,erros:array} Comprovantes gravados e falhas por arquivo.
     *
     * @throws RuntimeException         Pagamento inexistente.
     * @throws InvalidArgumentException Arquivos inválidos (quantidade, tamanho ou tipo).
     * @throws Exception                Mês fechado ou pasta do financeiro indisponível.
     */
    public function enviar(string $tipo, int $pagamentoId, array $arquivos, ?int $userId = null): array
    {
        $pagamento = $this->buscarPagamento($tipo, $pagamentoId);
        $data      = $this->dataDoPagamento($tipo, $pagamento);

        $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int) ($pagamento['conta_id'] ?? 0), $data);

        $validos = $this->validarArquivos($arquivos);

        $anoMes  = date('Y-m', strtotime($data));
        $pastaId = $this->drive()->pastaMensal($anoMes);
        $base    = $this->nomeBase($tipo, $pagamento, $data, $pagamentoId);
        $existentes = count($this->repository->listarPorPagamento($tipo, $pagamentoId));

        $enviados = [];
        $erros    = [];
        foreach ($validos as $i => $arquivo) {
            $nomeDrive = sprintf('%s_%d.%s', $base, $existentes + $i + 1, $arquivo['ext']);

            try {
                $drive = $this->drive()->uploadFileToFolder($arquivo['path'], $nomeDrive, $arquivo['mime'], $pastaId);

                $registro = [
                    'tipo'         => $tipo,
                    'pagamento_id' => $pagamentoId,
                    'nome'         => $nomeDrive,
                    'mime_type'    => $arquivo['mime'],
                    'tamanho'      => $arquivo['tamanho'],
                    'drive_id'     => $drive['id'],
                    'drive_link'   => $drive['link'],
                    'pasta_mes'    => $anoMes,
                    'enviado_por'  => $userId,
                ];
                $registro['id_comprovante'] = $this->repository->create($registro);
                $enviados[] = $registro;
            } catch (Exception $e) {
                log_message('error', '[ComprovantesService::enviar] ' . $e->getMessage());
                $erros[] = ['arquivo' => $arquivo['nome'], 'mensagem' => $e->getMessage()];
            }
        }

        return ['enviados' => $enviados, 'erros' => $erros];
    }

    /**
     * Envia os mesmos arquivos como comprovante de vários pagamentos de uma vez.
     *
     * Tudo é validado antes do primeiro envio (arquivos, pagamentos e meses fechados). O arquivo sobe
     * uma única vez para cada mês dos pagamentos e os pagamentos do mês compartilham o mesmo arquivo.
     *
     * @param array<int,array{tipo:string,id:int}>      $pagamentos
     * @param array<int,array{path:string,nome:string}> $arquivos
     * @return array{enviados:array,erros:array,pagamentos:int}
     *
     * @throws InvalidArgumentException Lista de pagamentos ou arquivos inválida.
     * @throws RuntimeException         Pagamento inexistente.
     * @throws Exception                Mês fechado ou pasta do financeiro indisponível.
     */
    public function enviarLote(array $pagamentos, array $arquivos, ?int $userId = null): array
    {
        $selecionados = [];
        foreach ($pagamentos as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            $id   = (int) ($item['id'] ?? 0);
            if (!in_array($tipo, self::TIPOS, true) || $id <= 0) {
                throw new InvalidArgumentException('Lista de pagamentos inválida.');
            }
            $selecionados[$tipo . ':' . $id] = ['tipo' => $tipo, 'id' => $id];
        }

        if (!$selecionados) {
            throw new InvalidArgumentException('Selecione ao menos um pagamento.');
        }
        if (count($selecionados) > self::MAX_PAGAMENTOS_LOTE) {
            throw new InvalidArgumentException('Selecione no máximo ' . self::MAX_PAGAMENTOS_LOTE . ' pagamentos por vez.');
        }

        $validos = $this->validarArquivos($arquivos);

        // Valida todos os pagamentos antes de enviar qualquer arquivo.
        $porMes = [];
        foreach ($selecionados as $item) {
            $pagamento = $this->buscarPagamento($item['tipo'], $item['id']);
            $data      = $this->dataDoPagamento($item['tipo'], $pagamento);
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int) ($pagamento['conta_id'] ?? 0), $data);

            $porMes[date('Y-m', strtotime($data))][] = $item;
        }

        ksort($porMes);
        $enviados = [];
        $erros    = [];
        $carimbo  = date('Ymd-His');

        foreach ($porMes as $anoMes => $itens) {
            $pastaId = $this->drive()->pastaMensal($anoMes);

            foreach ($validos as $i => $arquivo) {
                $nomeDrive = sprintf('%s_lote-%s_%d.%s', $anoMes, $carimbo, $i + 1, $arquivo['ext']);

                try {
                    $drive = $this->drive()->uploadFileToFolder($arquivo['path'], $nomeDrive, $arquivo['mime'], $pastaId);
                } catch (Exception $e) {
                    log_message('error', '[ComprovantesService::enviarLote] ' . $e->getMessage());
                    $erros[] = ['arquivo' => $arquivo['nome'], 'mes' => $anoMes, 'mensagem' => $e->getMessage()];
                    continue;
                }

                foreach ($itens as $item) {
                    $registro = [
                        'tipo'         => $item['tipo'],
                        'pagamento_id' => $item['id'],
                        'nome'         => $nomeDrive,
                        'mime_type'    => $arquivo['mime'],
                        'tamanho'      => $arquivo['tamanho'],
                        'drive_id'     => $drive['id'],
                        'drive_link'   => $drive['link'],
                        'pasta_mes'    => $anoMes,
                        'enviado_por'  => $userId,
                    ];
                    $registro['id_comprovante'] = $this->repository->create($registro);
                    $enviados[] = $registro;
                }
            }
        }

        return ['enviados' => $enviados, 'erros' => $erros, 'pagamentos' => count($selecionados)];
    }

    /**
     * Resumo dos pagamentos do mês sem comprovante (informativo: não impede o fechamento).
     *
     * `do_extrato` indica quantas das pendências são pagamentos vinculados a movimentos do extrato bancário.
     *
     * @return array{ano:int,mes:int,total:int,do_extrato:int,despesas:int,receitas:int,valor_despesas:float,valor_receitas:float,contas:array}
     * @throws InvalidArgumentException Mês inválido.
     */
    public function pendenciasDoMes(int $ano, int $mes): array
    {
        if ($ano < 2000 || $ano > 2100 || $mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Informe ano e mês válidos.');
        }

        $resumo = [
            'ano' => $ano, 'mes' => $mes, 'total' => 0, 'do_extrato' => 0,
            'despesas' => 0, 'receitas' => 0, 'valor_despesas' => 0.0, 'valor_receitas' => 0.0,
            'contas' => [],
        ];

        foreach ($this->repository->pendenciasDoMes($ano, $mes) as $linha) {
            $plural = $linha['tipo'] === 'despesa' ? 'despesas' : 'receitas';

            $resumo['total']         += $linha['total'];
            $resumo['do_extrato']    += (int) ($linha['do_extrato'] ?? 0);
            $resumo[$plural]         += $linha['total'];
            $resumo['valor_' . $plural] += $linha['valor'];

            $id = $linha['conta_id'];
            $resumo['contas'][$id] ??= ['conta_id' => $id, 'conta' => $linha['conta'], 'despesas' => 0, 'receitas' => 0, 'do_extrato' => 0];
            $resumo['contas'][$id][$plural] += $linha['total'];
            $resumo['contas'][$id]['do_extrato'] += (int) ($linha['do_extrato'] ?? 0);
        }
        $resumo['contas'] = array_values($resumo['contas']);

        return $resumo;
    }

    /**
     * Acrescenta `total_comprovantes` às linhas do extrato que são pagamentos
     * (despesa/receita); transferências ficam como estão.
     *
     * @param array[] $transacoes Linhas com `tipo` e `id`.
     */
    public function anexarTotaisExtrato(array $transacoes): array
    {
        $totais = [];
        foreach (self::TIPOS as $tipo) {
            $ids = [];
            foreach ($transacoes as $t) {
                if (($t['tipo'] ?? '') === $tipo) {
                    $ids[] = $t['id'];
                }
            }
            $totais[$tipo] = $this->contarPorPagamentos($tipo, $ids);
        }

        foreach ($transacoes as &$t) {
            if (isset($totais[$t['tipo'] ?? ''])) {
                $t['total_comprovantes'] = $totais[$t['tipo']][(int) $t['id']] ?? 0;
            }
        }

        return $transacoes;
    }

    /**
     * Remove um comprovante: arquivo vai para a lixeira do Drive e o registro sai da lista (soft delete).
     * Se o Drive falhar, nada é removido e o erro é devolvido para nova tentativa.
     *
     * @throws RuntimeException Comprovante inexistente.
     * @throws Exception        Mês fechado ou falha ao mover o arquivo no Drive.
     */
    public function excluir(int $idComprovante): void
    {
        $comprovante = $this->repository->findById($idComprovante);
        if (!$comprovante) {
            throw new RuntimeException('Comprovante não encontrado.');
        }

        try {
            $pagamento = $this->buscarPagamento($comprovante['tipo'], (int) $comprovante['pagamento_id']);
            $this->saldoMensalService->verificarBloqueioMensal(
                'CONTA',
                (int) ($pagamento['conta_id'] ?? 0),
                $this->dataDoPagamento($comprovante['tipo'], $pagamento)
            );
        } catch (RuntimeException $e) {
            // Pagamento já estornado: não há mês a proteger, só o anexo a limpar.
        }

        // Arquivo de lote: só vai para a lixeira quando nenhum outro pagamento o usa.
        if ($this->repository->contarPorDriveId($comprovante['drive_id'], $idComprovante) === 0) {
            $this->drive()->trashFile($comprovante['drive_id']);
        }
        $this->repository->delete($idComprovante);
    }

    /**
     * Remove todos os comprovantes de pagamentos estornados ou excluídos.
     *
     * Nunca interrompe o estorno: se o Drive falhar, o registro é removido mesmo assim e o
     * arquivo órfão é registrado no log (com o `drive_id`, que também consta na auditoria).
     *
     * @param int[] $pagamentoIds
     * @return int Quantidade de comprovantes removidos.
     */
    public function removerPorPagamentos(string $tipo, array $pagamentoIds): int
    {
        $removidos = 0;

        foreach ($this->repository->listarPorPagamentos($tipo, $pagamentoIds) as $comprovante) {
            try {
                if ($this->repository->contarPorDriveId($comprovante['drive_id'], (int) $comprovante['id_comprovante']) === 0) {
                    $this->drive()->trashFile($comprovante['drive_id']);
                }
            } catch (Exception $e) {
                log_message('error', sprintf(
                    '[ComprovantesService::removerPorPagamentos] arquivo %s (comprovante %d, pagamento %s #%d) ficou no Drive: %s',
                    $comprovante['drive_id'],
                    $comprovante['id_comprovante'],
                    $tipo,
                    $comprovante['pagamento_id'],
                    $e->getMessage()
                ));
            }

            if ($this->repository->delete((int) $comprovante['id_comprovante'])) {
                $removidos++;
            }
        }

        return $removidos;
    }

    /**
     * Valida quantidade, tamanho e tipo real (MIME) dos arquivos, antes de qualquer envio.
     *
     * @param array<int,array{path:string,nome:string}> $arquivos
     * @return array<int,array{path:string,nome:string,mime:string,ext:string,tamanho:int}>
     * @throws InvalidArgumentException
     */
    public function validarArquivos(array $arquivos): array
    {
        $arquivos = array_values($arquivos);
        if (!$arquivos) {
            throw new InvalidArgumentException('Nenhum arquivo enviado.');
        }

        $maxArquivos = $this->limiteArquivos();
        if (count($arquivos) > $maxArquivos) {
            throw new InvalidArgumentException("Envie no máximo {$maxArquivos} arquivos por vez.");
        }

        $maxBytes = $this->limiteMb() * 1024 * 1024;
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $validos  = [];

        foreach ($arquivos as $arquivo) {
            $path = (string) ($arquivo['path'] ?? '');
            $nome = (string) ($arquivo['nome'] ?? basename($path));

            if ($path === '' || !is_file($path)) {
                throw new InvalidArgumentException("Arquivo \"{$nome}\" inválido ou não recebido.");
            }

            $tamanho = (int) filesize($path);
            if ($tamanho === 0) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" está vazio.");
            }
            if ($tamanho > $maxBytes) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" excede o limite de {$this->limiteMb()} MB.");
            }

            $mime = (string) $finfo->file($path);
            if (!isset(self::MIMES_PERMITIDOS[$mime])) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" não é permitido. Envie PDF, JPG, PNG ou WEBP.");
            }

            $validos[] = [
                'path'    => $path,
                'nome'    => $nome,
                'mime'    => $mime,
                'ext'     => self::MIMES_PERMITIDOS[$mime],
                'tamanho' => $tamanho,
            ];
        }

        return $validos;
    }

    /**
     * Nome padrão no Drive (sem sequência e extensão): AAAA-MM-DD_<conta>_<valor>_<id>.
     */
    protected function nomeBase(string $tipo, array $pagamento, string $data, int $pagamentoId): string
    {
        $conta = $this->contasRepository->findById((int) ($pagamento['conta_id'] ?? 0));
        $nomeConta = $this->slug((string) ($conta['conta'] ?? '')) ?: 'sem-conta';
        $valor = str_replace('.', '-', number_format((float) ($pagamento['valor'] ?? 0), 2, '.', ''));
        $prefixo = $tipo === 'despesa' ? 'D' : 'R';

        return sprintf('%s_%s_%s_%s%d', date('Y-m-d', strtotime($data)), $nomeConta, $valor, $prefixo, $pagamentoId);
    }

    private function slug(string $texto): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $slug  = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii === false ? $texto : $ascii), '-');

        return substr($slug, 0, 30);
    }

    /**
     * @throws RuntimeException Quando o tipo é inválido ou o pagamento não existe.
     */
    protected function buscarPagamento(string $tipo, int $pagamentoId): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de pagamento inválido.');
        }

        $repo = $tipo === 'despesa' ? $this->pagamentoDespesasRepository : $this->pagamentoReceitasRepository;
        $pagamento = $repo->findById($pagamentoId);
        if (!$pagamento) {
            throw new RuntimeException('Pagamento não encontrado.');
        }

        return $pagamento;
    }

    protected function dataDoPagamento(string $tipo, array $pagamento): string
    {
        $campo = $tipo === 'despesa' ? 'pagamento_despesa_dt' : 'pagamento_receita_dt';

        return (string) ($pagamento[$campo] ?? date('Y-m-d'));
    }

    private function limiteMb(): int
    {
        return $this->limiteConfigurado('COMPROVANTES_MAX_MB', self::MAX_MB_PADRAO);
    }

    private function limiteArquivos(): int
    {
        return $this->limiteConfigurado('COMPROVANTES_MAX_ARQUIVOS', self::MAX_ARQUIVOS_PADRAO);
    }

    private function limiteConfigurado(string $nome, int $padrao): int
    {
        $this->configuracoesService ??= new ConfiguracoesService();
        $valor = (int) $this->configuracoesService->getValor($nome, $padrao);

        return $valor > 0 ? $valor : $padrao;
    }
}
