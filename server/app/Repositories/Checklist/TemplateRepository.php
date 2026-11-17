<?php

declare(strict_types=1);

namespace App\Repositories\Checklist;

use App\Models\TemplateModel;
use App\Models\SecaoTemplateModel;
use App\Models\ItemTemplateModel;
use App\Repositories\BaseRepository;

/**
 * Repositório para Templates de Checklist.
 */
class TemplateRepository extends BaseRepository
{
    /**
     * @var SecaoTemplateModel Modelo de seções de template.
     */
    protected SecaoTemplateModel $secaoModel;

    /**
     * @var ItemTemplateModel Modelo de itens de template.
     */
    protected ItemTemplateModel $itemModel;

    /**
     * @var ProcedimentosRepository Repositório de procedimentos.
     */
    protected ProcedimentosRepository $procedimentoRepository;

    /**
     * @var \CodeIgniter\Database\BaseConnection Conexão com o banco de dados.
     */
    protected $db;

    /**
     * Construtor do repositório.
     * Inicializa os modelos e repositórios auxiliares.
     */
    public function __construct(
        ?TemplateModel $model = null,
        ?SecaoTemplateModel $secaoModel = null,
        ?ItemTemplateModel $itemModel = null,
        ?ProcedimentosRepository $procedimentoRepository = null
    ) {
        parent::__construct();
        $this->model                    = $model ?? new TemplateModel();
        $this->cacheKey                 = 'checklist_templates';
        $this->secaoModel               = $secaoModel ?? new SecaoTemplateModel();
        $this->itemModel                = $itemModel ?? new ItemTemplateModel();
        $this->procedimentoRepository   = $procedimentoRepository ?? new ProcedimentosRepository();
        $this->db                       = db_connect();
    }

    /**
     * Retorna um template pelo ID com suas seções e itens.
     */
    public function getById(int $id): ?array
    {
        return $this->remember('full_' . $id, function () use ($id) {
            $template = $this->findById($id);
            if ($template) {
                $template['secoes'] = $this->getSecoesWithItens($id);
            }
            return $template;
        });
    }

    /**
     * Retorna as seções de um template com seus respectivos itens.
     */
    public function getSecoesWithItens(int $templateId): array
    {
        $secoes = $this->secaoModel->where('template_id', $templateId)
                                    ->where('deleted_at', null)
                                    ->orderBy('ordem', 'ASC')
                                    ->findAll();

        foreach ($secoes as &$secao) {
            $secao['itens'] = $this->itemModel->where('secao_id', $secao['id'])
                                              ->where('deleted_at', null)
                                              ->orderBy('ordem', 'ASC')
                                              ->findAll();
        }

        return $secoes;
    }


