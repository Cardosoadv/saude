<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model para a tabela de tokens de magic link do cliente.
 *
 * Armazena hash SHA-256 do token gerado para autenticação sem senha
 * na Área do Cliente. O token bruto nunca é persistido no banco.
 *
 * @package App\Models
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteTokenModel extends Model
{
    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'cliente_tokens';

    /**
     * Chave primária.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Auto increment.
     *
     * @var bool
     */
    protected $useAutoIncrement = true;

    /**
     * Tipo de retorno.
     *
     * @var string
     */
    protected $returnType = 'array';

    /**
     * Soft deletes desativado — token expirado é token inútil.
     *
     * @var bool
     */
    protected $useSoftDeletes = false;

    /**
     * Proteção de campos.
     *
     * @var bool
     */
    protected $protectFields = true;

    /**
     * Campos permitidos para inserção/atualização.
     *
     * @var list<string>
     */
    protected $allowedFields = [
        'cliente_id',
        'token_hash',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * Timestamps gerenciados manualmente (created_at apenas).
     *
     * @var bool
     */
    protected $useTimestamps = false;

    /**
     * Regras de validação.
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'cliente_id' => 'required|integer',
        'token_hash' => 'required|max_length[64]',
        'expires_at' => 'required',
    ];

    /**
     * Mensagens de validação.
     *
     * @var array<string, array<string, string>>
     */
    protected $validationMessages = [];

    /**
     * Pular validação.
     *
     * @var bool
     */
    protected $skipValidation = false;

    /**
     * Callbacks permitidos.
     *
     * @var bool
     */
    protected $allowCallbacks = false;
}
