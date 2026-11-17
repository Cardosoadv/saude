<?php

declare(strict_types=1);

namespace App\Repositories\Checklist;

use App\Models\ChecklistModel;
use App\Models\ItensCasosModel;
use App\Models\ChecklistHistoricoModel;
use App\Repositories\BaseRepository;
use App\Repositories\CasosRepository;
use App\Repositories\ProcessosRepository;
use App\Repositories\TarefasRepository;

/**
 * Repositório para Checklists aplicados a Casos ou Processos.
 */
class ChecklistRepository extends BaseRepository
{
    /**
     * @var ItensCasosModel Modelo de itens de checklist.
     */
    protected ItensCasosModel           $itemCasoModel;

    /**
     * @var ChecklistHistoricoModel Modelo de histórico de checklist.
     */
    protected ChecklistHistoricoModel   $historicoModel;

    /**
     * @var TemplateRepository Repositório de templates.
     */
    protected TemplateRepository        $templateRepository;

    /**
     * @var CasosRepository Repositório de casos.
     */
    protected CasosRepository           $casosRepository;

    /**
     * @var ProcessosRepository Repositório de processos.
     */
    protected ProcessosRepository       $processosRepository;

    /**
     * @var \CodeIgniter\Database\BaseConnection Conexão com o banco de dados.
     */
    protected $db;

    /**
     * @var TarefasRepository Repositório de tarefas.
     */
    protected TarefasRepository $tarefasRepository;

    /**
     * Construtor do repositório.
     */
    public function __construct(
        ?ChecklistModel          $model = null,
        ?ItensCasosModel         $itemCasoModel = null,
        ?ChecklistHistoricoModel $historicoModel = null,
        ?TemplateRepository      $templateRepository = null,
        ?CasosRepository         $casosRepository = null,
        ?ProcessosRepository     $processosRepository = null,
        ?TarefasRepository       $tarefasRepository = null
    ) {
        parent::__construct();
        $this->model                    = $model ?? new ChecklistModel();
        $this->cacheKey                 = 'checklists';
        $this->itemCasoModel            = $itemCasoModel ?? new ItensCasosModel();
        $this->historicoModel           = $historicoModel ?? new ChecklistHistoricoModel();
        $this->templateRepository       = $templateRepository ?? new TemplateRepository();
        $this->casosRepository          = $casosRepository ?? new CasosRepository();
        $this->processosRepository      = $processosRepository ?? new ProcessosRepository();
        $this->tarefasRepository        = $tarefasRepository ?? new TarefasRepository();
        $this->db                       = db_connect();
    }

    /**
     * Retorna checklists de um caso.
     */
    public function getByCaso(int $casoId): array
    {
        return $this->getByEntity('caso_id', $casoId);
    }

    /**
     * Retorna checklists de um processo.
     */
    public function getByProcesso(int $processoId): array
    {
        return $this->getByEntity('processo_id', $processoId);
    }

