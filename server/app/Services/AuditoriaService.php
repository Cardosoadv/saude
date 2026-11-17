<?php

namespace App\Services;

/**
 * Serviço responsável pelas operações de auditoria no sistema.
 *
 * Esta classe centraliza a lógica de negócios relacionada à auditoria,
 * delegando tarefas específicas para bibliotecas especializadas quando necessário.
 */
class AuditoriaService extends BaseService
{
    /**
     * Instância da biblioteca de auditoria de recebimento de intimações.
     *
     * @var \App\Libraries\Auditoria\AuditoriaRecebimentoIntimacoes
     */
    protected $auditoriaRecebimentoIntimacoes;

    /**
     * Construtor do AuditoriaService.
     *
     * Inicializa a instância da biblioteca AuditoriaRecebimentoIntimacoes.
     *
     * @param \App\Libraries\Auditoria\AuditoriaRecebimentoIntimacoes|null $auditoriaRecebimentoIntimacoes
     */
    public function __construct(?\App\Libraries\Auditoria\AuditoriaRecebimentoIntimacoes $auditoriaRecebimentoIntimacoes = null)
    {
        $this->auditoriaRecebimentoIntimacoes = $auditoriaRecebimentoIntimacoes ?? new \App\Libraries\Auditoria\AuditoriaRecebimentoIntimacoes();
    }

    /**
     * Registra o processamento de intimações.
     *
     * Delega a gravação dos dados de auditoria para a biblioteca especializada.
     *
     * @param array $dados Os dados a serem registrados na auditoria. Geralmente contém informações sobre o status, contagem e detalhes do processamento.
     * @return mixed O resultado da operação de registro.
     */
    public function registrarProcessamentoIntimacoes($dados)
    {
        return $this->auditoriaRecebimentoIntimacoes->registraProcessamentoIntimacoes($dados);
    }
}
