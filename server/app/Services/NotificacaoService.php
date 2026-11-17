<?php

namespace App\Services;

use App\Libraries\Email\EmailMarca;
use App\Repositories\NotificacaoRepository;
use App\Repositories\TarefasRepository;
use App\Repositories\UsuariosRepository;
use CodeIgniter\Email\Email;

/**
 * Serviço responsável pelo envio de notificações.
 * @version 1.0
 * @since 2025-12-21
 *
 * Esta classe gerencia o envio de notificações por e-mail para usuários do sistema,
 * cobrindo cenários como prazos de tarefas vencendo, atribuição de tarefas e mensagens gerais.
 */
class NotificacaoService extends BaseService
{
    /**
     * Repositório de responsáveis (usuários).
     *
     * @var UsuariosRepository
     */
    protected UsuariosRepository $usuariosRepository;

    /**
     * Repositório de tarefas, usado para resolver o link da entidade vinculada
     * (cliente) exibido nas notificações.
     *
     * @var TarefasRepository
     */
    protected TarefasRepository $tarefasRepository;

    /**
     * Serviço de e-mail do CodeIgniter.
     *
     * @var Email
     */
    protected $email;

    /**
     * Construtor do NotificacaoService.
     *
     * Inicializa os repositórios e o serviço de e-mail.
     *
     * @param NotificacaoRepository|null $repository
     * @param UsuariosRepository|null $usuariosRepository
     * @param TarefasRepository|null $tarefasRepository
     */
    public function __construct(
        ?NotificacaoRepository $repository = null,
        ?UsuariosRepository $usuariosRepository = null,
        ?TarefasRepository $tarefasRepository = null
    ) {
        $this->repository           = $repository ?? new NotificacaoRepository();
        $this->usuariosRepository   = $usuariosRepository ?? new UsuariosRepository();
        $this->tarefasRepository    = $tarefasRepository ?? new TarefasRepository();
        $this->email                = service('email');
    }

    /**
     * Notifica os responsáveis sobre tarefas com prazo vencendo.
     *
     * Busca tarefas prestes a vencer e envia e-mails para os responsáveis.
     *
     * @return array Retorna um array com contagem de 'enviadas' e 'falhas'.
     */
    public function notificarTarefasPrazoVencendo()
    {
        $tarefas = $this->repository->listarTarefasPrazoVencendo();
        $tarefas = $this->tarefasRepository->popularVinculos($tarefas);
        log_message('info', 'Pesquisando tarefas a notificar. Total encontrado: ' . count($tarefas));

        $notificacoesEnviadas = 0;
        $falhas = 0;

        foreach ($tarefas as $tarefa) {
            $resultado = $this->enviarNotificacao(
                $tarefa['responsavel'],
                'LAYOUT_TAREFA_PRAZO_VENCENDO',
                $tarefa
            );

            if ($resultado) {
                $notificacoesEnviadas++;
            } else {
                $falhas++;
            }
        }

        log_message('info', "Notificações enviadas: {$notificacoesEnviadas}, Falhas: {$falhas}");

        return [
            'enviadas' => $notificacoesEnviadas,
            'falhas' => $falhas,
        ];
    }

