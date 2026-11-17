<?php

declare(strict_types=1);

namespace App\Services\Checklist;

use App\Repositories\Checklist\ChecklistRepository;
use App\Services\BaseService;
use Exception;

/**
 * Serviço para gestão de Checklists aplicados a Casos ou Processos.
 *
 * @property ChecklistRepository $repository
 */
class ChecklistService extends BaseService
{

    /**
     * @var TemplateService
     */
    protected TemplateService $templateService;

    /**
     * @var \App\Repositories\CasosRepository
     */
    protected \App\Repositories\CasosRepository $casosRepository;

    /**
     * @var \App\Services\ProcessosService
     */
    protected \App\Services\ProcessosService $processoService;

    /**
     * @var \App\Interfaces\AIServiceInterface
     */
    protected \App\Interfaces\AIServiceInterface $aiService;

    /**
     * @var \App\Services\TarefasService
     */
    protected \App\Services\TarefasService $tarefasService;

    /**
     * Construtor do ChecklistService com injeção de dependência.
     *
     * @param ChecklistRepository|null $repository
     * @param TemplateService|null $templateService
     * @param \App\Repositories\CasosRepository|null $casosRepository
     * @param \App\Services\ProcessosService|null $processoService
     * @param \App\Interfaces\AIServiceInterface|null $aiService
     * @param \App\Services\TarefasService|null $tarefasService
     */
    public function __construct(
        ?ChecklistRepository $repository = null,
        ?TemplateService $templateService = null,
        ?\App\Repositories\CasosRepository $casosRepository = null,
        ?\App\Services\ProcessosService $processoService = null,
        ?\App\Interfaces\AIServiceInterface $aiService = null,
        ?\App\Services\TarefasService $tarefasService = null
    ) {
        $this->repository       = $repository ?? new ChecklistRepository();
        $this->templateService  = $templateService ?? new TemplateService();
        $this->casosRepository  = $casosRepository ?? new \App\Repositories\CasosRepository();
        $this->processoService  = $processoService ?? new \App\Services\ProcessosService();
        $this->aiService        = $aiService ?? service('AIService');
        $this->tarefasService   = $tarefasService ?? new \App\Services\TarefasService();
    }

    public function listarPorEntidade(string $type, int $id): array
    {
        $checklists = [];
        if ($type === 'caso') {
            $checklists = $this->repository->getByCaso($id);
        } elseif ($type === 'processo') {
            $checklists = $this->repository->getByProcesso($id);
        }

        if (empty($checklists)) {
            return [];
        }

        // Extrai todos os IDs de checklists para busca em lote (O(1) queries em vez de O(N))
        $checklistIds = array_column($checklists, 'id');
        $itensPorChecklist = $this->repository->getItensBatch($checklistIds);

        // Associa os itens aos seus respectivos checklists em memória
        foreach ($checklists as &$checklist) {
            $checklistId = (int) $checklist['id'];
            $checklist['itens'] = $itensPorChecklist[$checklistId] ?? [];
        }

        return $checklists;
    }

    public function listarChecklistsDoCaso(int $id): array
    {
        return $this->listarPorEntidade('caso', $id);
    }

