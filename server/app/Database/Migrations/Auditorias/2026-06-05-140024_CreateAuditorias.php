<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditorias extends Migration
{
    public function up()
    {
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
    }
}
