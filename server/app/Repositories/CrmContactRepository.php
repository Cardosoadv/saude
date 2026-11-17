<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CrmContactModel;
use App\Models\RamosDireitoModel;

/**
 * Repositório para gerenciamento de contatos do CRM.
 *
 * @version 1.1.0
 */
class CrmContactRepository extends BaseRepository
{
    /**
     * @var RamosDireitoModel Instância do model de ramos do direito.
     */
    protected RamosDireitoModel $ramosDireitoModel;

    /**
     * Construtor do repositório de contatos CRM.
     *
     * @param CrmContactModel|null $model Instância do model de contatos.
     * @param RamosDireitoModel|null $ramosDireitoModel Instância do model de ramos do direito.
     */
    public function __construct(?CrmContactModel $model = null, ?RamosDireitoModel $ramosDireitoModel = null)
    {
        parent::__construct();
        $this->model = $model ?? new CrmContactModel();
        $this->cacheKey = 'crm_contacts';
        $this->ramosDireitoModel = $ramosDireitoModel ?? new RamosDireitoModel();
    }

    /**
     * Retorna contatos filtrados por área.
     *
     * @param int $areaId ID da área.
     * @param string|array $select Campos a selecionar.
     * @param array $where Condições adicionais.
     * @param string|null $orderBy Campo de ordenação.
     * @param string|null $direction Direção da ordenação.
     * @param array $joins Joins adicionais.
     * @return array
     */
    public function getContactsByArea(
        int $areaId,
        string|array $select = '*',
        array $where = [],
        ?string $orderBy = null,
        ?string $direction = null,
        array $joins = []
    ): array {
        $where['area_id'] = $areaId;

        return $this->remember("contacts_area_{$areaId}", function () use ($select, $where, $orderBy, $direction, $joins) {
            $builder = $this->model->select($select);

            foreach ($joins as $join) {
                $builder->join($join[0], $join[1], $join[2] ?? 'inner');
            }

            if (!empty($where)) {
                $builder->where($where);
            }

            if ($orderBy !== null) {
                $builder->orderBy($orderBy, $direction ?? 'ASC');
            }

            return $builder->findAll();
        });
    }

    /**
     * Retorna todos os ramos do direito ordenados por nome.
     *
     * @return array
     */
    public function getRamosDireito(): array
    {
        return $this->remember('ramos_direito_all', function () {
            return $this->ramosDireitoModel
                ->orderBy('nome', 'ASC')
                ->findAll();
        });
    }

    /**
     * Retorna um ramo do direito pelo ID.
     *
     * @param int $id ID do ramo.
     * @return array|null
     */
    public function getRamoDireitoById(int $id): ?array
    {
        return $this->remember("ramo_direito_{$id}", function () use ($id) {
            return $this->ramosDireitoModel->find($id);
        });
    }

    /**
     * Busca contatos por nome, e-mail ou telefone com paginação e filtros.
     *
     * @param string $search Termo de busca.
     * @param int $perPage Registros por página.
     * @param int $page Página atual.
     * @param string|null $status Filtro por status (new, contacted, qualified, converted, lost).
     * @param int|null $areaId Filtro por área de interesse (ramo do direito).
     * @return array ['data' => ..., 'pager' => ..., 'stats' => ...]
     */
    public function buscarContatos(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
        ?string $status = null,
        ?int $areaId = null
    ): array {
        $statusKey = $status ?? 'all';
        $areaKey = $areaId ?? 'all';
        $cacheKey = "buscar_search_" . md5($search) . "_st_{$statusKey}_area_{$areaKey}_per_{$perPage}_page_{$page}";

        return $this->remember($cacheKey, function () use ($search, $perPage, $page, $status, $areaId) {
            $builder = $this->model
                ->select('site_crm_contacts.*, juris_ramos_direito.nome as area_interesse_nome')
                ->join('juris_ramos_direito', 'juris_ramos_direito.id = site_crm_contacts.area_interesse', 'left')
                ->orderBy('site_crm_contacts.created_at', 'DESC');

            if ($search !== '') {
                $builder = $builder
                    ->groupStart()
                        ->like('site_crm_contacts.name', $search)
                        ->orLike('site_crm_contacts.email', $search)
                        ->orLike('site_crm_contacts.phone', $search)
                        ->orLike('site_crm_contacts.message', $search)
                    ->groupEnd();
            }

            if (!empty($status) && $status !== 'all' && $status !== 'todos') {
                $builder = $builder->where('site_crm_contacts.status', $status);
            }

            if (!empty($areaId)) {
                $builder = $builder->where('site_crm_contacts.area_interesse', $areaId);
            }

            $items = $builder->paginate($perPage, 'default', $page);
            $pager = $builder->pager;

            // Estatísticas consolidadas por status
            $db = \Config\Database::connect();
            $statsRaw = $db->table('site_crm_contacts')
                ->select('status, COUNT(*) as qtd')
                ->where('deleted_at IS NULL')
                ->groupBy('status')
                ->get()
                ->getResultArray();

            $stats = [
                'total'     => 0,
                'new'       => 0,
                'contacted' => 0,
                'qualified' => 0,
                'converted' => 0,
                'lost'      => 0,
            ];

            foreach ($statsRaw as $s) {
                $st = (string) $s['status'];
                $qtd = (int) $s['qtd'];
                if (isset($stats[$st])) {
                    $stats[$st] = $qtd;
                }
                $stats['total'] += $qtd;
            }

            return [
                'data'  => $items,
                'pager' => [
                    'currentPage' => $page,
                    'pageCount'   => $pager ? $pager->getPageCount() : 1,
                    'total'       => $pager ? $pager->getTotal() : count($items),
                    'perPage'     => $perPage,
                ],
                'stats' => $stats,
            ];
        });
    }
}
