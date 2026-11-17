<?php

namespace App\Repositories;

use App\Models\TarefasModel;
use App\Models\UsuariosModel;

class NotificacaoRepository extends BaseRepository
{
    private UsuariosModel $usuariosModel;


    public function __construct(?TarefasModel $model = null, ?UsuariosModel $usuariosModel = null)
    {
        parent::__construct();
        $this->model = $model ?? model('TarefasModel');
        $this->usuariosModel = $usuariosModel ?? model('UsuariosModel');
        $this->cacheKey = 'notificacoes_tarefas';
    }


    public function listarTarefasPrazoVencendo(): array
    {
        return $this->remember('prazo_vencendo', function () {
            return $this->model
                ->where('notificar', 1)
                ->whereIn('status', [1, 2, 3])
                ->where('prazo <=', date('Y-m-d', strtotime('+3 days')))
                ->orderBy('prazo', 'ASC')
                ->findAll();
        });
    }

    /**
     * Resume um processo para exibição em notificações: número, título e cliente.
     *
     * Consulta direta, sem os escopos de privacidade do model: a notificação é
     * destinada ao responsável e pode ser disparada sem usuário logado (cron).
     *
     * @return array{numero: string, titulo: ?string, cliente: ?string, url: string}|null
     */
    public function resumoProcesso(int $processoId): ?array
    {
        $linha = db_connect()->table('processos')
            ->select('processos.numero_processo, processos.numeroprocessocommascara, processos.titulo_processo, clientes.nome as cliente_nome')
            ->join('clientes', 'clientes.id_cliente = processos.cliente_id', 'left')
            ->where('processos.id_processo', $processoId)
            ->get()
            ->getRowArray();

        if (!$linha) {
            return null;
        }

        $numero = trim((string) ($linha['numeroprocessocommascara'] ?: $linha['numero_processo']));

        return [
            'numero'  => $numero,
            'titulo'  => trim((string) $linha['titulo_processo']) ?: null,
            'cliente' => trim((string) $linha['cliente_nome']) ?: null,
            'url'     => config('App')->frontURL . 'processos/detalhes/' . $processoId,
        ];
    }
}
