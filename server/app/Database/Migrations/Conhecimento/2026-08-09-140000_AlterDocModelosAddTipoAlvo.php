<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterDocModelosAddTipoAlvo extends Migration
{
    public function up()
    {
        $this->forge->addColumn('doc_modelos', [
            'tipo_alvo' => [
                'type'       => 'ENUM',
                'constraint' => ['processo', 'caso', 'ambos'],
                'default'    => 'ambos',
                'null'       => false,
                'after'      => 'ramo_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('doc_modelos', 'tipo_alvo');
    }
}
