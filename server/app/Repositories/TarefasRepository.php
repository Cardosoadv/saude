<?php

namespace App\Repositories;

use App\Models\TarefasModel;
use App\Models\ClientesModel;
use App\Models\CasosModel;
use App\Models\ProcessosModel;

/**
 * Repositório responsável pelo acesso aos dados de tarefas.
 *
 * Esta classe encapsula toda a lógica de acesso ao banco de dados
 * relacionada às tarefas.
 */
class TarefasRepository extends BaseRepository
{
    /**
     * Modelo de clientes.
     *
     * @var ClientesModel
     */
    private ClientesModel $clientesModel;

    /**
     * Modelo de casos.
     *
     * @var CasosModel
     */
    private CasosModel $casosModel;

    /**
     * Modelo de processos.
     *
     * @var ProcessosModel
     */
    private ProcessosModel $processosModel;

    /**
     * Construtor do TarefasRepository.
     */
    public function __construct(?TarefasModel $model = null, ?ClientesModel $clientesModel = null, ?CasosModel $casosModel = null, ?ProcessosModel $processosModel = null)
    {
        parent::__construct();
        $this->model           = $model ?? model('TarefasModel');
        $this->clientesModel   = $clientesModel ?? model('ClientesModel');
        $this->casosModel      = $casosModel ?? model('CasosModel');
        $this->processosModel  = $processosModel ?? model('ProcessosModel');
        $this->cacheKey        = 'tarefas';
    }

