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

}
