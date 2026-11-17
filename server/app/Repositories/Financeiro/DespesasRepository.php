<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroDespesasCategoriasModel;
use App\Models\Financeiro\FinanceiroDespesasModel;
use App\Models\Financeiro\FinanceiroPagtoDespesasModel;
use App\Repositories\BaseRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FornecedorRepository;
use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * @property FinanceiroDespesasModel $model
 * @version 1.0.3
 */
class DespesasRepository extends BaseRepository
{
    protected FinanceiroPagtoDespesasModel $pagtoDespesasModel;
    protected FinanceiroDespesasCategoriasModel $categoriasSplitModel;
    protected FornecedorRepository $fornecedorRepository;
    protected ClienteRepository $clienteRepository;
    protected CategoriasRepository $categoriaRepository;

    public function __construct(
        ?FinanceiroDespesasModel $model = null,
        ?FinanceiroPagtoDespesasModel $pagtoDespesasModel = null,
        ?FornecedorRepository $fornecedorRepository = null,
        ?ClienteRepository $clienteRepository = null,
        ?CategoriasRepository $categoriaRepository = null,
        ?FinanceiroDespesasCategoriasModel $categoriasSplitModel = null
    ) {
        parent::__construct();
        $this->model                = $model ?? model('Financeiro/FinanceiroDespesasModel');
        $this->pagtoDespesasModel   = $pagtoDespesasModel ?? model('Financeiro/FinanceiroPagtoDespesasModel');
        $this->categoriasSplitModel = $categoriasSplitModel ?? model('Financeiro/FinanceiroDespesasCategoriasModel');

        $this->fornecedorRepository = $fornecedorRepository ?? new FornecedorRepository();
        $this->clienteRepository    = $clienteRepository ?? new ClienteRepository();
        $this->categoriaRepository  = $categoriaRepository ?? new CategoriasRepository();
        $this->cacheKey = 'financeiro_despesas';
    }

    /**
     * Sobrescreve clearCache para limpar também o cache de extratos.
     */
    public function clearCache(): void
    {
        parent::clearCache();
        (new ExtratoRepository())->clearCache();
    }

    /**
     * Recupera as despesas que ainda não foram pagas
     * * @param array $filtros Filtros opcionais para a consulta [data_inicio, data_fim, despesa, categoria, fornecedor, vencidas, ordenar_por, ordem].
     * @param int $limit Limite de registros para retornar
     * @param int $offset Deslocamento para paginação
     * @return array Lista de despesas não pagas
     */
    public function listarDespesasNaoPagas(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';
        $cacheSuffix = "nao_pagas_{$filtrosKey}_{$limit}_{$offset}";

        return $this->remember($cacheSuffix, function () use ($filtros, $limit, $offset) {
            // Subconsulta em nível de SQL para excluir despesas pagas
            $subQuery = $this->pagtoDespesasModel->builder()
                ->select('despesa_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            // Query principal para buscar despesas sem pagamento
            $builder = $this->model
                ->select('fin_despesas.*, fin_fornecedores.nome as nome_fornecedor')
                ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
                ->where("id_despesa NOT IN ($subQuery)");

            // Aplicar filtros opcionais
            if (!empty($filtros)) {
                // Filtro por data de vencimento
                if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                    $builder->where('vencimento_dt >=', $filtros['data_inicio'])
                        ->where('vencimento_dt <=', $filtros['data_fim']);
                }

                // Busca global (search)
                if (!empty($filtros['search'])) {
                    $termo = $filtros['search'];
                    $builder->groupStart()
                        ->like('fin_despesas.despesa', $termo)
                        ->orLike('fin_fornecedores.nome', $termo)
                        ->groupEnd();
                }

                // Filtro por despesa
                if (!empty($filtros['despesa'])) {
                    $builder->where('despesa', $filtros['despesa']);
                }

                // Filtro por categoria
                if (!empty($filtros['categoria'])) {
                    $builder->where("id_despesa IN ({$this->categoriaFiltroSubquery('despesa_id', (int) $filtros['categoria'])})", null, false);
                }

                // Filtro por fornecedor
                if (!empty($filtros['fornecedor'])) {
                    $builder->where('fornecedor', $filtros['fornecedor']);
                }

                // Filtro por vencidas
                if (!empty($filtros['vencidas'])) {
                    $builder->where('vencimento_dt <', date('Y-m-d'));
                }

                // Ordenação
                if (!empty($filtros['sort'])) {
                    switch ($filtros['sort']) {
                        case 'valor_desc':
                            $builder->orderBy('fin_despesas.valor', 'DESC');
                            break;
                        case 'valor_asc':
                            $builder->orderBy('fin_despesas.valor', 'ASC');
                            break;
                        case 'vencimento_asc':
                        default:
                            $builder->orderBy('fin_despesas.vencimento_dt', 'ASC');
                            break;
                    }
                } elseif (isset($filtros['ordenar_por']) && isset($filtros['ordem'])) {
                    $ordenarPorPermitidos = ['valor', 'vencimento_dt', 'descricao', 'status'];
                    $ordenarPor = in_array($filtros['ordenar_por'], $ordenarPorPermitidos, true)
                        ? $filtros['ordenar_por']
                        : 'vencimento_dt';
                    $ordem = strtoupper((string) $filtros['ordem']) === 'DESC' ? 'DESC' : 'ASC';
                    $builder->orderBy('fin_despesas.' . $ordenarPor, $ordem);
                } else {
                    $builder->orderBy('vencimento_dt', 'ASC');
                }
            } else {
                $builder->orderBy('vencimento_dt', 'ASC');
            }

            // Garantir que apenas despesas não excluídas sejam listadas
            $builder->where('fin_despesas.deleted_at IS NULL');

            // Aplicar limite e offset para paginação
            if ($limit > 0) {
                $builder->limit($limit, $offset);
            }

            $resultado = $builder->get()->getResultArray();
            $this->anexarCategorias($resultado);

            return $resultado;
        });
    }

