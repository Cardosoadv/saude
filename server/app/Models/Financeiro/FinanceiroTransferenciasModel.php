<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de transferências financeiras.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_transferencias',
 * registrando transferências de valores entre contas internas.
 */
class FinanceiroTransferenciasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_transferencias';
    protected $primaryKey       = 'id_transferencia';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'transferencia',
        'data_transferencia',
        'id_conta_origem',
        'id_conta_destino',
        'valor',
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
        'transferencia'      => 'required|string|max_length[255]',
        'data_transferencia' => 'required|valid_date',
        'id_conta_origem'    => 'required|integer',
        'id_conta_destino'   => 'required|integer',
        'valor'              => 'required|numeric',
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
     * Obtém o nome da transferência pelo ID.
     *
     * @param int|string|null $id ID da transferência.
     * @return string Nome da transferência ou string vazia se não encontrada.
     */
    public function getNomeTransferencia(int|string|null $id = null): string
    {
        if (!$id) {
            return '';
        }
        $transferencia = $this->find($id);
        return $transferencia['transferencia'] ?? '';
    }
}
