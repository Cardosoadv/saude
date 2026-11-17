<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Telas do site público que não devem ser indexadas nem listadas no
 * sitemap, llms.txt e llms-full.txt (área e painel do cliente), e os
 * prefixos que o robots.txt proíbe rastrear.
 */
final class AreasRestritas
{
    /** Slugs das páginas de sistema restritas (a rota `cliente` é alias de `painel-cliente`). */
    public const SLUGS = ['area-cliente', 'painel-cliente', 'cliente'];

    /** Caminhos que o robots.txt proíbe. */
    public const CAMINHOS_BLOQUEADOS = ['/api/', '/area-cliente', '/painel-cliente', '/cliente', '/sistema/'];

    public static function ehRestrita(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }
}
