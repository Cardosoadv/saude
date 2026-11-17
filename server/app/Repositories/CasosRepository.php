<?php

namespace App\Repositories;

use App\Models\CasosModel;

/**
 * Repositório para gerenciar casos (extrajudiciais e judiciais).
 *
 * Lida com operações de banco de dados para a tabela 'casos'.
 */
class CasosRepository extends BaseRepository
{
    /**
     * Construtor da classe CasosRepository.
     */
    public function __construct(?CasosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('CasosModel');
        $this->cacheKey = 'casos';
    }

    /**
     * Retorna todos os casos.
     *
     * @return array Lista de todos os casos.
     */
    public function buscarTodos(): array
    {
        return $this->findAll();
    }

    /**
     * Busca casos com filtros e paginação.
     *
     * @param string|null $search Termo de busca.
     * @param string $sortField Campo para ordenação.
     * @param string $sortOrder Ordem de classificação.
     * @param int $perPage Itens por página.
     * @param array $filtros Filtros adicionais.
     * @return array Array com 'casos' e 'pager'.
     */
    public function buscarCasos(?string $search, string $sortField, string $sortOrder, int $perPage = 25, array $filtros = [], int $page = 1): array
    {
        $filtrosKey = md5(serialize($filtros) . ($search ?? ''));
        $suffix = "buscar_{$filtrosKey}_page_{$page}_per_{$perPage}_{$sortField}_{$sortOrder}";

        return $this->remember($suffix, function () use ($search, $sortField, $sortOrder, $perPage, $filtros, $page) {
            $builder = $this->model->select('casos.*, clientes.nome as cliente_nome');
            $builder->join('clientes', 'casos.cliente_id = clientes.id_cliente', 'left');

            // Filtro de busca
            if (!empty($search)) {
                $builder->groupStart()
                    ->like('casos.titulo', $search)
                    ->orLike('clientes.nome', $search)
                    ->orLike('casos.descricao', $search)
                    ->groupEnd();
            }

            // Filtro por tipo
            if (!empty($filtros['tipo'])) {
                $builder->where('casos.tipo', $filtros['tipo']);
            }

            // Filtro por status
            if (!empty($filtros['status'])) {
                $builder->where('casos.status', $filtros['status']);
            }

            // Filtro por data de início
            if (!empty($filtros['data_inicio'])) {
                $builder->where('casos.data_inicio >=', $filtros['data_inicio']);
            }

            $builder->orderBy('casos.' . $sortField, $sortOrder);

            return [
                'casos' => $builder->paginate($perPage, 'default', $page),
                'pager' => $this->model->pager,
            ];
        });
    }

    /**
     * Busca um caso pelo ID.
     *
     * @param int $id ID do caso.
     * @return array|null Dados do caso ou null se não encontrado.
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->remember((string) $id, function () use ($id) {
            return $this->model
                ->select('casos.*, clientes.nome as cliente_nome, contratos.numero_contrato as contrato_numero, contratos.id_contrato, juris_ramos_direito.nome as area_do_direito_nome, processos.numero_processo as numero_processo_vinculado, processos.numeroprocessocommascara as numero_processo_formatado')
                ->join('clientes', 'casos.cliente_id = clientes.id_cliente', 'left')
                ->join('contratos', 'casos.contrato_id = contratos.id_contrato', 'left')
                ->join('juris_ramos_direito', 'casos.area_do_direito = juris_ramos_direito.id', 'left')
                ->join('processos', 'casos.processo_id = processos.id_processo', 'left')
                ->where('casos.id_caso', $id)
                ->first();
        });
    }

    /**
     * Salva um caso (criação ou atualização).
     *
     * @param array $data Dados do caso.
     * @param int|null $id ID do caso (null para criação).
     * @return int|bool ID do caso salvo ou false em caso de erro.
     */
    public function salvar(array $data, ?int $id = null)
    {
        if ($id) {
            return $this->update($id, $data) ? $id : false;
        } else {
            return $this->create($data);
        }
    }

