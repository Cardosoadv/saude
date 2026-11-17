<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run()
    {
        // Default funnel stages
        $etapas = [
            ['nome' => 'Novo Lead', 'cor' => '#0dcaf0', 'ordem' => 1],
            ['nome' => 'Contato Feito', 'cor' => '#ffc107', 'ordem' => 2],
            ['nome' => 'Reunião Agendada', 'cor' => '#6610f2', 'ordem' => 3],
            ['nome' => 'Proposta Enviada', 'cor' => '#0d6efd', 'ordem' => 4],
            ['nome' => 'Em Negociação', 'cor' => '#fd7e14', 'ordem' => 5],
        ];

        foreach ($etapas as $etapa) {
            $etapa['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('crm_etapas')->insert($etapa);
        }

        // Default lead origins
        $origens = [
            ['nome' => 'Indicação', 'icone' => 'bi-person-heart'],
            ['nome' => 'Google Ads', 'icone' => 'bi-google'],
            ['nome' => 'Instagram', 'icone' => 'bi-instagram'],
            ['nome' => 'Facebook', 'icone' => 'bi-facebook'],
            ['nome' => 'LinkedIn', 'icone' => 'bi-linkedin'],
            ['nome' => 'Site / Orgânico', 'icone' => 'bi-globe'],
            ['nome' => 'Evento / Networking', 'icone' => 'bi-people'],
        ];

        foreach ($origens as $origem) {
            $origem['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('crm_origens')->insert($origem);
        }
    }
}
