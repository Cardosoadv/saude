<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Repositories\LiquidacoesRepository;
use App\Services\AcessoEntidadeService;
use App\Services\LiquidacoesService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Liquidação 1/6: CRUD da liquidação, total recalculado, histórico e escopo por cliente.
 */
class LiquidacoesServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    protected LiquidacoesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasLiquidacao();

        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'numero_processo' => '0001', 'cliente_id' => 10],
            ['id_processo' => 2, 'numero_processo' => '0002', 'cliente_id' => 20],
        ]);

        $this->service = $this->criarService();
    }

    private function criarService(?int $escopoCliente = null): LiquidacoesService
    {
        $acesso = $this->createMock(AcessoEntidadeService::class);
        $acesso->method('podeAcessar')->willReturn(true);
        $acesso->method('aplicarEscopo')->willReturnArgument(0);

        $repo = new LiquidacoesRepository(null, null, null, null, null, null, $acesso);

        return new class ($repo, null, $acesso, $escopoCliente) extends LiquidacoesService {
            public function __construct($repo, $cliente, $acesso, private ?int $escopo)
            {
                parent::__construct($repo, $cliente, $acesso);
            }

            public function escopoClienteId(): ?int
            {
                return $this->escopo;
            }
        };
    }

    public function testCriaLiquidacaoComItensETotal(): void
    {
        $id = $this->service->criar([
            'processo_id' => 1,
            'itens'       => [
                ['tipo_item' => 'principal', 'descricao' => 'Verba A', 'valor_base' => 100000],
                ['tipo_item' => 'juros', 'descricao' => 'Juros', 'valor_base' => 5000, 'valor_calculado' => 7000],
            ],
        ]);

        $liquidacao = $this->service->getById($id);

        $this->assertSame('rascunho', $liquidacao['status']);
        $this->assertSame('por_calculo', $liquidacao['tipo_liquidacao']);
        $this->assertSame(107000, (int) $liquidacao['valor_total']);
        $this->assertCount(2, $liquidacao['itens']);
    }

    public function testRejeitaStatusForaDoDominio(): void
    {
        $this->expectException(LiquidacaoException::class);
        $this->service->criar(['processo_id' => 1, 'status' => '1']);
    }

    public function testRejeitaProcessoInexistente(): void
    {
        $this->expectException(LiquidacaoException::class);
        $this->service->criar(['processo_id' => 999]);
    }

    public function testDescricaoLongaEhAceita(): void
    {
        $id = $this->service->criar(['processo_id' => 1]);
        $itemId = $this->service->addItem([
            'liquidacao_id' => $id,
            'tipo_item'     => 'outros',
            'descricao'     => str_repeat('x', 500),
            'valor_base'    => 100,
        ]);

        $this->assertNotEmpty($itemId);
    }

    public function testAtualizarItemRecalculaTotalEGravaHistorico(): void
    {
        $id = $this->service->criar([
            'processo_id' => 1,
            'itens'       => [['tipo_item' => 'principal', 'descricao' => 'A', 'valor_base' => 1000]],
        ]);
        $item = $this->service->getById($id)['itens'][0];

        $this->service->updateItem($item['item_id'], ['valor_base' => 2500]);

        $liquidacao = $this->service->getById($id);
        $this->assertSame(2500, (int) $liquidacao['valor_total']);

        $historico = $this->service->getHistorico($id);
        $this->assertNotEmpty($historico);
        $ultimo = $historico[0];
        $this->assertSame(1000, (int) $ultimo['valor_anterior']);
        $this->assertSame(2500, (int) $ultimo['valor_novo']);
        // data_calculo é DATETIME (antes gravava time()) e usuario_id nulo sem usuário (antes gravava 0)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ultimo['data_calculo']);
        $this->assertNull($ultimo['usuario_id']);
    }

    public function testExcluirItemRecalculaTotal(): void
    {
        $id = $this->service->criar([
            'processo_id' => 1,
            'itens'       => [
                ['tipo_item' => 'principal', 'descricao' => 'A', 'valor_base' => 1000],
                ['tipo_item' => 'multa', 'descricao' => 'B', 'valor_base' => 300],
            ],
        ]);
        $itens = $this->service->getById($id)['itens'];

        $this->service->deleteItem($itens[1]['item_id']);

        $this->assertSame(1000, (int) $this->service->getById($id)['valor_total']);
    }

    public function testAtualizarIgnoraTotalEProcesso(): void
    {
        $id = $this->service->criar(['processo_id' => 1]);

        $this->service->atualizar($id, ['status' => 'homologada', 'valor_total' => 999999, 'processo_id' => 2]);

        $liquidacao = $this->service->getById($id);
        $this->assertSame('homologada', $liquidacao['status']);
        $this->assertSame(0, (int) $liquidacao['valor_total']);
        $this->assertSame(1, (int) $liquidacao['processo_id']);
    }

    public function testExcluirRemoveLiquidacao(): void
    {
        $id = $this->service->criar(['processo_id' => 1]);

        $this->assertTrue($this->service->excluir($id));
        $this->assertNull($this->service->getById($id));
    }

    public function testEscopoExclusivePorClienteEsconderOutrosProcessos(): void
    {
        $idCliente10 = $this->service->criar(['processo_id' => 1]);
        $idCliente20 = $this->service->criar(['processo_id' => 2]);

        $restrito = $this->criarService(10);

        $this->assertNotNull($restrito->getById($idCliente10));
        $this->assertNull($restrito->getById($idCliente20));

        $ids = array_column($restrito->getAll(), 'liquidacao_id');
        $this->assertSame([$idCliente10], array_map('intval', $ids));

        $this->expectException(LiquidacaoException::class);
        $restrito->excluir($idCliente20);
    }

    public function testPagamentoEDocumentoPassamPelaChecagemDeAcesso(): void
    {
        $id = $this->service->criar(['processo_id' => 1]);

        $pagId = $this->service->addPagamento([
            'liquidacao_id'   => $id,
            'numero_parcela'  => 1,
            'valor_parcela'   => 5000,
            'data_vencimento' => '2026-12-01',
        ]);
        $this->service->updatePagamento($pagId, ['forma_pagamento' => 'pix']);
        $this->assertSame('pix', $this->service->getPagamento($pagId)['forma_pagamento']);

        $docId = $this->service->addDocumento([
            'liquidacao_id'  => $id,
            'tipo_documento' => 'sentenca',
            'nome_arquivo'   => 'sentenca.pdf',
            'url_arquivo'    => 'https://exemplo.invalid/sentenca.pdf',
        ]);
        $this->assertSame(1, (int) $this->service->getDocumento($docId)['processo_id']);

        $restrito = $this->criarService(20);
        $this->expectException(LiquidacaoException::class);
        $restrito->addPagamento(['liquidacao_id' => $id, 'numero_parcela' => 2, 'valor_parcela' => 1, 'data_vencimento' => '2026-12-02']);
    }
}
