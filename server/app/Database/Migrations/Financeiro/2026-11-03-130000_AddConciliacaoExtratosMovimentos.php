<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Conciliação de extratos: motivo de ignorado e índice único do vínculo.
 *
 * O índice (conta_id, pagamento_tipo, pagamento_id) impede que um mesmo
 * pagamento seja vinculado a dois movimentos. As linhas sem vínculo têm
 * pagamento_tipo/pagamento_id nulos, que o índice único não compara entre si.
 * A conta entra no índice porque uma transferência tem dois lados (um movimento
 * em cada conta) e os dois podem apontar para a mesma transferência.
 */
class AddConciliacaoExtratosMovimentos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('fin_extratos_movimentos', [
            'motivo_ignorado' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'pagamento_id'],
        ]);

        $this->forge->addUniqueKey(['conta_id', 'pagamento_tipo', 'pagamento_id'], 'uq_fin_extratos_mov_pagamento');
        $this->forge->processIndexes('fin_extratos_movimentos');
    }

    public function down()
    {
        $this->forge->dropKey('fin_extratos_movimentos', 'uq_fin_extratos_mov_pagamento', false);
        $this->forge->dropColumn('fin_extratos_movimentos', 'motivo_ignorado');
    }
}
