<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_cliente'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tipo_cliente'    => ['type' => 'ENUM', 'constraint' => ['F', 'J'], 'default' => 'F'],
            'nome'            => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'nacionalidade'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'estado_civil'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'profissao'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'documento'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'unique' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefone'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'endereco'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'numero'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'complemento'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'cep'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'cidade'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'uf'              => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'razao_social'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'ativo'           => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 1],
            'usuario_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'pasta'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'data_nascimento' => ['type' => 'DATE', 'null' => true],
            'dataAquisicao'   => ['type' => 'DATE', 'null' => true],
            'status_crm'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'client', 'null' => true],
            'origem_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'temperatura'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_cliente');
        $this->forge->addKey('nome');
        $this->forge->addKey('deleted_at', false, false, 'idx_clientes_deleted');
        $this->forge->addForeignKey('usuario_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('clientes', true);
    }

    public function down()
    {
        $this->forge->dropTable('clientes', true);
    }
}
