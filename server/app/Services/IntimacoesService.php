<?php

namespace App\Services;

use App\Services\ReceberIntimacoes;
use App\Repositories\IntimacoesRepository;
use App\Services\ProcessosService;
use App\Interfaces\AIServiceInterface;
use App\Traits\FormataDataTrait;
use Exception;
use App\Services\BaseService;

/**
 * Serviço responsável pela gestão de intimações.
 *
 * Esta classe centraliza a lógica de recebimento, processamento, listagem e
 * sincronização de intimações judiciais, interagindo com repositórios e serviços externos.
 *
 * @property IntimacoesRepository $repository
 */
class IntimacoesService extends BaseService
{
    use FormataDataTrait;

    // Constantes para evitar magic strings
    private const STATUS_PENDENTE = 'P';
    private const RISCO_POSSIVEL = 'Possível';
    private const POLO_ATIVO = 'A';
    private const POLO_PASSIVO = 'P';

    private ?ProcessosService $processoService = null;
    private ?UsuariosService $usuariosService = null;
    private ?AIServiceInterface $aiService = null;
    private ?TarefasService $tarefasService = null;
    private ?ArgumentosSugeridosService $argumentosService = null;
    private ?CalendarioForenseService $calendarioService = null;
    protected ?ReceberIntimacoes $receberIntimacoesService = null;
    protected ?AuditoriaService $auditoriaService = null;

    public function __construct(
        ?IntimacoesRepository $repository = null,
        ?ProcessosService $processoService = null,
        ?UsuariosService $usuariosService = null,
        ?AIServiceInterface $aiService = null,
        ?ReceberIntimacoes $receberIntimacoesService = null,
        ?AuditoriaService $auditoriaService = null,
        ?TarefasService $tarefasService = null,
        ?ArgumentosSugeridosService $argumentosService = null,
        ?CalendarioForenseService $calendarioService = null
    ) {
        $this->repository               = $repository ?? new IntimacoesRepository();
        $this->processoService          = $processoService;
        $this->usuariosService          = $usuariosService;
        $this->aiService                = $aiService;
        $this->receberIntimacoesService = $receberIntimacoesService;
        $this->auditoriaService         = $auditoriaService;
        $this->tarefasService           = $tarefasService;
        $this->argumentosService        = $argumentosService;
        $this->calendarioService        = $calendarioService;
    }

    protected function getCalendarioService(): CalendarioForenseService
    {
        if ($this->calendarioService === null) {
            $this->calendarioService = new CalendarioForenseService();
        }
        return $this->calendarioService;
    }

    protected function getProcessoService(): ProcessosService
    {
        if ($this->processoService === null) {
            $this->processoService = new ProcessosService();
        }
        return $this->processoService;
    }

    protected function getUsuariosService(): UsuariosService
    {
        if ($this->usuariosService === null) {
            $this->usuariosService = new UsuariosService();
        }
        return $this->usuariosService;
    }

    protected function getAiService(): AIServiceInterface
    {
        if ($this->aiService === null) {
            $this->aiService = service('AIService');
        }
        return $this->aiService;
    }

    protected function getReceberIntimacoesService(): ReceberIntimacoes
    {
        if ($this->receberIntimacoesService === null) {
            $this->receberIntimacoesService = new ReceberIntimacoes();
        }
        return $this->receberIntimacoesService;
    }

    protected function getAuditoriaService(): AuditoriaService
    {
        if ($this->auditoriaService === null) {
            $this->auditoriaService = new AuditoriaService();
        }
        return $this->auditoriaService;
    }

    protected function getTarefasService(): TarefasService
    {
        if ($this->tarefasService === null) {
            $this->tarefasService = new TarefasService();
        }
        return $this->tarefasService;
    }

    protected function getArgumentosService(): ArgumentosSugeridosService
    {
        if ($this->argumentosService === null) {
            $this->argumentosService = new ArgumentosSugeridosService();
        }
        return $this->argumentosService;
    }

    /**
     * Lista todas as intimações ordenadas pela data de disponibilização.
     *
     * @param bool $naoTratado Se true, retorna apenas intimações que ainda não foram tratadas.
     * @return array Lista de intimações.
     */
    public function listarIntimacoes(bool $naoTratado = false): array
    {
        if ($naoTratado) {
            return $this->repository->buscarIntimacoesNaoTratadas();
        }

        return $this->repository->buscarIntimacoes();
    }

