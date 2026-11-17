<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\PlanoParcelas;
use App\Libraries\Liquidacao\ResumoPagamentos;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Liquidação 4/6: plano de parcelas e resumo de pagamentos (sem banco).
 */
class PlanoParcelasTest extends CIUnitTestCase
{
    public function testDivideComSobraNaUltimaParcela(): void
    {
        $plano = PlanoParcelas::gerar(10000, 0, 3, '2026-01-10');

        $this->assertSame([3333, 3333, 3334], array_column($plano, 'valor_parcela'));
        $this->assertSame(10000, array_sum(array_column($plano, 'valor_parcela')));
        $this->assertSame([1, 2, 3], array_column($plano, 'numero_parcela'));
    }

    public function testEntradaEhParcelaZero(): void
    {
        $plano = PlanoParcelas::gerar(10000, 4000, 2, '2026-02-10', 'mensal', '2026-01-05');

        $this->assertSame(0, $plano[0]['numero_parcela']);
        $this->assertSame(4000, $plano[0]['valor_parcela']);
        $this->assertSame('2026-01-05', $plano[0]['data_vencimento']);
        $this->assertSame([3000, 3000], [$plano[1]['valor_parcela'], $plano[2]['valor_parcela']]);
    }

    public function testMesesCurtosFicamNoUltimoDiaDoMes(): void
    {
        $plano = PlanoParcelas::gerar(3000, 0, 3, '2026-01-31');

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($plano, 'data_vencimento'));
    }

    public function testPeriodicidadeQuinzenal(): void
    {
        $plano = PlanoParcelas::gerar(2000, 0, 2, '2026-01-01', 'quinzenal');

        $this->assertSame(['2026-01-01', '2026-01-16'], array_column($plano, 'data_vencimento'));
    }

    #[DataProvider('invalidos')]
    public function testRejeitaParametrosInvalidos(array $args): void
    {
        $this->expectException(LiquidacaoException::class);
        PlanoParcelas::gerar(...$args);
    }

    public static function invalidos(): array
    {
        return [
            'total zero'         => [[0, 0, 2, '2026-01-01']],
            'entrada >= total'   => [[1000, 1000, 2, '2026-01-01']],
            'sem parcelas'       => [[1000, 0, 0, '2026-01-01']],
            'parcelas demais'    => [[1000, 0, 361, '2026-01-01']],
            'periodicidade'      => [[1000, 0, 2, '2026-01-01', 'diaria']],
            'data inválida'      => [[1000, 0, 2, '2026-02-30']],
        ];
    }

    public function testResumoSomaPagasVencidasEProxima(): void
    {
        $resumo = ResumoPagamentos::calcular([
            ['numero_parcela' => 1, 'valor_parcela' => 3000, 'data_vencimento' => '2026-01-10', 'status_pagamento' => 'paga'],
            ['numero_parcela' => 2, 'valor_parcela' => 3000, 'data_vencimento' => '2026-02-10', 'status_pagamento' => 'vencida'],
            ['numero_parcela' => 3, 'valor_parcela' => 3000, 'data_vencimento' => '2026-03-10', 'status_pagamento' => 'pendente'],
            ['numero_parcela' => 4, 'valor_parcela' => 9999, 'data_vencimento' => '2026-04-10', 'status_pagamento' => 'cancelada'],
        ], 10000, '2026-02-20');

        $this->assertSame(3, $resumo['total_parcelas']);
        $this->assertSame(9000, $resumo['valor_parcelado']);
        $this->assertSame(3000, $resumo['total_pago']);
        $this->assertSame(3000, $resumo['total_vencido']);
        $this->assertSame(7000, $resumo['saldo_a_receber']);
        $this->assertSame(2, $resumo['proxima_parcela']['numero_parcela']);
        $this->assertSame(1000, $resumo['diferenca_parcelamento']);
    }

    public function testResumoSemParcelas(): void
    {
        $resumo = ResumoPagamentos::calcular([], 5000, '2026-01-01');

        $this->assertSame(0, $resumo['total_parcelas']);
        $this->assertNull($resumo['proxima_parcela']);
        $this->assertNull($resumo['diferenca_parcelamento']);
        $this->assertSame(5000, $resumo['saldo_a_receber']);
    }
}
