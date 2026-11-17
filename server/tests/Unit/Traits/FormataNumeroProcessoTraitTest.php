<?php

namespace Tests\Unit\Traits;

use App\Traits\FormataNumeroProcessoTrait;
use CodeIgniter\Test\CIUnitTestCase;

class FormataNumeroProcessoTraitTest extends CIUnitTestCase
{
    use FormataNumeroProcessoTrait;

    /**
     * @dataProvider nupsValidos
     */
    public function testAceitaNupValidoComOuSemMascara(string $comMascara)
    {
        $this->assertTrue($this->ehNumeroProcessoCnjValido($comMascara));
        $this->assertTrue($this->ehNumeroProcessoCnjValido($this->limparNumeroProcesso($comMascara)));
    }

    /**
     * @dataProvider nupsValidos
     */
    public function testNormalizaNupParaMascaraCnj(string $comMascara)
    {
        $this->assertSame($comMascara, $this->normalizarNumeroProcessoCnj($this->limparNumeroProcesso($comMascara)));
        $this->assertSame($comMascara, $this->normalizarNumeroProcessoCnj($comMascara));
    }

    public function testRejeitaDigitoVerificadorIncorreto()
    {
        $this->assertFalse($this->ehNumeroProcessoCnjValido('0006827-75.2016.8.27.2706'));
        $this->assertNull($this->normalizarNumeroProcessoCnj('0006827-75.2016.8.27.2706'));
    }

    public function testRejeitaTamanhoInvalidoOuVazio()
    {
        foreach (['1990285', '', '   ', null, 'REsp 1990285', '0006827-74.2016.8.27.27060'] as $valor) {
            $this->assertFalse($this->ehNumeroProcessoCnjValido($valor));
            $this->assertNull($this->normalizarNumeroProcessoCnj($valor));
        }
    }

    /**
     * Comportamento já usado pelo ProcessosRepository: não valida o dígito
     * verificador e trunca em 20 dígitos.
     */
    public function testFormatarNumeroProcessoMantemComportamentoOriginal()
    {
        $this->assertSame('0006827-75.2016.8.27.2706', $this->formatarNumeroProcesso('00068277520168272706'));
        $this->assertSame('0006827-74.2016.8.27.2706', $this->formatarNumeroProcesso('0006827-74.2016.8.27.27060'));
        $this->assertNull($this->formatarNumeroProcesso('1990285'));
    }

    public function testEhFormatoNumeroProcessoValido()
    {
        $this->assertTrue($this->ehFormatoNumeroProcessoValido('0006827-74.2016.8.27.2706'));
        $this->assertFalse($this->ehFormatoNumeroProcessoValido('00068277420168272706'));
    }

    public static function nupsValidos(): array
    {
        return [
            'TJTO' => ['0006827-74.2016.8.27.2706'],
            'TJRS' => ['0051235-90.2013.8.21.0001'],
            'TJSP' => ['1502134-44.2018.8.26.0567'],
            'TJSC' => ['5077479-71.2020.8.24.0023'],
        ];
    }
}
