<?php

declare(strict_types=1);

namespace App\Traits;

use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use Throwable;

/**
 * Para os serviços que excluem pagamentos/transferências: o movimento de extrato que apontava para o
 * registro excluído volta para `pendente` (e nunca é apagado), na mesma transação da exclusão. Assim o
 * extrato nunca fica ligado a um pagamento que não existe mais, e o movimento pode ser lançado de novo.
 */
trait LiberaMovimentosExtratoTrait
{
    /**
     * Executa a exclusão e, se ela excluiu algo, devolve os movimentos ligados a `$ids` para pendente.
     * Qualquer falha desfaz as duas coisas.
     *
     * @param 'despesa'|'receita'|'transferencia' $tipo
     * @param int[]                               $ids  ids dos registros que a exclusão remove
     * @param callable():(bool|int)               $excluir
     * @return bool|int o que `$excluir` devolveu
     */
    protected function excluirLiberandoExtrato(string $tipo, array $ids, callable $excluir)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $resultado = $excluir();
            if ($resultado) {
                $liberados = $this->repositorioMovimentosExtrato()->liberarPorPagamentos($tipo, $ids);
                if ($liberados) {
                    log_message('info', sprintf('[LancamentoExtrato] %s=%s excluído: movimentos %s voltaram para pendente', $tipo, implode(',', $ids), implode(',', $liberados)));
                }
            }
            $db->transComplete();

            return $resultado;
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    protected function repositorioMovimentosExtrato(): ExtratosMovimentosRepository
    {
        return new ExtratosMovimentosRepository();
    }
}
