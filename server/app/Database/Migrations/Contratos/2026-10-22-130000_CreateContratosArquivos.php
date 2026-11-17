<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Arquivos de contratos (contrato original, aditivos, anexos) guardados no Google Drive,
 * na subpasta "Contratos" da pasta do cliente.
 */
class CreateContratosArquivos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_contrato_arquivo' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'contrato_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'categoria'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Contrato original'],
            'nome'                => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tamanho'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'drive_id'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'drive_link'          => ['type' => 'VARCHAR', 'constraint' => 500],
            'enviado_por'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_contrato_arquivo', true);
        $this->forge->addKey('contrato_id');
        $this->forge->createTable('contratos_arquivos', true);
    }

    public function down()
    {
        $this->forge->dropTable('contratos_arquivos', true);
    }
}
