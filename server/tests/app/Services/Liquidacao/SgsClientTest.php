<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\SgsClient;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

class SgsClientTest extends CIUnitTestCase
{
    private function periodo(): array
    {
        return [new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-03-31')];
    }

    public function testMontaAUrlEInterpretaOsPontos(): void
    {
        $urls = [];
        $cliente = new SgsClient(function (string $url) use (&$urls) {
            $urls[] = $url;

            return ['status' => 200, 'body' => '[{"data":"01/01/2025","valor":"0.16"},{"data":"01/02/2025","valor":""},{"data":"01/03/2025","valor":"0.56"}]'];
        }, 1, 0);

        [$de, $ate] = $this->periodo();
        $serie = $cliente->serie(433, $de, $ate);

        $this->assertSame('https://api.bcb.gov.br/dados/serie/bcdata.sgs.433/dados?formato=json&dataInicial=01/01/2025&dataFinal=31/03/2025', $urls[0]);
        $this->assertSame([
            ['competencia' => '2025-01-01', 'valor' => '0.16'],
            ['competencia' => '2025-03-01', 'valor' => '0.56'],
        ], $serie);
    }

    public function testRepeteEmErroDeServidorEConsegue(): void
    {
        $chamadas = 0;
        $cliente = new SgsClient(function () use (&$chamadas) {
            return ++$chamadas < 3 ? ['status' => 503, 'body' => ''] : ['status' => 200, 'body' => '[]'];
        }, 3, 0);

        [$de, $ate] = $this->periodo();

        $this->assertSame([], $cliente->serie(433, $de, $ate));
        $this->assertSame(3, $chamadas);
    }

    public function testNaoRepeteErroDoPedido(): void
    {
        $chamadas = 0;
        $cliente = new SgsClient(function () use (&$chamadas) {
            $chamadas++;

            return ['status' => 404, 'body' => ''];
        }, 3, 0);

        [$de, $ate] = $this->periodo();

        try {
            $cliente->serie(999, $de, $ate);
            $this->fail('Deveria falhar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 404', $e->getMessage());
        }
        $this->assertSame(1, $chamadas);
    }

    public function testFalhaDeRedeEsgotaTentativas(): void
    {
        [$de, $ate] = $this->periodo();
        $cliente = new SgsClient(static function () {
            throw new RuntimeException('timeout');
        }, 2, 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timeout');
        $cliente->serie(433, $de, $ate);
    }

    public function testRespostaInvalidaEhErro(): void
    {
        [$de, $ate] = $this->periodo();
        $cliente = new SgsClient(static fn () => ['status' => 200, 'body' => 'não é json'], 1, 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inválida');
        $cliente->serie(433, $de, $ate);
    }
}
