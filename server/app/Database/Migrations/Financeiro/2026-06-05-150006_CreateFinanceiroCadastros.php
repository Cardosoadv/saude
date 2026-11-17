<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinanceiroCadastros extends Migration
{
    public function up()
    {
        // fin_contas
        $this->forge->addField([
            'id_conta'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'banco'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'agencia'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'numero_conta' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'pix'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'comentarios'  => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_conta');
        $this->forge->createTable('fin_contas', true);

        // fin_fornecedores
        $this->forge->addField([
            'id_fornecedor' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tipo_pessoa'   => ['type' => 'ENUM', 'constraint' => ['F', 'J'], 'default' => 'F'],
            'nome'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'documento'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'telefone'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'endereco'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'numero'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'complemento'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cep'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'cidade'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'uf'            => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'razao_social'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ativo'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'banco'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'agencia'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'conta'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'tipo_conta'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'pix'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_fornecedor');
        $this->forge->createTable('fin_fornecedores', true);

        // fin_elementos
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('fin_elementos', true);

        // fin_categorias
        $this->forge->addField([
            'id_categoria'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'categoria'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'cor'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'comentarios'      => ['type' => 'TEXT', 'null' => true],
            'categoria_pai_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'elemento_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_categoria');
        $this->forge->addForeignKey('categoria_pai_id', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('elemento_id', 'fin_elementos', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_categorias', true);

        // cont_plano_contas
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'codigo'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'nome'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'tipo_conta'        => ['type' => 'ENUM', 'constraint' => ['ATIVO', 'PASSIVO', 'PATRIMONIO_LIQUIDO', 'RECEITA', 'DESPESA']],
            'natureza'          => ['type' => 'ENUM', 'constraint' => ['DEVEDORA', 'CREDORA']],
            'nivel'             => ['type' => 'INT', 'constraint' => 11],
            'conta_pai_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'aceita_lancamento' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'ativo'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('conta_pai_id', 'cont_plano_contas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cont_plano_contas', true);
    }

    public function down()
    {
        $this->forge->dropTable('cont_plano_contas', true);
        $this->forge->dropTable('fin_categorias', true);
        $this->forge->dropTable('fin_elementos', true);
        $this->forge->dropTable('fin_fornecedores', true);
        $this->forge->dropTable('fin_contas', true);
    }
}
