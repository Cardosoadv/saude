<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGoogleEventIdToTarefas extends Migration
{
    public function up()
    {
        // 1. Adiciona o campo google_event_id na tabela tarefas
        $fields = [
            'google_event_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'checklist_item_id',
            ],
        ];
        $this->forge->addColumn('tarefas', $fields);

        // 2. Cria índice para o campo google_event_id
        $this->forge->addKey('google_event_id');

        // 3. Insere a configuração do GOOGLE_CALENDAR_ID na tabela configuracoes caso não exista
        $db = \Config\Database::connect();
        $exists = $db->table('configuracoes')
            ->where('nome', 'GOOGLE_CALENDAR_ID')
            ->countAllResults();

        if ($exists === 0) {
            $db->table('configuracoes')->insert([
                'nome'       => 'GOOGLE_CALENDAR_ID',
                'valor'      => null,
                'descricao'  => 'ID do calendário do Google utilizado para sincronização de tarefas (Ordo Juris).',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        // 1. Remove o campo google_event_id (pula se for SQLite)
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('tarefas', 'google_event_id');
        }

        // 2. Remove a configuração do GOOGLE_CALENDAR_ID
        $db = \Config\Database::connect();
        $db->table('configuracoes')
            ->where('nome', 'GOOGLE_CALENDAR_ID')
            ->delete();
    }
}
