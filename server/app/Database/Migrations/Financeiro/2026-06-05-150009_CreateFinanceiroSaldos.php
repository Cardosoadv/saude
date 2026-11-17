<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinanceiroSaldos extends Migration
{
    public function up()
    {
        // fin_saldo_mensal
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tipo'            => ['type' => 'ENUM', 'constraint' => ['CONTA', 'GLOBAL'], 'default' => 'CONTA'],
            'conta_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'ano'             => ['type' => 'INT', 'constraint' => 11],
            'mes'             => ['type' => 'INT', 'constraint' => 11],
            'saldo_acumulado' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'fechado_em'      => ['type' => 'DATETIME', 'null' => true],
            'fechado_por'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['ABERTO', 'FECHADO'], 'default' => 'FECHADO'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('conta_id', 'fin_contas', 'id_conta', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('fechado_por', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_saldo_mensal', true);

        // fin_saldo_mensal_despesas
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ano'        => ['type' => 'INT', 'constraint' => 11],
            'mes'        => ['type' => 'INT', 'constraint' => 11],
            'total'      => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('fin_saldo_mensal_despesas', true);

        // fin_saldo_mensal_rateio
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'saldo_mensal_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'rateio_acumulado' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('saldo_mensal_id', 'fin_saldo_mensal', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('fin_saldo_mensal_rateio', true);

        // fin_pgto_despesas_rateio
        $this->forge->addField([
            'id_rateio'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'pagto_despesa_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'valor'            => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ]);
        $this->forge->addPrimaryKey('id_rateio');
        $this->forge->addForeignKey('pagto_despesa_id', 'fin_pgto_despesas', 'id_pgto_despesa', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_pgto_despesas_rateio', true);

        // fin_pgto_receitas_rateio
        $this->forge->addField([
            'id_rateio'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'pagto_receita_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'valor'            => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ]);
        $this->forge->addPrimaryKey('id_rateio');
        $this->forge->addForeignKey('pagto_receita_id', 'fin_pgto_receitas', 'id_pgto_receita', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_pgto_receitas_rateio', true);
    }

    public function down()
    {
        $this->forge->dropTable('fin_pgto_receitas_rateio', true);
        $this->forge->dropTable('fin_pgto_despesas_rateio', true);
        $this->forge->dropTable('fin_saldo_mensal_rateio', true);
        $this->forge->dropTable('fin_saldo_mensal_despesas', true);
        $this->forge->dropTable('fin_saldo_mensal', true);
    }
}
