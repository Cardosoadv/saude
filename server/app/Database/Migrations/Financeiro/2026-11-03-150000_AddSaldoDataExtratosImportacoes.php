<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Guarda a data do saldo (DTASOF do LEDGERBAL) junto do saldo do arquivo OFX,
 * para a conferência entre o saldo do banco e o saldo do sistema na mesma data.
 * Importações anteriores ficam com a data nula e não entram na conferência.
 */
class AddSaldoDataExtratosImportacoes extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('saldo_final_data', 'fin_extratos_importacoes')) {
            return;
        }

        $this->forge->addColumn('fin_extratos_importacoes', [
            'saldo_final_data' => ['type' => 'DATE', 'null' => true, 'after' => 'saldo_final_arquivo'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('saldo_final_data', 'fin_extratos_importacoes')) {
            $this->forge->dropColumn('fin_extratos_importacoes', 'saldo_final_data');
        }
    }
}
