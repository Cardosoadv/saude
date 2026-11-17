<?php

declare(strict_types=1);

namespace Tests\Support\WebPush;

use App\Libraries\WebPush\Base64Url;
use App\Libraries\WebPush\ChavesEc;
use RuntimeException;

/**
 * Faz o papel do navegador inscrito: tem as chaves da inscrição e decifra
 * a mensagem (RFC 8291) como o navegador faria.
 */
final class NavegadorFalso
{
    public readonly string $publica;
    public readonly string $privada;
    public readonly string $auth;

    public function __construct(public readonly string $endpoint = 'https://push.exemplo.com/envio/abc123')
    {
        $par           = ChavesEc::gerar();
        $this->publica = $par['publica'];
        $this->privada = $par['privada'];
        $this->auth    = random_bytes(16);
    }

    /** @return array{endpoint: string, keys: array{p256dh: string, auth: string}} */
    public function inscricao(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys'     => ['p256dh' => Base64Url::codificar($this->publica), 'auth' => Base64Url::codificar($this->auth)],
        ];
    }

    public function decifrar(string $corpo): string
    {
        $salt     = substr($corpo, 0, 16);
        $tamChave = ord($corpo[20]);
        $efemera  = substr($corpo, 21, $tamChave);
        $resto    = substr($corpo, 21 + $tamChave);

        $segredo = openssl_pkey_derive(ChavesEc::chavePublica($efemera), ChavesEc::chavePrivada($this->privada, $this->publica), 32);
        $ikm     = hash_hkdf('sha256', $segredo, 32, "WebPush: info\x00" . $this->publica . $efemera, $this->auth);
        $cek     = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce   = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $claro = openssl_decrypt(substr($resto, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($resto, -16));
        if ($claro === false) {
            throw new RuntimeException('Mensagem não pôde ser decifrada.');
        }

        // Remove o preenchimento (0x00) e o delimitador de último registro (0x02).
        $claro = rtrim($claro, "\x00");
        if (!str_ends_with($claro, "\x02")) {
            throw new RuntimeException('Delimitador de registro ausente.');
        }

        return substr($claro, 0, -1);
    }
}
