<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ContratosModel;
use App\Repositories\Traits\EscopoPrivacidadeTrait;
use App\Services\AcessoEntidadeService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Pager\Pager;

/**
 * Repositório para gerenciar contratos.
 *
 * Lida com operações de banco de dados para a tabela contratos.
 */
class ContratosRepository extends BaseRepository
{
    use EscopoPrivacidadeTrait;

    public function __construct(?ContratosModel $model = null, ?AcessoEntidadeService $acessos = null)
    {
        parent::__construct();
        $this->acessos = $acessos;
        $this->model = $model ?? model('ContratosModel');
        $this->cacheKey = 'contratos';
    }

    /**
     * Lista contratos com filtros avançados e paginação.
     *
     * @param array $filtros Filtros de busca (s, status, data_inicio, ordenar)
     * @param int $perPage Itens por página
     * @return array{contratos: array, pager: Pager}
     */
    public function buscarContratos(array $filtros = [], int $perPage = 25): array
    {
        $this->filtrarContratos($filtros);

        return [
            'contratos' => $this->model->paginate($perPage),
            'pager'     => $this->model->pager,
        ];
    }

    /**
     * Lista todos os contratos aplicando os filtros (sem paginação).
     */
    public function buscarTodos(array $filtros = []): array
    {
        $this->filtrarContratos($filtros);
        return $this->model->findAll();
    }

    /**
     * Busca um contrato por ID.
     */
    public function findById(int $id): ?array
    {
        $contrato = $this->model
            ->select('contratos.*, clientes.nome as cliente_nome, clientes.nome as nome_cliente, clientes.id_cliente as cliente_id, contratos.area_atuacao as ramo_nome, contratos.area_atuacao as ramo_direito, responsavel.username as responsavel_nome')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->join('users as responsavel', 'responsavel.id = contratos.responsavel_id', 'left')
            ->where('contratos.id_contrato', $id)
            ->where('contratos.deleted_at', null)
            ->first();

        if ($contrato) {
            $contrato['receitas']  = $this->getReceitas($id);
            $contrato['despesas']  = $this->getDespesas($id);
        }

        return $contrato;
    }

    /**
     * Cria um novo contrato.
     */
    public function create(array $data): int
    {
        if (!$this->model->insert($data)) {
            $errors = $this->model->errors();
            throw new \RuntimeException('Erro ao criar contrato: ' . implode(', ', $errors));
        }

        $this->clearCache();
        return (int) $this->model->getInsertID();
    }

    /**
     * Atualiza um contrato existente.
     */
    public function update(int $id, array $data): bool
    {
        if (!$this->model->update($id, $data)) {
            $errors = $this->model->errors();
            throw new \RuntimeException('Erro ao atualizar contrato: ' . implode(', ', $errors));
        }

        $this->clearCache();
        return true;
    }

    /**
     * Exclui (soft delete) um contrato.
     */
    public function delete(int $id): bool
    {
        if (!$this->model->delete($id)) {
            throw new \RuntimeException('Erro ao excluir contrato.');
        }
        $this->clearCache();
        return true;
    }

    /**
     * Aplica os filtros e retorna o builder.
     */
    protected function filtrarContratos(array $filtros): object // Returns Builder instance
    {
        $builder = $this->model
            ->select('contratos.*, clientes.nome as cliente_nome, clientes.nome as nome_cliente, contratos.area_atuacao as ramo_nome, contratos.area_atuacao as ramo_direito, responsavel.username as responsavel_nome')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->join('users as responsavel', 'responsavel.id = contratos.responsavel_id', 'left');

        $builder->where('contratos.deleted_at', null);
        $this->escopo($builder, 'contrato');

        if (!empty($filtros['responsavel_id'])) {
            $builder->where('contratos.responsavel_id', (int) $filtros['responsavel_id']);
        }

        if (!empty($filtros['s'])) {
            $termo = $filtros['s'];
            $builder->groupStart()
                ->like('clientes.nome', $termo)
                ->orLike('contratos.numero_contrato', $termo)
                ->orLike('contratos.objeto', $termo)
                ->orLike('contratos.area_atuacao', $termo)
                ->groupEnd();
        }

        if (!empty($filtros['ramo_id'])) {
            $builder->where('contratos.area_atuacao', $filtros['ramo_id']);
        }

        if (!empty($filtros['status'])) {
            $this->aplicarFiltroStatus($builder, $filtros['status']);
        }

        if (!empty($filtros['data_inicio'])) {
            $builder->where('contratos.data_inicio >=', $filtros['data_inicio']);
        }

        $ordenacao = $filtros['ordenar'] ?? 'recente';
        $this->aplicarOrdenacao($builder, $ordenacao);

        $builder->groupBy('contratos.id_contrato');

        return $builder;
    }