    /**
     * Exclui um caso (soft delete).
     *
     * @param int $id ID do caso.
     * @return bool True se excluído com sucesso.
     */
    public function excluir(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Busca casos recentes.
     *
     * @param int $limite Quantidade de casos.
     * @return array
     */
    public function buscarRecentes(int $limite = 5): array
    {
        return $this->remember("recentes_{$limite}", function () use ($limite) {
            return $this->model
                ->select('casos.*, clientes.nome as cliente_nome')
                ->join('clientes', 'casos.cliente_id = clientes.id_cliente', 'left')
                ->orderBy('casos.created_at', 'DESC')
                ->limit($limite)
                ->findAll();
        });
    }

    /**
     * Obtém estatísticas de casos.
     * Otimizado para realizar apenas uma consulta ao banco de dados.
     *
     * @param int|null $clienteId ID do cliente para filtrar estatísticas.
     * @return array
     */
    public function obterEstatisticas(?int $clienteId = null): array
    {
        return $this->remember('estatisticas_' . ($clienteId ?? 'geral'), function () use ($clienteId) {
            $builder = $this->model->builder();

            if ($clienteId !== null) {
                $builder->where('cliente_id', $clienteId);
            }

            // Ensure we only count non-deleted records (soft delete support)
            $result = $builder
                ->select('COUNT(*) as total')
                ->select("SUM(CASE WHEN status IN ('Ativo', 'Em Andamento') THEN 1 ELSE 0 END) as ativos")
                ->select("SUM(CASE WHEN tipo = 'Judicial' THEN 1 ELSE 0 END) as judiciais")
                ->select("SUM(CASE WHEN status = 'Encerrado' THEN 1 ELSE 0 END) as encerrados")
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            return [
                'total'      => (int) ($result['total'] ?? 0),
                'ativos'     => (int) ($result['ativos'] ?? 0),
                'judiciais'  => (int) ($result['judiciais'] ?? 0),
                'encerrados' => (int) ($result['encerrados'] ?? 0),
            ];
        });
    }

    /**
     * Exclui múltiplos casos.
     *
     * @param array $ids Array de IDs.
     * @return int Número de casos excluídos.
     */
    public function excluirVarios(array $ids): int
    {
        $result = $this->model->whereIn('id_caso', $ids)->delete();
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Atualiza status de múltiplos casos.
     *
     * @param array $ids Array de IDs.
     * @param string $status Novo status.
     * @return bool
     */
    public function atualizarStatusVarios(array $ids, string $status): bool
    {
        $result = $this->model->whereIn('id_caso', $ids)->set(['status' => $status])->update();
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Busca casos por cliente.
     *
     * @param int $clienteId
     * @return array
     */
    public function buscarPorCliente(int $clienteId, ?string $search, string $sortField, string $sortOrder, int $perPage, array $filtros = [], int $page = 1): array
    {
        $filtrosKey = md5(serialize($filtros) . ($search ?? ''));
        $suffix = "por_cliente_{$clienteId}_{$filtrosKey}_page_{$page}_per_{$perPage}_{$sortField}_{$sortOrder}";

        return $this->remember($suffix, function () use ($clienteId, $search, $sortField, $sortOrder, $perPage, $filtros, $page) {
            $builder = $this->model
                ->select('casos.*, clientes.nome as cliente_nome')
                ->join('clientes', 'casos.cliente_id = clientes.id_cliente', 'left')
                ->where('casos.cliente_id', $clienteId);

            if (!empty($search)) {
                $builder->groupStart()
                    ->like('casos.titulo', $search)
                    ->orLike('clientes.nome', $search)
                    ->groupEnd();
            }

            // Filtro por tipo
            if (!empty($filtros['tipo'])) {
                $builder->where('casos.tipo', $filtros['tipo']);
            }

            // Filtro por status
            if (!empty($filtros['status'])) {
                $builder->where('casos.status', $filtros['status']);
            }

            // Filtro por data de início
            if (!empty($filtros['data_inicio'])) {
                $builder->where('casos.data_inicio >=', $filtros['data_inicio']);
            }

            $builder->orderBy('casos.' . $sortField, $sortOrder);

            return [
                'casos' => $builder->paginate($perPage, 'default', $page),
                'pager' => $this->model->pager,
            ];
        });
    }

    /**
     * Realiza uma busca leve para autocomplete.
     * Otimizado para selecionar apenas campos necessários e evitar overhead de paginação.
     *
     * @param string $term Termo de busca
     * @param int $limit Limite de resultados
     * @param int|null $clienteId ID do cliente para filtrar
     * @return array
     */
    public function search(string $term, int $limit = 15, ?int $clienteId = null): array
    {
        $normalizedTerm = mb_strtolower(trim($term));
        $cacheKey = "search_" . md5($normalizedTerm . ($clienteId ?? '')) . "_lim_{$limit}";

        return $this->remember($cacheKey, function () use ($term, $limit, $clienteId) {
            $builder = $this->model
                ->select('id_caso, titulo, status');

            if ($clienteId !== null) {
                $builder->where('cliente_id', $clienteId);
            }

            return $builder->groupStart()
                    ->like('titulo', $term)
                    ->orLike('descricao', $term)
                ->groupEnd()
                ->orderBy('titulo', 'ASC')
                ->findAll($limit);
        });
    }

    /**
     * Busca casos para exportação (sem paginação).
     *
     * @param array $filtros
     * @return array
     */
    public function buscarParaExportacao(array $filtros = []): array
    {
        $builder = $this->model->select('casos.*, clientes.nome as cliente_nome');
        $builder->join('clientes', 'casos.cliente_id = clientes.id_cliente', 'left');

        if (!empty($filtros['search'])) {
            $builder->groupStart()
                ->like('casos.titulo', $filtros['search'])
                ->orLike('clientes.nome', $filtros['search'])
                ->groupEnd();
        }

        if (!empty($filtros['tipo'])) {
            $builder->where('casos.tipo', $filtros['tipo']);
        }

        if (!empty($filtros['status'])) {
            $builder->where('casos.status', $filtros['status']);
        }

        return $builder->orderBy('casos.created_at', 'DESC')->findAll();
    }

    /**
     * Busca documentos relacionados ao caso.
     *
     * Nota: Esta função assume que existe uma tabela 'documentos_casos'
     * Se não existir, adapte conforme sua estrutura
     *
     * @param int $casoId
     * @return array
     */
    public function buscarDocumentos(int $casoId): array
    {
        return $this->remember("documentos_{$casoId}", function () use ($casoId) {
            $db = \Config\Database::connect();

            // Verifica se a tabela existe
            if (!$db->tableExists('documentos_casos')) {
                return [];
            }

            return $db->table('documentos_casos')
                ->where('caso_id', $casoId)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Busca histórico do caso.
     *
     * Nota: Esta função assume que existe uma tabela 'casos_historico'
     * Se não existir, adapte conforme sua estrutura
     *
     * @param int $casoId
     * @return array
     */
    public function buscarHistorico(int $casoId): array
    {
        return $this->remember("historico_{$casoId}", function () use ($casoId) {
            $db = \Config\Database::connect();

            // Verifica se a tabela existe
            if (!$db->tableExists('casos_historico')) {
                return [];
            }

            return $db->table('casos_historico')
                ->where('caso_id', $casoId)
                ->orderBy('created_at', 'DESC')
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Adiciona um registro ao histórico do caso.
     *
     * @param array $dados
     * @return bool
     */
    public function adicionarHistorico(array $dados): bool
    {
        $db = \Config\Database::connect();
        
        if (!$db->tableExists('casos_historico')) {
            return false;
        }

        $dados['created_at'] = date('Y-m-d H:i:s');
        
        $result = $db->table('casos_historico')->insert($dados);
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }

    /**
     * Obtém estatísticas específicas de um caso.
     *
     * @param int $casoId
     * @return array
     */
    public function obterEstatisticasCaso(int $casoId): array
    {
        return $this->remember("stats_caso_{$casoId}", function () use ($casoId) {
            $db = \Config\Database::connect();

            $stats = [
                'documentos' => 0,
                'anotacoes' => 0,
            ];

            // Conta documentos se a tabela existir
            if ($db->tableExists('documentos_casos')) {
                $stats['documentos'] = $db->table('documentos_casos')
                    ->where('caso_id', $casoId)
                    ->countAllResults();
            }

            // Conta anotações se a tabela existir
            if ($db->tableExists('anotacoes_casos')) {
                $stats['anotacoes'] = $db->table('anotacoes_casos')
                    ->where('caso_id', $casoId)
                    ->countAllResults();
            }

            return $stats;
        });
    }

    /**
     * Busca casos relacionados (mesmo cliente ou área).
     *
     * @param int $casoId
     * @return array
     */
    public function buscarRelacionados(int $casoId): array
    {
        return $this->remember("relacionados_{$casoId}", function () use ($casoId) {
            $caso = $this->buscarPorId($casoId);

            if (!$caso) {
                return [];
            }

            $builder = $this->model->select('casos.*');
            $builder->where('casos.id_caso !=', $casoId);

            // Busca casos do mesmo cliente
            if (!empty($caso['cliente_id'])) {
                $builder->where('casos.cliente_id', $caso['cliente_id']);
            }

            return $builder
                ->orderBy('casos.created_at', 'DESC')
                ->limit(5)
                ->findAll();
        });
    }

    /**
     * Busca timeline detalhada do caso.
     *
     * @param int $casoId
     * @return array
     */
    public function buscarTimeline(int $casoId): array
    {
        return $this->remember("timeline_{$casoId}", function () use ($casoId) {
            $db = \Config\Database::connect();
            $timeline = [];

            // Adiciona criação do caso
            $caso = $this->buscarPorId($casoId);
            if ($caso) {
                $timeline[] = [
                    'tipo' => 'criacao',
                    'titulo' => 'Caso criado',
                    'descricao' => 'Caso cadastrado no sistema',
                    'data' => $caso['created_at'],
                    'icone' => 'bi-plus-circle',
                ];
            }

            // Adiciona histórico se existir
            if ($db->tableExists('casos_historico')) {
                $historico = $db->table('casos_historico')
                    ->where('caso_id', $casoId)
                    ->orderBy('created_at', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($historico as $item) {
                    $timeline[] = [
                        'tipo' => 'historico',
                        'titulo' => $item['acao'] ?? 'Atualização',
                        'descricao' => $item['descricao'] ?? '',
                        'data' => $item['created_at'],
                        'icone' => 'bi-clock-history',
                    ];
                }
            }

            // Adiciona transformação em processo se houver
            if (!empty($caso['processo_id']) && !empty($caso['transformado_em'])) {
                $timeline[] = [
                    'tipo' => 'transformacao',
                    'titulo' => 'Transformado em processo',
                    'descricao' => 'Caso convertido para processo judicial',
                    'data' => $caso['transformado_em'],
                    'icone' => 'bi-arrow-right-circle',
                ];
            }

            // Ordena por data
            usort($timeline, function ($a, $b) {
                return strtotime($a['data']) - strtotime($b['data']);
            });

            return $timeline;
        });
    }
}