    /**
     * Duplica um template, incluindo suas seções e itens.
     */
    public function duplicar(int $id, int $usuarioId): int|false
    {
        $templateOriginal = $this->getById($id);
        if (!$templateOriginal) {
            return false;
        }

        // Criar novo template
        $novoTemplateId = $this->create([
            'procedimento_id' => $templateOriginal['procedimento_id'],
            'titulo'          => $templateOriginal['titulo'] . ' (Cópia)',
            'descricao'       => $templateOriginal['descricao'] ?? null,
            'created_by'      => $usuarioId,
        ]);

        if (!$novoTemplateId) {
            return false;
        }

        // Duplicar seções e itens
        if (isset($templateOriginal['secoes'])) {
            foreach ($templateOriginal['secoes'] as $secao) {
                $novaSecaoId = (int) $this->secaoModel->insert([
                    'template_id' => $novoTemplateId,
                    'titulo'      => $secao['titulo'],
                    'descricao'   => $secao['descricao'] ?? null,
                    'ordem'       => $secao['ordem'],
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);

                if ($novaSecaoId && !empty($secao['itens'])) {
                    $itensParaInserir = [];
                    foreach ($secao['itens'] as $item) {
                        $itensParaInserir[] = [
                            'secao_id'          => $novaSecaoId,
                            'titulo'            => $item['titulo'],
                            'observacao_padrao' => $item['observacao_padrao'] ?? null,
                            'ordem'             => $item['ordem'],
                            'created_at'        => date('Y-m-d H:i:s'),
                        ];
                    }
                    $this->itemModel->insertBatch($itensParaInserir);
                }
            }
        }

        return $novoTemplateId;
    }

    /**
     * Retorna templates paginados com filtros.
     */
    public function getPaged(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $filtersKey = md5(serialize($filters));
        return $this->remember("paged_{$page}_{$perPage}_{$filtersKey}", function () use ($page, $perPage, $filters) {
            $builder = $this->model->select('chk_templates.*, p.nome as procedimento_nome')
                ->join('chk_procedimentos p', 'p.id = chk_templates.procedimento_id', 'left');

            if (!empty($filters)) {
                if (isset($filters['procedimento_id']) && $filters['procedimento_id'] != '') {
                    $builder->where('chk_templates.procedimento_id', $filters['procedimento_id']);
                }
                if (isset($filters['search'])) {
                    $builder->groupStart()
                        ->like('chk_templates.titulo', $filters['search'])
                        ->orLike('chk_templates.descricao', $filters['search'])
                        ->groupEnd();
                }
            }

            return [
                'data'    => $builder->paginate($perPage, 'default', $page),
                'pager'   => $this->getPager(),
                'total'   => $this->countAll($filters),
                'perPage' => $perPage,
            ];
        });
    }

    /**
     * Conta o total de registros com base nos filtros.
     */
    public function countAll(array $filters = []): int
    {
        $builder = $this->model->where('chk_templates.deleted_at', null);

        if (!empty($filters)) {
            if (isset($filters['procedimento_id']) && $filters['procedimento_id'] != '') {
                $builder->where('chk_templates.procedimento_id', $filters['procedimento_id']);
            }
            if (isset($filters['search'])) {
                $builder->groupStart()
                        ->like('chk_templates.titulo', $filters['search'])
                        ->orLike('chk_templates.descricao', $filters['search'])
                        ->groupEnd();
            }
        }

        return $builder->countAllResults();
    }

    /**
     * Retorna templates por procedimento.
     */
    public function getByProcedimento(int $procedimentoId): array
    {
        return $this->model->where('procedimento_id', $procedimentoId)->findAll();
    }

    /**
     * Conta quantos checklists (casos) utilizam este template.
     *
     * @param int $id ID do template.
     * @return int
     */
    public function countUsages(int $id): int
    {
        return $this->db->table('chk_casos_checklists')
                        ->where('template_id', $id)
                        ->countAllResults();
    }

    /**
     * Busca uma seção pelo ID.
     *
     * @param int $id ID da seção.
     * @return object|null
     */
    public function getSecaoById(int $id): ?object
    {
        return $this->db->table('chk_secoes_template')
                        ->where('id', $id)
                        ->where('deleted_at', null)
                        ->get()
                        ->getRow();
    }

    /**
     * Salva uma nova seção para um template.
     */
    public function salvarSecao(int $templateId, array $dados): int
    {
        $dados['template_id'] = $templateId;
        $dados['created_at'] = date('Y-m-d H:i:s');

        if (!isset($dados['ordem'])) {
            $query = $this->db->table('chk_secoes_template')
                ->selectMax('ordem')
                ->where('template_id', $templateId)
                ->where('deleted_at', null)
                ->get()
                ->getRow();
            $dados['ordem'] = ((int) ($query->ordem ?? 0)) + 1;
        }

        $id = (int) $this->secaoModel->insert($dados);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    /**
     * Salva um novo item para uma seção.
     */
    public function salvarItem(int $secaoId, array $dados): int
    {
        $dados['secao_id'] = $secaoId;
        $dados['created_at'] = date('Y-m-d H:i:s');

        if (!isset($dados['ordem'])) {
            $query = $this->db->table('chk_itens_template')
                ->selectMax('ordem')
                ->where('secao_id', $secaoId)
                ->where('deleted_at', null)
                ->get()
                ->getRow();
            $dados['ordem'] = ((int) ($query->ordem ?? 0)) + 1;
        }

        $id = (int) $this->itemModel->insert($dados);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    /**
     * Duplica uma seção e seus itens.
     */
    public function duplicarSecao(int $secaoId): int|false
    {
        $secaoOriginal = $this->secaoModel->find($secaoId);

        if (!$secaoOriginal) {
            return false;
        }

        $novaSecaoId = (int) $this->secaoModel->insert([
            'template_id' => $secaoOriginal['template_id'],
            'titulo'      => $secaoOriginal['titulo'] . ' (Cópia)',
            'descricao'   => $secaoOriginal['descricao'] ?? null,
            'ordem'       => $secaoOriginal['ordem'] + 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        if ($novaSecaoId) {
            $itens = $this->itemModel->where('secao_id', $secaoId)->where('deleted_at', null)->findAll();
            if (!empty($itens)) {
                $itensParaInserir = [];
                foreach ($itens as $item) {
                    $itensParaInserir[] = [
                        'secao_id'          => $novaSecaoId,
                        'titulo'            => $item['titulo'],
                        'observacao_padrao' => $item['observacao_padrao'] ?? null,
                        'ordem'             => $item['ordem'],
                        'created_at'        => date('Y-m-d H:i:s'),
                    ];
                }
                $this->itemModel->insertBatch($itensParaInserir);
            }
            $this->clearCache();
        }

        return $novaSecaoId;
    }

    /**
     * Reordena as seções de um template.
     */
    public function reordenarSecoes(array $ordens): bool
    {
        if (empty($ordens)) {
            return true;
        }

        $batch = [];
        foreach ($ordens as $id => $ordem) {
            $batch[] = [
                'id'    => $id,
                'ordem' => $ordem
            ];
        }

        $result = $this->secaoModel->updateBatch($batch, 'id');

        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }

    /**
     * Reordena os itens de uma seção.
     */
    public function reordenarItens(array $ordens): bool
    {
        if (empty($ordens)) {
            return true;
        }

        $batch = [];
        foreach ($ordens as $id => $ordem) {
            $batch[] = [
                'id'    => $id,
                'ordem' => $ordem
            ];
        }

        $result = $this->itemModel->updateBatch($batch, 'id');

        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }
}
