<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Importacao\LeitorArquivoTabular;
use App\Libraries\Importacao\NormalizadorFontesProcesso as Fontes;
use App\Repositories\ProcessosImportacaoRepository;
use App\Traits\FormataNumeroProcessoTrait;
use InvalidArgumentException;
use Throwable;

/**
 * Importação de processos em lote para a carteira, com conferência.
 *
 * Fluxo:
 *  1. {@see ler()}: lê o arquivo (CSV ou JSON, até 50 processos). Só o número
 *     CNJ é obrigatório; os demais campos são opcionais.
 *  2. {@see conferir()}: para cada número, consulta o DJEN e o DataJud (só
 *     leitura) e, se o processo já estiver na carteira, os dados atuais. Monta
 *     campo a campo os valores de cada fonte, a sugestão e se há divergência,
 *     as partes de todas as fontes e sugestões de cliente.
 *  3. {@see confirmar()}: grava o que o usuário escolheu na tela. Processos
 *     novos são criados; os já cadastrados recebem só os campos alterados e
 *     as partes novas (nenhuma parte é removida).
 *
 * As intimações encontradas no DJEN não são importadas: a sincronização
 * normal (sync:djen) cuida das próximas.
 */
class ProcessosImportacaoService
{
    use FormataNumeroProcessoTrait;

    public const MAX_REGISTROS = 50;
    public const PESQUISA_MIN_CARACTERES = 4;
    public const TITULO_PADRAO = 'Processo sem título';
    public const RISCOS        = ['Provável', 'Possível', 'Remoto'];
    public const VISIBILIDADES = ['publico', 'restrito'];

    /** Campos aceitos no arquivo e os nomes de coluna/chave sinônimos. */
    public const COLUNAS = [
        'numero_processo'   => ['numeroprocesso', 'numero', 'processo', 'cnj', 'numero_cnj', 'numeroprocessocommascara'],
        'titulo_processo'   => ['titulo', 'titulo_do_processo'],
        'cliente_documento' => ['cpf_cnpj', 'documento_cliente', 'cpf', 'cnpj', 'documento'],
        'cliente_nome'      => ['cliente', 'nome_cliente'],
        'siglaTribunal'     => ['tribunal', 'sigla_tribunal', 'siglatribunal'],
        'nomeOrgao'         => ['orgao', 'vara', 'orgao_julgador', 'nomeorgao'],
        'tipoDocumento'     => ['classe', 'classe_processual', 'tipodocumento', 'tipo_documento'],
        'codigoClasse'      => ['codigo_classe', 'codigoclasse'],
        'dataDistribuicao'  => ['data_distribuicao', 'datadistribuicao', 'distribuicao', 'data_ajuizamento'],
        'valorCausa'        => ['valor_causa', 'valorcausa', 'valor_da_causa'],
        'risco'             => [],
        'pasta'             => [],
        'etiquetas'         => ['etiqueta', 'tags'],
        'responsavel'       => ['responsavel_id', 'advogado_responsavel', 'usuario_responsavel'],
        'visibilidade'      => [],
        'ramo_direito'      => ['ramo', 'area', 'ramo_do_direito'],
        'comentario'        => ['comentarios', 'observacao', 'observacoes', 'obs'],
        'poloAtivo'         => ['polo_ativo', 'autor', 'autores', 'requerente'],
        'poloPassivo'       => ['polo_passivo', 'reu', 'reus', 'requerido'],
    ];

    /**
     * Campos mostrados na conferência, na ordem da tela.
     * "oficial" indica que, sem valor na carteira, a sugestão prefere
     * DataJud e DJEN ao arquivo.
     */
    public const CAMPOS = [
        'titulo_processo'  => ['rotulo' => 'Título', 'tipo' => 'texto'],
        'cliente_id'       => ['rotulo' => 'Cliente', 'tipo' => 'cliente'],
        'contrato_id'      => ['rotulo' => 'Contrato', 'tipo' => 'contrato'],
        'siglaTribunal'    => ['rotulo' => 'Tribunal', 'tipo' => 'texto', 'oficial' => true],
        'nomeOrgao'        => ['rotulo' => 'Órgão', 'tipo' => 'texto', 'oficial' => true],
        'tipoDocumento'    => ['rotulo' => 'Classe', 'tipo' => 'texto', 'oficial' => true],
        'codigoClasse'     => ['rotulo' => 'Código da classe', 'tipo' => 'texto', 'oficial' => true],
        'dataDistribuicao' => ['rotulo' => 'Data de distribuição', 'tipo' => 'data', 'oficial' => true],
        'link'             => ['rotulo' => 'Link', 'tipo' => 'url', 'oficial' => true],
        'valorCausa'       => ['rotulo' => 'Valor da causa', 'tipo' => 'valor'],
        'risco'            => ['rotulo' => 'Risco', 'tipo' => 'risco'],
        'pasta'            => ['rotulo' => 'Pasta', 'tipo' => 'texto'],
        'ramo_direito'     => ['rotulo' => 'Ramo do direito', 'tipo' => 'ramo'],
        'responsavel_id'   => ['rotulo' => 'Responsável', 'tipo' => 'usuario'],
        'visibilidade'     => ['rotulo' => 'Visibilidade', 'tipo' => 'visibilidade'],
        'comentario'       => ['rotulo' => 'Comentário', 'tipo' => 'texto_longo'],
    ];

    /**
     * Campos que a importação não altera em processo já cadastrado: trocar o
     * responsável ou a visibilidade tem regras próprias na tela do processo.
     */
    public const BLOQUEADOS_EM_CADASTRADO = ['responsavel_id', 'visibilidade'];

    /** Ordem de preferência das fontes oficiais na sugestão. */
    private const ORDEM_OFICIAL = ['datajud', 'djen', 'arquivo'];

    /** Palavras ignoradas ao montar iniciais de um nome. */
    private const PREPOSICOES = ['de', 'da', 'do', 'das', 'dos', 'e', 'del', 'di'];

    private ?DjenService $djen = null;
    private DatajudService $datajud;
    private ProcessosImportacaoRepository $repository;
    private ?ProcessosService $processos = null;
    private ?EtiquetasService $etiquetas = null;

    public function __construct(
        ?DjenService $djen = null,
        ?DatajudService $datajud = null,
        ?ProcessosImportacaoRepository $repository = null,
        ?ProcessosService $processos = null,
        ?EtiquetasService $etiquetas = null
    ) {
        $this->djen       = $djen;
        $this->datajud    = $datajud ?? new DatajudService();
        $this->repository = $repository ?? new ProcessosImportacaoRepository();
        $this->processos  = $processos;
        $this->etiquetas  = $etiquetas;
    }

    // =========================================================================
    // 1. Leitura do arquivo
    // =========================================================================

