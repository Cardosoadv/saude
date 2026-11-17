<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroPagtoDespesasModel;
use App\Repositories\BaseRepository;

/**
 * Repositório de dados históricos para a previsão de fluxo de caixa e a
 * detecção de anomalias em lançamentos financeiros.
 *
 * Reúne os totais mensais pagos/recebidos (fin_pgto_despesas/fin_pgto_receitas)
 * usados para projetar o saldo futuro, e os lançamentos individuais
 * (fin_despesas/fin_receitas) com sua categoria principal, usados para
 * detectar valores fora do padrão histórico da categoria.
 */
class CashFlowForecastRepository extends BaseRepository
{
    /**
     * @var string Chave de cache para o repositório.
     */
    protected $cacheKey = 'financeiro_cash_flow_forecast';

    public function __construct(?FinanceiroPagtoDespesasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroPagtoDespesasModel');
    }

    /**
     * Retorna o total pago em despesas por mês (formato 'YYYY-MM' => total), para os
     * últimos $meses meses, opcionalmente filtrado por conta.
     *
     * @param int|null $contaId
     * @param int $meses
     * @return array<string, float>
     */
    public function getTotaisMensaisDespesas(?int $contaId, int $meses): array
    {
        $suffix = 'desp_mensal_' . ($contaId ?? '0') . "_{$meses}";

        return $this->remember($suffix, function () use ($contaId, $meses) {
            return $this->getTotaisMensais('fin_pgto_despesas', 'pagamento_despesa_dt', $contaId, $meses);
        });
    }

    /**
     * Retorna o total recebido em receitas por mês (formato 'YYYY-MM' => total), para
     * os últimos $meses meses, opcionalmente filtrado por conta.
     *
     * @param int|null $contaId
     * @param int $meses
     * @return array<string, float>
     */
    public function getTotaisMensaisReceitas(?int $contaId, int $meses): array
    {
        $suffix = 'rec_mensal_' . ($contaId ?? '0') . "_{$meses}";

        return $this->remember($suffix, function () use ($contaId, $meses) {
            return $this->getTotaisMensais('fin_pgto_receitas', 'pagamento_receita_dt', $contaId, $meses);
        });
    }

    /**
     * Agrega o total de uma tabela de pagamentos por mês.
     *
     * @param string $tabela 'fin_pgto_despesas' ou 'fin_pgto_receitas'
     * @param string $colunaData Coluna de data do pagamento
     * @param int|null $contaId
     * @param int $meses
     * @return array<string, float>
     */
    protected function getTotaisMensais(string $tabela, string $colunaData, ?int $contaId, int $meses): array
    {
        $formato = $this->getMonthFormat($colunaData);
        $dataInicio = date('Y-m-01', strtotime('-' . max(0, $meses - 1) . ' months'));

        $builder = $this->db->table($tabela)
            ->select("{$formato['field']} as periodo, SUM(valor) as total", false)
            ->where("{$colunaData} >=", $dataInicio)
            ->where('deleted_at IS NULL', null, false)
            ->groupBy('periodo')
            ->orderBy('periodo', 'ASC');

        if ($contaId) {
            $builder->where('conta_id', $contaId);
        }

        $resultado = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $resultado[$row['periodo']] = (float) $row['total'];
        }

        return $resultado;
    }

    /**
     * Retorna os lançamentos (despesas ou receitas) dos últimos $meses meses, já
     * resolvidos para sua categoria principal (a de maior valor no rateio, quando o
     * lançamento estiver classificado em mais de uma categoria).
     *
     * @param string $tipo 'despesa' ou 'receita'
     * @param int $meses
     * @return array Lista de ['id', 'descricao', 'valor', 'data', 'categoria_id', 'categoria_nome']
     */
    public function getLancamentosComCategoria(string $tipo, int $meses): array
    {
        $suffix = "lancamentos_{$tipo}_{$meses}";

        return $this->remember($suffix, function () use ($tipo, $meses) {
            $config = $this->configLancamentos($tipo);
            $dataInicio = date('Y-m-01', strtotime('-' . max(0, $meses - 1) . ' months'));

            $rows = $this->db->table("{$config['tabela']} as l")
                ->select(
                    "l.{$config['pk']} as id, l.{$config['descricao']} as descricao, l.valor as valor, "
                    . "l.{$config['data']} as data, lc.categoria_id as categoria_id, "
                    . "cat.categoria as categoria_nome, lc.valor as categoria_valor",
                    false
                )
                ->join("{$config['tabelaCategorias']} as lc", "lc.{$config['fkCategoria']} = l.{$config['pk']}", 'inner')
                ->join('fin_categorias as cat', 'cat.id_categoria = lc.categoria_id', 'left')
                ->where("l.{$config['data']} >=", $dataInicio)
                ->where('l.deleted_at IS NULL', null, false)
                ->orderBy("l.{$config['pk']}", 'ASC')
                ->orderBy('lc.valor', 'DESC')
                ->get()
                ->getResultArray();

            // Um lançamento pode ter rateio em mais de uma categoria: mantém apenas a
            // primeira ocorrência (maior valor de rateio) como categoria principal.
            $porLancamento = [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if (isset($porLancamento[$id])) {
                    continue;
                }

                $porLancamento[$id] = [
                    'id'             => $id,
                    'descricao'      => $row['descricao'],
                    'valor'          => (float) $row['valor'],
                    'data'           => $row['data'],
                    'categoria_id'   => $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
                    'categoria_nome' => $row['categoria_nome'] ?? 'Sem categoria',
                ];
            }

            return array_values($porLancamento);
        });
    }

    /**
     * Resolve nomes de tabelas/colunas para despesas ou receitas.
     *
     * @param string $tipo 'despesa' ou 'receita'
     * @return array{tabela: string, tabelaCategorias: string, pk: string, fkCategoria: string, descricao: string, data: string}
     */
    private function configLancamentos(string $tipo): array
    {
        if ($tipo === 'receita') {
            return [
                'tabela'           => 'fin_receitas',
                'tabelaCategorias' => 'fin_receitas_categorias',
                'pk'               => 'id_receita',
                'fkCategoria'      => 'receita_id',
                'descricao'        => 'receita',
                'data'             => 'vencimento_dt',
            ];
        }

        return [
            'tabela'           => 'fin_despesas',
            'tabelaCategorias' => 'fin_despesas_categorias',
            'pk'               => 'id_despesa',
            'fkCategoria'      => 'despesa_id',
            'descricao'        => 'despesa',
            'data'             => 'vencimento_dt',
        ];
    }
}
