<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmOportunidadesModel extends Model
{
    protected $table = 'crm_oportunidades';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'cliente_id',
        'titulo',
        'descricao',
        'valor_estimado',
        'etapa_id',
        'data_fechamento_esperada',
        'status',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getOportunidadesComCliente()
    {
        return $this->select('crm_oportunidades.*, clientes.nome as cliente_nome')
            ->join('clientes', 'clientes.id_cliente = crm_oportunidades.cliente_id')
            ->findAll();
    }
}
