<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de favoritos de receitas e despesas.
 */
class FinanceiroFavoritosModel extends Model
{
    protected $table            = 'fin_favoritos';
    protected $primaryKey       = 'id_favorito';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    
    protected $allowedFields    = [
        'titulo',
        'tipo',
        'valor',
        'categoria_id',
        'fornecedor_id',
        'cliente_id',
        'comentario',
        'rateio',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'titulo'       => 'required|string|max_length[255]',
        'tipo'         => 'required|in_list[despesa,receita]',
        'valor'        => 'permit_empty|numeric',
        'categoria_id' => 'permit_empty|integer',
        'fornecedor_id'=> 'permit_empty|integer',
        'cliente_id'   => 'permit_empty|integer',
        'comentario'   => 'permit_empty|string',
        'rateio'       => 'permit_empty',
    ];

    protected $validationMessages = [
        'titulo' => [
            'required'   => 'O campo título é obrigatório',
            'max_length' => 'O campo título não pode ter mais que 255 caracteres',
        ],
        'tipo' => [
            'required' => 'O tipo do favorito é obrigatório',
            'in_list'  => 'O tipo deve ser despesa ou receita',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['preparaSetRateio'];
    protected $beforeUpdate   = ['preparaSetRateio'];
    protected $beforeFind     = ['preparaGetRateio'];
    protected $afterFind      = ['preparaGetRateio'];

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
                if (isset($row['rateio']) && !empty($row['rateio'])) {
                    try {
                        $decodedRateio = json_decode((string)$row['rateio'], true);
                        $row['rateio'] = $decodedRateio === null ? [] : $decodedRateio;
                    } catch (\Exception $e) {
                        log_message('error', 'Error decoding rateio JSON: ' . $e->getMessage());
                        $row['rateio'] = [];
                    }
                } else if (isset($row['rateio'])) {
                    $row['rateio'] = [];
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
     */
    protected function preparaSetRateio(array $data): array
    {
        if (!isset($data['data']['rateio'])) {
            return $data;
        }

        $rateio = $data['data']['rateio'];

        if (is_array($rateio)) {
            try {
                $data['data']['rateio'] = json_encode($rateio, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException('Invalid rateio data structure: ' . $e->getMessage());
            }
        } else {
            $data['data']['rateio'] = null;
        }

        return $data;
    }
}
