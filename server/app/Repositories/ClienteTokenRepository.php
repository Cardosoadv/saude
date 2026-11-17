<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ClienteTokenModel;

/**
 * Repository para tokens de magic link do cliente.
 *
 * Gerencia a criação, validação e revogação de tokens
 * utilizados na autenticação da Área do Cliente.
 *
 * Não usa cache versionado — tokens são dados voláteis por natureza.
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteTokenRepository extends BaseRepository
{
    /**
     * Construtor com injeção de dependência.
     *
     * @param ClienteTokenModel|null $model
     */
    public function __construct(?ClienteTokenModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? model('ClienteTokenModel');
        $this->cacheKey = null; // Sem cache — tokens são dados de segurança voláteis
    }

    /**
     * Gera e persiste um token de magic link para o cliente.
     *
     * O token bruto é retornado para envio por email.
     * Apenas o hash SHA-256 é armazenado no banco.
     *
     * @param int $clienteId ID do cliente.
     * @param int $ttlHoras  Tempo de vida em horas (padrão: 24).
     * @return string Token bruto (para envio por email).
     */
    public function gerarToken(int $clienteId, int $ttlHoras = 24): string
    {
        // Revoga tokens anteriores antes de gerar novo
        $this->revogarTokensAntigos($clienteId);

        $tokenBruto = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $tokenBruto);
        $expiresAt  = date('Y-m-d H:i:s', strtotime("+{$ttlHoras} hours"));

        $request = service('request');

        $this->model->insert([
            'cliente_id' => $clienteId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => substr($request->getUserAgent()->getAgentString(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $tokenBruto;
    }

    /**
     * Valida um token bruto recebido da URL.
     *
     * Verifica: hash correspondente, não expirado, não usado.
     * Marca como usado (idempotente) em caso de sucesso.
     *
     * @param string $tokenBruto Token bruto recebido via URL.
     * @return array|null Dados do registro ou null se inválido.
     */
    public function validarToken(string $tokenBruto): ?array
    {
        $tokenHash = hash('sha256', $tokenBruto);

        $registro = $this->model
            ->where('token_hash', $tokenHash)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->where('used_at IS NULL', null, false)
            ->first();

        if (!$registro) {
            return null;
        }

        // Marca como usado (uso único)
        $this->model->update($registro['id'], [
            'used_at' => date('Y-m-d H:i:s'),
        ]);

        return $registro;
    }

    /**
     * Revoga todos os tokens ativos anteriores de um cliente.
     *
     * Chamado antes de gerar um novo token para evitar acúmulo.
     *
     * @param int $clienteId ID do cliente.
     */
    public function revogarTokensAntigos(int $clienteId): void
    {
        $this->model
            ->where('cliente_id', $clienteId)
            ->where('used_at IS NULL', null, false)
            ->set(['used_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Remove tokens expirados da tabela (limpeza periódica).
     *
     * @return int Número de tokens removidos.
     */
    public function limparTokensExpirados(): int
    {
        return $this->model
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->delete();
    }
}
