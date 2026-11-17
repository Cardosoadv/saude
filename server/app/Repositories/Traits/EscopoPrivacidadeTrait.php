<?php

namespace App\Repositories\Traits;

use App\Services\AcessoEntidadeService;

/**
 * Privacidade granular nos repositórios: restringe consultas aos registros
 * visíveis para o usuário logado e separa o cache por usuário quando o escopo
 * está ativo. Sem efeito com a funcionalidade desligada, em contexto de
 * sistema ou para quem tem `privacidade.bypass`.
 */
trait EscopoPrivacidadeTrait
{
    protected ?AcessoEntidadeService $acessos = null;

    /**
     * @param \CodeIgniter\Database\BaseBuilder|\CodeIgniter\Model $builder
     *
     * @return \CodeIgniter\Database\BaseBuilder|\CodeIgniter\Model
     */
    protected function escopo($builder, string $tipo, ?string $alias = null)
    {
        return $this->acessos()->aplicarEscopo($builder, $tipo, $alias);
    }

    /**
     * Condição para SQL escrito à mão; null quando o escopo não se aplica.
     */
    protected function condicaoEscopo(string $tipo, string $alias): ?string
    {
        return $this->acessos()->condicaoSql($tipo, $alias);
    }

    /**
     * Sufixo da chave de cache: vazio quando o escopo não se aplica, senão
     * por usuário, para que ninguém receba a listagem filtrada para outro.
     */
    protected function sufixoEscopo(string $tipo): string
    {
        return $this->acessos()->ignoraEscopo(null, $tipo) ? '' : '_u' . user_id();
    }

    protected function acessos(): AcessoEntidadeService
    {
        return $this->acessos ??= new AcessoEntidadeService();
    }
}
