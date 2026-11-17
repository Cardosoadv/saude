<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\SgsClient;
use App\Services\IndicesEconomicosService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Liquidação 2/6: séries de índices, importação (sem rede) e fator acumulado.
 * Os valores das séries abaixo são de exemplo, não índices oficiais.
 */
class IndicesEconomicosServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    private array $eventos = [];

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();

        $this->criarTabelasLiquidacao();

        $this->db->table('indices_economicos')->insertBatch([
            ['codigo' => 'IPCA', 'nome' => 'IPCA', 'periodicidade' => 'mensal', 'fonte' => 'sgs', 'codigo_serie' => 433, 'ativo' => 1],
            ['codigo' => 'DIA', 'nome' => 'Diário', 'periodicidade' => 'diaria', 'fonte' => 'manual', 'codigo_serie' => null, 'ativo' => 1],
            ['codigo' => 'MANUAL', 'nome' => 'Tabela do tribunal', 'periodicidade' => 'mensal', 'fonte' => 'manual', 'codigo_serie' => null, 'ativo' => 1],
        ]);

        $this->eventos = [];
        Events::removeAllListeners('indices_atualizados');
        Events::on('indices_atualizados', function (array $codigos) {
            $this->eventos[] = $codigos;
        });
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('indices_atualizados');
        parent::tearDown();
    }

    private function servico(?callable $http = null): IndicesEconomicosService
    {
        return new IndicesEconomicosService(null, new SgsClient($http ?? static fn () => ['status' => 200, 'body' => '[]'], 1, 0));
    }

    private function carregarIpca(IndicesEconomicosService $s): void
    {
        $s->lancarValor('IPCA', '2025-01', '0.16');
        $s->lancarValor('IPCA', '2025-02', '1.31');
        $s->lancarValor('IPCA', '2025-03', '0.56');
    }

    public function testFatorAcumuladoMesCheio(): void
    {
        $s = $this->servico();
        $this->carregarIpca($s);

        // 1,0016 x 1,0131 x 1,0056, calculado à mão
        $this->assertSame('1.020403397376', $s->fatorAcumulado('IPCA', '2025-01-20', '2025-03-05'));
    }

    public function testRegraExcluirMesInicial(): void
    {
        $s = $this->servico();
        $this->carregarIpca($s);

        $fator = $s->fatorAcumulado('IPCA', '2025-01-20', '2025-03-05', ['mes_inicial' => 'excluir']);

        $this->assertSame('1.018773360000', $fator); // 1,0131 x 1,0056
    }

    public function testRegraProRataNosMesesDasPontas(): void
    {
        $s = $this->servico();
        $this->carregarIpca($s);

        $esperado = (1 + 0.0016 * 16 / 31) * 1.0131 * (1 + 0.0056 * 5 / 31);
        $fator = $s->fatorAcumulado('IPCA', '2025-01-16', '2025-03-05', ['mes_inicial' => 'pro_rata', 'mes_final' => 'pro_rata']);

        $this->assertEqualsWithDelta($esperado, (float) $fator, 1e-9);
    }

    public function testMesmoMesComProRata(): void
    {
        $s = $this->servico();
        $this->carregarIpca($s);

        $fator = $s->fatorAcumulado('IPCA', '2025-02-01', '2025-02-15', ['mes_inicial' => 'pro_rata']);

        $this->assertEqualsWithDelta(1 + 0.0131 * 14 / 28, (float) $fator, 1e-9);
    }

    public function testDetalheTrazOPassoAPasso(): void
    {
        $s = $this->servico();
        $this->carregarIpca($s);

        $d = $s->fatorAcumuladoDetalhado('IPCA', '2025-01-01', '2025-03-31');

        $this->assertCount(3, $d['passos']);
        $this->assertSame('2025-02', $d['passos'][1]['competencia']);
        $this->assertEquals(1.31, $d['passos'][1]['valor']);
        $this->assertSame('2025-03', $d['ate_competencia']);
    }

    public function testLacunaNoMeioDoPeriodoRecusaOCalculo(): void
    {
        $s = $this->servico();
        $s->lancarValor('IPCA', '2025-01', '0.16');
        $s->lancarValor('IPCA', '2025-03', '0.56');

        try {
            $s->fatorAcumulado('IPCA', '2025-01-01', '2025-03-31');
            $this->fail('Deveria recusar o período com lacuna.');
        } catch (LiquidacaoException $e) {
            $this->assertSame(422, $e->status());
            $this->assertStringContainsString('2025-02', $e->getMessage());
        }
        $this->assertSame(['2025-02'], $s->verificarLacunas('IPCA'));
    }

    public function testToleraMesesFinaisAindaNaoDivulgados(): void
    {
        $s = $this->servico();
        $s->lancarValor('IPCA', '2025-01', '0.16');
        $s->lancarValor('IPCA', '2025-02', '1.31');

        $d = $s->fatorAcumuladoDetalhado('IPCA', '2025-01-01', '2025-04-10', ['tolerar_lacuna_final' => true]);

        $this->assertSame('1.014720960000', $d['fator']); // 1,0016 x 1,0131
        $this->assertSame(['2025-03', '2025-04'], $d['ignoradas']);
        $this->assertSame('2025-02', $d['ate_competencia']);
    }

    public function testIndiceDiarioMultiplicaOsDiasDoPeriodo(): void
    {
        $s = $this->servico();
        $s->lancarValor('DIA', '2025-01-02', '0.10');
        $s->lancarValor('DIA', '2025-01-03', '0.20');
        $s->lancarValor('DIA', '2025-01-06', '0.05');

        $this->assertSame('1.003503501000', $s->fatorAcumulado('DIA', '2025-01-02', '2025-01-06')); // 1,001 x 1,002 x 1,0005
    }

    public function testImportaCsvComVirgulaDecimalEDisparaEvento(): void
    {
        $s = $this->servico();

        $r = $s->importarCsv('MANUAL', "competencia;valor\n01/2025;0,50\n02/2025;0,70\n");

        $this->assertSame(['novos' => 2, 'atualizados' => 0], $r);
        $this->assertEquals(0.7, $s->valorDoMes('MANUAL', '2025-02'));
        $this->assertSame([['MANUAL']], $this->eventos);

        // reimportar o mesmo arquivo não muda nada nem dispara evento
        $this->eventos = [];
        $this->assertSame(['novos' => 0, 'atualizados' => 0], $s->importarCsv('MANUAL', "01/2025;0,50\n02/2025;0,70"));
        $this->assertSame([], $this->eventos);
    }

    public function testCsvComLinhaInvalidaNaoImportaNada(): void
    {
        $s = $this->servico();

        try {
            $s->importarCsv('MANUAL', "2025-01;0,50\n2025-13;0,70\n2025-03;abc");
            $this->fail('Deveria recusar o CSV.');
        } catch (LiquidacaoException $e) {
            $this->assertCount(2, $e->erros());
        }
        $this->assertSame([], $s->valores('MANUAL'));
    }

    public function testAtualizaDoSgsBuscandoSoOQueFalta(): void
    {
        $urls = [];
        $http = function (string $url) use (&$urls): array {
            $urls[] = $url;
            $corpo = str_contains($url, 'dataInicial=01/07/1994')
                ? [['data' => '01/01/2025', 'valor' => '0.16'], ['data' => '01/02/2025', 'valor' => '1.31']]
                : [['data' => '01/02/2025', 'valor' => '1.31'], ['data' => '01/03/2025', 'valor' => '0.56']];

            return ['status' => 200, 'body' => json_encode($corpo)];
        };
        $s = $this->servico($http);

        $primeira = $s->atualizarDoSgs('IPCA');
        $this->assertSame(2, $primeira['IPCA']['novos']);
        $this->assertStringContainsString('sgs.433', $urls[0]);

        $segunda = $s->atualizarDoSgs('IPCA');
        $this->assertSame(1, $segunda['IPCA']['novos']); // só março é novo; fevereiro foi rebuscado e não mudou
        $this->assertStringContainsString('dataInicial=01/02/2025', $urls[1]);
        $this->assertSame([['IPCA'], ['IPCA']], $this->eventos);
    }

    public function testFalhaDoSgsFicaRegistradaESemEvento(): void
    {
        $s = $this->servico(static fn () => ['status' => 500, 'body' => 'erro']);

        $r = $s->atualizarDoSgs('IPCA');

        $this->assertSame('falha', $r['IPCA']['status']);
        $this->assertSame([], $this->eventos);
        $listagem = array_column($s->listar(), null, 'codigo');
        $this->assertSame('falha', $listagem['IPCA']['ultima_importacao']['status']);
    }

    public function testListarMostraUltimaCompetenciaELacunas(): void
    {
        $s = $this->servico();
        $s->lancarValor('IPCA', '2025-01', '0.16');
        $s->lancarValor('IPCA', '2025-04', '0.30');

        $ipca = array_column($s->listar(), null, 'codigo')['IPCA'];

        $this->assertSame('2025-04-01', $ipca['ultima_competencia']);
        $this->assertSame(2, $ipca['lacunas']);
    }
}
