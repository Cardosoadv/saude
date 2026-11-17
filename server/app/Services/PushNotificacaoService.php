<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebPush\Enviador;
use App\Libraries\WebPush\Vapid;
use App\Repositories\PushRepository;
use Closure;
use Config\Push;
use Throwable;

/**
 * Notificações push do painel (PWA): novas intimações e prazos de tarefas.
 *
 * Regras:
 *  - Intimações: avisa quem tem acesso ao módulo de intimações e ligou "Intimações" no aparelho.
 *    Só entram intimações não tratadas que chegaram depois da inscrição (e dentro da janela
 *    configurada), uma única vez por usuário.
 *  - Prazos: avisa o responsável por tarefas em aberto com prazo hoje (ou nos próximos dias,
 *    conforme `Push::$diasAntecedenciaPrazo`). Mudou o prazo, avisa de novo.
 *  - Nada é enviado fora do horário configurado; o que ficou pendente sai na execução seguinte.
 *  - O conteúdo vai cifrado de ponta a ponta e traz só o número do processo e o tipo do aviso,
 *    nunca o texto da intimação.
 */
class PushNotificacaoService
{
    public const TIPO_INTIMACAO = 'intimacao';
    public const TIPO_PRAZO     = 'prazo';

    private const MAX_CORPO = 140;

    private PushRepository $repository;
    private Push $config;
    private ?Enviador $enviador;

    /** @var Closure(int $userId, string $tipo): bool */
    private Closure $podeReceber;

    /** @var Closure(): int */
    private Closure $relogio;

    public function __construct(
        ?PushRepository $repository = null,
        ?Push $config = null,
        ?Enviador $enviador = null,
        ?Closure $podeReceber = null,
        ?Closure $relogio = null,
    ) {
        $this->repository  = $repository ?? new PushRepository();
        $this->config      = $config ?? config('Push');
        $this->enviador    = $enviador;
        $this->podeReceber = $podeReceber ?? self::podeReceberPadrao(...);
        $this->relogio     = $relogio ?? static fn (): int => time();
    }

    public function habilitado(): bool
    {
        return $this->config->habilitado();
    }

    public function chavePublica(): ?string
    {
        return $this->habilitado() ? $this->config->vapidPublicKey : null;
    }

    public function dentroDoHorario(): bool
    {
        $hora = (int) date('G', ($this->relogio)());

        return $hora >= $this->config->horaInicio && $hora < $this->config->horaFim;
    }

    /**
     * Rotina do cron: envia o que estiver pendente.
     *
     * @return array{executado: bool, motivo?: string, intimacoes: int, prazos: int, usuarios: int, falhas: int, removidas: int}
     */
    public function processar(): array
    {
        $resumo = ['executado' => false, 'intimacoes' => 0, 'prazos' => 0, 'usuarios' => 0, 'falhas' => 0, 'removidas' => 0];

        if (!$this->habilitado()) {
            return $resumo + ['motivo' => 'Chaves VAPID não configuradas no .env (push.vapidPublicKey / push.vapidPrivateKey).'];
        }
        if (!$this->dentroDoHorario()) {
            return $resumo + ['motivo' => sprintf('Fora do horário de envio (%dh às %dh).', $this->config->horaInicio, $this->config->horaFim)];
        }

        $resumo['executado'] = true;
        $usuarios = [];

        foreach ($this->avisosDeIntimacoes() as $userId => $aviso) {
            $r = $this->entregar($userId, 'notificar_intimacoes', $aviso['mensagem'], 'normal');
            $this->somar($resumo, $r);
            if ($r['enviadas'] > 0) {
                $this->repository->registrarEnvios($userId, self::TIPO_INTIMACAO, $aviso['referencias']);
                $resumo['intimacoes'] += count($aviso['referencias']);
                $usuarios[$userId] = true;
            }
        }

        foreach ($this->avisosDePrazos() as $userId => $aviso) {
            $r = $this->entregar($userId, 'notificar_prazos', $aviso['mensagem'], $aviso['urgencia']);
            $this->somar($resumo, $r);
            if ($r['enviadas'] > 0) {
                $this->repository->registrarEnvios($userId, self::TIPO_PRAZO, $aviso['referencias']);
                $resumo['prazos'] += count($aviso['referencias']);
                $usuarios[$userId] = true;
            }
        }

        $resumo['usuarios'] = count($usuarios);
        $this->repository->limparEnviosAntigos();

        return $resumo;
    }

    /**
     * Notificação de teste para todos os aparelhos do usuário (botão no Perfil).
     *
     * @return array{enviadas: int, falhas: int, removidas: int}
     */
    public function enviarTeste(int $userId): array
    {
        return $this->entregar($userId, null, [
            'title' => 'Notificações ativadas',
            'body'  => 'Este aparelho vai receber os avisos do Ordo Juris.',
            'url'   => '#/perfil',
            'tag'   => 'teste',
        ], 'normal');
    }

