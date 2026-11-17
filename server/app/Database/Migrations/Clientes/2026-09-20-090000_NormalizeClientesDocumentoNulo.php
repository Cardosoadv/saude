<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeClientesDocumentoNulo extends Migration
{
    /**
     * A coluna `clientes.documento` é UNIQUE. Leads e oportunidades passam a poder
     * ser salvos sem CPF/CNPJ, então qualquer valor de string vazia precisa virar
     * NULL — do contrário o índice único bloquearia o segundo lead/oportunidade
     * sem documento (MySQL permite múltiplos NULL num índice único, mas não
     * permite múltiplas strings vazias).
     */
    public function up()
    {
        $this->db->table('clientes')->where('documento', '')->update(['documento' => null]);
    }

    public function down()
    {
        // Normalização de dados; não há estado anterior determinístico para restaurar.
    }
}
