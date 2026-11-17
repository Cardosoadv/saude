<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MensagensModel;

class MensagensRepository extends BaseRepository
{
    public function __construct(?MensagensModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('MensagensModel');
        $this->cacheKey = 'mensagens';
    }

    /**
     * Busca uma mensagem pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->remember((string) $id, function () use ($id) {
            return $this->model->select('mensagens.*, remetente.username as remetente_nome, destinatario.username as destinatario_nome, processos.numero_processo as processo_numero, processos.titulo_processo as processo_titulo')
                ->join('users as remetente', 'mensagens.remetente_id = remetente.id', 'left')
                ->join('users as destinatario', 'mensagens.destinatario_id = destinatario.id', 'left')
                ->join('processos', 'mensagens.processo_id = processos.id_processo', 'left')
                ->find($id);
        });
    }

    /**
     * Busca mensagens com filtros e paginação.
     *
     * @param int    $userId
     * @param string|null $term
     * @param string|null $filter
     * @param string $sort
     * @param int    $perPage
     * @param int    $page
     * @return array{data: array, pager: \CodeIgniter\Pager\Pager}
     */
    public function search(int $userId, ?string $term, ?string $filter, string $sort, int $perPage, int $page = 1): array
    {
        $cacheSuffix = "search_{$userId}_" . md5(serialize([$term, $filter, $sort, $perPage, $page]));

        return $this->remember($cacheSuffix, function () use ($userId, $term, $filter, $sort, $perPage, $page) {
            $builder = $this->model->select('mensagens.*, remetente.username as remetente_nome, destinatario.username as destinatario_nome, processos.numero_processo as processo_numero, processos.titulo_processo as processo_titulo')
                ->join('users as remetente', 'mensagens.remetente_id = remetente.id', 'left')
                ->join('users as destinatario', 'mensagens.destinatario_id = destinatario.id', 'left')
                ->join('processos', 'mensagens.processo_id = processos.id_processo', 'left')
                ->groupStart()
                ->where('mensagens.destinatario_id', $userId)
                ->orWhere('mensagens.remetente_id', $userId)
                ->groupEnd();

            if ($term) {
                $builder->groupStart()
                    ->like('mensagens.assunto', $term)
                    ->orLike('mensagens.conteudo', $term)
                    ->groupEnd();
            }

            if ($filter === 'nao-lidas' || $filter === 'nao_lidas') {
                $builder->where('mensagens.data_leitura IS NULL', null, false);
            } elseif ($filter === 'lidas') {
                $builder->where('mensagens.data_leitura IS NOT NULL', null, false);
            }

            switch ($sort) {
                case 'antiga':
                case 'antigas':
                    $builder->orderBy('mensagens.data_envio', 'ASC');
                    break;
                case 'nao-lidas':
                case 'nao_lidas':
                    $builder->orderBy('mensagens.data_leitura', 'ASC')
                        ->orderBy('mensagens.data_envio', 'DESC');
                    break;
                case 'recente':
                default:
                    $builder->orderBy('mensagens.data_envio', 'DESC');
                    break;
            }

            return [
                'data'  => $builder->paginate($perPage, 'default', $page),
                'pager' => $this->model->pager,
            ];
        });
    }

