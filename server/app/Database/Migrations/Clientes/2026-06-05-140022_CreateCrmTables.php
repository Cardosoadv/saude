<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCrmTables extends Migration
{
    public function up()
    {
        // crm_origens
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome' => ['type' => 'VARCHAR', 'constraint' => 100],
            'icone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('crm_origens', true);

        // Add foreign key constraint to Clientes referring to crm_origens
        if ($this->db->getPlatform() !== 'SQLite3') {
            $this->db->query('ALTER TABLE `clientes` ADD CONSTRAINT `clientes_origem_id_foreign` FOREIGN KEY (`origem_id`) REFERENCES `crm_origens`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        // crm_etapas
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome' => ['type' => 'VARCHAR', 'constraint' => 100],
            'cor' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#6c757d'],
            'ordem' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('crm_etapas', true);

        // crm_oportunidades
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cliente_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'titulo' => ['type' => 'VARCHAR', 'constraint' => 255],
            'descricao' => ['type' => 'TEXT', 'null' => true],
            'valor_estimado' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'etapa_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'data_fechamento_esperada' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'aberto'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('etapa_id', 'crm_etapas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('crm_oportunidades', true);

        // crm_interacoes
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cliente_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'outro'],
            'descricao' => ['type' => 'TEXT'],
            'data_interacao' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'CASCADE', 'CASCADE');
        $this->forge->createTable('crm_interacoes', true);
    }

    public function down()
    {
        if ($this->db->getPlatform() !== 'SQLite3') {
            try {
                $this->db->query('ALTER TABLE `clientes` DROP FOREIGN KEY `clientes_origem_id_foreign`');
            } catch (\Throwable $e) {
                // ignore if table/fk doesn't exist
            }
        }
        $this->forge->dropTable('crm_interacoes', true);
        $this->forge->dropTable('crm_oportunidades', true);
        $this->forge->dropTable('crm_etapas', true);
        $this->forge->dropTable('crm_origens', true);
    }
}
