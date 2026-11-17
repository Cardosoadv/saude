<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DriveSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Processa a fila de criação de pastas de clientes no Google Drive.
 *
 * Agende no cron para rodar sozinho (ex.: a cada 10 minutos).
 */
class DriveSync extends BaseCommand
{
    protected $group       = 'Google Drive';
    protected $name        = 'drive:sync';
    protected $description = 'Processa a fila de criação de pastas de clientes no Google Drive.';
    protected $usage       = 'drive:sync [limite]';
    protected $arguments   = ['limite' => 'Quantos pedidos processar de uma vez (padrão 20, máximo 100).'];

    public function run(array $params)
    {
        $limite  = (int) ($params[0] ?? 20) ?: 20;
        $servico = new DriveSyncService();

        try {
            $resultado = $servico->processar($limite, static fn (string $msg, string $cor) => CLI::write($msg, $cor));
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Processados: %d · sucesso: %d · falhas: %d · restantes: %d',
            $resultado['processados'],
            $resultado['sucesso'],
            $resultado['falhas'],
            $resultado['restantes']
        ), $resultado['falhas'] > 0 ? 'yellow' : 'green');

        return EXIT_SUCCESS;
    }
}
