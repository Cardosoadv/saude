<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGestao extends Migration
{
    public function up()
    {
        // modulos
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => false],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'ativo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('modulos', true);

        // business_canvas
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['active', 'archived'], 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('business_canvas', true);

        // business_canvas_blocks
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'canvas_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'block_type'     => ['type' => 'ENUM', 'constraint' => ['key_partners', 'key_activities', 'key_resources', 'value_proposition', 'customer_relationships', 'channels', 'customer_segments', 'cost_structure', 'revenue_streams']],
            'content'        => ['type' => 'TEXT', 'null' => true],
            'order_position' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('canvas_id', 'business_canvas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('business_canvas_blocks', true);

        // configuracoes
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'valor'      => ['type' => 'TEXT', 'null' => true],
            'descricao'  => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nome', 'idx_unique_config_nome');
        $this->forge->createTable('configuracoes', true);

        // settings
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'],
            'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('settings', true);
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
        $this->forge->dropTable('configuracoes', true);
        $this->forge->dropTable('business_canvas_blocks', true);
        $this->forge->dropTable('business_canvas', true);
        $this->forge->dropTable('modulos', true);
    }
}
