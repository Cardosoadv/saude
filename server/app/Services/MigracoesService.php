<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\MigrationRunner;
use RuntimeException;
use Throwable;

/**
 * Executa e consulta as migrations do banco via HTTP, como um proxy do
 * `php spark migrate`.
 *
 * Considera apenas o namespace da aplicação (`App`), como o CLI sem `--all`:
 * as migrations do Shield/Settings no vendor não entram, pois o projeto
 * mantém as próprias versões dessas tabelas em app/Database/Migrations.
 *
 * @version 1.0.0
 * @since 2026-09-19
 */
class MigracoesService
{
    private MigrationRunner $runner;

    private string $lockFile;

    /**
     * @param MigrationRunner|null $runner   Runner do CodeIgniter (injetável para testes).
     * @param string|null          $lockFile Arquivo usado para impedir execuções simultâneas.
     */
    public function __construct(?MigrationRunner $runner = null, ?string $lockFile = null)
    {
        $this->runner   = $runner ?? service('migrations');
        $this->lockFile = $lockFile ?? WRITEPATH . 'migrations.lock';
    }

    /**
     * Lista as migrations do namespace da aplicação com o respectivo estado
     * (aplicada/pendente).
     *
     * @return array{pendentes:int,aplicadas:int,ultimo_lote:int,migracoes:list<array<string,mixed>>}
     */
    public function status(): array
    {
        $this->runner->setNamespace(APP_NAMESPACE);

        $historico = [];
        foreach ($this->runner->getHistory('') as $h) {
            $historico[$this->runner->getObjectUid($h)] = $h;
        }

        $migracoes = [];
        $pendentes = 0;
        foreach ($this->runner->findMigrations() as $uid => $m) {
            $h = $historico[$uid] ?? null;
            if ($h === null) {
                $pendentes++;
            }

            $migracoes[] = [
                'versao'      => (string) $m->version,
                'nome'        => (string) $m->name,
                'classe'      => (string) $m->class,
                'namespace'   => (string) $m->namespace,
                'status'      => $h === null ? 'pendente' : 'aplicada',
                'lote'        => $h === null ? null : (int) $h->batch,
                'aplicada_em' => $h === null ? null : date('Y-m-d H:i:s', (int) $h->time),
            ];
        }

        return [
            'pendentes'   => $pendentes,
            'aplicadas'   => count($migracoes) - $pendentes,
            'ultimo_lote' => $this->runner->getLastBatch(),
            'migracoes'   => $migracoes,
        ];
    }

    /**
     * Executa todas as migrations pendentes (`migrate`).
     *
     * @return array{sucesso:bool,mensagem:string,executadas:list<string>,mensagens:list<string>}
     *
     * @throws RuntimeException Quando já existe outra execução em andamento.
     */
    public function executar(): array
    {
        $lock = fopen($this->lockFile, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('Já existe uma migração em andamento. Aguarde a conclusão.');
        }

        try {
            $antes = $this->pendentes();

            $this->runner->setNamespace(APP_NAMESPACE);
            $this->runner->setSilent(true);
            $this->runner->clearCliMessages();

            try {
                $ok       = $this->runner->latest();
                $mensagem = $ok ? 'Migrations executadas com sucesso.' : 'A migration falhou e foi revertida.';
            } catch (Throwable $e) {
                log_message('error', 'Erro ao executar migrations via HTTP: ' . $e->getMessage());
                $ok       = false;
                $mensagem = 'Erro ao executar migrations: ' . $e->getMessage();
            }

            $executadas = array_values(array_diff($antes, $this->pendentes()));

            return [
                'sucesso'    => $ok,
                'mensagem'   => $mensagem,
                'executadas' => $executadas,
                'mensagens'  => $this->mensagensRunner(),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Nomes (versão_classe) das migrations ainda não aplicadas.
     *
     * @return list<string>
     */
    private function pendentes(): array
    {
        $out = [];
        foreach ($this->status()['migracoes'] as $m) {
            if ($m['status'] === 'pendente') {
                $out[] = $m['versao'] . '_' . $m['classe'];
            }
        }

        return $out;
    }

    /**
     * Mensagens do runner sem os códigos de cor ANSI usados no CLI.
     *
     * @return list<string>
     */
    private function mensagensRunner(): array
    {
        $out = [];
        foreach ($this->runner->getCliMessages() as $msg) {
            $out[] = trim((string) preg_replace('/\e\[[0-9;]*m/', '', $msg));
        }

        return $out;
    }
}
