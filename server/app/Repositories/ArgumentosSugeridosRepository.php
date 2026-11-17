<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProcessoArgumentosModel;
use App\Models\ArgumentosModel;

class ArgumentosSugeridosRepository extends BaseRepository
{
    protected $argumentosModel;

    public function __construct(?ProcessoArgumentosModel $model = null, ?ArgumentosModel $argumentosModel = null)
    {
        parent::__construct();
        $this->cacheKey = 'argumentos_sugeridos';
        // O repositório vai usar o pivot model como principal para operações de vínculo
        $this->model = $model ?? model('ProcessoArgumentosModel');
        $this->argumentosModel = $argumentosModel ?? model('ArgumentosModel');
    }

    /**
     * Remove fisicamente os vínculos de argumentos de um processo.
     */
    public function deletarPorProcesso(int $processoId): bool
    {
        $result = $this->model->where('processo_id', $processoId)->delete();
        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }

    /**
     * Busca os argumentos salvos de um processo (fazendo JOIN).
     */
    public function buscarPorProcesso(int $processoId): array
    {
        return $this->remember("processo_{$processoId}", function () use ($processoId) {
            return $this->model->select('processo_argumentos.*, argumentos.titulo, argumentos.ramo_id, ramos.nome as ramo_nome, argumentos.estrategia_recomendada, argumentos.teses_relacionadas')
                               ->join('argumentos', 'argumentos.id_argumento = processo_argumentos.argumento_id')
                               ->join('juris_ramos_direito ramos', 'ramos.id = argumentos.ramo_id', 'left')
                               ->where('processo_argumentos.processo_id', $processoId)
                               ->orderBy('processo_argumentos.id_processo_argumento', 'ASC')
                               ->findAll();
        });
    }

    /**
     * Busca os argumentos salvos de um caso (fazendo JOIN).
     */
    public function buscarPorCaso(int $casoId): array
    {
        return $this->remember("caso_{$casoId}", function () use ($casoId) {
            return $this->model->select('processo_argumentos.*, argumentos.titulo, argumentos.ramo_id, ramos.nome as ramo_nome, argumentos.estrategia_recomendada, argumentos.teses_relacionadas')
                               ->join('argumentos', 'argumentos.id_argumento = processo_argumentos.argumento_id')
                               ->join('juris_ramos_direito ramos', 'ramos.id = argumentos.ramo_id', 'left')
                               ->where('processo_argumentos.caso_id', $casoId)
                               ->orderBy('processo_argumentos.id_processo_argumento', 'ASC')
                               ->findAll();
        });
    }

