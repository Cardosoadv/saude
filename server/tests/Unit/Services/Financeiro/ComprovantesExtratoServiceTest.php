<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\ComprovantesExtratoService;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tradução movimento do extrato -> pagamento para anexar comprovantes. Só dados fictícios.
 */
class ComprovantesExtratoServiceTest extends CIUnitTestCase
{
    private function servico(array $movimentos): ComprovantesExtratoService
    {
        $repo = $this->createMock(ExtratosMovimentosRepository::class);
        $repo->method('obter')->willReturnCallback(static fn (int $id) => $movimentos[$id] ?? null);

        return new ComprovantesExtratoService($repo);
    }

    private function mov(string $status, ?string $tipo, ?int $pagamento): array
    {
        return ['status' => $status, 'pagamento_tipo' => $tipo, 'pagamento_id' => $pagamento];
    }

    public function testConciliadoELancadoViramPagamentosSemRepeticao(): void
    {
        $servico = $this->servico([
            1 => $this->mov('conciliado', 'despesa', 10),
            2 => $this->mov('lancado', 'receita', 20),
            3 => $this->mov('conciliado', 'despesa', 10),
        ]);

        $this->assertSame(
            [['tipo' => 'despesa', 'id' => 10], ['tipo' => 'receita', 'id' => 20]],
            $servico->pagamentosDosMovimentos([1, '2', 3, 1])
        );
    }

    public function testMovimentoPendenteOuIgnoradoNaoTemPagamento(): void
    {
        $servico = $this->servico([1 => $this->mov('pendente', null, null), 2 => $this->mov('ignorado', null, null)]);

        foreach ([1, 2] as $id) {
            try {
                $servico->pagamentosDosMovimentos([$id]);
                $this->fail('deveria recusar');
            } catch (ExtratoInvalidoException $e) {
                $this->assertStringContainsString('ainda não foi conciliado nem lançado', $e->getMessage());
            }
        }
    }

    public function testTransferenciaNaoTemComprovante(): void
    {
        $servico = $this->servico([1 => $this->mov('conciliado', 'transferencia', 5)]);

        $this->expectException(ExtratoInvalidoException::class);
        $this->expectExceptionMessage('transferência');
        $servico->pagamentosDosMovimentos([1]);
    }

    public function testMovimentoInexistente(): void
    {
        $this->expectException(ExtratoNaoEncontradoException::class);
        $this->servico([])->pagamentosDosMovimentos([99]);
    }

    public function testListaVaziaEExcessoDeMovimentos(): void
    {
        $servico = $this->servico([]);

        try {
            $servico->pagamentosDosMovimentos([0, 'x']);
            $this->fail('lista vazia');
        } catch (ExtratoInvalidoException $e) {
            $this->assertStringContainsString('ao menos um', $e->getMessage());
        }

        $this->expectException(ExtratoInvalidoException::class);
        $servico->pagamentosDosMovimentos(range(1, 51));
    }
}
