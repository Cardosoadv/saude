<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\WebPush\Base64Url;
use App\Libraries\WebPush\ChavesEc;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Gera o par de chaves VAPID das notificações push. Rode uma vez por tenant e copie as
 * linhas para o .env. Trocar as chaves invalida todas as inscrições existentes.
 */
class PushVapid extends BaseCommand
{
    protected $group       = 'Notificações';
    protected $name        = 'push:vapid';
    protected $description = 'Gera as chaves VAPID das notificações push (copie a saída para o .env).';
    protected $usage       = 'push:vapid';

    public function run(array $params)
    {
        $par = ChavesEc::gerar();

        if (config('Push')->habilitado()) {
            CLI::write('Atenção: já existem chaves no .env. Trocá-las desativa as notificações de todos os aparelhos já inscritos.', 'yellow');
            CLI::newLine();
        }

        CLI::write('# Notificações push (PWA)');
        CLI::write('push.vapidPublicKey = "' . Base64Url::codificar($par['publica']) . '"');
        CLI::write('push.vapidPrivateKey = "' . Base64Url::codificar($par['privada']) . '"');
        CLI::write('push.contato = "mailto:SEU-EMAIL@cardosoebruno.adv.br"');

        return EXIT_SUCCESS;
    }
}
