<?php

namespace App\Repositories;

use App\Models\ProcessosModel;

/**
 * Lawsuit queries for the Client Portal (Área do Cliente).
 *
 * Every query is restricted by the client id instead of the granular
 * privacy scope, because the portal user is the client, not a lawyer.
 * Shares the 'processos' cache key with ProcessosRepository, so portal data
 * is refreshed whenever a lawsuit changes.
 */
class ClientPortalProcessesRepository extends BaseRepository
{
    /**
     * @var \App\Models\ProcessosMovimentosModel
     */
    protected $movementsModel;

    public function __construct(?ProcessosModel $model = null)
    {
        parent::__construct();
        $this->cacheKey       = 'processos';
        $this->model          = $model ?? model('ProcessosModel');
        $this->movementsModel = model('ProcessosMovimentosModel');
    }

    /**
     * Lista todos os processos do cliente com movimentos recentes e dados financeiros.
     */
    public function listarProcessosClienteFull(int $clienteId): array
    {
        return $this->remember("cliente_full_{$clienteId}", function () use ($clienteId) {
            $processos = $this->model
                ->select([
                    'processos.id_processo',
                    'processos.numero_processo',
                    'processos.titulo_processo',
                    'processos.encerrado',
                    'processos.valorCausa',
                    'processos.valorCondenacao',
                    'processos.resultado',
                    'processos.risco',
                    'processos.arquived_at',
                    'jrd.nome AS ramo_direito',
                    'u.username AS responsavel_nome',
                    'ai.secret AS responsavel_email',
                    'u.id AS responsavel_user_id',
                ])
                ->join('juris_ramos_direito jrd', 'processos.ramo_direito = jrd.id', 'left')
                ->join('users u', 'processos.responsavel_id = u.id', 'left')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->where('processos.cliente_id', $clienteId)
                ->orderBy('processos.id_processo', 'DESC')
                ->findAll();

            foreach ($processos as &$processo) {
                $processo['status'] = $this->resolverStatusProcesso($processo);
                $processo['movimentos_recentes'] = $this->buscarMovimentosRecentes($processo['numero_processo'] ?? '');
            }

            return $processos;
        });
    }

    /**
     * Retorna os detalhes completos de um processo para a Área do Cliente.
     */
    public function detalharProcessoParaCliente(int $clienteId, int $processoId): ?array
    {
        return $this->remember("detalhes_cliente_{$clienteId}_{$processoId}", function () use ($clienteId, $processoId) {
            $processo = $this->model
                ->select([
                    'processos.*',
                    'jrd.nome AS ramo_direito_nome',
                    'u.username AS responsavel_nome',
                    'ai.secret AS responsavel_email',
                    'u.id AS responsavel_user_id',
                    'c.nome AS nome_cliente',
                ])
                ->join('juris_ramos_direito jrd', 'processos.ramo_direito = jrd.id', 'left')
                ->join('users u', 'processos.responsavel_id = u.id', 'left')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->join('clientes c', 'processos.cliente_id = c.id_cliente', 'left')
                ->where('processos.id_processo', $processoId)
                ->where('processos.cliente_id', $clienteId)
                ->first();

            if (!$processo) {
                return null;
            }

            $numero = $processo['numero_processo'] ?? '';

            $movimentos = $this->movementsModel
                ->where('numero_processo', $numero)
                ->orderBy('dataHora', 'DESC')
                ->limit(20)
                ->findAll();

            // Ocultar relatório final se não estiver publicado
            if (isset($processo['relatorio_final_publicado']) && (int) $processo['relatorio_final_publicado'] !== 1) {
                $processo['relatorio_final'] = null;
            }

            $processo['status'] = $this->resolverStatusProcesso($processo);

            return [
                'processo'   => $processo,
                'movimentos' => $movimentos,
            ];
        });
    }

    /**
     * Busca os dados brutos (processo + movimentos) necessários para montar a
     * linha do tempo simplificada exibida no Portal do Cliente.
     *
     * Ao contrário de `detalharProcessoParaCliente`, não limita os movimentos
     * a 20 registros — a timeline do cliente cobre todo o histórico do caso.
     */
    public function timelineCliente(int $clienteId, int $processoId): ?array
    {
        return $this->remember("timeline_cliente_{$clienteId}_{$processoId}", function () use ($clienteId, $processoId) {
            $processo = $this->model
                ->select([
                    'processos.id_processo',
                    'processos.numero_processo',
                    'processos.titulo_processo',
                    'processos.encerrado',
                    'processos.dataDistribuicao',
                    'processos.data_encerramento',
                    'processos.arquived_at',
                ])
                ->where('processos.id_processo', $processoId)
                ->where('processos.cliente_id', $clienteId)
                ->first();

            if (!$processo) {
                return null;
            }

            $numero = $processo['numero_processo'] ?? '';

            $movimentos = $numero !== ''
                ? $this->movementsModel
                    ->select(['nome', 'nome_complemento', 'descricao_complemento', 'dataHora', 'sentiment'])
                    ->where('numero_processo', $numero)
                    ->orderBy('dataHora', 'ASC')
                    ->findAll(200)
                : [];

            return [
                'processo'   => $processo,
                'movimentos' => $movimentos,
            ];
        });
    }

    /**
     * Resolve o status textual do processo para exibição ao cliente.
     */
    private function resolverStatusProcesso(array $processo): string
    {
        if (!empty($processo['arquived_at'])) {
            return 'Arquivado';
        }

        if ((int) ($processo['encerrado'] ?? 0) === 1) {
            return 'Encerrado';
        }

        return 'Em andamento';
    }

    /**
     * Busca os 5 movimentos mais recentes de um processo pelo número.
     */
    public function buscarMovimentosRecentes(string $numeroProcesso): array
    {
        if (empty($numeroProcesso)) {
            return [];
        }

        return $this->movementsModel
            ->select(['dataHora AS data', 'nome'])
            ->where('numero_processo', $numeroProcesso)
            ->orderBy('dataHora', 'DESC')
            ->limit(5)
            ->findAll();
    }
}
