<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * URL pública de uma página do site: a home é a raiz, as páginas de sistema
 * (notícias, área do cliente) têm rota própria com o mesmo slug, e as demais
 * ficam em /pagina/{slug}.
 */
final class UrlPagina
{
    public static function de(array $pagina): string
    {
        helper('site');

        return match ($pagina['tipo']) {
            'home'    => site_url_publica(),
            'sistema' => site_url_publica($pagina['slug']),
            default   => site_url_publica('pagina/' . $pagina['slug']),
        };
    }
}