    /**
     * Retorna a contagem de intimações não tratadas.
     *
     * @return int
     */
    public function contarNaoTratadas(): int
    {
        return $this->repository->contarNaoTratadas();
    }

    /**
     * Lista todas as intimações com detalhes completos.
     *
     * Recupera intimações incluindo informações detalhadas de destinatários e advogados.
     *
     * @return array Lista detalhada de intimações.
     */
    public function listarTodasIntimacoes(): array
    {
        return $this->repository->buscarTodasIntimacoes();
    }

    /**
     * Lista todas as intimações não sincronizadas.
     *
     * Recupera as intimações que ainda não foram enviadas ou sincronizadas com sistemas externos.
     *
     * @param int|null $limit Opcional limite de registros a retornar.
     * @return array Lista de intimações não sincronizadas.
     */
    public function listarIntimacoesNaoSincronizadas(?int $limit = null): array
    {
        return $this->repository->buscarIntimacoesNaoSincronizadas($limit);
    }

    /**
     * Detalha uma intimação específica pelo ID.
     *
     * @param int $id O ID da intimação.
     * @return array Os dados da intimação.
     * @throws Exception Se a intimação não for encontrada.
     */
    public function detalharIntimacao(int $id): array
    {
        $intimacao = $this->repository->detalharIntimacao($id);

        if (!$intimacao) {
            throw new Exception("Intimação #{$id} não encontrada");
        }

        return $intimacao;
    }

    /**
     * Busca intimações utilizando um serviço externo.
     *
     * Aciona o serviço ReceberIntimacoes para consultar novas intimações.
     *
     * @param array $params Parâmetros de filtro para a busca.
     * @return array Resultado da busca no serviço externo.
     */
    public function receberIntimacoes(array $params): array
    {
        log_message('info', 'Service: Iniciando busca de intimações com os parâmetros: ' . json_encode($params));

        return $this->getReceberIntimacoesService()->getIntimacoes($params);
    }

    /**
     * Processa as intimações recebidas de uma fonte externa.
     *
     * Itera sobre os dados recebidos, verifica duplicatas, salva novas intimações
     * e registra estatísticas de auditoria.
     *
     * @param array $data Dados contendo as intimações e informações de status.
     * @param string $filename Nome do arquivo ou identificador da origem.
     * @param int $userId ID do usuário que iniciou o processamento.
     * @return array Estatísticas do processamento (recebidas, processadas, repetidas, etc).
     */
    public function processarIntimacoes(array $data, string $filename, int $userId): array
    {
        $estatisticas = $this->inicializarEstatisticas($data, $filename, $userId);

        if ($estatisticas['numero_intimacoes_recebidas'] === 0) {
            return $estatisticas;
        }

        foreach ($data['items'] as $item) {
            $this->processarItemComEstatisticas($item, $estatisticas);
        }

        $this->registrarAuditoria($estatisticas);

        return $estatisticas;
    }

    /**
     * Inicializa as estatísticas de processamento.
     *
     * @param array $data Dados recebidos.
     * @param string $filename Nome do arquivo.
     * @param int $userId ID do usuário.
     * @return array Estatísticas inicializadas.
     */
    private function inicializarEstatisticas(array $data, string $filename, int $userId): array
    {
        return [
            'status_recebimento_intimacao' => $data['message'],
            'numero_intimacoes_recebidas' => $data['count'],
            'numero_intimacoes_repetidas' => 0,
            'numero_intimacoes_processadas' => 0,
            'numero_intimacoes_com_erro' => 0,
            'nomeArquivo' => $filename,
            'usuario_id' => $userId,
        ];
    }

    /**
     * Processa um item e atualiza as estatísticas.
     *
     * @param array $item Dados do item.
     * @param array $estatisticas Estatísticas (passado por referência).
     * @return void
     */
    private function processarItemComEstatisticas(array $item, array &$estatisticas): void
    {
        if ($this->repository->intimacaoJaExiste($item['id'])) {
            $estatisticas['numero_intimacoes_repetidas']++;
            return;
        }

        try {
            $resultado = $this->processarItem($item);

            if ($resultado['status'] === 'success') {
                $estatisticas['numero_intimacoes_processadas']++;
            }
        } catch (Exception $e) {
            $estatisticas['numero_intimacoes_com_erro']++;
            log_message('error', sprintf(
                'Erro ao processar intimação ID %s: %s',
                $item['id'] ?? 'N/A',
                $e->getMessage()
            ));
        }
    }

