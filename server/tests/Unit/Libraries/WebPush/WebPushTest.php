<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\WebPush;

use App\Controllers\ApiV2\Push;
use App\Libraries\WebPush\Base64Url;
use App\Libraries\WebPush\ChavesEc;
use App\Libraries\WebPush\Cifrador;
use App\Libraries\WebPush\Enviador;
use App\Libraries\WebPush\Vapid;
use App\Libraries\WebPush\WebPushException;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WebPush\NavegadorFalso;

/**
 * Web Push sem dependências externas: criptografia (RFC 8291), VAPID (RFC 8292) e envio.
 */
final class WebPushTest extends CIUnitTestCase
{
    /** Exemplo do Apêndice A da RFC 8291, byte a byte. */
    public function testCifraIgualAoExemploDaRfc8291(): void
    {
        $corpo = Cifrador::cifrar(
            'When I grow up, I want to be a watermelon',
            Base64Url::decodificar('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            Base64Url::decodificar('BTBZMqHH6r4Tts7J_aSIgg'),
            [
                'publica' => Base64Url::decodificar('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'),
                'privada' => Base64Url::decodificar('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'),
            ],
            Base64Url::decodificar('DGv6ra1nlYgDCS1FRnbzlw')
        );

        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            Base64Url::codificar($corpo)
        );
    }

    public function testNavegadorDecifraOConteudo(): void
    {
        $navegador = new NavegadorFalso();
        $corpo     = Cifrador::cifrar('{"title":"Olá, prazo!"}', $navegador->publica, $navegador->auth);

        $this->assertSame('{"title":"Olá, prazo!"}', $navegador->decifrar($corpo));
    }

    public function testRecusaConteudoGrandeDemais(): void
    {
        $navegador = new NavegadorFalso();
        $this->expectException(WebPushException::class);
        Cifrador::cifrar(str_repeat('x', Cifrador::MAXIMO_CONTEUDO + 1), $navegador->publica, $navegador->auth);
    }

    public function testTokenVapidAssinadoComAChaveDoServidor(): void
    {
        $par   = ChavesEc::gerar();
        $vapid = new Vapid($par['publica'], $par['privada'], 'mailto:ti@exemplo.com.br');

        $cabecalho = $vapid->autorizacao('https://fcm.googleapis.com/fcm/send/xyz', 1_800_000_000);
        $this->assertMatchesRegularExpression('/^vapid t=[\w-]+\.[\w-]+\.[\w-]+, k=[\w-]+$/', $cabecalho);

        preg_match('/t=([^,]+), k=(.+)$/', $cabecalho, $m);
        [$h, $c, $assinatura] = explode('.', $m[1]);

        $this->assertSame(Base64Url::codificar($par['publica']), $m[2]);
        $this->assertSame(
            ['aud' => 'https://fcm.googleapis.com', 'exp' => 1_800_000_000 + Vapid::VALIDADE_SEGUNDOS, 'sub' => 'mailto:ti@exemplo.com.br'],
            json_decode(Base64Url::decodificar($c), true)
        );

        $rs = Base64Url::decodificar($assinatura);
        $this->assertSame(64, strlen($rs));
        $this->assertSame(1, openssl_verify("{$h}.{$c}", self::rsParaDer($rs), ChavesEc::publicaPem($par['publica']), OPENSSL_ALGO_SHA256));
    }

    public function testContatoVapidInvalido(): void
    {
        $par = ChavesEc::gerar();
        $this->expectException(WebPushException::class);
        new Vapid($par['publica'], $par['privada'], 'ti@exemplo.com.br');
    }

    public function testEnviadorMontaARequisicaoEInterpretaRespostas(): void
    {
        $navegador = new NavegadorFalso();
        $par       = ChavesEc::gerar();
        $capturado = [];
        $status    = 201;

        $enviador = new Enviador(
            new Vapid($par['publica'], $par['privada'], 'mailto:ti@exemplo.com.br'),
            function (string $url, array $cabecalhos, string $corpo) use (&$capturado, &$status): int {
                $capturado = compact('url', 'cabecalhos', 'corpo');

                return $status;
            }
        );

        $inscricao = ['endpoint' => $navegador->endpoint] + $navegador->inscricao()['keys'];
        $r = $enviador->enviar($inscricao, '{"title":"Teste"}', 3600, 'high', 'prazos');

        $this->assertTrue($r->sucesso());
        $this->assertSame($navegador->endpoint, $capturado['url']);
        $this->assertSame('aes128gcm', $capturado['cabecalhos']['Content-Encoding']);
        $this->assertSame('3600', $capturado['cabecalhos']['TTL']);
        $this->assertSame('high', $capturado['cabecalhos']['Urgency']);
        $this->assertSame('prazos', $capturado['cabecalhos']['Topic']);
        $this->assertStringStartsWith('vapid t=', $capturado['cabecalhos']['Authorization']);
        $this->assertSame('{"title":"Teste"}', $navegador->decifrar($capturado['corpo']));

        $status = 410;
        $r = $enviador->enviar($inscricao, '{}');
        $this->assertFalse($r->sucesso());
        $this->assertTrue($r->expirada, '410 Gone: a inscrição deixou de existir');

        $status = 500;
        $r = $enviador->enviar($inscricao, '{}');
        $this->assertFalse($r->sucesso());
        $this->assertFalse($r->expirada, 'erro temporário não apaga a inscrição');
    }

    public function testEnviadorNaoEnviaParaEndpointSemHttps(): void
    {
        $par      = ChavesEc::gerar();
        $chamado  = false;
        $enviador = new Enviador(new Vapid($par['publica'], $par['privada'], 'mailto:a@b.c'), function () use (&$chamado): int {
            $chamado = true;

            return 201;
        });

        $r = $enviador->enviar(['endpoint' => 'http://push.exemplo.com/x', 'p256dh' => 'x', 'auth' => 'y'], '{}');

        $this->assertFalse($r->sucesso());
        $this->assertFalse($chamado);
    }

    #[DataProvider('endpoints')]
    public function testValidacaoDoEndpointDaInscricao(string $endpoint, bool $valido): void
    {
        $this->assertSame($valido, Push::endpointValido($endpoint));
    }

    public static function endpoints(): array
    {
        return [
            'FCM'              => ['https://fcm.googleapis.com/fcm/send/abc:def', true],
            'Apple'            => ['https://web.push.apple.com/QGx1', true],
            'Mozilla'          => ['https://updates.push.services.mozilla.com/wpush/v2/gAAA', true],
            'http'             => ['http://fcm.googleapis.com/fcm/send/abc', false],
            'localhost'        => ['https://localhost/x', false],
            'IP privado'       => ['https://192.168.0.10/x', false],
            'IP de loopback'   => ['https://127.0.0.1/x', false],
            'texto qualquer'   => ['nao-e-url', false],
        ];
    }

    private static function rsParaDer(string $rs): string
    {
        $inteiro = static function (string $v): string {
            $v = ltrim($v, "\x00");
            if ($v === '' || (ord($v[0]) & 0x80)) {
                $v = "\x00" . $v;
            }

            return "\x02" . chr(strlen($v)) . $v;
        };
        $corpo = $inteiro(substr($rs, 0, 32)) . $inteiro(substr($rs, 32));

        return "\x30" . chr(strlen($corpo)) . $corpo;
    }
}
