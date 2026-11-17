<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Services\CalculoLiquidacaoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;

/**
 * Liquidação 3/6: motor de cálculo. Os resultados esperados foram calculados à mão
 * (o comentário de cada teste traz a conta). Índices fictícios: IPCA 1,00% e INPC 2,00%
 * ao mês de janeiro a junho de 2025.
 */
class CalculoLiquidacaoServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    protected CalculoLiquidacaoService $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarTabelasLiquidacao();

        $meses = ['2025-01', '2025-02', '2025-03', '2025-04', '2025-05', '2025-06'];
        $this->semearIndices([
            'IPCA' => array_fill_keys($meses, '1.00'),
            'INPC' => array_fill_keys($meses, '2.00'),
        ]);

        $this->motor = new CalculoLiquidacaoService();
    }

    private function item(array $criterio, int $valorBase = 100000, string $tipo = 'principal'): array
    {
        return ['tipo_item' => $tipo, 'descricao' => 'Verba', 'valor_base' => $valorBase, 'valor_calculado' => 0, 'criterio' => $criterio];
    }

    public function testItemSemCriterioMantemOValorManual(): void
    {
        $r = $this->motor->calcularItem(['tipo_item' => 'outros', 'valor_base' => 5000, 'valor_calculado' => 7000, 'criterio' => null], '2025-03-20');

        $this->assertSame(7000, $r['valor']);
        $this->assertSame('manual', $r['memoria']['tipo']);
    }

    public function testCorrecaoSimples(): void
    {
        // 100000 x 1,01^3 (jan, fev, mar) = 103030,10 -> 103030
        $r = $this->motor->calcularItem($this->item(['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']]), '2025-03-20');

        $this->assertSame(103030, $r['valor']);
        $this->assertSame(103030, $r['memoria']['total_corrigido']);
        $this->assertSame(0, $r['memoria']['total_juros']);
        $this->assertSame('1.030301000000', $r['memoria']['parcelas'][0]['fator_correcao']);
        $this->assertCount(3, $r['memoria']['parcelas'][0]['correcao'][0]['passos']);
    }

    public function testJurosSimplesSobreOCorrigido(): void
    {
        // 15/01 a 20/03 = 2 meses + 5 dias = 2,1666...; 103030 x 1% x 2,1666... = 2232,32 -> 2232
        $r = $this->motor->calcularItem($this->item([
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'juros'    => ['tipo' => 'simples', 'taxa_mensal' => '1'],
        ]), '2025-03-20');

        $this->assertSame(2232, $r['memoria']['total_juros']);
        $this->assertSame(105262, $r['valor']);
        $this->assertSame('2.166666666666', $r['memoria']['parcelas'][0]['juros']['segmentos'][0]['meses']);
    }

    public function testJurosSimplesSobreOValorNominal(): void
    {
        // 100000 x 1% x 2,1666... = 2166,67 -> 2167
        $r = $this->motor->calcularItem($this->item([
            'juros' => ['tipo' => 'simples', 'taxa_mensal' => '1', 'data_inicial' => 'vencimento', 'base' => 'nominal'],
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
        ]), '2025-03-20');

        $this->assertSame(2167, $r['memoria']['total_juros']);
    }

    public function testJurosCompostos(): void
    {
        // (1,01^2 x (1 + 0,01 x 5/30) - 1) x 100000 = 2180,02 -> 2180 (base nominal)
        $r = $this->motor->calcularItem($this->item([
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'juros'    => ['tipo' => 'compostos', 'taxa_mensal' => '1', 'base' => 'nominal'],
        ]), '2025-03-20');

        $this->assertSame(2180, $r['memoria']['total_juros']);
    }

    public function testJurosComecandoDepoisDaParcela(): void
    {
        // juros só a partir de 01/02: 01/02 a 20/03 = 1 mês + 19 dias = 1,6333...; 100000 x 1% x 1,63333 = 1633,33 -> 1633
        $r = $this->motor->calcularItem($this->item([
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'juros'    => ['tipo' => 'simples', 'taxa_mensal' => '1', 'data_inicial' => '2025-02-01', 'base' => 'nominal'],
        ]), '2025-03-20');

        $this->assertSame(1633, $r['memoria']['total_juros']);
    }

    public function testJurosPorIndice(): void
    {
        // INPC 2% ao mês, jan a mar: simples = 6% de 100000 = 6000; compostos = (1,02^3 - 1) = 6120,80 -> 6121
        $base = ['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-01']];
        $simples   = $this->motor->calcularItem($this->item($base + ['juros' => ['tipo' => 'simples', 'indice' => 'INPC', 'base' => 'nominal']]), '2025-04-01');
        $compostos = $this->motor->calcularItem($this->item($base + ['juros' => ['tipo' => 'compostos', 'indice' => 'INPC', 'base' => 'nominal']]), '2025-04-01');

        $this->assertSame(6000, $simples['memoria']['total_juros']);
        $this->assertSame(6121, $compostos['memoria']['total_juros']);
    }

    public function testTrocaDeIndiceAPartirDeUmaData(): void
    {
        // IPCA jan-mar (1,01^3) e INPC abr-jun (1,02^3): 100000 x 1,030301 x 1,061208 = 109336,37 -> 109336
        $r = $this->motor->calcularItem($this->item([
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'mudancas' => [['a_partir_de' => '2025-04-17', 'correcao' => ['indice' => 'INPC']]], // vale desde abril
        ]), '2025-06-10');

        $this->assertSame(109336, $r['valor']);
        $correcao = $r['memoria']['parcelas'][0]['correcao'];
        $this->assertSame(['IPCA', 'INPC'], array_column($correcao, 'indice'));
        $this->assertSame('2025-03-31', $correcao[0]['ate']);
        $this->assertSame('2025-04-01', $correcao[1]['de']);
    }

    public function testTrocaDeJurosParaSemJurosNoMeioDoPeriodo(): void
    {
        // juros simples 1% de 01/01 a 31/03 (3 meses) e nenhum depois: 100000 x 3% = 3000 (nominal)
        $r = $this->motor->calcularItem($this->item([
            'juros'    => ['tipo' => 'simples', 'taxa_mensal' => '1', 'data_inicial' => '2025-01-01', 'base' => 'nominal'],
            'mudancas' => [['a_partir_de' => '2025-04-01', 'juros' => ['tipo' => 'nenhum']]],
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-01'],
        ]), '2025-06-01');

        $this->assertSame(3000, $r['memoria']['total_juros']);
    }

    public function testParcelasMensaisCadaUmaCorrigidaDesdeOSeuVencimento(): void
    {
        // 50000 em 10/01 (1,01^3 = 51515,05), 10/02 (1,01^2 = 51005) e 10/03 (1,01 = 50500); base 31/03
        $r = $this->motor->calcularItem($this->item([
            'correcao' => ['indice' => 'IPCA'],
            'parcelas' => [
                ['data' => '2025-01-10', 'valor' => 50000],
                ['data' => '2025-02-10', 'valor' => 50000],
                ['data' => '2025-03-10', 'valor' => 50000],
            ],
        ], 0), '2025-03-31');

        $this->assertSame([51515, 51005, 50500], array_column($r['memoria']['parcelas'], 'corrigido'));
        $this->assertSame(153020, $r['valor']);
    }

    public function testAbatimentoParcialDescontadoNaDataEmQueOcorreu(): void
    {
        // principal 100000 em 15/01: 103030; abatimento de 40000 em 20/02 corrigido de fev a mar (1,01^2 = 40804)
        $r = $this->motor->calcularItem($this->item([
            'correcao'    => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'abatimentos' => [['data' => '2025-02-20', 'valor' => 40000]],
        ]), '2025-03-20');

        $this->assertSame(103030 - 40804, $r['valor']);
        $abatimento = $r['memoria']['parcelas'][1];
        $this->assertTrue($abatimento['abatimento']);
        $this->assertSame(-40804, $abatimento['corrigido']);
    }

    public function testArredondamentoMeioParaCimaAfastandoDeZero(): void
    {
        // 1005 x 10% = 100,5 -> 101 ; abatimento de 1005 x 10%... valor negativo -> -101
        $itens = [
            1 => ['tipo_item' => 'principal', 'descricao' => 'P', 'valor_base' => 1005, 'valor_calculado' => 1005, 'criterio' => null],
            2 => ['tipo_item' => 'multa', 'descricao' => 'M', 'valor_base' => 0, 'valor_calculado' => 0, 'criterio' => ['percentual' => '10', 'base_percentual' => 'principal']],
            3 => ['tipo_item' => 'principal', 'descricao' => 'N', 'valor_base' => -1005, 'valor_calculado' => -1005, 'criterio' => null],
        ];
        $r = $this->motor->calcularLiquidacao($itens, '2025-03-20');
        $this->assertSame(0, $r[2]['valor']); // 1005 + (-1005) = 0 de base

        $itens[3]['valor_calculado'] = 0;
        $itens[3]['valor_base'] = 0;
        $this->assertSame(101, $this->motor->calcularLiquidacao($itens, '2025-03-20')[2]['valor']);

        $itens[1]['valor_calculado'] = -1005;
        $itens[3]['valor_calculado'] = 0;
        $this->assertSame(-101, $this->motor->calcularLiquidacao($itens, '2025-03-20')[2]['valor']);
    }

    public function testMultaEHonorariosEmPercentualSobreBasesEscolhidas(): void
    {
        $criterioPrincipal = [
            'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
            'juros'    => ['tipo' => 'simples', 'taxa_mensal' => '1', 'base' => 'nominal'],
        ];
        $itens = [
            1 => ['tipo_item' => 'principal', 'descricao' => 'P', 'valor_base' => 100000, 'valor_calculado' => 0, 'criterio' => $criterioPrincipal],
            2 => ['tipo_item' => 'multa', 'descricao' => 'M', 'valor_base' => 0, 'valor_calculado' => 0, 'criterio' => ['percentual' => '10', 'base_percentual' => 'principal']],
            3 => ['tipo_item' => 'honorarios', 'descricao' => 'H', 'valor_base' => 0, 'valor_calculado' => 0, 'criterio' => ['percentual' => '20', 'base_percentual' => 'total']],
            4 => ['tipo_item' => 'custas', 'descricao' => 'C', 'valor_base' => 20000, 'valor_calculado' => 20000, 'criterio' => null],
        ];

        $r = $this->motor->calcularLiquidacao($itens, '2025-03-20');

        // principal: corrigido 103030 + juros nominais 2167 = 105197; custas manuais 20000
        $this->assertSame(105197, $r[1]['valor']);
        // multa 10% sobre o principal corrigido + custas (103030 + 20000) = 123030 -> 12303
        $this->assertSame(12303, $r[2]['valor']);
        // honorários 20% sobre o total (105197 + 20000 + 12303 = 137500) = 27500
        $this->assertSame(27500, $r[3]['valor']);
        $this->assertSame(137500 + 27500, array_sum(array_column($r, 'valor')));
        $this->assertSame([1, 2, 3, 4], array_keys($r));
    }

    public function testBasePrincipalMaisJuros(): void
    {
        $itens = [
            1 => ['tipo_item' => 'principal', 'descricao' => 'P', 'valor_base' => 100000, 'valor_calculado' => 0, 'criterio' => [
                'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15'],
                'juros'    => ['tipo' => 'simples', 'taxa_mensal' => '1', 'base' => 'nominal'],
            ]],
            2 => ['tipo_item' => 'multa', 'descricao' => 'M', 'valor_base' => 0, 'valor_calculado' => 0, 'criterio' => ['percentual' => '10', 'base_percentual' => 'principal_juros']],
        ];

        $r = $this->motor->calcularLiquidacao($itens, '2025-03-20');

        $this->assertSame(10520, $r[2]['valor']); // 10% de 105197 = 10519,7
    }

    public function testCriterioPadraoDaLiquidacaoEOverrideDoItem(): void
    {
        $padrao = ['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']];
        $itens = [
            1 => ['tipo_item' => 'principal', 'descricao' => 'usa o padrão', 'valor_base' => 100000, 'valor_calculado' => 0, 'criterio' => ['usar_padrao' => true]],
            2 => ['tipo_item' => 'principal', 'descricao' => 'troca o índice', 'valor_base' => 100000, 'valor_calculado' => 0, 'criterio' => ['correcao' => ['indice' => 'INPC', 'data_inicial' => '2025-01-15']]],
            3 => ['tipo_item' => 'outros', 'descricao' => 'manual', 'valor_base' => 500, 'valor_calculado' => 500, 'criterio' => null],
        ];

        $r = $this->motor->calcularLiquidacao($itens, '2025-03-20', $padrao);

        $this->assertSame(103030, $r[1]['valor']);
        $this->assertSame(106121, $r[2]['valor']); // 100000 x 1,02^3 = 106120,80
        $this->assertSame(500, $r[3]['valor']);
    }

    public function testMesesFinaisSemDivulgacaoSaoAvisadosEFicamForaDaCorrecao(): void
    {
        // série vai até 06/2025; data-base em agosto: jan-jun entram (1,01^6 = 106152,015 -> 106152), jul-ago não
        $r = $this->motor->calcularItem($this->item(['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']]), '2025-08-20');

        $this->assertSame(106152, $r['valor']);
        $this->assertStringContainsString('2025-07, 2025-08', $r['memoria']['avisos'][0]);
    }

    public function testLacunaNoMeioDoPeriodoRecusaOCalculoComONomeDoItem(): void
    {
        $this->db->table('indices_valores')->where('indice_id', 1)->where('competencia', '2025-02-01')->delete();
        service('cache')->clean();

        try {
            $this->motor->calcularItem($this->item(['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']]), '2025-03-20');
            $this->fail('Deveria recusar.');
        } catch (LiquidacaoException $e) {
            $this->assertStringContainsString('Verba', $e->getMessage());
            $this->assertStringContainsString('2025-02', $e->getMessage());
        }
    }

    public function testParcelaPosteriorADataBaseNaoRecebeCorrecao(): void
    {
        $r = $this->motor->calcularItem($this->item(['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-05-01']]), '2025-03-20');

        $this->assertSame(100000, $r['valor']);
        $this->assertNotEmpty($r['memoria']['avisos']);
    }

    public function testCorrecaoSemDataInicialExigeInformarOTermo(): void
    {
        $this->expectException(LiquidacaoException::class);
        $this->motor->calcularItem($this->item(['correcao' => ['indice' => 'IPCA']]), '2025-03-20');
    }

    public function testCriterioInvalidoEhRecusado(): void
    {
        $this->expectException(LiquidacaoException::class);
        $this->motor->calcularItem($this->item(['juros' => ['tipo' => 'inventado']]), '2025-03-20');
    }
}
