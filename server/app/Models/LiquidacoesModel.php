<?php

namespace App\Models;

use App\Libraries\Liquidacao\LiquidacaoDominio;
use CodeIgniter\Model;

class LiquidacoesModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'liquidacoes';
    protected $primaryKey       = 'liquidacao_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'processo_id',
        'tipo_liquidacao',
        'status',
        'data_liquidacao',
        'data_homologacao',
        'valor_total',
        'observacoes',
        'liquidador_id',
        'data_base',
        'criterio_padrao',
        'calculado_em',
        'lancar_honorarios',
        'honorarios_percentual',
        'contrato_id',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'processo_id'      => 'required|integer',
        'tipo_liquidacao'  => 'required|in_list[por_calculo,por_arbitramento,procedimento_comum]',
        'status'           => 'required|in_list[rascunho,em_calculo,apresentada,impugnada,homologada,em_pagamento,quitada]',
        'data_liquidacao'  => 'permit_empty|valid_date[Y-m-d]',
        'data_homologacao' => 'permit_empty|valid_date[Y-m-d]',
        'valor_total'      => 'permit_empty|integer',
        'data_base'        => 'permit_empty|valid_date[Y-m-d]',
        'lancar_honorarios' => 'permit_empty|in_list[0,1]',
        'honorarios_percentual' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
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

    public function getTiposLiquidacao(): array
    {
        return LiquidacaoDominio::tipos();
    }

    public function getStatusLiquidacao(): array
    {
        return LiquidacaoDominio::status();
    }
}
