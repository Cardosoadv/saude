<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfil dos usuários:
 *  - `responsavel_tarefas`: só quem estiver marcado aparece na busca de responsável das tarefas.
 *    Para não esvaziar a busca em instalações existentes, os usuários já cadastrados nascem marcados.
 *  - `exibir_site` e dados profissionais: alimentam a seção "Equipe" da página Quem Somos do site.
 */
class AddPerfilUsuarios extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('users')) {
            return;
        }

        $novas = [
            'responsavel_tarefas' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0, 'after' => 'rateio_ativo'],
            'exibir_site'         => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0, 'after' => 'responsavel_tarefas'],
            'site_ordem'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0, 'after' => 'exibir_site'],
            'cargo'               => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'site_ordem'],
            'formacao'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'cargo'],
            'especializacoes'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'formacao'],
            'areas_atuacao'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'especializacoes'],
            'curriculo'           => ['type' => 'TEXT', 'null' => true, 'after' => 'areas_atuacao'],
            'lattes_url'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'curriculo'],
            'linkedin_url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'lattes_url'],
        ];

        $faltando = [];
        foreach ($novas as $nome => $definicao) {
            if (!$this->db->fieldExists($nome, 'users')) {
                $faltando[$nome] = $definicao;
            }
        }
        if ($faltando === []) {
            return;
        }

        $this->forge->addColumn('users', $faltando);

        if (isset($faltando['responsavel_tarefas'])) {
            $this->db->table('users')->update(['responsavel_tarefas' => 1]);
        }
    }

    public function down()
    {
        foreach (['responsavel_tarefas', 'exibir_site', 'site_ordem', 'cargo', 'formacao', 'especializacoes', 'areas_atuacao', 'curriculo', 'lattes_url', 'linkedin_url'] as $coluna) {
            if ($this->db->fieldExists($coluna, 'users')) {
                $this->forge->dropColumn('users', $coluna);
            }
        }
    }
}
