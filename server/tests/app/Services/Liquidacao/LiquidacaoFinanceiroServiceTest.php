<?php

namespace Tests\App\Services\Liquidacao;

use App\Services\Financeiro\ReceitasService;
use App\Services\LiquidacaoFinanceiroService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Liquidação 4/6: cálculo e lançamento dos honorários na baixa de parcela.
 */
class LiquidacaoFinanceiroServiceTest extends CIUnitTestCase
{
    private array $liquidacao = ['liquidacao_id' => 9, 'valor_total' => 100000, 'honorarios_percentual' => '30', 'contrato_id' => null];
    private array $itens = [
        ['tipo_item' => 'principal', 'valor_calculado' => 90000],
        ['tipo_item' => 'honorarios', 'valor_calculado' => 10000],
    ];

    public function testSucumbenciaProporcionalEContratuaisSobreOResto(): void
    {
        // parcela de 50.000: 10% é sucumbência (5.000); 30% de 45.000 = 13.500 contratuais
        $v = (new LiquidacaoFinanceiroService())->calcularHonorarios($this->liquidacao, $this->itens, ['valor_parcela' => 50000]);

        $this->assertSame(['sucumbencia' => 5000, 'contratuais' => 13500], $v);
    }

    public function testSemPercentualNemItemNaoHaHonorarios(): void
    {
        $liq = ['liquidacao_id' => 9, 'valor_total' => 100000, 'honorarios_percentual' => null];
        $v = (new LiquidacaoFinanceiroService())->calcularHonorarios($liq, [['tipo_item' => 'principal', 'valor_calculado' => 100000]], ['valor_parcela' => 1000]);

        $this->assertSame(['sucumbencia' => 0, 'contratuais' => 0], $v);
    }

    public function testLancaUmaReceitaPorTipoDeHonorario(): void
    {
        $receitas = $this->createMock(ReceitasService::class);
        $receitas->expects($this->exactly(2))->method('create')
            ->with($this->callback(static fn ($d) => $d['cliente_id'] === 10 && $d['data'] === '2026-01-10' && (float) $d['valor'] > 0))
            ->willReturnOnConsecutiveCalls(1, 2);

        $ids = (new LiquidacaoFinanceiroService($receitas))->lancarHonorarios(
            $this->liquidacao, $this->itens,
            ['valor_parcela' => 50000, 'numero_parcela' => 1],
            ['cliente_id' => 10, 'numero_processo' => '0001'],
            '2026-01-10'
        );

        $this->assertSame([1, 2], $ids);
    }

    public function testComContaLancaJaRecebida(): void
    {
        $receitas = $this->createMock(ReceitasService::class);
        $receitas->expects($this->exactly(2))->method('createWithPayment')->willReturn(3);
        $receitas->expects($this->never())->method('create');

        (new LiquidacaoFinanceiroService($receitas))->lancarHonorarios(
            $this->liquidacao, $this->itens, ['valor_parcela' => 50000, 'numero_parcela' => 0], ['cliente_id' => 10], '2026-01-10', 4
        );
    }
}
