<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use Config\Database;

/**
 * Consultas de leitura usadas pela conciliação de extratos: pagamentos já
 * lançados, despesas/receitas em aberto e transferências, sempre em centavos.
 *
 * Os pagamentos "livres" são os que nenhum movimento do extrato (nem os
 * excluídos, que continuam ocupando o vínculo) referencia.
 */
class ConciliacaoCandidatosRepository
{
    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Pagamentos de despesa (saídas) da conta, com valor exato, na janela de datas e sem vínculo.
     *
     * @return list<array<string,mixed>> id, data, valor_centavos, descricao, contraparte, comentario
     */
    public function pagamentosDespesaLivres(int $contaId, int $centavos, string $de, string $ate): array
    {
        return $this->pagamentosLivres('despesa', $contaId, $centavos, $de, $ate);
    }

    /** @return list<array<string,mixed>> */
    public function pagamentosReceitaLivres(int $contaId, int $centavos, string $de, string $ate): array
    {
        return $this->pagamentosLivres('receita', $contaId, $centavos, $de, $ate);
    }

    /**
     * Despesas sem nenhum pagamento, com valor exato e vencimento na janela.
     *
     * @return list<array<string,mixed>> id, data (vencimento), valor_centavos, descricao, contraparte, comentario
     */
    public function despesasEmAberto(int $centavos, string $de, string $ate): array
    {
        $b = $this->db->table('fin_despesas d')
            ->select('d.id_despesa AS id, d.vencimento_dt AS data, d.valor, d.despesa AS descricao, f.nome AS contraparte, d.comentario')
            ->join('fin_fornecedores f', 'f.id_fornecedor = d.fornecedor', 'left')
            ->where('d.deleted_at IS NULL', null, false)
            ->where('d.vencimento_dt >=', $de)
            ->where('d.vencimento_dt <=', $ate)
            ->where('d.valor >=', ($centavos - 0.5) / 100)
            ->where('d.valor <', ($centavos + 0.5) / 100)
            ->where('d.id_despesa NOT IN (SELECT despesa_id FROM ' . $this->db->prefixTable('fin_pgto_despesas') . ' WHERE deleted_at IS NULL AND despesa_id IS NOT NULL)', null, false)
            ->orderBy('d.vencimento_dt', 'ASC');

        return $this->normalizar($b->get()->getResultArray());
    }

    /** @return list<array<string,mixed>> */
    public function receitasEmAberto(int $centavos, string $de, string $ate): array
    {
        $b = $this->db->table('fin_receitas r')
            ->select('r.id_receita AS id, r.vencimento_dt AS data, r.valor, r.receita AS descricao, c.nome AS contraparte, r.comentario')
            ->join('clientes c', 'c.id_cliente = r.cliente_id', 'left')
            ->where('r.deleted_at IS NULL', null, false)
            ->where('r.vencimento_dt >=', $de)
            ->where('r.vencimento_dt <=', $ate)
            ->where('r.valor >=', ($centavos - 0.5) / 100)
            ->where('r.valor <', ($centavos + 0.5) / 100)
            ->where('r.id_receita NOT IN (SELECT receita_id FROM ' . $this->db->prefixTable('fin_pgto_receitas') . ' WHERE deleted_at IS NULL AND receita_id IS NOT NULL)', null, false)
            ->orderBy('r.vencimento_dt', 'ASC');

        return $this->normalizar($b->get()->getResultArray());
    }

    /**
     * Transferências existentes entre duas contas, com valor exato e data na janela,
     * que ainda não têm vínculo do lado da conta informada.
     *
     * @param int $contaOrigem Conta de onde saiu o dinheiro.
     * @param int $contaDestino Conta que recebeu.
     * @return list<array<string,mixed>> id, data, valor_centavos, descricao, comentario, conta_origem, conta_destino
     */
    public function transferenciasLivres(int $contaOrigem, int $contaDestino, int $centavos, string $de, string $ate, int $ladoContaId): array
    {
        $b = $this->db->table('fin_transferencias t')
            ->select('t.id_transferencia AS id, t.data_transferencia AS data, t.valor, t.transferencia AS descricao, t.comentarios AS comentario, t.id_conta_origem AS conta_origem, t.id_conta_destino AS conta_destino')
            ->where('t.deleted_at IS NULL', null, false)
            ->where('t.id_conta_origem', $contaOrigem)
            ->where('t.id_conta_destino', $contaDestino)
            ->where('t.data_transferencia >=', $de)
            ->where('t.data_transferencia <=', $ate)
            ->where('t.valor >=', ($centavos - 0.5) / 100)
            ->where('t.valor <', ($centavos + 0.5) / 100)
            ->where(
                't.id_transferencia NOT IN (SELECT pagamento_id FROM ' . $this->db->prefixTable('fin_extratos_movimentos') . ' WHERE pagamento_tipo = ' . $this->db->escape('transferencia')
                . ' AND conta_id = ' . (int) $ladoContaId . ' AND pagamento_id IS NOT NULL)',
                null,
                false
            )
            ->orderBy('t.data_transferencia', 'ASC');

        return $this->normalizar($b->get()->getResultArray());
    }

