<?php

namespace App\Services;

use App\Repositories\ProcessosRepository;
use App\Repositories\ProcessPartiesRepository;
use App\Repositories\ObjetosRepository;
use App\Repositories\ProcessLinksRepository;
use App\Repositories\ProcessStatisticsRepository;
use App\Services\BaseService;
use Exception;
use App\Interfaces\AIServiceInterface;
use App\Services\GoogleDriveService;
use App\DTO\ProcessoSearchCriteria;

/**
 * Serviço responsável pelo gerenciamento de processos judiciais.
 *
 * Esta classe centraliza a lógica de negócios para criar, ler, atualizar e excluir
 * processos, além de gerenciar entidades relacionadas como partes, anotações,
 * etiquetas e objetos.
 *
 * @property ProcessosRepository $repository
 */
class ProcessosService extends BaseService
{

    protected GoogleDriveService $googleDriveService;
    protected AIServiceInterface $aiService;
    protected UsuariosService $usuariosService;
    protected ProcessPartiesRepository $partiesRepository;
    protected ObjetosRepository $objectsRepository;
    protected ProcessLinksRepository $linksRepository;
    protected ProcessStatisticsRepository $statisticsRepository;
    protected $cache;

    /**
     * Construtor do ProcessosService com injeção de dependência.
     *
     * @param ProcessosRepository|null $repository
     * @param AIServiceInterface|null $aiService
     * @param GoogleDriveService|null $googleDriveService
     * @param UsuariosService|null $usuariosService
     * @param ProcessPartiesRepository|null $partiesRepository
     * @param ObjetosRepository|null $objectsRepository
     * @param ProcessLinksRepository|null $linksRepository
     * @param ProcessStatisticsRepository|null $statisticsRepository
     */
    public function __construct(
        ?ProcessosRepository $repository = null,
        ?AIServiceInterface $aiService = null,
        ?GoogleDriveService $googleDriveService = null,
        ?UsuariosService $usuariosService = null,
        ?ProcessPartiesRepository $partiesRepository = null,
        ?ObjetosRepository $objectsRepository = null,
        ?ProcessLinksRepository $linksRepository = null,
        ?ProcessStatisticsRepository $statisticsRepository = null
    ) {
        $this->repository              = $repository ?? new ProcessosRepository();
        $this->googleDriveService      = $googleDriveService ?? new GoogleDriveService();
        $this->aiService               = $aiService ?? service('AIService');
        $this->usuariosService         = $usuariosService ?? new UsuariosService();
        $this->partiesRepository       = $partiesRepository ?? new ProcessPartiesRepository();
        $this->objectsRepository       = $objectsRepository ?? new ObjetosRepository(null, $this->repository);
        $this->linksRepository         = $linksRepository ?? new ProcessLinksRepository();
        $this->statisticsRepository    = $statisticsRepository ?? new ProcessStatisticsRepository();
        $this->cache                   = service('cache');
    }

    /**
     * Realiza o upload de um arquivo para a pasta do Google Drive associada ao processo.
     *
     * @param int $idProcesso ID do processo.
     * @param string $filePath Caminho do arquivo temporário.
     * @param string $fileName Nome original do arquivo.
     * @param string $mimeType Tipo MIME do arquivo.
     * @return array Resultado do upload com ID e Link.
     * @throws Exception
     */
    public function uploadArquivoProcesso(int $idProcesso, string $filePath, string $fileName, string $mimeType): array
    {
        $detalhes = $this->buscarDetalhesProcesso($idProcesso);
        $processo = $detalhes['processo'];

        if (empty($processo['pasta'])) {
            throw new Exception("O processo não possui uma pasta do Google Drive vinculada.");
        }

        $folderId = $this->googleDriveService->extractFolderIdFromUrl($processo['pasta']);

        if (!$folderId) {
            throw new Exception("Não foi possível identificar o ID da pasta do Google Drive.");
        }

        return $this->googleDriveService->uploadFileToFolder($filePath, $fileName, $mimeType, $folderId);
    }


