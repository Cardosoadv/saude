<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

/**
 * Inscrições de notificação push e registro do que já foi avisado.
 *
 * Consultas diretas (sem os escopos de privacidade dos models): o disparo roda no cron,
 * sem usuário logado, e o destinatário já é filtrado por permissão no serviço.
 */
class PushRepository
{
    /** Status de tarefa em aberto (mesmo critério de TarefasRepository::contarPorResponsavel). */
    public const STATUS_TAREFA_ABERTA = [1, 2, 3];

    public const PREFERENCIAS = ['notificar_intimacoes', 'notificar_prazos'];

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Cria ou atualiza a inscrição do aparelho. Um mesmo endpoint pertence a um único usuário:
     * se outra pessoa entrar no mesmo navegador, a inscrição passa para ela.
     *
     * @param array{endpoint: string, p256dh: string, auth: string} $chaves
     * @param array<string, bool> $preferencias
     */
    public function salvarInscricao(int $userId, array $chaves, array $preferencias, ?string $userAgent): array
    {
        $hash  = hash('sha256', $chaves['endpoint']);
        $agora = date('Y-m-d H:i:s');

        $dados = [
            'user_id'    => $userId,
            'endpoint'   => $chaves['endpoint'],
            'p256dh'     => $chaves['p256dh'],
            'auth'       => $chaves['auth'],
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'falhas'     => 0,
            'updated_at' => $agora,
        ];
        foreach (self::PREFERENCIAS as $campo) {
            if (array_key_exists($campo, $preferencias)) {
                $dados[$campo] = $preferencias[$campo] ? 1 : 0;
            }
        }

        $existente = $this->db->table('push_inscricoes')->where('endpoint_hash', $hash)->get()->getRowArray();

        if ($existente) {
            if ((int) $existente['user_id'] !== $userId) {
                // Troca de dono: o histórico de "já avisado" do outro usuário não vale para este.
                $dados['created_at'] = $agora;
            }
            $this->db->table('push_inscricoes')->where('id', $existente['id'])->update($dados);
        } else {
            $dados['endpoint_hash'] = $hash;
            $dados['created_at']    = $agora;
            $this->db->table('push_inscricoes')->insert($dados);
        }

        return $this->db->table('push_inscricoes')->where('endpoint_hash', $hash)->get()->getRowArray();
    }

    public function inscricaoDoUsuario(int $userId, string $endpoint): ?array
    {
        return $this->db->table('push_inscricoes')
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->where('user_id', $userId)
            ->get()->getRowArray() ?: null;
    }

    public function removerInscricao(int $userId, string $endpoint): bool
    {
        $this->db->table('push_inscricoes')
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->where('user_id', $userId)
            ->delete();

        return $this->db->affectedRows() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function inscricoesDoUsuario(int $userId): array
    {
        return $this->db->table('push_inscricoes')->where('user_id', $userId)->orderBy('id')->get()->getResultArray();
    }

    /**
     * Inscrições com a preferência ligada, agrupadas por usuário.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function inscricoesPorUsuario(string $preferencia): array
    {
        if (!in_array($preferencia, self::PREFERENCIAS, true)) {
            throw new \InvalidArgumentException("Preferência desconhecida: {$preferencia}");
        }

        $grupos = [];
        foreach ($this->db->table('push_inscricoes')->where($preferencia, 1)->orderBy('id')->get()->getResultArray() as $linha) {
            $grupos[(int) $linha['user_id']][] = $linha;
        }

        return $grupos;
    }

    public function registrarSucesso(int $id): void
    {
        $this->db->table('push_inscricoes')->where('id', $id)
            ->update(['falhas' => 0, 'ultimo_envio_em' => date('Y-m-d H:i:s')]);
    }

    /** Conta a falha e apaga a inscrição ao atingir o limite. Devolve true se apagou. */
    public function registrarFalha(int $id, int $maximo): bool
    {
        $this->db->table('push_inscricoes')->where('id', $id)->set('falhas', 'falhas + 1', false)->update();
        $linha = $this->db->table('push_inscricoes')->select('falhas')->where('id', $id)->get()->getRowArray();

        if ($linha && (int) $linha['falhas'] >= $maximo) {
            $this->apagar($id);

            return true;
        }

        return false;
    }

    public function apagar(int $id): void
    {
        $this->db->table('push_inscricoes')->where('id', $id)->delete();
    }

    /**
     * Quais referências já foram avisadas a este usuário.
     *
     * @param list<string> $referencias
     * @return array<string, true>
     */
    public function jaEnviados(int $userId, string $tipo, array $referencias): array
    {
        if ($referencias === []) {
            return [];
        }

        $linhas = $this->db->table('push_envios')->select('referencia')
            ->where('user_id', $userId)->where('tipo', $tipo)->whereIn('referencia', $referencias)
            ->get()->getResultArray();

        return array_fill_keys(array_column($linhas, 'referencia'), true);
    }

    /** @param list<string> $referencias */
    public function registrarEnvios(int $userId, string $tipo, array $referencias): void
    {
        $agora = date('Y-m-d H:i:s');
        $novas = array_keys(array_diff_key(array_fill_keys($referencias, true), $this->jaEnviados($userId, $tipo, $referencias)));

        if ($novas !== []) {
            $this->db->table('push_envios')->insertBatch(array_map(
                static fn (string $ref): array => ['user_id' => $userId, 'tipo' => $tipo, 'referencia' => $ref, 'created_at' => $agora],
                $novas
            ));
        }
    }

    /** Apaga o histórico de avisos antigo (as referências antigas não voltam a aparecer). */
    public function limparEnviosAntigos(int $dias = 90): int
    {
        $this->db->table('push_envios')->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$dias} days")))->delete();

        return $this->db->affectedRows();
    }

    /**
     * Intimações ainda não tratadas que chegaram a partir de `$desde`.
     *
     * @return list<array{id_intimacao: int|string, numero_processo: ?string, tipoComunicacao: ?string, created_at: string}>
     */
    public function intimacoesNovas(string $desde): array
    {
        return $this->db->table('intimacoes')
            ->select('intimacoes.id_intimacao, intimacoes.numero_processo, intimacoes.tipoComunicacao, intimacoes.created_at')
            ->join('intimacoes_tratamento as it', 'it.intimacao_id = intimacoes.id_intimacao', 'left')
            ->where('it.tratamento_id IS NULL')
            ->where('intimacoes.deleted_at IS NULL')
            ->where('intimacoes.created_at >=', $desde)
            ->orderBy('intimacoes.id_intimacao')
            ->get()->getResultArray();
    }

    /**
     * Tarefas em aberto, com responsável, cujo prazo está entre as datas (inclusive).
     *
     * @return list<array<string, mixed>>
     */
    public function tarefasComPrazoEntre(string $inicio, string $fim): array
    {
        return $this->db->table('tarefas')
            ->select('tarefas.id_tarefa, tarefas.tarefa, tarefas.prazo, tarefas.responsavel, tarefas.processo_id, '
                . 'processos.numeroprocessocommascara, processos.numero_processo')
            ->join('processos', 'processos.id_processo = tarefas.processo_id', 'left')
            ->whereIn('tarefas.status', self::STATUS_TAREFA_ABERTA)
            ->where('tarefas.responsavel IS NOT NULL')
            ->where('tarefas.deleted_at IS NULL')
            ->where('tarefas.prazo >=', $inicio)
            ->where('tarefas.prazo <=', $fim)
            ->orderBy('tarefas.prazo')
            ->orderBy('tarefas.id_tarefa')
            ->get()->getResultArray();
    }
}
