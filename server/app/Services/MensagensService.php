<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AIServiceInterface;
use App\Repositories\MensagensRepository;
use App\Services\NotificacaoService;
use App\Services\ProcessosService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;

class MensagensService extends BaseService
{
    private NotificacaoService $notificacaoService;
    private AIServiceInterface $aiService;
    private ProcessosService $processosService;
    private $db;

    public function __construct(
        ?MensagensRepository $repository = null,
        ?NotificacaoService $notificacaoService = null,
        ?AIServiceInterface $aiService = null,
        ?ProcessosService $processosService = null
    ) {
        $this->repository           = $repository ?? new MensagensRepository();
        $this->notificacaoService   = $notificacaoService ?? new NotificacaoService();
        $this->aiService            = $aiService ?? service('AIService');
        $this->processosService     = $processosService ?? new ProcessosService();
        $this->db                   = \Config\Database::connect();
    }

    /**
     * Lista mensagens para um usuário com filtros.
     *
     * @param int    $userId
     * @param string|null $term
     * @param string|null $filter
     * @param string $sort
     * @param int    $perPage
     * @return array{data: array, pager: \CodeIgniter\Pager\Pager}
     */
    public function listar(int $userId, ?string $term, ?string $filter, string $sort, int $perPage = 25, int $page = 1): array
    {
        /** @var MensagensRepository $repo */
        $repo = $this->repository;
        $resultado = $repo->search($userId, $term, $filter, $sort, $perPage, $page);
        $this->repository->getModel()->pager = $resultado['pager'];
        return $resultado['data'];
    }