    /**
     * Lê o arquivo e valida o número de cada linha. Não consulta nada externo
     * nem grava.
     *
     * @return array{formato: string, total: int, processos: array, erros: array}
     *
     * @throws InvalidArgumentException Arquivo vazio, malformado ou acima do limite.
     */
    public function ler(string $conteudo, string $nomeArquivo): array
    {
        $leitor = new LeitorArquivoTabular(self::COLUNAS, 'numero_processo');
        $lido   = $leitor->ler($conteudo, $nomeArquivo);

        $registros = $lido['registros'];
        if ($registros === []) {
            throw new InvalidArgumentException('O arquivo não contém processos para importar.');
        }
        if (count($registros) > self::MAX_REGISTROS) {
            throw new InvalidArgumentException(
                'O arquivo tem ' . count($registros) . ' processos; o limite por importação é ' . self::MAX_REGISTROS . '.'
            );
        }

        $processos = [];
        $erros     = [];
        $vistos    = [];

        foreach ($registros as $r) {
            $original = trim((string) ($r['dados']['numero_processo'] ?? ''));
            $numero   = $this->limparNumeroProcesso($original);

            $motivo = match (true) {
                $numero === ''                               => 'Número do processo não informado.',
                strlen($numero) !== 20                       => 'Número CNJ deve ter 20 dígitos (tem ' . strlen($numero) . ').',
                !$this->ehNumeroProcessoCnjValido($numero)   => 'Dígito verificador do número CNJ não confere.',
                isset($vistos[$numero])                      => 'Repetido no arquivo (linha ' . ($vistos[$numero] ?? '') . ').',
                default                                      => null,
            };

            if ($motivo !== null) {
                $erros[] = ['linha' => $r['linha'], 'numero_processo' => $original, 'motivo' => $motivo];
                continue;
            }

            $vistos[$numero] = $r['linha'];
            [$arquivo, $avisos] = $this->normalizarLinha($r['dados']);

            $processos[] = [
                'linha'                    => $r['linha'],
                'numero_processo'          => $numero,
                'numeroprocessocommascara' => $this->formatarNumeroProcesso($numero),
                'arquivo'                  => $arquivo,
                'avisos'                   => $avisos,
            ];
        }

        return [
            'formato'   => $lido['formato'],
            'total'     => count($registros),
            'processos' => $processos,
            'erros'     => $erros,
        ];
    }

    /**
     * Converte os valores de uma linha do arquivo. Valores que não puderam ser
     * entendidos são descartados com aviso (não impedem a importação).
     *
     * @return array{0: array, 1: string[]} Dados normalizados e avisos.
     */
    public function normalizarLinha(array $dados): array
    {
        $avisos = [];
        $texto  = static fn (string $campo): ?string => ($v = trim((string) ($dados[$campo] ?? ''))) === '' ? null : $v;
        $lista  = static fn (string $campo): array => array_values(array_filter(
            array_map('trim', explode(LeitorArquivoTabular::SEPARADOR_MULTIPLO, (string) ($dados[$campo] ?? ''))),
            'strlen'
        ));

        $arquivo = [
            'titulo_processo'   => $texto('titulo_processo'),
            'cliente_documento' => ($d = preg_replace('/\D/', '', (string) ($dados['cliente_documento'] ?? ''))) === '' ? null : $d,
            'cliente_nome'      => $texto('cliente_nome'),
            'siglaTribunal'     => ($t = $texto('siglaTribunal')) !== null ? mb_strtoupper($t) : null,
            'nomeOrgao'         => $texto('nomeOrgao'),
            'tipoDocumento'     => $texto('tipoDocumento'),
            'codigoClasse'      => $texto('codigoClasse'),
            'dataDistribuicao'  => null,
            'valorCausa'        => null,
            'risco'             => null,
            'pasta'             => $texto('pasta'),
            'etiquetas'         => $lista('etiquetas'),
            'responsavel'       => $texto('responsavel'),
            'visibilidade'      => null,
            'ramo_direito'      => $texto('ramo_direito'),
            'comentario'        => $texto('comentario'),
            'poloAtivo'         => $lista('poloAtivo'),
            'poloPassivo'       => $lista('poloPassivo'),
        ];

        if (($v = $texto('dataDistribuicao')) !== null) {
            $arquivo['dataDistribuicao'] = $this->converterData($v);
            if ($arquivo['dataDistribuicao'] === null) {
                $avisos[] = "Data de distribuição \"{$v}\" não reconhecida (use dd/mm/aaaa).";
            }
        }

        if (($v = $texto('valorCausa')) !== null) {
            $arquivo['valorCausa'] = $this->converterValor($v);
            if ($arquivo['valorCausa'] === null) {
                $avisos[] = "Valor da causa \"{$v}\" não reconhecido.";
            }
        }

        if (($v = $texto('risco')) !== null) {
            $arquivo['risco'] = $this->converterRisco($v);
            if ($arquivo['risco'] === null) {
                $avisos[] = "Risco \"{$v}\" inválido (use Provável, Possível ou Remoto).";
            }
        }

        if (($v = $texto('visibilidade')) !== null) {
            $chave = Fontes::comparavel($v);
            $arquivo['visibilidade'] = in_array($chave, self::VISIBILIDADES, true) ? $chave : null;
            if ($arquivo['visibilidade'] === null) {
                $avisos[] = "Visibilidade \"{$v}\" inválida (use publico ou restrito).";
            }
        }

        return [$arquivo, $avisos];
    }

    // =========================================================================
    // 1b. Pesquisa no DJEN pelo nome da parte
    // =========================================================================

    /**
     * Pesquisa no DJEN os processos em que aparece uma parte com o nome
     * informado. Não grava nada: devolve a lista para o usuário escolher quais
     * seguem para a conferência ({@see conferir()}).
     *
     * O DJEN encontra nomes que contêm o termo ("Maria Juraci da Silva" para
     * "Juraci da Silva"); cada processo informa se a correspondência é exata.
     *
     * @return array{status: string, mensagem: ?string, total_comunicacoes: int, truncado: bool,
     *               filtros: array, processos: array}
     *
     * @throws InvalidArgumentException Nome, período ou tribunal inválidos.
     */
    public function pesquisarPorParte(string $nome, ?string $dataInicio = null, ?string $dataFim = null, ?string $tribunal = null): array
    {
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        if (mb_strlen((string) preg_replace('/[^\p{L}\p{N}]/u', '', $nome)) < self::PESQUISA_MIN_CARACTERES) {
            throw new InvalidArgumentException('Informe o nome da parte com pelo menos ' . self::PESQUISA_MIN_CARACTERES . ' letras.');
        }
        if (mb_strlen($nome) > 150) {
            throw new InvalidArgumentException('O nome da parte pode ter no máximo 150 caracteres.');
        }

        $hoje   = date('Y-m-d');
        $inicio = $this->dataPesquisa($dataInicio, date('Y-m-d', strtotime('-1 year')), 'inicial');
        $fim    = $this->dataPesquisa($dataFim, $hoje, 'final');
        if ($fim > $hoje) {
            $fim = $hoje;
        }
        if ($inicio > $fim) {
            throw new InvalidArgumentException('A data inicial deve ser anterior à data final.');
        }

        $tribunal = strtoupper(trim((string) $tribunal));
        if ($tribunal !== '' && !preg_match('/^[A-Z0-9]{2,12}$/', $tribunal)) {
            throw new InvalidArgumentException('Tribunal inválido. Use a sigla, por exemplo TJSP ou TRT2.');
        }

        $resposta = $this->djen()->pesquisarPorNomeParte($nome, $inicio, $fim, $tribunal !== '' ? $tribunal : null);

        // A consulta ao DJEN pode passar do wait_timeout do MySQL.
        $this->repository->reabrirConexao();

        return $this->montarPesquisa($nome, $resposta) + [
            'filtros' => ['nome' => $nome, 'data_inicio' => $inicio, 'data_fim' => $fim, 'tribunal' => $tribunal !== '' ? $tribunal : null],
        ];
    }

