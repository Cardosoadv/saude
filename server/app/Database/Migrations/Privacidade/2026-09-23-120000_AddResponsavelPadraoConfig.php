<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Responsável atribuído a clientes e contratos criados sem usuário
 * logado (crons, filas, comandos, importações). Sem ele,
 * esses registros nascem restritos e sem responsável, visíveis só para admins.
 */
class AddResponsavelPadraoConfig extends Migration
{
    public function up()
    {
        $existe = $this->db->table('configuracoes')
            ->where('nome', 'privacidade_responsavel_padrao')
            ->countAllResults();

        if ($existe === 0) {
            $this->db->table('configuracoes')->insert([
                'nome'       => 'privacidade_responsavel_padrao',
                'valor'      => null,
                'descricao'  => 'ID do profissional que assume como responsável os clientes e contratos '
                    . 'criados automaticamente (importações), sem usuário logado.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('configuracoes')->where('nome', 'privacidade_responsavel_padrao')->delete();
    }
}
