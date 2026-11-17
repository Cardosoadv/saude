<?php

namespace App\Commands;

use App\Services\AuditoriaArquivamentoService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Arquivamento mensal da tabela `auditoria`: salva os registros em backup
 * (JSON Lines + gzip), confere o arquivo, apaga o que foi salvo e otimiza a tabela.
 *
 * Cron (um por tenant), no dia 1º às 03:00:
 *   0 3 1 * * cd /caminho/do/tenant && php spark auditoria:arquivar >> writable/logs/auditoria-arquivar.log 2>&1
 *
 * Código de saída: 0 = ok (ou nada a arquivar / outra execução em andamento); 1 = falha (nada foi apagado
 * se a falha ocorreu antes da conferência do backup).
 */
class AuditoriaArquivar extends BaseCommand
{
    protected $group       = 'Auditoria';
    protected $name        = 'auditoria:arquivar';
    protected $description = 'Salva a tabela de auditoria em backup compactado, apaga os registros salvos e otimiza a tabela.';
    protected $usage       = 'auditoria:arquivar [--manter-dias N] [--dir PASTA] [--sem-otimizar] [--simular]';
    protected $arguments   = [];
    protected $options     = [
        '--manter-dias'  => 'Mantém na tabela os registros dos últimos N dias (padrão: 0 = arquiva tudo)',
        '--dir'          => 'Pasta do backup (padrão: writable/backups/auditoria)',
        '--sem-otimizar' => 'Não executa OPTIMIZE TABLE ao final',
        '--simular'      => 'Só mostra quantos registros seriam arquivados; não grava nem apaga nada',
    ];

    public function run(array $params)
    {
        $manter = CLI::getOption('manter-dias');
        $manter = ($manter === null || $manter === true) ? 0 : $manter;
        if (filter_var($manter, FILTER_VALIDATE_INT) === false || $manter < 0) {
            CLI::error('--manter-dias deve ser um inteiro maior ou igual a 0.');

            return EXIT_ERROR;
        }

        $dir = CLI::getOption('dir');
        $dir = (is_string($dir) && $dir !== '') ? $dir : WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'auditoria';

        $lock = $this->adquirirLock();
        if ($lock === null) {
            CLI::write('Outra execução de auditoria:arquivar está em andamento; nada a fazer.', 'yellow');
            log_message('warning', 'auditoria:arquivar: execução ignorada, outra já em andamento.');

            return EXIT_SUCCESS;
        }

        try {
            $r = $this->servico()->arquivar(
                (int) $manter,
                $dir,
                CLI::getOption('sem-otimizar') === null,
                CLI::getOption('simular') !== null,
                static fn (string $m) => CLI::write('[' . date('Y-m-d H:i:s') . "] {$m}")
            );
        } catch (\Throwable $e) {
            CLI::error('Falha ao arquivar a auditoria: ' . $e->getMessage());
            log_message('error', 'auditoria:arquivar: ' . $e->getMessage());

            return EXIT_ERROR;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $resumo = sprintf('%d registro(s) arquivado(s), %d apagado(s)%s', $r['total'], $r['apagados'], $r['arquivo'] ? ", backup em {$r['arquivo']}" : '');
        CLI::write("Resumo: {$resumo}", 'green');
        log_message('info', "auditoria:arquivar: {$resumo}");

        return EXIT_SUCCESS;
    }

    /** Evita duas execuções simultâneas. Retorna null se já houver uma. */
    protected function adquirirLock()
    {
        $dir = WRITEPATH . 'cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        $fh = fopen($dir . DIRECTORY_SEPARATOR . 'auditoria_arquivar.lock', 'c');
        if ($fh === false) {
            return null;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return null;
        }

        return $fh;
    }

    protected function servico(): AuditoriaArquivamentoService
    {
        return new AuditoriaArquivamentoService();
    }
}