    /**
     * Agrupa por processo as comunicações devolvidas pelo DJEN e marca a
     * situação de cada um na carteira.
     *
     * @param array $resposta Retorno de DjenService::pesquisarPorNomeParte().
     */
    public function montarPesquisa(string $nome, array $resposta): array
    {
        $porNumero = [];
        foreach ($resposta['itens'] ?? [] as $item) {
            $digitos = $this->limparNumeroProcesso((string) ($item['numero_processo'] ?? $item['numeroprocessocommascara'] ?? ''));
            if ($this->ehNumeroProcessoCnjValido($digitos)) {
                $porNumero[$digitos][] = $item;
            }
        }

        $mascaras = [];
        foreach (array_keys($porNumero) as $digitos) {
            $mascaras[(string) $digitos] = (string) $this->formatarNumeroProcesso((string) $digitos);
        }
        $naCarteira = $this->repository->situacaoNaCarteira(array_map('strval', array_keys($porNumero)), array_values($mascaras));

        $busca     = self::nomeParaPesquisa($nome);
        $processos = [];
        foreach ($porNumero as $digitos => $itens) {
            $digitos = (string) $digitos;
            $dados   = Fontes::deDjen($itens);

            $encontradas = [];
            $exata       = false;
            foreach ($itens as $item) {
                foreach ($item['destinatarios'] ?? [] as $d) {
                    $parte = trim((string) ($d['nome'] ?? ''));
                    $alvo  = self::nomeParaPesquisa($parte);
                    if ($parte === '' || $busca === '' || !str_contains($alvo, $busca)) {
                        continue;
                    }
                    $exata = $exata || $alvo === $busca;
                    $polo  = in_array($d['polo'] ?? null, ['A', 'P'], true) ? $d['polo'] : null;
                    $encontradas[$polo . '|' . $alvo] ??= ['nome' => $parte, 'polo' => $polo];
                }
            }

            $carteira = $naCarteira[$digitos] ?? null;
            $processos[] = [
                'numero_processo'          => $digitos,
                'numeroprocessocommascara' => $mascaras[$digitos],
                'siglaTribunal'            => $dados['siglaTribunal'],
                'nomeOrgao'                => $dados['nomeOrgao'],
                'classe'                   => $dados['tipoDocumento'],
                'poloAtivo'                => $dados['poloAtivo'],
                'poloPassivo'              => $dados['poloPassivo'],
                'partes_encontradas'       => array_values($encontradas),
                'correspondencia'          => $exata ? 'exata' : ($encontradas !== [] ? 'parcial' : 'nenhuma'),
                'total_publicacoes'        => $dados['total_publicacoes'],
                'ultima_publicacao'        => $dados['ultima_publicacao'],
                'situacao'                 => $carteira === null ? 'novo' : ($carteira['deleted_at'] !== null ? 'excluido' : 'cadastrado'),
            ];
        }

        $ordem = ['exata' => 0, 'parcial' => 1, 'nenhuma' => 2];
        usort($processos, static fn (array $a, array $b): int =>
            [$ordem[$a['correspondencia']], $b['ultima_publicacao'] ?? ''] <=> [$ordem[$b['correspondencia']], $a['ultima_publicacao'] ?? '']);

        $truncado = (bool) ($resposta['truncado'] ?? false);
        $mensagem = $resposta['mensagem'] ?? null;
        if ($truncado && $mensagem === null) {
            $mensagem = 'O DJEN encontrou mais publicações do que é possível ler de uma vez. '
                . 'Reduza o período ou informe o tribunal para ver todos os processos.';
        }

        return [
            'status'             => $resposta['status'] ?? 'erro',
            'mensagem'           => $mensagem,
            'total_comunicacoes' => (int) ($resposta['total'] ?? 0),
            'truncado'           => $truncado,
            'processos'          => $processos,
        ];
    }

    /** Nome sem acento, caixa ou pontuação, para comparar com a parte pesquisada. */
    private static function nomeParaPesquisa(string $nome): string
    {
        $texto = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', Fontes::comparavel($nome));

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    private function dataPesquisa(?string $valor, string $padrao, string $qual): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return $padrao;
        }
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($data === false || $data->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException("Data {$qual} inválida.");
        }

