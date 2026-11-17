<?php

declare(strict_types=1);

namespace App\Traits;

use CodeIgniter\Events\Events;

/**
 * Avisa o resto do sistema que o conteúdo público do site mudou
 * (`site.paginas.alterado`, que invalida o sitemap, e `site.conteudo.alterado`,
 * que invalida o cache das páginas — ver SiteCache).
 */
trait NotificaAlteracaoSiteTrait
{
    protected function notificaAlteracaoSite(array $data): array
    {
        Events::trigger('site.paginas.alterado');
        Events::trigger('site.conteudo.alterado');

        return $data;
    }
}
