<?php

namespace App\Commands;

use App\Services\IndicesEconomicosService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Importa os índices econômicos do SGS (Banco Central).
 *
 * Agendamento sugerido (cron): `0 8 * * * php spark indices:atualizar`.
 * Rodar todo dia é inofensivo: só entram os valores que ainda não existem.
 */
class IndicesAtualizar extends BaseCommand
{
    protected $group       = 'Liquidação';
    protected $name        = 'indices:atualizar';
    protected $description = 'Importa os índices econômicos (IPCA, INPC, IGP-M, Selic...) do SGS do Banco Central.';
    protected $usage       = 'indices:atualizar [codigo] [--historico]';
    protected $arguments   = ['codigo' => 'Código do índice (ex.: IPCA). Sem ele, todos os ativos do SGS.'];
    protected $options     = ['--historico' => 'Recarrega o histórico completo (carga inicial).'];

    public function run(array $params)
    {
        $codigo    = $params[0] ?? null;
        $historico = array_key_exists('historico', $params) || CLI::getOption('historico') !== null;

        $resultado = (new IndicesEconomicosService())->atualizarDoSgs($codigo, $historico);

        $linhas = [];
        $falhou = false;
        foreach ($resultado as $indice => $r) {
            $falhou = $falhou || $r['status'] !== 'ok';
            $linhas[] = [
                $indice,
                $r['status'] === 'ok' ? CLI::color('OK', 'green') : CLI::color('FALHA', 'red'),
                (string) $r['novos'],
                (string) $r['atualizados'],
                (string) ($r['mensagem'] ?? ''),
            ];
        }
        CLI::table($linhas, ['Índice', 'Status', 'Novos', 'Atualizados', 'Mensagem']);

        return $falhou ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