    /**
     * Salva um lote de argumentos. Cria em "argumentos" e vincula em "processo_argumentos".
     */
    public function salvarLote(int $processoId, array $argumentos, ?int $casoId = null): void
    {
        $this->db->transStart();

        try {
            // Deleta os vínculos antigos do mesmo processo
            $this->deletarPorProcesso($processoId);

            $vinculos = [];
            foreach ($argumentos as $arg) {
                // As teses podem vir como array, transformamos em JSON
                $teses = isset($arg['teses_relacionadas']) && is_array($arg['teses_relacionadas'])
                         ? json_encode($arg['teses_relacionadas'])
                         : ($arg['teses_relacionadas'] ?? null);

                $novoArgumento = [
                    'titulo'                 => $arg['titulo'] ?? 'Argumento Sem Título',
                    'estrategia_recomendada' => $arg['estrategia_recomendada'] ?? '',
                    'teses_relacionadas'     => $teses,
                ];

                // Insere em argumentos
                $this->argumentosModel->insert($novoArgumento);
                $idArgumento = $this->argumentosModel->getInsertID();

                // Prepara o vínculo
                $vinculos[] = [
                    'processo_id'  => $processoId,
                    'caso_id'      => $casoId,
                    'argumento_id' => $idArgumento,
                    'avaliacao'    => null,
                ];
            }

            if (!empty($vinculos)) {
                // Insere os vínculos na tabela processo_argumentos
                $this->model->insertBatch($vinculos);
            }

            $this->db->transComplete();
            $this->clearCache();
        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', '[ArgumentosSugeridosRepository::salvarLote] ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cria um único argumento manualmente.
     */
    public function criarArgumentoCompleto(?int $processoId, array $dados, ?int $casoId = null): array
    {
        $this->db->transStart();

        try {
            $this->argumentosModel->insert($dados);
            $idArgumento = $this->argumentosModel->getInsertID();

            // Só insere o vínculo se tivermos um caso ou processo associado
            $idVinculo = null;
            if ($processoId || $casoId) {
                $idVinculo = $this->create([
                    'processo_id'  => $processoId,
                    'caso_id'      => $casoId,
                    'argumento_id' => (int) $idArgumento,
                    'avaliacao'    => null,
                ]);
            }

            $this->db->transComplete();
            $this->clearCache();

            return [
                'id_argumento' => $idArgumento,
                'id_processo_argumento' => $idVinculo,
            ];
        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', '[ArgumentosSugeridosRepository::criarArgumentoCompleto] ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualiza os dados de um argumento específico (tabela `argumentos`)
     */
    public function atualizarArgumento(int $idArgumento, array $dados): bool
    {
        $result = $this->argumentosModel->update($idArgumento, $dados);
        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }

    /**
     * Busca todos os argumentos paginados com joins.
     *
     * Busca textual via FULLTEXT (titulo + estrategia_recomendada), no mesmo padrão de
     * DocumentoModeloRepository::buscarPorTermo, para que a relevância seja comparável
     * à dos Modelos na busca global do hub de Conhecimento (issue #604).
     */
    public function buscarTodosPaginado(string $search = '', ?int $processoId = null, int $page = 1, int $limit = 20, ?int $ramoId = null): array
    {
        $builder = $this->argumentosModel->builder();

        $selectCampos = 'argumentos.*, ramos.nome as ramo_nome, processo_argumentos.id_processo_argumento, processo_argumentos.avaliacao, processo_argumentos.processo_id, processo_argumentos.caso_id, COALESCE(processos.titulo_processo, casos.titulo) as processo_titulo, processos.numero_processo';

        $builder->join('juris_ramos_direito ramos', 'ramos.id = argumentos.ramo_id', 'left')
                ->join('processo_argumentos', 'processo_argumentos.argumento_id = argumentos.id_argumento', 'left')
                ->join('processos', 'processos.id_processo = processo_argumentos.processo_id', 'left')
                ->join('casos', 'casos.id_caso = processo_argumentos.caso_id', 'left');

        if (!empty($search)) {
            $termoEscapado = $this->db->escape($search);
            $matchExpr = "MATCH(argumentos.titulo, argumentos.estrategia_recomendada) AGAINST({$termoEscapado} IN NATURAL LANGUAGE MODE)";
            // Filtro em WHERE (não HAVING/alias): Model::paginate() chama countAllResults(),
            // que troca o SELECT por COUNT(*) - um agregado sem GROUP BY. Nesse caso o MySQL só
            // enxerga em HAVING as colunas da projeção do SELECT (aqui, só o COUNT(*)), então
            // qualquer HAVING que referencie colunas da tabela (via alias ou não) quebra a
            // query de contagem. WHERE é avaliado antes da agregação e não tem essa limitação.
            $selectCampos .= ", {$matchExpr} as relevancia";
            $builder->select($selectCampos)
                    ->where("{$matchExpr} >", 0, false)
                    ->orderBy('relevancia', 'DESC');
        } else {
            $builder->select($selectCampos)
                    ->orderBy('argumentos.id_argumento', 'DESC');
        }

        if ($processoId) {
            $builder->where('processo_argumentos.processo_id', $processoId);
        }

        if ($ramoId) {
            $builder->where('argumentos.ramo_id', $ramoId);
        }

        return [
            'data' => $this->argumentosModel->paginate($limit, 'default', $page),
            'pager' => $this->argumentosModel->pager,
        ];
    }

    /**
     * Conta o total de argumentos cadastrados na base.
     */
    public function contarTodos(): int
    {
        return $this->argumentosModel->countAllResults();
    }
}