    /**
     * Método genérico para buscar checklists por entidade.
     */
    private function getByEntity(string $entityField, int $entityId): array
    {
        return $this->remember("{$entityField}_{$entityId}", function () use ($entityField, $entityId) {
            return $this->model->select('chk_casos_checklists.*,
                                   (SELECT COUNT(*) FROM chk_itens_caso WHERE checklist_id = chk_casos_checklists.id) as total_itens,
                                   (SELECT COUNT(*) FROM chk_itens_caso WHERE checklist_id = chk_casos_checklists.id AND cumprido = 1) as itens_cumpridos')
                ->where($entityField, $entityId)
                ->findAll();
        });
    }

    /**
     * Retorna todos os checklists do sistema, com informações resumidas.
     */
    public function listarTodos(int $limit = 20, int $offset = 0, ?int $clienteId = null): array
    {
        $cacheKey = "todos_{$limit}_{$offset}" . ($clienteId ? "_cl{$clienteId}" : "");
        return $this->remember($cacheKey, function () use ($limit, $offset, $clienteId) {
            $query = $this->model->select('chk_casos_checklists.*,
                                   c.titulo as caso_titulo,
                                   p.numeroprocessocommascara as processo_titulo,
                                   cl.nome as cliente_nome,
                                   (SELECT COUNT(*) FROM chk_itens_caso WHERE checklist_id = chk_casos_checklists.id) as total_itens,
                                   (SELECT COUNT(*) FROM chk_itens_caso WHERE checklist_id = chk_casos_checklists.id AND cumprido = 1) as itens_cumpridos')
                ->join('casos c', 'c.id_caso = chk_casos_checklists.caso_id', 'left')
                ->join('processos p', 'p.id_processo = chk_casos_checklists.processo_id', 'left')
                ->join('clientes cl', 'cl.id_cliente = COALESCE(c.cliente_id, p.cliente_id)', 'left');

            if ($clienteId) {
                $query->groupStart()
                      ->where('c.cliente_id', $clienteId)
                      ->orWhere('p.cliente_id', $clienteId)
                      ->groupEnd();
            }

            return $query->orderBy('chk_casos_checklists.created_at', 'DESC')
                ->findAll($limit, $offset);
        });
    }

    /**
     * Conta o total de checklists no sistema.
     */
    public function contarTodos(?int $clienteId = null): int
    {
        if ($clienteId) {
            return $this->model->join('casos c', 'c.id_caso = chk_casos_checklists.caso_id', 'left')
                ->join('processos p', 'p.id_processo = chk_casos_checklists.processo_id', 'left')
                ->groupStart()
                    ->where('c.cliente_id', $clienteId)
                    ->orWhere('p.cliente_id', $clienteId)
                ->groupEnd()
                ->countAllResults();
        }
        return $this->model->countAllResults();
    }

    /**
     * Retorna um checklist pelo ID com seus itens.
     */
    public function getById(int $id): ?array
    {
        return $this->remember('full_' . $id, function () use ($id) {
            $checklist = $this->findById($id);
            if ($checklist) {
                $checklist['itens'] = $this->getItensAgrupados($id);
            }
            return $checklist;
        });
    }

    /**
     * Retorna os itens de um checklist agrupados por seção.
     */
    public function getItensAgrupados(int $checklistId): array
    {
        $itens = $this->getItensSimples($checklistId);

        $agrupados = [];
        foreach ($itens as $item) {
            $secaoTitulo = $item['secao_titulo'];
            if (!isset($agrupados[$secaoTitulo])) {
                $agrupados[$secaoTitulo] = [
                    'titulo' => $secaoTitulo,
                    'itens'  => [],
                ];
            }
            $agrupados[$secaoTitulo]['itens'][] = $item;
        }

        return $agrupados;
    }

    /**
     * Retorna itens de um checklist sem agrupamento.
     */
    public function getItensSimples(int $checklistId): array
    {
        return $this->itemCasoModel->select('chk_itens_caso.*, chk_itens_caso.item_titulo as titulo, chk_itens_caso.observacao as descricao, chk_itens_caso.cumprido as concluido')
                                     ->where('checklist_id', $checklistId)
                                     ->orderBy('ordem', 'ASC')
                                     ->findAll();
    }

    /**
     * Retorna itens de múltiplos checklists de uma vez para evitar N+1.
     * Otimizado: O(1) database query instead of O(N).
     *
     * @param array $checklistIds Array de IDs de checklists.
     * @return array Itens agrupados por ID do checklist.
     */
    public function getItensBatch(array $checklistIds): array
    {
        if (empty($checklistIds)) {
            return [];
        }

        $itens = $this->itemCasoModel->select('chk_itens_caso.*, chk_itens_caso.item_titulo as titulo, chk_itens_caso.observacao as descricao, chk_itens_caso.cumprido as concluido')
            ->whereIn('checklist_id', $checklistIds)
            ->orderBy('ordem', 'ASC')
            ->findAll();

        $agrupados = [];
        foreach ($itens as $item) {
            $id = (int) $item['checklist_id'];
            if (!isset($agrupados[$id])) {
                $agrupados[$id] = [];
            }
            $agrupados[$id][] = $item;
        }

        return $agrupados;
    }

    /**
     * Aplica um template a uma entidade (Caso ou Processo).
     */
    public function aplicarTemplate(array $params, int $templateId, int $usuarioId): int|false
    {
        $template = $this->templateRepository->getById($templateId);
        if (!$template) {
            return false;
        }

        $data = [
            'template_id' => $templateId,
            'titulo'      => $template['titulo'],
            'created_by'  => $usuarioId,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if (isset($params['caso_id'])) $data['caso_id'] = $params['caso_id'];
        if (isset($params['processo_id'])) $data['processo_id'] = $params['processo_id'];

        // Criar o checklist
        $checklistId = $this->create($data);

        if (!$checklistId) {
            return false;
        }

        // Copiar itens do template
        $itensInseridos = [];
        if (isset($template['secoes'])) {
            foreach ($template['secoes'] as $secao) {
                if (isset($secao['itens'])) {
                    foreach ($secao['itens'] as $item) {
                        $itemData = [
                            'checklist_id'      => $checklistId,
                            'secao_original_id' => $secao['id'],
                            'item_template_id'  => $item['id'],
                            'secao_titulo'      => $secao['titulo'],
                            'item_titulo'       => $item['titulo'],
                            'observacao'        => $item['observacao_padrao'] ?? null,
                            'ordem'             => $item['ordem'],
                            'is_avulso'         => 0,
                            'created_by'        => $usuarioId,
                            'created_at'        => date('Y-m-d H:i:s'),
                        ];

                        $itemId = $this->itemCasoModel->insert($itemData);
                        if ($itemId) {
                            $itemData['id'] = $itemId;
                            $itemData['prazo_dias'] = $item['prazo_dias'] ?? 7;
                            $itemData['prioridade'] = $item['prioridade'] ?? 5;
                            $itemData['responsavel_padrao'] = $item['responsavel_padrao'] ?? null;
                            $itensInseridos[] = $itemData;
                        }
                    }
                }
            }
        }

        // Criar tarefas se a flag estiver ativa
        if (!empty($itensInseridos) && ($template['gerar_tarefas'] ?? 1)) {
            $checklistData = $this->findById($checklistId);
            if ($checklistData) {
                $this->criarTarefasDoChecklist($checklistId, $checklistData, $itensInseridos, $usuarioId);
            }
        }

        // Registrar histórico
        $entidadeTxt = isset($data['processo_id']) ? "processo" : "caso";
        $this->historicoModel->insert([
            'checklist_id' => $checklistId,
            'acao'         => 'template_aplicado',
            'observacao'   => "Template '{$template['titulo']}' aplicado ao {$entidadeTxt}",
            'created_by'   => $usuarioId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->clearCache();
        return $checklistId;
    }

    /**
     * Cria tarefas automaticamente para os itens de um checklist.
     */
    public function criarTarefasDoChecklist(int $checklistId, array $checklist, array $itensInseridos, int $usuarioId): void
    {
        $clienteId = null;
        if (!empty($checklist['processo_id'])) {
            $processo = $this->processosRepository->findById((int)$checklist['processo_id']);
            $clienteId = $processo['cliente_id'] ?? null;
        } elseif (!empty($checklist['caso_id'])) {
            $caso = $this->casosRepository->findById((int)$checklist['caso_id']);
            $clienteId = $caso['cliente_id'] ?? null;
        }

        foreach ($itensInseridos as $item) {
            $prazoDias = $item['prazo_dias'] ?? 7;
            $prazo = date('Y-m-d', strtotime("+{$prazoDias} days"));

            $tarefaDados = [
                'tarefa'            => "[Checklist] {$item['item_titulo']}",
                'detalhes'          => "Seção: {$item['secao_titulo']} | Checklist: {$checklist['titulo']}",
                'prazo'             => $prazo,
                'prioridade'        => $item['prioridade'] ?? 5,
                'responsavel'       => $item['responsavel_padrao'] ?? $usuarioId,
                'processo_id'       => $checklist['processo_id'] ?? null,
                'caso_id'           => $checklist['caso_id'] ?? null,
                'cliente_id'        => $clienteId,
                'status'            => 1, // Pendente
                'notificar'         => 1,
                'checklist_item_id' => $item['id']
            ];

            $tarefaId = $this->tarefasRepository->criarTarefaDeChecklist($tarefaDados);

            if ($tarefaId) {
                $this->itemCasoModel->update($item['id'], ['tarefa_id' => $tarefaId]);
            }
        }

        // Histórico de tarefas geradas
        $this->historicoModel->insert([
            'checklist_id' => $checklistId,
            'acao'         => 'tarefas_geradas',
            'observacao'   => "Tarefas automáticas geradas para o checklist.",
            'created_by'   => $usuarioId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Adiciona um item avulso ao checklist.
     */
    public function adicionarItemAvulso(int $checklistId, array $dados, int $usuarioId): int|false
    {
        $query = $this->db->table('chk_itens_caso')
                          ->selectMax('ordem')
                          ->where('checklist_id', $checklistId)
                          ->get()
                          ->getRow();
        $proximaOrdem = ((int) ($query->ordem ?? 0)) + 1;

        $itemId = (int) $this->itemCasoModel->insert([
            'checklist_id' => $checklistId,
            'secao_titulo' => $dados['secao_titulo'],
            'item_titulo'  => $dados['item_titulo'],
            'observacao'   => $dados['observacao'] ?? null,
            'ordem'        => $proximaOrdem,
            'is_avulso'    => 1,
            'created_by'   => $usuarioId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        if ($itemId) {
            $this->historicoModel->insert([
                'checklist_id' => $checklistId,
                'item_id'      => $itemId,
                'acao'         => 'item_adicionado',
                'observacao'   => "Item avulso adicionado: {$dados['item_titulo']}",
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // Se o checklist pai gera tarefas, cria tarefa para o item avulso
            $checklist = $this->findById($checklistId);
            $templateId = $checklist['template_id'] ?? null;
            $gerarTarefa = true; // Padrão para avulso

            if ($templateId) {
                $template = $this->templateRepository->getById((int)$templateId);
                $gerarTarefa = ($template['gerar_tarefas'] ?? 1) == 1;
            }

            if ($gerarTarefa || ($dados['gerar_tarefa'] ?? false)) {
                $itemData = [
                    'id' => $itemId,
                    'item_titulo' => $dados['item_titulo'],
                    'secao_titulo' => $dados['secao_titulo'],
                    'prazo_dias' => $dados['prazo_dias'] ?? 7,
                    'prioridade' => $dados['prioridade'] ?? 5,
                    'responsavel_padrao' => $dados['responsavel_padrao'] ?? null
                ];
                $this->criarTarefasDoChecklist($checklistId, $checklist, [$itemData], $usuarioId);
            }

            $this->clearCache();
        }

        return $itemId;
    }

    /**
     * Cria um checklist avulso em uma entidade.
     */
    public function criarChecklistAvulso(string $type, int $targetId, string $titulo, int $usuarioId): int|false
    {
        $data = [
            'titulo'     => $titulo,
            'created_by' => $usuarioId,
            'created_at' => date('Y-m-d H:i:s'),
            'status'     => 'pendente'
        ];

        if ($type === 'caso') {
            $data['caso_id'] = $targetId;
        } else {
            $data['processo_id'] = $targetId;
        }

        $checklistId = $this->create($data);

        if ($checklistId) {
            $entidadeTxt = $type === 'processo' ? "processo" : "caso";
            $this->historicoModel->insert([
                'checklist_id' => $checklistId,
                'acao'         => 'checklist_criado',
                'observacao'   => "Seção '{$titulo}' criada no {$entidadeTxt}",
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->clearCache();
        }

        return $checklistId;
    }

    /**
     * Atualiza um item de checklist.
     */
    public function atualizarItem(int $itemId, array $dados, int $usuarioId): bool
    {
        $item = $this->itemCasoModel->find($itemId);
        if (!$item) return false;

        $updateData = [];
        if (isset($dados['item_titulo'])) $updateData['item_titulo'] = $dados['item_titulo'];
        if (array_key_exists('observacao', $dados)) $updateData['observacao'] = $dados['observacao'];
        if (isset($dados['secao_titulo'])) $updateData['secao_titulo'] = $dados['secao_titulo'];

        if (empty($updateData)) return true;

        $atualizado = $this->itemCasoModel->update($itemId, $updateData);

        if ($atualizado) {
            $this->historicoModel->insert([
                'checklist_id' => $item['checklist_id'],
                'item_id'      => $itemId,
                'acao'         => 'item_atualizado',
                'observacao'   => "Item atualizado",
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            if (!empty($item['tarefa_id']) && isset($dados['item_titulo'])) {
                $this->tarefasRepository->update((int)$item['tarefa_id'], ['tarefa' => "[Checklist] {$dados['item_titulo']}"]);
            }

            $this->clearCache();
        }

        return $atualizado;
    }

    /**
     * Exclui um item de checklist.
     */
    public function excluirItem(int $itemId, int $usuarioId): bool
    {
        $item = $this->itemCasoModel->find($itemId);
        if (!$item) return false;

        $excluido = $this->itemCasoModel->delete($itemId);

        if ($excluido) {
            if (!empty($item['tarefa_id'])) {
                $this->tarefasRepository->delete((int)$item['tarefa_id']);
            }

            $this->historicoModel->insert([
                'checklist_id' => $item['checklist_id'],
                'acao'         => 'item_excluido',
                'observacao'   => "Item excluído: {$item['item_titulo']}",
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->verificarConclusaoAutomatica((int) $item['checklist_id'], $usuarioId);
            $this->clearCache();
        }

        return $excluido;
    }

    /**
     * Retorna um item com o status do seu checklist.
     */
    public function getItemComStatus(int $itemId): ?object
    {
        return $this->db->table('chk_itens_caso ic')
                    ->select('ic.*, cc.status as checklist_status')
                    ->join('chk_casos_checklists cc', 'cc.id = ic.checklist_id')
                    ->where('ic.id', $itemId)
                    ->get()
                    ->getRow();
    }

    /**
     * Retorna IDs dos templates já aplicados a uma entidade.
     */
    public function getTemplatesAplicados(string $field, int $id): array
    {
        $aplicados = $this->db->table('chk_casos_checklists')
                        ->select('template_id')
                        ->where($field, $id)
                        ->where('template_id IS NOT NULL')
                        ->get()
                        ->getResult();

        return array_column($aplicados, 'template_id');
    }

    /**
     * Marca ou desmarca um item como cumprido.
     */
    public function marcarItem(int $itemId, bool $cumprido, int $usuarioId, bool $syncTask = true): bool
    {
        $item = $this->itemCasoModel->find($itemId);
        if (!$item) {
            return false;
        }

        $dados = [
            'cumprido'     => $cumprido ? 1 : 0,
            'cumprido_em'  => $cumprido ? date('Y-m-d H:i:s') : null,
            'cumprido_por' => $cumprido ? $usuarioId : null,
        ];

        $atualizado = $this->itemCasoModel->update($itemId, $dados);

        if ($atualizado) {
            // Sincronizar com tarefa
            if ($syncTask && !empty($item['tarefa_id'])) {
                $novoStatusTarefa = $cumprido ? 4 : 2; // 4 = Concluída, 2 = Em Andamento
                $this->tarefasRepository->atualizarStatus((int)$item['tarefa_id'], $novoStatusTarefa);
            }

            $this->historicoModel->insert([
                'checklist_id' => $item['checklist_id'],
                'item_id'      => $itemId,
                'acao'         => $cumprido ? 'item_marcado' : 'item_desmarcado',
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // Verificar se todos os itens estão cumpridos
            $this->verificarConclusaoAutomatica((int) $item['checklist_id'], $usuarioId);
            $this->clearCache();
        }

        return $atualizado;
    }

    /**
     * Conclui manualmente um checklist.
     */
    public function concluirChecklist(int $checklistId, int $usuarioId): bool
    {
        $dados = [
            'status'        => 'concluido',
            'concluido_em'  => date('Y-m-d H:i:s'),
            'concluido_por' => $usuarioId,
        ];

        $atualizado = $this->update($checklistId, $dados);

        if ($atualizado) {
            // Concluir tarefas vinculadas que ainda estão pendentes
            $itens = $this->itemCasoModel->where('checklist_id', $checklistId)
                                         ->where('tarefa_id IS NOT NULL')
                                         ->findAll();

            foreach ($itens as $item) {
                $tarefa = $this->tarefasRepository->buscarPorId((int)$item['tarefa_id']);
                if ($tarefa && in_array($tarefa['status'], [1, 2, 3])) {
                    $this->tarefasRepository->atualizarStatus((int)$item['tarefa_id'], 4);
                }
            }

            $this->historicoModel->insert([
                'checklist_id' => $checklistId,
                'acao'         => 'checklist_concluido',
                'created_by'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->clearCache();
        }

        return $atualizado;
    }

    /**
     * Verifica se todos os itens foram cumpridos para concluir o checklist automaticamente.
     */
    private function verificarConclusaoAutomatica(int $checklistId, int $usuarioId): void
    {
        $itens = $this->itemCasoModel->where('checklist_id', $checklistId)->findAll();

        $totalItens = count($itens);
        $itensCumpridos = 0;

        foreach ($itens as $item) {
            if ($item['cumprido']) {
                $itensCumpridos++;
            }
        }

        if ($totalItens > 0 && $itensCumpridos === $totalItens) {
            $this->concluirChecklist($checklistId, $usuarioId);
        }
    }

    /**
     * Retorna o histórico de um checklist.
     */
    public function getHistorico(int $checklistId): array
    {
        return $this->historicoModel->where('checklist_id', $checklistId)
                                    ->orderBy('created_at', 'DESC')
                                    ->findAll();
    }

    /**
     * Retorna dados formatados para impressão de um checklist.
     */
    public function getDadosImpressao(int $checklistId): ?array
    {
        $checklist = $this->getById($checklistId);

        if (!$checklist) {
            return null;
        }

        $entidade = [];
        if ($checklist['caso_id']) {
            $entidade = $this->casosRepository->buscarPorId((int)$checklist['caso_id']);
            $entidade['tipo'] = 'Caso';
            $entidade['display_nome'] = $entidade['titulo'] ?? '';
        } elseif ($checklist['processo_id']) {
            $procData = $this->processosRepository->buscarDetalhesProcesso((int)$checklist['processo_id']);
            $entidade = $procData['processo'] ?? [];
            $entidade['tipo'] = 'Processo';
            $entidade['display_nome'] = $entidade['numeroprocessocommascara'] ?? '';
        }

        return [
            'checklist'       => $checklist,
            'entidade'        => $entidade,
            'itens_agrupados' => $this->getItensAgrupados($checklistId),
            'data_impressao'  => date('d/m/Y H:i:s'),
        ];
    }

    /**
     * Retorna o cliente_id associado ao checklist.
     */
    public function getClienteIdByChecklistId(int $id): ?int
    {
        $checklist = $this->model->find($id);
        if (!$checklist) return null;

        if (!empty($checklist['caso_id'])) {
            $caso = $this->casosRepository->findById((int)$checklist['caso_id']);
            return $caso ? (int)$caso['cliente_id'] : null;
        }

        if (!empty($checklist['processo_id'])) {
            $processo = $this->processosRepository->findById((int)$checklist['processo_id']);
            return $processo ? (int)$processo['cliente_id'] : null;
        }

        return null;
    }

    /**
     * Retorna o cliente_id associado a um item de checklist.
     */
    public function getClienteIdByItemId(int $itemId): ?int
    {
        $item = $this->itemCasoModel->find($itemId);
        if (!$item) return null;

        return $this->getClienteIdByChecklistId((int)$item['checklist_id']);
    }
    /**
     * Exclui um checklist e seus itens e histórico.
     */
    public function excluirChecklist(int $id): bool
    {
        log_message('debug', 'Excluindo checklist: ' . $id);
        $this->db->transStart();

        // Remove histórico
        $this->db->table('chk_historico')->where('checklist_id', $id)->delete();

        // Busca os itens para tratar vínculos
        $itens = $this->db->table('chk_itens_caso')->select('id, tarefa_id')->where('checklist_id', $id)->get()->getResultArray();
        $itensIds = array_column($itens, 'id');
        $tarefasIds = array_filter(array_column($itens, 'tarefa_id'));

        if (!empty($tarefasIds)) {
            // Exclui tarefas vinculadas que não estão concluídas (status 1, 2, 3)
            $this->db->table('tarefas')
                     ->whereIn('id_tarefa', $tarefasIds)
                     ->whereIn('status', [1, 2, 3])
                     ->delete();
        }

        if (!empty($itensIds)) {
            // Desvincula as tarefas que sobraram (por exemplo, já concluídas)
            $this->db->table('tarefas')
                     ->whereIn('checklist_item_id', $itensIds)
                     ->update(['checklist_item_id' => null]);

            // Remove itens
            $this->db->table('chk_itens_caso')->whereIn('id', $itensIds)->delete();
        }

        // Remove checklist
        $this->model->delete($id);

        $this->db->transComplete();

        if ($this->db->transStatus() === true) {
            $this->clearCache();
            return true;
        }

        $error = $this->db->error();
        log_message('error', '[ChecklistRepository::excluirChecklist] Erro ao excluir checklist ID ' . $id . ': ' . json_encode($error));

        return false;
    }

    /**
     * Salva sugestões de IA como um novo checklist.
     */
    public function salvarSugestoesIA(int $targetId, string $type, array $sugestoes, int $usuarioId): int
    {
        $this->db->transStart();

        $data = [
            'titulo'      => 'Sugestão IA - ' . date('d/m/Y H:i'),
            'created_by'  => $usuarioId,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if ($type === 'caso') $data['caso_id'] = $targetId;
        else $data['processo_id'] = $targetId;

        $checklistId = (int) $this->model->insert($data);

        $ordem = 1;
        $itensParaInserir = [];
        foreach ($sugestoes as $secao) {
            $secaoTitulo = $secao['secao'] ?? 'Geral';
            foreach ($secao['itens'] as $item) {
                $itensParaInserir[] = [
                    'checklist_id' => $checklistId,
                    'secao_titulo' => $secaoTitulo,
                    'item_titulo'  => $item['titulo'],
                    'observacao'   => $item['descricao'] ?? null,
                    'ordem'        => $ordem++,
                    'is_avulso'    => 1,
                    'created_by'   => $usuarioId,
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($itensParaInserir)) {
            $idsInseridos = [];
            foreach ($itensParaInserir as $itemData) {
                $itemId = $this->itemCasoModel->insert($itemData);
                if ($itemId) {
                    $itemData['id'] = $itemId;
                    // IA items default values
                    $itemData['prazo_dias'] = 7;
                    $itemData['prioridade'] = 5;
                    $itemData['responsavel_padrao'] = null;
                    $idsInseridos[] = $itemData;
                }
            }

            // Criar tarefas para sugestões da IA (IA sempre gera tarefas por padrão)
            if (!empty($idsInseridos)) {
                $checklistData = $this->findById($checklistId);
                $this->criarTarefasDoChecklist($checklistId, $checklistData, $idsInseridos, $usuarioId);
            }
        }

        // Histórico
        $this->historicoModel->insert([
            'checklist_id' => $checklistId,
            'acao'         => 'ia_sugerido',
            'observacao'   => "Checklist gerado por Inteligência Artificial.",
            'created_by'   => $usuarioId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === true) {
            $this->clearCache();
            return $checklistId;
        }

        return 0;
    }
}
