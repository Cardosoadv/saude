<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;

/**
 * Liga os movimentos do extrato bancário aos pagamentos que eles conciliaram/lançaram, para anexar
 * comprovantes pelo `ComprovantesService` (enviar/enviarLote), que continua dono das regras de tipo,
 * tamanho, pasta mensal e arquivo único por mês. Este serviço só traduz movimento -> pagamento.
 */
class ComprovantesExtratoService
{
    private ExtratosMovimentosRepository $movimentos;

    public function __construct(?ExtratosMovimentosRepository $movimentos = null)
    {
        $this->movimentos = $movimentos ?? new ExtratosMovimentosRepository();
    }

    /**
     * Pagamentos (despesa/receita) vinculados aos movimentos, no formato de `ComprovantesService::enviarLote`.
     * Repetições (dois movimentos para o mesmo pagamento) são unificadas.
     *
     * @param array<int,int|string> $movimentoIds
     * @return array<int,array{tipo:string,id:int}>
     *
     * @throws ExtratoInvalidoException    Lista vazia, movimento sem pagamento ou transferência.
     * @throws ExtratoNaoEncontradoException Movimento inexistente.
     */
    public function pagamentosDosMovimentos(array $movimentoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $movimentoIds), static fn (int $i): bool => $i > 0)));
        if (!$ids) {
            throw new ExtratoInvalidoException('Selecione ao menos um movimento do extrato.');
        }
        if (count($ids) > ComprovantesService::MAX_PAGAMENTOS_LOTE) {
            throw new ExtratoInvalidoException('Selecione no máximo ' . ComprovantesService::MAX_PAGAMENTOS_LOTE . ' movimentos por vez.');
        }

        $pagamentos = [];
        foreach ($ids as $id) {
            $mov = $this->movimentos->obter($id);
            if ($mov === null) {
                throw new ExtratoNaoEncontradoException("Movimento {$id} não encontrado.");
            }

            $tipo = (string) ($mov['pagamento_tipo'] ?? '');
            if (!in_array($mov['status'], ['conciliado', 'lancado'], true) || (int) ($mov['pagamento_id'] ?? 0) <= 0) {
                throw new ExtratoInvalidoException("O movimento {$id} ainda não foi conciliado nem lançado.");
            }
            if (!in_array($tipo, ComprovantesService::TIPOS, true)) {
                throw new ExtratoInvalidoException("O movimento {$id} é uma transferência e não tem comprovante de pagamento.");
            }

            $pagamentos[$tipo . ':' . (int) $mov['pagamento_id']] = ['tipo' => $tipo, 'id' => (int) $mov['pagamento_id']];
        }

        return array_values($pagamentos);
    }
}