    /**
     * Uma mensagem por usuário com as intimações novas que ele ainda não recebeu.
     *
     * @return array<int, array{mensagem: array<string, string>, referencias: list<string>}>
     */
    public function avisosDeIntimacoes(): array
    {
        $inscritos = $this->repository->inscricoesPorUsuario('notificar_intimacoes');
        if ($inscritos === []) {
            return [];
        }

        $limite     = date('Y-m-d H:i:s', ($this->relogio)() - $this->config->janelaIntimacoesHoras * 3600);
        $intimacoes = $this->repository->intimacoesNovas($limite);
        if ($intimacoes === []) {
            return [];
        }

        $avisos = [];
        foreach ($inscritos as $userId => $inscricoes) {
            if (!($this->podeReceber)($userId, 'intimacoes')) {
                continue;
            }

            // Só o que chegou depois que o usuário ativou as notificações.
            $desde     = min(array_column($inscricoes, 'created_at'));
            $relevantes = array_values(array_filter($intimacoes, static fn (array $i): bool => $i['created_at'] >= $desde));
            $relevantes = $this->semJaEnviados($userId, self::TIPO_INTIMACAO, $relevantes, static fn (array $i): string => (string) $i['id_intimacao']);

            if ($relevantes !== []) {
                $avisos[$userId] = [
                    'mensagem'    => $this->mensagemIntimacoes($relevantes),
                    'referencias' => array_map(static fn (array $i): string => (string) $i['id_intimacao'], $relevantes),
                ];
            }
        }

        return $avisos;
    }

    /**
     * Uma mensagem por responsável com os prazos de hoje (e dos próximos dias configurados).
     *
     * @return array<int, array{mensagem: array<string, string>, referencias: list<string>, urgencia: string}>
     */
    public function avisosDePrazos(): array
    {
        $inscritos = $this->repository->inscricoesPorUsuario('notificar_prazos');
        if ($inscritos === []) {
            return [];
        }

        $agora  = ($this->relogio)();
        $hoje   = date('Y-m-d', $agora);
        $ate    = date('Y-m-d', strtotime('+' . max(0, $this->config->diasAntecedenciaPrazo) . ' days', strtotime($hoje)));
        $porResponsavel = [];
        foreach ($this->repository->tarefasComPrazoEntre($hoje, $ate) as $tarefa) {
            $porResponsavel[(int) $tarefa['responsavel']][] = $tarefa;
        }

        $avisos = [];
        foreach ($porResponsavel as $userId => $tarefas) {
            if (!isset($inscritos[$userId]) || !($this->podeReceber)($userId, 'tarefas')) {
                continue;
            }

            $tarefas = $this->semJaEnviados($userId, self::TIPO_PRAZO, $tarefas, self::referenciaPrazo(...));
            if ($tarefas === []) {
                continue;
            }

            $temHoje = in_array($hoje, array_column($tarefas, 'prazo'), true);
            $avisos[$userId] = [
                'mensagem'    => $this->mensagemPrazos($tarefas, $hoje),
                'referencias' => array_map(self::referenciaPrazo(...), $tarefas),
                'urgencia'    => $temHoje ? 'high' : 'normal',
            ];
        }

        return $avisos;
    }

    /** Referência do prazo: muda se o prazo da tarefa for alterado, gerando novo aviso. */
    public static function referenciaPrazo(array $tarefa): string
    {
        return $tarefa['id_tarefa'] . ':' . $tarefa['prazo'];
    }

    /**
     * @param list<array<string, mixed>> $itens
     * @return array<string, string>
     */
    private function mensagemIntimacoes(array $itens): array
    {
        $total = count($itens);
        if ($total === 1) {
            $i = $itens[0];

            return [
                'title' => 'Nova intimação',
                'body'  => $this->corpo(array_filter([
                    $i['numero_processo'] ? 'Processo ' . $i['numero_processo'] : null,
                    $i['tipoComunicacao'] ?: null,
                ])),
                'url' => '#/intimacoes/detalhes/' . $i['id_intimacao'],
                'tag' => 'intimacoes',
            ];
        }

        $numeros = array_values(array_unique(array_filter(array_column($itens, 'numero_processo'))));

        return [
            'title' => "{$total} novas intimações",
            'body'  => $this->corpo([
                count($numeros) > 0 ? 'Processos ' . implode(', ', array_slice($numeros, 0, 2)) . (count($numeros) > 2 ? ' e outros' : '') : 'Veja as intimações não tratadas',
            ]),
            'url' => '#/intimacoes',
            'tag' => 'intimacoes',
        ];
    }