    /**
     * Envia uma notificação por e-mail para um usuário específico.
     *
     * @param int $userId ID do usuário destinatário.
     * @param string $tipoLayout Identificador do layout de e-mail a ser usado.
     * @param array $dados Dados para preencher o layout do e-mail.
     * @return bool True se enviado com sucesso, false em caso de erro.
     */
    public function enviarNotificacao($userId, $tipoLayout, $dados)
    {
        try {
            log_message('info', "Tentando enviar notificação para usuário ID: {$userId}");

            $email = $this->usuariosRepository->getEmailById($userId);

            if (!$email) {
                log_message('error', "Email não encontrado para usuário ID: {$userId}");
                return false;
            }

            // Monta o corpo (limpa o e-mail e os anexos anteriores) e devolve o assunto
            $assunto = $this->montarMensagem($tipoLayout, $dados);

            $this->email->setFrom('noreply@exemplo.com.br', config('Email')->fromName ?: 'Saúde - Gestão de Clínicas');
            $this->email->setTo($email);
            $this->email->setSubject($assunto);

            // Enviar o email
            if ($this->email->send()) {
                log_message('info', "Notificação enviada com sucesso para: {$email}");
                return true;
            } else {
                log_message('error', "Falha ao enviar email para: {$email}. Erro: " . $this->email->printDebugger(['headers']));
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', "Exceção ao enviar notificação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renderiza o corpo do e-mail conforme o layout escolhido e devolve o assunto.
     *
     * @param string $tipoLayout Identificador do layout.
     * @param array $dados Dados para interpolação no template.
     * @return string Assunto do e-mail.
     */
    private function montarMensagem($tipoLayout, $dados): string
    {
        $urlSistema = config('App')->frontURL;

        switch ($tipoLayout) {
            case 'LAYOUT_TAREFA_PRAZO_VENCENDO':
            case 'LAYOUT_TAREFA_ATRIBUIDA':
                $prazoVencendo = $tipoLayout === 'LAYOUT_TAREFA_PRAZO_VENCENDO';
                $prazo = $this->descreverPrazo($dados['prazo'] ?? null);

                EmailMarca::montar($this->email, 'emails/notificacao_tarefa', [
                    'modo'           => $prazoVencendo ? 'prazo' : 'atribuida',
                    'tarefa'         => (string) ($dados['tarefa'] ?? ''),
                    'detalhes'       => $prazoVencendo ? null : ($dados['detalhes'] ?? null),
                    'prazoFormatado' => $prazo['data'],
                    'situacaoPrazo'  => $prazo['situacao'],
                    'vinculo'        => $this->resumoVinculo($dados),
                    'urlSistema'     => $urlSistema,
                ], [
                    'titulo'    => $prazoVencendo ? 'Prazo de tarefa se aproximando' : 'Nova tarefa atribuída a você',
                    'etiqueta'  => $prazoVencendo ? 'Prazo' : 'Tarefa',
                    'tom'       => $prazoVencendo ? ($prazo['atrasado'] ? 'danger' : 'warning') : 'accent',
                    'preheader' => (string) ($dados['tarefa'] ?? ''),
                ]);

                return $prazoVencendo ? 'Tarefa com prazo vencendo' : 'Nova tarefa atribuída a você';

            default:
                EmailMarca::montar($this->email, 'emails/notificacao_generica', [
                    'urlSistema' => $urlSistema,
                ], [
                    'titulo'   => 'Nova notificação',
                    'etiqueta' => 'Aviso',
                    'tom'      => 'primary',
                ]);

                return 'Nova notificação';
        }
    }

    /**
     * Resume o cliente vinculado à tarefa.
     *
     * @param array $dados Dados da tarefa, já enriquecidos por TarefasRepository::popularVinculos.
     * @return array{rotulo: string, info: string, url: string, acao: string}|null
     */
    private function resumoVinculo(array $dados): ?array
    {
        if (empty($dados['vinculo_url'])) {
            return null;
        }

        $info = (string) ($dados['vinculo_info'] ?? '');

        return ['rotulo' => 'Cliente', 'info' => $info, 'url' => $dados['vinculo_url'], 'acao' => 'Ver cliente'];
    }

    /**
     * Formata o prazo e descreve a situação em relação a hoje.
     *
     * @return array{data: string, situacao: ?string, atrasado: bool}
     */
    private function descreverPrazo(?string $prazo): array
    {
        $timestamp = $prazo ? strtotime($prazo) : false;
        if ($timestamp === false) {
            return ['data' => '—', 'situacao' => null, 'atrasado' => false];
        }

        $dias = (int) round(($timestamp - strtotime('today')) / 86400);

        $situacao = match (true) {
            $dias < -1   => 'venceu há ' . abs($dias) . ' dias',
            $dias === -1 => 'venceu ontem',
            $dias === 0  => 'vence hoje',
            $dias === 1  => 'vence amanhã',
            default      => "vence em {$dias} dias",
        };

        return ['data' => date('d/m/Y', $timestamp), 'situacao' => $situacao, 'atrasado' => $dias < 0];
    }

    /**
     * Nome de exibição de um usuário, ou string vazia se não encontrado.
     *
     * @param int|string|null $userId
     */
    private function nomeUsuario($userId): string
    {
        if (empty($userId)) {
            return '';
        }

        $usuario = model('UserModel')->find((int) $userId);

        return trim((string) ($usuario->display_name ?? $usuario->username ?? ''));
    }
}