    /**
     * Movimentos pendentes de OUTRAS contas, com o valor oposto exato, na janela (par de transferência).
     *
     * @return list<array<string,mixed>> linhas de fin_extratos_movimentos
     */
    public function movimentosParaTransferencia(int $contaId, int $valorCentavosOposto, string $de, string $ate): array
    {
        return $this->db->table('fin_extratos_movimentos')
            ->where('deleted_at IS NULL', null, false)
            ->where('status', 'pendente')
            ->where('conta_id !=', $contaId)
            ->where('valor_centavos', $valorCentavosOposto)
            ->where('data >=', $de)
            ->where('data <=', $ate)
            ->orderBy('data', 'ASC')
            ->orderBy('id_movimento', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Busca um pagamento (despesa ou receita) pelo id, com dados para validar o vínculo.
     *
     * @return array<string,mixed>|null id, data, valor_centavos, conta_id, ...
     */
    public function obterPagamento(string $tipo, int $id): ?array
    {
        $linhas = $tipo === 'despesa'
            ? $this->db->table('fin_pgto_despesas')->select('id_pgto_despesa AS id, pagamento_despesa_dt AS data, valor, conta_id')->where('id_pgto_despesa', $id)->where('deleted_at IS NULL', null, false)
            : $this->db->table('fin_pgto_receitas')->select('id_pgto_receita AS id, pagamento_receita_dt AS data, valor, conta_id')->where('id_pgto_receita', $id)->where('deleted_at IS NULL', null, false);
        $r = $this->normalizar($linhas->get()->getResultArray());

        return $r[0] ?? null;
    }

    /**
     * Despesa (ou receita) existente, com a informação de já ter pagamento, para quitar a partir de um movimento.
     *
     * @return array<string,mixed>|null id, data (vencimento), valor_centavos, paga (bool)
     */
    public function obterEmAberto(string $tipo, int $id): ?array
    {
        if ($tipo === 'despesa') {
            $b = $this->db->table('fin_despesas')->select('id_despesa AS id, vencimento_dt AS data, valor')->where('id_despesa', $id);
            $pagos = $this->db->table('fin_pgto_despesas')->where('despesa_id', $id)->where('deleted_at IS NULL', null, false);
        } else {
            $b = $this->db->table('fin_receitas')->select('id_receita AS id, vencimento_dt AS data, valor')->where('id_receita', $id);
            $pagos = $this->db->table('fin_pgto_receitas')->where('receita_id', $id)->where('deleted_at IS NULL', null, false);
        }
        $r = $this->normalizar($b->where('deleted_at IS NULL', null, false)->get()->getResultArray());
        if (!$r) {
            return null;
        }
        $r[0]['paga'] = $pagos->countAllResults() > 0;

        return $r[0];
    }

    /** @return array<string,mixed>|null id, data, valor_centavos, conta_origem, conta_destino */
    public function obterTransferencia(int $id): ?array
    {
        $r = $this->normalizar(
            $this->db->table('fin_transferencias')
                ->select('id_transferencia AS id, data_transferencia AS data, valor, id_conta_origem AS conta_origem, id_conta_destino AS conta_destino')
                ->where('id_transferencia', $id)
                ->where('deleted_at IS NULL', null, false)
                ->get()->getResultArray()
        );

        return $r[0] ?? null;
    }

    /** @return list<array<string,mixed>> */
    private function pagamentosLivres(string $tipo, int $contaId, int $centavos, string $de, string $ate): array
    {
        if ($tipo === 'despesa') {
            $b = $this->db->table('fin_pgto_despesas p')
                ->select('p.id_pgto_despesa AS id, p.pagamento_despesa_dt AS data, p.valor, d.despesa AS descricao, f.nome AS contraparte, p.comentario')
                ->join('fin_despesas d', 'd.id_despesa = p.despesa_id', 'left')
                ->join('fin_fornecedores f', 'f.id_fornecedor = d.fornecedor', 'left')
                ->where('p.pagamento_despesa_dt >=', $de)
                ->where('p.pagamento_despesa_dt <=', $ate)
                ->orderBy('p.pagamento_despesa_dt', 'ASC');
            $pk = 'p.id_pgto_despesa';
        } else {
            $b = $this->db->table('fin_pgto_receitas p')
                ->select('p.id_pgto_receita AS id, p.pagamento_receita_dt AS data, p.valor, r.receita AS descricao, c.nome AS contraparte, p.comentario')
                ->join('fin_receitas r', 'r.id_receita = p.receita_id', 'left')
                ->join('clientes c', 'c.id_cliente = r.cliente_id', 'left')
                ->where('p.pagamento_receita_dt >=', $de)
                ->where('p.pagamento_receita_dt <=', $ate)
                ->orderBy('p.pagamento_receita_dt', 'ASC');
            $pk = 'p.id_pgto_receita';
        }

        $b->where('p.deleted_at IS NULL', null, false)
            ->where('p.conta_id', $contaId)
            ->where('p.valor >=', ($centavos - 0.5) / 100)
            ->where('p.valor <', ($centavos + 0.5) / 100)
            ->where(
                "{$pk} NOT IN (SELECT pagamento_id FROM " . $this->db->prefixTable('fin_extratos_movimentos') . ' WHERE pagamento_tipo = ' . $this->db->escape($tipo)
                . ' AND pagamento_id IS NOT NULL)',
                null,
                false
            );

        return $this->normalizar($b->get()->getResultArray());
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    private function normalizar(array $linhas): array
    {
        foreach ($linhas as &$l) {
            $l['id']             = (int) $l['id'];
            $l['valor_centavos'] = (int) round(((float) $l['valor']) * 100);
            unset($l['valor']);
        }

        return $linhas;
    }
}