    private function aplicarFiltroStatus(object $builder, string $status): void
    {
        switch ($status) {
            case 'ativo':
                $builder->where('contratos.status', 'Ativo');
                break;
            case 'vencido':
                $builder->groupStart()
                    ->where('contratos.data_fim IS NOT NULL')
                    ->where('contratos.data_fim >', '1900-01-01')
                    ->where('contratos.data_fim <', date('Y-m-d'))
                    ->groupEnd();
                break;
            case 'cancelado':
                $builder->where('contratos.status', 'Cancelado');
                break;
        }
    }

    private function aplicarOrdenacao(object $builder, string $ordenacao): void
    {
        $mapaOrdenacao = [
            'nome'        => ['coluna' => 'clientes.nome',         'direcao' => 'ASC'],
            'antigo'      => ['coluna' => 'contratos.data_inicio', 'direcao' => 'ASC'],
            'valor_maior' => ['coluna' => 'contratos.valor',       'direcao' => 'DESC'],
            'valor_menor' => ['coluna' => 'contratos.valor',       'direcao' => 'ASC'],
            'recente'     => ['coluna' => 'contratos.data_inicio', 'direcao' => 'DESC'],
        ];

        $regra = $mapaOrdenacao[$ordenacao] ?? $mapaOrdenacao['recente'];

        $builder->orderBy($regra['coluna'], $regra['direcao']);
    }

    // --- Métodos Auxiliares de Consulta (Queries Específicas) ---

    /**
     * Obtém estatísticas gerais de contratos em uma única consulta.
     * Otimizado com agregação condicional (O(1) queries).
     */
    public function getEstatisticas(): array
    {
        $hoje = date('Y-m-d');

        $builder = $this->db->table('contratos');
        $this->escopo($builder, 'contrato');

        $result = $builder
            ->select('COUNT(*) as total')
            ->select("SUM(CASE WHEN status = 'Ativo' THEN 1 ELSE 0 END) as ativos")
            ->select("SUM(CASE WHEN data_fim IS NOT NULL AND data_fim > '1900-01-01' AND data_fim < '$hoje' THEN 1 ELSE 0 END) as vencidos")
            ->select('SUM(valor) as valor_total')
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return [
            'total'       => (int) ($result['total'] ?? 0),
            'ativos'      => (int) ($result['ativos'] ?? 0),
            'vencidos'    => (int) ($result['vencidos'] ?? 0),
            'valor_total' => (float) ($result['valor_total'] ?? 0),
        ];
    }

    public function getContratosProximosVencimento(int $limit = 5): array
    {
        return $this->model
            ->select('contratos.id_contrato, contratos.data_fim, clientes.nome as cliente_nome')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->where('contratos.deleted_at', null)
            ->where($this->condicaoEscopo('contrato', 'contratos') ?? '1 = 1', null, false)
            ->where('contratos.data_fim !=', null)
            ->where('contratos.data_fim >', '1900-01-01')
            ->orderBy('contratos.data_fim', 'ASC')
            ->limit($limit)
            ->find();
    }

    public function getContratosRecentes(int $limit = 5): array
    {
        return $this->model
            ->select('contratos.id_contrato, contratos.numero_contrato, contratos.data_inicio, clientes.nome as cliente_nome')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->where('contratos.deleted_at', null)
            ->where($this->condicaoEscopo('contrato', 'contratos') ?? '1 = 1', null, false)
            ->orderBy('contratos.created_at', 'DESC')
            ->limit($limit)
            ->find();
    }

