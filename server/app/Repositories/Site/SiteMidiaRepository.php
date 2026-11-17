<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SiteMidiaModel;
use App\Repositories\BaseRepository;

class SiteMidiaRepository extends BaseRepository
{
    public function __construct(?SiteMidiaModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SiteMidiaModel();
        $this->cacheKey = 'site_midias';
    }
}
