<?php

namespace Tests\Unit\Services\Financeiro;

use App\Services\Financeiro\ConferenciaContaExtrato as Conf;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Conferência de agência/conta do cadastro com o OFX. Só dados fictícios.
 */
class ConferenciaContaExtratoTest extends CIUnitTestCase
{
    /**
     * @dataProvider aceitos
     */
    public function testAceita(string $cadastro, string $arquivo): void
    {
        $this->assertTrue(Conf::confere($cadastro, $arquivo));
    }

    public static function aceitos(): array
    {
        return [
            'iguais'                         => ['1234', '1234'],
            'zeros à esquerda'               => ['0001', '1'],
            'pontuação'                      => ['12.345-6', '123456'],
            'dv separado no arquivo'         => ['0001', '0001-9'],
            'dv separado no cadastro'        => ['0001-9', '0001'],
            'dv separado nos dois'           => ['0001-9', '1-9'],
            'dv colado no arquivo'           => ['0001', '00019'],
            'dv colado no cadastro'          => ['00019', '0001'],
            'conta com dv só no cadastro'    => ['1234567-0', '12345670'],
            'conta com dv só no arquivo'     => ['1234567', '1234567-0'],
        ];
    }

    /**
     * @dataProvider recusados
     */
    public function testRecusa(?string $cadastro, ?string $arquivo): void
    {
        $this->assertFalse(Conf::confere($cadastro, $arquivo));
    }

    public static function recusados(): array
    {
        return [
            'números diferentes'             => ['1234', '1235'],
            'dv separado, base diferente'    => ['0002', '0001-9'],
            'zero a mais no meio'            => ['123450010', '12345010'],
            'dois dígitos a mais'            => ['0001', '000123'],
            'colado em número curto'         => ['12', '123'],
            'vazio no arquivo'               => ['1234', ''],
            'nulo'                           => [null, null],
        ];
    }
}
