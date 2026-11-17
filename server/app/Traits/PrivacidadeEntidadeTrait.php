<?php

namespace App\Traits;

use App\Services\AcessoEntidadeService;

/**
 * Callbacks de Model para a privacidade granular.
 *
 * O Model que usa este trait deve declarar `protected string $tipoEntidadeAcesso`
 * ('processo', 'cliente' ou 'contrato') e registrar os callbacks desejados.
 */
trait PrivacidadeEntidadeTrait
{
    /**
     * afterInsert: quem cadastra passa a ter acesso ao registro (que nasce restrito).
     */
    protected function concederAcessoAoCriador(array $data): array
    {
        $id = $data['id'] ?? null;

        if ($id && ($data['result'] ?? true)) {
            (new AcessoEntidadeService())->concederAoCriador($this->tipoEntidadeAcesso, (int) $id);
        }

        return $data;
    }

    /**
     * beforeInsert: sem responsável informado, o responsável é quem cadastra
     * (desde que seja um advogado ativo). Em contexto de sistema, usa o
     * responsável padrão configurado.
     */
    protected function definirResponsavelPadrao(array $data): array
    {
        if (!empty($data['data']['responsavel_id'])) {
            return $data;
        }

        $acessos = new AcessoEntidadeService();
        $userId  = user_id();

        if ($userId) {
            if ($acessos->ehAdvogadoAtivo((int) $userId)) {
                $data['data']['responsavel_id'] = (int) $userId;
            }

            return $data;
        }

        return $this->definirResponsavelDoSistema($data);
    }

    /**
     * beforeInsert: registros criados sem usuário logado (crons, filas,
     * comandos — ex.: processos importados do DJE) recebem o responsável
     * padrão configurado em `privacidade_responsavel_padrao`. Sem isso, um
     * registro restrito sem responsável ficaria visível só para admins.
     */
    protected function definirResponsavelDoSistema(array $data): array
    {
        if (!empty($data['data']['responsavel_id']) || user_id()) {
            return $data;
        }

        $padrao = (new AcessoEntidadeService())->responsavelPadraoDoSistema();
        if ($padrao !== null) {
            $data['data']['responsavel_id'] = $padrao;
        }

        return $data;
    }
}
