<?php

namespace App\Repositories;

use App\Models\ProcessosMovimentosModel;

class MovimentosRepository extends BaseRepository
{
    public function __construct(?ProcessosMovimentosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('ProcessosMovimentosModel');
        $this->cacheKey = 'movimentos';
    }

    public function buscarMovimentosNaoSincronizados($limit = 100): array
    {
        // Busca os movimentos que ainda não foram sincronizados, limitando a quantidade retornada
        return $this->model->where('synchronize_at IS NULL')->limit($limit)->findAll();
    }

    public function contarMovimentosNaoSincronizados(): int
    {
        // Conta o total de movimentos que ainda não foram sincronizados
        return $this->model->where('synchronize_at IS NULL')->countAllResults();
    }

    public function registrarSincronizacao(int $id): void
    {
        $result = $this->model->update($id, ['synchronize_at' => date('Y-m-d H:i:s')]);
        if ($result) {
            $this->clearCache();
        }
    }

    public function movimentoJaExiste(string $numeroProcesso, string $dataHora): bool
    {
        return $this->model
                                    ->where('numero_processo', $numeroProcesso)
                                    ->where('dataHora', $dataHora)
                                    ->first() !== null;
    }

    public function salvarMovimento(array $data): void
    {
        $result = $this->model->insert($data);
        if ($result) {
            $this->clearCache();
        }
    }
}
