<?php

namespace App\Repositories;

use App\Models\IntimacoesModel;
use App\Models\IntimacoesDestinatariosModel;
use App\Models\IntimacoesAdvogadosModel;
use App\Models\IntimacoesTratamentoModel;
use App\Models\TarefasModel;

class IntimacoesRepository extends BaseRepository
{
    private IntimacoesDestinatariosModel $destinatariosModel;
    private IntimacoesAdvogadosModel     $advogadosModel;
    private IntimacoesTratamentoModel    $tratamentoModel;
    private TarefasModel                 $tarefasModel;
    private ProcessosRepository          $processosRepository;



    public function __construct(?IntimacoesModel $model = null)
    {
        parent::__construct();
        $this->cacheKey = 'intimacoes';
        $this->model                    = $model ?? model('IntimacoesModel');
        $this->destinatariosModel       = model('IntimacoesDestinatariosModel');
        $this->advogadosModel           = model('IntimacoesAdvogadosModel');
        $this->tratamentoModel          = model('IntimacoesTratamentoModel');
        $this->tarefasModel             = model('TarefasModel');
        $this->processosRepository      = new ProcessosRepository();
    }

    /**
     * Busca todas as intimações.
     * @return array dados das intimações incluindo Destinatários e Advogados
     */
    public function buscarTodasIntimacoes(): array
    {
        return $this->remember('todas_full', function () {
            $intimacoes = $this->model->findAll();

            if (empty($intimacoes)) {
                return [];
            }

            // Extrai todos os IDs das intimações
            $intimacaoIds = array_column($intimacoes, 'id_intimacao');

            // Busca todos os destinatários de uma vez
            $todosDestinatarios = $this->destinatariosModel
                ->select('nome, polo, comunicacao_id')
                ->whereIn('comunicacao_id', $intimacaoIds)
                ->findAll();

            // Busca todos os advogados de uma vez
            $todosAdvogados = $this->advogadosModel
                ->select('id, advogado_id, advogado_nome, advogado_oab, advogado_oab_uf, comunicacao_id')
                ->whereIn('comunicacao_id', $intimacaoIds)
                ->findAll();

            // Agrupa destinatários por comunicacao_id
            $destinatariosPorId = [];
            foreach ($todosDestinatarios as $destinatario) {
                $destinatariosPorId[$destinatario['comunicacao_id']][] = $destinatario;
            }

            // Agrupa advogados por comunicacao_id
            $advogadosPorId = [];
            foreach ($todosAdvogados as $advogado) {
                $advogadosPorId[$advogado['comunicacao_id']][] = $advogado;
            }

            // Associa os dados às intimações
            foreach ($intimacoes as &$intimacao) {
                $id = $intimacao['id_intimacao'];
                $intimacao['destinatarios'] = $destinatariosPorId[$id] ?? [];
                $intimacao['advogados'] = $advogadosPorId[$id] ?? [];
            }
            unset($intimacao);

            return $intimacoes;
        });
    }

    /**
     * Busca todas as intimações incluindo dados do tratamento.
     * Ordena por data de disponibilização.
     * @return array dados das intimações
     */
    public function buscarIntimacoes(): array
    {
        return $this->remember('all_dados', function () {
            return $this->model
                ->join('intimacoes_tratamento as it', 'it.intimacao_id = intimacoes.id_intimacao', 'left')
                ->join('intimacoes_tipos_tratamento as itt', 'itt.id_tratamento = it.tratamento_id', 'left')
                ->orderBy('intimacoes.data_disponibilizacao', 'DESC')
                ->findAll();
        });
    }

    /**
     * Conta o número de intimações não tratadas.
     * Otimizado: Realiza um COUNT(*) via SQL (O(1)) em vez de carregar registros para PHP (O(N)).
     *
     * @return int
     */
    public function contarNaoTratadas(): int
    {
        return (int) $this->remember('count_nao_tratadas', function () {
            return $this->model->builder()
                ->join('intimacoes_tratamento as it', 'it.intimacao_id = intimacoes.id_intimacao', 'left')
                ->where('it.tratamento_id IS NULL')
                ->countAllResults();
        });
    }

