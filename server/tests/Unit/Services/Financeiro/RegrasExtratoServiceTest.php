<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ExtratosRegrasRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\Financeiro\RegrasExtratoService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regras de classificação de extratos (só sugestão), com repositório em memória. Dados fictícios.
 */
class RegrasExtratoServiceTest extends CIUnitTestCase
{
    private MemoriaRegras $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MemoriaRegras();
    }

    private function servico(): RegrasExtratoService
    {
        return new RegrasExtratoService($this->repo);
    }

    private function mov(string $contraparte, int $centavos = -1000, string $memo = '', int $conta = 1): array
    {
        return ['conta_id' => $conta, 'valor_centavos' => $centavos, 'memo' => $memo, 'nome_contraparte' => $contraparte];
    }

    public function testCriaRegraComRateioEDevolveApresentada(): void
    {
        $r = $this->servico()->criar([
            'padrao' => 'Papelaria Exemplo', 'fornecedor_id' => 7, 'categoria_id' => 3, 'sinal' => 'saida',
            'rateio' => [['id' => 1, 'percentual' => 70], ['id' => 2, 'percentual' => 30]],
        ], 9);

        $this->assertSame('Papelaria Exemplo', $r['nome']);
        $this->assertSame('ambos', $r['campo']);
        $this->assertTrue($r['ativo']);
        $this->assertSame([['id' => 1, 'percentual' => 70], ['id' => 2, 'percentual' => 30]], $r['rateio']);
        $this->assertSame(9, $this->repo->linhas[$r['id_regra']]['criado_por']);
    }

    public function testValidacoes(): void
    {
        $casos = [
            ['padrao' => 'ab'],
            ['padrao' => '--'],
            ['padrao' => 'Papelaria', 'campo' => 'outro'],
            ['padrao' => 'Papelaria', 'sinal' => 'x'],
            ['padrao' => 'Papelaria', 'fornecedor_id' => 1, 'cliente_id' => 2],
            ['padrao' => 'Papelaria', 'rateio' => [['id' => 1, 'percentual' => 50]]],
            ['padrao' => 'Papelaria', 'rateio' => [['id' => 1, 'percentual' => 50], ['id' => 1, 'percentual' => 50]]],
            ['padrao' => 'Papelaria', 'rateio' => [['id' => 0, 'percentual' => 100]]],
        ];
        foreach ($casos as $c) {
            try {
                $this->servico()->criar($c);
                $this->fail('deveria recusar: ' . json_encode($c));
            } catch (ExtratoInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->repo->linhas);
    }

    public function testSugereRegraQueCasaIgnorandoCaixaEAcento(): void
    {
        $this->servico()->criar(['padrao' => 'MERCADO São João', 'campo' => 'contraparte', 'sinal' => 'saida', 'categoria_id' => 4]);

        $s = $this->servico()->sugerir($this->mov('Mercado Sao Joao Ltda'));

        $this->assertSame(4, $s['categoria_id']);
        $this->assertNull($this->servico()->sugerir($this->mov('Outro Mercado')));
    }

    public function testSinalEContaRestringemARegra(): void
    {
        $this->servico()->criar(['padrao' => 'Papelaria', 'sinal' => 'saida', 'conta_id' => 1, 'fornecedor_id' => 7]);

        $this->assertNotNull($this->servico()->sugerir($this->mov('Papelaria Exemplo', -500)));
        $this->assertNull($this->servico()->sugerir($this->mov('Papelaria Exemplo', 500)), 'entrada não casa regra de saída');
        $this->assertNull($this->servico()->sugerir($this->mov('Papelaria Exemplo', -500, '', 2)), 'outra conta');
    }

    public function testCampoMemoEContraparteSaoSeparados(): void
    {
        $this->servico()->criar(['padrao' => 'tarifa mensal', 'campo' => 'memo']);

        $this->assertNotNull($this->servico()->sugerir($this->mov('Banco', -990, 'Tarifa mensal da conta')));
        $this->assertNull($this->servico()->sugerir($this->mov('Tarifa mensal', -990, 'Outra coisa')));
    }

    public function testCasaPorPalavraInteiraEPadraoMaisEspecificoVence(): void
    {
        $this->servico()->criar(['padrao' => 'posto', 'categoria_id' => 1]);
        $this->servico()->criar(['padrao' => 'posto central', 'categoria_id' => 2]);

        $this->assertSame(2, $this->servico()->sugerir($this->mov('Posto Central 123'))['categoria_id']);
        $this->assertSame(1, $this->servico()->sugerir($this->mov('Posto Ipiranga'))['categoria_id']);
        $this->assertNull($this->servico()->sugerir($this->mov('Compostos Químicos')), 'não casa no meio de uma palavra');
    }

    public function testRegraInativaNaoSugere(): void
    {
        $r = $this->servico()->criar(['padrao' => 'Papelaria']);
        $this->servico()->atualizar($r['id_regra'], ['ativo' => false]);

        $this->assertNull($this->servico()->sugerir($this->mov('Papelaria Exemplo')));
    }

    public function testAtualizaEExclui(): void
    {
        $r = $this->servico()->criar(['padrao' => 'Papelaria']);

        $a = $this->servico()->atualizar($r['id_regra'], ['categoria_id' => 8, 'rateio' => []]);
        $this->assertSame(8, $a['categoria_id']);

        $this->servico()->excluir($r['id_regra']);
        $this->expectException(ExtratoNaoEncontradoException::class);
        $this->servico()->obter($r['id_regra']);
    }

    public function testAtualizarSemCamposOuRegraInexistente(): void
    {
        $r = $this->servico()->criar(['padrao' => 'Papelaria']);
        try {
            $this->servico()->atualizar($r['id_regra'], []);
            $this->fail();
        } catch (ExtratoInvalidoException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(ExtratoNaoEncontradoException::class);
        $this->servico()->atualizar(999, ['nome' => 'x']);
    }
}

class MemoriaRegras extends ExtratosRegrasRepository
{
    /** @var array<int,array> */
    public array $linhas = [];

    public function __construct()
    {
    }

    public function obter(int $id): ?array
    {
        return $this->linhas[$id] ?? null;
    }

    public function listar(?int $contaId = null, bool $apenasAtivas = false): array
    {
        return array_values($this->linhas);
    }

    public function listarAtivasDaConta(int $contaId): array
    {
        return array_values(array_filter(
            $this->linhas,
            static fn (array $r): bool => (int) $r['ativo'] === 1 && ($r['conta_id'] === null || $r['conta_id'] === $contaId)
        ));
    }

    public function criar(array $dados): int
    {
        $id = count($this->linhas) + 1;
        $this->linhas[$id] = $dados + ['id_regra' => $id, 'conta_id' => null, 'fornecedor_id' => null, 'cliente_id' => null, 'categoria_id' => null, 'rateio' => null];
        $this->linhas[$id]['id_regra'] = $id;

        return $id;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $this->linhas[$id] = $dados + $this->linhas[$id];

        return true;
    }

    public function excluir(int $id): bool
    {
        unset($this->linhas[$id]);

        return true;
    }
}
