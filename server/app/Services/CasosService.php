<?php

namespace App\Services;

use App\Repositories\CasosRepository;
use App\Repositories\ProcessosRepository;
use App\Interfaces\AIServiceInterface;
use Exception;

/**
 * Serviço responsável pelo gerenciamento de casos.
 *
 * Centraliza a lógica de negócios para casos e a transformação em processos.
 *
 * @version 1.0.1
 * @author Cardoso
 */
class CasosService extends BaseService
{
    /**
     * Blocos do detalhe do caso que pertencem a outros módulos:
     * chave => [slug do módulo, permissões aceitas]. Ver VisibilidadeModulosService.
     */
    public const BLOCOS_DETALHE = [
        // Rotas de checklist exigem só login: nem todo grupo tem checklist.*.
        'checklists' => ['checklist'],
    ];


    /**
     * @var ProcessosRepository
     */
    protected ProcessosRepository $processosRepository;

    /**
     * @var AIServiceInterface
     */
    protected $aiService;
    protected $checklistService;

    public function __construct(
        ?CasosRepository $repository = null,
        ?ProcessosRepository $processosRepository = null,
        ?AIServiceInterface $aiService = null,
        ?\App\Services\Checklist\ChecklistService $checklistService = null
    ) {
        $this->repository = $repository ?? new CasosRepository();
        $this->processosRepository = $processosRepository ?? new ProcessosRepository();
        $this->aiService = $aiService ?? service('AIService');
        $this->checklistService = $checklistService ?? new \App\Services\Checklist\ChecklistService();
    }

    /**
     * Lista casos com filtros.
     *
     * @param string|null $search
     * @param string $sortField
     * @param string $sortOrder
     * @param int $perPage
     * @param array $filtros
     * @return array
     */
    public function listarCasos(?string $search, string $sortField = 'created_at', string $sortOrder = 'DESC', int $perPage = 25, array $filtros = [], int $page = 1): array
    {
        return $this->repository->buscarCasos($search, $sortField, $sortOrder, $perPage, $filtros, $page);
    }


    /**
     * Busca um caso pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function buscarCaso(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }


    /**
     * Salva um caso.
     *
     * @param array $data
     * @param int|null $id
     * @return int|bool
     */
    public function salvarCaso(array $data, ?int $id = null)
    {
        // Remove formatação de valores monetários se houver
        if (isset($data['valor_causa'])) {
            $data['valor_causa'] = preg_replace('/[^\d,]/', '', $data['valor_causa']);
            $data['valor_causa'] = str_replace(',', '.', $data['valor_causa']);
        }

        return $this->repository->salvar($data, $id);
    }

    /**
     * Exclui um caso.
     *
     * @param int $id
     * @return bool
     */
    public function excluirCaso(int $id): bool
    {
        return $this->repository->excluir($id);
    }

    /**
     * Transforma um caso judicial em um processo.
     *
     * @param int $casoId
     * @param string $numeroProcesso
     * @param string|null $dataDistribuicao
     * @return int ID do novo processo.
     * @throws Exception
     */
    public function transformarEmProcesso(int $casoId, string $numeroProcesso, ?string $dataDistribuicao = null): int
    {
        $caso = $this->repository->buscarPorId($casoId);

        if (!$caso) {
            throw new Exception("Caso não encontrado.");
        }

        if ($caso['tipo'] !== 'Judicial') {
            throw new Exception("Apenas casos judiciais podem ser transformados em processos.");
        }

        if (!empty($caso['processo_id'])) {
            throw new Exception("Este caso já foi transformado em processo.");
        }

        // Remove formatação do número do processo
        $numeroLimpo = preg_replace('/\D/', '', $numeroProcesso);

        // Prepara dados para o processo
        $dadosProcesso = [
            'numero_processo' => $numeroLimpo,
            'titulo_processo' => $caso['titulo'],
            'cliente_id'      => $caso['cliente_id'],
            'comentario'      => $caso['descricao'],
            'ativo'           => 1,
            'encerrado'       => 0,
            'ramo_direito'    => $caso['area_do_direito'] ?? null,
        ];

        if ($dataDistribuicao) {
            $dadosProcesso['dataDistribuicao'] = $dataDistribuicao;
        }

        // Cria o processo
        $processoId = $this->processosRepository->salvarProcesso($dadosProcesso, null);

        // Atualiza o caso
        $this->repository->salvar([
            'status'          => 'Transformado em Processo',
            'processo_id'     => $processoId,
            'transformado_em' => date('Y-m-d H:i:s'),
        ], $casoId);

        // Migração de Checklists (Continuidade de Processo)
        $this->migrarChecklistsParaProcesso($casoId, $processoId);

        return $processoId;
    }

