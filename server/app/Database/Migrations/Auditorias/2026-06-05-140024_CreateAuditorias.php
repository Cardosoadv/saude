<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditorias extends Migration
{
    public function up()
    {
        // auditoria_intimacao
        $this->forge->addField([
            'id'                            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'status_recebimento_intimacao'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'numero_intimacoes_recebidas'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'numero_intimacoes_repetidas'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'numero_intimacoes_processadas' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'nomeArquivo'                   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'usuario_id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'                    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'                    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('usuario_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('auditoria_intimacao', true);

        // auditoria_processo
        $this->forge->addField([
            'id_audit'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'processo_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action_type'   => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE']],
            'dados_novos'   => ['type' => 'JSON', 'null' => true],
            'dados_antigos' => ['type' => 'JSON', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_audit');
        $this->forge->addKey('user_id', false, false, 'idx_audit_proc_user');
        $this->forge->addKey('processo_id', false, false, 'idx_audit_proc_processo');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('processo_id', 'processos', 'id_processo', 'CASCADE', 'CASCADE');
        $this->forge->createTable('auditoria_processo', true);

        // auditoria_cliente
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cliente_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action_type'   => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE']],
            'dados_antigos' => ['type' => 'JSON', 'null' => true],
            'dados_novos'      => ['type' => 'JSON', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id', false, false, 'idx_audit_cli_user');
        $this->forge->addKey('cliente_id', false, false, 'idx_audit_cli_cliente');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'CASCADE', 'CASCADE');
        $this->forge->createTable('auditoria_cliente', true);

        // auditoria
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // Set NULL default is allowed
            'table_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'action_type'   => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE']],
            'dados_antigos' => ['type' => 'JSON', 'null' => true],
            'dados_novos'      => ['type' => 'JSON', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id', false, false, 'idx_auditoria_user_id');
        $this->forge->addKey(['table_name', 'user_id'], false, false, 'idx_auditoria_entidade');
        $this->forge->addKey('deleted_at', false, false, 'idx_auditoria_deleted');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('auditoria', true);
    }

    public function down()
    {
        $this->forge->dropTable('auditoria', true);
        $this->forge->dropTable('auditoria_cliente', true);
        $this->forge->dropTable('auditoria_processo', true);
        $this->forge->dropTable('auditoria_intimacao', true);
    }
}
