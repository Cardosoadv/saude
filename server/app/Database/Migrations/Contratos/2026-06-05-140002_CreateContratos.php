<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContratos extends Migration
{
    public function up()
    {
        // contratos
        $this->forge->addField([
            'id_contrato'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'numero_contrato'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'cliente_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'tipo_contrato'        => ['type' => 'ENUM', 'constraint' => ['Mensalidade', 'Plano de tratamento', 'Convênio', 'Pacote de sessões', 'Consultoria', 'Outro'], 'default' => 'Mensalidade'],
            'objeto'               => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'data_inicio'          => ['type' => 'DATE', 'null' => true],
            'data_fim'             => ['type' => 'DATE', 'null' => true],
            'prazo_vigencia'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'prazo_tipo'           => ['type' => 'ENUM', 'constraint' => ['meses', 'anos'], 'default' => 'meses'],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'forma_pagamento'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'dia_vencimento'       => ['type' => 'INT', 'constraint' => 11, 'default' => 10],
            'status'               => ['type' => 'ENUM', 'constraint' => ['Ativo', 'Pendente', 'Suspenso', 'Encerrado', 'Cancelado', 'Em negociação'], 'default' => 'Em negociação'],
            'renovacao_automatica' => ['type' => 'ENUM', 'constraint' => ['Sim', 'Não'], 'default' => 'Não'],
            'prazo_aviso'          => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
            'comentarios'          => ['type' => 'TEXT', 'null' => true],
            'responsavel_interno'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'area_atuacao'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'numero_parcelas'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'indice_reajuste'      => ['type' => 'ENUM', 'constraint' => ['IPCA', 'IGP-M', 'INPC', 'Percentual Fixo', ''], 'null' => true],
            'pasta'                => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'clausulas_especiais'  => ['type' => 'TEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_contrato');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'SET NULL', 'CASCADE');
        $this->forge->addKey('deleted_at', false, false, 'idx_contratos_deleted');
        $this->forge->addKey('status', false, false, 'idx_contratos_status');
        $this->forge->addKey(['data_inicio', 'data_fim'], false, false, 'idx_contratos_datas');
        $this->forge->addKey('responsavel_interno', false, false, 'idx_contratos_responsavel'); // CodeIgniter compile will handle size if specified or we can name it
        $this->forge->createTable('contratos', true);

        // contratos_aditivos
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'contrato_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'numero_aditivo' => ['type' => 'VARCHAR', 'constraint' => 50],
            'type'           => ['type' => 'ENUM', 'constraint' => ['Prazo', 'Valor', 'Escopo', 'Outros']], // Wait, let's keep original 'tipo'
            'tipo'           => ['type' => 'ENUM', 'constraint' => ['Prazo', 'Valor', 'Escopo', 'Outros']],
            'descricao'      => ['type' => 'TEXT'],
            'data_aditivo'   => ['type' => 'DATE'],
            'valor_anterior' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'valor_novo'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'arquivo'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contrato_id', 'contratos', 'id_contrato', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contratos_aditivos', true);

        // contratos_historico
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'contrato_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'acao'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'descricao'        => ['type' => 'TEXT', 'null' => true],
            'dados_anteriores' => ['type' => 'JSON', 'null' => true],
            'dados_novos'      => ['type' => 'JSON', 'null' => true],
            'usuario_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contrato_id', 'contratos', 'id_contrato', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('contratos_historico', true);

        // contratos_renovacoes
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'contrato_original_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'contrato_renovado_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'data_renovacao'       => ['type' => 'DATE'],
            'observacoes'          => ['type' => 'TEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contrato_original_id', 'contratos', 'id_contrato', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contrato_renovado_id', 'contratos', 'id_contrato', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contratos_renovacoes', true);

        // documentos_contratos
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'contrato_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nome'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'tipo'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'categoria'   => ['type' => 'ENUM', 'constraint' => ['Contrato Original', 'Aditivo', 'Anexo', 'Comprovante', 'Outros'], 'default' => 'Outros'],
            'caminho'     => ['type' => 'VARCHAR', 'constraint' => 500],
            'tamanho'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'descricao'   => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('contrato_id', 'contratos', 'id_contrato', 'CASCADE', 'CASCADE');
        $this->forge->createTable('documentos_contratos', true);
    }

    public function down()
    {
        $this->forge->dropTable('documentos_contratos', true);
        $this->forge->dropTable('contratos_renovacoes', true);
        $this->forge->dropTable('contratos_historico', true);
        $this->forge->dropTable('contratos_aditivos', true);
        $this->forge->dropTable('contratos', true);
    }
}
