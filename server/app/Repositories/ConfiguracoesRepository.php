<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ConfiguracoesModel;

/**
 * Repositório para gerenciar configurações com suporte a cache.
 * 
 * @author Cardoso
 * @version 1.0.0
 * @since 2026-05-09
 */
class ConfiguracoesRepository extends BaseRepository
{
    /**
     * @var string Chave base para o cache.
     */
    protected $cacheKey = 'configuracoes';

    /**
     * @param ConfiguracoesModel|null $model
     */
    public function __construct(?ConfiguracoesModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new ConfiguracoesModel();
    }

    /**
     * Busca uma configuração pelo nome.
     *
     * @param string $nome Nome da configuração.
     * @return array|null
     */
    public function findByNome(string $nome): ?array
    {
        return $this->remember("nome_{$nome}", function () use ($nome) {
            return $this->model->where('nome', $nome)->first();
        });
    }

    /**
     * Retorna o valor de uma configuração pelo nome.
     *
     * @param string $nome Nome da configuração.
     * @param mixed $default Valor padrão caso não encontrada.
     * @return mixed
     */
    public function getValor(string $nome, $default = null)
    {
        $config = $this->findByNome($nome);
        return $config ? $config['valor'] : $default;
    }
}
