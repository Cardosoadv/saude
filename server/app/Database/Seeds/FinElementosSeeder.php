<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FinElementosSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['nome' => 'Receita'],
            ['nome' => 'Despesa'],
            ['nome' => 'Ativo'],
            ['nome' => 'Passivo'],
            ['nome' => 'Patrimônio Líquido'],
        ];

        // Usando Query Builder para garantir escape
        $this->db->table('fin_elementos')->insertBatch($data);
    }
}
