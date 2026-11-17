<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

/** Base64 "URL-safe" sem preenchimento, formato usado pelo Web Push (RFC 7515/8291/8292). */
final class Base64Url
{
    public static function codificar(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decodificar(string $texto): string
    {
        $texto = strtr(trim($texto), '-_', '+/');
        $resto = strlen($texto) % 4;
        if ($resto !== 0) {
            $texto .= str_repeat('=', 4 - $resto);
        }

        $bytes = base64_decode($texto, true);
        if ($bytes === false) {
            throw new WebPushException('Valor base64url inválido.');
        }

        return $bytes;
    }
}
