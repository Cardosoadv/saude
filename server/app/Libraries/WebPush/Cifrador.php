<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

/**
 * Criptografia do conteúdo de uma mensagem Web Push (RFC 8291, codificação "aes128gcm" da RFC 8188).
 *
 * Só o navegador inscrito consegue decifrar: o serviço de push (Google, Apple, Mozilla)
 * transporta a mensagem sem ver o conteúdo.
 */
final class Cifrador
{
    /** Tamanho do registro declarado no cabeçalho. A mensagem inteira vai num registro só. */
    public const TAMANHO_REGISTRO = 4096;

    /** Limite prático do conteúdo (os serviços aceitam 4096 bytes de corpo cifrado). */
    public const MAXIMO_CONTEUDO = 3800;

    /**
     * @param string      $conteudo     texto a enviar (normalmente JSON)
     * @param string      $p256dh       chave pública do navegador (65 bytes crus)
     * @param string      $auth         segredo de autenticação do navegador (16 bytes crus)
     * @param array|null  $efemera      ['publica' => 65 bytes, 'privada' => 32 bytes] — só para testes
     * @param string|null $salt         16 bytes — só para testes
     *
     * @return string corpo binário pronto para o POST (Content-Encoding: aes128gcm)
     */
    public static function cifrar(string $conteudo, string $p256dh, string $auth, ?array $efemera = null, ?string $salt = null): string
    {
        if (strlen($conteudo) > self::MAXIMO_CONTEUDO) {
            throw new WebPushException('Conteúdo da notificação grande demais.');
        }
        if (strlen($auth) !== 16) {
            throw new WebPushException('Segredo de autenticação da inscrição deve ter 16 bytes.');
        }

        $efemera ??= ChavesEc::gerar();
        $salt    ??= random_bytes(16);

        // Segredo compartilhado (ECDH) entre a chave efêmera do servidor e a do navegador.
        $segredo = openssl_pkey_derive(
            ChavesEc::chavePublica($p256dh),
            ChavesEc::chavePrivada($efemera['privada'], $efemera['publica']),
            32
        );
        if ($segredo === false || strlen($segredo) !== 32) {
            throw new WebPushException('Falha no ECDH: ' . (string) openssl_error_string());
        }

        $infoChave = "WebPush: info\x00" . $p256dh . $efemera['publica'];
        $ikm       = hash_hkdf('sha256', $segredo, 32, $infoChave, $auth);
        $cek       = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce     = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // Delimitador 0x02 = último (e único) registro.
        $tag     = '';
        $cifrado = openssl_encrypt($conteudo . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cifrado === false) {
            throw new WebPushException('Falha ao cifrar: ' . (string) openssl_error_string());
        }

        $cabecalho = $salt . pack('N', self::TAMANHO_REGISTRO) . chr(strlen($efemera['publica'])) . $efemera['publica'];

        return $cabecalho . $cifrado . $tag;
    }
}
