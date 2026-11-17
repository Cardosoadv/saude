<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\Decimal;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

class DecimalTest extends CIUnitTestCase
{
    public function testArredondaMeioParaCimaAfastandoDeZero(): void
    {
        $this->assertSame(3, Decimal::centavos('2.5'));
        $this->assertSame(2, Decimal::centavos('2.4999999'));
        $this->assertSame(-3, Decimal::centavos('-2.5'));
        $this->assertSame(0, Decimal::centavos('0.49'));
        $this->assertSame('1.01', Decimal::arredonda('1.005', 2));
    }

    public function testAritmeticaExataSemErroDeFloat(): void
    {
        $this->assertSame('0.300000000000', Decimal::soma('0.1', '0.2'));
        $this->assertSame('1.008300000000', Decimal::fatorDoPercentual('0.83'));
        $this->assertSame(-1, Decimal::cmp('1.0083', '1.0084'));
    }

    public function testAceitaVirgulaERejeitaLixo(): void
    {
        $this->assertSame('0.500000000000', Decimal::soma('0,5', '0'));
        $this->expectException(InvalidArgumentException::class);
        Decimal::soma('abc', '1');
    }
}
