<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocEtiquetas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'etiqueta_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'entidade_tipo' => ['type' => 'ENUM', 'constraint' => ['modelo', 'documento_gerado']],
            'entidade_id'   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
        ]);
        $this->forge->addKey(['entidade_tipo', 'entidade_id', 'etiqueta_id'], false, true, 'idx_doc_etiq_unique');
        $this->forge->addKey('etiqueta_id');
        $this->forge->addForeignKey('etiqueta_id', 'etiquetas', 'id_etiqueta', 'CASCADE', 'CASCADE');
        $this->forge->createTable('doc_etiquetas', true);
    }

    public function down()
    {
        $this->forge->dropTable('doc_etiquetas', true);
    }
}
