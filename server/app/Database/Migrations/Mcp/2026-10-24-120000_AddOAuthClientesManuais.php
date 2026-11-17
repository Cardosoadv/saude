<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Suporte a clientes OAuth cadastrados manualmente (Client ID/Secret), como o
 * conector personalizado do Gemini, que não usa Dynamic Client Registration
 * e trata PKCE como opcional:
 *
 *  - mcp_oauth_clients.usuario_id: quem cadastrou o cliente pela tela de
 *    Integração IA (nulo para os autorregistrados em /oauth/register).
 *  - mcp_oauth_codes.code_challenge: passa a aceitar nulo — clientes
 *    confidenciais (com client_secret) podem dispensar PKCE.
 */
class AddOAuthClientesManuais extends Migration
{
    public function up()
    {
        $this->forge->addColumn('mcp_oauth_clients', [
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'token_endpoint_auth_method',
                'comment'    => 'Usuário que cadastrou o cliente manualmente. Nulo para clientes de /oauth/register.',
            ],
        ]);

        if ($this->db->getPlatform() === 'SQLite3') {
            $this->forge->modifyColumn('mcp_oauth_codes', [
                'code_challenge' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                ],
            ]);
        } else {
            $this->db->query('ALTER TABLE mcp_oauth_codes MODIFY code_challenge VARCHAR(128) NULL');
        }
    }

    public function down()
    {
        $this->forge->dropColumn('mcp_oauth_clients', 'usuario_id');

        if ($this->db->getPlatform() === 'SQLite3') {
            $this->forge->modifyColumn('mcp_oauth_codes', [
                'code_challenge' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => false,
                ],
            ]);
        } else {
            $this->db->query("UPDATE mcp_oauth_codes SET code_challenge = '' WHERE code_challenge IS NULL");
            $this->db->query('ALTER TABLE mcp_oauth_codes MODIFY code_challenge VARCHAR(128) NOT NULL');
        }
    }
}
