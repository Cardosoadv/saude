<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\Site\SiteConfiguracaoRepository;
use App\Repositories\Site\SiteMenuRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Repositories\Site\SiteTemplateRepository;
use CodeIgniter\Events\Events;

/**
 * Cache do site público: leituras do banco (repositórios) e HTML das páginas
 * renderizadas (chave `site_pagina_<hash>`).
 *
 * Quem escreve conteúdo chama `limpar()`, que dispara `site.conteudo.alterado`;
 * o ouvinte (app/Config/Events.php) chama `invalidar()`. As páginas ficam sob
 * uma "versão" guardada no cache: trocar a versão torna todas obsoletas de uma
 * vez, sem depender de o driver listar chaves por prefixo.
 */
final class SiteCache
{
    private const CHAVE_VERSAO = 'site_versao';

    /** Avisa que o conteúdo mudou. */
    public static function limpar(): void
    {
        Events::trigger('site.conteudo.alterado');
    }

    /** Descarta de fato os caches (ouvinte do evento e comando `site:cache:clear`). */
    public static function invalidar(): void
    {
        (new SitePaginaRepository())->clearCache();
        (new SiteSecaoRepository())->clearCache();
        (new SiteMenuRepository())->clearCache();
        (new SiteConfiguracaoRepository())->clearCache();
        (new SiteTemplateRepository())->clearCache();

        $cache = service('cache');
        $cache->delete('sitemap');
        $cache->save(self::CHAVE_VERSAO, uniqid('', true), 0);
    }

    /** HTML guardado para a página, ou null. */
    public static function pagina(string $slug): ?string
    {
        $html = service('cache')->get(self::chavePagina($slug));

        return is_string($html) ? $html : null;
    }

    public static function guardarPagina(string $slug, string $html, int $ttl): void
    {
        if ($ttl > 0) {
            service('cache')->save(self::chavePagina($slug), $html, $ttl);
        }
    }

    private static function chavePagina(string $slug): string
    {
        $versao = service('cache')->get(self::CHAVE_VERSAO) ?: '0';

        return 'site_pagina_' . md5($versao . '|' . $slug);
    }
}
