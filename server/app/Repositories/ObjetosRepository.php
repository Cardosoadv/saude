<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProcessoObjetoModel;

/**
 * Repositório para gerenciamento de Objetos (Bens/Direitos).
 */
class ObjetosRepository extends BaseRepository
{
    private ?ProcessosRepository $processesRepository;

    public function __construct(?ProcessoObjetoModel $model = null, ?ProcessosRepository $processesRepository = null)
    {
        parent::__construct();
        $this->model = $model ?? model('ProcessoObjetoModel');
        $this->cacheKey = 'objetos';
        $this->processesRepository = $processesRepository;
    }

    /**
     * Lista objetos ordenados por critérios específicos.
     *
     * @return array
     */
    public function findAllOrdenado(): array
    {
        return $this->remember('all_ordered', function () {
            return $this->model
                ->orderBy('cidade', 'ASC')
                ->orderBy('bairro', 'ASC')
                ->orderBy('quadra', 'ASC')
                ->orderBy('lote', 'ASC')
                ->findAll();
        });
    }

    /**
     * Seleciona objetos associados a um ID de processo.
     *
     * @param int $processoId O ID do processo.
     * @return array Array contendo 'objetos' paginados e 'pager'.
     */
    public function selecionarObjetoPorProcessoId(int $processoId): array
    {
        return $this->remember("processo_{$processoId}", function () use ($processoId) {
            return $this->model->selecionarObjetoPorProcessoId($processoId);
        });
    }

    /**
     * Busca objetos com filtros e paginação.
     *
     * @param string|null $search Termo de busca.
     * @param int $page Página atual.
     * @param int $perPage Itens por página.
     * @return array Array contendo 'objetos' e 'pager'.
     */
    public function buscarObjetos(?string $search = null, int $page = 1, int $perPage = 25): array
    {
        $cacheSuffix = "search_" . md5(serialize([$search, $page, $perPage]));

        return $this->remember($cacheSuffix, function () use ($search, $page, $perPage) {
            $builder = $this->model->builder();

            if ($search) {
                $builder->groupStart()
                    ->like('LOWER(bairro)', strtolower(trim($search)))
                    ->orLike('LOWER(cidade)', strtolower(trim($search)))
                    ->orLike('LOWER(cod_interno)', strtolower(trim($search)))
                    ->orLike('LOWER(logradouro)', strtolower(trim($search)))
                    ->groupEnd();
            }

            $builder->orderBy('cidade', 'ASC')
                ->orderBy('bairro', 'ASC')
                ->orderBy('quadra', 'ASC')
                ->orderBy('lote', 'ASC');

            // O group precisa variar com o termo de busca: o cache interno de paginate()
            // é isolado por (page, perPage, group), então um group fixo faria buscas por
            // termos diferentes reaproveitarem indevidamente a página cacheada de outra busca.
            // Esse group serve só para isolar o cache: BaseRepository::paginate() sempre
            // popula o Pager real sob o group 'default'.
            $group = 'search_' . md5((string) $search);
            $data  = $this->paginate($perPage, $group, $page);

            return [
                'objetos' => $data,
                'pager'   => $this->getPager(),
            ];
        });
    }

    /**
     * Retorna a lista de bairros distintos.
     */
    public function getBairrosDistintos(): array
    {
        return $this->remember('bairros_distintos', function () {
            return $this->model->select('bairro')->distinct()->orderBy('bairro', 'ASC')->findAll();
        });
    }

    /**
     * Retorna a lista de cidades distintas.
     */
    public function getCidadesDistintas(): array
    {
        return $this->remember('cidades_distintas', function () {
            return $this->model->select('cidade')->distinct()->orderBy('cidade', 'ASC')->findAll();
        });
    }

    /**
     * Retorna dados para exportação com joins.
     */
    public function getDadosExportacao(): array
    {
        return $this->remember('dados_exportacao', function () {
            return $this->model
                ->orderBy('cidade', 'ASC')
                ->orderBy('bairro', 'ASC')
                ->join('processos_objeto_processo as pop', 'pop.objeto_id = processos_objeto.id_objeto', 'left')
                ->join('processos as p', 'p.id_processo = pop.processo_id', 'left')
                ->get()->getResultArray();
        });
    }

