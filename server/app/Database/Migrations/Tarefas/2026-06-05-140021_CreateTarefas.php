<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTarefas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_tarefa'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tarefa'            => ['type' => 'TEXT', 'null' => true],
            'prazo'             => ['type' => 'DATE', 'null' => true],
            'prioridade'        => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => false, 'default' => 5],
            'detalhes'          => ['type' => 'TEXT', 'null' => true],
            'responsavel'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'cliente_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'notificar'         => ['type' => 'TINYINT', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_tarefa');
        $this->forge->addKey('cliente_id');
        $this->forge->addKey('deleted_at', false, false, 'idx_tarefas_deleted');
        $this->forge->addKey('responsavel', false, false, 'idx_tarefas_responsavel');
        $this->forge->addKey('status', false, false, 'idx_tarefas_status');
        $this->forge->addKey(['prazo', 'status'], false, false, 'idx_tarefas_prazo');

        $this->forge->addForeignKey('responsavel', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'CASCADE', 'CASCADE');

        $this->forge->createTable('tarefas', true);
    }

    public function down()
    {
        $this->forge->dropTable('tarefas', true);
    }
}