        return $valor;
    }

    // =========================================================================
    // 2. Conferência
    // =========================================================================

    /**
     * Consulta as fontes externas de um processo e monta a conferência.
     *
     * @param string $numero  Número CNJ (com ou sem máscara).
     * @param array  $arquivo Dados da linha do arquivo, como devolvidos por {@see ler()}.
     *
     * @throws InvalidArgumentException Número CNJ inválido.
     */
    public function conferir(string $numero, array $arquivo = []): array
    {
        $digitos = $this->limparNumeroProcesso($numero);
        if (!$this->ehNumeroProcessoCnjValido($digitos)) {
            throw new InvalidArgumentException('Número CNJ inválido.');
        }

        $djen    = $this->djen()->consultarProcesso($digitos);
        $datajud = $this->datajud->consultarProcesso($digitos);

        // As consultas externas podem passar do wait_timeout do MySQL.
        $this->repository->reabrirConexao();

        $carteira = $this->repository->processoPorNumero($digitos, (string) $this->formatarNumeroProcesso($digitos));
        if ($carteira !== null && empty($carteira['deleted_at'])) {
            $carteira['partes']    = $this->repository->partesDoProcesso((int) $carteira['id_processo']);
            $carteira['etiquetas'] = $this->repository->etiquetasDoProcesso((int) $carteira['id_processo']);
        }

        return $this->montarConferencia($digitos, $arquivo, $djen, $datajud, $carteira);
    }

    /**
     * Monta a conferência a partir dos dados já obtidos de cada fonte.
     *
     * @param array      $arquivo  Dados normalizados da linha do arquivo.
     * @param array      $djen     Retorno de DjenService::consultarProcesso().
     * @param array      $datajud  Retorno de DatajudService::consultarProcesso().
     * @param array|null $carteira Processo já cadastrado (com "partes" e "etiquetas"), ou null.
     */
    public function montarConferencia(string $digitos, array $arquivo, array $djen, array $datajud, ?array $carteira): array
    {
        $mascara  = (string) $this->formatarNumeroProcesso($digitos);
        $excluido = $carteira !== null && !empty($carteira['deleted_at']);
        $avisos   = [];

        $resultado = [
            'numero_processo'          => $digitos,
            'numeroprocessocommascara' => $mascara,
            'situacao'                 => $excluido ? 'excluido' : ($carteira ? 'cadastrado' : 'novo'),
            'id_processo'              => $carteira ? (int) $carteira['id_processo'] : null,
            'importavel'               => !$excluido,
            'fontes'                   => [
                'djen'    => ['status' => $djen['status'] ?? 'erro', 'mensagem' => $djen['mensagem'] ?? null],
                'datajud' => ['status' => $datajud['status'] ?? 'erro', 'mensagem' => $datajud['mensagem'] ?? null],
            ],
        ];

        if ($excluido) {
            $resultado['avisos'] = ['Este processo foi excluído da carteira. Restaure-o antes de importar.'];
            $resultado['campos'] = [];
            $resultado['partes'] = [];
            $resultado['clientes_sugeridos'] = [];
            $resultado['etiquetas'] = ['arquivo' => $arquivo['etiquetas'] ?? [], 'carteira' => []];
            $resultado['sigilo'] = false;
            $resultado['informacoes'] = [];

            return $resultado;
        }

        $dadosDjen    = $djen['dados'] ?? [];
        $dadosDatajud = $datajud['dados'] ?? [];

        // Resolve no cadastro os nomes vindos do arquivo.
        $clienteArquivo = $this->resolverCliente($arquivo, $avisos);
        $responsavel    = $this->resolverPorNome($arquivo['responsavel'] ?? null, $this->repository->usuarios(), 'id', 'username');
        if (!empty($arquivo['responsavel']) && $responsavel === null) {
            $avisos[] = "Responsável \"{$arquivo['responsavel']}\" não encontrado entre os usuários.";
        }
        $ramo = $this->resolverPorNome($arquivo['ramo_direito'] ?? null, $this->repository->ramos(), 'id', 'nome');
        if (!empty($arquivo['ramo_direito']) && $ramo === null) {
            $avisos[] = "Ramo do direito \"{$arquivo['ramo_direito']}\" não encontrado.";
        }

        $partes  = $this->juntarPartes($arquivo, $dadosDjen, $carteira['partes'] ?? null);
        $sigilo  = (bool) ($dadosDatajud['sigilo'] ?? false) || $this->partesSoComIniciais($dadosDjen);
        if ($sigilo) {
            $avisos[] = 'Processo com sigilo: os nomes das partes podem estar só com as iniciais.';
        }

        $sugeridos = $this->sugerirClientes($partes, $arquivo['cliente_nome'] ?? null, $clienteArquivo['id'] ?? null);

        $campos = [];
        foreach (self::CAMPOS as $campo => $def) {
            $opcoes = match ($campo) {
                'titulo_processo' => $this->opcoes(['arquivo' => $arquivo['titulo_processo'] ?? null], $carteira, $campo),
                'cliente_id'      => $this->opcoes(
                    ['arquivo' => $clienteArquivo['id'] ?? null],
                    $carteira,
                    $campo,
                    ['arquivo' => $clienteArquivo['nome'] ?? null, 'carteira' => $carteira['cliente_nome'] ?? null]
                ),
                'contrato_id'     => $this->opcoes([], $carteira, $campo, [
                    'carteira' => self::rotuloContrato($carteira['contrato_id'] ?? null, $carteira['contrato_numero'] ?? null),
                ]),
                'responsavel_id'  => $this->opcoes(
                    ['arquivo' => $responsavel['id'] ?? null],
                    $carteira,
                    $campo,
                    ['arquivo' => $responsavel['rotulo'] ?? null, 'carteira' => $carteira['responsavel_nome'] ?? null]
                ),
                'ramo_direito'    => $this->opcoes(
                    ['arquivo' => $ramo['id'] ?? null],
                    $carteira,
                    $campo,
                    ['arquivo' => $ramo['rotulo'] ?? null, 'carteira' => $carteira['ramo_direito_nome'] ?? null]
                ),
                default => $this->opcoes([
                    'arquivo' => $arquivo[$campo] ?? null,
                    'djen'    => $dadosDjen[$campo] ?? null,
                    'datajud' => $dadosDatajud[$campo] ?? null,
                ], $carteira, $campo),
            };

            $bloqueado = $carteira !== null && in_array($campo, self::BLOQUEADOS_EM_CADASTRADO, true);

            $campos[] = [
                'bloqueado'  => $bloqueado,
                'campo'      => $campo,
                'rotulo'     => $def['rotulo'],
                'tipo'       => $def['tipo'],
                'opcoes'     => $opcoes,
                'divergente' => $this->divergente($opcoes),
                'atual'      => $carteira !== null ? $this->valorCarteira($carteira, $campo) : null,
                'sugestao'   => $bloqueado
                    ? $this->valorCarteira($carteira, $campo)
                    : $this->sugestao($opcoes, $carteira !== null, !empty($def['oficial'])),
            ];
        }

        $campos = $this->completarSugestoes($campos, $sugeridos, $carteira !== null);

        $resultado['avisos']             = $avisos;
        $resultado['campos']             = $campos;
        $resultado['partes']             = $partes;
        $resultado['clientes_sugeridos'] = $sugeridos;
        $resultado['etiquetas']          = [
            'arquivo'  => $arquivo['etiquetas'] ?? [],
            'carteira' => $carteira['etiquetas'] ?? [],
        ];
        $resultado['sigilo']      = $sigilo;
        $resultado['informacoes'] = array_filter([
            'assuntos'          => $dadosDatajud['assuntos'] ?? [],
            'graus'             => $dadosDatajud['graus'] ?? [],
            'sistema'           => $dadosDatajud['sistema'] ?? null,
            'total_publicacoes' => $dadosDjen['total_publicacoes'] ?? null,
            'ultima_publicacao' => $dadosDjen['ultima_publicacao'] ?? null,
        ], static fn ($v) => $v !== null && $v !== []);

        return $resultado;
    }

    /** Texto exibido para um contrato: "Contrato #número" (ou o ID, se não houver número). */
    public static function rotuloContrato(mixed $id, ?string $numero): ?string
    {
        if (empty($id)) {
            return null;
        }
        $numero = trim((string) $numero);

        return 'Contrato #' . ($numero !== '' ? $numero : $id);
    }

    /**
     * Título padrão: "Cliente - Classe" quando os dois existem; senão o
     * placeholder.
     */
    public static function tituloPadrao(?string $cliente, ?string $classe): string
    {
        $cliente = trim((string) $cliente);
        $classe  = trim((string) $classe);

        return $cliente !== '' && $classe !== '' ? "{$cliente} - {$classe}" : self::TITULO_PADRAO;
    }

    /**
     * Opções de valor de um campo, uma por fonte que o tenha.
     *
     * @param array<string, mixed>       $porFonte Fonte => valor.
     * @param array<string, string|null> $rotulos  Fonte => texto exibido (para campos com ID).
     * @return array<int, array{fonte: string, valor: mixed, rotulo: string}>
     */
    private function opcoes(array $porFonte, ?array $carteira, string $campo, array $rotulos = []): array
    {
        if ($carteira !== null) {
            $porFonte['carteira'] = $this->valorCarteira($carteira, $campo);
        }

        $opcoes = [];
        foreach ($porFonte as $fonte => $valor) {
            if ($valor === null || $valor === '' || ($campo === 'titulo_processo' && $valor === self::TITULO_PADRAO && $fonte !== 'carteira')) {
                continue;
            }
            $opcoes[] = [
                'fonte'  => $fonte,
                'valor'  => is_int($valor) ? $valor : (string) $valor,
                'rotulo' => (string) ($rotulos[$fonte] ?? $valor),
            ];
        }

        return $opcoes;
    }

    private function valorCarteira(array $carteira, string $campo): mixed
    {
        $valor = $carteira[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($campo) {
            'cliente_id', 'contrato_id', 'responsavel_id', 'ramo_direito' => (int) $valor ?: null,
            'dataDistribuicao' => substr((string) $valor, 0, 10) === '0000-00-00' ? null : substr((string) $valor, 0, 10),
            'valorCausa'       => $this->converterValor((string) $valor),
            default            => (string) $valor,
        };
    }

    /** Há divergência quando as fontes trazem mais de um valor diferente. */
    private function divergente(array $opcoes): bool
    {
        $distintos = [];
        foreach ($opcoes as $o) {
            $distintos[$this->chaveComparacao($o['valor'])] = true;
        }

        return count($distintos) > 1;
    }

    private function chaveComparacao(mixed $valor): string
    {
        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }
        $texto = (string) $valor;
        if (is_numeric($texto)) {
            return (string) (float) $texto;
        }

        return Fontes::comparavel($texto);
    }

    /**
     * Sugestão inicial: o valor atual da carteira, se houver; nos campos
     * oficiais, DataJud > DJEN > arquivo; nos demais, o arquivo.
     */
    private function sugestao(array $opcoes, bool $cadastrado, bool $oficial): mixed
    {
        $porFonte = array_column($opcoes, 'valor', 'fonte');

        if ($cadastrado && array_key_exists('carteira', $porFonte)) {
            return $porFonte['carteira'];
        }

        foreach ($oficial ? self::ORDEM_OFICIAL : ['arquivo', 'datajud', 'djen'] as $fonte) {
            if (array_key_exists($fonte, $porFonte)) {
                return $porFonte[$fonte];
            }
        }

        return null;
    }

    /**
     * Preenche a sugestão de cliente (com a sugestão de confiança alta, se não
     * houver outra) e gera o título padrão quando nenhum título foi informado.
     */
    private function completarSugestoes(array $campos, array $sugeridos, bool $cadastrado): array
    {
        $indice = array_flip(array_column($campos, 'campo'));
        $cli    = &$campos[$indice['cliente_id']];

        $nomeCliente = null;
        if ($cli['sugestao'] === null && ($sugeridos[0]['confianca'] ?? null) === 'alta') {
            $cli['sugestao'] = $sugeridos[0]['id_cliente'];
            $nomeCliente     = $sugeridos[0]['nome'];
        } elseif ($cli['sugestao'] !== null) {
            foreach ($cli['opcoes'] as $o) {
                if ($o['valor'] === $cli['sugestao']) {
                    $nomeCliente = $o['rotulo'];
                    break;
                }
            }
        }
        unset($cli);

        $tit = &$campos[$indice['titulo_processo']];
        if ($tit['sugestao'] === null || ($cadastrado && $tit['sugestao'] === self::TITULO_PADRAO)) {
            $classe = $this->classeParaTitulo($campos[$indice['tipoDocumento']]['opcoes']);
            $gerado = self::tituloPadrao($nomeCliente, $classe);
            if (!$cadastrado || $gerado !== self::TITULO_PADRAO) {
                $tit['sugestao'] = $gerado;
                $tit['gerado']   = true;
            }
        }
        $tit['gerado'] ??= false;
        unset($tit);

        return $campos;
    }

    /** Classe usada no título: prefere o DataJud (que vem com caixa normal). */
    private function classeParaTitulo(array $opcoes): ?string
    {
        $porFonte = array_column($opcoes, 'valor', 'fonte');
        foreach (['datajud', 'arquivo', 'djen', 'carteira'] as $fonte) {
            if (!empty($porFonte[$fonte])) {
                return (string) $porFonte[$fonte];
            }
        }

        return null;
    }

    /**
     * Partes de todas as fontes, sem repetição (mesmo polo e mesmo nome
     * comparável), indicando de onde vieram e se já estão na carteira.
     */
    private function juntarPartes(array $arquivo, array $djen, ?array $partesCarteira): array
    {
        $partes = [];
        $add = function (string $nome, string $polo, string $fonte) use (&$partes): void {
            $nome = trim($nome);
            if ($nome === '') {
                return;
            }
            $chave = $polo . '|' . Fontes::comparavel($nome);
            if (!isset($partes[$chave])) {
                $partes[$chave] = ['nome' => $nome, 'polo' => $polo, 'fontes' => [], 'na_carteira' => false];
            }
            if ($fonte === 'carteira') {
                $partes[$chave]['na_carteira'] = true;
                $partes[$chave]['nome']        = $nome; // mantém a grafia já cadastrada
            } elseif (!in_array($fonte, $partes[$chave]['fontes'], true)) {
                $partes[$chave]['fontes'][] = $fonte;
            }
        };

        foreach ($partesCarteira ?? [] as $p) {
            $add($p['nome'], $p['polo'], 'carteira');
        }
        foreach (['poloAtivo' => 'A', 'poloPassivo' => 'P'] as $chave => $polo) {
            foreach ($arquivo[$chave] ?? [] as $nome) {
                $add((string) $nome, $polo, 'arquivo');
            }
            foreach ($djen[$chave] ?? [] as $nome) {
                $add((string) $nome, $polo, 'djen');
            }
        }

        return array_values(array_map(static function (array $p): array {
            $p['incluir'] = !$p['na_carteira'];

            return $p;
        }, $partes));
    }

    /** O DJEN trouxe partes e todas parecem iniciais ("J. S. S.", "JSS"). */
    private function partesSoComIniciais(array $djen): bool
    {
        $nomes = array_merge($djen['poloAtivo'] ?? [], $djen['poloPassivo'] ?? []);
        if ($nomes === []) {
            return false;
        }

        foreach ($nomes as $nome) {
            if (!$this->ehIniciais((string) $nome)) {
                return false;
            }
        }

        return true;
    }

    private function ehIniciais(string $nome): bool
    {
        $nome = trim($nome);

        return $nome !== '' && (bool) preg_match('/^(\p{Lu}\.?\s*){1,8}$/u', $nome) && mb_strlen((string) preg_replace('/[^\p{L}]/u', '', $nome)) <= 8;
    }

    /** Letras das iniciais (minúsculas, sem acento): "J. S. Silva" não; "J.S.S." → "jss". */
    private function letrasIniciais(string $nome): string
    {
        return Fontes::comparavel((string) preg_replace('/[^\p{L}]/u', '', $nome));
    }

    /** Iniciais de um nome completo, sem preposições: "João da Silva Souza" → "jss". */
    private function iniciaisDoNome(string $nome): string
    {
        $palavras = preg_split('/\s+/', Fontes::comparavel($nome)) ?: [];
        $iniciais = '';
        foreach ($palavras as $p) {
            $p = (string) preg_replace('/[^a-z0-9]/', '', $p);
            if ($p !== '' && !in_array($p, self::PREPOSICOES, true)) {
                $iniciais .= $p[0];
            }
        }

        return $iniciais;
    }

    /**
     * Sugere clientes cadastrados comparando-os com as partes e com o nome do
     * cliente do arquivo (quando ele não foi achado de forma exata).
     *
     * Confiança: "alta" (nome igual, sem considerar acento e caixa), "media"
     * (um nome contém o outro) ou "baixa" (iniciais iguais, em processos com
     * sigilo). Nunca é obrigatória.
     *
     * @return array<int, array{id_cliente: int, nome: string, confianca: string, motivo: string}>
     */
    public function sugerirClientes(array $partes, ?string $clienteNomeArquivo = null, ?int $clienteArquivoId = null): array
    {
        $alvos = [];
        foreach ($partes as $p) {
            $alvos[] = ['nome' => $p['nome'], 'motivo' => 'Parte do polo ' . ($p['polo'] === 'A' ? 'ativo' : 'passivo')];
        }
        if ($clienteNomeArquivo !== null && $clienteArquivoId === null) {
            $alvos[] = ['nome' => $clienteNomeArquivo, 'motivo' => 'Cliente informado no arquivo'];
        }
        if ($alvos === []) {
            return [];
        }

        $peso      = ['alta' => 3, 'media' => 2, 'baixa' => 1];
        $sugestoes = [];

        foreach ($this->repository->clientes() as $cliente) {
            if ($cliente['id_cliente'] === $clienteArquivoId) {
                continue;
            }

            $nomesCliente = array_filter([$cliente['nome'], $cliente['razao_social'] ?? null]);
            foreach ($alvos as $alvo) {
                $confianca = null;

                foreach ($nomesCliente as $nomeCliente) {
                    $confianca = $this->compararNomes($alvo['nome'], (string) $nomeCliente);
                    if ($confianca !== null) {
                        break;
                    }
                }

                if ($confianca === null) {
                    continue;
                }

                $id = $cliente['id_cliente'];
                if (!isset($sugestoes[$id]) || $peso[$confianca] > $peso[$sugestoes[$id]['confianca']]) {
                    $sugestoes[$id] = [
                        'id_cliente' => $id,
                        'nome'       => $cliente['nome'],
                        'documento'  => $cliente['documento'] ?? null,
                        'confianca'  => $confianca,
                        'motivo'     => $alvo['motivo'] . ': ' . $alvo['nome'],
                    ];
                }
            }
        }

        $sugestoes = array_values($sugestoes);
        usort($sugestoes, static fn (array $a, array $b): int => [$peso[$b['confianca']], $a['nome']] <=> [$peso[$a['confianca']], $b['nome']]);

        // Iniciais casam com muitos nomes; acima de 5 a sugestão não ajuda.
        $baixas = array_filter($sugestoes, static fn (array $s): bool => $s['confianca'] === 'baixa');
        if (count($baixas) > 5) {
            $sugestoes = array_values(array_filter($sugestoes, static fn (array $s): bool => $s['confianca'] !== 'baixa'));
        }

        return array_slice($sugestoes, 0, 5);
    }

    private function compararNomes(string $parte, string $cliente): ?string
    {
        $a = Fontes::comparavel($parte);
        $b = Fontes::comparavel($cliente);
        if ($a === '' || $b === '') {
            return null;
        }

        if ($a === $b) {
            return 'alta';
        }

        if ($this->ehIniciais($parte)) {
            $letras = $this->letrasIniciais($parte);

            return strlen($letras) >= 2 && $letras === $this->iniciaisDoNome($cliente) ? 'baixa' : null;
        }

        $curto = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longo = $curto === $a ? $b : $a;
        if (mb_strlen($curto) >= 10 && preg_match('/(^|\s)' . preg_quote($curto, '/') . '(\s|$)/u', $longo)) {
            return 'media';
        }

        return null;
    }

    /**
     * Cliente indicado no arquivo: pelo CPF/CNPJ (prioridade) ou pelo nome
     * exato. Não cria cliente novo.
     *
     * @return array{id: int, nome: string}|null
     */
    private function resolverCliente(array $arquivo, array &$avisos): ?array
    {
        $documento = $arquivo['cliente_documento'] ?? null;
        $nome      = $arquivo['cliente_nome'] ?? null;

        foreach ($this->repository->clientes() as $c) {
            if ($documento !== null && preg_replace('/\D/', '', (string) $c['documento']) === $documento) {
                return ['id' => $c['id_cliente'], 'nome' => $c['nome']];
            }
        }
        if ($documento !== null) {
            $avisos[] = "Cliente com CPF/CNPJ {$documento} não está cadastrado.";
        }

        if ($nome !== null) {
            $alvo = Fontes::comparavel($nome);
            foreach ($this->repository->clientes() as $c) {
                if (Fontes::comparavel($c['nome']) === $alvo || Fontes::comparavel((string) $c['razao_social']) === $alvo) {
                    return ['id' => $c['id_cliente'], 'nome' => $c['nome']];
                }
            }
            $avisos[] = "Cliente \"{$nome}\" não está cadastrado. Veja as sugestões ou escolha outro.";
        }

        return null;
    }

    /**
     * Acha um registro pelo nome (sem acento e caixa) ou pelo ID numérico.
     *
     * @return array{id: int, rotulo: string}|null
     */
    private function resolverPorNome(?string $valor, array $lista, string $chaveId, string $chaveNome): ?array
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $alvo = Fontes::comparavel($valor);
        foreach ($lista as $item) {
            if ((ctype_digit($valor) && (int) $valor === (int) $item[$chaveId]) || Fontes::comparavel((string) $item[$chaveNome]) === $alvo) {
                return ['id' => (int) $item[$chaveId], 'rotulo' => (string) $item[$chaveNome]];
            }
        }

        return null;
    }

    /**
     * Converte uma conferência no item que {@see confirmar()} grava, aceitando
     * as sugestões sem a tela de conferência (usado pelo MCP).
     *
     * - Campos: a sugestão de cada um; quando o valor foi informado (fonte
     *   "arquivo"), ele prevalece. Em processo cadastrado vão só os campos que
     *   mudam, nenhum é apagado e os bloqueados ficam de fora.
     * - Partes: as que ainda não estão na carteira, de todas as fontes.
     * - Etiquetas: as informadas.
     *
     * @param int|null $clienteId Cliente escolhido (substitui a sugestão e refaz o título gerado).
     *
     * @throws InvalidArgumentException Processo excluído (não importável).
     */
    public function itemParaConfirmar(array $conferencia, ?int $clienteId = null): array
    {
        if (empty($conferencia['importavel'])) {
            throw new InvalidArgumentException($conferencia['avisos'][0] ?? 'Este processo não pode ser importado.');
        }

        $cadastrado = $conferencia['id_processo'] !== null;
        $porCampo   = array_column($conferencia['campos'] ?? [], null, 'campo');
        $escolhido  = [];

        foreach ($porCampo as $campo => $c) {
            if (!empty($c['bloqueado'])) {
                continue;
            }
            $informado = array_column($c['opcoes'], 'valor', 'fonte')['arquivo'] ?? null;
            $escolhido[$campo] = $informado ?? $c['sugestao'];
        }

        if ($clienteId !== null) {
            $escolhido['cliente_id'] = $clienteId;

            $titulo = $porCampo['titulo_processo'] ?? null;
            $tituloInformado = $titulo !== null && in_array('arquivo', array_column($titulo['opcoes'], 'fonte'), true);
            if ($titulo !== null && !$tituloInformado && (!$cadastrado || !empty($titulo['gerado']))) {
                $gerado = self::tituloPadrao($this->nomeCliente($clienteId), $this->classeParaTitulo($porCampo['tipoDocumento']['opcoes'] ?? []));
                if (!$cadastrado || $gerado !== self::TITULO_PADRAO) {
                    $escolhido['titulo_processo'] = $gerado;
                }
            }
        }

        $campos = [];
        foreach ($escolhido as $campo => $valor) {
            if ($valor === null || $valor === '') {
                continue; // não apaga nada
            }
            $atual = $porCampo[$campo]['atual'] ?? null;
            if ($cadastrado && $atual !== null && $this->chaveComparacao($atual) === $this->chaveComparacao($valor)) {
                continue;
            }
            $campos[$campo] = $valor;
        }

        $partes = [];
        foreach ($conferencia['partes'] ?? [] as $p) {
            if (!empty($p['incluir'])) {
                $partes[] = ['nome' => $p['nome'], 'polo' => $p['polo']];
            }
        }

        return [
            'numero_processo' => $conferencia['numero_processo'],
            'id_processo'     => $conferencia['id_processo'],
            'campos'          => $campos,
            'partes'          => $partes,
            'etiquetas'       => $conferencia['etiquetas']['arquivo'] ?? [],
        ];
    }

    // =========================================================================
    // 3. Gravação
    // =========================================================================

    /**
     * Grava os processos confirmados na tela de conferência.
     *
     * Cada item: numero_processo, id_processo (null para novo), campos
     * (campo => valor escolhido; nos já cadastrados, só os alterados),
     * partes ([{nome, polo}] a incluir) e etiquetas (nomes a incluir).
     *
     * @param array         $itens       Processos confirmados.
     * @param callable|null $podeEditar  fn(int $idProcesso): bool — permissão de editar o processo existente.
     * @param callable|null $responsavelValido fn(int $userId): bool — o usuário pode ser responsável (advogado ativo).
     * @return array{criados: int, atualizados: int, falhas: int, resultados: array}
     *
     * @throws InvalidArgumentException Lista vazia ou acima do limite.
     */
    public function confirmar(array $itens, ?callable $podeEditar = null, ?callable $responsavelValido = null): array
    {
        if ($itens === []) {
            throw new InvalidArgumentException('Nenhum processo selecionado para importar.');
        }
        if (count($itens) > self::MAX_REGISTROS) {
            throw new InvalidArgumentException('O limite por importação é ' . self::MAX_REGISTROS . ' processos.');
        }

        $resultados = [];
        $contagem   = ['criado' => 0, 'atualizado' => 0, 'sem_alteracao' => 0, 'erro' => 0];

        foreach ($itens as $item) {
            $numero = $this->limparNumeroProcesso((string) ($item['numero_processo'] ?? ''));

            try {
                $r = $this->gravarItem($numero, is_array($item) ? $item : [], $podeEditar, $responsavelValido);
            } catch (InvalidArgumentException $e) {
                $r = ['status' => 'erro', 'mensagem' => $e->getMessage()];
            } catch (Throwable $e) {
                log_message('error', "Importação de processos: falha ao gravar {$numero}: " . $e->getMessage());
                $r = ['status' => 'erro', 'mensagem' => 'Erro interno ao gravar o processo.'];
            }

            $contagem[$r['status']]++;
            $resultados[] = [
                'numero_processo'          => $numero,
                'numeroprocessocommascara' => $this->formatarNumeroProcesso($numero),
            ] + $r + ['id_processo' => null, 'mensagem' => null];
        }

        return [
            'criados'       => $contagem['criado'],
            'atualizados'   => $contagem['atualizado'],
            'sem_alteracao' => $contagem['sem_alteracao'],
            'falhas'        => $contagem['erro'],
            'resultados'    => $resultados,
        ];
    }

    /**
     * @return array{status: string, id_processo?: int, mensagem?: string}
     */
    private function gravarItem(string $numero, array $item, ?callable $podeEditar, ?callable $responsavelValido): array
    {
        if (!$this->ehNumeroProcessoCnjValido($numero)) {
            throw new InvalidArgumentException('Número CNJ inválido.');
        }

        $mascara   = (string) $this->formatarNumeroProcesso($numero);
        $existente = $this->repository->processoPorNumero($numero, $mascara);
        $idInformado = !empty($item['id_processo']) ? (int) $item['id_processo'] : null;

        if ($existente !== null && !empty($existente['deleted_at'])) {
            throw new InvalidArgumentException('O processo foi excluído da carteira. Restaure-o antes de importar.');
        }

        $campos    = $this->sanitizarCampos(is_array($item['campos'] ?? null) ? $item['campos'] : []);
        $partes    = $this->sanitizarPartes(is_array($item['partes'] ?? null) ? $item['partes'] : []);
        $etiquetas = array_values(array_unique(array_filter(array_map(
            static fn ($e) => mb_substr(trim((string) $e), 0, 100),
            is_array($item['etiquetas'] ?? null) ? $item['etiquetas'] : []
        ), 'strlen')));

        // Processo novo.
        if ($idInformado === null) {
            if (!empty($campos['responsavel_id']) && $responsavelValido !== null && !$responsavelValido((int) $campos['responsavel_id'])) {
                throw new InvalidArgumentException('Responsável: selecione um advogado ativo.');
            }

            if ($existente !== null) {
                throw new InvalidArgumentException('O processo foi cadastrado na carteira depois da conferência. Confira-o de novo.');
            }

            $dados = $campos + [
                'numero_processo'          => $numero,
                'numeroprocessocommascara' => $mascara,
                'status'                   => 'P',
                'ativo'                    => 1,
                'resultado'                => 'Não Finalizado',
            ];
            $dados['numero_processo']          = $numero;
            $dados['numeroprocessocommascara'] = $mascara;
            $dados['risco']                  ??= 'Possível';
            if (empty($dados['titulo_processo'])) {
                $dados['titulo_processo'] = self::tituloPadrao($this->nomeCliente($dados['cliente_id'] ?? null), $dados['tipoDocumento'] ?? null);
            }

            $poloAtivo   = array_values(array_map(static fn ($p) => ['nome' => $p['nome']], array_filter($partes, static fn ($p) => $p['polo'] === 'A')));
            $poloPassivo = array_values(array_map(static fn ($p) => ['nome' => $p['nome']], array_filter($partes, static fn ($p) => $p['polo'] === 'P')));

            $id = $this->processos()->salvarProcesso($dados, null, $poloAtivo, $poloPassivo);
            $this->vincularEtiquetas($id, $etiquetas);

            return ['status' => 'criado', 'id_processo' => $id];
        }

        // Processo já cadastrado.
        if ($existente === null || (int) $existente['id_processo'] !== $idInformado) {
            throw new InvalidArgumentException('O processo informado não corresponde ao número na carteira.');
        }
        if ($podeEditar !== null && !$podeEditar($idInformado)) {
            throw new InvalidArgumentException('Você não tem permissão para alterar este processo.');
        }

        foreach (self::BLOQUEADOS_EM_CADASTRADO as $bloqueado) {
            unset($campos[$bloqueado]);
        }

        if (array_key_exists('titulo_processo', $campos) && empty($campos['titulo_processo'])) {
            unset($campos['titulo_processo']); // não apaga o título existente
        }

        $atuais = array_map(fn (array $p) => $p['polo'] . '|' . Fontes::comparavel($p['nome']), $this->repository->partesDoProcesso($idInformado));
        $novas  = array_values(array_filter($partes, static fn ($p) => !in_array($p['polo'] . '|' . Fontes::comparavel($p['nome']), $atuais, true)));
        $etiquetasNovas = array_values(array_diff(
            array_map([Fontes::class, 'comparavel'], $etiquetas),
            array_map([Fontes::class, 'comparavel'], $this->repository->etiquetasDoProcesso($idInformado))
        ));

        if ($campos === [] && $novas === [] && $etiquetasNovas === []) {
            return ['status' => 'sem_alteracao', 'id_processo' => $idInformado, 'mensagem' => 'Nada a alterar.'];
        }

        $this->processos()->atualizarProcessoSemRemoverPartes($idInformado, $campos, $novas);
        $this->vincularEtiquetas($idInformado, array_values(array_filter(
            $etiquetas,
            static fn ($e) => in_array(Fontes::comparavel($e), $etiquetasNovas, true)
        )));

        return ['status' => 'atualizado', 'id_processo' => $idInformado];
    }

    /**
     * Mantém só os campos conhecidos e valida cada valor. Valores vazios viram
     * null (limpam o campo). Valores inválidos geram erro.
     *
     * @throws InvalidArgumentException
     */
    public function sanitizarCampos(array $campos): array
    {
        $saida = [];

        foreach ($campos as $campo => $valor) {
            if (!isset(self::CAMPOS[$campo])) {
                continue;
            }
            $rotulo = self::CAMPOS[$campo]['rotulo'];
            $valor  = is_string($valor) ? trim($valor) : $valor;

            if ($valor === null || $valor === '') {
                $saida[$campo] = null;
                continue;
            }
            if (!is_scalar($valor)) {
                throw new InvalidArgumentException("{$rotulo}: valor inválido.");
            }

            $saida[$campo] = match (self::CAMPOS[$campo]['tipo']) {
                'cliente' => $this->idExistente($valor, $this->repository->clientes(), 'id_cliente', $rotulo),
                'contrato' => $this->idExistente($valor, $this->repository->contratos(), 'id_contrato', $rotulo),
                'usuario' => $this->idExistente($valor, $this->repository->usuarios(), 'id', $rotulo),
                'ramo'    => $this->idExistente($valor, $this->repository->ramos(), 'id', $rotulo),
                'data'    => $this->converterData((string) $valor) ?? throw new InvalidArgumentException("{$rotulo}: data inválida."),
                'valor'   => $this->converterValor((string) $valor) ?? throw new InvalidArgumentException("{$rotulo}: valor inválido."),
                'risco'   => $this->converterRisco((string) $valor) ?? throw new InvalidArgumentException("{$rotulo}: use Provável, Possível ou Remoto."),
                'visibilidade' => in_array((string) $valor, self::VISIBILIDADES, true)
                    ? (string) $valor
                    : throw new InvalidArgumentException("{$rotulo}: use publico ou restrito."),
                'url'     => preg_match('#^https?://#i', (string) $valor) && filter_var($valor, FILTER_VALIDATE_URL)
                    ? mb_substr((string) $valor, 0, 500)
                    : throw new InvalidArgumentException("{$rotulo}: endereço inválido."),
                'texto_longo' => mb_substr((string) $valor, 0, 5000),
                default   => mb_substr((string) $valor, 0, 255),
            };
        }

        return $saida;
    }

    /**
     * @return array<int, array{nome: string, polo: string}>
     */
    private function sanitizarPartes(array $partes): array
    {
        $saida  = [];
        $vistos = [];
        foreach ($partes as $p) {
            $nome = mb_substr(trim((string) ($p['nome'] ?? '')), 0, 255);
            $polo = (string) ($p['polo'] ?? '');
            $chave = $polo . '|' . Fontes::comparavel($nome);
            if ($nome === '' || !in_array($polo, ['A', 'P'], true) || isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $saida[] = ['nome' => $nome, 'polo' => $polo];
        }

        return $saida;
    }

    private function idExistente(mixed $valor, array $lista, string $chave, string $rotulo): int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || !in_array($id, array_map('intval', array_column($lista, $chave)), true)) {
            throw new InvalidArgumentException("{$rotulo}: registro não encontrado.");
        }

        return $id;
    }

    private function nomeCliente(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        foreach ($this->repository->clientes() as $c) {
            if ($c['id_cliente'] === $id) {
                return $c['nome'];
            }
        }

        return null;
    }

    private function vincularEtiquetas(int $idProcesso, array $nomes): void
    {
        if ($nomes === []) {
            return;
        }

        $etiquetas = $this->etiquetas ??= new EtiquetasService();
        foreach ($nomes as $nome) {
            $this->processos()->gerenciarEtiquetas($idProcesso, $etiquetas->encontrarOuCriar($nome), true, false);
        }
    }

    // =========================================================================
    // Conversões
    // =========================================================================

    /** "dd/mm/aaaa", "aaaa-mm-dd" (com ou sem hora) → "aaaa-mm-dd". */
    public function converterData(string $valor): ?string
    {
        $valor = trim($valor);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $valor, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    /** "R$ 1.234,56", "1234,56", "1234.56" → "1234.56". */
    public function converterValor(string $valor): ?string
    {
        $valor = trim(str_ireplace(['R$', ' ', "\u{00A0}"], '', $valor));
        if ($valor === '') {
            return null;
        }

        if (str_contains($valor, ',')) {
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
            $valor = str_replace('.', '', $valor); // "1.234.567" sem centavos
        }

        if (!is_numeric($valor) || (float) $valor < 0) {
            return null;
        }

        return number_format((float) $valor, 2, '.', '');
    }

    public function converterRisco(string $valor): ?string
    {
        $chave = Fontes::comparavel($valor);
        foreach (self::RISCOS as $risco) {
            if (Fontes::comparavel($risco) === $chave) {
                return $risco;
            }
        }

        return null;
    }

    private function djen(): DjenService
    {
        return $this->djen ??= new DjenService();
    }

    private function processos(): ProcessosService
    {
        return $this->processos ??= new ProcessosService();
    }
}
