<?php

namespace App\Repositories\Financeiro;

use App\Models\ClientesModel;
use App\Models\Financeiro\FinanceiroCategoriasModel;
use App\Models\Financeiro\FinanceiroDespesasModel;
use App\Models\Financeiro\FinanceiroPagtoDespesasModel;
use App\Models\Financeiro\FinanceiroPagtoReceitasModel;
use App\Models\Financeiro\FinanceiroReceitasModel;
use App\Models\Financeiro\FinanceiroTransferenciasModel;
use App\Models\Financeiro\FinanceiroTransferenciasRateioModel;
use App\Models\FornecedoresModel;
use App\Models\UsuariosModel;
use App\Repositories\BaseRepository;
use App\Repositories\UsuariosRepository;

class ExtratoRepository extends BaseRepository
{
    protected FinanceiroReceitasModel $receitaModel;
    protected FinanceiroDespesasModel $despesaModel;
    protected FinanceiroPagtoDespesasModel $pagtoDespesasModel;
    protected FinanceiroPagtoReceitasModel $pagtoReceitasModel;
    protected FinanceiroTransferenciasModel $transferenciaModel;
    protected FinanceiroTransferenciasRateioModel $transferenciasRateioModel;
    protected FornecedoresModel $fornecedorModel;
    protected ClientesModel $clienteModel;
    protected FinanceiroCategoriasModel $categoriaModel;
    protected UsuariosModel $usuariosModel;

    protected UsuariosRepository $usuariosRepository;

    /**
     * Cache local de usuários para evitar N+1 queries
     * @var array
     */
    protected $mapaUsuarios = [];

    /**
     * Cache local de rateios processados para evitar JSON decode repetitivo
     * @var array
     */
    protected $rateioCache = [];

    /**
     * Construtor para inicializar os modelos necessários para o repositório
     */
    public function __construct(
        ?FinanceiroReceitasModel $receitaModel = null,
        ?FinanceiroDespesasModel $despesaModel = null,
        ?FinanceiroPagtoDespesasModel $pagtoDespesasModel = null,
        ?FinanceiroPagtoReceitasModel $pagtoReceitasModel = null,
        ?FinanceiroTransferenciasModel $transferenciaModel = null,
        ?FornecedoresModel $fornecedorModel = null,
        ?ClientesModel $clienteModel = null,
        ?FinanceiroCategoriasModel $categoriaModel = null,
        ?UsuariosModel $usuariosModel = null,
        ?UsuariosRepository $usuariosRepository = null
    ) {
        parent::__construct();
        $this->cacheKey = 'financeiro_extrato';
        $this->receitaModel       = $receitaModel ?? model('Financeiro/FinanceiroReceitasModel');
        $this->despesaModel       = $despesaModel ?? model('Financeiro/FinanceiroDespesasModel');
        $this->pagtoDespesasModel = $pagtoDespesasModel ?? model('Financeiro/FinanceiroPagtoDespesasModel');
        $this->pagtoReceitasModel = $pagtoReceitasModel ?? model('Financeiro/FinanceiroPagtoReceitasModel');
        $this->transferenciaModel       = $transferenciaModel ?? model('Financeiro/FinanceiroTransferenciasModel');
        $this->transferenciasRateioModel = model('Financeiro/FinanceiroTransferenciasRateioModel');
        $this->fornecedorModel    = model('FornecedoresModel');
        $this->clienteModel       = model('ClientesModel');
        $this->categoriaModel     = model('Financeiro/FinanceiroCategoriasModel');
        $this->usuariosModel      = model('UsuariosModel');
        $this->usuariosRepository = new UsuariosRepository();
    }

    /**
     * Carrega todos os usuários para um mapa local (id => nome)
     * Otimiza o processo de rateio evitando múltiplas consultas ao banco
     */
    protected function carregarUsuarios(): void
    {
        $usuarios = $this->usuariosRepository->getUsers();
        foreach ($usuarios as $usuario) {
            $this->mapaUsuarios[$usuario['id']] = $usuario['username'];
        }
    }

    /**
     * Obtém o nome do usuário do cache local
     */
    public function getNomeUsuario(int $id): string
    {
        if (empty($this->mapaUsuarios)) {
            $this->carregarUsuarios();
        }

        return $this->mapaUsuarios[$id] ?? "Usuário #$id";
    }