    public function listarTodos(int $page = 1, int $perPage = 20, ?int $clienteId = null): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->repository->contarTodos($clienteId);
        return [
            'data'  => $this->repository->listarTodos($perPage, $offset, $clienteId),
            'total' => $total,
            'pager' => service('pager')->makeLinks($page, $perPage, $total, 'default_full'),
        ];
    }

    public function buscarChecklist(int $id): array
    {
        $checklist = $this->repository->getById($id);
        if (!$checklist) {
            throw new Exception('Checklist não encontrado');
        }
        return $checklist;
    }

    public function aplicarTemplate(string $type, int $targetId, int $templateId, int $usuarioId): int
    {
        if ($type === 'caso') {
            $entidade = $this->casosRepository->buscarPorId($targetId);
            $params = ['caso_id' => $targetId];
        } else {
            $entidade = $this->processoService->buscarDetalhesProcesso($targetId);
            $params = ['processo_id' => $targetId];
        }

        if (!$entidade) {
            throw new Exception('Entidade não encontrada');
        }

        $checklistId = $this->repository->aplicarTemplate($params, $templateId, $usuarioId);
        if (!$checklistId) {
            throw new Exception('Erro ao aplicar template');
        }

        return $checklistId;
    }

    public function adicionarItemAvulso(int $checklistId, array $dados, int $usuarioId): int
    {
        $checklist = $this->buscarChecklist($checklistId);
        if ($checklist['status'] === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        $itemId = $this->repository->adicionarItemAvulso($checklistId, $dados, $usuarioId);
        if (!$itemId) {
            throw new Exception('Erro ao adicionar item');
        }

        return $itemId;
    }

    public function marcarItem(int $itemId, bool $cumprido, int $usuarioId, bool $syncTask = true): bool
    {
        $item = $this->repository->getItemComStatus($itemId);
        if (!$item) {
            throw new Exception('Item não encontrado');
        }
        if ($item->checklist_status === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        return $this->repository->marcarItem($itemId, $cumprido, $usuarioId, $syncTask);
    }

    public function buscarItem(int $itemId): ?object
    {
        return $this->repository->getItemComStatus($itemId);
    }

    public function concluirChecklist(int $checklistId, int $usuarioId): bool
    {
        $checklist = $this->buscarChecklist($checklistId);
        if ($checklist['status'] === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        return $this->repository->concluirChecklist($checklistId, $usuarioId);
    }

    public function getHistorico(int $checklistId): array
    {
        return $this->repository->getHistorico($checklistId);
    }

    public function getDadosImpressao(int $checklistId): array
    {
        return $this->repository->getDadosImpressao($checklistId);
    }

    public function getTemplatesDisponiveis(string $type, int $id): array
    {
        $templates = $this->templateService->listarTemplates(1, 999);
        $field = ($type === 'caso') ? 'caso_id' : 'processo_id';
        $idsAplicados = $this->repository->getTemplatesAplicados($field, $id);

        $disponiveis = [];
        foreach ($templates['data'] as $template) {
            if (!in_array($template['id'], $idsAplicados)) {
                $disponiveis[] = $template;
            }
        }
        return $disponiveis;
    }

    /**
     * Cria um checklist avulso em uma entidade.
     */
    public function criarChecklistAvulso(string $type, int $targetId, string $titulo, int $usuarioId): int
    {
        if ($type === 'caso') {
            $entidade = $this->casosRepository->buscarPorId($targetId);
        } else {
            $entidade = $this->processoService->buscarDetalhesProcesso($targetId);
        }

        if (!$entidade) {
            throw new Exception('Entidade não encontrada');
        }

        $checklistId = $this->repository->criarChecklistAvulso($type, $targetId, $titulo, $usuarioId);
        if (!$checklistId) {
            throw new Exception('Erro ao criar checklist');
        }

        return $checklistId;
    }

    /**
     * Atualiza o título de um checklist.
     */
    public function atualizarChecklist(int $id, string $titulo, int $usuarioId): bool
    {
        $checklist = $this->buscarChecklist($id);
        if ($checklist['status'] === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        return $this->repository->update($id, ['titulo' => $titulo]);
    }

    /**
     * Atualiza um item do checklist.
     */
    public function atualizarItem(int $itemId, array $dados, int $usuarioId): bool
    {
        $item = $this->repository->getItemComStatus($itemId);
        if (!$item) {
            throw new Exception('Item não encontrado');
        }
        if ($item->checklist_status === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        return $this->repository->atualizarItem($itemId, $dados, $usuarioId);
    }

    /**
     * Exclui um item de um checklist.
     */
    public function excluirItem(int $itemId, int $usuarioId): bool
    {
        $item = $this->repository->getItemComStatus($itemId);
        if (!$item) {
            throw new Exception('Item não encontrado');
        }
        if ($item->checklist_status === 'concluido') {
            throw new Exception('Checklist já concluído');
        }

        return $this->repository->excluirItem($itemId, $usuarioId);
    }

    /** 
     * Exclui um checklist do sistema.
     */
    public function excluirChecklist(int $id): bool
    {
        return $this->repository->excluirChecklist($id);
    }

    /**
     * Sugere itens de checklist usando IA baseado no contexto da entidade.
     */
    public function sugerirChecklistComIA(string $type, int $id): array
    {
        $contexto = $this->getContextoEntidade($type, $id);
        return $this->aiService->generateChecklist($contexto);
    }

    /**
     * Monta uma string de contexto baseada na entidade.
     */
    private function getContextoEntidade(string $type, int $id): string
    {
        if ($type === 'caso') {
            $caso = $this->casosRepository->buscarPorId($id);
            if (!$caso) {
                return "";
            }
            return "Caso: {$caso['titulo']}\nTipo: {$caso['tipo']}\nDescrição: {$caso['descricao']}";
        }

        if ($type === 'processo') {
            $detalhes = $this->processoService->buscarDetalhesProcesso($id);
            $proc = $detalhes['processo'] ?? null;
            if (!$proc) {
                return "";
            }
            return "Processo: {$proc['numeroprocessocommascara']}\nTítulo: {$proc['titulo_processo']}\nComentários: " . ($proc['comentario'] ?? '');
        }

        return "";
    }

    /**
     * Salva as sugestões da IA como um novo checklist.
     */
    public function salvarSugestoesIA(int $targetId, string $type, array $sugestoes, int $usuarioId): int
    {
        return $this->repository->salvarSugestoesIA($targetId, $type, $sugestoes, $usuarioId);
    }

    /**
     * Retorna o cliente_id associado ao checklist.
     */
    public function getClienteIdByChecklistId(int $id): ?int
    {
        return $this->repository->getClienteIdByChecklistId($id);
    }

    /**
     * Retorna o cliente_id associado ao item do checklist.
     */
    public function getClienteIdByItemId(int $id): ?int
    {
        return $this->repository->getClienteIdByItemId($id);
    }

    /**
     * Retorna o cliente_id associado a um Caso ou Processo.
     */
    public function getClienteIdByEntidade(string $type, int $id): ?int
    {
        if ($type === 'caso') {
            $caso = $this->casosRepository->buscarPorId($id);
            return $caso ? (int)$caso['cliente_id'] : null;
        }

        if ($type === 'processo') {
            $detalhes = $this->processoService->buscarDetalhesProcesso($id);
            $proc = $detalhes['processo'] ?? null;
            return $proc ? (int)$proc['cliente_id'] : null;
        }

        return null;
    }
}
