<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\PushNotificacaoService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Envia as notificações push pendentes: prazos de tarefas.
 *
 * Agendamento sugerido (cron):
 *   0,15,30,45 * * * * php spark push:notificar   (a cada 15 minutos)
 * Fora do horário configurado em Config\Push o comando não envia nada.
 */
class PushNotificar extends BaseCommand
{
    protected $group       = 'Notificações';
    protected $name        = 'push:notificar';
    protected $description = 'Envia as notificações push pendentes (prazos de tarefas).';
    protected $usage       = 'push:notificar';

    public function run(array $params)
    {
        $r = (new PushNotificacaoService())->processar();

        if (!$r['executado']) {
            CLI::write($r['motivo'] ?? 'Nada a fazer.', 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::table([[
            (string) $r['prazos'],
            (string) $r['usuarios'],
            (string) $r['falhas'],
            (string) $r['removidas'],
        ]], ['Prazos avisados', 'Usuários', 'Falhas', 'Inscrições removidas']);

        return EXIT_SUCCESS;
    }
}
