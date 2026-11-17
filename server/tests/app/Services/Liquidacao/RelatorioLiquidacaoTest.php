<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\RelatorioLiquidacao;
use App\Libraries\Liquidacao\Xlsx;
use CodeIgniter\Test\CIUnitTestCase;
use ZipArchive;

/**
 * Liquidação 5/6: montagem do relatório (PDF/CSV/XLSX) a partir da memória de cálculo.
 */
class RelatorioLiquidacaoTest extends CIUnitTestCase
{
    private function liquidacao(): array
    {
        return [
            'liquidacao_id' => 5, 'status' => 'apresentada', 'data_base' => '2026-03-31', 'valor_total' => 111500,
            'criterio_padrao' => ['correcao' => ['indice' => 'IPCA']],
            'itens' => [
                ['item_id' => 1, 'tipo_item' => 'principal', 'descricao' => 'Verba A', 'valor_base' => 100000, 'valor_calculado' => 103030],
                ['item_id' => 2, 'tipo_item' => 'honorarios', 'descricao' => 'Honorários', 'valor_base' => 0, 'valor_calculado' => 10303],
                ['item_id' => 3, 'tipo_item' => 'custas', 'descricao' => 'Custas', 'valor_base' => 5000, 'valor_calculado' => 5000],
            ],
        ];
    }

    private function memorias(): array
    {
        return [
            1 => [
                'tipo' => 'calculado', 'data_base' => '2026-03-31',
                'criterio' => [
                    'correcao' => ['indice' => 'IPCA', 'data_inicial' => '2026-01-01'],
                    'juros' => ['tipo' => 'simples', 'taxa_mensal' => '1.0000', 'data_inicial' => 'vencimento', 'base' => 'corrigido'],
                ],
                'parcelas' => [
                    ['data' => '2026-01-01', 'valor' => 100000, 'abatimento' => false, 'fator_correcao' => '1.030300000000', 'corrigido' => 103030, 'juros' => ['valor' => 0], 'subtotal' => 103030],
                    ['data' => '2026-02-01', 'valor' => -10000, 'abatimento' => true, 'fator_correcao' => '1', 'corrigido' => -10000, 'juros' => ['valor' => 0], 'subtotal' => -10000],
                ],
                'avisos' => ['Índice IPCA ainda não divulgado para 2026-03.'],
            ],
            2 => ['tipo' => 'percentual', 'data_base' => '2026-03-31', 'percentual' => '10.0000', 'base_percentual' => 'principal', 'base_valor' => 103030, 'valor' => 10303],
            3 => null,
        ];
    }

    public function testMontaLinhasPorParcelaPercentualEManual(): void
    {
        $r = RelatorioLiquidacao::montar($this->liquidacao(), $this->memorias(), ['processo' => '0001', 'cliente' => 'Cliente X']);

        // 2 parcelas + 1 percentual + 1 manual + total
        $this->assertCount(5, $r['linhas']);
        $this->assertSame('1.0303', $r['linhas'][0][4]);
        $this->assertSame(103030, $r['linhas'][0][5]);
        $this->assertSame('Abatimento', $r['linhas'][1][8]);
        $this->assertSame('10% sobre o principal corrigido', $r['linhas'][2][8]);
        $this->assertSame('TOTAL', $r['linhas'][4][0]);
        $this->assertSame(111500, $r['linhas'][4][7]);

        $this->assertSame([['item' => 'Verba A', 'data' => '2026-02-01', 'valor' => 10000]], $r['abatimentos']);
        $this->assertSame(10000, $r['totais']['abatimentos']);
        $this->assertSame('Cliente X', $r['cabecalho']['cliente']);
        $this->assertStringContainsString('Verba A', $r['avisos'][0]);
    }

    public function testDescreveCriterio(): void
    {
        $frases = RelatorioLiquidacao::descreverCriterio($this->memorias()[1]['criterio']);

        $this->assertSame('Correção monetária pelo IPCA, a partir de 01/01/2026.', $frases[0]);
        $this->assertSame('Juros simples à taxa de 1% ao mês, a partir do vencimento, sobre o valor corrigido.', $frases[1]);
    }

    public function testCsvUsaPontoEVirgulaVirgulaDecimalEBom(): void
    {
        $csv = RelatorioLiquidacao::csv(RelatorioLiquidacao::montar($this->liquidacao(), $this->memorias(), ['processo' => '0001']));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Item;Tipo;Data;"Valor original"', $csv);
        $this->assertStringContainsString('"Verba A";Principal;01/01/2026;1.000,00;1,0303;1.030,30;0,00;1.030,30;', $csv);
        $this->assertStringContainsString('TOTAL;;31/03/2026;;;;;1.115,00;', $csv);
    }

    public function testXlsxContemOsValoresNumericos(): void
    {
        $linhas = RelatorioLiquidacao::linhasXlsx(RelatorioLiquidacao::montar($this->liquidacao(), $this->memorias()));
        $binario = Xlsx::gerar($linhas);

        $arquivo = tempnam(sys_get_temp_dir(), 'teste');
        file_put_contents($arquivo, $binario);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($arquivo));
        $planilha = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($arquivo);

        $this->assertNotFalse($planilha);
        $this->assertNotFalse(simplexml_load_string($planilha), 'XML da planilha precisa ser válido');
        $this->assertStringContainsString('<v>1030.3</v>', $planilha);
        $this->assertStringContainsString('Verba A', $planilha);
    }

    public function testXlsxEscapaCaracteresEspeciais(): void
    {
        $binario = Xlsx::gerar([['A & B <c>', 1]]);
        $arquivo = tempnam(sys_get_temp_dir(), 'teste');
        file_put_contents($arquivo, $binario);
        $zip = new ZipArchive();
        $zip->open($arquivo);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($arquivo);

        $this->assertStringContainsString('A &amp; B &lt;c&gt;', $xml);
    }
}
