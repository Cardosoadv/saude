<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

/**
 * Identificação do servidor perante o serviço de push (VAPID, RFC 8292):
 * um JWT ES256 assinado com a chave privada da clínica.
 */
final class Vapid
{
    /** Validade do token (o máximo permitido é 24 h). */
    public const VALIDADE_SEGUNDOS = 12 * 3600;

    public function __construct(
        private readonly string $publica,
        private readonly string $privada,
        private readonly string $contato,
    ) {
        if (!preg_match('/^(mailto:|https:\/\/)/', $contato)) {
            throw new WebPushException('O contato VAPID deve começar com "mailto:" ou "https://".');
        }
    }

    public static function deBase64Url(string $publica, string $privada, string $contato): self
    {
        return new self(Base64Url::decodificar($publica), Base64Url::decodificar($privada), $contato);
    }

    public function chavePublicaBase64Url(): string
    {
        return Base64Url::codificar($this->publica);
    }

    /** Valor do cabeçalho Authorization para um endpoint de push. */
    public function autorizacao(string $endpoint, ?int $agora = null): string
    {
        $partes = parse_url($endpoint);
        if (empty($partes['scheme']) || empty($partes['host'])) {
            throw new WebPushException('Endpoint de push inválido.');
        }
        $audiencia = $partes['scheme'] . '://' . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '');

        $cabecalho = Base64Url::codificar(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims    = Base64Url::codificar(json_encode([
            'aud' => $audiencia,
            'exp' => ($agora ?? time()) + self::VALIDADE_SEGUNDOS,
            'sub' => $this->contato,
        ], JSON_UNESCAPED_SLASHES));
        $assinar = $cabecalho . '.' . $claims;

        $der = '';
        if (!openssl_sign($assinar, $der, ChavesEc::chavePrivada($this->privada, $this->publica), OPENSSL_ALGO_SHA256)) {
            throw new WebPushException('Falha ao assinar o token VAPID: ' . (string) openssl_error_string());
        }

        $jwt = $assinar . '.' . Base64Url::codificar(self::derParaRs($der));

        return 'vapid t=' . $jwt . ', k=' . $this->chavePublicaBase64Url();
    }

    /** A assinatura ECDSA do OpenSSL vem em DER (SEQUENCE de dois INTEGER); o JWT exige r||s com 32 bytes cada. */
    public static function derParaRs(string $der): string
    {
        $pos = 0;
        $tamanho = static function () use ($der, &$pos): int {
            $t = ord($der[$pos++] ?? "\x00");
            if ($t & 0x80) {
                $n = $t & 0x7f;
                $t = (int) hexdec(bin2hex(substr($der, $pos, $n)));
                $pos += $n;
            }

            return $t;
        };
        $inteiro = static function () use ($der, &$pos, $tamanho): string {
            if (($der[$pos++] ?? '') !== "\x02") {
                throw new WebPushException('Assinatura DER inesperada.');
            }
            $t = $tamanho();
            $valor = substr($der, $pos, $t);
            $pos += $t;

            return str_pad(ltrim($valor, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };

        if (($der[$pos++] ?? '') !== "\x30") {
            throw new WebPushException('Assinatura DER inesperada.');
        }
        $tamanho();

        return $inteiro() . $inteiro();
    }
}
