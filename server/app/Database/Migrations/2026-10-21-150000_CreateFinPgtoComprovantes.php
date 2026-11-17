<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Comprovantes de pagamento (despesas e receitas) guardados no Google Drive,
 * na pasta do financeiro, em subpastas mensais (AAAA-MM).
 */
class CreateFinPgtoComprovantes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_comprovante' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'pagamento_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'tamanho' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'drive_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'drive_link' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'pasta_mes' => [
                'type'       => 'CHAR',
                'constraint' => 7,
            ],
            'enviado_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_comprovante', true);
        $this->forge->addKey(['tipo', 'pagamento_id']);
        $this->forge->createTable('fin_pgto_comprovantes');

        $existe = $this->db->table('configuracoes')
            ->where('nome', 'GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID')
            ->countAllResults();

        if ($existe === 0) {
            $this->db->table('configuracoes')->insert([
                'nome'       => 'GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID',
                'valor'      => null,
                'descricao'  => 'ID (ou URL) da pasta do Google Drive onde ficam os comprovantes de pagamento, '
                    . 'organizados em subpastas mensais (AAAA-MM).',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('configuracoes')->where('nome', 'GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID')->delete();
        $this->forge->dropTable('fin_pgto_comprovantes');
    }
}
