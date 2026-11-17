<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SiteMenuModel;
use App\Repositories\BaseRepository;

class SiteMenuRepository extends BaseRepository
{
    public function __construct(?SiteMenuModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SiteMenuModel();
        $this->cacheKey = 'site_menus';
    }

    /**
     * Itens do menu de um local como árvore (`filhos` aninhados), em ordem.
     * Devolve [] se o menu não existe.
     */
    public function arvore(string $local): array
    {
        return $this->remember("arvore_{$local}", function () use ($local) {
            $menu = $this->model->where('local', $local)->first();
            if (!$menu) {
                return [];
            }

            $itens = $this->db->table('site_menu_itens')
                ->where('menu_id', $menu['id'])
                ->orderBy('ordem', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();

            return $this->montaArvore($itens);
        });
    }

    private function montaArvore(array $itens): array
    {
        $porId = [];
        foreach ($itens as $item) {
            $item['filhos'] = [];
            $porId[$item['id']] = $item;
        }

        $raiz = [];
        foreach ($porId as $id => &$item) {
            $pai = $item['parent_id'];
            if ($pai !== null && isset($porId[$pai])) {
                $porId[$pai]['filhos'][] = &$item;
            } else {
                $raiz[] = &$item;
            }
        }
        unset($item);

        return $raiz;
    }
}
