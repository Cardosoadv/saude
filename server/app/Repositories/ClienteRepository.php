<?php

namespace App\Repositories;

use App\Models\ClientesModel;
use App\Repositories\Traits\EscopoPrivacidadeTrait;
use App\Services\AcessoEntidadeService;

/**
 * Repositório para operações de banco de dados relacionadas a clientes.
 *
 * Esta classe encapsula todas as consultas ao banco de dados relacionadas
 * a clientes, fornecendo uma camada de abstração entre o serviço e o modelo.
 *
 * @version 1.1.0
 */
class ClienteRepository extends BaseRepository
{
    use EscopoPrivacidadeTrait;

    /**
     * Construtor do ClienteRepository.
     */
    public function __construct(?ClientesModel $model = null, ?AcessoEntidadeService $acessos = null)
    {
        parent::__construct();
        $this->acessos = $acessos;
        $this->model = $model ?? model('ClientesModel');
        $this->cacheKey = 'clientes';
    }

    /**
     * Busca clientes com filtros e paginação.
     *
     * @param array $filters Filtros de busca
     * @param int $perPage Itens por página
     * @param int $page Número da página
     * @return array
     */
    public function buscarClientes(array $filters = [], int $perPage = 25, int $page = 1): array
    {
        $filtersKey = md5(json_encode($filters));

        return $this->remember("buscar_{$filtersKey}_page_{$page}_per_{$perPage}" . $this->sufixoEscopo('cliente'), function () use ($filters, $perPage, $page) {
            $builder = $this->model
                ->select('clientes.*, responsavel.username as responsavel_nome')
                ->join('users as responsavel', 'responsavel.id = clientes.responsavel_id', 'left')
                ->orderBy('clientes.nome', 'ASC');
            $this->escopo($builder, 'cliente');

            if (!empty($filters['responsavel_id'])) {
                $builder->where('clientes.responsavel_id', (int) $filters['responsavel_id']);
            }

            // Filtro de pesquisa por nome, documento ou email
            if (!empty($filters['search'])) {
                $builder->groupStart()
                    ->like('clientes.nome', $filters['search'])
                    ->orLike('clientes.documento', $filters['search'])
                    ->orLike('clientes.email', $filters['search'])
                    ->groupEnd();
            }

            // Filtro por tipo de cliente
            if (!empty($filters['tipo'])) {
                $tipo = $filters['tipo'] === 'pessoa_fisica' ? 'F' : 'J';
                $builder->where('clientes.tipo_cliente', $tipo);
            }

            // Filtro por status
            if (isset($filters['status']) && $filters['status'] !== '') {
                $ativo = ($filters['status'] === 'ativo' || (string) $filters['status'] === '1') ? 1 : 0;
                $builder->where('clientes.ativo', $ativo);
            }

            // Ordenação
            if (!empty($filters['ordenar'])) {
                switch ($filters['ordenar']) {
                    case 'nome':
                        $builder->orderBy('clientes.nome', 'ASC');
                        break;
                    case 'recente':
                        $builder->orderBy('clientes.id_cliente', 'DESC');
                        break;
                    case 'antigo':
                        $builder->orderBy('clientes.id_cliente', 'ASC');
                        break;
                }
            }

            return [
                'clientes' => $builder->paginate($perPage, 'default', $page),
                'pager'    => $builder->pager,
            ];
        });
    }

    /**
     * Retorna o ID do cliente associado ao usuário logado.
     *
     * @return int|null ID do cliente ou null se não encontrado.
     */
    public function getUsuarioCliente(): ?int
    {
        return $this->getClienteIdByUserId(user_id());
    }


