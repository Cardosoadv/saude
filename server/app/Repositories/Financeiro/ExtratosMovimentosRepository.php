<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroExtratosMovimentosModel;
use App\Repositories\BaseRepository;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * Repositório dos movimentos importados de extratos bancários.
 */
class ExtratosMovimentosRepository extends BaseRepository
{
    protected $cacheKey = 'fin_extratos_movimentos';

    public function __construct(?FinanceiroExtratosMovimentosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new FinanceiroExtratosMovimentosModel();
    }

    /**
     * Busca movimentos já existentes de uma conta pelas chaves informadas,
     * inclusive os excluídos (soft delete), pois eles continuam ocupando a chave.
     *
     * @param string[] $chaves
     * @return array<string,array> chave => movimento
     */
    public function buscarPorChaves(int $contaId, array $chaves): array
    {
        $chaves = array_values(array_unique(array_filter($chaves)));
        if (!$chaves) {
            return [];
        }

        $encontrados = [];
        foreach (array_chunk($chaves, 500) as $lote) {
            $linhas = $this->model
                ->withDeleted()
                ->where('conta_id', $contaId)
                ->whereIn('chave', $lote)
                ->findAll();

            foreach ($linhas as $linha) {
                $encontrados[$linha['chave']] = $linha;
            }
        }

        return $encontrados;
    }

    /**
     * Insere um movimento novo. Se a chave (conta_id, chave) já existir, o
     * índice único do banco rejeita a linha e o método devolve null, sem
     * lançar exceção: o chamador conta o item como duplicado.
     *
     * @param bool $auditar false não grava a auditoria automática do model (que copiaria memo e nome
     *                      da contraparte); quem chama registra um resumo da importação.
     * @return int|null ID do movimento criado, ou null se já existia.
     */
    public function inserirSeNovo(array $dados, bool $auditar = true): ?int
    {
        $contaId = (int) ($dados['conta_id'] ?? 0);
        $chave   = (string) ($dados['chave'] ?? '');

        if ($contaId > 0 && $chave !== '' && $this->buscarPorChaves($contaId, [$chave])) {
            return null;
        }

        try {
            // allowCallbacks vale só para a próxima operação do model: precisa vir logo antes do insert
            // (e não antes da consulta acima, que a consumiria).
            $this->model->allowCallbacks($auditar);
            $id = $this->model->insert($dados, true);
        } catch (DatabaseException $e) {
            // Corrida entre duas requisições: o índice único barrou a segunda.
            if ($this->buscarPorChaves($contaId, [$chave])) {
                return null;
            }
            throw $e;
        }

        if ($id === false) {
            if ($this->buscarPorChaves($contaId, [$chave])) {
                return null;
            }
            throw new \RuntimeException('Não foi possível gravar o movimento do extrato.');
        }

        return (int) $id;
    }

    /**
     * Movimento pelo id (não inclui os excluídos).
     */
    public function obter(int $id): ?array
    {
        $this->model->resetQuery();

        return $this->model->find($id) ?: null;
    }

    /**
     * Atualiza o vínculo/status de um movimento sem a auditoria automática do model,
     * que copiaria memo e nome da contraparte para o log. Quem chama registra um resumo só com IDs.
     *
     * Devolve false se o banco rejeitou a gravação (índice único do vínculo).
     */
    public function atualizarVinculo(int $id, array $dados): bool
    {
        $this->model->allowCallbacks(false);
        try {
            return (bool) $this->model->update($id, $dados);
        } catch (DatabaseException) {
            return false;
        } finally {
            $this->model->allowCallbacks(true);
        }
    }

    /**
     * Movimento pelo id, travando a linha até o fim da transação (SELECT ... FOR UPDATE) para que duas
     * requisições sobre o mesmo movimento não lancem em paralelo. Deve ser chamado dentro de uma transação.
     * Em bancos sem esse recurso (SQLite, dos testes) é uma leitura comum: a gravação condicional
     * de `marcarLancado` continua impedindo o lançamento duplo.
     */
    public function obterTravando(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (!str_contains(strtolower((string) $this->db->DBDriver), 'mysql')) {
            return $this->obter($id);
        }

        $linha = $this->db->query(
            'SELECT * FROM ' . $this->db->prefixTable('fin_extratos_movimentos') . ' WHERE id_movimento = ? AND deleted_at IS NULL FOR UPDATE',
            [$id]
        )->getRowArray();

        return $linha ?: null;
    }

    /**
     * Grava o vínculo de um lançamento somente se o movimento ainda estiver `pendente`.
     * Devolve false se outra requisição chegou antes (nenhuma linha alterada) ou se o índice único barrou.
     */
    public function marcarLancado(int $id, array $dados): bool
    {
        try {
            $ok = $this->db->table('fin_extratos_movimentos')
                ->where('id_movimento', $id)
                ->where('status', 'pendente')
                ->where('deleted_at IS NULL', null, false)
                ->update($dados + ['updated_at' => date('Y-m-d H:i:s')]);
        } catch (DatabaseException) {
            return false;
        }

        return $ok !== false && $this->db->affectedRows() === 1;
    }

