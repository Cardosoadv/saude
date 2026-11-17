<?php

namespace Tests\App\Services\Liquidacao;

use App\Interfaces\AIServiceInterface;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Services\IndicesEconomicosService;
use App\Services\LiquidacaoCriteriosIaService;
use App\Services\LiquidacoesService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Liquidação 5/6: a sugestão da IA é validada e nunca aplicada sozinha.
 */
class LiquidacaoCriteriosIaServiceTest extends CIUnitTestCase
{
    private function servico(array $respostaIa, ?int &$chamadas = null): LiquidacaoCriteriosIaService
    {
        $liquidacoes = $this->createMock(LiquidacoesService::class);
        $liquidacoes->method('liquidacaoComAcesso')->willReturn(['liquidacao_id' => 1]);

        $indices = $this->createMock(IndicesEconomicosService::class);
        $indices->method('listar')->willReturn([
            ['codigo' => 'IPCA', 'ativo' => 1], ['codigo' => 'SELIC', 'ativo' => 1], ['codigo' => 'IPCA-E', 'ativo' => 0],
        ]);

        $ia = $this->createMock(AIServiceInterface::class);
        $ia->method('suggestLiquidacaoCriteria')->willReturnCallback(function ($texto, $codigos) use ($respostaIa, &$chamadas) {
            $chamadas++;
            $this->assertSame(['IPCA', 'SELIC'], $codigos, 'só índices ativos são oferecidos à IA');

            return $respostaIa;
        });

        return new LiquidacaoCriteriosIaService($liquidacoes, $indices, $ia);
    }

    public function testConverteSugestaoEmCriterioValido(): void
    {
        $r = $this->servico([
            'indice_correcao' => 'ipca', 'termo_inicial_correcao' => '2020-05-10',
            'juros_tipo' => 'simples', 'juros_taxa_mensal' => 1, 'termo_inicial_juros' => '2020-06-01',
            'multa_percentual' => 10, 'honorarios_percentual' => 15,
            'trechos' => ['correção pelo IPCA desde o ajuizamento'], 'observacoes' => '',
        ])->sugerir(1, 'Sentença ...');

        $this->assertTrue($r['requer_confirmacao']);
        $this->assertSame('IPCA', $r['criterio']['correcao']['indice']);
        $this->assertSame('2020-05-10', $r['criterio']['correcao']['data_inicial']);
        $this->assertSame('simples', $r['criterio']['juros']['tipo']);
        $this->assertSame('10', $r['sugestao']['multa_percentual']);
        $this->assertSame('15', $r['sugestao']['honorarios_percentual']);
        $this->assertSame([], $r['avisos']);
    }

    public function testDescartaIndiceInexistenteDataInvalidaEJurosSemTaxa(): void
    {
        $r = $this->servico([
            'indice_correcao' => 'TR', 'termo_inicial_correcao' => '2020-13-45',
            'juros_tipo' => 'compostos', 'juros_taxa_mensal' => null, 'juros_indice' => null,
        ])->sugerir(1, 'texto');

        $this->assertSame([], $r['criterio']);
        $this->assertCount(2, $r['avisos']); // índice desconhecido e juros sem taxa (a data só é avaliada se houver índice)
        $this->assertStringContainsString('TR', $r['avisos'][0]);
    }

    public function testJurosPorIndiceCadastrado(): void
    {
        $r = $this->servico(['juros_tipo' => 'simples', 'juros_indice' => 'SELIC'])->sugerir(1, 'texto');

        $this->assertSame('SELIC', $r['criterio']['juros']['indice']);
    }

    public function testRejeitaTextoVazioOuGigante(): void
    {
        $chamadas = 0;
        $servico = $this->servico([], $chamadas);

        foreach (['  ', str_repeat('a', LiquidacaoCriteriosIaService::MAX_TEXTO + 1)] as $texto) {
            try {
                $servico->sugerir(1, $texto);
                $this->fail('Deveria recusar.');
            } catch (LiquidacaoException $e) {
                $this->assertSame(422, $e->status());
            }
        }
        $this->assertSame(0, $chamadas, 'a IA não é chamada com texto inválido');
    }
}
