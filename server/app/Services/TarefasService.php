<?php

namespace App\Services;

use App\Repositories\TarefasRepository;
use App\Services\NotificacaoService;
use Exception;

/**
 * Serviço responsável pela gestão de tarefas.
 *
 * Esta classe gerencia a lógica de negócios relacionada a tarefas,
 * incluindo criação, atualização, listagem e mudança de status.
 */
class TarefasService extends BaseService
{
    /**
     * Serviço de notificações.
     *
     * @var NotificacaoService
     */
    private NotificacaoService $notificacaoService;

    /**
     * Serviço de cache.
     *
     * @var \CodeIgniter\Cache\CacheInterface
     */
    private $cache;

    /**
     * Construtor do TarefasService com injeção de dependência.
     *
     * @param TarefasRepository|null $repository
     * @param NotificacaoService|null $notificacaoService
     */
    public function __construct(
        ?TarefasRepository $repository = null,
        ?NotificacaoService $notificacaoService = null
    ) {
        $this->repository           = $repository ?? new TarefasRepository();
        $this->notificacaoService   = $notificacaoService ?? new NotificacaoService();
        $this->cache                = service('cache');
    }

    /**
     * Lista tarefas com base em filtros.
     *
     * @param array $filtros Filtros de busca
     * @return array Lista de tarefas
     */
    public function listarTarefas(array $filtros = []): array
    {
        return $this->repository->listarTarefas($filtros);
    }

