<?php

namespace App\Database\Migrations\Conhecimento;

use CodeIgniter\Database\Migration;

/**
 * Permite classificar argumentos por Ramo do Direito, fechando o mesmo
 * eixo de taxonomia já usado por jurisprudências e modelos de documentos.
 */
class AddRamoIdToArgumentos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('argumentos', [
            'ramo_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'titulo',
            ],
        ]);

        $this->forge->addKey('ramo_id', false, false, 'idx_argumentos_ramo_id');

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->addForeignKey('ramo_id', 'juris_ramos_direito', 'id', 'CASCADE', 'SET NULL');
        }

        $this->forge->processIndexes('argumentos');
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropForeignKey('argumentos', 'argumentos_ramo_id_foreign');
        }
        $this->forge->dropColumn('argumentos', 'ramo_id');
    }
}
