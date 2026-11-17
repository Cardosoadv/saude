<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Link assinado e com validade para pré-visualizar uma página em rascunho.
 * Quem emite (endpoint autenticado da #648) controla quem recebe o link; aqui
 * só se garante que ele não pode ser forjado nem reaproveitado em outra página.
 */
final class PreviewToken
{
    public function __construct(private ?string $segredo = null)
    {
        $this->segredo ??= (string) config('Encryption')->key;
    }

    public function gerar(int $paginaId, int $validadeSegundos = 3600, ?int $agora = null): string
    {
        if ($this->segredo === '') {
            throw new \RuntimeException('Chave de criptografia não configurada (encryption.key).');
        }
        $corpo = $paginaId . '.' . (($agora ?? time()) + $validadeSegundos);

        return $corpo . '.' . $this->assinatura($corpo);
    }

    public function valido(string $token, int $paginaId, ?int $agora = null): bool
    {
        if ($this->segredo === '' || substr_count($token, '.') !== 2) {
            return false;
        }
        [$id, $expira, $assinatura] = explode('.', $token);

        return ctype_digit($id) && ctype_digit($expira)
            && (int) $id === $paginaId
            && (int) $expira >= ($agora ?? time())
            && hash_equals($this->assinatura("{$id}.{$expira}"), $assinatura);
    }

    private function assinatura(string $corpo): string
    {
        return hash_hmac('sha256', 'site-preview|' . $corpo, (string) $this->segredo);
    }
}