    /**
     * Processa um único item de intimação, salvando o processo e a intimação.
     *
     * Cria ou atualiza o processo associado e salva a intimação, seus destinatários
     * e advogados no banco de dados.
     *
     * @param array $item Dados de um item de intimação.
     * @return array Resultado do processamento.
     */
    public function processarItem(array $item): array
    {
        $this->validarDadosItem($item);

        $jaExisteProcesso = $this->getProcessoService()->processoJaExiste($item['numero_processo']);

        if (!$jaExisteProcesso) {
            $resultado = $this->criarProcessoSeNecessario($item);

            if ($resultado['status'] === 'skipped') {
                return $resultado;
            }
        }

        $resultado = $this->salvarIntimacaoCompleta($item);

        // Enriquecimento por IA (apenas para novas intimações)
        if ($resultado['status'] === 'success' && !($resultado['repetida'] ?? false)) {
            $processoData = $this->getProcessoService()->processoJaExiste($item['numero_processo']);

            // Para processos arquivados ou excluidos não envie o processo para a ia;
            $isArquivadoOuExcluido = $processoData && ( !empty($processoData['arquived_at']) || !empty($processoData['deleted_at']) );

            if (!$isArquivadoOuExcluido) {
                $this->enriquecerIntimacaoPorIA($item['id'], $item['texto'], $item['numero_processo'], $processoData, $item['data_disponibilizacao'] ?? $item['datadisponibilizacao'] ?? null);
            }
        }

        return $resultado;
    }

    /**
     * Valida os dados essenciais do item.
     *
     * @param array $item Dados do item.
     * @throws Exception Se algum campo obrigatório estiver ausente.
     * @return void
     */
    private function validarDadosItem(array $item): void
    {
        $camposObrigatorios = ['id', 'numero_processo', 'data_disponibilizacao'];

        foreach ($camposObrigatorios as $campo) {
            if (empty($item[$campo])) {
                throw new Exception("Campo obrigatório ausente: {$campo}");
            }
        }
    }

    /**
     * Cria processo se houver advogado correspondente.
     *
     * @param array $item Dados do item.
     * @return array Resultado da criação.
     */
    private function criarProcessoSeNecessario(array $item): array
    {
        if (!$this->temAdvogadoCorrespondente($item)) {
            return [
                'status' => 'skipped',
                'message' => 'Processo não existe e nenhum advogado correspondente encontrado.',
            ];
        }

        $this->criarProcessoComPartes($item);

        return ['status' => 'success'];
    }

    /**
     * Verifica se há advogado correspondente na intimação.
     * Complexidade: O(n + m) onde n = advogados do sistema, m = advogados da intimação
     *
     * @param array $item Dados do item.
     * @return bool True se houver correspondência.
     */
    private function temAdvogadoCorrespondente(array $item): bool
    {
        // 1. Verificação Primária: OAB (Rápida e Determinística)
        if ($this->temOabCorrespondente($item)) {
            return true;
        }

        // 2. Verificação Secundária: Análise Semântica via IA (Fuzzy Matching)
        // Isso resolve casos onde o tribunal não capturou a OAB, mas o texto cita o escritório ou clientes.
        return $this->verificarRelevanciaViaIA($item);
    }

