<?php

namespace App\Database\Migrations;

use App\Services\Financeiro\ExtratoConfig;
use CodeIgniter\Database\Migration;

/**
 * Cria as configurações dos limites de extratos (tamanho do arquivo, janela de conciliação, lotes).
 * Idempotente: não sobrescreve valor já existente.
 */
class AddConfiguracoesExtratos extends Migration
{
    public function up()
    {
        foreach (ExtratoConfig::DEFINICOES as $nome => [$padrao, , , $descricao]) {
            if ($this->db->table('configuracoes')->where('nome', $nome)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('configuracoes')->insert([
                'nome'       => $nome,
                'valor'      => (string) $padrao,
                'descricao'  => $descricao,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('configuracoes')->whereIn('nome', array_keys(ExtratoConfig::DEFINICOES))->delete();
    }
}
