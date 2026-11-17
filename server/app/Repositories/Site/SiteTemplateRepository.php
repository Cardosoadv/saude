<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SiteTemplateModel;
use App\Repositories\BaseRepository;

class SiteTemplateRepository extends BaseRepository
{
    public function __construct(?SiteTemplateModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SiteTemplateModel();
        $this->cacheKey = 'site_templates';
    }

    public function getAtivo(): ?array
    {
        return $this->remember('ativo', fn () => $this->model->where('ativo', 1)->first());
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->remember("slug_{$slug}", fn () => $this->model->where('slug', $slug)->first());
    }

    /**
     * Torna o template o único ativo, de forma atômica.
     */
    public function ativar(int $id): bool
    {
        if (!$this->model->find($id)) {
            return false;
        }

        $this->db->transStart();
        $this->db->table('site_templates')->where('ativo', 1)->update(['ativo' => 0]);
        $this->db->table('site_templates')->where('id', $id)->update(['ativo' => 1]);
        $this->db->transComplete();

        $this->clearCache();
        return $this->db->transStatus();
    }
}
