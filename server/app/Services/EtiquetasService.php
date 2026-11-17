<?php

namespace App\Services;

use App\Repositories\EtiquetasRepository;

/**
 * Serviço para gerenciar etiquetas.
 */
class EtiquetasService extends BaseService
{


    public function __construct(?EtiquetasRepository $repository = null)
    {
        $this->repository = $repository ?? new EtiquetasRepository();
    }

    /**
     * Salva uma etiqueta.
     *
     * @param array $data
     * @return int
     */
    public function salvarEtiqueta(array $data): int
    {
        return $this->repository->salvarEtiqueta($data);
    }

    /**
     * Deleta uma etiqueta.
     *
     * @param int $id
     * @return bool
     */
    public function deletarEtiqueta(int $id): bool
    {
        return $this->repository->deletarEtiqueta($id);
    }

    /**
     * Lista todas as etiquetas.
     *
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public function listarEtiquetas(string $orderby = 'nome', string $order = 'asc'): array
    {
        return $this->repository->listarEtiquetas($orderby, $order);
    }

    /**
     * Busca uma etiqueta pelo nome.
     *
     * @param string|null $nome
     * @return array|null
     */
    public function getByNome(?string $nome): ?array
    {
        return $this->repository->getByNome($nome);
    }

    /**
     * Retorna o ID da última inserção.
     *
     * @return int
     */
    public function getInsertID(): int
    {
        return $this->repository->getInsertID();
    }

    /**
     * Encontra ou cria uma etiqueta pelo nome.
     *
     * @param string $nome
     * @param string $cor
     * @return int ID da etiqueta
     */
    public function encontrarOuCriar(string $nome, string $cor = '6c757d'): int
    {
        $etiqueta = $this->getByNome($nome);

        if ($etiqueta) {
            return (int) $etiqueta['id_etiqueta'];
        }

        return $this->salvarEtiqueta([
            'nome' => $nome,
            'cor'  => $cor
        ]);
    }
}
