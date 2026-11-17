<?php

namespace App\Models;

use App\Libraries\Liquidacao\LiquidacaoDominio;
use CodeIgniter\Model;

class LiquidacoesPagamentosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'liquidacoes_pagamentos';
    protected $primaryKey       = 'pagamento_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'liquidacao_id',
        'numero_parcela',
        'valor_parcela',
        'data_vencimento',
        'data_pagamento',
        'status_pagamento',
        'forma_pagamento',
        'comprovante_url',
        'aviso_enviado_em',
        'lancamentos_financeiros',
        'observacao',
    ];

    // Dates
    protected $useTimestamps = false;

    // Validation
    protected $validationRules      = [
        'liquidacao_id'    => 'required|integer',
        'numero_parcela'   => 'required|integer',
        'valor_parcela'    => 'required|integer',
        'data_vencimento'  => 'required|valid_date[Y-m-d]',
        'data_pagamento'   => 'permit_empty|valid_date[Y-m-d]',
        'status_pagamento' => 'required|in_list[pendente,paga,vencida,cancelada]',
        'forma_pagamento'  => 'permit_empty|max_length[50]',
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

    public function getStatusPagamento(): array
    {
        return LiquidacaoDominio::statusParcela();
    }
}