    /**
     * Lista tarefas com base em filtros.
     *
     * @param array $filtros Filtros a serem aplicados
     * @return array Lista de tarefas
     */
    public function listarTarefas(array $filtros = []): array
    {
        $suffix = empty($filtros) ? 'lista_completa' : 'lista_filtros_' . md5(serialize($filtros));

        return $this->remember($suffix, function () use ($filtros) {
            $builder = $this->model->builder();

            $builder->select('tarefas.*');
            $builder->select('clientes.nome as cliente_nome, clientes.razao_social as cliente_razao_social');
            $builder->select('casos.titulo as caso_titulo');
            $builder->select('processos.numeroprocessocommascara as processo_numero');
            $builder->select('users.username as responsavel_username');

            $builder->join('clientes', 'clientes.id_cliente = tarefas.cliente_id', 'left');
            $builder->join('casos', 'casos.id_caso = tarefas.caso_id', 'left');
            $builder->join('processos', 'processos.id_processo = tarefas.processo_id', 'left');
            $builder->join('users', 'users.id = tarefas.responsavel', 'left');

            // Filtro por usuário
            if (!empty($filtros['minhas'])) {
                $builder->where('tarefas.responsavel', user_id());
            }

            // Filtro por status em andamento
            if (!empty($filtros['emAndamento'])) {
                $builder->whereIn('tarefas.status', [1, 2, 3]);
            }

            // Filtro por status específico
            if (!empty($filtros['status'])) {
                $builder->where('tarefas.status', $filtros['status']);
            }

            // Filtro por tipo de vínculo
            if (!empty($filtros['vinculo'])) {
                switch ($filtros['vinculo']) {
                    case 'cliente':
                        $builder->where('tarefas.cliente_id IS NOT NULL');
                        break;
                    case 'caso':
                        $builder->where('tarefas.caso_id IS NOT NULL');
                        break;
                    case 'processo':
                        $builder->where('tarefas.processo_id IS NOT NULL');
                        break;
                }
            }

            // Filtro por prazo
            if (!empty($filtros['prazo'])) {
                $date = date('Y-m-d', strtotime((string) $filtros['prazo']));
                $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
                $builder->where('tarefas.prazo >=', $date)
                        ->where('tarefas.prazo <', $nextDate);
            }

            // Filtro de busca
            if (!empty($filtros['search'])) {
                $builder->groupStart()
                    ->like('tarefas.tarefa', $filtros['search'])
                    ->orLike('tarefas.detalhes', $filtros['search'])
                    ->groupEnd();
            }

            // Ordenação
            $sortField = $filtros['sort_field'] ?? 'prazo';
            if ($sortField === 'tarefa' || $sortField === 'prazo' || $sortField === 'status') {
                $sortField = 'tarefas.' . $sortField;
            } else {
                $sortField = 'tarefas.prazo';
            }
            $sortOrder = strtoupper((string) ($filtros['sort_order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $builder->orderBy($sortField, $sortOrder);

            return $builder->get()->getResultArray();
        });
    }

    /**
     * Busca uma tarefa pelo ID.
     *
     * @param int $id ID da tarefa
     * @return array|null Dados da tarefa ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Insere uma nova tarefa.
     *
     * @param array $dados Dados da tarefa
     * @return int|false ID da tarefa criada ou false
     */
    public function inserir(array $dados)
    {
        return $this->create($dados);
    }

    /**
     * Método dedicado para inserir tarefa originada de checklist.
     *
     * @param array $dados Dados da tarefa
     * @return int|false ID da tarefa criada ou false
     */
    public function criarTarefaDeChecklist(array $dados)
    {
        return $this->inserir($dados);
    }

    /**
     * Atualiza uma tarefa existente.
     *
     * @param int $id ID da tarefa
     * @param array $dados Dados a atualizar
     * @return bool Sucesso da operação
     */
    public function atualizar(int $id, array $dados): bool
    {
        return $this->update($id, $dados);
    }

    /**
     * Exclui uma tarefa.
     *
     * @param int $id ID da tarefa
     * @return bool Sucesso da operação
     */
    public function excluir(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Exclui múltiplas tarefas.
     *
     * @param array $ids IDs das tarefas
     * @return bool Sucesso da operação
     */
    public function excluirEmLote(array $ids): bool
    {
        $result = $this->model->whereIn('id_tarefa', $ids)->delete();
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Retorna tarefas urgentes (prazo próximo).
     *
     * @param int $diasAntecedencia Dias de antecedência
     * @param int $limit Limite de resultados
     * @return array Lista de tarefas urgentes
     */
    public function buscarTarefasUrgentes(int $diasAntecedencia = 3, int $limit = 5): array
    {
        return $this->remember("urgentes_{$diasAntecedencia}_{$limit}", function () use ($diasAntecedencia, $limit) {
            $dataLimite = date('Y-m-d', strtotime("+{$diasAntecedencia} days"));

            return $this->model
                ->where('prazo <=', $dataLimite)
                ->where('prazo >=', date('Y-m-d'))
                ->whereIn('status', [1, 2, 3])
                ->orderBy('prazo', 'ASC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Retorna tarefas atrasadas.
     *
     * @return array Lista de tarefas atrasadas
     */
    public function buscarTarefasAtrasadas(): array
    {
        return $this->remember('atrasadas', function () {
            return $this->model
                ->where('prazo <', date('Y-m-d'))
                ->whereIn('status', [1, 2, 3])
                ->orderBy('prazo', 'ASC')
                ->findAll();
        });
    }

    /**
     * Retorna estatísticas de tarefas.
     * Otimizado para realizar apenas uma consulta ao banco de dados com agregação condicional.
     *
     * @param int|null $userId ID do usuário (null para todas)
     * @return array Estatísticas
     */
    public function obterEstatisticas(?int $userId = null): array
    {
        return $this->remember('estatisticas_' . ($userId ?? 'todas'), function () use ($userId) {
            $builder = $this->model->builder();

            if ($userId) {
                $builder->where('responsavel', $userId);
            }

            $hoje = date('Y-m-d');

            // Realiza agregação condicional em uma única consulta SQL (O(1) queries em vez de O(4))
            $result = $builder
                ->select('COUNT(*) as total')
                ->select('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as fazendo')
                ->select('SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as concluidas')
                ->select("SUM(CASE WHEN prazo < '$hoje' AND status IN (1, 2, 3) THEN 1 ELSE 0 END) as atrasadas")
                ->get()
                ->getRowArray();

            return [
                'total'      => (int) ($result['total'] ?? 0),
                'fazendo'    => (int) ($result['fazendo'] ?? 0),
                'concluidas' => (int) ($result['concluidas'] ?? 0),
                'atrasadas'  => (int) ($result['atrasadas'] ?? 0),
            ];
        });
    }

    /**
     * Busca tarefas com informações de vínculo populadas.
     * Otimizado para evitar N+1 queries utilizando busca em lote.
     *
     * @param array $tarefas Lista de tarefas
     * @return array Tarefas com vínculos populados
     */
    /**
     * Popula as informações de vínculo das tarefas.
     * Otimizado para aproveitar dados já buscados via JOIN ou realizar busca em lote se necessário.
     *
     * @param array $tarefas Lista de tarefas
     * @return array Tarefas com vínculos populados
     */
    public function popularVinculos(array $tarefas): array
    {
        if (empty($tarefas)) {
            return [];
        }

        // Verifica se a primeira tarefa já possui os campos do JOIN
        $hasJoinData = isset($tarefas[0]['cliente_nome']) || isset($tarefas[0]['caso_titulo']) ||
                      isset($tarefas[0]['processo_numero']) || isset($tarefas[0]['responsavel_username']);

        $clientesMap = [];
        $casosMap = [];
        $processosMap = [];
        $responsaveisMap = [];

        if (!$hasJoinData) {
            $clienteIds = [];
            $casoIds = [];
            $processoIds = [];
            $responsavelIds = [];

            foreach ($tarefas as $tarefa) {
                if (!empty($tarefa['cliente_id'])) {
                    $clienteIds[] = $tarefa['cliente_id'];
                } elseif (!empty($tarefa['caso_id'])) {
                    $casoIds[] = $tarefa['caso_id'];
                } elseif (!empty($tarefa['processo_id'])) {
                    $processoIds[] = $tarefa['processo_id'];
                }
                if (!empty($tarefa['responsavel'])) {
                    $responsavelIds[] = $tarefa['responsavel'];
                }
            }

            if (!empty($clienteIds)) {
                $clientes = $this->clientesModel->whereIn('id_cliente', array_unique($clienteIds))->findAll();
                $clientesMap = array_column($clientes, null, 'id_cliente');
            }
            if (!empty($casoIds)) {
                $casos = $this->casosModel->whereIn('id_caso', array_unique($casoIds))->findAll();
                $casosMap = array_column($casos, null, 'id_caso');
            }
            if (!empty($processoIds)) {
                $processos = $this->processosModel->whereIn('id_processo', array_unique($processoIds))->findAll();
                $processosMap = array_column($processos, null, 'id_processo');
            }
            if (!empty($responsavelIds)) {
                $usersModel = model('UserModel');
                $responsaveis = $usersModel->whereIn('id', array_unique($responsavelIds))->findAll();
                foreach ($responsaveis as $u) {
                    $responsaveisMap[$u->id] = $u;
                }
            }
        }

        $frontURL = config('App')->frontURL;

        foreach ($tarefas as &$tarefa) {
            $tarefa['vinculo_info'] = null;
            $tarefa['vinculo_tipo'] = null;
            $tarefa['vinculo_url'] = null;

            if (!empty($tarefa['cliente_id'])) {
                $tarefa['vinculo_tipo'] = 'cliente';
                if ($hasJoinData) {
                    $tarefa['vinculo_info'] = $tarefa['cliente_nome'] ?? $tarefa['cliente_razao_social'] ?? 'Cliente não encontrado';
                } else {
                    $cliente = $clientesMap[$tarefa['cliente_id']] ?? null;
                    $tarefa['vinculo_info'] = $cliente['nome'] ?? $cliente['razao_social'] ?? 'Cliente não encontrado';
                }
                $tarefa['vinculo_url'] = $frontURL . 'clientes/editar/' . $tarefa['cliente_id'];
            } elseif (!empty($tarefa['caso_id'])) {
                $tarefa['vinculo_tipo'] = 'caso';
                if ($hasJoinData) {
                    $tarefa['vinculo_info'] = $tarefa['caso_titulo'] ?? 'Caso não encontrado';
                } else {
                    $caso = $casosMap[$tarefa['caso_id']] ?? null;
                    $tarefa['vinculo_info'] = $caso['titulo'] ?? 'Caso não encontrado';
                }
                $tarefa['vinculo_url'] = $frontURL . 'casos/detalhes/' . $tarefa['caso_id'];
            } elseif (!empty($tarefa['processo_id'])) {
                $tarefa['vinculo_tipo'] = 'processo';
                if ($hasJoinData) {
                    $tarefa['vinculo_info'] = $tarefa['processo_numero'] ?? 'Processo não encontrado';
                } else {
                    $processo = $processosMap[$tarefa['processo_id']] ?? null;
                    $tarefa['vinculo_info'] = $processo['numeroprocessocommascara'] ?? 'Processo não encontrado';
                }
                $tarefa['vinculo_url'] = $frontURL . 'processos/detalhes/' . $tarefa['processo_id'];
            }

            // Informações do Responsável
            if ($hasJoinData) {
                $tarefa['responsavel_nome'] = $tarefa['responsavel_username'] ?? 'Usuário';
            } else {
                $responsavel = $responsaveisMap[$tarefa['responsavel']] ?? null;
                $tarefa['responsavel_nome'] = $responsavel->display_name ?? $responsavel->username ?? 'Usuário';
            }

            // Formata prazo
            if (!empty($tarefa['prazo'])) {
                $prazo = strtotime($tarefa['prazo']);
                $hoje = strtotime('today');
                $diff = ($prazo - $hoje) / 86400;

                $tarefa['prazo_formatado'] = date('d/m/Y', strtotime($tarefa['prazo']));
                $tarefa['prazo_status'] = 'normal';

                if ($diff < 0) {
                    $tarefa['prazo_status'] = 'atrasado';
                } elseif ($diff <= 3) {
                    $tarefa['prazo_status'] = 'urgente';
                }
            }
        }

        return $tarefas;
    }


    /**
     * Atualiza o status de uma tarefa.
     *
     * @param int $id ID da tarefa
     * @param int $status Novo status
     * @return bool Sucesso da operação
     */
    public function atualizarStatus(int $id, int $status): bool
    {
        $result = $this->model->update($id, ['status' => $status]);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Conta tarefas por responsável.
     *
     * @param int $userId ID do usuário
     * @return int Número de tarefas
     */
    public function contarPorResponsavel(int $userId): int
    {
        return (int) $this->remember("count_responsavel_{$userId}", function () use ($userId) {
            return $this->model
                ->where('responsavel', $userId)
                ->whereIn('status', [1, 2, 3])
                ->countAllResults();
        });
    }

    /**
     * Lista tarefas recentes.
     *
     * @param int $limit Limite de resultados
     * @return array Lista de tarefas
     */
    public function buscarRecentes(int $limit = 10): array
    {
        return $this->remember("recentes_{$limit}", function () use ($limit) {
            return $this->model
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    public function buscarTarefasNaoConcluidasPorUsuario(int $idUsuario): array
    {
        return $this->remember("tarefas_nao_concluidas_usuario_{$idUsuario}", function () use ($idUsuario) {
            return $this->model
                ->where('responsavel', $idUsuario)
                ->whereNotIn('status', [4, 5])
                ->findAll();
        });
    }

    /**
     * Retorna contagem de tarefas por data de prazo.
     *
     * @param array $filtros Filtros de busca (ex: responsavel)
     * @return array Array de objetos { data: 'YYYY-MM-DD', total: X }
     */
    public function obterDadosCalendario(array $filtros = []): array
    {
        $currentUserId = user_id();
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return $this->remember("tarefas_calendario_user_{$currentUserId}_{$filtrosKey}", function () use ($filtros) {
            $builder = $this->model->builder();
            $builder->select('prazo as data_prazo, COUNT(*) as total');
            $builder->where('prazo IS NOT NULL');

            $builder->where('status !=', 5);

            if (!empty($filtros['responsavel'])) {
                $builder->where('responsavel', $filtros['responsavel']);
            }

            if (!empty($filtros['minhas'])) {
                $builder->where('responsavel', user_id());
            }

            return $builder->groupBy('prazo')
                ->get()
                ->getResultArray();
        });
    }
}