    /**
     * Conta as despesas que ainda não foram pagas.
     *
     * @param array $filtros Filtros opcionais para a consulta rules.
     * @return int Número de despesas não pagas.
     */
    public function contarDespesasNaoPagas(array $filtros = []): int
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return (int) $this->remember("despesas_count_nao_pagas_{$filtrosKey}", function () use ($filtros) {
            $subQuery = $this->pagtoDespesasModel->builder()
                ->select('despesa_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $builder = $this->model->where("id_despesa NOT IN ($subQuery)");

            // Aplicar filtros opcionais
            if (!empty($filtros)) {
                // Filtro por data de vencimento
                if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                    $builder->where('vencimento_dt >=', $filtros['data_inicio'])
                        ->where('vencimento_dt <=', $filtros['data_fim']);
                }

                // Filtro por categoria
                if (!empty($filtros['categoria'])) {
                    $builder->where("id_despesa IN ({$this->categoriaFiltroSubquery('despesa_id', (int) $filtros['categoria'])})", null, false);
                }

                // Filtro por fornecedor
                if (!empty($filtros['fornecedor'])) {
                    $builder->where('fornecedor', $filtros['fornecedor']);
                }

                // Filtro por vencimento (despesas vencidas)
                if (isset($filtros['vencidas']) && $filtros['vencidas'] === true) {
                    $builder->where('vencimento_dt <', date('Y-m-d'));
                }
            }

            $builder->where('deleted_at IS NULL');

            return $builder->countAllResults();
        });
    }

    /**
     * Soma o valor das despesas que ainda não foram pagas.
     *
     * @param array $filtros Filtros opcionais for querying.
     * @return float Valor total das despesas não pagas.
     */
    public function somarDespesasNaoPagas(array $filtros = []): float
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return (float) $this->remember("soma_nao_pagas_{$filtrosKey}", function () use ($filtros) {
            $subQuery = $this->pagtoDespesasModel->builder()
                ->select('despesa_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $builder = $this->model->where("id_despesa NOT IN ($subQuery)");

            // Aplicar filtros opcionais
            if (!empty($filtros)) {
                // Filtro por data de vencimento
                if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                    $builder->where('vencimento_dt >=', $filtros['data_inicio'])
                        ->where('vencimento_dt <=', $filtros['data_fim']);
                }

                // Filtro por categoria
                if (!empty($filtros['categoria'])) {
                    $builder->where("id_despesa IN ({$this->categoriaFiltroSubquery('despesa_id', (int) $filtros['categoria'])})", null, false);
                }

                // Filtro por fornecedor
                if (!empty($filtros['fornecedor'])) {
                    $builder->where('fornecedor', $filtros['fornecedor']);
                }

                // Filtro por vencimento (despesas vencidas)
                if (isset($filtros['vencidas']) && $filtros['vencidas'] === true) {
                    $builder->where('vencimento_dt <', date('Y-m-d'));
                }
            }

            $builder->where('deleted_at IS NULL');

            $result = $builder->selectSum('valor')->get()->getRow();

            return $result->valor ?? 0.0;
        });
    }

    /**
     * Método para obter as despesas de reembolso de um cliente com filtros opcionais.
     * Otimizado: Filtros aplicados no banco de dados para reduzir processamento em PHP.
     *
     * @param int $cliente ID do cliente para reembolso
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search]
     * @return array Lista de despesas de reembolso do cliente
     */
    public function getDespesasPorCliente(int $cliente, array $filtros = []): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';
        return $this->remember("por_cliente_{$cliente}_{$filtrosKey}", function () use ($cliente, $filtros) {
            $builder = $this->pagtoDespesasModel
                ->distinct()
                ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, d.despesa as descricao, d.fornecedor as fornecedor_id, f.nome as cliente_fornecedor, (pd.valor * -1) as valor, d.id_despesa as despesa_id')
                ->from('fin_pgto_despesas as pd')
                ->join('fin_despesas as d', 'd.id_despesa = pd.despesa_id', 'left')
                ->join('fin_fornecedores as f', 'f.id_fornecedor = d.fornecedor', 'left')
                ->where('pd.cliente_reembolso', $cliente)
                ->where('pd.deleted_at is null');

            // Aplicar filtros diretamente na query
            if (!empty($filtros['categoria'])) {
                $builder->where("d.id_despesa IN ({$this->categoriaFiltroSubquery('despesa_id', (int) $filtros['categoria'])})", null, false);
            }

            if (!empty($filtros['data_inicio'])) {
                $builder->where('pd.pagamento_despesa_dt >=', $filtros['data_inicio']);
            }

            if (!empty($filtros['data_fim'])) {
                $builder->where('pd.pagamento_despesa_dt <=', $filtros['data_fim']);
            }

            if (!empty($filtros['search'])) {
                $termo = $filtros['search'];
                $builder->groupStart()
                    ->like('d.despesa', $termo)
                    ->orLike('f.nome', $termo)
                    ->orWhere("d.id_despesa IN ({$this->categoriaLikeSubquery('despesa_id', $termo)})", null, false)
                    ->groupEnd();
            }

            $resultado = $builder->orderBy('pd.pagamento_despesa_dt', 'DESC')->findAll();
            $this->anexarCategorias($resultado, 'despesa_id');

            return $resultado;
        });
    }

    /**
     * Obtém as despesas vencendo no mês atual.
     * Otimizado para ser SARGable, permitindo o uso de índices no campo vencimento_dt.
     *
     * @return array
     */
    public function getDespesasVencendo(): array
    {
        return $this->remember('vencendo_mes', function () {
            $dataInicio = date('Y-m-01');
            $proximoMes = date('Y-m-01', strtotime('first day of next month'));

            return $this->model
                ->where('vencimento_dt >=', $dataInicio)
                ->where('vencimento_dt <', $proximoMes)
                ->where('deleted_at', null)
                ->findAll();
        });
    }

    /**
     * Método para obter as despesas pagas por Determinado Periodo
     * @param string $dataInicio Data de início do período (formato 'Y-mm-d')
     * @param string $dataFim Data de fim do período (formato 'Y-mm-d')
     * @return array Lista de Pagamentos de Despesas no período especificado
     */
    public function getDespesasPagasPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->remember("pagas_periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $resultado = $this->pagtoDespesasModel
                ->distinct()
                ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, d.despesa as descricao, f.nome as cliente_fornecedor, (pd.valor) as valor, d.id_despesa as despesa_id')
                ->from('fin_pgto_despesas as pd')
                ->where('pd.pagamento_despesa_dt <=', $dataFim)
                ->where('pd.pagamento_despesa_dt >=', $dataInicio)
                ->where('pd.deleted_at is null')
                ->join('fin_despesas as d', 'd.id_despesa = pd.despesa_id', 'left')
                ->join('fin_fornecedores as f', 'f.id_fornecedor = d.fornecedor', 'left')
                ->findAll();

            $this->anexarCategorias($resultado, 'despesa_id');

            return $resultado;
        });
    }

    public function somarDespesasPagasPorPeriodo(?string $dataInicio, ?string $dataFim): float
    {
        return (float) $this->remember("soma_pagas_periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            return $this->pagtoDespesasModel
                ->where('pagamento_despesa_dt <=', $dataFim)
                ->where('pagamento_despesa_dt >=', $dataInicio)
                ->where('deleted_at is null')
                ->selectSum('valor')
                ->get()
                ->getRow()
                ->valor ?? 0.0;
        });
    }


    /**
     * Método para obter as despesas por Contrato com filtros opcionais.
     * Otimizado: Filtros aplicados no banco de dados.
     *
     * @param int $contratoId ID do contrato
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search]
     * @return array Lista de despesas do contrato
     */
    public function getDespesasPorContrato(int $contratoId, array $filtros = []): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return $this->remember("por_contrato_{$contratoId}_{$filtrosKey}", function () use ($contratoId, $filtros) {
            $builder = $this->model
                ->select('fin_despesas.*, (CASE WHEN COUNT(fin_pgto_despesas.id_pgto_despesa) > 0 THEN "Pago" ELSE "Pendente" END) as status', false)
                ->select('fin_fornecedores.nome as cliente_fornecedor')
                ->join('fin_pgto_despesas', 'fin_pgto_despesas.despesa_id = fin_despesas.id_despesa AND fin_pgto_despesas.deleted_at IS NULL', 'left')
                ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
                ->where('fin_despesas.contrato_id', $contratoId);

            // Aplicar filtros
            if (!empty($filtros['categoria'])) {
                $builder->where("fin_despesas.id_despesa IN ({$this->categoriaFiltroSubquery('despesa_id', (int) $filtros['categoria'])})", null, false);
            }

            if (!empty($filtros['data_inicio'])) {
                $builder->where('fin_despesas.vencimento_dt >=', $filtros['data_inicio']);
            }

            if (!empty($filtros['data_fim'])) {
                $builder->where('fin_despesas.vencimento_dt <=', $filtros['data_fim']);
            }

            if (!empty($filtros['search'])) {
                $termo = $filtros['search'];
                $builder->groupStart()
                    ->like('fin_despesas.despesa', $termo)
                    ->orLike('fin_fornecedores.nome', $termo)
                    ->orWhere("fin_despesas.id_despesa IN ({$this->categoriaLikeSubquery('despesa_id', $termo)})", null, false)
                    ->groupEnd();
            }

            $resultado = $builder->groupBy('fin_despesas.id_despesa')
                ->orderBy('fin_despesas.vencimento_dt', 'DESC')
                ->findAll();

            $this->anexarCategorias($resultado);

            return $resultado;
        });
    }

    /**
     * Retorna as últimas despesas pagas
     * @param int $limit
     * @return array
     */
    public function findLastPaid(int $limit): array
    {
        return $this->remember("ultimas_pagas_{$limit}", function () use ($limit) {
            return $this->pagtoDespesasModel
                ->select('fin_pgto_despesas.*, fin_despesas.despesa')
                ->join('fin_despesas', 'fin_despesas.id_despesa = fin_pgto_despesas.despesa_id')
                ->orderBy('pagamento_despesa_dt', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Busca despesas por IDs
     * @param array $ids
     * @return array
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->model->whereIn('id_despesa', $ids)->findAll();
    }



    ################################


    /**
     * Busca uma despesa pelo ID with dados relacionados
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->remember("detalhe_{$id}", function () use ($id) {
            $despesa = $this->model
                ->select('fin_despesas.*')
                ->select('fin_fornecedores.nome as nome_fornecedor')
                ->select('contratos.numero_contrato, contratos.objeto as contrato_nome')
                ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
                ->join('contratos', 'contratos.id_contrato = fin_despesas.contrato_id', 'left')
                ->find($id);

            if ($despesa) {
                $despesa['categorias'] = $this->getCategoriasSplit($id);
                $despesa['nome_categoria'] = implode(', ', array_column($despesa['categorias'], 'categoria_nome'));
            }

            return $despesa;
        });
    }

    /**
     * @deprecated Use findById instead.
     */
    public function find(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Cria uma nova despesa
     * @param array $data Dados da despesa. Pode incluir 'categorias' => [['categoria_id' => int, 'valor' => float], ...]
     * @return int ID da nova despesa
     */
    public function create(array $data): int
    {
        $categorias = $data['categorias'] ?? null;
        unset($data['categorias']);

        if (!$this->model->insert($data)) {
            $errors = $this->model->errors();
            throw new \Exception(implode(', ', $errors));
        }
        $id = (int) $this->model->getInsertID();

        if (is_array($categorias)) {
            $this->salvarCategorias($id, $categorias);
        }

        $this->clearCache();
        return $id;
    }

    /**
     * Atualiza uma despesa existente
     * @param int $id
     * @param array $data Dados da despesa. Pode incluir 'categorias' => [['categoria_id' => int, 'valor' => float], ...]
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $categorias = $data['categorias'] ?? null;
        unset($data['categorias']);

        if (!empty($data) && !$this->model->update($id, $data)) {
            $errors = $this->model->errors();
            throw new \Exception(implode(', ', $errors));
        }

        if (is_array($categorias)) {
            $this->salvarCategorias($id, $categorias);
        }

        $this->clearCache();
        return true;
    }

    /**
     * Exclui uma despesa
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        if (!$this->model->delete($id)) {
            throw new \Exception('Erro ao excluir despesa.');
        }
        $this->clearCache();
        return true;
    }

    /**
     * Obtém as 5 despesas mais recentes.
     * @return array
     */
    public function getRecentes(int $limit = 5): array
    {
        return $this->remember("recentes_{$limit}", function () use ($limit) {
            return $this->model->orderBy('created_at', 'DESC')->limit($limit)->findAll();
        });
    }

    /**
     * Obtém estatísticas básicas das despesas.
     * @return array
     */
    public function getEstatisticas(): array
    {
        return $this->remember('estatisticas', function () {
            $total = $this->model->countAll();
            $pagas = $this->pagtoDespesasModel->countAll();
            $a_pagar = $total - $pagas;

            // Ensure non-negative
            $a_pagar = $a_pagar < 0 ? 0 : $a_pagar;

            return [
                'total'     => $total,
                'pagas'     => $pagas,
                'a_pagar'   => $a_pagar,
            ];
        });
    }

    /**
     * Obtém o total de despesas pagas agrupadas por mês.
     * Otimizado para dashboard.
     */
    public function getDespesasPagasAgrupadasPorMes(string $dataInicio, string $dataFim): array
    {
        return $this->remember("pagas_agrupadas_mes_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $format = $this->getMonthFormat('pagamento_despesa_dt');

            return $this->pagtoDespesasModel
                ->select("{$format['field']} as mes_ano, {$format['label']} as mes, SUM(valor) as despesas", false)
                ->where('pagamento_despesa_dt >=', $dataInicio)
                ->where('pagamento_despesa_dt <=', $dataFim)
                ->where('deleted_at IS NULL')
                ->groupBy(['mes_ano', 'mes'])
                ->orderBy('mes_ano', 'ASC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém o total de despesas pagas agrupadas por área/especialidade (via contrato vinculado).
     * Despesas sem contrato ou cujo contrato não tem área definida caem no bucket vazio (Sem área).
     * Otimizado para dashboard.
     *
     * @param string|null $dataInicio Data inicial opcional (filtra pela data de pagamento).
     * @param string|null $dataFim Data final opcional (filtra pela data de pagamento).
     * @return array Lista de [area, despesas]
     */
    public function getDespesasPagasAgrupadasPorArea(?string $dataInicio = null, ?string $dataFim = null): array
    {
        $cacheSuffix = 'pagas_agrupadas_area_' . ($dataInicio ?? 'all') . '_' . ($dataFim ?? 'all');

        return $this->remember($cacheSuffix, function () use ($dataInicio, $dataFim) {
            $builder = $this->pagtoDespesasModel
                ->select('contratos.area_atuacao as area, SUM(fin_pgto_despesas.valor) as despesas', false)
                ->join('fin_despesas', 'fin_despesas.id_despesa = fin_pgto_despesas.despesa_id')
                ->join('contratos', 'contratos.id_contrato = fin_despesas.contrato_id', 'left')
                ->where('fin_despesas.deleted_at IS NULL')
                ->where('fin_pgto_despesas.deleted_at IS NULL');

            if ($dataInicio) {
                $builder->where('fin_pgto_despesas.pagamento_despesa_dt >=', $dataInicio);
            }
            if ($dataFim) {
                $builder->where('fin_pgto_despesas.pagamento_despesa_dt <=', $dataFim);
            }

            return $builder->groupBy('contratos.area_atuacao')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém o resumo financeiro de despesas para um contrato específico.
     * Otimizado para realizar agregação no banco de dados.
     *
     * @param int $contratoId
     * @return array [total_despesas, quantidade_despesas]
     */
    public function getResumoPorContrato(int $contratoId): array
    {
        return $this->remember("resumo_contrato_{$contratoId}", function () use ($contratoId) {
            $result = $this->model
                ->select('SUM(valor) as total, COUNT(id_despesa) as quantidade', false)
                ->where('contrato_id', $contratoId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();

            return [
                'total_despesas'      => (float) ($result['total'] ?? 0.0),
                'quantidade_despesas' => (int) ($result['quantidade'] ?? 0),
            ];
        });
    }

    /**
     * Retorna o total e a soma das despesas não pagas em um período.
     * Otimizado: Realiza as operações em uma única consulta SQL.
     *
     * @param array $filtros Filtros de data_inicio e data_fim.
     * @return array [total, soma]
     */
    public function getResumoNaoPagas(array $filtros = []): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return $this->remember("resumo_nao_pagas_{$filtrosKey}", function () use ($filtros) {
            $subQuery = $this->pagtoDespesasModel->builder()
                ->select('despesa_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $builder = $this->model->where("id_despesa NOT IN ($subQuery)");

            if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                $builder->where('vencimento_dt >=', $filtros['data_inicio'])
                        ->where('vencimento_dt <=', $filtros['data_fim']);
            }

            $builder->where('deleted_at IS NULL');

            $result = $builder
                ->select('COUNT(*) as total')
                ->select('SUM(valor) as soma')
                ->get()
                ->getRowArray();

            return [
                'total' => (int) ($result['total'] ?? 0),
                'soma'  => (float) ($result['soma'] ?? 0.0),
            ];
        });
    }

    /**
     * Obtém resumo estatístico de despesas a pagar em uma única consulta.
     * Otimizado: O(1) queries em vez de O(N) processamento em PHP.
     *
     * @return array [total_vencidas, valor_vencidas, vence_hoje, vence_semana]
     */
    public function getResumoEstatisticas(): array
    {
        return $this->remember('despesas_estatisticas_resumo', function () {
            $hoje = date('Y-m-d');
            $inicioSemana = date('Y-m-d', strtotime('monday this week'));
            $fimSemana = date('Y-m-d', strtotime('sunday this week'));

            // Subconsulta para excluir despesas pagas
            $subQuery = $this->pagtoDespesasModel->builder()
                ->select('despesa_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $amanha = date('Y-m-d', strtotime('+1 day'));
            $proximaSemana = date('Y-m-d', strtotime($fimSemana . ' +1 day'));

            $result = $this->model->where("id_despesa NOT IN ($subQuery)")
                ->where('fin_despesas.deleted_at', null)
                ->select("SUM(CASE WHEN vencimento_dt < '{$hoje}' THEN valor ELSE 0 END) as valor_vencidas", false)
                ->select("SUM(CASE WHEN vencimento_dt < '{$hoje}' THEN 1 ELSE 0 END) as total_vencidas", false)
                ->select("SUM(CASE WHEN vencimento_dt >= '{$hoje}' AND vencimento_dt < '{$amanha}' THEN 1 ELSE 0 END) as vence_hoje", false)
                ->select("SUM(CASE WHEN vencimento_dt >= '{$inicioSemana}' AND vencimento_dt < '{$proximaSemana}' THEN 1 ELSE 0 END) as vence_semana", false)
                ->get()
                ->getRowArray();

            return [
                'total_vencidas' => (int) ($result['total_vencidas'] ?? 0),
                'valor_vencidas' => (float) ($result['valor_vencidas'] ?? 0.0),
                'vence_hoje'      => (int) ($result['vence_hoje'] ?? 0),
                'vence_semana'    => (int) ($result['vence_semana'] ?? 0),
            ];
        });
    }

    /**
     * Busca o rateio de categorias de uma despesa, com o nome de cada categoria.
     *
     * @param int $despesaId
     * @return array Lista de ['id', 'despesa_id', 'categoria_id', 'valor', 'categoria_nome']
     */
    public function getCategoriasSplit(int $despesaId): array
    {
        return $this->categoriasSplitModel
            ->select('fin_despesas_categorias.*, fin_categorias.categoria as categoria_nome')
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_despesas_categorias.categoria_id', 'left')
            ->where('despesa_id', $despesaId)
            ->orderBy('fin_despesas_categorias.id', 'ASC')
            ->findAll();
    }

    /**
     * Substitui o rateio de categorias de uma despesa pelo novo conjunto informado.
     *
     * @param int $despesaId
     * @param array $categorias Lista de ['categoria_id' => int, 'valor' => float]
     */
    private function salvarCategorias(int $despesaId, array $categorias): void
    {
        $this->categoriasSplitModel->where('despesa_id', $despesaId)->delete();

        if (empty($categorias)) {
            return;
        }

        $linhas = [];
        foreach ($categorias as $item) {
            if (empty($item['categoria_id']) || !isset($item['valor'])) {
                throw new InvalidArgumentException('Cada item do rateio de categorias precisa de categoria_id e valor.');
            }

            $linhas[] = [
                'despesa_id'   => $despesaId,
                'categoria_id' => (int) $item['categoria_id'],
                'valor'        => (float) $item['valor'],
            ];
        }

        if (!$this->categoriasSplitModel->insertBatch($linhas)) {
            throw new \Exception('Erro ao salvar o rateio de categorias da despesa.');
        }
    }

    /**
     * Anexa a cada item da lista o rateio de categorias correspondente, em lote
     * (evita N+1 queries), preenchendo 'categorias' (lista completa) e
     * 'nome_categoria' (nomes concatenados, para exibição/compatibilidade).
     *
     * @param array $itens Lista de despesas (ou registros relacionados), por referência.
     * @param string $idField Nome do campo que contém o id_despesa em cada item.
     */
    private function anexarCategorias(array &$itens, string $idField = 'id_despesa'): void
    {
        $ids = array_unique(array_filter(array_column($itens, $idField)));

        if (empty($ids)) {
            foreach ($itens as &$item) {
                $item['categorias'] = [];
                $item['nome_categoria'] = '';
            }
            return;
        }

        $linhas = $this->categoriasSplitModel
            ->select('fin_despesas_categorias.despesa_id, fin_despesas_categorias.categoria_id, fin_despesas_categorias.valor, fin_categorias.categoria as categoria_nome')
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_despesas_categorias.categoria_id', 'left')
            ->whereIn('despesa_id', $ids)
            ->findAll();

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[$linha['despesa_id']][] = $linha;
        }

        foreach ($itens as &$item) {
            $categorias = $mapa[$item[$idField]] ?? [];
            $item['categorias'] = $categorias;
            $item['nome_categoria'] = implode(', ', array_column($categorias, 'categoria_nome'));
        }
    }

    /**
     * Subconsulta que resolve os IDs de despesa classificados em uma categoria.
     */
    private function categoriaFiltroSubquery(string $idField, int $categoriaId): string
    {
        return $this->categoriasSplitModel->builder()
            ->select($idField)
            ->where('categoria_id', $categoriaId)
            ->getCompiledSelect();
    }

    /**
     * Subconsulta que resolve os IDs de despesa cuja categoria (nome) casa com o termo de busca.
     */
    private function categoriaLikeSubquery(string $idField, string $termo): string
    {
        return $this->categoriasSplitModel->builder()
            ->select($idField)
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_despesas_categorias.categoria_id')
            ->like('fin_categorias.categoria', $termo)
            ->getCompiledSelect();
    }
}
