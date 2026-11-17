<?php

namespace Tests\Unit\Services\Financeiro;

use App\Services\Financeiro\ChaveMovimentoExtrato as Chave;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * Chave de idempotência dos movimentos de extrato. Só dados fictícios.
 */
class ChaveMovimentoExtratoTest extends CIUnitTestCase
{
    public function testMesmaEntradaGeraMesmaChave(): void
    {
        $a = Chave::gerar('999', '0001', '12345-6', 'FIT-0001', '2026-10-05', '-10.50', 'Pix enviado');
        $b = Chave::gerar('999', '0001', '12345-6', 'FIT-0001', '2026-10-05', '-10.50', 'Pix enviado');

        $this->assertSame($a, $b);
        $this->assertSame(Chave::TIPO_FITID, $a['chave_tipo']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a['chave']);
    }

    public function testChaveComFitidSegueFormulaDocumentada(): void
    {
        $esperada = hash('sha256', '999|1|123456|FIT-0001');

        $this->assertSame($esperada, Chave::chavePorFitid('0999', '0001', '12345-6', 'FIT-0001'));
    }

    public function testFitidIgualEmContasDiferentesGeraChavesDiferentes(): void
    {
        $contaA = Chave::chavePorFitid('999', '0001', '11111-1', 'FIT-0001');
        $contaB = Chave::chavePorFitid('999', '0001', '22222-2', 'FIT-0001');
        $outroBanco = Chave::chavePorFitid('888', '0001', '11111-1', 'FIT-0001');

        $this->assertNotSame($contaA, $contaB);
        $this->assertNotSame($contaA, $outroBanco);
    }

    public function testFormatoDoIdentificadorNaoAlteraAChave(): void
    {
        $this->assertSame(
            Chave::chavePorFitid('999', '1', '123456', 'X1'),
            Chave::chavePorFitid('0999', '0001', '0012345-6', 'X1')
        );
    }

    public function testChecknumERefnumNaoFazemParteDaChave(): void
    {
        // A assinatura sequer aceita esses campos: a chave depende só de conta + FITID.
        $this->assertSame(
            Chave::gerar('999', '1', '123456', 'X1', '2026-10-05', '1.00', 'a'),
            Chave::gerar('999', '1', '123456', 'X1', '2026-10-06', '9.99', 'outro memo')
        );
    }

    /**
     * @dataProvider fitidInvalidos
     */
    public function testFitidInvalidoOuVazioUsaFallbackPorHash(?string $fitid): void
    {
        $r = Chave::gerar('999', '1', '123456', $fitid, '2026-10-05', '-10.50', 'Pix enviado');

        $this->assertSame(Chave::TIPO_HASH, $r['chave_tipo']);
        $this->assertSame(
            Chave::chaveFallback('999', '1', '123456', '2026-10-05', -1050, 'Pix enviado', 1),
            $r['chave']
        );
    }

    public static function fitidInvalidos(): array
    {
        return [
            'nulo'          => [null],
            'vazio'         => [''],
            'so espacos'    => ['   '],
            'controle'      => ["AB\x00CD"],
            'muito grande'  => [str_repeat('A', 256)],
        ];
    }

