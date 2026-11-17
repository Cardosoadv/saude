<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Support\EntradaMcp;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;

class EntradaMcpTest extends TestCase
{
    public function testDataAceitaFormatoBrasileiroEIso(): void
    {
        $this->assertSame('2026-03-15', EntradaMcp::data('15/03/2026', 'Data'));
        $this->assertSame('2026-03-15', EntradaMcp::data('2026-03-15', 'Data'));
        $this->assertNull(EntradaMcp::data('  ', 'Data'));
    }

    public function testDataInvalidaExplicaOFormato(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('dd/mm/aaaa');

        EntradaMcp::data('31/02/2026', 'Data de início');
    }

    public function testOpcaoIgnoraAcentoECaixa(): void
    {
        $formas = ['Mensal', 'Parcela Única', 'Por Hora'];

        $this->assertSame('Parcela Única', EntradaMcp::opcao('parcela unica', $formas, 'Forma'));
        $this->assertSame('Honorários', EntradaMcp::opcao('HONORARIOS', ['Honorários', 'Êxito'], 'Tipo'));
        $this->assertSame('Êxito', EntradaMcp::opcao('exito', ['Honorários', 'Êxito'], 'Tipo'));
        $this->assertNull(EntradaMcp::opcao(null, $formas, 'Forma'));
    }

    public function testOpcaoInvalidaListaAsAceitas(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Use: Mensal, Anual.');

        EntradaMcp::opcao('quinzenal', ['Mensal', 'Anual'], 'Forma de pagamento');
    }

    public function testTextoAparaELimita(): void
    {
        $this->assertNull(EntradaMcp::texto('   '));
        $this->assertSame('abc', EntradaMcp::texto('  abcdef ', 3));
    }
}