    /**
     * Devolve para `pendente` os movimentos ligados a pagamentos que foram excluídos (lançados a partir do
     * extrato ou conciliados). O movimento nunca é apagado e o vínculo é limpo para liberar o índice único.
     * Inclui linhas com soft delete, que continuam ocupando o vínculo.
     *
     * @param int[] $pagamentoIds
     * @return int[] ids dos movimentos liberados
     */
    public function liberarPorPagamentos(string $tipo, array $pagamentoIds): array
    {
        $pagamentoIds = array_values(array_unique(array_filter(array_map('intval', $pagamentoIds))));
        if (!$pagamentoIds) {
            return [];
        }

        // Instalação sem a tabela de extratos (migration ainda não aplicada): não há movimento para liberar.
        if (!$this->db->tableExists('fin_extratos_movimentos')) {
            return [];
        }

        $builder = fn () => $this->db->table('fin_extratos_movimentos')
            ->where('pagamento_tipo', $tipo)
            ->whereIn('pagamento_id', $pagamentoIds)
            ->whereIn('status', ['lancado', 'conciliado']);

        $ids = array_map('intval', array_column($builder()->select('id_movimento')->get()->getResultArray(), 'id_movimento'));
        if (!$ids) {
            return [];
        }

        $builder()->update([
            'status'          => 'pendente',
            'pagamento_tipo'  => null,
            'pagamento_id'    => null,
            'motivo_ignorado' => null,
            'conciliado_por'  => null,
            'conciliado_em'   => null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return $ids;
    }

    /**
     * Movimento de qualquer conta que já aponta para o pagamento (ou lado da transferência), se houver.
     */
    public function buscarPorPagamento(int $contaId, string $tipo, int $pagamentoId): ?array
    {
        $this->model->resetQuery();

        return $this->model
            ->withDeleted()
            ->where('conta_id', $contaId)
            ->where('pagamento_tipo', $tipo)
            ->where('pagamento_id', $pagamentoId)
            ->first() ?: null;
    }

    /**
     * Lista os movimentos de uma conta num período, opcionalmente por status.
     */
    public function listarPorConta(int $contaId, ?string $dataInicio = null, ?string $dataFim = null, ?string $status = null): array
    {
        $builder = $this->model->where('conta_id', $contaId);

        if ($dataInicio) {
            $builder->where('data >=', $dataInicio);
        }
        if ($dataFim) {
            $builder->where('data <=', $dataFim);
        }
        if ($status) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('data', 'ASC')->orderBy('id_movimento', 'ASC')->findAll();
    }

    /**
     * Movimentos pendentes da conta até a data (inclusive), do mais antigo ao mais novo.
     */
    public function listarPendentesAte(int $contaId, string $dataFim): array
    {
        return $this->model
            ->where('conta_id', $contaId)
            ->where('status', 'pendente')
            ->where('data <=', $dataFim)
            ->orderBy('data', 'ASC')
            ->orderBy('id_movimento', 'ASC')
            ->findAll();
    }

    /**
     * Quantidade de movimentos por status no intervalo, por conta (ou de uma conta só).
     *
     * @return array<int,array{pendente:int,conciliado:int,lancado:int,ignorado:int}> indexado por conta_id
     */
    public function contarPorStatus(string $dataInicio, string $dataFim, ?int $contaId = null): array
    {
        $this->model->resetQuery();
        $builder = $this->model
            ->select('conta_id, status, COUNT(*) AS total')
            ->where('data >=', $dataInicio)
            ->where('data <=', $dataFim);
        if ($contaId) {
            $builder->where('conta_id', $contaId);
        }
        $linhas = $builder->groupBy('conta_id, status')->findAll();

        $resultado = [];
        foreach ($linhas as $linha) {
            $id = (int) $linha['conta_id'];
            $resultado[$id] ??= ['pendente' => 0, 'conciliado' => 0, 'lancado' => 0, 'ignorado' => 0];
            if (isset($resultado[$id][$linha['status']])) {
                $resultado[$id][$linha['status']] = (int) $linha['total'];
            }
        }

        return $resultado;
    }

    /**
     * Movimentos de uma conta num intervalo de datas (para comparar com um arquivo novo).
     * Não inclui os excluídos.
     */
    public function listarPorPeriodo(int $contaId, string $dataInicio, string $dataFim): array
    {
        return $this->model
            ->where('conta_id', $contaId)
            ->where('data >=', $dataInicio)
            ->where('data <=', $dataFim)
            ->findAll();
    }

    /**
     * Movimentos criados por uma importação.
     */
    public function listarPorImportacao(int $importacaoId, int $limite = 1000): array
    {
        return $this->model
            ->where('importacao_id', $importacaoId)
            ->orderBy('data', 'ASC')
            ->orderBy('id_movimento', 'ASC')
            ->findAll($limite);
    }

    /**
     * Pesquisa paginada. Filtros: conta_id, data_inicio, data_fim, status, texto (memo ou contraparte).
     *
     * @return array{itens:array,total:int}
     */
    public function pesquisar(array $filtros, int $pagina = 1, int $porPagina = 50): array
    {
        $pagina    = max(1, $pagina);
        $porPagina = min(200, max(1, $porPagina));

        $total = $this->aplicarFiltros($filtros)->countAllResults(false);
        $itens = $this->aplicarFiltros($filtros)
            ->orderBy('data', 'DESC')
            ->orderBy('id_movimento', 'DESC')
            ->findAll($porPagina, ($pagina - 1) * $porPagina);

        return ['itens' => $itens, 'total' => (int) $total];
    }

    private function aplicarFiltros(array $filtros): FinanceiroExtratosMovimentosModel
    {
        $this->model->resetQuery();

        if (!empty($filtros['conta_id'])) {
            $this->model->where('conta_id', (int) $filtros['conta_id']);
        }
        if (!empty($filtros['data_inicio'])) {
            $this->model->where('data >=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $this->model->where('data <=', $filtros['data_fim']);
        }
        if (!empty($filtros['status'])) {
            $this->model->where('status', $filtros['status']);
        }
        if (isset($filtros['texto']) && trim((string) $filtros['texto']) !== '') {
            $this->model->groupStart()
                ->like('memo', trim((string) $filtros['texto']))
                ->orLike('nome_contraparte', trim((string) $filtros['texto']))
                ->groupEnd();
        }

        return $this->model;
    }
}