    /**
     * Busca os itens de rateio de despesa a partir da tabela relacionada
     * e formata para o padrão esperado (valor negativo).
     *
     * @param int[] $pgtoIds IDs dos pagamentos de despesa
     * @return array Mapa indexado por pgto_despesa_id => array de rateio
     */
    protected function carregarRateioDespesas(array $pgtoIds): array
    {
        if (empty($pgtoIds)) {
            return [];
        }

        $db   = $this->pagtoDespesasModel->db;
        $rows = $db->table('fin_pgto_despesas_rateio')
            ->whereIn('pagto_despesa_id', $pgtoIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row['pagto_despesa_id']][] = [
                'id'    => $row['user_id'],
                'nome'  => $this->getNomeUsuario((int) $row['user_id']),
                'valor' => -floatval($row['valor']),
            ];
        }

        return $mapa;
    }

    /**
     * Busca os itens de rateio de receita a partir da tabela relacionada
     * e formata para o padrão esperado.
     *
     * @param int[] $pgtoIds IDs dos pagamentos de receita
     * @return array Mapa indexado por pgto_receita_id => array de rateio
     */
    protected function carregarRateioReceitas(array $pgtoIds): array
    {
        if (empty($pgtoIds)) {
            return [];
        }

        $db   = $this->pagtoReceitasModel->db;
        $rows = $db->table('fin_pgto_receitas_rateio')
            ->whereIn('pagto_receita_id', $pgtoIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row['pagto_receita_id']][] = [
                'id'    => $row['user_id'],
                'nome'  => $this->getNomeUsuario((int) $row['user_id']),
                'valor' => floatval($row['valor']),
            ];
        }

        return $mapa;
    }

    /**
     * Busca os itens de rateio de transferência a partir da tabela relacionada
     * e formata para o padrão esperado.
     *
     * @param int[] $transfIds IDs das transferências
     * @param bool  $negativo  Se verdadeiro, inverte o sinal do valor (para saídas)
     * @return array Mapa indexado por transferencia_id => array de rateio
     */
    protected function carregarRateioTransferencias(array $transfIds, bool $negativo = false): array
    {
        if (empty($transfIds)) {
            return [];
        }

        $db   = $this->transferenciasRateioModel->db;
        $rows = $db->table('fin_transferencias_rateios')
            ->whereIn('transferencia_id', $transfIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $valor = floatval($row['valor']);
            $mapa[$row['transferencia_id']][] = [
                'id'    => $row['user_id'],
                'nome'  => $this->getNomeUsuario((int) $row['user_id']),
                'valor' => $negativo ? -$valor : $valor,
            ];
        }

        return $mapa;
    }

    /**
     * Hidrata o campo 'rateio' de cada item usando um mapa pré-carregado.
     * Evita N+1 queries ao trabalhar com listas.
     *
     * @param array  $itens      Lista de registros (despesas ou receitas)
     * @param array  $mapaRateio Mapa [id => [rateio]] retornado por carregarRateio*
     * @param string $chaveId    Nome do campo que identifica o pagamento (ex: 'id')
     */
    protected function aplicarRateio(array &$itens, array $mapaRateio, string $chaveId = 'id'): void
    {
        foreach ($itens as &$item) {
            $item['rateio'] = $mapaRateio[$item[$chaveId]] ?? [];
        }
    }

    /**
     * Carrega o rateio de categorias de um conjunto de despesas, já convertendo
     * o valor de cada categoria para a proporção (0 a 1) que ela representa
     * dentro do valor total da despesa — usado depois para calcular quanto de
     * cada transação (pagamento) pertence a cada categoria.
     *
     * @param int[] $despesaIds
     * @return array Mapa despesa_id => [['id_categoria', 'categoria', 'categoria_pai_id', 'proporcao'], ...]
     */
    protected function carregarCategoriasDespesas(array $despesaIds): array
    {
        if (empty($despesaIds)) {
            return [];
        }

        $despesaIds = array_unique(array_filter($despesaIds));

        $valoresDespesas = $this->db->table('fin_despesas')
            ->select('id_despesa, valor')
            ->whereIn('id_despesa', $despesaIds)
            ->get()
            ->getResultArray();
        $valorPorDespesa = array_column($valoresDespesas, 'valor', 'id_despesa');

        $rows = $this->db->table('fin_despesas_categorias as dc')
            ->select('dc.despesa_id, dc.categoria_id, dc.valor, cat.categoria, cat.categoria_pai_id')
            ->join('fin_categorias as cat', 'cat.id_categoria = dc.categoria_id', 'left')
            ->whereIn('dc.despesa_id', $despesaIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $valorDespesa = (float) ($valorPorDespesa[$row['despesa_id']] ?? 0);
            $mapa[$row['despesa_id']][] = [
                'id_categoria'      => $row['categoria_id'],
                'categoria'         => $row['categoria'],
                'categoria_pai_id'  => $row['categoria_pai_id'],
                'proporcao'         => $valorDespesa > 0 ? ((float) $row['valor'] / $valorDespesa) : 0.0,
            ];
        }

        return $mapa;
    }

    /**
     * Equivalente a carregarCategoriasDespesas, para receitas.
     *
     * @param int[] $receitaIds
     * @return array Mapa receita_id => [['id_categoria', 'categoria', 'categoria_pai_id', 'proporcao'], ...]
     */
    protected function carregarCategoriasReceitas(array $receitaIds): array
    {
        if (empty($receitaIds)) {
            return [];
        }

        $receitaIds = array_unique(array_filter($receitaIds));

        $valoresReceitas = $this->db->table('fin_receitas')
            ->select('id_receita, valor')
            ->whereIn('id_receita', $receitaIds)
            ->get()
            ->getResultArray();
        $valorPorReceita = array_column($valoresReceitas, 'valor', 'id_receita');

        $rows = $this->db->table('fin_receitas_categorias as rc')
            ->select('rc.receita_id, rc.categoria_id, rc.valor, cat.categoria, cat.categoria_pai_id')
            ->join('fin_categorias as cat', 'cat.id_categoria = rc.categoria_id', 'left')
            ->whereIn('rc.receita_id', $receitaIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $valorReceita = (float) ($valorPorReceita[$row['receita_id']] ?? 0);
            $mapa[$row['receita_id']][] = [
                'id_categoria'      => $row['categoria_id'],
                'categoria'         => $row['categoria'],
                'categoria_pai_id'  => $row['categoria_pai_id'],
                'proporcao'         => $valorReceita > 0 ? ((float) $row['valor'] / $valorReceita) : 0.0,
            ];
        }

        return $mapa;
    }

    /**
     * Hidrata cada item (despesa ou receita) com o rateio de categorias correspondente:
     * - 'categorias': lista completa, com o valor de cada categoria já proporcional
     *   ao valor da transação (pagamento/recebimento), somando de volta o valor total.
     * - 'id_categoria' / 'categoria' / 'categoria_pai_id': primeira categoria do rateio,
     *   mantidos para compatibilidade com código legado que ainda lê um valor único.
     *
     * @param array  $itens        Lista de registros (despesas ou receitas), por referência.
     * @param array  $mapaCategorias Mapa [id => [rateio]] retornado por carregarCategorias*.
     * @param string $chaveId      Nome do campo que identifica a despesa/receita (ex: 'despesa_id').
     */
    protected function aplicarCategorias(array &$itens, array $mapaCategorias, string $chaveId): void
    {
        foreach ($itens as &$item) {
            $categorias = $mapaCategorias[$item[$chaveId]] ?? [];

            $item['categorias'] = array_map(static function (array $c) use ($item) {
                return [
                    'id_categoria'     => $c['id_categoria'],
                    'categoria'        => $c['categoria'],
                    'categoria_pai_id' => $c['categoria_pai_id'],
                    'valor'            => $item['valor'] * $c['proporcao'],
                ];
            }, $categorias);

            $primeira = $categorias[0] ?? null;
            $item['id_categoria']     = $primeira['id_categoria'] ?? null;
            $item['categoria_pai_id'] = $primeira['categoria_pai_id'] ?? null;
            $item['categoria']        = $primeira['categoria'] ?? null;
        }
    }

    // -------------------------------------------------------------------------
    // Métodos por conta (sem filtro de período)
    // -------------------------------------------------------------------------

    /**
     * Obtém as despesas de uma conta.
     */
    public function getDespesasPorConta(int $conta_id): array
    {
        $despesas = $this->pagtoDespesasModel
            ->distinct()
            ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, d.despesa as descricao, d.fornecedor as fornecedor_id, f.nome as cliente_fornecedor, (pd.valor * -1) as valor, d.id_despesa as despesa_id')
            ->from('fin_pgto_despesas as pd')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.deleted_at is null')
            ->join('fin_despesas as d', 'd.id_despesa = pd.despesa_id', 'left')
            ->join('fin_fornecedores as f', 'f.id_fornecedor = d.fornecedor', 'left')
            ->findAll();

        $pgtoIds    = array_column($despesas, 'id');
        $mapaRateio = $this->carregarRateioDespesas($pgtoIds);
        $this->aplicarRateio($despesas, $mapaRateio);

        $mapaCategorias = $this->carregarCategoriasDespesas(array_column($despesas, 'despesa_id'));
        $this->aplicarCategorias($despesas, $mapaCategorias, 'despesa_id');

        return $despesas;
    }

    /**
     * Obtém as receitas de uma conta.
     */
    public function getReceitasPorConta(int $conta_id): array
    {
        $receitas = $this->pagtoReceitasModel
            ->distinct()
            ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, r.cliente_id, c.nome as cliente_fornecedor, pr.valor as valor, r.id_receita as receita_id')
            ->from('fin_pgto_receitas as pr')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.deleted_at is null')
            ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
            ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
            ->findAll();

        $pgtoIds    = array_column($receitas, 'id');
        $mapaRateio = $this->carregarRateioReceitas($pgtoIds);
        $this->aplicarRateio($receitas, $mapaRateio);

        $mapaCategorias = $this->carregarCategoriasReceitas(array_column($receitas, 'receita_id'));
        $this->aplicarCategorias($receitas, $mapaCategorias, 'receita_id');

        return $receitas;
    }

    /**
     * Obtém as transferências enviadas por uma conta.
     */
    public function getTransferenciasDePorConta(int $conta_id): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, (t.valor * -1) as valor, cd.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_origem', $conta_id)
            ->where('t.deleted_at is null')
            ->join('fin_contas as cd', 'cd.id_conta = t.id_conta_destino', 'left')
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, true);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    /**
     * Obtém as transferências recebidas por uma conta.
     */
    public function getTransferenciasParaPorConta(int $conta_id): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, t.valor as valor, co.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_destino', $conta_id)
            ->where('t.deleted_at is null')
            ->join('fin_contas as co', 'co.id_conta = t.id_conta_origem', 'left')
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, false);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    // -------------------------------------------------------------------------
    // Métodos por conta e período
    // -------------------------------------------------------------------------

    /**
     * Obtém as despesas de uma conta em um período.
     */
    public function getDespesasPorContaPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $despesas = $this->pagtoDespesasModel
            ->distinct()
            ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, d.despesa as descricao, d.fornecedor as fornecedor_id, f.nome as cliente_fornecedor, (pd.valor * -1) as valor, d.id_despesa as despesa_id, "despesa" as tipo')
            ->from('fin_pgto_despesas as pd')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.deleted_at is null')
            ->where('pd.pagamento_despesa_dt >=', $dataInicial)
            ->where('pd.pagamento_despesa_dt <=', $dataFinal)
            ->join('fin_despesas as d', 'd.id_despesa = pd.despesa_id', 'left')
            ->join('fin_fornecedores as f', 'f.id_fornecedor = d.fornecedor', 'left')
            ->findAll();

        $pgtoIds    = array_column($despesas, 'id');
        $mapaRateio = $this->carregarRateioDespesas($pgtoIds);
        $this->aplicarRateio($despesas, $mapaRateio);

        $mapaCategorias = $this->carregarCategoriasDespesas(array_column($despesas, 'despesa_id'));
        $this->aplicarCategorias($despesas, $mapaCategorias, 'despesa_id');

        return $despesas;
    }

    /**
     * Obtém as receitas de uma conta em um período.
     */
    public function getReceitasPorContaPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $receitas = $this->pagtoReceitasModel
            ->distinct()
            ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, r.cliente_id, c.nome as cliente_fornecedor, pr.valor as valor, r.id_receita as receita_id, "receita" as tipo')
            ->from('fin_pgto_receitas as pr')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.deleted_at is null')
            ->where('pr.pagamento_receita_dt >=', $dataInicial)
            ->where('pr.pagamento_receita_dt <=', $dataFinal)
            ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
            ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
            ->findAll();

        $pgtoIds    = array_column($receitas, 'id');
        $mapaRateio = $this->carregarRateioReceitas($pgtoIds);
        $this->aplicarRateio($receitas, $mapaRateio);

        $mapaCategorias = $this->carregarCategoriasReceitas(array_column($receitas, 'receita_id'));
        $this->aplicarCategorias($receitas, $mapaCategorias, 'receita_id');

        return $receitas;
    }

    /**
     * Obtém as transferências enviadas por uma conta em um período.
     */
    public function getTransferenciasDePorContaPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, (t.valor * -1) as valor, "transferencia_saida" as tipo, cd.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_origem', $conta_id)
            ->where('t.deleted_at is null')
            ->where('t.data_transferencia >=', $dataInicial)
            ->where('t.data_transferencia <=', $dataFinal)
            ->join('fin_contas as cd', 'cd.id_conta = t.id_conta_destino', 'left')
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, true);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    /**
     * Obtém as transferências recebidas por uma conta em um período.
     */
    public function getTransferenciasParaPorContaPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, t.valor as valor, "transferencia_entrada" as tipo, co.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_destino', $conta_id)
            ->where('t.deleted_at is null')
            ->where('t.data_transferencia >=', $dataInicial)
            ->where('t.data_transferencia <=', $dataFinal)
            ->join('fin_contas as co', 'co.id_conta = t.id_conta_origem', 'left')
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, false);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    // -------------------------------------------------------------------------
    // Métodos de saldo anterior (antes de um período)
    // -------------------------------------------------------------------------

    /**
     * Obtém as despesas de uma conta anteriores à data inicial.
     */
    public function getDespesasAnteriores(int $conta_id, string $dataInicial): array
    {
        $despesas = $this->pagtoDespesasModel
            ->distinct()
            ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, (pd.valor * -1) as valor')
            ->from('fin_pgto_despesas as pd')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.deleted_at is null')
            ->where('pd.pagamento_despesa_dt <', $dataInicial)
            ->findAll();

        $pgtoIds    = array_column($despesas, 'id');
        $mapaRateio = $this->carregarRateioDespesas($pgtoIds);
        $this->aplicarRateio($despesas, $mapaRateio);

        return $despesas;
    }

    /**
     * Obtém as receitas de uma conta anteriores à data inicial.
     */
    public function getReceitasAnteriores(int $conta_id, string $dataInicial): array
    {
        $receitas = $this->pagtoReceitasModel
            ->distinct()
            ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, pr.valor as valor')
            ->from('fin_pgto_receitas as pr')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.deleted_at is null')
            ->where('pr.pagamento_receita_dt <', $dataInicial)
            ->findAll();

        $pgtoIds    = array_column($receitas, 'id');
        $mapaRateio = $this->carregarRateioReceitas($pgtoIds);
        $this->aplicarRateio($receitas, $mapaRateio);

        return $receitas;
    }

    /**
     * Obtém as transferências enviadas por uma conta anteriores à data inicial.
     */
    public function getTransferenciasDeAnteriores(int $conta_id, string $dataInicial): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('id_transferencia as id, data_transferencia as data, (valor * -1) as valor')
            ->where('id_conta_origem', $conta_id)
            ->where('deleted_at is null')
            ->where('data_transferencia <', $dataInicial)
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, true);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    /**
     * Obtém as transferências recebidas por uma conta anteriores à data inicial.
     */
    public function getTransferenciasParaAnteriores(int $conta_id, string $dataInicial): array
    {
        $transf = $this->transferenciaModel
            ->distinct()
            ->select('id_transferencia as id, data_transferencia as data, valor')
            ->where('id_conta_destino', $conta_id)
            ->where('deleted_at is null')
            ->where('data_transferencia <', $dataInicial)
            ->findAll();

        $ids = array_column($transf, 'id');
        $mapaRateio = $this->carregarRateioTransferencias($ids, false);
        $this->aplicarRateio($transf, $mapaRateio);

        return $transf;
    }

    /**
     * Calcula os saldos anteriores (total e por rateio) de forma agrupada via SQL.
     * Otimizado para evitar o carregamento de milhares de linhas para o PHP.
     *
     * @param int $conta_id
     * @param string $dataLimite Data limite (exclusiva) para o saldo anterior.
     * @return array [total => float, rateio => [user_id => ['valor' => float, 'nome' => string]]]
     */
    public function getSaldosAnterioresAgrupados(int $conta_id, string $dataLimite): array
    {
        $db = $this->db;
        $saldosRateio = [];

        // Pré-carregar usuários para evitar N+1 queries ao buscar nomes
        $this->carregarUsuarios();

        // 0. Total geral: SEMPRE calculado diretamente a partir do valor real das
        // transações (receitas - despesas - transferências de saída + transferências
        // de entrada), nunca a partir da soma dos rateios individuais.
        //
        // O rateio é uma atribuição informativa por advogado e não tem garantia de
        // somar 100% do valor da transação (ex.: parte do valor fica sem rateio
        // explícito, atribuída implicitamente ao escritório). Se o total fosse
        // reconstruído a partir da soma dos rateios (como era feito antes), qualquer
        // transação com rateio parcial faria o saldo anterior "sumir" uma diferença,
        // fazendo o mesmo período gerar saldos diferentes dependendo de onde a data
        // de corte cai (ex.: extrato de 01/07 a 31/08 vs. 01/08 a 31/08).
        $totalReceitas = (float) ($db->table('fin_pgto_receitas')
            ->selectSum('valor')
            ->where('conta_id', $conta_id)
            ->where('pagamento_receita_dt <', $dataLimite)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0);

        $totalDespesas = (float) ($db->table('fin_pgto_despesas')
            ->selectSum('valor')
            ->where('conta_id', $conta_id)
            ->where('pagamento_despesa_dt <', $dataLimite)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0);

        $totalTransfSaida = (float) ($db->table('fin_transferencias')
            ->selectSum('valor')
            ->where('id_conta_origem', $conta_id)
            ->where('data_transferencia <', $dataLimite)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0);

        $totalTransfEntrada = (float) ($db->table('fin_transferencias')
            ->selectSum('valor')
            ->where('id_conta_destino', $conta_id)
            ->where('data_transferencia <', $dataLimite)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0);

        $totalGeral = $totalReceitas - $totalDespesas - $totalTransfSaida + $totalTransfEntrada;

        // 1. Somar Rateios de Receitas (apenas para o detalhamento por advogado)
        $receitasRateio = $db->table('fin_pgto_receitas_rateio as rr')
            ->select('rr.user_id, SUM(rr.valor) as total')
            ->join('fin_pgto_receitas as pr', 'pr.id_pgto_receita = rr.pagto_receita_id')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.pagamento_receita_dt <', $dataLimite)
            ->where('pr.deleted_at IS NULL')
            ->groupBy('rr.user_id')
            ->get()
            ->getResultArray();

        foreach ($receitasRateio as $r) {
            $userId = (int) $r['user_id'];
            $valor = (float) $r['total'];
            $saldosRateio[$userId] = [
                'valor' => $valor,
                'nome'  => $this->getNomeUsuario($userId)
            ];
        }

        // 2. Somar Rateios de Despesas (valor negativo)
        $despesasRateio = $db->table('fin_pgto_despesas_rateio as dr')
            ->select('dr.user_id, SUM(dr.valor) as total')
            ->join('fin_pgto_despesas as pd', 'pd.id_pgto_despesa = dr.pagto_despesa_id')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.pagamento_despesa_dt <', $dataLimite)
            ->where('pd.deleted_at IS NULL')
            ->groupBy('dr.user_id')
            ->get()
            ->getResultArray();

        foreach ($despesasRateio as $d) {
            $userId = (int) $d['user_id'];
            $valor = (float) $d['total'];
            if (!isset($saldosRateio[$userId])) {
                $saldosRateio[$userId] = ['valor' => 0.0, 'nome' => $this->getNomeUsuario($userId)];
            }
            $saldosRateio[$userId]['valor'] -= $valor;
        }

        // 3. Somar Rateios de Transferências
        // Saídas (origem)
        $transfRateioSaida = $db->table('fin_transferencias_rateios as tr')
            ->select('tr.user_id, SUM(tr.valor) as total')
            ->join('fin_transferencias as t', 't.id_transferencia = tr.transferencia_id')
            ->where('t.id_conta_origem', $conta_id)
            ->where('t.data_transferencia <', $dataLimite)
            ->where('t.deleted_at IS NULL')
            ->groupBy('tr.user_id')
            ->get()
            ->getResultArray();

        foreach ($transfRateioSaida as $ts) {
            $userId = (int) $ts['user_id'];
            $valor = (float) $ts['total'];
            if (!isset($saldosRateio[$userId])) {
                $saldosRateio[$userId] = ['valor' => 0.0, 'nome' => $this->getNomeUsuario($userId)];
            }
            $saldosRateio[$userId]['valor'] -= $valor;
        }

        // Entradas (destino)
        $transfRateioEntrada = $db->table('fin_transferencias_rateios as tr')
            ->select('tr.user_id, SUM(tr.valor) as total')
            ->join('fin_transferencias as t', 't.id_transferencia = tr.transferencia_id')
            ->where('t.id_conta_destino', $conta_id)
            ->where('t.data_transferencia <', $dataLimite)
            ->where('t.deleted_at IS NULL')
            ->groupBy('tr.user_id')
            ->get()
            ->getResultArray();

        foreach ($transfRateioEntrada as $te) {
            $userId = (int) $te['user_id'];
            $valor = (float) $te['total'];
            if (!isset($saldosRateio[$userId])) {
                $saldosRateio[$userId] = ['valor' => 0.0, 'nome' => $this->getNomeUsuario($userId)];
            }
            $saldosRateio[$userId]['valor'] += $valor;
        }

        // 4. Lidar com valores que não possuem rateio (alocar em 'geral')
        // Receitas sem rateio
        $receitasSemRateio = $db->table('fin_pgto_receitas as pr')
            ->select('SUM(pr.valor) as total')
            ->join('fin_pgto_receitas_rateio as rr', 'rr.pagto_receita_id = pr.id_pgto_receita', 'left')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.pagamento_receita_dt <', $dataLimite)
            ->where('pr.deleted_at IS NULL')
            ->where('rr.id_rateio IS NULL')
            ->get()
            ->getRow();

        $valorSemRateioRec = (float) ($receitasSemRateio->total ?? 0.0);
        if ($valorSemRateioRec != 0) {
            if (!isset($saldosRateio['geral'])) $saldosRateio['geral'] = ['valor' => 0.0, 'nome' => 'Geral'];
            $saldosRateio['geral']['valor'] += $valorSemRateioRec;
        }

        // Despesas sem rateio
        $despesasSemRateio = $db->table('fin_pgto_despesas as pd')
            ->select('SUM(pd.valor) as total')
            ->join('fin_pgto_despesas_rateio as dr', 'dr.pagto_despesa_id = pd.id_pgto_despesa', 'left')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.pagamento_despesa_dt <', $dataLimite)
            ->where('pd.deleted_at IS NULL')
            ->where('dr.id_rateio IS NULL')
            ->get()
            ->getRow();

        $valorSemRateioDesp = (float) ($despesasSemRateio->total ?? 0.0);
        if ($valorSemRateioDesp != 0) {
            if (!isset($saldosRateio['geral'])) $saldosRateio['geral'] = ['valor' => 0.0, 'nome' => 'Geral'];
            $saldosRateio['geral']['valor'] -= $valorSemRateioDesp;
        }

        // Transferências sem rateio
        // Saídas
        $transfSemRateioSaida = $db->table('fin_transferencias as t')
            ->select('SUM(t.valor) as total')
            ->join('fin_transferencias_rateios as tr', 'tr.transferencia_id = t.id_transferencia', 'left')
            ->where('t.id_conta_origem', $conta_id)
            ->where('t.data_transferencia <', $dataLimite)
            ->where('t.deleted_at IS NULL')
            ->where('tr.id_rateio IS NULL')
            ->get()
            ->getRow();

        $valorSemRateioTransfS = (float) ($transfSemRateioSaida->total ?? 0.0);
        if ($valorSemRateioTransfS != 0) {
            if (!isset($saldosRateio['geral'])) $saldosRateio['geral'] = ['valor' => 0.0, 'nome' => 'Geral'];
            $saldosRateio['geral']['valor'] -= $valorSemRateioTransfS;
        }

        // Entradas
        $transfSemRateioEntrada = $db->table('fin_transferencias as t')
            ->select('SUM(t.valor) as total')
            ->join('fin_transferencias_rateios as tr', 'tr.transferencia_id = t.id_transferencia', 'left')
            ->where('t.id_conta_destino', $conta_id)
            ->where('t.data_transferencia <', $dataLimite)
            ->where('t.deleted_at IS NULL')
            ->where('tr.id_rateio IS NULL')
            ->get()
            ->getRow();

        $valorSemRateioTransfE = (float) ($transfSemRateioEntrada->total ?? 0.0);
        if ($valorSemRateioTransfE != 0) {
            if (!isset($saldosRateio['geral'])) $saldosRateio['geral'] = ['valor' => 0.0, 'nome' => 'Geral'];
            $saldosRateio['geral']['valor'] += $valorSemRateioTransfE;
        }

        return [
            'total'   => $totalGeral,
            'rateio'  => $saldosRateio
        ];
    }

    /**
     * Obtém todas as transações de uma conta em um período específico.
     */
    public function getTransacoesNoPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $despesas = $this->getDespesasPorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $receitas = $this->getReceitasPorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $transferenciasDe = $this->getTransferenciasDePorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $transferenciasPara = $this->getTransferenciasParaPorContaPeriodo($conta_id, $dataInicial, $dataFinal);

        return array_merge($despesas, $receitas, $transferenciasDe, $transferenciasPara);
    }

    /**
     * Obtém todas as transações de uma conta APÓS uma data específica
     * (otimizado para usar a partir do último fechamento)
     */
    public function getTransacoesApartirDe(int $conta_id, string $dataInicial): array
    {
        // Despesas após data inicial
        $despesas = $this->pagtoDespesasModel
            ->select('pd.id_pgto_despesa as id, pd.pagamento_despesa_dt as data, d.despesa as descricao, d.fornecedor as fornecedor_id, f.nome as cliente_fornecedor, (pd.valor * -1) as valor, d.id_despesa as despesa_id, "despesa" as tipo')
            ->from('fin_pgto_despesas as pd')
            ->where('pd.conta_id', $conta_id)
            ->where('pd.deleted_at is null')
            ->where('pd.pagamento_despesa_dt >=', $dataInicial)
            ->join('fin_despesas as d', 'd.id_despesa = pd.despesa_id', 'left')
            ->join('fin_fornecedores as f', 'f.id_fornecedor = d.fornecedor', 'left')
            ->findAll();

        // Receitas após data inicial
        $receitas = $this->pagtoReceitasModel
            ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, r.cliente_id, c.nome as cliente_fornecedor, pr.valor as valor, r.id_receita as receita_id, "receita" as tipo')
            ->from('fin_pgto_receitas as pr')
            ->where('pr.conta_id', $conta_id)
            ->where('pr.deleted_at is null')
            ->where('pr.pagamento_receita_dt >=', $dataInicial)
            ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
            ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
            ->findAll();

        // Transferências enviadas após data inicial
        $transferenciasDe = $this->transferenciaModel
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, (t.valor * -1) as valor, "transferencia_saida" as tipo, cd.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_origem', $conta_id)
            ->where('t.deleted_at is null')
            ->where('t.data_transferencia >=', $dataInicial)
            ->join('fin_contas as cd', 'cd.id_conta = t.id_conta_destino', 'left')
            ->findAll();

        // Transferências recebidas após data inicial
        $transferenciasPara = $this->transferenciaModel
            ->select('t.id_transferencia as id, t.data_transferencia as data, t.transferencia as descricao, t.valor as valor, "transferencia_entrada" as tipo, co.conta as cliente_fornecedor')
            ->from('fin_transferencias as t')
            ->where('t.id_conta_destino', $conta_id)
            ->where('t.deleted_at is null')
            ->where('t.data_transferencia >=', $dataInicial)
            ->join('fin_contas as co', 'co.id_conta = t.id_conta_origem', 'left')
            ->findAll();

        // Carregar rateios apenas para as transações retornadas
        $pgtoIdsDespesas = array_column($despesas, 'id');
        $pgtoIdsReceitas = array_column($receitas, 'id');
        $transfIdsDe     = array_column($transferenciasDe, 'id');
        $transfIdsPara   = array_column($transferenciasPara, 'id');

        $mapaRateioDespesas = $this->carregarRateioDespesas($pgtoIdsDespesas);
        $mapaRateioReceitas = $this->carregarRateioReceitas($pgtoIdsReceitas);
        $mapaRateioTransfDe = $this->carregarRateioTransferencias($transfIdsDe, true);
        $mapaRateioTransfPara = $this->carregarRateioTransferencias($transfIdsPara, false);

        $this->aplicarRateio($despesas, $mapaRateioDespesas);
        $this->aplicarRateio($receitas, $mapaRateioReceitas);
        $this->aplicarRateio($transferenciasDe, $mapaRateioTransfDe);
        $this->aplicarRateio($transferenciasPara, $mapaRateioTransfPara);

        // Carregar rateio de categorias apenas para as transações retornadas
        $mapaCategoriasDespesas = $this->carregarCategoriasDespesas(array_column($despesas, 'despesa_id'));
        $mapaCategoriasReceitas = $this->carregarCategoriasReceitas(array_column($receitas, 'receita_id'));
        $this->aplicarCategorias($despesas, $mapaCategoriasDespesas, 'despesa_id');
        $this->aplicarCategorias($receitas, $mapaCategoriasReceitas, 'receita_id');

        return array_merge($despesas, $receitas, $transferenciasDe, $transferenciasPara);
    }
}
