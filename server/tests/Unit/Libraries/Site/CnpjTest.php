<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\Cnpj;
use CodeIgniter\Test\CIUnitTestCase;

class CnpjTest extends CIUnitTestCase
{
    public function testValidaDigitosVerificadores(): void
    {
        $this->assertTrue(Cnpj::valido('11.222.333/0001-81'));
        $this->assertTrue(Cnpj::valido('11222333000181'));
        $this->assertFalse(Cnpj::valido('11.222.333/0001-82'), 'dígito errado');
        $this->assertFalse(Cnpj::valido('11111111111111'), 'dígitos repetidos');
        $this->assertFalse(Cnpj::valido('123'), 'tamanho errado');
        $this->assertFalse(Cnpj::valido('abc'));
    }

    public function testDigitosEMascara(): void
    {
        $this->assertSame('11222333000181', Cnpj::digitos('11.222.333/0001-81'));
        $this->assertSame('11.222.333/0001-81', Cnpj::formatar('11222333000181'));
        $this->assertSame('11.222.333/0001-81', Cnpj::formatar('11.222.333/0001-81'));
        $this->assertSame('', Cnpj::formatar(''));
        $this->assertSame('texto livre', Cnpj::formatar(' texto livre '), 'sem 14 dígitos devolve como veio');
    }
}
