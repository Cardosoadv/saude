<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\Financeiro\SaldoMensalService;

class FecharMesFinanceiro extends BaseCommand
{
    protected $group       = 'Financeiro';
    protected $name        = 'financeiro:fechar-mes';
    protected $description = 'Realiza o fechamento financeiro automático de um mês.';
    protected $usage       = 'financeiro:fechar-mes [ano] [mes] [user_id]';
    protected $arguments   = [
        'ano'     => 'Ano do fechamento (ex: 2024)',
        'mes'     => 'Mês do fechamento (ex: 5)',
        'user_id' => 'ID do usuário responsável (padrão: 1)'
    ];

    public function run(array $params)
    {
        $ano = $params[0] ?? date('Y');
        $mes = $params[1] ?? date('m', strtotime('-1 month'));
        $userId = $params[2] ?? 1;

        CLI::write("Iniciando fechamento financeiro para o período: {$mes}/{$ano}", 'yellow');

        $service = new SaldoMensalService();

        try {
            $resultados = $service->fecharMesGlobal((int)$ano, (int)$mes, (int)$userId);

            $thead = ['Conta', 'Status', 'Mensagem'];
            $tbody = [];

            foreach ($resultados as $res) {
                $tbody[] = [
                    $res['conta'],
                    $res['status'] === 'success' ? CLI::color('OK', 'green') : CLI::color('ERRO', 'red'),
                    $res['message']
                ];
            }

            CLI::table($tbody, $thead);
            CLI::write("Processamento concluído.", 'green');
        } catch (\Exception $e) {
            CLI::error("Erro fatal: " . $e->getMessage());
        }
    }
}
