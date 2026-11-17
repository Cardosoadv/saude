<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinanceiroTransferencias extends Migration
{
    public function up()
    {
        // fin_transferencias
        $this->forge->addField([
            'id_transferencia'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'transferencia'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'data_transferencia' => ['type' => 'DATE'],
            'id_conta_origem'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'id_conta_destino'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'valor'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'comentarios'        => ['type' => 'TEXT', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_transferencia');
        $this->forge->addForeignKey('id_conta_origem', 'fin_contas', 'id_conta', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_conta_destino', 'fin_contas', 'id_conta', 'CASCADE', 'CASCADE');
        $this->forge->createTable('fin_transferencias', true);

        // fin_transferencias_rateios
        $this->forge->addField([
            'id_rateio'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'transferencia_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'valor'            => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ]);
        $this->forge->addPrimaryKey('id_rateio');
        $this->forge->addForeignKey('transferencia_id', 'fin_transferencias', 'id_transferencia', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_transferencias_rateios', true);
    }

    public function down()
    {
        $this->forge->dropTable('fin_transferencias_rateios', true);
        $this->forge->dropTable('fin_transferencias', true);
    }
}
