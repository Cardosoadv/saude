<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Repositories\Financeiro\ExtratoRepository;

class BenchmarkExtrato extends BaseCommand
{
    protected $group       = 'Performance';
    protected $name        = 'benchmark:extrato';
    protected $description = 'Benchmarks ExtratoRepository';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $repo = new ExtratoRepository();

        // 1. Setup a large dataset
        CLI::write('Setting up dataset...', 'yellow');

        $count = 1000;

        // Ensure we have a count
        $db->table('users')->insertBatch(array_map(fn($i) => ['username' => "User $i"], range(1, 100)));
        $users = $db->table('users')->get()->getResultArray();
        $userIds = array_column($users, 'id');

        $db->table('fin_pgto_despesas')->truncate();
        $db->table('fin_pgto_despesas_rateio')->truncate();

        $despesas = [];
        for ($i = 1; $i <= $count; $i++) {
            $despesas[] = [
                'id_pgto_despesa' => $i,
                'conta_id' => 1,
                'valor' => 100.00,
                'pagamento_despesa_dt' => '2025-01-01',
                'despesa_id' => 1
            ];
        }
        $db->table('fin_pgto_despesas')->insertBatch($despesas);

        $rateios = [];
        for ($i = 1; $i <= $count; $i++) {
            $rateios[] = [
                'pagto_despesa_id' => $i,
                'user_id' => $userIds[array_rand($userIds)],
                'valor' => 100.00
            ];
        }
        $db->table('fin_pgto_despesas_rateio')->insertBatch($rateios);

        CLI::write('Running benchmark...', 'yellow');

        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $repo->getDespesasPorContaPeriodo(1, '2025-01-01', '2025-01-31');
        }
        $end = microtime(true);

        CLI::write('Time taken: ' . ($end - $start) . 's', 'green');
    }
}
