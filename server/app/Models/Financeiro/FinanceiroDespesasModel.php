<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Modelo para a tabela de despesas financeiras.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_despesas',
 * incluindo validação, manipulação de rateio (JSON), e métodos para contar e listar
 * despesas não pagas.
 */
class FinanceiroDespesasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_despesas';
    protected $primaryKey       = 'id_despesa';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'despesa',
        'vencimento_dt',
        'valor',
        'fornecedor',
        'comentario',
        'rateio',
        'contrato_id',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'despesa'       => 'required|string|max_length[255]',
        'vencimento_dt' => 'required|valid_date',
        'valor'         => 'required|numeric|greater_than[0]',
        'fornecedor'    => 'required|integer', // ID do fornecedor
        'comentario'    => 'permit_empty|string',
        'rateio'        => 'permit_empty',
    ];

    protected $validationMessages = [
        'despesa' => [
            'required'   => 'O campo despesa é obrigatório',
            'max_length' => 'O campo despesa não pode ter mais que 255 caracteres',
        ],
        'valor' => [
            'required'     => 'O valor é obrigatório',
            'numeric'      => 'O valor deve ser numérico',
            'greater_than' => 'O valor deve ser maior que zero',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['preparaSetRateio'];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['preparaSetRateio', 'auditoriaAtualizar'];
    protected $afterUpdate    = [];
    protected $beforeFind     = ['preparaGetRateio'];
    protected $afterFind      = ['preparaGetRateio'];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    /**
     * Prepara o campo 'rateio' ao recuperar dados, decodificando JSON.
     *
     * @param array $data O array de dados do banco.
     * @return array O array de dados processado.
     */
    protected function preparaGetRateio(array $data): array
    {
        if (!isset($data['data'])) {
            return $data;
        }

        if (is_array($data['data'])) {
            foreach ($data['data'] as &$row) {
                if (isset($row['rateio'])) {
                    try {
                        $decodedRateio = json_decode((string)$row['rateio'], true);
                        $row['rateio'] = $decodedRateio === null ? [] : $decodedRateio;
                    } catch (\Exception $e) {
                        log_message('error', 'Error decoding rateio JSON: ' . $e->getMessage());
                        $row['rateio'] = [];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Prepara o campo 'rateio' para armazenamento, codificando em JSON.
     *
     * @param array $data O array de dados a ser salvo.
     * @return array O array de dados processado.
     * @throws InvalidArgumentException Se o rateio for inválido.
     */
    protected function preparaSetRateio(array $data): array
    {
        if (!isset($data['data']['rateio'])) {
            return $data;
        }

        $rateio = $data['data']['rateio'];

        if (is_array($rateio)) {
            try {
                // Validate rateio structure if needed
                $this->validateRateioStructure($rateio);

                $data['data']['rateio'] = json_encode($rateio, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidArgumentException('Invalid rateio data structure: ' . $e->getMessage());
            }
        } else {
            $data['data']['rateio'] = null;
        }

        return $data;
    }

    /**
     * Valida a estrutura do array de rateio.
     *
     * @param array $rateio Os dados de rateio a validar.
     * @throws InvalidArgumentException Se a estrutura for inválida.
     */
    private function validateRateioStructure(array $rateio): void
    {
        foreach ($rateio as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Each rateio item must be an array');
            }

            if (!isset($item['valor']) || !is_numeric($item['valor'])) {
                throw new InvalidArgumentException('Each rateio item must have a numeric valor');
            }
        }
    }

    /**
     * Calcula o valor total de um rateio.
     *
     * @param array $rateio O array de rateio.
     * @return float O valor total.
     */
    public function calculateTotal(array $rateio): float
    {
        return array_reduce($rateio, function ($carry, $item) {
            return $carry + (float)($item['valor'] ?? 0);
        }, 0.0);
    }
}
