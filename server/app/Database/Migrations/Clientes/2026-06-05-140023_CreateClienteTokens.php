<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClienteTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'cliente_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'FK para clientes.id_cliente',
            ],
            'token_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'Hash SHA-256 do token bruto enviado por email',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'used_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'comment' => 'Preenchido após uso único. Token inválido se não nulo.',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
                'default'    => null,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('cliente_id');
        $this->forge->addKey('expires_at');

        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'RESTRICT', 'CASCADE');

        $attributes = [];
        if ($this->db->getPlatform() !== 'SQLite3') {
            $attributes = [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
            ];
        }
        $this->forge->createTable('cliente_tokens', true, $attributes);
    }

    public function down()
    {
        $this->forge->dropTable('cliente_tokens', true);
    }
}
