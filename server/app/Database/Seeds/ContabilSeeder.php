<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Services\Contabil\PlanoContasModeloService;

/**
 * Seeder de inicialização do módulo Contábil.
 * Cria a entidade principal (Clínica Saúde), importa o plano de contas
 * padrão (ITG 1000, clínica) e abre o exercício fiscal corrente.
 *
 * @version 1.1.0
 */
class ContabilSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        // 1. Entidade principal
        $entity = $db->table('acc_entities')->where('id', 1)->get()->getRowArray();
        if (! $entity) {
            $db->table('acc_entities')->insert([
                'id'            => 1,
                'name'          => 'Clínica Saúde',
                'currency'      => 'BRL',
                'default_basis' => 'ACCRUAL',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        // 2. Plano de contas
        $accountsCount = $db->table('acc_accounts')->where('entity_id', 1)->countAllResults();
        if ($accountsCount === 0) {
            (new PlanoContasModeloService())->importar(PlanoContasModeloService::MODELO_PADRAO, 1);
        }

        // 3. Exercício Fiscal Corrente (2026)
        $anoAtual = (int) date('Y');
        $anoExiste = $db->table('acc_fiscal_years')
            ->where('entity_id', 1)
            ->where('start_date <=', "{$anoAtual}-01-01")
            ->where('end_date >=', "{$anoAtual}-12-31")
            ->countAllResults();

        if ($anoExiste === 0) {
            $uc = service('contabil');
            $uc->createFiscalYear()->execute(1, "{$anoAtual}-01-01");
        }
    }
}