    public function listarProcessos(
        ?string $search,
        string $sortField,
        string $sortOrder,
        ?int $encerrado,
        int $perPage = 25,
        ?int $clienteId = null,
        ?array $filtros = []
    ): array {
        $criteria = new ProcessoSearchCriteria([
            'search' => $search,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'encerrado' => $encerrado,
            'perPage' => $perPage,
            'filtrosExtras' => $filtros,
            'incluirArquivados' => isset($filtros['arquivados']) && $filtros['arquivados'] === '1',
        ]);

        if ($clienteId) {
            return $this->repository->buscarProcessosCliente($clienteId, $criteria);
        } else {
            return $this->repository->buscarProcessos($criteria);
        }
    }

    public function listarProcessosObjeto(
        int $objetoId,
        ?string $search,
        string $sortField = 'numero_processo',
        string $sortOrder = 'ASC',
        ?int $encerrado = null,
        int $perPage = 25
    ): array {
        $criteria = new ProcessoSearchCriteria([
            'search'            => $search,
            'sortField'         => $sortField,
            'sortOrder'         => $sortOrder,
            'encerrado'         => $encerrado,
            'perPage'           => $perPage,
            // Um objeto (bem/imóvel) permanece vinculado ao processo mesmo após o
            // arquivamento; o vínculo não deve desaparecer da listagem por isso.
            'incluirArquivados' => null,
        ]);

        return $this->repository->buscarProcessosObjeto($objetoId, $criteria);
    }

    /**
     * Obtém os dados formatados para o gráfico de processos por período.
     * Otimizado para dashboard, realizando o agrupamento diretamente no banco de dados.
     *
     * @param string $campoData O campo de data para agrupamento (ex: 'created_at' ou 'data_encerramento').
     * @param string $dataInicio Data inicial (Y-m-d).
     * @param string $dataFim Data final (Y-m-d).
     * @param int|null $clienteId ID do cliente para filtrar os resultados.
     * @return array Lista de totais por mês prontos para o gráfico.
     */
    public function getDadosGraficoProcessosPorPeriodo(string $campoData, string $dataInicio, string $dataFim, ?int $clienteId = null): array
    {
        return $this->statisticsRepository->getProcessosAgrupadosPorMes($campoData, $dataInicio, $dataFim, $clienteId);
    }

    /**
     * Lista os processos que tiveram movimentação nos últimos dias.
     *
     * @param int $dias A quantidade de dias retroativos para buscar movimentações.
     * @return array Uma lista de processos movimentados recentemente.
     */
    public function getProcessosMovimentados(int $dias): array
    {
        $hoje = date('Y-m-d', time());
        $dataInicial = date('Y-m-d', strtotime('-' . $dias . ' days'));
        return $this->statisticsRepository->buscarProcessosMovimentados($dataInicial, $hoje);
    }

    /**
     * Blocos do detalhe do processo que pertencem a outros módulos:
     * chave => [slug do módulo, permissões aceitas]. Ver VisibilidadeModulosService.
     */
    public const BLOCOS_DETALHE = [
        'objetos'         => ['objetos', 'objetos.access'],
        'intimacoes'      => ['intimacoes', 'intimacoes.access'],
        'tarefas'         => ['tarefas', 'tarefas.access'],
        // Rotas de checklist exigem só login: nem todo grupo tem checklist.*.
        'checklists'      => ['checklist'],
        'liquidacao'      => ['processos', 'liquidacoes.access', 'liquidacoes.exclusive'],
    ];