    /**
     * Busca as intimações não tratadas incluindo dados do tratamento.
     * Ordena por data de disponibilização.
     * @return array dados das intimações
     */
    public function buscarIntimacoesNaoTratadas(): array
    {
        return $this->remember('naoTratadas_dados', function () {
            return $this->model
                ->join('intimacoes_tratamento as it', 'it.intimacao_id = intimacoes.id_intimacao', 'left')
                ->join('intimacoes_tipos_tratamento as itt', 'itt.id_tratamento = it.tratamento_id', 'left')
                ->where('it.tratamento_id IS NULL')
                ->orderBy('intimacoes.data_disponibilizacao', 'DESC')
                ->findAll();
        });
    }

    /**
     * Busca todas as intimações não sincronizadas.
     * @return array dados das intimações com o array de Destinatarios e Advogados
     */
    public function buscarIntimacoesNaoSincronizadas($limit = null): array
    {
        $intimacoes = $this->model->where('synchronize_at IS NULL')
                                  ->limit($limit)
                                  ->get()->getResultArray();

        if (empty($intimacoes)) {
            return [];
        }

        // Extrai todos os IDs das intimações para busca em lote (evita N+1 queries)
        $intimacaoIds = array_column($intimacoes, 'id_intimacao');

        // Busca todos os destinatários de uma vez
        $todosDestinatarios = $this->destinatariosModel
            ->select('nome, polo, comunicacao_id')
            ->whereIn('comunicacao_id', $intimacaoIds)
            ->findAll();

        // Busca todos os advogados de uma vez
        $todosAdvogados = $this->advogadosModel
            ->select('id, comunicacao_id, advogado_id, advogado_nome, advogado_oab, advogado_oab_uf, created_at, updated_at')
            ->whereIn('comunicacao_id', $intimacaoIds)
            ->findAll();

        // Agrupa destinatários por comunicacao_id para associação eficiente em O(1)
        $destinatariosPorId = [];
        foreach ($todosDestinatarios as $destinatario) {
            $destinatariosPorId[$destinatario['comunicacao_id']][] = $destinatario;
        }

        // Agrupa advogados por comunicacao_id para associação eficiente em O(1)
        $advogadosPorId = [];
        foreach ($todosAdvogados as $advogado) {
            $advogadosPorId[$advogado['comunicacao_id']][] = $advogado;
        }

        // Associa os dados às intimações
        foreach ($intimacoes as &$intimacao) {
            $id = $intimacao['id_intimacao'];
            $intimacao['destinatarios'] = $destinatariosPorId[$id] ?? [];
            $intimacao['advogados'] = $advogadosPorId[$id] ?? [];
        }
        unset($intimacao);

        return $intimacoes;
    }

    public function detalharIntimacao(int $id): array
    {
        return $this->remember("detalhe_{$id}_user_" . user_id(), function () use ($id) {
            $intimacao = $this->model
                ->join('intimacoes_tratamento as it', 'it.intimacao_id = intimacoes.id_intimacao', 'left')
                ->join('intimacoes_tipos_tratamento as itt', 'itt.id_tratamento = it.tratamento_id', 'left')
                ->where('id_intimacao', $id)
                ->first();

            if (!$intimacao) {
                return [];
            }

            $intimacao['destinatarios'] = $this->destinatariosModel
                ->distinct()
                ->where('comunicacao_id', $id)
                ->select(['nome', 'polo', 'comunicacao_id'])
                ->orderBy('polo')
                ->orderBy('nome')
                ->findAll();

            $intimacao['advogados'] = $this->advogadosModel
                ->distinct()
                ->where('comunicacao_id', $id)
                ->select(['id', 'advogado_id', 'advogado_nome', 'advogado_oab', 'advogado_oab_uf'])
                ->orderBy('advogado_nome')
                ->findAll();

            $idProcesso = $this->processosRepository->getIdporNumeroProcesso($intimacao['numero_processo']);
            log_message('debug', 'Buscando tarefas para o processo ID: ' . $idProcesso . ' da intimação ID: ' . $id);
            $intimacao['tarefas'] = $this->tarefasModel->where('processo_id', $idProcesso)->whereNotIn('status', [4, 5])->get()->getResultArray();

            return $intimacao;
        });
    }

