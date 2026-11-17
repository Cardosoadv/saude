<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\PreviewToken;
use CodeIgniter\Test\CIUnitTestCase;

class PreviewTokenTest extends CIUnitTestCase
{
    public function testTokenValidoSoParaAPaginaEEnquantoNaoExpira(): void
    {
        $t = new PreviewToken('segredo');
        $token = $t->gerar(7, 60, 1000);

        $this->assertTrue($t->valido($token, 7, 1000));
        $this->assertTrue($t->valido($token, 7, 1060));
        $this->assertFalse($t->valido($token, 7, 1061), 'expirou');
        $this->assertFalse($t->valido($token, 8, 1000), 'outra página');
    }

    public function testRejeitaTokenAdulteradoOuDeOutroSegredo(): void
    {
        $token = (new PreviewToken('segredo'))->gerar(7, 60, 1000);
        [$id, $expira, $assinatura] = explode('.', $token);

        $this->assertFalse((new PreviewToken('outro'))->valido($token, 7, 1000));
        $this->assertFalse((new PreviewToken('segredo'))->valido("{$id}.9999999999.{$assinatura}", 7, 1000), 'validade estendida');
        foreach (['', 'abc', '7.1060', "{$token}.x", '7.abc.' . $assinatura] as $lixo) {
            $this->assertFalse((new PreviewToken('segredo'))->valido($lixo, 7, 1000), $lixo);
        }
    }

    public function testSemSegredoNaoGeraNemValida(): void
    {
        $t = new PreviewToken('');

        $this->assertFalse($t->valido('1.2.3', 1));
        $this->expectException(\RuntimeException::class);
        $t->gerar(1);
    }
}
