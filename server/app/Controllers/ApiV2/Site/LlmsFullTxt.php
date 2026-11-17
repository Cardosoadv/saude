<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site;

use App\Services\Site\LlmsFullService;

/**
 * GET /llms-full.txt na raiz do site: texto integral das páginas e dos artigos em markdown para IAs.
 */
class LlmsFullTxt extends LlmsTxt
{
    public function __construct(private ?LlmsFullService $completo = null)
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->servir('arquivo:llms-full.txt', fn () => ($this->completo ??= new LlmsFullService())->gerar());
    }
}