    /**
     * Verifica se uma intimação já existe no banco de dados.
     * @param string $id ID da intimação.
     * @return bool True se a intimação existe, False caso contrário.
     */
    public function intimacaoJaExiste(string $id): bool
    {
        return $this->model->intimacaoJaExiste($id);
    }

    /**
     * Salva os dados de uma intimação no banco de dados.
     *
     * @param array $data Dados da intimação.
     * @return array Dados da intimação com o ID gerado.
     */
    public function salvarIntimacao(array $data): array
    {
        $this->model->insert($data);
        $insertedId = $this->model->insertID();
        if ($insertedId) {
            $data['id_intimacao'] = $insertedId;
        }
        $this->clearCache();
        return $data;
    }

    /**
     * Busca intimações em um período específico.
     *
     * @param int $dias Número de dias para retroceder na busca.
     * @return array Lista de intimações no período.
     */
    public function buscarIntimacoesPorPeriodo(int $dias): array
    {
        return $this->remember("periodo_{$dias}", function () use ($dias) {
            $hoje = date('Y-m-d', time());
            $dataInicial = date('Y-m-d', strtotime('-' . $dias . ' days'));
            return $this->model->getIntimacoesdoPeriodo($dataInicial, $hoje);
        });
    }

    /**
     * Salva os dados de um destinatário de intimação no banco de dados.
     * @param array $destinatario Dados do destinatário.
     * @return void
     */
    public function salvarDestinatario(array $destinatario): void
    {
        $this->destinatariosModel->insert($destinatario);
        $this->clearCache();
    }

    /**
     * Salva os dados de um advogado de intimação no banco de dados.
     * @param array $advogado Dados do advogado
     * @return void
     */
    public function salvarAdvogado(array $advogado): void
    {
        $this->advogadosModel->save($advogado);
        $this->clearCache();
    }

    /**
     * Insere, em lote, os destinatários de uma intimação no banco de dados.
     * @param array $advogados Lista de advogados.
     * @return void
     */
    public function advogadosInsertBatch(array $advogados): void
    {
        $this->advogadosModel->insertBatch($advogados);
        $this->clearCache();
    }

    /**
     * Insere, em lote, os advogados de uma intimação no banco de dados.
     * @param array $destinatarios Lista de destinatários.
     * @return void
     */
    public function destinatariosInsertBatch(array $destinatarios): void
    {
        $this->destinatariosModel->insertBatch($destinatarios);
        $this->clearCache();
    }

    /**
     * Registra a sincronização de uma intimação no banco de dados.
     * @param int $id ID da intimação.
     * @return void
     */
    public function registrarSincronizacao(int $id): void
    {
        // Atualiza o campo de sincronização para a data atual
        $this->model->registrarSincronizacao($id);
        $this->clearCache();
    }

    public function tratarIntimacao($idIntimacao, $idTratamento): void
    {
        $this->tratamentoModel->tratarIntimacao($idIntimacao, $idTratamento);
        $this->clearCache();
    }

    public function getIdsIntimacoesPorNumeroProcesso(string $numeroProcesso): array
    {
        return $this->remember("ids_processo_{$numeroProcesso}", function () use ($numeroProcesso) {
            return $this->model->select('id_intimacao')
                ->where('numero_processo', $numeroProcesso)
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Busca todos os tipos de tratamento ordenados por nome.
     * @return array tipos de tratamento
     */
    public function buscarTodosTiposTratamento(): array
    {
        return $this->remember('todos_tipos_tratamento', function () {
            return $this->tratamentoModel->orderBy('tratamento')->findAll();
        });
    }

    /**
     * Limpa o cache de intimações.
     * Segue o padrão de versionamento de cache do BaseRepository.
     *
     * @return void
     */
    public function limparCacheIntimacoes(): void
    {
        $this->clearCache();
    }
}
