<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Repositories\LiquidacoesRepository;
use App\Services\AcessoEntidadeService;
use App\Services\IndicesEconomicosService;
use App\Services\LiquidacoesService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;

/**
 * Liquidação 3/6: recálculo persistido, histórico, simulação, memória e recálculo por novo índice.
 */
class RecalculoLiquidacaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    protected LiquidacoesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarTabelasLiquidacao();
        $this->db->table('processos')->insert(['id_processo' => 1, 'numero_processo' => '0001', 'cliente_id' => 10]);
        $meses = ['2025-01', '2025-02', '2025-03'];
        $this->semearIndices([
            'IPCA' => array_fill_keys($meses, '1.00'),
            'INPC' => array_fill_keys($meses, '2.00'),
        ]);

        $acesso = $this->createMock(AcessoEntidadeService::class);
        $acesso->method('podeAcessar')->willReturn(true);
        $acesso->method('aplicarEscopo')->willReturnArgument(0);

        $this->service = new LiquidacoesService(new LiquidacoesRepository(null, null, null, null, null, null, $acesso), null, $acesso);
        Events::removeAllListeners('indices_atualizados');
    }

    private function criarComItemCorrigido(array $extra = []): int
    {
        return $this->service->criar($extra + [
            'processo_id' => 1,
            'data_base'   => '2025-03-20',
            'itens'       => [[
                'tipo_item' => 'principal', 'descricao' => 'Verba', 'valor_base' => 100000,
                'criterio'  => ['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']],
            ], [
                'tipo_item' => 'multa', 'descricao' => 'Multa 10%', 'valor_base' => 0,
                'criterio'  => ['percentual' => '10', 'base_percentual' => 'principal'],
            ]],
        ]);
    }

    public function testCriarCalculaItensTotalEMemoria(): void
    {
        $id = $this->criarComItemCorrigido();

        $liquidacao = $this->service->getById($id);

        $this->assertSame(103030 + 10303, (int) $liquidacao['valor_total']);
        $this->assertNotEmpty($liquidacao['calculado_em']);
        $this->assertTrue($liquidacao['itens'][0]['tem_memoria']);
        $this->assertArrayNotHasKey('memoria_calculo', $liquidacao['itens'][0]);
        $this->assertSame('IPCA', $liquidacao['itens'][0]['criterio']['correcao']['indice']);

        $memoria = $this->service->memoriaDoItem((int) $liquidacao['itens'][0]['item_id']);
        $this->assertSame('calculado', $memoria['tipo']);
        $this->assertSame(103030, $memoria['valor']);
    }

    public function testHistoricoGravaUmaLinhaPorItemAlteradoComOMotivo(): void
    {
        $id = $this->criarComItemCorrigido();
        $this->assertCount(2, $this->service->getHistorico($id)); // criação: principal (0 -> 103030) e multa (0 -> 10303)

        $item = $this->service->getById($id)['itens'][0];
        $this->service->updateItem($item['item_id'], ['criterio' => ['correcao' => ['indice' => 'INPC', 'data_inicial' => '2025-01-15']]]);

        $historico = $this->service->getHistorico($id);
        $this->assertCount(4, $historico); // principal e multa mudaram
        $motivos = array_unique(array_column($historico, 'motivo_alteracao'));
        $this->assertContains('Atualização do item', $motivos);
        $this->assertSame(106121 + 10612, (int) $this->service->getById($id)['valor_total']); // 100000 x 1,02^3 e 10% do corrigido
    }

    public function testAtualizarAteHojeMudaADataBaseEGravaOMotivo(): void
    {
        $id = $this->criarComItemCorrigido(['data_base' => '2025-02-20']);
        $antes = (int) $this->service->getById($id)['valor_total']; // jan-fev: 1,01^2

        $resultado = $this->service->recalcular($id, 'Atualização', '2025-03-31');

        $this->assertSame('2025-03-31', $resultado['data_base']);
        $this->assertGreaterThan($antes, $resultado['valor_total']);
        $this->assertSame('2025-03-31', $this->service->getById($id)['data_base']);
    }

    public function testFalhaNoCalculoDesfazTudo(): void
    {
        $id = $this->criarComItemCorrigido();
        $totalAntes = (int) $this->service->getById($id)['valor_total'];

        try {
            $this->service->addItem(['liquidacao_id' => $id, 'tipo_item' => 'principal', 'descricao' => 'Sem índice', 'valor_base' => 100,
                'criterio' => ['correcao' => ['indice' => 'NAOEXISTE', 'data_inicial' => '2025-01-01']]]);
            $this->fail('Deveria recusar o índice inexistente.');
        } catch (LiquidacaoException $e) {
            $this->assertSame(404, $e->status());
        }

        $liquidacao = $this->service->getById($id);
        $this->assertCount(2, $liquidacao['itens']);
        $this->assertSame($totalAntes, (int) $liquidacao['valor_total']);
    }

    public function testSimularNaoGravaECompararCriterios(): void
    {
        $id = $this->criarComItemCorrigido();
        $item = $this->service->getById($id)['itens'][0];

        $r = $this->service->simular([
            'item_id'  => $item['item_id'],
            'cenarios' => [
                ['nome' => 'IPCA', 'criterio' => ['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']]],
                ['nome' => 'INPC', 'criterio' => ['correcao' => ['indice' => 'INPC', 'data_inicial' => '2025-01-15']]],
            ],
        ]);

        $this->assertSame([103030, 106121], array_column($r['cenarios'], 'valor'));
        $this->assertSame([0, 3091], array_column($r['cenarios'], 'diferenca'));
        $this->assertSame(103030, (int) $this->service->getItem($item['item_id'])['valor_calculado']); // nada foi gravado
    }

    public function testSimularItemAvulso(): void
    {
        $r = $this->service->simular([
            'item'      => ['valor_base' => 100000, 'criterio' => ['correcao' => ['indice' => 'IPCA', 'data_inicial' => '2025-01-15']]],
            'data_base' => '2025-03-20',
        ]);

        $this->assertSame(103030, $r['cenarios'][0]['valor']);
    }

    public function testNovoIndiceRecalculaSoLiquidacoesAbertasQueUsamOIndice(): void
    {
        $aberta    = $this->criarComItemCorrigido();
        $homolog   = $this->criarComItemCorrigido(['status' => 'homologada']);
        $outroIdx  = $this->service->criar(['processo_id' => 1, 'data_base' => '2025-03-20', 'itens' => [[
            'tipo_item' => 'principal', 'descricao' => 'INPC', 'valor_base' => 100000,
            'criterio'  => ['correcao' => ['indice' => 'INPC', 'data_inicial' => '2025-01-15']],
        ]]]);

        // revisão do IPCA de fevereiro (1,00 -> 2,00)
        $this->db->table('indices_valores')->where('indice_id', 1)->where('competencia', '2025-02-01')->update(['valor' => '2.00']);
        service('cache')->clean();

        $recalculadas = $this->service->recalcularPorIndices(['IPCA']);

        $this->assertSame([$aberta], $recalculadas);
        // 100000 x 1,01 x 1,02 x 1,01 = 104050,2 -> 104050; multa de 10% = 10405
        $this->assertSame(104050 + 10405, (int) $this->service->getById($aberta)['valor_total']);
        $this->assertSame(103030 + 10303, (int) $this->service->getById($homolog)['valor_total']);
        $this->assertSame(106121, (int) $this->service->getById($outroIdx)['valor_total']);
    }

    public function testEventoDeIndiceDisparaORecalculo(): void
    {
        $id = $this->criarComItemCorrigido();
        Events::on('indices_atualizados', fn (array $codigos) => $this->service->recalcularPorIndices($codigos));

        $this->db->table('indices_valores')->where('indice_id', 1)->where('competencia', '2025-03-01')->update(['valor' => '3.00']);
        service('cache')->clean();
        $indices = new IndicesEconomicosService();
        $indices->lancarValor('IPCA', '2025-03', '3.00'); // sem mudança de valor: não dispara
        $this->assertSame(103030 + 10303, (int) $this->service->getById($id)['valor_total']);

        $this->db->table('indices_valores')->where('indice_id', 1)->where('competencia', '2025-03-01')->update(['valor' => '1.00']);
        service('cache')->clean();
        $indices->lancarValor('IPCA', '2025-03', '2.00'); // muda: dispara
        $this->assertGreaterThan(103030 + 10303, (int) $this->service->getById($id)['valor_total']);
        Events::removeAllListeners('indices_atualizados');
    }
}
