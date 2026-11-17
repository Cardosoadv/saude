<?php

namespace App\Controllers\ApiV2\Site;

use App\Controllers\BaseController;
use App\Services\SitemapService;

class Sitemap extends BaseController
{
    protected SitemapService $service;

    public function __construct(?SitemapService $service = null)
    {
        $this->service = $service ?? new SitemapService();
    }

    public function index()
    {

        $sitemap = $this->service->getSitemapLinks();
        return view('sitemap', ['sitemap' => $sitemap]);
    }

    public function generate()
    {
        $this->service->generateSitemap();
        return redirect()->back()->with('success', 'Sitemap gerado com sucesso!');
    }
}