    public function testItensIdenticosNoMesmoDiaSemFitidTemChavesDiferentesPorN(): void
    {
        $lote = Chave::gerarLote('999', '1', '123456', [
            ['fitid' => null, 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
            ['fitid' => '',   'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'PIX  enviado'],
            ['fitid' => null, 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
            ['fitid' => null, 'data' => '2026-10-06', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
        ]);

        $chaves = array_column($lote, 'chave');
        $this->assertCount(4, array_unique($chaves));
        $this->assertSame(Chave::TIPO_HASH, $lote[0]['chave_tipo']);

        // O mesmo arquivo processado de novo gera exatamente as mesmas chaves.
        $de_novo = Chave::gerarLote('999', '1', '123456', [
            ['fitid' => null, 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
            ['fitid' => '',   'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'PIX  enviado'],
            ['fitid' => null, 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
            ['fitid' => null, 'data' => '2026-10-06', 'valor' => '-25.00', 'memo' => 'Pix enviado'],
        ]);
        $this->assertSame($chaves, array_column($de_novo, 'chave'));
    }

    public function testGerarLoteMisturaItensComESemFitid(): void
    {
        $lote = Chave::gerarLote('999', '1', '123456', [
            ['fitid' => 'F1', 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'a'],
            ['fitid' => 'F1', 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'a'],
            ['fitid' => null, 'data' => '2026-10-05', 'valor' => '-25.00', 'memo' => 'a'],
        ]);

        $this->assertSame(Chave::TIPO_FITID, $lote[0]['chave_tipo']);
        $this->assertSame(Chave::TIPO_HASH, $lote[2]['chave_tipo']);
        $this->assertSame(
            Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2500, 'a', 1),
            $lote[2]['chave']
        );
    }

    public function testFallbackDependeDeDataValorMemoEConta(): void
    {
        $base = Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2500, 'a', 1);

        $this->assertNotSame($base, Chave::chaveFallback('999', '1', '123456', '2026-10-06', -2500, 'a', 1));
        $this->assertNotSame($base, Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2501, 'a', 1));
        $this->assertNotSame($base, Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2500, 'b', 1));
        $this->assertNotSame($base, Chave::chaveFallback('999', '1', '654321', '2026-10-05', -2500, 'a', 1));
        $this->assertNotSame($base, Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2500, 'a', 2));
    }

    public function testValorComoTextoOuCentavosGeraAMesmaChave(): void
    {
        $this->assertSame(
            Chave::chaveFallback('999', '1', '123456', '2026-10-05', '-25.00', 'a', 1),
            Chave::chaveFallback('999', '1', '123456', '2026-10-05', -2500, 'a', 1)
        );
    }

    public function testNormalizarMemo(): void
    {
        $this->assertSame('pix enviado joao da conceicao', Chave::normalizarMemo("  PIX   Enviado\tJoão da Conceição "));
        $this->assertSame('pix enviado', Chave::normalizarMemo('Pix enviado protocolo 98765432'));
        $this->assertSame('a b', Chave::normalizarMemo('a|b'));
        $this->assertSame('', Chave::normalizarMemo(null));
    }

    public function testNormalizarMemoConverteWindows1252(): void
    {
        $cp1252 = mb_convert_encoding('Cobrança Ação', 'Windows-1252', 'UTF-8');

        $this->assertSame('cobranca acao', Chave::normalizarMemo($cp1252));
    }

    /**
     * @dataProvider valoresEmCentavos
     */
    public function testParaCentavosNuncaUsaFloat(string|int $entrada, int $esperado): void
    {
        $this->assertSame($esperado, Chave::paraCentavos($entrada));
    }

    public static function valoresEmCentavos(): array
    {
        return [
            'negativo'       => ['-10.50', -1050],
            'positivo'       => ['10.5', 1050],
            'virgula'        => ['12,34', 1234],
            'milhar br'      => ['1.234,56', 123456],
            'milhar us'      => ['1,234.56', 123456],
            'inteiro texto'  => ['7', 700],
            'sem inteiro'    => ['.99', 99],
            'imprecisao'     => ['0.29', 29],
            'grande'         => ['12345678901.99', 1234567890199],
            'ja em centavos' => [-1050, -1050],
            'com sinal mais' => ['+3.00', 300],
        ];
    }

    /**
     * @dataProvider valoresInvalidos
     */
    public function testParaCentavosRejeitaValorInvalido(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);
        Chave::paraCentavos($entrada);
    }

    public static function valoresInvalidos(): array
    {
        return [['abc'], [''], ['1.2.3'], ['10.505'], ['--5']];
    }

    public function testDataInvalidaERejeitada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Chave::chaveFallback('999', '1', '123456', '2026-02-30', 100, 'a', 1);
    }

    public function testOrdemDeOcorrenciaComecaEmUm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Chave::chaveFallback('999', '1', '123456', '2026-10-05', 100, 'a', 0);
    }
}