    /**
     * Verifica correspondência baseada apenas em números de OAB.
     */
    private function temOabCorrespondente(array $item): bool
    {
        $advogadosIntimacao = $item['destinatarioadvogados'] ?? [];

        if (empty($advogadosIntimacao)) {
            return false;
        }

        // Extrai OABs da intimação e cria um Set para busca O(1)
        $oabsIntimacao = [];
        foreach ($advogadosIntimacao as $advIntimacao) {
            $oabNumero = $advIntimacao['advogado']['numero_oab'] ?? null;
            $oabNormalizada = $this->normalizarOab($oabNumero);
            if ($oabNormalizada !== '') {
                $oabsIntimacao[$oabNormalizada] = true; // Usando array como Set
            }
        }

        if (empty($oabsIntimacao)) {
            return false;
        }

        // Busca em O(n) onde n = advogados do sistema
        $advogadosSistema = $this->getUsuariosService()->getAdvogados();
        foreach ($advogadosSistema as $advSistema) {
            $oabSistema = $advSistema['oab_numero'] ?? null;
            $oabSistemaNormalizada = $this->normalizarOab($oabSistema);

            // Busca O(1) no Set
            if ($oabSistemaNormalizada !== '' && isset($oabsIntimacao[$oabSistemaNormalizada])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza um número de OAB para comparação.
     * Remove caracteres não numéricos e zeros à esquerda.
     */
    private function normalizarOab(?string $oab): string
    {
        if (empty($oab)) {
            return '';
        }

        // Remove tudo que não for dígito
        $oabLimpa = preg_replace('/\D/', '', $oab);

        // Remove zeros à esquerda
        return ltrim($oabLimpa, '0');
    }

    /**
     * Utiliza IA para verificar se a intimação pertence ao escritório sem depender de OAB.
     */
    private function verificarRelevanciaViaIA(array $item): bool
    {
        $advogados = $this->getUsuariosService()->getAdvogados();
        $nomesAdvogados = array_column($advogados, 'nome');

        $contexto = [
            'advogados_escritorio' => $nomesAdvogados,
            'titulo_intimacao' => $item['titulo_processo'] ?? '',
        ];

        $analise = $this->getAiService()->checkRelevance($item['texto'], $contexto);

        if ($analise['is_relevant'] && ($analise['score'] ?? 0) >= 0.7) {
            return true;
        }

        return false;
    }

    /**
     * Enriquece a intimação com metadados gerados por IA.
     */
    private function enriquecerIntimacaoPorIA(string $idIntimacao, string $texto, string $numeroProcesso, ?array $processoData = null, ?string $dataDisponibilizacao = null): void
    {
        try {
            $contexto = [];
            if ($processoData && isset($processoData['id_processo'])) {
                // Processo já existente: envie além da intimação, os dados do processo e das tarefas vinculadas a ele.
                $idProcesso = (int) $processoData['id_processo'];
                $contexto['processo']        = $processoData;
                $contexto['tarefas']         = $this->getTarefasService()->listarTarefas(['processo_id' => $idProcesso]);
                $contexto['argumentos']      = $this->getArgumentosService()->buscarPorProcesso($idProcesso);
            }

            $dadosIA = $this->getAiService()->analyzeNotification($texto, $contexto);

            if (empty($dadosIA)) {
                return;
            }

            $updateData = [
                'ai_categoria'         => $dadosIA['categoria'] ?? null,
                'ai_resumo'            => $dadosIA['resumo'] ?? null,
                'ai_data_critica'      => $dadosIA['data_critica'] ?? null,
                'ai_valor_condenacao'  => $dadosIA['valor_condenacao'] ?? null,
                'ai_urgencia'          => $dadosIA['urgencia'] ?? null,
                'ai_score_confianca'   => $dadosIA['score_confianca'] ?? null,
                'ai_partes_relevantes' => isset($dadosIA['partes_relevantes']) ? json_encode($dadosIA['partes_relevantes']) : null,
                'ai_detalhes_extracao' => $this->montarDetalhesExtracao($dadosIA),
                'ai_analisado_em'      => date('Y-m-d H:i:s'),
            ];

            // Atualiza via repositório (que internamente gerencia o cache e o model)
            $this->repository->update($idIntimacao, $updateData);

            // Cria tarefa automática se for relevante
            $categoriasAcionaveis = ['prazo', 'audiencia'];
            $urgenciaAlta = ($dadosIA['urgencia'] ?? '') === 'alta';

            if (in_array($dadosIA['categoria'] ?? '', $categoriasAcionaveis) || $urgenciaAlta) {

                log_message('info', "Intimação ID {$idIntimacao} identificada como relevante via IA." . json_encode($dadosIA));
                $this->criarTarefaDaIntimacao($idIntimacao, $numeroProcesso, $dadosIA, $dataDisponibilizacao);
            }

        } catch (Exception $e) {
            log_message('error', "Erro ao enriquecer intimação {$idIntimacao}: " . $e->getMessage());
        }
    }

    /**
     * Cria uma tarefa automática baseada na análise da IA.
     */
    protected function criarTarefaDaIntimacao(string $idIntimacao, string $numeroProcesso, array $dadosIA, ?string $dataDisponibilizacao = null): void
    {
        try {
            $processo = $this->getProcessoService()->processoJaExiste($numeroProcesso);
            if (!$processo) {
                return;
            }

            $sugestao   = $dadosIA['sugestao_tarefa'] ?? [];
            $titulo     = "AI: " . ($sugestao['tarefa'] ?? ($sugestao['titulo'] ?? ("Tratar Intimação: " . ($dadosIA['resumo'] ?? $numeroProcesso))));
            $descricao  = $sugestao['detalhes'] ?? ($sugestao['descricao'] ?? ("IA detectou uma intimação do tipo " . ($dadosIA['categoria'] ?? 'decisão') . " com urgência " . ($dadosIA['urgencia'] ?? 'média') . "."));

            // A IA só identifica o ato e o número de dias; a data sai da contagem em dias úteis.
            $prazo = $this->resolverPrazo($dadosIA, $dataDisponibilizacao, $processo);
            $descricao .= "\nVencimento: " . date('d/m/Y', strtotime($prazo['prazo']));
            if ($prazo['a_conferir']) {
                $descricao .= " (PRAZO A CONFERIR: " . ($prazo['memoria']['avisos'][0] ?? 'data estimada, não calculada') . ")";
            }

            $dadosTarefa = [
                'tarefa'          => mb_strtoupper($titulo),
                'detalhes'        => $descricao,
                'status'          => 2, // Pendente
                'prioridade'      => ($dadosIA['urgencia'] ?? 'media') === 'alta' ? 3 : 2,
                'processo_id'     => $processo['id_processo'],
                'cliente_id'      => $processo['cliente_id'] ?? null,
                'responsavel'     => $processo['responsavel_id'] ?? user_id(),
                'prazo'           => $prazo['prazo'],
                'prazo_memoria_calculo' => json_encode($prazo['memoria'], JSON_UNESCAPED_UNICODE),
                'prazo_a_conferir'      => $prazo['a_conferir'] ? 1 : 0,
                'created_by'      => function_exists('user_id') && user_id() ? user_id() : 1,
            ];

            $this->getTarefasService()->salvarTarefa($dadosTarefa);
            log_message('info', "Tarefa automática criada para intimação {$idIntimacao} no processo {$numeroProcesso}.");

        } catch (Exception $e) {
            log_message('error', "Erro ao criar tarefa automática para intimação {$idIntimacao}: " . $e->getMessage());
        }
    }

    /**
     * Junta o que a IA extraiu (tipo de ato e número de dias) aos detalhes da extração.
     */
    private function montarDetalhesExtracao(array $dadosIA): ?string
    {
        $detalhes = is_array($dadosIA['detalhes_extracao'] ?? null) ? $dadosIA['detalhes_extracao'] : [];
        foreach (['tipo_ato', 'prazo_dias'] as $campo) {
            if (isset($dadosIA[$campo]) && $dadosIA[$campo] !== '') {
                $detalhes[$campo] = $dadosIA[$campo];
            }
        }

        return $detalhes === [] ? null : json_encode($detalhes);
    }

    /**
     * Define o prazo da tarefa e a memória de cálculo.
     *
     * Com número de dias identificado, o vencimento sai da contagem em dias úteis.
     * Sem ele, a data da IA (ou o padrão de 2 dias úteis) é usada e o prazo fica "a conferir".
     * Audiência tem data certa no texto, então a data informada não precisa de conferência de cálculo.
     *
     * @return array{prazo:string, memoria:array, a_conferir:bool}
     */
    protected function resolverPrazo(array $dadosIA, ?string $dataDisponibilizacao, ?array $processo): array
    {
        $calendario = $this->getCalendarioService();
        $tribunal   = $processo['siglaTribunal'] ?? null;
        $dias       = (int) ($dadosIA['prazo_dias'] ?? 0);
        $dataBase   = $dataDisponibilizacao ? substr($dataDisponibilizacao, 0, 10) : null;

        if ($dias > 0 && $dataBase) {
            try {
                $memoria = $calendario->memoriaCalculo($dataBase, $dias, $tribunal);
                $memoria['tipo_ato'] = $dadosIA['tipo_ato'] ?? null;

                return ['prazo' => $memoria['vencimento'], 'memoria' => $memoria, 'a_conferir' => false];
            } catch (\InvalidArgumentException $e) {
                log_message('warning', 'Contagem de prazo falhou, usando estimativa: ' . $e->getMessage());
            }
        }

        if (!empty($dadosIA['data_critica']) && strtotime($dadosIA['data_critica']) !== false) {
            $data = date('Y-m-d', strtotime($dadosIA['data_critica']));
            if (($dadosIA['categoria'] ?? '') === 'audiencia') {
                return [
                    'prazo'      => $data,
                    'memoria'    => ['metodo' => 'data_informada', 'a_conferir' => false, 'origem' => 'texto_da_intimacao', 'vencimento' => $data, 'avisos' => []],
                    'a_conferir' => false,
                ];
            }

            return ['prazo' => $data, 'memoria' => $calendario->memoriaPrazoAConferir($data, 'estimativa_ia'), 'a_conferir' => true];
        }

        // Sem dias e sem data: padrão de 2 dias úteis a partir da disponibilização (ou de hoje).
        try {
            $memoria = $calendario->memoriaCalculo($dataBase ?? date('Y-m-d'), 2, $tribunal);
            $memoria['metodo']  = 'padrao';
            $memoria['avisos']  = array_merge(
                ['Prazo não identificado na intimação: aplicado o padrão de 2 dias úteis. Conferir manualmente.'],
                $memoria['avisos']
            );

            return ['prazo' => $memoria['vencimento'], 'memoria' => $memoria, 'a_conferir' => true];
        } catch (\InvalidArgumentException $e) {
            $data = date('Y-m-d', strtotime('+2 days'));

            return ['prazo' => $data, 'memoria' => $calendario->memoriaPrazoAConferir($data, 'padrao'), 'a_conferir' => true];
        }
    }

    /**
     * Memória de cálculo de uma intimação, recalculada com o calendário atual.
     *
     * @return array{disponivel:bool, motivo?:string, memoria?:array, a_conferir?:bool}
     * @throws Exception Se a intimação não existir.
     */
    public function memoriaCalculoPrazo(int $id): array
    {
        $intimacao = $this->detalharIntimacao($id);
        $detalhes  = json_decode((string) ($intimacao['ai_detalhes_extracao'] ?? ''), true) ?: [];
        $dias      = (int) ($detalhes['prazo_dias'] ?? 0);
        $dataDisp  = $intimacao['data_disponibilizacao'] ?? $intimacao['datadisponibilizacao'] ?? null;

        if ($dias < 1 || !$dataDisp) {
            return [
                'disponivel' => false,
                'a_conferir' => !empty($intimacao['ai_data_critica']),
                'motivo'     => 'O número de dias do prazo não foi identificado nesta intimação.',
                'data_estimada_ia' => $intimacao['ai_data_critica'] ?? null,
            ];
        }

        $processo = $this->getProcessoService()->processoJaExiste($intimacao['numero_processo'] ?? '');
        $memoria  = $this->getCalendarioService()->memoriaCalculo((string) $dataDisp, $dias, $processo['siglaTribunal'] ?? null);
        $memoria['tipo_ato'] = $detalhes['tipo_ato'] ?? null;

        return ['disponivel' => true, 'a_conferir' => false, 'memoria' => $memoria];
    }

    /**
     * Cria o processo com suas partes.
     *
     * @param array $item Dados do item.
     * @return void
     */
    private function criarProcessoComPartes(array $item): void
    {
        $dadosProcesso = $this->prepararDadosProcesso($item);
        [$poloAtivo, $poloPassivo] = $this->separarPolos($item);

        $this->getProcessoService()->salvarProcesso($dadosProcesso, null, $poloAtivo, $poloPassivo);
    }

    /**
     * Prepara os dados do processo para salvamento.
     *
     * @param array $item Dados do item.
     * @return array Dados formatados do processo.
     */
    private function prepararDadosProcesso(array $item): array
    {
        return [
            'siglaTribunal'             => $item['siglaTribunal'] ?? null,
            'nomeOrgao'                 => $item['nomeOrgao'] ?? null,
            'numero_processo'           => $item['numero_processo'] ?? null,
            'link'                      => $item['link'] ?? null,
            'tipoDocumento'             => $item['nomeClasse'] ?? null,
            'codigoClasse'              => $item['codigoClasse'] ?? null,
            'ativo'                     => $item['ativo'] ?? 1,
            'status'                    => $item['status'] ?? self::STATUS_PENDENTE,
            'risco'                     => $item['risco'] ?? self::RISCO_POSSIVEL,
            'numeroprocessocommascara'  => $item['numeroprocessocommascara'] ?? null,
            'titulo_processo'           => $item['titulo_processo'] ?? 'Processo sem título',
            'dataDistribuicao'          => $item['dataDistribuicao'] ?? null,
        ];
    }

    /**
     * Separa destinatários em polos ativo e passivo.
     *
     * @param array $item Dados do item.
     * @return array Array com [poloAtivo, poloPassivo].
     */
    private function separarPolos(array $item): array
    {
        $poloAtivo = [];
        $poloPassivo = [];

        foreach ($item['destinatarios'] ?? [] as $destinatario) {
            $polo = $destinatario['polo'] ?? null;
            $nome = ['nome' => $destinatario['nome'] ?? ''];

            if ($polo === self::POLO_ATIVO) {
                $poloAtivo[] = $nome;
            } elseif ($polo === self::POLO_PASSIVO) {
                $poloPassivo[] = $nome;
            }
        }

        return [$poloAtivo, $poloPassivo];
    }

    /**
     * Salva intimação completa com destinatários e advogados.
     *
     * @param array $item Dados do item.
     * @return array Resultado do salvamento.
     */
    private function salvarIntimacaoCompleta(array $item): array
    {
        $resultado = $this->salvarIntimacao($item);

        if ($resultado['status'] === 'success' && !$resultado['repetida']) {
            $this->salvarDestinatarios($item);
            $this->salvarAdvogados($item);
        }

        return $resultado;
    }

    /**
     * Salva todos os destinatários da intimação.
     *
     * @param array $item Dados do item.
     * @return void
     */
    private function salvarDestinatarios(array $item): void
    {
        foreach ($item['destinatarios'] ?? [] as $destinatario) {
            $destinatario['comunicacao_id'] = $item['id'];
            $this->salvarDestinatario($destinatario);
        }
    }

    /**
     * Salva todos os advogados da intimação.
     *
     * @param array $item Dados do item.
     * @return void
     */
    private function salvarAdvogados(array $item): void
    {
        foreach ($item['destinatarioadvogados'] ?? [] as $advogado) {
            $advogado['comunicacao_id'] = $item['id'];
            $this->salvarAdvogado($advogado);
        }
    }

    /**
     * Salva os dados de uma intimação no banco de dados.
     *
     * Prepara os dados e insere no repositório se a intimação ainda não existir.
     *
     * @param array $intimacao Dados brutos da intimação.
     * @return array Resultado do salvamento.
     */
    private function salvarIntimacao(array $intimacao): array
    {
        if ($this->repository->intimacaoJaExiste($intimacao['id'])) {
            return [
                'status' => 'success',
                'repetida' => true,
                'message' => 'Intimação já existe',
            ];
        }

        $data = $this->prepararDadosIntimacao($intimacao);
        $this->repository->salvarIntimacao($data);

        return [
            'status' => 'success',
            'repetida' => false,
            'message' => 'Nova intimação salva com sucesso',
        ];
    }

    /**
     * Prepara os dados da intimação para salvamento.
     *
     * @param array $intimacao Dados da intimação.
     * @return array Dados formatados.
     */
    private function prepararDadosIntimacao(array $intimacao): array
    {
        return [
            'id_intimacao'          => $intimacao['id'],
            'data_disponibilizacao' => $this->dataParaBancoDados($intimacao['data_disponibilizacao']),
            'tipoComunicacao'       => $intimacao['tipoComunicacao'],
            'texto'                 => $intimacao['texto'],
            'numero_processo'       => $intimacao['numero_processo'],
            'meio'                  => $intimacao['meio'],
            'link'                  => $intimacao['link'],
            'numeroComunicacao'     => $intimacao['numeroComunicacao'],
            'hash'                  => $intimacao['hash'],
            'motivo_cancelamento'   => $intimacao['motivo_cancelamento'] ?? null,
            'data_cancelamento'     => $intimacao['data_cancelamento'] ?? null,
            'datadisponibilizacao'  => $this->dataParaBancoDados($intimacao['datadisponibilizacao']),
            'dataenvio'             => $this->dataParaBancoDados($intimacao['datadisponibilizacao']),
            'meiocompleto'          => $intimacao['meiocompleto'],
        ];
    }

    /**
     * Verifica se uma intimação já existe no banco de dados.
     *
     * @param string $idIntimacao ID da intimação.
     * @return bool True se a intimação existe, False caso contrário.
     */
    public function intimacaoJaExiste(string $idIntimacao): bool
    {
        return $this->repository->intimacaoJaExiste($idIntimacao);
    }

    /**
     * Salva uma intimação no banco de dados com dados já preparados.
     *
     * @param array $dadosPreparados Dados da intimação já formatados para inserção.
     * @return array Resultado do salvamento.
     */
    public function salvarIntimacaoDadosPreparados(array $dadosPreparados): array
    {
        if ($this->repository->intimacaoJaExiste($dadosPreparados['id_intimacao'])) {
            return [
                'status' => 'success',
                'repetida' => true,
                'message' => 'Intimação já existe',
            ];
        }

        $this->repository->salvarIntimacao($dadosPreparados);

        return [
            'status' => 'success',
            'repetida' => false,
            'message' => 'Nova intimação salva com sucesso',
        ];
    }

    /**
     * Insere, em lote, os destinatários de uma intimação no banco de dados.
     *
     * @param array $destinatarios Lista de destinatários.
     * @return void
     */
    public function destinatarioInsertBatch(array $destinatarios): void
    {
        $this->repository->destinatariosInsertBatch($destinatarios);
    }

    /**
     * Insere, em lote, os advogados de uma intimação no banco de dados.
     *
     * @param array $advogados Lista de advogados.
     * @return void
     */
    public function advogadosInsertBatch(array $advogados): void
    {
        $this->repository->advogadosInsertBatch($advogados);
    }

    /**
     * Salva os dados de um destinatário de intimação no banco de dados.
     *
     * @param array $destinatario Dados do destinatário.
     * @return void
     */
    private function salvarDestinatario(array $destinatario): void
    {
        $data = [
            'nome' => $destinatario['nome'] ?? null,
            'polo' => $destinatario['polo'] ?? null,
            'comunicacao_id' => $destinatario['comunicacao_id'] ?? null,
        ];

        $this->repository->salvarDestinatario($data);
    }

    /**
     * Salva os dados de um advogado de intimação no banco de dados.
     *
     * @param array $advogado Dados do advogado (estrutura complexa aninhada).
     * @return void
     */
    private function salvarAdvogado(array $advogado): void
    {
        $data = [
            'id'                 => $advogado['id'] ?? null,
            'comunicacao_id'     => $advogado['comunicacao_id'] ?? null,
            'advogado_id'        => $advogado['advogado']['id'] ?? null,
            'advogado_nome'      => $advogado['advogado']['nome'] ?? null,
            'advogado_oab'       => $advogado['advogado']['numero_oab'] ?? null,
            'advogado_oab_uf'    => $advogado['advogado']['uf_oab'] ?? null,
            'created_at'         => $advogado['created_at'] ?? null,
            'updated_at'         => $advogado['updated_at'] ?? null,
        ];

        $this->repository->salvarAdvogado($data);
    }

    /**
     * Registra as estatísticas de processamento de intimações na auditoria.
     *
     * @param array $estatisticas Estatísticas do processamento.
     * @return void
     */
    private function registrarAuditoria(array $estatisticas): void
    {
        $this->getAuditoriaService()->registrarProcessamentoIntimacoes($estatisticas);
    }

    /**
     * Busca intimações em um período específico.
     *
     * @param int $dias Número de dias para retroceder na busca (a partir de hoje).
     * @return array Lista de intimações encontradas no período.
     */
    public function buscarIntimacoesPorPeriodo(int $dias): array
    {
        return $this->repository->buscarIntimacoesPorPeriodo($dias);
    }

    /**
     * Registra a sincronização de uma intimação no banco de dados.
     *
     * Marca a intimação como sincronizada atualizando o campo de controle.
     *
     * @param int $id O ID da intimação.
     * @return void
     */
    public function registrarSincronizacao(int $id): void
    {
        $this->repository->registrarSincronizacao($id);
    }

    /**
     * Marca uma intimação como tratada.
     *
     * @param int $idIntimacao O ID da intimação.
     * @param int $idTratamento O ID do tratamento aplicado.
     * @return void
     */
    public function tratarIntimacao(int $idIntimacao, int $idTratamento): void
    {
        $this->repository->tratarIntimacao($idIntimacao, $idTratamento);
    }

    /**
     * Lista todos os tipos de tratamento disponíveis.
     *
     * @return array Lista de tipos de tratamento.
     */
    public function listarTiposTratamento(): array
    {
        return $this->repository->buscarTodosTiposTratamento();
    }
}
