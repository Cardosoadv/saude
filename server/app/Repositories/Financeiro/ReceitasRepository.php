<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroReceitasModel;
use App\Models\Financeiro\FinanceiroPagtoReceitasModel;
use App\Models\Financeiro\FinanceiroCategoriasModel;
use App\Models\Financeiro\FinanceiroReceitasCategoriasModel;
use App\Models\FornecedoresModel;
use App\Models\ClientesModel;
use App\Repositories\BaseRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FornecedorRepository;
use InvalidArgumentException;

/**
 * @property FinanceiroReceitasModel $model
 * @version 1.0.2
 */
class ReceitasRepository extends BaseRepository
{
    protected FinanceiroPagtoReceitasModel $pagtoReceitasModel;
    protected FinanceiroReceitasCategoriasModel $categoriasSplitModel;
    protected CategoriasRepository $categoriaRepository;
    protected FornecedorRepository $fornecedorRepository;
    protected ClienteRepository $clienteRepository;

    public function __construct(
        ?FinanceiroReceitasModel $model = null,
        ?FinanceiroPagtoReceitasModel $pagtoReceitasModel = null,
        ?CategoriasRepository $categoriaRepository = null,
        ?FornecedorRepository $fornecedorRepository = null,
        ?ClienteRepository $clienteRepository = null,
        ?FinanceiroReceitasCategoriasModel $categoriasSplitModel = null
    ) {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroReceitasModel');
        $this->pagtoReceitasModel = $pagtoReceitasModel ?? model('Financeiro/FinanceiroPagtoReceitasModel');
        $this->categoriasSplitModel = $categoriasSplitModel ?? model('Financeiro/FinanceiroReceitasCategoriasModel');

        // Repositórios auxiliares via factory de models do CI4 (ou instanciamento direto)
        $this->categoriaRepository  = $categoriaRepository ?? new CategoriasRepository();
        $this->fornecedorRepository = $fornecedorRepository ?? new FornecedorRepository();
        $this->clienteRepository    = $clienteRepository ?? new ClienteRepository();
        $this->cacheKey = 'financeiro_receitas';
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
     * Método para obter as receitas de um cliente específico com filtros opcionais.
     * Otimizado: Filtros aplicados no banco de dados para reduzir processamento em PHP.
     *
     * @param int $cliente_id ID do cliente
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search]
     * @return array Lista de Receitas do cliente
     */
    public function getReceitasPorCliente(int $cliente_id, array $filtros = []): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';
        return $this->remember("por_cliente_{$cliente_id}_{$filtrosKey}", function () use ($cliente_id, $filtros) {
            $builder = $this->pagtoReceitasModel
                ->distinct()
                ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, c.nome as cliente_fornecedor, (pr.valor) as valor, r.id_receita as receita_id')
                ->from('fin_pgto_receitas as pr')
                ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
                ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
                ->where('r.cliente_id', $cliente_id)
                ->where('pr.deleted_at is null');

            // Aplicar filtros diretamente na query
            if (!empty($filtros['categoria'])) {
                $builder->where("r.id_receita IN ({$this->categoriaFiltroSubquery('receita_id', (int) $filtros['categoria'])})", null, false);
            }

            if (!empty($filtros['data_inicio'])) {
                $builder->where('pr.pagamento_receita_dt >=', $filtros['data_inicio']);
            }

            if (!empty($filtros['data_fim'])) {
                $builder->where('pr.pagamento_receita_dt <=', $filtros['data_fim']);
            }

            if (!empty($filtros['search'])) {
                $termo = $filtros['search'];
                $builder->groupStart()
                    ->like('r.receita', $termo)
                    ->orLike('c.nome', $termo)
                    ->orWhere("r.id_receita IN ({$this->categoriaLikeSubquery('receita_id', $termo)})", null, false)
                    ->groupEnd();
            }

            $resultado = $builder->orderBy('pr.pagamento_receita_dt', 'DESC')->findAll();
            $this->anexarCategorias($resultado, 'receita_id');

            return $resultado;
        });
    }

    /**
     * Método para obter as receitas por Determinado Periodo
     * @param string $dataInicio Data de início do período (formato 'Y-m-d')
     * @param string $dataFim Data de fim do período (formato 'Y-m-d')
     * @return array Lista de receitas no período especificado
     */
    public function getReceitasPorPeriodo(string $dataInicio, string $dataFim): array
    {
        return $this->remember("por_periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $resultado = $this->pagtoReceitasModel
                ->distinct()
                ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, c.nome as cliente_fornecedor, (pr.valor) as valor, r.id_receita as receita_id')
                ->from('fin_pgto_receitas as pr')
                ->where('pr.pagamento_receita_dt <=', $dataFim)
                ->where('pr.pagamento_receita_dt >=', $dataInicio)
                ->where('pr.deleted_at is null')
                ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
                ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
                ->findAll();

            $this->anexarCategorias($resultado, 'receita_id');

            return $resultado;
        });
    }

    /**
     * Recupera as receitas que ainda não foram pagas
     *
     * @param array $filtros Filtros opcionais para a consulta [data_inicio, data_fim, receita, categoria, fornecedor, vencidas, ordenar_por, ordem].
     * @param int $limit Limite de registros para retornar
     * @param int $offset Deslocamento para paginação
     * @return array Lista de receitas não pagas
     */
    public function listarReceitasNaoPagas(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';
        $cacheSuffix = "nao_pagas_{$filtrosKey}_{$limit}_{$offset}";

        return $this->remember($cacheSuffix, function () use ($filtros, $limit, $offset) {
            $subQuery = $this->pagtoReceitasModel->db->table('fin_pgto_receitas')
                ->select('receita_id')
                ->where('deleted_at IS NULL');

            // Query principal para buscar receitas sem pagamento
            $builder = $this->model
                ->select('fin_receitas.*, clientes.nome as nome_cliente')
                ->join('clientes', 'clientes.id_cliente = fin_receitas.cliente_id', 'left')
                ->whereNotIn('id_receita', $subQuery);

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
                        ->like('fin_receitas.receita', $termo)
                        ->orLike('clientes.nome', $termo)
                        ->groupEnd();
                }

                // Filtro por cliente
                if (!empty($filtros['cliente_id'])) {
                    $builder->where('fin_receitas.cliente_id', $filtros['cliente_id']);
                }

                // Filtro por receita
                if (!empty($filtros['receita'])) {
                    $builder->where('receita', $filtros['receita']);
                }

                // Filtro por categoria
                if (!empty($filtros['categoria'])) {
                    $builder->where("id_receita IN ({$this->categoriaFiltroSubquery('receita_id', (int) $filtros['categoria'])})", null, false);
                }

                // Filtro por vencidas
                if (!empty($filtros['vencidas'])) {
                    $builder->where('vencimento_dt <', date('Y-m-d'));
                }

                // Ordenação
                if (!empty($filtros['sort'])) {
                    switch ($filtros['sort']) {
                        case 'valor_desc':
                            $builder->orderBy('fin_receitas.valor', 'DESC');
                            break;
                        case 'valor_asc':
                            $builder->orderBy('fin_receitas.valor', 'ASC');
                            break;
                        case 'vencimento_asc':
                        default:
                            $builder->orderBy('fin_receitas.vencimento_dt', 'ASC');
                            break;
                    }
                } elseif (isset($filtros['ordenar_por']) && isset($filtros['ordem'])) {
                    $ordenarPorPermitidos = ['valor', 'vencimento_dt', 'descricao', 'status'];
                    $ordenarPor = in_array($filtros['ordenar_por'], $ordenarPorPermitidos, true)
                        ? $filtros['ordenar_por']
                        : 'vencimento_dt';
                    $ordem = strtoupper((string) $filtros['ordem']) === 'DESC' ? 'DESC' : 'ASC';
                    $builder->orderBy('fin_receitas.' . $ordenarPor, $ordem);
                } else {
                    $builder->orderBy('vencimento_dt', 'ASC');
                }
            } else {
                $builder->orderBy('vencimento_dt', 'ASC');
            }

            $builder->where('fin_receitas.deleted_at IS NULL');

            if ($limit > 0) {
                $builder->limit($limit, $offset);
            }

            $resultado = $builder->get()->getResultArray();
            $this->anexarCategorias($resultado);

            return $resultado;
        });
    }

    /**
     * Conta as receitas que ainda não foram recebidas.
     *
     * @param array $filtros Filtros opcionais para a consulta rules.
     * @return int Número de receitas não recebidas.
     */
    public function contarReceitasNaoRecebidas(array $filtros = []): int
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return (int) $this->remember("receitas_count_nao_recebidas_{$filtrosKey}", function () use ($filtros) {
            // Subconsulta em nível de SQL para excluir receitas pagas
            $subQuery = $this->pagtoReceitasModel->db->table('fin_pgto_receitas')
                ->select('receita_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $builder = $this->model->where("id_receita NOT IN ($subQuery)");

            // Aplicar filtros opcionais
            if (!empty($filtros)) {
                // Filtro por data de vencimento
                if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                    $builder->where('vencimento_dt >=', $filtros['data_inicio'])
                        ->where('vencimento_dt <=', $filtros['data_fim']);
                }

                // Filtro por categoria
                if (!empty($filtros['categoria'])) {
                    $builder->where("id_receita IN ({$this->categoriaFiltroSubquery('receita_id', (int) $filtros['categoria'])})", null, false);
                }

                // Filtro por cliente
                if (!empty($filtros['cliente_id'])) {
                    $builder->where('cliente_id', $filtros['cliente_id']);
                }

                // Filtro por vencimento (receitas vencidas)
                if (isset($filtros['vencidas']) && $filtros['vencidas'] === true) {
                    $builder->where('vencimento_dt <', date('Y-m-d'));
                }
            }

            $builder->where('deleted_at IS NULL');

            return $builder->countAllResults();
        });
    }

    /**
     * Busca uma receita pelo ID com dados relacionados
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->remember("detalhe_{$id}", function () use ($id) {
            $receita = $this->model
                ->select('fin_receitas.*')
                ->select('clientes.nome as nome_cliente')
                ->select('contratos.numero_contrato, contratos.objeto as contrato_nome')
                ->join('clientes', 'clientes.id_cliente = fin_receitas.cliente_id', 'left')
                ->join('contratos', 'contratos.id_contrato = fin_receitas.contrato_id', 'left')
                ->find($id);

            if ($receita) {
                $receita['categorias'] = $this->getCategoriasSplit($id);
                $receita['nome_categoria'] = implode(', ', array_column($receita['categorias'], 'categoria_nome'));
            }

            return $receita;
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
     * Cria uma nova receita
     * @param array $data Dados da receita. Pode incluir 'categorias' => [['categoria_id' => int, 'valor' => float], ...]
     * @return int ID da receita criada
     * @throws \RuntimeException
     */
    public function create(array $data): int
    {
        $categorias = $data['categorias'] ?? null;
        unset($data['categorias']);

        if (!$this->model->insert($data)) {
            throw new \RuntimeException(implode('; ', $this->model->errors()));
        }
        $id = (int) $this->model->getInsertID();

        if (is_array($categorias)) {
            $this->salvarCategorias($id, $categorias);
        }

        $this->clearCache();
        return $id;
    }

    /**
     * Atualiza uma receita existente
     * @param int $id
     * @param array $data Dados da receita. Pode incluir 'categorias' => [['categoria_id' => int, 'valor' => float], ...]
     * @return bool
     * @throws \RuntimeException
     */
    public function update(int $id, array $data): bool
    {
        $categorias = $data['categorias'] ?? null;
        unset($data['categorias']);

        if (!empty($data) && !$this->model->update($id, $data)) {
            throw new \RuntimeException(implode('; ', $this->model->errors()));
        }

        if (is_array($categorias)) {
            $this->salvarCategorias($id, $categorias);
        }

        $this->clearCache();
        return true;
    }

    /**
     * Exclui uma receita
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $result = $this->model->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Lista todos os clientes para dropdown
     * @return array
     */
    public function listarClientes(): array
    {
        return $this->clienteRepository->findAll();
    }

    /**
     * Método para obter as receitas por Contrato com filtros opcionais.
     * Otimizado: Filtros aplicados no banco de dados.
     *
     * @param int $contratoId ID do contrato
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search]
     * @return array Lista de Receitas do contrato
     */
    public function getReceitasPorContrato(int $contratoId, array $filtros = []): array
    {
        $filtrosKey = !empty($filtros) ? md5(serialize($filtros)) : 'default';

        return $this->remember("por_contrato_{$contratoId}_{$filtrosKey}", function () use ($contratoId, $filtros) {
            $builder = $this->model
                ->select('fin_receitas.*, (CASE WHEN COUNT(fin_pgto_receitas.id_pgto_receita) > 0 THEN "Pago" ELSE "Pendente" END) as status', false)
                ->select('clientes.nome as cliente_fornecedor')
                ->join('fin_pgto_receitas', 'fin_pgto_receitas.receita_id = fin_receitas.id_receita AND fin_pgto_receitas.deleted_at IS NULL', 'left')
                ->join('clientes', 'clientes.id_cliente = fin_receitas.cliente_id', 'left')
                ->where('fin_receitas.contrato_id', $contratoId);

            // Aplicar filtros
            if (!empty($filtros['categoria'])) {
                $builder->where("fin_receitas.id_receita IN ({$this->categoriaFiltroSubquery('receita_id', (int) $filtros['categoria'])})", null, false);
            }

            if (!empty($filtros['data_inicio'])) {
                $builder->where('fin_receitas.vencimento_dt >=', $filtros['data_inicio']);
            }

            if (!empty($filtros['data_fim'])) {
                $builder->where('fin_receitas.vencimento_dt <=', $filtros['data_fim']);
            }

            if (!empty($filtros['search'])) {
                $termo = $filtros['search'];
                $builder->groupStart()
                    ->like('fin_receitas.receita', $termo)
                    ->orLike('clientes.nome', $termo)
                    ->orWhere("fin_receitas.id_receita IN ({$this->categoriaLikeSubquery('receita_id', $termo)})", null, false)
                    ->groupEnd();
            }

            $resultado = $builder->groupBy('fin_receitas.id_receita')
                ->orderBy('fin_receitas.vencimento_dt', 'DESC')
                ->findAll();

            $this->anexarCategorias($resultado);

            return $resultado;
        });
    }


    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->model->whereIn('id_receita', $ids)->findAll();
    }

    /**
     * Método para obter as receitas pagas por Determinado Periodo
     * @param string $dataInicio Data de início do período (formato 'Y-mm-d')
     * @param string $dataFim Data de fim do período (formato 'Y-mm-d')
     * @return array Lista de Pagamentos de receitas no período especificado
     */
    public function getReceitasPagasPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->remember("pagas_por_periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $resultado = $this->pagtoReceitasModel
                ->distinct()
                ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, c.nome as cliente_fornecedor, (pr.valor) as valor, r.id_receita as receita_id')
                ->from('fin_pgto_receitas as pr')
                ->where('pr.pagamento_receita_dt <=', $dataFim)
                ->where('pr.pagamento_receita_dt >=', $dataInicio)
                ->where('pr.deleted_at is null')
                ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
                ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
                ->findAll();

            $this->anexarCategorias($resultado, 'receita_id');

            return $resultado;
        });
    }

    /**
     * Soma o valor das receitas pagas em um determinado período.
     *
     * @param string $dataInicio Data inicial.
     * @param string $dataFim Data final.
     * @return float Valor total.
     */
    public function somarReceitasPagasPorPeriodo(string $dataInicio, string $dataFim): float
    {
        return (float) $this->remember("soma_pagas_periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $resultado = $this->pagtoReceitasModel
                ->select('SUM(valor) as total', false)
                ->where('pagamento_receita_dt >=', $dataInicio)
                ->where('pagamento_receita_dt <=', $dataFim)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow();

            return $resultado->total ?? 0.0;
        });
    }

    /**
     * Obtém o total de receitas pagas agrupadas por mês.
     * Otimizado para dashboard.
     */
    public function getReceitasPagasAgrupadasPorMes(string $dataInicio, string $dataFim): array
    {
        return $this->remember("pagas_agrupadas_mes_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $format = $this->getMonthFormat('pagamento_receita_dt');

            return $this->pagtoReceitasModel
                ->select("{$format['field']} as mes_ano, {$format['label']} as mes, SUM(valor) as receitas", false)
                ->where('pagamento_receita_dt >=', $dataInicio)
                ->where('pagamento_receita_dt <=', $dataFim)
                ->where('deleted_at IS NULL')
                ->groupBy(['mes_ano', 'mes'])
                ->orderBy('mes_ano', 'ASC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém o resumo financeiro de receitas para um cliente específico.
     * Otimizado para realizar agregação no banco de dados.
     *
     * @param int $clienteId
     * @return array [total_receitas, quantidade_receitas]
     */
    public function getResumoPorCliente(int $clienteId): array
    {
        return $this->remember("resumo_cliente_{$clienteId}", function () use ($clienteId) {
            $result = $this->pagtoReceitasModel
                ->select('SUM(fin_pgto_receitas.valor) as total, COUNT(fin_pgto_receitas.id_pgto_receita) as quantidade', false)
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->where('fin_receitas.cliente_id', $clienteId)
                ->where('fin_receitas.deleted_at IS NULL')
                ->where('fin_pgto_receitas.deleted_at IS NULL')
                ->get()
                ->getRowArray();

            return [
                'total_receitas'      => (float) ($result['total'] ?? 0.0),
                'quantidade_receitas' => (int) ($result['quantidade'] ?? 0),
            ];
        });
    }

    /**
     * Obtém os dados de receitas por mês para um cliente específico, formatados para gráficos.
     * Otimizado para realizar agregação no banco de dados.
     *
     * @param int $clienteId
     * @param string|null $dataInicio Data inicial opcional.
     * @param string|null $dataFim Data final opcional.
     * @return array Lista de [mes, receitas]
     */
    public function getDadosGraficosPorCliente(int $clienteId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $cacheSuffix = "grafico_cliente_{$clienteId}" . ($dataInicio ? "_{$dataInicio}" : "") . ($dataFim ? "_{$dataFim}" : "");
        return $this->remember($cacheSuffix, function () use ($clienteId, $dataInicio, $dataFim) {
            $format = $this->getMonthFormat('fin_pgto_receitas.pagamento_receita_dt');

            $builder = $this->pagtoReceitasModel
                ->select("{$format['field']} as mes_ano, {$format['label']} as mes, SUM(fin_pgto_receitas.valor) as receitas", false)
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->where('fin_receitas.cliente_id', $clienteId)
                ->where('fin_receitas.deleted_at IS NULL')
                ->where('fin_pgto_receitas.deleted_at IS NULL');

            if ($dataInicio) {
                $builder->where('fin_pgto_receitas.pagamento_receita_dt >=', $dataInicio);
            }
            if ($dataFim) {
                $builder->where('fin_pgto_receitas.pagamento_receita_dt <=', $dataFim);
            }

            return $builder->groupBy(['mes_ano', 'mes'])
                ->orderBy('mes_ano', 'ASC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém o total de receitas pagas agrupadas por ramo do direito (via contrato vinculado).
     * Receitas sem contrato ou cujo contrato não tem ramo definido caem no bucket "0" (Sem Ramo).
     * Otimizado para dashboard.
     *
     * @param string|null $dataInicio Data inicial opcional (filtra pela data de pagamento).
     * @param string|null $dataFim Data final opcional (filtra pela data de pagamento).
     * @return array Lista de [ramo_id, receitas]
     */
    public function getReceitasPagasAgrupadasPorRamo(?string $dataInicio = null, ?string $dataFim = null): array
    {
        $cacheSuffix = 'pagas_agrupadas_ramo_' . ($dataInicio ?? 'all') . '_' . ($dataFim ?? 'all');

        return $this->remember($cacheSuffix, function () use ($dataInicio, $dataFim) {
            $builder = $this->pagtoReceitasModel
                ->select('contratos.area_atuacao as ramo_id, SUM(fin_pgto_receitas.valor) as receitas', false)
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->join('contratos', 'contratos.id_contrato = fin_receitas.contrato_id', 'left')
                ->where('fin_receitas.deleted_at IS NULL')
                ->where('fin_pgto_receitas.deleted_at IS NULL');

            if ($dataInicio) {
                $builder->where('fin_pgto_receitas.pagamento_receita_dt >=', $dataInicio);
            }
            if ($dataFim) {
                $builder->where('fin_pgto_receitas.pagamento_receita_dt <=', $dataFim);
            }

            return $builder->groupBy('contratos.area_atuacao')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém o resumo financeiro de receitas para um contrato específico.
     * Otimizado para realizar agregação no banco de dados.
     *
     * @param int $contratoId
     * @return array [total_receitas, quantidade_receitas]
     */
    public function getResumoPorContrato(int $contratoId): array
    {
        return $this->remember("resumo_contrato_{$contratoId}", function () use ($contratoId) {
            $result = $this->pagtoReceitasModel
                ->select('SUM(fin_pgto_receitas.valor) as total, COUNT(fin_pgto_receitas.id_pgto_receita) as quantidade', false)
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->where('fin_receitas.contrato_id', $contratoId)
                ->where('fin_receitas.deleted_at IS NULL')
                ->where('fin_pgto_receitas.deleted_at IS NULL')
                ->get()
                ->getRowArray();

            return [
                'total_receitas'      => (float) ($result['total'] ?? 0.0),
                'quantidade_receitas' => (int) ($result['quantidade'] ?? 0),
            ];
        });
    }

    /**
     * Obtém os dados de receitas por mês para um contrato específico, formatados para gráficos.
     * Otimizado para realizar agregação no banco de dados.
     *
     * @param int $contratoId
     * @return array Lista de [mes, receitas]
     */
    public function getDadosGraficosPorContrato(int $contratoId): array
    {
        return $this->remember("grafico_contrato_{$contratoId}", function () use ($contratoId) {
            $db = \Config\Database::connect();
            $driver = $db->getPlatform();

            if ($driver === 'MySQLi') {
                $monthField = "DATE_FORMAT(COALESCE(fin_pgto_receitas.pagamento_receita_dt, fin_receitas.vencimento_dt), '%Y-%m')";
                $monthLabel = "DATE_FORMAT(COALESCE(fin_pgto_receitas.pagamento_receita_dt, fin_receitas.vencimento_dt), '%m/%Y')";
            } else {
                $monthField = "strftime('%Y-%m', COALESCE(fin_pgto_receitas.pagamento_receita_dt, fin_receitas.vencimento_dt))";
                $monthLabel = "strftime('%m/%Y', COALESCE(fin_pgto_receitas.pagamento_receita_dt, fin_receitas.vencimento_dt))";
            }

            return $this->pagtoReceitasModel
                ->select("$monthField as mes_ano, $monthLabel as mes, SUM(fin_pgto_receitas.valor) as receitas", false)
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->where('fin_receitas.contrato_id', $contratoId)
                ->where('fin_receitas.deleted_at IS NULL')
                ->where('fin_pgto_receitas.deleted_at IS NULL')
                ->groupBy(['mes_ano', 'mes'])
                ->orderBy('mes_ano', 'ASC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Obtém resumo estatístico de receitas a receber em uma única consulta.
     * Otimizado: O(1) queries em vez de O(N) processamento em PHP.
     *
     * @return array [total_atrasadas, valor_receber, vence_hoje, vence_semana]
     */
    public function getResumoEstatisticas(): array
    {
        return $this->remember('receitas_estatisticas_resumo', function () {
            $hoje = date('Y-m-d');
            $inicioSemana = date('Y-m-d', strtotime('monday this week'));
            $fimSemana = date('Y-m-d', strtotime('sunday this week'));

            // Subconsulta em nível de SQL para excluir receitas pagas
            $subQuery = $this->pagtoReceitasModel->db->table('fin_pgto_receitas')
                ->select('receita_id')
                ->where('deleted_at IS NULL')
                ->getCompiledSelect();

            $amanha = date('Y-m-d', strtotime('+1 day'));
            $proximaSemana = date('Y-m-d', strtotime($fimSemana . ' +1 day'));

            $result = $this->model->where("id_receita NOT IN ($subQuery)")
                ->where('fin_receitas.deleted_at', null)
                ->select('SUM(valor) as valor_receber', false)
                ->select("SUM(CASE WHEN vencimento_dt < '{$hoje}' THEN 1 ELSE 0 END) as total_atrasadas", false)
                ->select("SUM(CASE WHEN vencimento_dt >= '{$hoje}' AND vencimento_dt < '{$amanha}' THEN 1 ELSE 0 END) as vence_hoje", false)
                ->select("SUM(CASE WHEN vencimento_dt >= '{$inicioSemana}' AND vencimento_dt < '{$proximaSemana}' THEN 1 ELSE 0 END) as vence_semana", false)
                ->get()
                ->getRowArray();

            return [
                'total_atrasadas' => (int) ($result['total_atrasadas'] ?? 0),
                'valor_receber'   => (float) ($result['valor_receber'] ?? 0.0),
                'vence_hoje'      => (int) ($result['vence_hoje'] ?? 0),
                'vence_semana'    => (int) ($result['vence_semana'] ?? 0),
            ];
        });
    }

    /**
     * Busca o rateio de categorias de uma receita, com o nome de cada categoria.
     *
     * @param int $receitaId
     * @return array Lista de ['id', 'receita_id', 'categoria_id', 'valor', 'categoria_nome']
     */
    public function getCategoriasSplit(int $receitaId): array
    {
        return $this->categoriasSplitModel
            ->select('fin_receitas_categorias.*, fin_categorias.categoria as categoria_nome')
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_receitas_categorias.categoria_id', 'left')
            ->where('receita_id', $receitaId)
            ->orderBy('fin_receitas_categorias.id', 'ASC')
            ->findAll();
    }

    /**
     * Substitui o rateio de categorias de uma receita pelo novo conjunto informado.
     *
     * @param int $receitaId
     * @param array $categorias Lista de ['categoria_id' => int, 'valor' => float]
     */
    private function salvarCategorias(int $receitaId, array $categorias): void
    {
        $this->categoriasSplitModel->where('receita_id', $receitaId)->delete();

        if (empty($categorias)) {
            return;
        }

        $linhas = [];
        foreach ($categorias as $item) {
            if (empty($item['categoria_id']) || !isset($item['valor'])) {
                throw new InvalidArgumentException('Cada item do rateio de categorias precisa de categoria_id e valor.');
            }

            $linhas[] = [
                'receita_id'   => $receitaId,
                'categoria_id' => (int) $item['categoria_id'],
                'valor'        => (float) $item['valor'],
            ];
        }

        if (!$this->categoriasSplitModel->insertBatch($linhas)) {
            throw new \Exception('Erro ao salvar o rateio de categorias da receita.');
        }
    }

    /**
     * Anexa a cada item da lista o rateio de categorias correspondente, em lote
     * (evita N+1 queries), preenchendo 'categorias' (lista completa) e
     * 'nome_categoria' (nomes concatenados, para exibição/compatibilidade).
     *
     * @param array $itens Lista de receitas (ou registros relacionados), por referência.
     * @param string $idField Nome do campo que contém o id_receita em cada item.
     */
    private function anexarCategorias(array &$itens, string $idField = 'id_receita'): void
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
            ->select('fin_receitas_categorias.receita_id, fin_receitas_categorias.categoria_id, fin_receitas_categorias.valor, fin_categorias.categoria as categoria_nome')
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_receitas_categorias.categoria_id', 'left')
            ->whereIn('receita_id', $ids)
            ->findAll();

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[$linha['receita_id']][] = $linha;
        }

        foreach ($itens as &$item) {
            $categorias = $mapa[$item[$idField]] ?? [];
            $item['categorias'] = $categorias;
            $item['nome_categoria'] = implode(', ', array_column($categorias, 'categoria_nome'));
        }
    }

    /**
     * Subconsulta que resolve os IDs de receita classificados em uma categoria.
     */
    private function categoriaFiltroSubquery(string $idField, int $categoriaId): string
    {
        return $this->categoriasSplitModel->builder()
            ->select($idField)
            ->where('categoria_id', $categoriaId)
            ->getCompiledSelect();
    }

    /**
     * Subconsulta que resolve os IDs de receita cuja categoria (nome) casa com o termo de busca.
     */
    private function categoriaLikeSubquery(string $idField, string $termo): string
    {
        return $this->categoriasSplitModel->builder()
            ->select($idField)
            ->join('fin_categorias', 'fin_categorias.id_categoria = fin_receitas_categorias.categoria_id')
            ->like('fin_categorias.categoria', $termo)
            ->getCompiledSelect();
    }
}
