<?php

declare(strict_types=1);

namespace App\Models\Mcp;

use CodeIgniter\Model;

/**
 * Model para códigos de autorização OAuth 2.1 (RFC 6749 + PKCE/RFC 7636) de
 * uso único, emitidos em /oauth/authorize (tela de consentimento) e
 * trocados por um Personal Access Token do Shield em /oauth/token.
 */
class OAuthCodeModel extends Model
{
    protected $table            = 'mcp_oauth_codes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code_hash',
        'client_id',
        'usuario_id',
        'redirect_uri',
        'code_challenge',
        'code_challenge_method',
        'scope',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'code_hash'      => 'required|max_length[64]',
        'client_id'      => 'required|max_length[64]',
        'usuario_id'     => 'required|integer',
        'redirect_uri'   => 'required',
        'expires_at'     => 'required',
    ];
}
