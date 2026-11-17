<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\Decimal;
use App\Libraries\Liquidacao\LiquidacaoDominio;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Services\Financeiro\ReceitasService;
use Throwable;

/**
 * Lança no Financeiro (fin_receitas do cliente) os honorários do escritório que correspondem
 * a cada pagamento recebido da liquidação.
 *
 * Divisão de cada parcela paga:
 *  - sucumbência = parcela × (soma dos itens `honorarios` ÷ valor total da liquidação);
 *  - contratuais = (parcela − sucumbência) × `honorarios_percentual` da liquidação (se informado).
 *
 * Sem `conta_id` a receita fica "a receber" para ser conferida no Financeiro; com `conta_id`
 * já é lançada como recebida naquela conta. Natureza, repasse ao cliente e tributação dos
 * valores devem ser conferidos pelo advogado/contador.
 */
class LiquidacaoFinanceiroService
{
    protected ?ReceitasService $receitas;

    public function __construct(?ReceitasService $receitas = null)
    {
        $this->receitas = $receitas;
    }

    /**
     * @param array $liquidacao linha de `liquidacoes`
     * @param array $itens      itens da liquidação
     * @param array $pagamento  parcela baixada (`valor_parcela`)
     *
     * @return array{sucumbencia: int, contratuais: int} centavos
     */
    public function calcularHonorarios(array $liquidacao, array $itens, array $pagamento): array
    {
        $parcela = (int) $pagamento['valor_parcela'];
        $total   = (int) $liquidacao['valor_total'];

        $honorarios = 0;
        foreach ($itens as $item) {
            if (($item['tipo_item'] ?? '') === LiquidacaoDominio::ITEM_HONORARIOS) {
                $honorarios += (int) $item['valor_calculado'];
            }
        }

        $sucumbencia = ($total > 0 && $honorarios > 0)
            ? min($parcela, Decimal::centavos(Decimal::div(Decimal::mul((string) $parcela, (string) $honorarios), (string) $total)))
            : 0;

        $percentual  = $liquidacao['honorarios_percentual'] ?? null;
        $contratuais = ($percentual !== null && $percentual !== '' && (float) $percentual > 0)
            ? Decimal::centavos(Decimal::div(Decimal::mul((string) ($parcela - $sucumbencia), (string) $percentual), '100'))
            : 0;

        return ['sucumbencia' => $sucumbencia, 'contratuais' => $contratuais];
    }

    /**
     * Cria as receitas da parcela. Devolve os ids para guardar na parcela (evita lançar duas vezes).
     *
     * @return list<int>
     *
     * @throws LiquidacaoException se o Financeiro recusar o lançamento (ex.: mês fechado)
     */
    public function lancarHonorarios(array $liquidacao, array $itens, array $pagamento, array $processo, string $dataPagamento, ?int $contaId = null): array
    {
        $valores = $this->calcularHonorarios($liquidacao, $itens, $pagamento);
        $numero  = trim((string) ($processo['numeroprocessocommascara'] ?? '')) ?: (string) ($processo['numero_processo'] ?? '');
        $parcela = (int) $pagamento['numero_parcela'] === 0 ? 'entrada' : 'parcela ' . $pagamento['numero_parcela'];

        $ids = [];
        foreach (['sucumbencia' => 'Honorários de sucumbência', 'contratuais' => 'Honorários contratuais'] as $chave => $rotulo) {
            if ($valores[$chave] <= 0) {
                continue;
            }

            $dados = [
                'receita'       => mb_substr("{$rotulo} - liquidação #{$liquidacao['liquidacao_id']} ({$parcela})", 0, 150),
                'vencimento_dt' => $dataPagamento,
                'data'          => $dataPagamento,
                'valor'         => Decimal::div((string) $valores[$chave], '100', 2),
                'cliente_id'    => $processo['cliente_id'] ?? null,
                'contrato_id'   => $liquidacao['contrato_id'] ?? null,
                'comentario'    => "Gerado pela liquidação #{$liquidacao['liquidacao_id']}, processo {$numero}, {$parcela}.",
                'tipo'          => 'receita',
            ];

            try {
                $ids[] = $contaId !== null
                    ? $this->receitas()->createWithPayment($dados, [
                        'conta_id'             => $contaId,
                        'pagamento_receita_dt' => $dataPagamento,
                        'rateio'               => [],
                        'comentario'           => $dados['comentario'],
                    ])
                    : $this->receitas()->create($dados);
            } catch (Throwable $e) {
                throw LiquidacaoException::invalida('O Financeiro recusou o lançamento dos honorários: ' . $e->getMessage());
            }
        }

        return $ids;
    }

    protected function receitas(): ReceitasService
    {
        return $this->receitas ??= new ReceitasService();
    }
}
