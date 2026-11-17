<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMcpOAuthClients extends Migration
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
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'Identificador público do cliente OAuth (ex.: Claude), emitido em /oauth/register (RFC 7591).',
            ],
            'client_secret_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
                'comment'    => 'Hash SHA-256 do client_secret. Nulo para clientes públicos (token_endpoint_auth_method = none).',
            ],
            'client_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            'redirect_uris' => [
                'type'    => 'TEXT',
                'null'    => false,
                'comment' => 'Lista de redirect_uris permitidas para este cliente, codificada em JSON.',
            ],
            'token_endpoint_auth_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => 'client_secret_post',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('client_id');

        $attributes = [];
        if ($this->db->getPlatform() !== 'SQLite3') {
            $attributes = [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
            ];
        }
        $this->forge->createTable('mcp_oauth_clients', true, $attributes);
    }

    public function down()
    {
        $this->forge->dropTable('mcp_oauth_clients', true);
    }
}