    /**
     * Busca uma tarefa específica pelo ID.
     *
     * @param int $id O ID da tarefa
     * @return array|null Os dados da tarefa ou null se não encontrada
     */
    public function buscarTarefa(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    /**
     * Salva uma tarefa (nova ou existente).
     *
     * @param array $dados Os dados da tarefa
     * @return array Resultado da operação
     * @throws Exception
     */
    public function salvarTarefa(array $dados): array
    {
        try {
            // Limpa cache relacionado
            $this->limparCacheRelacionado($dados);

            // Atualiza tarefa existente
            if (isset($dados['id_tarefa']) && is_numeric($dados['id_tarefa'])) {
                $idTarefa = $dados['id_tarefa'];
                $tarefaAtual = $this->repository->buscarPorId($idTarefa);

                if (!$tarefaAtual) {
                    return [
                        'success' => false,
                        'message' => 'Tarefa não encontrada',
                    ];
                }

                // Verifica mudança de responsável
                if (
                    !empty($dados['responsavel'])
                    && $tarefaAtual['responsavel'] != $dados['responsavel']
                ) {
                    $this->notificarResponsavel($dados);
                }

                $sucesso = $this->repository->atualizar($idTarefa, $dados);

                if ($sucesso) {
                    $this->sincronizarGoogleCalendar((int) $idTarefa, 'update', $dados);
                }


                return [
                    'success' => $sucesso,
                    'message' => $sucesso ? 'Tarefa atualizada com sucesso!' : 'Erro ao atualizar tarefa',
                ];
            }

            // Cria nova tarefa
            if (!empty($dados['responsavel'])) {
                $this->notificarResponsavel($dados);
            }

            $id = $this->repository->inserir($dados);

            if ($id) {
                $this->sincronizarGoogleCalendar((int) $id, 'insert', $dados);


                return [
                    'success' => true,
                    'message' => 'Tarefa criada com sucesso!',
                    'id' => $id,
                ];
            }

            throw new Exception('Erro ao inserir tarefa no banco de dados');
        } catch (Exception $e) {
            log_message('error', 'Erro ao salvar tarefa: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao salvar tarefa: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Atualiza o status de uma tarefa.
     *
     * @param int $tarefaId O ID da tarefa
     * @param int $statusId O novo ID de status
     * @return array Resultado da operação
     */
    public function atualizarStatus(int $tarefaId, int $statusId): array
    {
        try {
            $tarefa = $this->repository->buscarPorId($tarefaId);

            if (!$tarefa) {
                return [
                    'success' => false,
                    'message' => 'Tarefa não encontrada',
                ];
            }

            $sucesso = $this->repository->atualizarStatus($tarefaId, $statusId);

            if ($sucesso) {
                $this->sincronizarGoogleCalendar($tarefaId, 'update', ['status' => $statusId]);
            }

            return [
                'success' => $sucesso,
                'message' => $sucesso ? 'Status atualizado com sucesso!' : 'Erro ao atualizar status',
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao atualizar status: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Exclui uma tarefa.
     *
     * @param int $id ID da tarefa
     * @return array Resultado da operação
     */
    public function excluirTarefa(int $id): array
    {
        try {
            $tarefa = $this->repository->buscarPorId($id);

            if (!$tarefa) {
                return [
                    'success' => false,
                    'message' => 'Tarefa não encontrada',
                ];
            }

            $this->limparCacheRelacionado($tarefa);
            $this->sincronizarGoogleCalendar($id, 'delete');
            $sucesso = $this->repository->excluir($id);

            return [
                'success' => $sucesso,
                'message' => $sucesso ? 'Tarefa excluída com sucesso!' : 'Erro ao excluir tarefa',
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao excluir tarefa: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao excluir tarefa: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Exclui múltiplas tarefas.
     *
     * @param array $ids IDs das tarefas
     * @return array Resultado da operação
     */
    public function excluirEmLote(array $ids): array
    {
        try {
            if (empty($ids)) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma tarefa selecionada',
                ];
            }

            foreach ($ids as $id) {
                $this->sincronizarGoogleCalendar((int) $id, 'delete');
            }

            $sucesso = $this->repository->excluirEmLote($ids);

            return [
                'success' => $sucesso,
                'message' => $sucesso
                    ? count($ids) . ' tarefa(s) excluída(s) com sucesso!'
                    : 'Erro ao excluir tarefas',
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao excluir tarefas em lote: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao excluir tarefas: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retorna tarefas urgentes.
     *
     * @return array Lista de tarefas urgentes
     */
    public function obterTarefasUrgentes(): array
    {
        $tarefas = $this->repository->buscarTarefasUrgentes();
        return $this->repository->popularVinculos($tarefas);
    }

    /**
     * Popula as informações de vínculo das tarefas.
     *
     * @param array $tarefas Lista de tarefas
     * @return array Tarefas com vínculos populados
     */
    public function popularVinculos(array $tarefas): array
    {
        return $this->repository->popularVinculos($tarefas);
    }

    /**
     * Retorna estatísticas de tarefas.
     *
     * @param int|null $userId ID do usuário
     * @return array Estatísticas
     */
    public function obterEstatisticas(?int $userId = null): array
    {
        return $this->repository->obterEstatisticas($userId);
    }

    /**
     * Retorna um resumo de tarefas por data para o calendário.
     *
     * @param array $filtros Filtros (ex: mes, ano, responsavel)
     * @return array Resumo por data
     */
    public function obterDadosCalendario(array $filtros = []): array
    {
        /** @var TarefasRepository $repo */
        $repo = $this->repository;
        return $repo->obterDadosCalendario($filtros);
    }

    /**
     * Conta tarefas pendentes por responsável.
     *
     * @param int $userId ID do usuário
     * @return int Número de tarefas
     */
    public function contarPorResponsavel(int $userId): int
    {
        /** @var TarefasRepository $repo */
        $repo = $this->repository;
        return $repo->contarPorResponsavel($userId);
    }

    /**
     * Aplica ação em lote nas tarefas.
     *
     * @param array $ids IDs das tarefas
     * @param string $acao Ação a ser aplicada
     * @return array Resultado da operação
     */
    public function aplicarAcaoEmLote(array $ids, string $acao): array
    {
        try {
            if (empty($ids)) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma tarefa selecionada',
                ];
            }

            $statusMap = [
                'concluir' => 4,
                'cancelar' => 5,
                'reabrir' => 2,
            ];

            if (!isset($statusMap[$acao])) {
                return [
                    'success' => false,
                    'message' => 'Ação inválida',
                ];
            }

            $novoStatus = $statusMap[$acao];
            $contador = 0;

            foreach ($ids as $id) {
                if ($this->repository->atualizarStatus($id, $novoStatus)) {
                    $contador++;
                }
            }

            return [
                'success' => true,
                'message' => "{$contador} tarefa(s) atualizada(s) com sucesso!",
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro em ação em lote: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao aplicar ação: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Limpa cache relacionado à tarefa.
     *
     * @param array $dados Dados da tarefa
     * @return void
     */
    private function limparCacheRelacionado(array $dados): void
    {
        // Limpa cache de cliente (se implementado)
        if (!empty($dados['cliente_id'])) {
            // $this->cache->delete('detalhes_cliente_' . $dados['cliente_id'] . '_user_' . user_id());
        }

    }

    /**
     * Notifica o responsável pela tarefa.
     *
     * @param array $dados Dados da tarefa
     * @return void
     */
    private function notificarResponsavel(array $dados): void
    {
        try {
            $dadosComVinculo = $this->repository->popularVinculos([$dados])[0] ?? $dados;

            $this->notificacaoService->enviarNotificacao(
                $dados['responsavel'],
                'LAYOUT_TAREFA_ATRIBUIDA',
                $dadosComVinculo
            );
        } catch (Exception $e) {
            log_message('error', 'Erro ao enviar notificação: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza os dados de uma tarefa existente.
     *
     * @param int $id O ID da tarefa
     * @param array $dados Os dados a serem atualizados
     * @return array Resultado da operação
     */
    public function atualizarTarefa(int $id, array $dados): array
    {
        try {
            $tarefa = $this->repository->buscarPorId($id);

            if (!$tarefa) {
                return [
                    'success' => false,
                    'message' => 'Tarefa não encontrada',
                ];
            }

            $this->limparCacheRelacionado($tarefa);
            $sucesso = $this->repository->atualizar($id, $dados);

            if ($sucesso) {
                $this->sincronizarGoogleCalendar($id, 'update', $dados);
            }

            return [
                'success' => $sucesso,
                'message' => $sucesso ? 'Tarefa atualizada com sucesso!' : 'Erro ao atualizar tarefa',
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao atualizar tarefa: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar tarefa: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Lista as tarefas de forma paginada (mantido para compatibilidade).
     *
     * @param int $porPagina Número de tarefas por página
     * @return array Array contendo 'tarefas' e a instância do 'pager'
     */
    public function listarTarefasPaginadas(int $porPagina = 25): array
    {
        $page = (int) (service('request')->getVar('page') ?? 1);
        $tarefas = $this->repository->paginate($porPagina, 'default', $page);

        return [
            'tarefas' => $tarefas,
            'pager'   => $this->repository->getPager(),
        ];
    }

    public function buscarTarefasNaoConcluidasPorUsuario(int $idUsuario): array
    {
        $tarefas = $this->repository->buscarTarefasNaoConcluidasPorUsuario($idUsuario);
        return $this->popularVinculos($tarefas);
    }

    /**
     * Sincroniza uma tarefa com o Google Agendas.
     *
     * @param int $idTarefa ID da tarefa
     * @param string $action Ação realizada ('insert', 'update', 'delete')
     * @param array $dados Dados opcionais
     */
    private function sincronizarGoogleCalendar(int $idTarefa, string $action, array $dados = []): void
    {
        try {
            $googleCalendarService = new \App\Services\GoogleCalendarService();
            $googleCalendarService->sincronizarTarefa($idTarefa, $action, $dados);
        } catch (\Exception $e) {
            log_message('error', 'Erro ao sincronizar tarefa com o Google Calendar: ' . $e->getMessage());
        }
    }
}