    /**
     * Busca estatísticas gerais de clientes.
     * Otimizado para realizar apenas uma consulta ao banco de dados com agregação condicional.
     *
     * @return array
     */
    public function buscarEstatisticas(): array
    {
        return $this->remember('estatisticas_gerais' . $this->sufixoEscopo('cliente'), function () {
            $builder = $this->model->builder();
            $this->escopo($builder, 'cliente');

            // Ensure soft deletes are respected if the model uses them
            if ($this->model->useSoftDeletes) {
                $builder->where($this->model->getTable() . '.' . $this->model->getDeletedAtField(), null);
            }

            // Realiza agregação condicional em uma única consulta SQL (O(1) queries em vez de O(4))
            $result = $builder
                ->select('COUNT(*) as total')
                ->select('SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos')
                ->select("SUM(CASE WHEN tipo_cliente = 'F' THEN 1 ELSE 0 END) as pessoa_fisica")
                ->select("SUM(CASE WHEN tipo_cliente = 'J' THEN 1 ELSE 0 END) as pessoa_juridica")
                ->get()
                ->getRowArray();

            return [
                'total'           => (int) ($result['total'] ?? 0),
                'ativos'          => (int) ($result['ativos'] ?? 0),
                'pessoa_fisica'   => (int) ($result['pessoa_fisica'] ?? 0),
                'pessoa_juridica' => (int) ($result['pessoa_juridica'] ?? 0),
            ];
        });
    }