    /**
     * Retorna os detalhes completos de um processo.
     *
     * @param int $id O ID do processo.
     * @return array Os detalhes do processo, incluindo dados relacionados.
     */
    public function buscarDetalhesProcesso(int $id): array
    {
        $detalhes = $this->repository->buscarDetalhesProcesso($id);

        // Built on every call (not cached) so the date window is always current.
        if (!empty($detalhes)) {
            $detalhes['urlIntimacao'] = $this->urlIntimacao($detalhes['processo']['numero_processo'] ?? '');
            $detalhes['liquidacao']   = $this->resumoLiquidacao($id);
        }

        return $detalhes;
    }

    /**
     * Valor atualizado e saldo a receber da liquidação mais recente do processo (null se não houver).
     * O bloco `liquidacao` é ocultado de quem não tem acesso ao módulo (ver BLOCOS_DETALHE).
     */
    protected function resumoLiquidacao(int $processoId): ?array
    {
        try {
            return (new \App\Repositories\LiquidacoesRepository())->resumoPorProcesso($processoId);
        } catch (\Throwable $e) {
            log_message('warning', '[ProcessosService] Resumo da liquidação indisponível: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Gera a URL para buscar intimidações de um processo.
     *
     * @param string $numeroProcesso Número do processo.
     * @return string URL para buscar intimidações.
     */
    private function urlIntimacao(string $numeroProcesso): string
    {
        $apiUrl = env('API_PJE');
        $hoje = date('Y-m-d', time());
        $mesPassado = date('Y-m-d', strtotime('-30 days'));

        $params = [
            'numeroProcesso' => $numeroProcesso,
            'dataDisponibilizacaoInicio' => $mesPassado,
            'dataDisponibilizacaoFim' => $hoje,
        ];

        return $apiUrl . '?' . http_build_query($params);
    }

    /**
     * Retorna o ID do cliente associado a um processo.
     *
     * @param int $id O ID do processo.
     * @return int|null O ID do cliente ou null se não houver cliente associado.
     */
    public function buscarClienteProcesso(int $id): ?int
    {
        return $this->repository->buscarClienteProcesso($id);
    }

    public function salvarProcessoRapido(array $data, int $id): int
    {
        $isTransitionToEncerrado = false;
        if (isset($data['encerrado']) && $data['encerrado'] == 1) {
            $processoAtual = $this->repository->buscarDetalhesProcesso($id)['processo'] ?? [];
            if (isset($processoAtual['encerrado']) && $processoAtual['encerrado'] != 1) {
                $isTransitionToEncerrado = true;
            }
        }

        $resultId = $this->repository->salvarProcesso($data, $id);

        if ($isTransitionToEncerrado) {
            $this->gerarEEnviarRelatorioEncerramento($resultId);
        }

        $this->agendarSincronizacaoDrive((int)$resultId);

        return $resultId;
    }

    /**
     * Salva ou atualiza um processo e suas partes.
     *
     * Gerencia a inserção/atualização dos dados principais do processo e, em seguida,
     * atualiza as partes envolvidas (polos ativo e passivo).
     *
     * @param array $data Os dados principais do processo.
     * @param int|null $id O ID do processo (null para criar um novo).
     * @param array $poloAtivo Lista de partes do polo ativo.
     * @param array $poloPassivo Lista de partes do polo passivo.
     * @return int O ID do processo salvo.
     * @throws Exception Se ocorrer um erro durante a operação de banco de dados.
     */
    public function salvarProcesso(array $data, ?int $id, array $poloAtivo = [], array $poloPassivo = []): int
    {
        $isTransitionToEncerrado = false;
        if ($id && isset($data['encerrado']) && $data['encerrado'] == 1) {
            $processoAtual = $this->repository->buscarDetalhesProcesso($id)['processo'] ?? [];
            if (isset($processoAtual['encerrado']) && $processoAtual['encerrado'] != 1) {
                $isTransitionToEncerrado = true;
            }
        }

        $idProcesso = $this->repository->salvarProcesso($data, $id);

        // Otimização: Salva todas as partes de uma vez (Batch Fetching & Inserting)
        $this->partiesRepository->salvarPartesBatch($idProcesso, $poloAtivo, $poloPassivo);

        if ($isTransitionToEncerrado) {
            $this->gerarEEnviarRelatorioEncerramento($idProcesso);
        }

        $this->agendarSincronizacaoDrive((int)$idProcesso);

        return $idProcesso;
    }

    /**
     * Gera o relatório de encerramento via IA, salva no processo e notifica o advogado responsável.
     *
     * @param int $idProcesso
     * @return void
     */
    public function gerarEEnviarRelatorioEncerramento(int $idProcesso): void
    {
        try {
            $detalhes = $this->buscarDetalhesProcesso($idProcesso);
            if (!$detalhes) return;

            $relatorio = $this->aiService->generateClosingReport($detalhes);

            // Salva no banco e publica para o cliente
            $this->repository->salvarProcesso([
                'relatorio_final' => $relatorio,
                'relatorio_final_publicado' => 1
            ], $idProcesso);

            // Notifica o advogado responsável (se houver)
            $p = $detalhes['processo'];
            if (!empty($p['responsavel_id'])) {
                $mensagensService = new \App\Services\MensagensService();
                $mensagensService->enviar(
                    function_exists('user_id') && user_id() ? user_id() : 1,
                    (int) $p['responsavel_id'],
                    "Relatório Final Gerado",
                    "O processo " . ($p['numeroprocessocommascara'] ?? $p['numero_processo']) . " foi encerrado e o Relatório Final (IA) foi gerado e disponibilizado ao cliente.",
                    (int) $idProcesso
                );
            }
        } catch (\Exception $e) {
            log_message('error', 'Falha ao gerar e enviar relatório de encerramento: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza somente os campos informados de um processo existente e
     * acrescenta partes, sem apagar as que já estão vinculadas.
     *
     * Diferente de {@see salvarProcesso()}, que substitui todas as partes.
     * Usado pela importação em lote, que nunca remove dados da carteira.
     *
     * @param int   $id          ID do processo.
     * @param array $data        Campos a alterar (pode ser vazio).
     * @param array $novasPartes Lista de ['nome' => ..., 'polo' => 'A'|'P'].
     */
    public function atualizarProcessoSemRemoverPartes(int $id, array $data, array $novasPartes = []): void
    {
        if ($data !== []) {
            $this->repository->salvarProcesso($data, $id);
        }

        foreach ($novasPartes as $parte) {
            $nome = trim((string) ($parte['nome'] ?? ''));
            $polo = $parte['polo'] ?? null;
            if ($nome !== '' && in_array($polo, ['A', 'P'], true)) {
                $this->partiesRepository->salvarParte(['nome' => $nome, 'polo' => $polo], $id);
            }
        }

        $this->repository->limparCacheDetalhesProcesso($id);
    }

    /**
     * Agenda a criação da pasta do Google Drive para o processo.
     */
    private function agendarSincronizacaoDrive(int $processoId): void
    {
        $jobModel = new \App\Models\DriveSyncJobModel();
        
        $existing = $jobModel->where('entity_type', 'processo')
                             ->where('entity_id', $processoId)
                             ->where('status', 'pending')
                             ->first();
        
        if (!$existing) {
            $jobModel->insert([
                'entity_type' => 'processo',
                'entity_id'   => $processoId,
                'status'      => 'pending'
            ]);
        }
    }

    /**
     * Salva uma anotação vinculada a um processo.
     *
     * @param array $data Os dados da anotação.
     */
    public function salvarAnotacao(array $data): void
    {
        $this->repository->salvarAnotacao($data);
    }

    /**
     * Remove uma anotação vinculada a um processo.
     *
     * @param int $anotacaoId ID da anotação.
     * @param int $processoId ID do processo.
     * @return bool True se excluído com sucesso.
     */
    public function removerAnotacao(int $anotacaoId, int $processoId): bool
    {
        return $this->repository->removerAnotacao($anotacaoId, $processoId);
    }

    /**
     * Adiciona ou remove uma etiqueta de um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $etiquetaId O ID da etiqueta.
     * @param bool $adicionar True para adicionar a etiqueta, false para remover.
     * @param bool $clearCache Se true, limpa o cache do processo.
     * @return bool True se a operação foi bem-sucedida, false caso contrário.
     */
    public function gerenciarEtiquetas(int $processoId, int $etiquetaId, bool $adicionar, bool $clearCache = true): bool
    {
        return $this->repository->gerenciarEtiqueta($processoId, $etiquetaId, $adicionar, $clearCache);
    }

    /**
     * Gerencia múltiplas etiquetas para múltiplos processos de forma eficiente.
     *
     * @param array $processoIds IDs dos processos.
     * @param array $etiquetaIds IDs das etiquetas.
     * @param bool $adicionar True para adicionar, false para remover.
     * @return void
     */
    public function gerenciarEtiquetasEmLote(array $processoIds, array $etiquetaIds, bool $adicionar): void
    {
        foreach ($processoIds as $processoId) {
            foreach ($etiquetaIds as $etiquetaId) {
                if (!empty($etiquetaId)) {
                    $this->repository->gerenciarEtiqueta((int) $processoId, (int) $etiquetaId, $adicionar, false);
                }
            }
            // Limpa o cache uma vez por processo após processar todas as etiquetas dele
            $this->repository->limparCacheDetalhesProcesso((int) $processoId);
        }
    }

    /**
     * Exclui um processo e todos os seus registros relacionados.
     *
     * Realiza uma exclusão lógica ou física, dependendo da configuração do repositório.
     *
     * @param int $id O ID do processo a ser excluído.
     * @return bool True se a exclusão foi bem-sucedida, false caso contrário.
     */
    public function deletarProcesso(int $id): bool
    {
        return $this->repository->deletarProcesso($id);
    }

    /**
     * Obtém o ID de um processo a partir do seu número.
     *
     * @param string $numeroProcesso O número do processo.
     * @return int|null O ID do processo se encontrado, ou null.
     */
    public function getIdporNumeroProcesso(string $numeroProcesso): ?int
    {
        return $this->repository->getIdporNumeroProcesso($numeroProcesso);
    }

    /**
     * Obtém o número do processo a partir do seu ID.
     *
     * @param int $id O ID do processo.
     * @return array|null O número do processo se encontrado, ou null.
     */
    public function getNumeroProcessoPorId(int $id)
    {
        return $this->repository->getNumeroProcessoPorId($id);
    }

    /**
     * Busca processos para autocomplete via AJAX.
     *
     * @param string $query O termo de busca digitado pelo usuário.
     * @return array Lista de processos correspondentes (geralmente ID e número/título).
     */
    public function getProcessoPorAjax(string $query): array
    {
        $query = trim($query);
        if (empty($query) || strlen($query) < 2) {
            return [
                'success' => false,
                'message' => 'Digite pelo menos 2 caracteres para buscar.',
                'data' => [],
            ];
        }

        try {
            return [
                'success' => true,
                'message' => null,
                'data' => $this->repository->searchForAutocomplete($query),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao buscar processos: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Salva um movimento processual.
     *
     * @param array $data Os dados do movimento.
     */
    public function salvarMovimento(array $data): void
    {
        $this->repository->salvarMovimento($data);
    }

    /**
     * Verifica se um processo já existe pelo seu número não formatado.
     *
     * @param string $numeroProcesso O número do processo (sem máscara).
     * @return array|null Os dados do processo se ele existir, ou null caso contrário.
     */
    public function processoJaExiste(string $numeroProcesso): ?array
    {
        return $this->repository->verificarProcessoExistente($numeroProcesso);
    }

    /**
     * Salva um vínculo entre dois processos.
     *
     * @param array $data Os dados do vínculo.
     */
    public function salvarVinculo(array $data): void
    {
        $this->linksRepository->salvarVinculo($data);
    }

    /**
     * Busca um vínculo específico pelo ID.
     *
     * @param int $id O ID do registro de vínculo.
     * @return array|null Dados do vínculo ou null se não encontrado.
     */
    public function getVinculoById(int $id): ?array
    {
        return $this->linksRepository->getVinculoById($id);
    }

    /**
     * Remove um vínculo entre processos.
     *
     * @param int $id O ID do registro de vínculo.
     */
    public function excluirVinculo(int $id): void
    {
        $this->linksRepository->excluirVinculo($id);
    }

    /********************* MÉTODOS RELACIONADOS AOS OBJETOS DO PROCESSO *********************/

    /**
     * Vincula um objeto (bem/direito) a um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     */
    public function vincularObjetoProcesso(int $processoId, int $objetoId): void
    {
        $this->objectsRepository->vincularProcessoObjeto($processoId, $objetoId);
    }

    /**
     * Desvincula um objeto de um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     */
    public function desvincularObjetoProcesso(int $processoId, int $objetoId): void
    {
        $this->objectsRepository->desvincularObjetoProcesso($processoId, $objetoId);
    }

    /**
     * Salva um objeto (bem/direito).
     *
     * @param array $dados Os dados do objeto.
     * @return int O ID do objeto salvo.
     */
    public function salvarObjeto(array $dados): int
    {
        return $this->objectsRepository->salvarObjeto($dados);
    }

    /**
     * Obtém os dados de um objeto pelo ID.
     *
     * @param int $id O ID do objeto.
     * @return array|null Os dados do objeto ou null se não encontrado.
     */
    public function obterObjeto(int $id): ?array
    {
        return $this->objectsRepository->obterObjeto($id);
    }

    /**
     * Lista todos os objetos cadastrados.
     *
     * @return array Uma lista de todos os objetos.
     */
    public function listarObjetos(): array
    {
        return $this->objectsRepository->listarObjetos();
    }

    /**
     * Exclui um objeto.
     *
     * @param int $id O ID do objeto a ser excluído.
     */
    public function deletarObjeto(int $id): void
    {
        $this->objectsRepository->deletarObjeto($id);
    }

    /**
     * Lista apenas os IDs dos processos com base em filtros.
     *
     * Útil para operações em lote ou consultas leves.
     *
     * @param string|null $search Termo de busca.
     * @param string $sortField Campo para ordenação.
     * @param string $sortOrder Ordem de classificação (ASC/DESC).
     * @param int|null $encerrado Status de encerramento.
     * @param int|null $clienteId ID do cliente para filtrar.
     * @param array|null $filtros Filtros adicionais.
     * @return array Um array contendo os IDs dos processos encontrados.
     */
    public function listarProcessosIds(
        ?string $search,
        string $sortField,
        string $sortOrder,
        ?int $encerrado,
        ?int $clienteId = null,
        ?array $filtros = []
    ): array {
        $criteria = new ProcessoSearchCriteria([
            'search' => $search,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'encerrado' => $encerrado,
            'filtrosExtras' => array_merge($filtros, ['cliente_id' => $clienteId]),
            'incluirArquivados' => isset($filtros['arquivados']) && $filtros['arquivados'] === '1',
        ]);

        return $this->repository->getProcessosIds($criteria);
    }
    /**
     * Adiciona uma parte a um processo.
     *
     * @param int $processoId ID do processo.
     * @param string $nome Nome da parte.
     * @param string $polo Polo da parte ('A' ou 'P').
     * @return void
     */
    public function adicionarParte(int $processoId, string $nome, string $polo): void
    {
        $this->partiesRepository->salvarParte(['nome' => $nome, 'polo' => $polo], $processoId);
        $this->repository->limparCacheDetalhesProcesso($processoId);
    }

    /**
     * Remove uma parte de um processo.
     *
     * @param int $processoId ID do processo.
     * @param int $parteId ID da parte.
     * @return bool True se removido com sucesso.
     */
    public function removerParte(int $processoId, int $parteId): bool
    {
        return $this->partiesRepository->removerParteDoProcesso($processoId, $parteId);
    }

    /**
     * Gera uma petição usando IA baseada nos dados do processo.
     *
     * @param int $processoId ID do processo.
     * @param string $tipoPeticao Tipo de petição a ser gerada.
     * @return string O texto da petição em Markdown.
     * @throws Exception
     */
        public function gerarPeticao(int $processoId, string $tipoPeticao = 'Petição Inicial'): string
    {
        $processo = $this->buscarDetalhesProcesso($processoId);

        if (!$processo) {
            throw new Exception("Processo não encontrado.");
        }

        return $this->aiService->generatePetition($tipoPeticao, $processo);
    }

    /**
     * Counts the processes matching the given search filters.
     */
    public function contarProcessos(
        ?string $search = null,
        ?int $encerrado = null,
        ?int $clienteId = null,
        ?array $filtros = []
    ): int {
        $criteria = new ProcessoSearchCriteria([
            'search' => $search,
            'encerrado' => $encerrado,
            'filtrosExtras' => array_merge($filtros ?? [], ['cliente_id' => $clienteId]),
        ]);

        return $this->repository->contarProcessos($criteria);
    }

    /**
     * Retorna um resumo geral com as contagens de processos ativos e encerrados.
     *
     * @param int|null $clienteId Opcionalmente filtra por cliente.
     * @return array [ativos, encerrados]
     */
    public function getResumoGeral(?int $clienteId = null): array
    {
        return $this->statisticsRepository->getResumoGeral($clienteId);
    }

    /**
     * Retorna as contagens de processos por status (ativos, arquivados, encerrados) para a
     * barra de estatísticas da listagem, em uma única consulta agregada.
     *
     * @param int|null $clienteId Opcionalmente restringe a contagem a um único cliente.
     * @return array{ativos: int, arquivados: int, encerrados: int, total: int}
     */
    public function getContadoresStatus(?int $clienteId = null): array
    {
        return $this->statisticsRepository->getContadoresStatus($clienteId);
    }

    /**
     * Busca os últimos movimentos processuais com dados do processo, para o widget lateral
     * da listagem.
     *
     * @param int      $limit     Quantidade máxima de movimentos a retornar.
     * @param int|null $clienteId Opcionalmente restringe aos processos de um único cliente.
     * @return array
     */
    public function getUltimosMovimentosComProcesso(int $limit = 5, ?int $clienteId = null): array
    {
        return $this->statisticsRepository->getUltimosMovimentosComProcesso($limit, $clienteId);
    }

    /**
     * Busca as últimas intimações registradas, para o widget lateral da listagem.
     *
     * @param int      $limit     Quantidade máxima de intimações a retornar.
     * @param int|null $clienteId Opcionalmente restringe aos processos de um único cliente.
     * @return array
     */
    public function getUltimasIntimacoesComProcesso(int $limit = 5, ?int $clienteId = null): array
    {
        return $this->statisticsRepository->getUltimasIntimacoesComProcesso($limit, $clienteId);
    }




    ###### Funções relacionadas aos Arquivamentos ######

    public function arquivarProcesso(int $processoId): void
    {
        $this->repository->arquivarProcesso($processoId);
    }

    public function desarquivarProcesso(int $processoId): void
    {
        $this->repository->desarquivarProcesso($processoId);
    }

    /**
     * Encerra o processo com a data atual e, se ele ainda não estava encerrado,
     * gera o Relatório Final (IA) e notifica o responsável.
     *
     * @param int $processoId ID do processo.
     * @return string Data de encerramento gravada (Y-m-d).
     */
    public function encerrarProcesso(int $processoId): string
    {
        $dataEncerramento = date('Y-m-d');

        $this->salvarProcessoRapido([
            'encerrado'         => 1,
            'data_encerramento' => $dataEncerramento,
        ], $processoId);

        return $dataEncerramento;
    }

    /**
     * Reabre um processo encerrado, limpando a data de encerramento.
     *
     * @param int $processoId ID do processo.
     */
    public function reabrirProcesso(int $processoId): void
    {
        $this->repository->salvarProcesso([
            'encerrado'         => 0,
            'data_encerramento' => null,
        ], $processoId);
    }

    public function atualizarParteIsRepresented(int $idProcesso, int $idParte, int $isRepresented): bool
    {
        return $this->partiesRepository->atualizarParteIsRepresented($idProcesso, $idParte, $isRepresented);
    }

    /**
     * Atribui um processo a um usuário (advogado).
     *
     * @param int $processoId ID do processo.
     * @param int $usuarioId ID do usuário.
     * @throws Exception Se o usuário não for encontrado ou não for advogado.
     */
    public function atribuirProcesso(int $processoId, int $usuarioId): void
    {
        $usuario = $this->usuariosService->getById($usuarioId);

        if (!$usuario) {
            throw new Exception("Usuário não encontrado.");
        }

        if ((int) ($usuario['is_lawyer'] ?? 0) !== 1) {
            throw new Exception("O usuário selecionado deve possuir o perfil de advogado.");
        }

        $this->repository->update($processoId, ['responsavel_id' => $usuarioId]);
    }

    /**
     * Verifica potenciais conflitos de interesse para uma parte.
     *
     * @param string $nome Nome da parte.
     * @param int|null $processoId ID do processo atual (opcional).
     * @return array Resultado da verificação.
     */
    public function checkConflictOfInterest(string $nome, ?int $processoId = null): array
    {
        $conflitos = $this->partiesRepository->findPotentialConflicts($nome, $processoId);

        if (empty($conflitos)) {
            return [
                'has_conflict' => false,
                'message' => 'Nenhum conflito de interesse detectado.',
                'conflicts' => []
            ];
        }

        $formattedConflicts = array_map(function($c) {
            $polo = $c['polo'] === 'A' ? 'Polo Ativo' : 'Polo Passivo';
            $status = (int)$c['is_represented'] === 1 ? 'Cliente Representado' : 'Parte Contrária';
            return [
                'processo_id' => $c['id_processo'],
                'numero_processo' => $c['numero_processo'],
                'titulo_processo' => $c['titulo_processo'],
                'nome_encontrado' => $c['nome'],
                'contexto' => "{$polo} - {$status}"
            ];
        }, $conflitos);

        return [
            'has_conflict' => true,
            'message' => 'Atenção: Potenciais conflitos de interesse encontrados.',
            'conflicts' => $formattedConflicts
        ];
    }

    /**
     * Obtém os IDs do processo anterior e próximo para navegação.
     *
     * @param int $id ID do processo atual.
     * @param array|null $listaIds Lista de IDs da sessão (opcional).
     * @return array Array com 'prev' e 'next'.
     */
    public function getNavigationIds(int $id, ?array $listaIds = null): array
    {
        $previousProcessId = null;
        $nextProcessId = null;

        if (!empty($listaIds)) {
            $currentIndex = array_search($id, $listaIds);
            if ($currentIndex !== false) {
                if ($currentIndex > 0) {
                    $previousProcessId = $listaIds[$currentIndex - 1];
                }
                if ($currentIndex < count($listaIds) - 1) {
                    $nextProcessId = $listaIds[$currentIndex + 1];
                }
            }
        } else {
            // Fallback: busca via repositório
            $clienteService = new \App\Services\ClienteService();
            $clienteId = $clienteService->getUsuarioCliente();

            return $this->repository->getNavigationIds($id, $clienteId);
        }

        return [
            'prev' => $previousProcessId ? (int)$previousProcessId : null,
            'next' => $nextProcessId ? (int)$nextProcessId : null
        ];
    }
}
