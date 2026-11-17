<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de contas bancárias.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_contas',
 * armazenando informações sobre contas bancárias, agências e chaves PIX.
 */
class FinanceiroContasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_contas';
    protected $primaryKey       = 'id_conta';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'conta',
        'banco',
        'agencia',
        'numero_conta',
        'pix',
        'comentarios',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'conta' => 'required|string|max_length[50]',
        'banco' => 'permit_empty|string|max_length[50]',
        'agencia' => 'permit_empty|string|max_length[50]',
        'numero_conta' => 'permit_empty|string|max_length[50]',
        'pix' => 'permit_empty|string|max_length[50]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    /**
     * Obtém o nome da conta pelo ID.
     *
     * @param int|string|null $id ID da conta.
     * @return string Nome da conta ou string vazia se não encontrada.
     */
    public function getNomeConta(int|string|null $id = null): string
    {
        if (!$id) {
            return '';
        }
        $conta = $this->find($id);
        return $conta['conta'] ?? '';
    }
}
