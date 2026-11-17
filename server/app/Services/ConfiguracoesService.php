<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ConfiguracoesRepository;

/**
 * Serviço para gerenciar a lógica de negócio de configurações.
 * 
 * @author Cardoso
 * @version 1.0.0
 * @since 2026-05-09
 */
class ConfiguracoesService extends BaseService
{
    /**
     * @param ConfiguracoesRepository|null $repository
     */
    public function __construct(?ConfiguracoesRepository $repository = null)
    {
        $this->repository = $repository ?? new ConfiguracoesRepository();
    }

    /**
     * Busca uma configuração pelo nome.
     *
     * @param string $nome
     * @return array|null
     */
    public function findByNome(string $nome): ?array
    {
        return $this->repository->findByNome($nome);
    }

    /**
     * Retorna o valor de uma configuração.
     *
     * @param string $nome
     * @param mixed $default
     * @return mixed
     */
    public function getValor(string $nome, $default = null)
    {
        return $this->repository->getValor($nome, $default);
    }
}