    /**
     * @param list<array<string, mixed>> $tarefas
     * @return array<string, string>
     */
    private function mensagemPrazos(array $tarefas, string $hoje): array
    {
        $quando = static function (string $prazo) use ($hoje): string {
            $dias = (int) round((strtotime($prazo) - strtotime($hoje)) / 86400);

            return match (true) {
                $dias <= 0 => 'hoje',
                $dias === 1 => 'amanhã',
                default    => 'em ' . date('d/m', strtotime($prazo)),
            };
        };

        if (count($tarefas) === 1) {
            $t      = $tarefas[0];
            $numero = trim((string) ($t['numeroprocessocommascara'] ?: $t['numero_processo']));

            return [
                'title' => 'Prazo ' . $quando((string) $t['prazo']),
                'body'  => $this->corpo(array_filter([
                    trim(strip_tags((string) $t['tarefa'])),
                    $numero !== '' ? 'Processo ' . $numero : null,
                ])),
                'url' => '#/tarefas',
                'tag' => 'prazos',
            ];
        }

        $hojeQtd = count(array_filter($tarefas, static fn (array $t): bool => $t['prazo'] <= $hoje));
        $titulo  = $hojeQtd === count($tarefas)
            ? count($tarefas) . ' prazos vencem hoje'
            : ($hojeQtd > 0 ? count($tarefas) . " prazos próximos ({$hojeQtd} hoje)" : count($tarefas) . ' prazos próximos');

        return [
            'title' => $titulo,
            'body'  => $this->corpo(array_map(
                static fn (array $t): string => trim(strip_tags((string) $t['tarefa'])),
                array_slice($tarefas, 0, 3)
            ), '; '),
            'url' => '#/tarefas',
            'tag' => 'prazos',
        ];
    }

    /** @param array<int|string, string> $partes */
    private function corpo(array $partes, string $separador = ' — '): string
    {
        $texto = preg_replace('/\s+/u', ' ', implode($separador, array_filter($partes, static fn ($p) => $p !== '')));

        return mb_strlen($texto) > self::MAX_CORPO ? mb_substr($texto, 0, self::MAX_CORPO - 1) . '…' : $texto;
    }

    /**
     * @template T of array
     * @param list<T> $itens
     * @param callable(T): string $referencia
     * @return list<T>
     */
    private function semJaEnviados(int $userId, string $tipo, array $itens, callable $referencia): array
    {
        $enviados = $this->repository->jaEnviados($userId, $tipo, array_map($referencia, $itens));

        return array_values(array_filter($itens, static fn (array $i): bool => !isset($enviados[$referencia($i)])));
    }

    /**
     * Envia a mensagem a todos os aparelhos do usuário (com a preferência ligada, se informada).
     *
     * @param array<string, string> $mensagem
     * @return array{enviadas: int, falhas: int, removidas: int}
     */
    private function entregar(int $userId, ?string $preferencia, array $mensagem, string $urgencia): array
    {
        $resultado = ['enviadas' => 0, 'falhas' => 0, 'removidas' => 0];
        $conteudo  = json_encode($mensagem + ['timestamp' => ($this->relogio)() * 1000], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($this->repository->inscricoesDoUsuario($userId) as $inscricao) {
            if ($preferencia !== null && !(int) $inscricao[$preferencia]) {
                continue;
            }

            $envio = $this->enviador()->enviar($inscricao, $conteudo, $this->config->ttl, $urgencia, $mensagem['tag'] ?? null);

            if ($envio->sucesso()) {
                $this->repository->registrarSucesso((int) $inscricao['id']);
                $resultado['enviadas']++;
            } elseif ($envio->expirada) {
                $this->repository->apagar((int) $inscricao['id']);
                $resultado['removidas']++;
            } else {
                log_message('warning', "Push: falha ao enviar para a inscrição {$inscricao['id']} (HTTP {$envio->status}) " . ($envio->erro ?? ''));
                $resultado['falhas']++;
                if ($this->repository->registrarFalha((int) $inscricao['id'], $this->config->maximoFalhas)) {
                    $resultado['removidas']++;
                }
            }
        }

        return $resultado;
    }

    /** @param array<string, mixed> $resumo */
    private function somar(array &$resumo, array $r): void
    {
        $resumo['falhas']    += $r['falhas'];
        $resumo['removidas'] += $r['removidas'];
    }

    private function enviador(): Enviador
    {
        return $this->enviador ??= new Enviador(Vapid::deBase64Url(
            $this->config->vapidPublicKey,
            $this->config->vapidPrivateKey,
            $this->config->contato
        ));
    }

    /** Permissão do usuário e módulo ativo (mesmos filtros das rotas da API). */
    private static function podeReceberPadrao(int $userId, string $modulo): bool
    {
        try {
            if (!service('modulos')->isActive($modulo)) {
                return false;
            }

            $usuario = auth()->getProvider()->findById($userId);

            return $usuario !== null && !$usuario->isBanned() && $usuario->can($modulo . '.access');
        } catch (Throwable $e) {
            log_message('error', 'Push: falha ao verificar permissão do usuário ' . $userId . ': ' . $e->getMessage());

            return false;
        }
    }
}
