<?php

namespace App\Database\Migrations\Conhecimento;

use CodeIgniter\Database\Migration;

class CreateArgumentosTables extends Migration
{
    public function up()
    {
        // 1. Tabela de Argumentos (Principal)
        $this->forge->addField([
            'id_argumento' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'estrategia_recomendada' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'teses_relacionadas' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id_argumento', true);
        $this->forge->createTable('argumentos', true);

        // 2. Tabela de Relacionamento (Processo x Argumentos)
        $this->forge->addField([
            'id_processo_argumento' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'processo_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'caso_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'argumento_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'avaliacao' => [
                'type'       => 'ENUM',
                'constraint' => ['like', 'dislike'],
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);

        $this->forge->addKey('id_processo_argumento', true);
        $this->forge->addKey('processo_id');
        $this->forge->addKey('caso_id');
        $this->forge->addKey('argumento_id');

        $this->forge->addForeignKey('processo_id', 'processos', 'id_processo', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('caso_id', 'casos', 'id_caso', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('argumento_id', 'argumentos', 'id_argumento', 'CASCADE', 'CASCADE');

        $this->forge->createTable('processo_argumentos', true);
    }

    public function down()
    {
        $this->forge->dropTable('processo_argumentos', true);
        $this->forge->dropTable('argumentos', true);
    }
}
