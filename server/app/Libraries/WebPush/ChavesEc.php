<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

use OpenSSLAsymmetricKey;

/**
 * Chaves ECDSA/ECDH na curva P-256 (prime256v1), convertidas entre o formato "cru" do
 * Web Push (ponto não comprimido de 65 bytes e escalar privado de 32 bytes) e o PEM do OpenSSL.
 *
 * As estruturas DER da P-256 têm tamanho fixo, então bastam prefixos constantes.
 */
final class ChavesEc
{
    /** SubjectPublicKeyInfo (id-ecPublicKey + prime256v1) até o BIT STRING do ponto. */
    private const PREFIXO_PUBLICA = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** ECPrivateKey (SEC1) até o escalar privado. */
    private const PREFIXO_PRIVADA = '30770201010420';

    /** Parâmetros (prime256v1) e início da chave pública dentro do ECPrivateKey. */
    private const MEIO_PRIVADA = 'a00a06082a8648ce3d030107a144034200';

    /**
     * Gera um par novo.
     *
     * @return array{publica: string, privada: string} bytes crus (65 e 32)
     */
    public static function gerar(): array
    {
        $chave = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($chave === false) {
            throw new WebPushException('Não foi possível gerar a chave EC: ' . (string) openssl_error_string());
        }

        $ec = openssl_pkey_get_details($chave)['ec'];

        return [
            'publica' => "\x04" . self::pad32($ec['x']) . self::pad32($ec['y']),
            'privada' => self::pad32($ec['d']),
        ];
    }

    public static function publicaPem(string $publicaCrua): string
    {
        self::validarPublica($publicaCrua);

        return self::pem('PUBLIC KEY', hex2bin(self::PREFIXO_PUBLICA) . $publicaCrua);
    }

    public static function privadaPem(string $privadaCrua, string $publicaCrua): string
    {
        self::validarPublica($publicaCrua);
        if (strlen($privadaCrua) !== 32) {
            throw new WebPushException('Chave privada P-256 deve ter 32 bytes.');
        }

        $der = hex2bin(self::PREFIXO_PRIVADA) . $privadaCrua . hex2bin(self::MEIO_PRIVADA) . $publicaCrua;

        return self::pem('EC PRIVATE KEY', $der);
    }

    public static function chavePrivada(string $privadaCrua, string $publicaCrua): OpenSSLAsymmetricKey
    {
        $chave = openssl_pkey_get_private(self::privadaPem($privadaCrua, $publicaCrua));
        if ($chave === false) {
            throw new WebPushException('Chave privada inválida: ' . (string) openssl_error_string());
        }

        return $chave;
    }

    public static function chavePublica(string $publicaCrua): OpenSSLAsymmetricKey
    {
        $chave = openssl_pkey_get_public(self::publicaPem($publicaCrua));
        if ($chave === false) {
            throw new WebPushException('Chave pública inválida (ponto fora da curva P-256?).');
        }

        return $chave;
    }

    private static function validarPublica(string $publicaCrua): void
    {
        if (strlen($publicaCrua) !== 65 || $publicaCrua[0] !== "\x04") {
            throw new WebPushException('Chave pública P-256 deve ser um ponto não comprimido de 65 bytes.');
        }
    }

    private static function pad32(string $bytes): string
    {
        return str_pad(substr($bytes, -32), 32, "\x00", STR_PAD_LEFT);
    }

    private static function pem(string $tipo, string $der): string
    {
        return "-----BEGIN {$tipo}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$tipo}-----\n";
    }
}
