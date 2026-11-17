<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para o rateio de categorias de uma receita (fin_receitas_categorias).
 *
 * Permite que uma mesma receita seja classificada em mais de uma categoria
 * financeira, cada linha representando uma categoria e o valor correspondente
 * a ela dentro do valor total da receita.
 */
class FinanceiroReceitasCategoriasModel extends Model
{
    protected $table            = 'fin_receitas_categorias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'receita_id',
        'categoria_id',
        'valor',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'receita_id'   => 'required|integer',
        'categoria_id' => 'required|integer',
        'valor'        => 'required|numeric|greater_than[0]',
    ];

    protected $validationMessages = [
        'categoria_id' => [
            'required' => 'Cada item do rateio precisa de uma categoria.',
        ],
        'valor' => [
            'required'     => 'Cada item do rateio precisa de um valor.',
            'greater_than' => 'O valor de cada categoria do rateio deve ser maior que zero.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
}