    /**
     * Lista, para o Relatório de Objetos com Processos Vinculados, apenas os
     * objetos que possuem ao menos um processo vinculado (inner join com a
     * tabela pivot), já com os dados dos processos ligados a cada um.
     *
     * @return array Linhas planas (um objeto pode repetir, uma linha por processo vinculado).
     */
    public function getObjetosComProcessosVinculados(): array
    {
        return $this->remember('objetos_processos_vinculados', function () {
            return $this->model->builder()
                ->select([
                    'processos_objeto.id_objeto',
                    'processos_objeto.cidade',
                    'processos_objeto.bairro',
                    'processos_objeto.logradouro',
                    'processos_objeto.numero as numero_imovel',
                    'processos_objeto.complemento',
                    'processos_objeto.cep',
                    'processos_objeto.quadra',
                    'processos_objeto.lote',
                    'processos_objeto.inscricao',
                    'processos_objeto.cod_interno',
                    'processos_objeto.matricula',
                    'processos_objeto.cartorio',
                    'p.id_processo',
                    'p.numeroprocessocommascara',
                    'p.numero_processo',
                    'p.titulo_processo',
                    'p.dataDistribuicao',
                    'p.valorCausa',
                    'p.encerrado',
                    'p.risco',
                    'cli.nome as cliente_nome',
                ])
                ->join('processos_objeto_processo as pop', 'pop.objeto_id = processos_objeto.id_objeto', 'inner')
                ->join('processos as p', 'p.id_processo = pop.processo_id', 'inner')
                ->join('clientes as cli', 'cli.id_cliente = p.cliente_id', 'left')
                ->where('p.deleted_at', null)
                ->orderBy('processos_objeto.cidade', 'ASC')
                ->orderBy('processos_objeto.bairro', 'ASC')
                ->orderBy('processos_objeto.quadra', 'ASC')
                ->orderBy('processos_objeto.lote', 'ASC')
                ->orderBy('p.dataDistribuicao', 'ASC')
                ->get()->getResultArray();
        });
    }

    /**
     * Vincula um objeto a um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     * @return void
     */
    public function vincularProcessoObjeto(int $processoId, int $objetoId): void
    {
        $this->model->vincularProcessoObjeto($processoId, $objetoId);
        $this->clearCache();
        $this->clearProcessesCache();
    }

    /**
     * Desvincula um objeto de um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     * @return void
     */
    public function desvincularObjetoProcesso(int $processoId, int $objetoId): void
    {
        $this->model->desvincularObjetoProcesso($processoId, $objetoId);
        $this->clearCache();
        $this->clearProcessesCache();
    }

    /**
     * Verifica se existe um objeto com o código interno fornecido.
     */
    public function existeObjetoCodInterno(string $codInterno): bool
    {
        return $this->model->existeObjetoCodInterno($codInterno);
    }

    /**
     * Verifica se existe um objeto com a inscrição fornecida.
     */
    public function existeObjetoInscricao(string $inscricao): bool
    {
        return $this->model->existeObjetoInscricao($inscricao);
    }

    /**
     * Salva um objeto (cria ou atualiza).
     *
     * @param array $dados Dados do objeto.
     * @return int ID do objeto salvo.
     */
    public function salvarObjeto(array $dados): int
    {
        if (!empty($dados['id_objeto'])) {
            $id = (int) $dados['id_objeto'];
            $this->model->update($id, $dados);
        } else {
            $this->model->insert($dados);
            $id = (int) $this->model->getInsertID();
        }

        $this->clearCache();
        $this->clearProcessesCache();

        return $id;
    }

    /**
     * Obtém um objeto pelo ID.
     *
     * @param int $id ID do objeto.
     * @return array|null Dados do objeto ou null se não encontrado.
     */
    public function obterObjeto(int $id): ?array
    {
        return $this->model->find($id);
    }

    /**
     * Lista todos os objetos.
     *
     * @return array Lista de todos os objetos.
     */
    public function listarObjetos(): array
    {
        return $this->model->findAll();
    }

    /**
     * Deleta um objeto.
     *
     * @param int $id ID do objeto.
     * @return void
     */
    public function deletarObjeto(int $id): void
    {
        $this->model->delete($id);

        $this->clearCache();
        $this->clearProcessesCache();
    }

    /**
     * Lawsuit details embed their linked objects and are cached under the
     * 'processos' key, so every object change must invalidate that cache too.
     */
    private function clearProcessesCache(): void
    {
        ($this->processesRepository ??= new ProcessosRepository())->clearCache();
    }
}
