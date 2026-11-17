<?php

namespace App\Models;

use App\Libraries\Liquidacao\LiquidacaoDominio;
use CodeIgniter\Model;

class ItensLiquidacaoModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'itens_liquidacao';
    protected $primaryKey       = 'item_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'liquidacao_id',
        'tipo_item',
        'descricao',
        'base_calculo',
        'periodo_inicio',
        'periodo_fim',
        'data_inicio',
        'data_fim',
        'taxa',
        'valor_base',
        'valor_calculado',
        'fundamento_legal',
        'criterio',
        'memoria_calculo',
    ];

    // Dates
    protected $useTimestamps = false; // No timestamps on items per schema, or check if needed
    // Schema didn't specify created_at/updated_at for items, only 'created_at' for liquidacoes. 
    // Usually good to have but following strict schema from user request.

    // Validation
    protected $validationRules      = [
        'liquidacao_id' => 'required|integer',
        'tipo_item'     => 'required|in_list[principal,juros,multa,honorarios,custas,outros]',
        'descricao'     => 'required',
        'data_inicio'   => 'permit_empty|valid_date[Y-m-d]',
        'data_fim'      => 'permit_empty|valid_date[Y-m-d]',
        'periodo_inicio' => 'permit_empty|valid_date[Y-m-d]',
        'periodo_fim'    => 'permit_empty|valid_date[Y-m-d]',
        'valor_base'     => 'permit_empty|integer',
        'valor_calculado' => 'permit_empty|integer',
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
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    public function getTiposItem(): array
    {
        return LiquidacaoDominio::tiposItem();
    }
}
