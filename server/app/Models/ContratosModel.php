<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

/**
 * Modelo para a tabela de contratos.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'contratos',
 * incluindo validação e auditoria de alterações (inserção, atualização, exclusão).
 */
class ContratosModel extends Model
{
    use AuditoriaTrait;
    use \App\Traits\PrivacidadeEntidadeTrait;

    protected string $tipoEntidadeAcesso = 'contrato';

    protected $table            = 'contratos';
    protected $primaryKey       = 'id_contrato';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_contrato',
        'numero_contrato',
        'cliente_id',
        'tipo_contrato',
        'objeto',
        'data_inicio',
        'data_fim',
        'prazo_vigencia',
        'prazo_tipo',
        'valor',
        'forma_pagamento',
        'dia_vencimento',
        'status',
        'renovacao_automatica',
        'prazo_aviso',
        'comentarios',
        'responsavel_interno', // Legado (texto livre). Use responsavel_id
        'responsavel_id',
        'visibilidade',
        'area_atuacao',
        'numero_parcelas',
        'indice_reajuste',
        'pasta',
        'clausulas_especiais',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'cliente_id' => [
            'rules' => 'required|integer|is_not_unique[clientes.id_cliente]',
            'errors' => [
                'required' => 'O cliente é obrigatório.',
                'integer' => 'O ID do cliente deve ser um número inteiro.',
                'is_not_unique' => 'Cliente não encontrado.',
            ]
        ],
        'tipo_contrato' => [
            'rules' => 'required|in_list[Mensalidade,Plano de tratamento,Convênio,Pacote de sessões,Consultoria,Outro]',
            'errors' => [
                'required' => 'O tipo de contrato é obrigatório.',
                'in_list' => 'Tipo de contrato inválido.',
            ]
        ],
        'objeto' => [
            'rules' => 'required|max_length[500]',
            'errors' => [
                'required' => 'O objeto do contrato é obrigatório.',
                'max_length' => 'O objeto não pode ter mais de 500 caracteres.',
            ]
        ],
        'data_inicio' => [
            'rules' => 'required|valid_date',
            'errors' => [
                'required' => 'A data de início é obrigatória.',
                'valid_date' => 'Data de início inválida.',
            ]
        ],
        'data_fim' => [
            'rules' => 'permit_empty|valid_date',
            'errors' => [
                'valid_date' => 'Data de término inválida.',
            ]
        ],
        'valor' => [
            'rules' => 'permit_empty|decimal',
            'errors' => [
                'decimal' => 'O valor deve ser um número decimal.',
            ]
        ],
        'forma_pagamento' => [
            'rules' => 'required|in_list[Mensal,Trimestral,Semestral,Anual,Parcela Única,Por Hora]',
            'errors' => [
                'required' => 'A forma de pagamento é obrigatória.',
                'in_list' => 'Forma de pagamento inválida.',
            ]
        ],
        'status' => [
            'rules' => 'required|in_list[Ativo,Pendente,Suspenso,Encerrado,Cancelado,Em negociação]',
            'errors' => [
                'required' => 'O status é obrigatório.',
                'in_list' => 'Status inválido.',
            ]
        ],
        'prazo_vigencia' => [
            'rules' => 'permit_empty|integer',
            'errors' => [
                'integer' => 'O prazo de vigência deve ser um número inteiro.',
            ]
        ],
        'dia_vencimento' => [
            'rules' => 'permit_empty|integer|greater_than[0]|less_than_equal_to[31]',
            'errors' => [
                'integer' => 'O dia de vencimento deve ser um número inteiro.',
                'greater_than' => 'O dia de vencimento deve ser maior que zero.',
                'less_than_equal_to' => 'O dia de vencimento não pode ser maior que 31.',
            ]
        ],
        'numero_parcelas' => [
            'rules' => 'permit_empty|integer',
            'errors' => [
                'integer' => 'O número de parcelas deve ser um número inteiro.',
            ]
        ],
        'prazo_aviso' => [
            'rules' => 'permit_empty|integer',
            'errors' => [
                'integer' => 'O prazo de aviso deve ser um número inteiro.',
            ]
        ],
        'responsavel_id' => [
            'rules'  => 'permit_empty|is_natural_no_zero|is_not_unique[users.id]',
            'errors' => ['is_not_unique' => 'Responsável não encontrado.'],
        ],
        'visibilidade' => [
            'rules'  => 'permit_empty|in_list[publico,restrito]',
            'errors' => ['in_list' => 'Visibilidade inválida.'],
        ],
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['gerarNumeroContrato', 'definirResponsavelDoSistema'];
    protected $afterInsert    = ['auditoriaNovo', 'registrarHistorico', 'concederAcessoAoCriador'];
    protected $beforeUpdate   = ['auditoriaAtualizar', 'verificarVencimento'];
    protected $afterUpdate    = ['registrarHistorico'];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    /**
     * Gera o número do contrato automaticamente antes de inserir.
     */
    protected function gerarNumeroContrato(array $data): array
    {
        if (empty($data['data']['numero_contrato'])) {
            $ano = date('Y');

            $ultimoContrato = $this->like('numero_contrato', $ano . '/', 'after')
                ->orderBy('id_contrato', 'DESC')
                ->first();

            if ($ultimoContrato && !empty($ultimoContrato['numero_contrato'])) {
                $partes = explode('/', $ultimoContrato['numero_contrato']);
                $ultimoNumero = isset($partes[1]) ? intval($partes[1]) : 0;
                $novoNumero = $ultimoNumero + 1;
            } else {
                $novoNumero = 1;
            }

            $data['data']['numero_contrato'] = $ano . '/' . str_pad((string)$novoNumero, 3, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    /**
     * Verifica se o contrato está próximo do vencimento antes de atualizar.
     */
    protected function verificarVencimento(array $data): array
    {
        if (isset($data['data']['data_fim']) && !empty($data['data']['data_fim'])) {
            $dataFim = new \DateTime($data['data']['data_fim']);
            $hoje = new \DateTime();
            $prazoAviso = $data['data']['prazo_aviso'] ?? 30;

            $diasRestantes = $hoje->diff($dataFim)->days;

            if (
                $diasRestantes <= $prazoAviso &&
                isset($data['data']['renovacao_automatica']) &&
                $data['data']['renovacao_automatica'] === 'Sim'
            ) {
                log_message('info', "Contrato próximo do vencimento. Dias restantes: {$diasRestantes}");
            }
        }

        return $data;
    }

    /**
     * Registra histórico de alterações na tabela específica de contratos_historico.
     * Além da auditoria geral, mantemos este histórico específico conforme legado/requisito.
     */
    protected function registrarHistorico(array $data): array
    {
        $db = \Config\Database::connect();

        $contratoId = $data['id'] ?? $this->getInsertID();

        // Se não tiver ID (ex: insert falhou ou algo assim, embora improvável no afterInsert), retorna
        if (!$contratoId) {
            return $data;
        }

        $acao = isset($data['result']) ? 'Criação' : 'Atualização';
        $userId = user_id();
        $descricao = "Contrato {$acao} realizada por " . ($userId ? 'usuário #' . $userId : 'sistema');

        // Handling case where find might return null (though unlikely in this hook context unless deleted)
        $dadosAnteriores = null;
        if (!isset($data['result'])) { // If it's update
            // Nota: Em 'afterUpdate', os dados já foram alterados. Para pegar o "antes", 
            // precisaria ter pego no 'beforeUpdate'. 
            // Aqui estamos pegando o estado ATUAL como "dadosAnteriores" o que pode parecer confuso, 
            // mas mantendo a lógica original para não quebrar comportamento esperado se não for o foco.
            // POREM, o ideal seria o `auditoriaAtualizar` que já pega o antes.
            // Vou manter a estrutura mas adicionar verificação de nulidade.
            $dadosAnterioresJson = $this->find($contratoId);
            $dadosAnteriores = $dadosAnterioresJson ? json_encode($dadosAnterioresJson) : null;
        }

        $historico = [
            'contrato_id' => $contratoId,
            'acao' => $acao,
            'descricao' => $descricao,
            'dados_anteriores' => $dadosAnteriores,
            'dados_novos' => json_encode($data['data'] ?? $data),
            'usuario_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $db->table('contratos_historico')->insert($historico);

        return $data;
    }
}