    /**
     * Marca todas as mensagens de um destinatário como lidas.
     *
     * @param int $destinatarioId
     * @return bool
     */
    public function markAllAsRead(int $destinatarioId): bool
    {
        $result = (bool) $this->model->builder()
            ->where('destinatario_id', $destinatarioId)
            ->where('data_leitura IS NULL', null, false)
            ->set(['data_leitura' => date('Y-m-d H:i:s')])
            ->update();

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Conta o número de mensagens não lidas de um usuário.
     * Otimizado: O(1) query.
     *
     * @param int $userId
     * @return int
     */
    public function countUnread(int $userId): int
    {
        return (int) $this->remember("unread_count_{$userId}", function () use ($userId) {
            return $this->model
                ->where('destinatario_id', $userId)
                ->where('data_leitura IS NULL', null, false)
                ->countAllResults();
        });
    }

    /**
     * Busca mensagens não lidas de um usuário.
     * Otimizado com JOIN para o remetente e cache versionado.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUnreadMessages(int $userId, int $limit = 5): array
    {
        return $this->remember("unread_{$userId}_{$limit}", function () use ($userId, $limit) {
            return $this->model->select('mensagens.*, remetente.username as remetente_nome')
                ->join('users as remetente', 'mensagens.remetente_id = remetente.id', 'left')
                ->where('destinatario_id', $userId)
                ->where('data_leitura IS NULL', null, false)
                ->orderBy('data_envio', 'DESC')
                ->findAll($limit);
        });
    }

    /**
     * Lista mensagens trocadas entre dois usuários (thread).
     *
     * Quando `$processoId` é informado, a thread é escopada apenas às
     * mensagens originadas daquele processo — evita misturar conversas de
     * processos diferentes que compartilham o mesmo advogado responsável.
     */
    public function getThread(int $userId, int $otherUserId, ?int $processoId = null): array
    {
        $cacheSuffix = "thread_{$userId}_{$otherUserId}_" . ($processoId ?? 'all');

        return $this->remember($cacheSuffix, function () use ($userId, $otherUserId, $processoId) {
            $builder = $this->model->select('mensagens.*, remetente.username AS remetente_nome, destinatario.username AS destinatario_nome')
                ->join('users remetente', 'mensagens.remetente_id = remetente.id', 'left')
                ->join('users destinatario', 'mensagens.destinatario_id = destinatario.id', 'left')
                ->groupStart()
                    ->groupStart()
                        ->where('mensagens.remetente_id', $userId)
                        ->where('mensagens.destinatario_id', $otherUserId)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('mensagens.remetente_id', $otherUserId)
                        ->where('mensagens.destinatario_id', $userId)
                    ->groupEnd()
                ->groupEnd();

            if ($processoId !== null) {
                $builder->where('mensagens.processo_id', $processoId);
            }

            return $builder->orderBy('mensagens.data_envio', 'ASC')->findAll();
        });
    }

    /**
     * Marca como lidas todas as mensagens recebidas por `$userId` dentro de
     * uma thread com `$otherUserId`, opcionalmente restrita a um processo.
     *
     * @return bool True se alguma mensagem foi marcada como lida.
     */
    public function markThreadAsRead(int $userId, int $otherUserId, ?int $processoId = null): bool
    {
        $builder = $this->model->builder()
            ->where('destinatario_id', $userId)
            ->where('remetente_id', $otherUserId)
            ->where('data_leitura IS NULL', null, false);

        if ($processoId !== null) {
            $builder->where('processo_id', $processoId);
        }

        $result = (bool) $builder->set(['data_leitura' => date('Y-m-d H:i:s')])->update();

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Lista todas as mensagens de um processo onde o cliente logado foi remetente ou destinatário.
     * Isso garante que respostas de outros advogados (que não apenas o responsável principal)
     * sejam exibidas corretamente no painel do cliente.
     */
    public function getThreadProcessoCliente(int $clienteUserId, int $processoId): array
    {
        $cacheSuffix = "thread_cliente_{$clienteUserId}_{$processoId}";

        return $this->remember($cacheSuffix, function () use ($clienteUserId, $processoId) {
            return $this->model->select('mensagens.*, remetente.username AS remetente_nome, destinatario.username AS destinatario_nome')
                ->join('users remetente', 'mensagens.remetente_id = remetente.id', 'left')
                ->join('users destinatario', 'mensagens.destinatario_id = destinatario.id', 'left')
                ->groupStart()
                    ->where('mensagens.processo_id', $processoId)
                    ->orWhere('mensagens.processo_id IS NULL', null, false)
                ->groupEnd()
                ->groupStart()
                    ->where('mensagens.remetente_id', $clienteUserId)
                    ->orWhere('mensagens.destinatario_id', $clienteUserId)
                ->groupEnd()
                ->orderBy('mensagens.data_envio', 'ASC')
                ->findAll();
        });
    }

    /**
     * Marca como lidas todas as mensagens recebidas pelo cliente em um determinado processo,
     * independentemente de qual advogado as enviou.
     */
    public function markProcessMessagesAsRead(int $destinatarioUserId, int $processoId): bool
    {
        $result = (bool) $this->model->builder()
            ->where('destinatario_id', $destinatarioUserId)
            ->groupStart()
                ->where('processo_id', $processoId)
                ->orWhere('processo_id IS NULL', null, false)
            ->groupEnd()
            ->where('data_leitura IS NULL', null, false)
            ->set(['data_leitura' => date('Y-m-d H:i:s')])
            ->update();

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }
}
