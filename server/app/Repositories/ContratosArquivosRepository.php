<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ContratosArquivosModel;

/**
 * Repositório dos arquivos de contratos guardados no Google Drive.
 */
class ContratosArquivosRepository extends BaseRepository
{
    protected $cacheKey = 'contratos_arquivos';

    public function __construct(?ContratosArquivosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new ContratosArquivosModel();
    }

    public function listarPorContrato(int $contratoId): array
    {
        return $this->model
            ->where('contrato_id', $contratoId)
            ->orderBy('id_contrato_arquivo', 'ASC')
            ->findAll();
    }
}