    /**
     * Migra os checklists de um caso para um processo.
     *
     * @param int $casoId
     * @param int $processoId
     * @return void
     */
    private function migrarChecklistsParaProcesso(int $casoId, int $processoId): void
    {
        try {
            $checklists = $this->checklistService->listarChecklistsDoCaso($casoId);

            if (empty($checklists)) {
                return;
            }

            $db = \Config\Database::connect();
            foreach ($checklists as $chk) {
                // Vincula o checklist existente também ao processo
                // Isso garante que o histórico e itens sejam mantidos e visíveis em ambos
                $db->table('chk_casos_checklists')
                   ->where('id', $chk['id'])
                   ->update(['processo_id' => $processoId]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Erro ao migrar checklists do Caso ' . $casoId . ' para Processo ' . $processoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Gera um estudo de caso com IA.
     *
     * @param int $casoId
     * @return string
     * @throws Exception
     */
        public function gerarEstudoCaso(int $casoId): string
    {
        $caso = $this->repository->buscarPorId($casoId);

        if (!$caso) {
            throw new Exception("Caso não encontrado.");
        }

        $estudo = $this->aiService->generateCaseStudy($caso);

        // Salva o estudo no banco de dados
        $this->repository->update($casoId, ['estudo_caso' => $estudo]);

        return $estudo;
    }

    /**
     * Salva o conteúdo do estudo de caso editado manualmente pelo advogado.
     *
     * @param int $casoId
     * @param string $estudoCaso
     * @return bool
     */
    public function salvarEstudoCaso(int $casoId, string $estudoCaso): bool
    {
        return (bool) $this->repository->salvar(['estudo_caso' => $estudoCaso], $casoId);
    }

    /**
     * Busca casos recentes.
     *
     * @param int $limite
     * @return array
     */
    public function buscarCasosRecentes(int $limite = 5): array
    {
        return $this->repository->buscarRecentes($limite);
    }

    /**
     * Obtém estatísticas gerais de casos.
     *
     * @return array
     */
    public function obterEstatisticasGerais(): array
    {
        return $this->repository->obterEstatisticas();
    }

    /**
     * Obtém estatísticas de casos para um cliente específico.
     *
     * @param int $clienteId
     * @return array
     */
    public function obterEstatisticasCliente(int $clienteId): array
    {
        $stats = $this->repository->obterEstatisticas($clienteId);

        // Mapeia para os nomes de campos esperados pelo controlador legado/dashboard
        return [
            'casos'            => $stats['total'],
            'casos_ativos'     => $stats['ativos'],
            'casos_judiciais'  => $stats['judiciais'],
            'casos_encerrados' => $stats['encerrados'],
        ];
    }

    /**
     * Exclui múltiplos casos.
     *
     * @param array $ids
     * @return int Número de casos excluídos
     */
    public function excluirVarios(array $ids): int
    {
        return $this->repository->excluirVarios($ids);
    }

    /**
     * Arquiva múltiplos casos.
     *
     * @param array $ids
     * @return bool
     */
    public function arquivarVarios(array $ids): bool
    {
        return $this->repository->atualizarStatusVarios($ids, 'Encerrado');
    }

    /**
     * Ativa múltiplos casos.
     *
     * @param array $ids
     * @return bool
     */
    public function ativarVarios(array $ids): bool
    {
        return $this->repository->atualizarStatusVarios($ids, 'Ativo');
    }

    /**
     * Verifica se um número de processo já existe.
     *
     * @param string $numero
     * @return bool
     */
    public function verificarNumeroProcessoExiste(string $numero): bool
    {
        $processo = $this->processosRepository->verificarProcessoExistente($numero);
        return !empty($processo);
    }

    /**
     * Busca um processo completo pelo número.
     *
     * @param string $numero Número do processo sem formatação.
     * @return array|null Dados do processo ou null se não encontrado.
     */
    public function buscarProcessoPorNumero(string $numero): ?array
    {
        return $this->processosRepository->verificarProcessoExistente($numero);
    }

    /**
     * Busca casos por cliente.
     *
     * @param int $clienteId
     * @return array
     */
    public function buscarPorCliente(int $clienteId, ?string $search = null, string $sortField = 'created_at', string $sortOrder = 'DESC', int $perPage = 25, array $filtros = [], int $page = 1): array
    {
        return $this->repository->buscarPorCliente($clienteId, $search, $sortField, $sortOrder, $perPage, $filtros, $page);
    }

    /**
     * Atualiza apenas o status de um caso.
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function atualizarStatus(int $id, string $status): bool
    {
        return $this->repository->salvar(['status' => $status], $id);
    }

    /**
     * Duplica um caso existente.
     *
     * @param int $id
     * @return int ID do novo caso
     * @throws Exception
     */
    public function duplicarCaso(int $id): int
    {
        $caso = $this->repository->buscarPorId($id);

        if (!$caso) {
            throw new Exception("Caso não encontrado.");
        }

        // Remove campos que não devem ser duplicados
        unset($caso['id_caso']);
        unset($caso['created_at']);
        unset($caso['updated_at']);
        unset($caso['processo_id']);
        unset($caso['transformado_em']);
        unset($caso['estudo_caso']);

        // Adiciona indicador de cópia no título
        $caso['titulo'] = $caso['titulo'] . ' (Cópia)';
        $caso['status'] = 'Ativo';

        return $this->repository->salvar($caso);
    }

    /**
     * Exporta casos para CSV.
     *
     * @param array $filtros
     * @return string
     */
    public function exportarParaCsv(array $filtros = []): string
    {
        $casos = $this->repository->buscarParaExportacao($filtros);

        $csv = "ID;Título;Cliente;Tipo;Status;Data Criação\n";

        foreach ($casos as $caso) {
            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s\n",
                $caso['id_caso'],
                $this->escapeCsv($caso['titulo']),
                $this->escapeCsv($caso['cliente_nome'] ?? 'N/A'),
                $caso['tipo'],
                $caso['status'],
                date('d/m/Y', strtotime($caso['created_at']))
            );
        }

        return $csv;
    }

    /**
     * Escapa valores para CSV.
     *
     * @param string $value
     * @return string
     */
    private function escapeCsv(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Busca documentos relacionados ao caso.
     *
     * @param int $casoId
     * @return array
     */
    public function buscarDocumentosCaso(int $casoId): array
    {
        return $this->repository->buscarDocumentos($casoId);
    }

    /**
     * Busca histórico do caso.
     *
     * @param int $casoId
     * @return array
     */
    public function buscarHistoricoCaso(int $casoId): array
    {
        return $this->repository->buscarHistorico($casoId);
    }

    /**
     * Adiciona histórico ao caso.
     *
     * @param int $casoId
     * @param string $acao
     * @param string $descricao
     * @param int $usuarioId
     * @return bool
     */
    public function adicionarHistorico(int $casoId, string $acao, string $descricao, int $usuarioId): bool
    {
        return $this->repository->adicionarHistorico([
            'caso_id' => $casoId,
            'acao' => $acao,
            'descricao' => $descricao,
            'usuario_id' => $usuarioId
        ]);
    }

    /**
     * Obtém estatísticas específicas de um caso.
     *
     * @param int $casoId
     * @return array
     */
    public function obterEstatisticasCaso(int $casoId): array
    {
        return $this->repository->obterEstatisticasCaso($casoId);
    }

    /**
     * Busca casos relacionados.
     *
     * @param int $casoId
     * @return array
     */
    public function buscarCasosRelacionados(int $casoId): array
    {
        return $this->repository->buscarRelacionados($casoId);
    }

    /**
     * Busca timeline/histórico detalhado do caso.
     *
     * @param int $casoId
     * @return array
     */
    public function buscarTimelineCaso(int $casoId): array
    {
        return $this->repository->buscarTimeline($casoId);
    }

    /**
     * Realiza uma busca leve de casos (autocomplete).
     *
     * @param string $term Termo de busca
     * @param int $limit Limite de resultados
     * @param int|null $clienteId ID do cliente para filtrar
     * @return array
     */
    public function pesquisarCasos(string $term, int $limit = 10, ?int $clienteId = null): array
    {
        return $this->repository->search($term, $limit, $clienteId);
    }
}
