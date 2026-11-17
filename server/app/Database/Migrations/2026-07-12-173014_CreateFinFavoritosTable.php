<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinFavoritosTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_favorito' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['despesa', 'receita'],
            ],
            'valor' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'categoria_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'fornecedor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'cliente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'comentario' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'rateio' => [
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
        
        $this->forge->addKey('id_favorito', true);
        $this->forge->createTable('fin_favoritos');
    }

    public function down()
    {
        $this->forge->dropTable('fin_favoritos');
    }
}
