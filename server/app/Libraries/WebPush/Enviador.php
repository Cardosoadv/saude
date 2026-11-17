<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

use Closure;
use Throwable;

/**
 * Entrega uma mensagem cifrada ao serviço de push do navegador (RFC 8030).
 */
class Enviador
{
    /** @var Closure(string $url, array<string,string> $cabecalhos, string $corpo): int */
    private Closure $transporte;

    public function __construct(private readonly Vapid $vapid, ?Closure $transporte = null)
    {
        $this->transporte = $transporte ?? self::transporteCurl(...);
    }

    /**
     * @param array{endpoint: string, p256dh: string, auth: string} $inscricao chaves em base64url
     * @param string $urgencia very-low | low | normal | high
     */
    public function enviar(array $inscricao, string $conteudo, int $ttl = 86400, string $urgencia = 'normal', ?string $topico = null): ResultadoEnvio
    {
        $endpoint = (string) $inscricao['endpoint'];
        if (!str_starts_with($endpoint, 'https://')) {
            return new ResultadoEnvio(0, false, 'Endpoint não usa https.');
        }

        try {
            $corpo = Cifrador::cifrar(
                $conteudo,
                Base64Url::decodificar((string) $inscricao['p256dh']),
                Base64Url::decodificar((string) $inscricao['auth'])
            );
        } catch (WebPushException $e) {
            // Chaves da inscrição corrompidas: nunca vai funcionar.
            return new ResultadoEnvio(0, true, $e->getMessage());
        }

        $cabecalhos = [
            'Authorization'    => $this->vapid->autorizacao($endpoint),
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'TTL'              => (string) max(0, $ttl),
            'Urgency'          => $urgencia,
        ];
        if ($topico !== null && $topico !== '') {
            // Até 32 caracteres base64url: uma mensagem nova com o mesmo tópico substitui a pendente.
            $cabecalhos['Topic'] = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $topico), 0, 32);
        }

        try {
            $status = ($this->transporte)($endpoint, $cabecalhos, $corpo);
        } catch (Throwable $e) {
            return new ResultadoEnvio(0, false, $e->getMessage());
        }

        return new ResultadoEnvio($status, in_array($status, [404, 410], true));
    }

    /** @param array<string,string> $cabecalhos */
    private static function transporteCurl(string $url, array $cabecalhos, string $corpo): int
    {
        $resposta = service('curlrequest', ['http_errors' => false, 'timeout' => 15], null, null, false)
            ->post($url, ['headers' => $cabecalhos, 'body' => $corpo]);

        return $resposta->getStatusCode();
    }
}
