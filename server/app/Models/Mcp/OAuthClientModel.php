<?php

declare(strict_types=1);

namespace App\Models\Mcp;

use CodeIgniter\Model;

/**
 * Model para clientes OAuth 2.1 que usam o endpoint /mcp como "conector
 * personalizado": registrados dinamicamente (RFC 7591) — tipicamente o
 * Claude.ai, que se autorregistra em /oauth/register na primeira conexão —
 * ou cadastrados manualmente por um usuário (usuario_id preenchido), para
 * clientes como o Gemini, que pedem Client ID/Secret prontos.
 */
class OAuthClientModel extends Model
{
    protected $table            = 'mcp_oauth_clients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'client_id',
        'client_secret_hash',
        'client_name',
        'redirect_uris',
        'token_endpoint_auth_method',
        'usuario_id',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'client_id'     => 'required|max_length[64]',
        'redirect_uris' => 'required',
    ];

    /**
     * Exige https, exceto em loopback (testes locais de clientes MCP).
     */
    public static function redirectUriPermitida(string $uri): bool
    {
        $partes = parse_url($uri);
        if ($partes === false || empty($partes['scheme']) || empty($partes['host'])) {
            return false;
        }

        if ($partes['scheme'] === 'https') {
            return true;
        }

        return $partes['scheme'] === 'http' && in_array($partes['host'], ['localhost', '127.0.0.1', '::1'], true);
    }
}