    /**
     * Envia uma nova mensagem.
     *
     * @param int      $remetenteId
     * @param int      $destinatarioId
     * @param string   $assunto
     * @param string   $conteudo
     * @param int|null $processoId Processo de origem da mensagem, quando aplicável.
     * @return void
     * @throws Exception
     */
    public function enviar(int $remetenteId, int $destinatarioId, string $assunto, string $conteudo, ?int $processoId = null): void
    {
        // Se o processo_id não foi enviado e parece ser uma resposta (Re:), tenta inferir da mensagem original
        if ($processoId === null && stripos($assunto, 'Re: ') === 0) {
            $assuntoOriginal = trim(substr($assunto, 4));
            $original = $this->repository->getModel()
                ->where('assunto', $assuntoOriginal)
                ->groupStart()
                    ->groupStart()
                        ->where('remetente_id', $destinatarioId)
                        ->where('destinatario_id', $remetenteId)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('remetente_id', $remetenteId)
                        ->where('destinatario_id', $destinatarioId)
                    ->groupEnd()
                ->groupEnd()
                ->where('processo_id IS NOT NULL', null, false)
                ->orderBy('id', 'DESC')
                ->first();
            
            if ($original) {
                $processoId = (int) $original['processo_id'];
            }
        }

        $this->db->transStart();

        try {
            $data = [
                'remetente_id'    => $remetenteId,
                'destinatario_id' => $destinatarioId,
                'processo_id'     => $processoId,
                'conteudo'        => $conteudo,
                'assunto'         => $assunto,
                'data_envio'      => date('Y-m-d H:i:s'),
            ];

            $insertedId = $this->repository->create($data);

            if (!$insertedId) {
                throw new Exception("Falha ao salvar a mensagem.");
            }

            // Envia a notificação (não interrompe o fluxo se o e-mail falhar)
            try {
                $this->notificacaoService->enviarNotificacao(
                    $destinatarioId,
                    'LAYOUT_MENSAGEM_RECEBIDA',
                    [
                        'assunto'      => $assunto,
                        'conteudo'     => $conteudo,
                        'remetente_id' => $remetenteId,
                        'processo_id'  => $processoId,
                        'mensagem_id'  => $insertedId,
                    ]
                );
            } catch (Exception $e) {
                log_message('warning', '[MensagensService::enviar] Erro ao enviar notificação por email: ' . $e->getMessage());
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new DatabaseException("Erro ao processar transação de envio de mensagem.");
            }
        } catch (Exception $e) {
            $this->db->transRollback();
            log_message('error', '[MensagensService::enviar] Erro: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marca uma mensagem como lida.
     *
     * @param int $id Mensagem ID
     * @param int $userId ID do usuário que está tentando ler
     * @return void
     * @throws Exception
     */
    public function marcarLida(int $id, int $userId): void
    {
        $mensagem = $this->repository->findById($id);

        if (!$mensagem) {
            throw new Exception("Mensagem não encontrada.");
        }

        if ((int) $mensagem['destinatario_id'] !== $userId) {
            throw new Exception("Acesso negado.");
        }

        if ($mensagem['data_leitura'] !== null) {
            return; // Já lida
        }

        if (!$this->repository->update($id, ['data_leitura' => date('Y-m-d H:i:s')])) {
            throw new Exception("Erro ao marcar mensagem como lida.");
        }
    }

    /**
     * Marca todas as mensagens de um usuário como lidas.
     *
     * @param int $userId
     * @return void
     * @throws Exception
     */
    public function marcarTodasLidas(int $userId): void
    {
        $this->db->transStart();

        try {
            $this->repository->markAllAsRead($userId);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new DatabaseException("Erro ao marcar todas as mensagens como lidas.");
            }
        } catch (Exception $e) {
            $this->db->transRollback();
            log_message('error', '[MensagensService::marcarTodasLidas] Erro: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lê uma mensagem (retorna os dados e marca como lida se necessário).
     *
     * @param int $id
     * @param int $userId
     * @return array
     * @throws Exception
     */
    public function ler(int $id, int $userId): array
    {
        $mensagem = $this->repository->findById($id);

        if (!$mensagem) {
            throw new Exception("Mensagem não encontrada.");
        }

        /**
         * Lógica de Permissão e Leitura:
         * 1. Apenas o destinatário e o remetente podem visualizar a mensagem.
         * 2. Somente quando o destinatário visualiza a mensagem pela primeira vez, ela é marcada como lida.
         */

        // Marca como lida se o usuário for o destinatário e a mensagem ainda não tiver sido lida
        if ((int) $mensagem['destinatario_id'] === $userId && $mensagem['data_leitura'] === null) {
            $this->marcarLida($id, $userId);
            $mensagem['data_leitura'] = date('Y-m-d H:i:s');
        }

        // Bloqueia acesso se o usuário não for nem o remetente nem o destinatário
        if ((int) $mensagem['destinatario_id'] !== $userId && (int) $mensagem['remetente_id'] !== $userId) {
            throw new Exception("Acesso negado à mensagem.");
        }

        return $mensagem;
    }

    /**
     * Obtém as mensagens não lidas de um usuário.
     *
     * @param int $userId
     * @return array
     */
    public function getMensagensNaoLidas(int $userId): array
    {
        return $this->repository->getUnreadMessages($userId);
    }

    /**
     * Conta as mensagens não lidas de um usuário.
     *
     * @param int $userId
     * @return int
     */
    public function countUnread(int $userId): int
    {
        /** @var MensagensRepository $repo */
        $repo = $this->repository;
        return $repo->countUnread($userId);
    }

    /**
     * Exclui uma mensagem se o usuário for o remetente ou destinatário.
     *
     * @param int $id
     * @param int $userId
     * @return void
     * @throws Exception
     */
    public function excluir(int $id, int $userId): void
    {
        $mensagem = $this->repository->findById($id);

        if (!$mensagem) {
            throw new Exception("Mensagem não encontrada.");
        }

        if ((int) $mensagem['destinatario_id'] !== $userId && (int) $mensagem['remetente_id'] !== $userId) {
            throw new Exception("Sem permissão para excluir esta mensagem.");
        }

        if (!$this->repository->delete($id)) {
            throw new Exception("Erro ao excluir mensagem.");
        }
    }

    /**
     * Classifica a urgência e o sentimento de uma mensagem via IA.
     *
     * @param int $id Mensagem ID
     * @param int $userId ID do usuário que está solicitando a análise
     * @return array [urgencia, sentimento, justificativa, score_confianca]
     * @throws Exception
     */
    public function classificarUrgencia(int $id, int $userId): array
    {
        $mensagem = $this->obterMensagemComPermissao($id, $userId);
        $contexto = $this->buildAIContext($mensagem);

        return $this->aiService->classifyMessageUrgency(
            (string) $mensagem['conteudo'],
            (string) ($mensagem['assunto'] ?? ''),
            $contexto
        );
    }

    /**
     * Gera uma sugestão de resposta para uma mensagem via IA, para revisão do advogado.
     *
     * @param int $id Mensagem ID
     * @param int $userId ID do usuário que está solicitando a sugestão
     * @return string Rascunho de resposta sugerido.
     * @throws Exception
     */
    public function sugerirResposta(int $id, int $userId): string
    {
        $mensagem = $this->obterMensagemComPermissao($id, $userId);
        $contexto = $this->buildAIContext($mensagem);

        return $this->aiService->draftMessageReply(
            (string) $mensagem['conteudo'],
            (string) ($mensagem['assunto'] ?? ''),
            $contexto
        );
    }

    /**
     * Busca uma mensagem garantindo que o usuário seja remetente ou destinatário.
     *
     * @param int $id
     * @param int $userId
     * @return array
     * @throws Exception
     */
    private function obterMensagemComPermissao(int $id, int $userId): array
    {
        $mensagem = $this->repository->findById($id);

        if (!$mensagem) {
            throw new Exception("Mensagem não encontrada.");
        }

        if ((int) $mensagem['destinatario_id'] !== $userId && (int) $mensagem['remetente_id'] !== $userId) {
            throw new Exception("Acesso negado à mensagem.");
        }

        return $mensagem;
    }

    /**
     * Monta o contexto do processo vinculado à mensagem para enriquecer as chamadas de IA.
     *
     * @param array $mensagem
     * @return array
     */
    private function buildAIContext(array $mensagem): array
    {
        if (empty($mensagem['processo_id'])) {
            return [];
        }

        try {
            $detalhes = $this->processosService->buscarDetalhesProcesso((int) $mensagem['processo_id']);
            return !empty($detalhes['processo']) ? ['processo' => $detalhes['processo']] : [];
        } catch (Exception $e) {
            log_message('warning', '[MensagensService::buildAIContext] Erro ao buscar contexto do processo: ' . $e->getMessage());
            return [];
        }
    }
}