    public function getReceitas(int $contratoId): array
    {
        return $this->db->table('fin_receitas')
            ->select('fin_receitas.id_receita, fin_receitas.receita, fin_receitas.vencimento_dt, fin_receitas.valor, (CASE WHEN COUNT(fin_pgto_receitas.id_pgto_receita) > 0 THEN "Pago" ELSE "Pendente" END) as status')
            ->join('fin_pgto_receitas', 'fin_pgto_receitas.receita_id = fin_receitas.id_receita AND fin_pgto_receitas.deleted_at IS NULL', 'left')
            ->where('fin_receitas.contrato_id', $contratoId)
            ->where('fin_receitas.deleted_at', null)
            ->groupBy('fin_receitas.id_receita')
            ->get()
            ->getResultArray();
    }

    public function getDespesas(int $contratoId): array
    {
        return $this->db->table('fin_despesas')
            ->select('fin_despesas.id_despesa, fin_despesas.despesa, fin_despesas.vencimento_dt, fin_despesas.valor, (CASE WHEN COUNT(fin_pgto_despesas.id_pgto_despesa) > 0 THEN "Pago" ELSE "Pendente" END) as status')
            ->join('fin_pgto_despesas', 'fin_pgto_despesas.despesa_id = fin_despesas.id_despesa AND fin_pgto_despesas.deleted_at IS NULL', 'left')
            ->where('fin_despesas.contrato_id', $contratoId)
            ->where('fin_despesas.deleted_at', null)
            ->groupBy('fin_despesas.id_despesa')
            ->get()
            ->getResultArray();
    } 

    public function buscarPorTermo(string $term): array
    {
        return $this->model->select('contratos.id_contrato, contratos.numero_contrato, contratos.objeto, clientes.nome as nome_cliente')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->where($this->condicaoEscopo('contrato', 'contratos') ?? '1 = 1', null, false)
            ->groupStart()
            ->like('contratos.numero_contrato', $term)
            ->orLike('clientes.nome', $term)
            ->groupEnd()
            ->limit(10)
            ->findAll();
    }

    public function getDocumentos(int $contratoId): array
    {
        return $this->db->table('documentos_contratos')
            ->where('contrato_id', $contratoId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getAditivos(int $contratoId): array
    {
        return $this->db->table('contratos_aditivos')
            ->where('contrato_id', $contratoId)
            ->orderBy('data_aditivo', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Busca dados básicos do contrato para exibição em outros módulos.
     */
    public function getDadosContrato(int $id): ?array
    {
        return $this->model
            ->select('contratos.numero_contrato, clientes.nome as nome_cliente')
            ->join('clientes', 'clientes.id_cliente = contratos.cliente_id', 'left')
            ->where('contratos.id_contrato', $id)
            ->where('contratos.deleted_at', null)
            ->first();
    }

    /**
     * Calcula a rentabilidade (Receitas - Despesas) dos contratos.
     * @param string $tipo 'rentaveis' para maior saldo, 'prejuizos' para menor saldo
     */
    public function getContratosRentabilidade(string $tipo = 'rentaveis', int $limit = 5): array
    {
        $db = \Config\Database::connect();
        
        $orderDir = $tipo === 'prejuizos' ? 'ASC' : 'DESC';
        $condicao = $this->condicaoEscopo('contrato', 'c');

        // Usa subqueries para garantir que não haja soma duplicada caso o contrato tenha múltiplas despesas e receitas
        $query = $db->query("
            SELECT 
                c.id_contrato, 
                c.numero_contrato, 
                cli.nome as cliente_nome,
                COALESCE((SELECT SUM(r.valor) FROM fin_receitas r WHERE r.contrato_id = c.id_contrato AND r.deleted_at IS NULL), 0) as total_receitas,
                COALESCE((SELECT SUM(d.valor) FROM fin_despesas d WHERE d.contrato_id = c.id_contrato AND d.deleted_at IS NULL), 0) as total_despesas,
                (
                    COALESCE((SELECT SUM(r.valor) FROM fin_receitas r WHERE r.contrato_id = c.id_contrato AND r.deleted_at IS NULL), 0) -
                    COALESCE((SELECT SUM(d.valor) FROM fin_despesas d WHERE d.contrato_id = c.id_contrato AND d.deleted_at IS NULL), 0)
                ) as saldo
            FROM contratos c
            LEFT JOIN clientes cli ON cli.id_cliente = c.cliente_id
            WHERE c.deleted_at IS NULL" . ($condicao ? " AND {$condicao}" : '') . "
            ORDER BY saldo $orderDir
            LIMIT ?
        ", [$limit]);

        return $query->getResultArray();
    }
}
