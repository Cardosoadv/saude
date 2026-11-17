<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMcpOAuthCodes extends Migration
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
            'code_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'Hash SHA-256 do código de autorização bruto. O bruto nunca é persistido.',
            ],
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'FK para users.id — usuário Shield que autorizou o cliente na tela de consentimento.',
            ],
            'redirect_uri' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'code_challenge' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'code_challenge_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'S256',
            ],
            'scope' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'default'    => 'mcp',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'used_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'comment' => 'Preenchido após troca por token. Código inválido se não nulo (uso único).',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code_hash');
        $this->forge->addKey('client_id');
        $this->forge->addKey('expires_at');

        $this->forge->addForeignKey('usuario_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'mcp_oauth_clients', 'client_id', 'CASCADE', 'CASCADE');

        $attributes = [];
        if ($this->db->getPlatform() !== 'SQLite3') {
            $attributes = [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
            ];
        }
        $this->forge->createTable('mcp_oauth_codes', true, $attributes);
    }

    public function down()
    {
        $this->forge->dropTable('mcp_oauth_codes', true);
    }
}
