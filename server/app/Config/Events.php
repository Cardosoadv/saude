<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        Services::toolbar()->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            Services::routes()->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * Site público: qualquer mudança em páginas ou seções invalida o sitemap em
 * cache; ele é regenerado no próximo acesso a /sitemap.xml.
 */
Events::on('site.paginas.alterado', static function (): void {
    service('cache')->delete('sitemap');
});

/*
 * Site público: qualquer escrita de conteúdo (páginas, seções, menus, configurações,
 * mídias, tema, template, notícias) invalida os caches de leitura e as páginas
 * renderizadas. O próximo acesso reconstrói tudo a partir do banco.
 */
Events::on('site.conteudo.alterado', static function (): void {
    \App\Services\Site\SiteCache::invalidar();
});

/*
 * Liquidação: valores novos de um índice econômico recalculam as liquidações em aberto
 * que o usam (as homologadas, em pagamento e quitadas ficam como estão).
 */
Events::on('indices_atualizados', static function (array $codigos): void {
    (new \App\Services\LiquidacoesService())->recalcularPorIndices($codigos);
});