    /**
     * Busca clientes cadastrados recentemente.
     *
     * @param int $limit Limite de registros
     * @return array
     */
    public function buscarClientesRecentes(int $limit = 5): array
    {
        return $this->remember("recentes_{$limit}" . $this->sufixoEscopo('cliente'), function () use ($limit) {
            return $this->escopo($this->model, 'cliente')
                ->select('id_cliente, nome, documento, created_at')
                ->orderBy('id_cliente', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Busca aniversariantes do mês atual.
     *
     * @return array
     */
    public function buscarAniversariantes(): array
    {
        $mesAtual = date('m');

        return $this->remember("aniversariantes_{$mesAtual}" . $this->sufixoEscopo('cliente'), function () use ($mesAtual) {
            $db = \Config\Database::connect();
            $driver = $db->getPlatform();

            $builder = $this->model->select('id_cliente, nome, data_nascimento');
            $this->escopo($builder, 'cliente');

            if ($driver === 'MySQLi') {
                $builder->where('MONTH(data_nascimento)', $mesAtual, false)
                        ->orderBy('DAY(data_nascimento)', 'ASC');
            } else {
                // Fallback para SQLite usado em testes
                $builder->where("strftime('%m', data_nascimento)", $mesAtual)
                        ->orderBy("strftime('%d', data_nascimento)", 'ASC');
            }

            return $builder->where('ativo', 1)->findAll();
        });
    }

    /**
     * Busca estatísticas específicas de um cliente.
     *
     * @param int $clienteId
     * @return array
     */
    public function buscarEstatisticasCliente(int $clienteId): array
    {
        return $this->remember("estatisticas_cliente_{$clienteId}", function () use ($clienteId) {
            $db = \Config\Database::connect();

            $receitas = 0;
            if ($db->tableExists('fin_receitas') && $db->tableExists('fin_pgto_receitas')) {
                $receitas = $db->table('fin_receitas')
                    ->join('fin_pgto_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                    ->selectSum('fin_pgto_receitas.valor')
                    ->where('fin_receitas.cliente_id', $clienteId)
                    ->get()->getRow()->valor ?? 0;
            }

            $tarefas = $db->table('tarefas')
                ->where('cliente_id', $clienteId)
                ->where('deleted_at', null)
                ->countAllResults();

            return [
                'receitas' => (float) $receitas,
                'tarefas'  => $tarefas,
            ];
        });
    }

    /**
     * Busca movimentações financeiras de um cliente.
     *
     * @param int $clienteId
     * @param int $limit
     * @return array
     */
    public function buscarMovimentacoesCliente(int $clienteId, int $limit = 5): array
    {
        return $this->remember("movimentacoes_cliente_{$clienteId}_{$limit}", function () use ($clienteId, $limit) {
            $db = \Config\Database::connect();

            if ($db->tableExists('fin_pgto_receitas')) {
                $query = $db->query("
                    SELECT
                        r.id_receita as id,
                        r.id_receita,
                        pr.id_pgto_receita,
                        r.receita as descricao,
                        COALESCE(pr.valor, r.valor) as valor,
                        COALESCE(pr.pagamento_receita_dt, r.vencimento_dt) as data,
                        CASE WHEN pr.id_pgto_receita IS NOT NULL THEN 1 ELSE 0 END as pago,
                        'fin_receita' as tipo,
                        r.created_at
                    FROM fin_receitas r
                    LEFT JOIN fin_pgto_receitas pr ON pr.receita_id = r.id_receita AND pr.deleted_at IS NULL
                    WHERE r.cliente_id = ?
                    ORDER BY COALESCE(pr.pagamento_receita_dt, r.vencimento_dt) DESC, r.id_receita DESC
                    LIMIT ?
                ", [$clienteId, $limit]);

                return $query->getResultArray();
            }

            $query = $db->query("
                SELECT
                    id_receita as id,
                    id_receita,
                    NULL as id_pgto_receita,
                    receita as descricao,
                    valor,
                    vencimento_dt as data,
                    0 as pago,
                    'fin_receita' as tipo,
                    created_at
                FROM fin_receitas
                WHERE cliente_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ", [$clienteId, $limit]);

            return $query->getResultArray();
        });
    }

    /**
     * Obtém o ID do cliente associado a um ID de usuário.
     *
     * @param int $userId
     * @return int|null
     */
    public function getClienteIdByUserId(int $userId): ?int
    {
        return $this->remember("user_id_{$userId}", function () use ($userId) {
            $usuarioCliente = $this->model
                ->select('id_cliente')
                ->where('usuario_id', $userId)
                ->first();

            return $usuarioCliente['id_cliente'] ?? null;
        });
    }

    /**
     * Retorna o ID do cliente pelo nome ou razão social.
     *
     * @param string $nome
     * @return int|null
     */
    public function getClientePeloNome(string $nome): ?int
    {
        return $this->remember('nome_' . md5($nome), function () use ($nome) {
            $cliente = $this->model
                ->select('id_cliente')
                ->groupStart()
                    ->where('nome', $nome)
                    ->orWhere('razao_social', $nome)
                ->groupEnd()
                ->first();

            return $cliente['id_cliente'] ?? null;
        });
    }

    /**
     * Retorna o ID do cliente pelo documento.
     *
     * @param string $documento
     * @return int|null
     */
    public function getClientePeloDocumento(string $documento): ?int
    {
        return $this->remember('doc_' . md5($documento), function () use ($documento) {
            $cliente = $this->model
                ->select('id_cliente')
                ->where('documento', $documento)
                ->first();

            return $cliente['id_cliente'] ?? null;
        });
    }

    /**
     * Verifica se existe cliente com o documento.
     *
     * @param string $documento
     * @param int|null $excluirId ID para excluir da verificação
     * @return bool
     */
    public function existeDocumento(string $documento, ?int $excluirId = null): bool
    {
        $builder = $this->model->where('documento', $documento);

        if ($excluirId !== null) {
            $builder->where('id_cliente !=', $excluirId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Realiza uma busca leve para autocomplete.
     * Otimizado para selecionar apenas campos necessários e evitar COUNT(*) de paginação.
     *
     * @param string $term Termo de busca
     * @param int $limit Limite de resultados
     * @return array
     */
    public function search(string $term, int $limit = 15): array
    {
        $normalizedTerm = mb_strtolower(trim($term));
        $cacheKey = "search_" . md5($normalizedTerm) . "_lim_{$limit}" . $this->sufixoEscopo('cliente');

        return $this->remember($cacheKey, function () use ($term, $limit) {
            return $this->escopo($this->model, 'cliente')
                ->select('id_cliente, nome, documento')
                ->groupStart()
                    ->like('nome', $term)
                    ->orLike('documento', $term)
                ->groupEnd()
                ->orderBy('nome', 'ASC')
                ->findAll($limit);
        });
    }

    /**
     * Busca um cliente pelo email.
     */
    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        return $this->remember("email_" . md5($email), function () use ($email) {
            return $this->model->where('email', $email)->where('ativo', 1)->first();
        });
    }
}
