<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\SeedImporter;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Repositories\Site\SiteMenuRepository;
use CodeIgniter\Database\Migration;

/**
 * Página "Quem Somos" (issue #691): reinstala o Clássico (seções `equipe` e
 * `dados_institucionais`), cria a página `quem-somos` se ainda não existir e a
 * acrescenta aos menus `cabecalho` e `rodape`.
 *
 * Idempotente e não destrutiva: nunca sobrescreve conteúdo do escritório.
 */
class PaginaQuemSomos extends Migration
{
    private const SLUG  = 'quem-somos';
    private const MENUS = ['cabecalho', 'rodape'];

    public function up()
    {
        if (!$this->db->tableExists('site_templates')) {
            return; // módulo do site ainda não instalado: o seed completo trará tudo
        }

        (new TemplateInstaller())->instalarCore('classico');

        $diretorio = APPPATH . 'Templates/classico/';
        $seed      = json_decode((string) file_get_contents($diretorio . 'seed/content.json'), true);
        $manifesto = json_decode((string) file_get_contents($diretorio . 'template.json'), true);

        (new SeedImporter())->importar([
            'paginas' => array_values(array_filter($seed['paginas'] ?? [], static fn ($p) => $p['slug'] === self::SLUG)),
        ], $manifesto);

        foreach (self::MENUS as $local) {
            $this->linkNoMenu($local, $seed['menus'][$local] ?? []);
        }
    }

    public function down()
    {
        // A página e os links ficam: removê-los apagaria conteúdo do escritório.
    }

    /** O SeedImporter não mexe em menu que já tem itens; aqui só acrescentamos o que falta. */
    private function linkNoMenu(string $local, array $itensSeed): void
    {
        $menu   = $this->db->table('site_menus')->where('local', $local)->get()->getRowArray();
        $pagina = $this->db->table('site_paginas')->where('slug', self::SLUG)->where('deleted_at', null)->get()->getRowArray();
        if (!$menu || !$pagina) {
            return;
        }
        if ($this->db->table('site_menu_itens')->where(['menu_id' => $menu['id'], 'pagina_id' => $pagina['id']])->countAllResults() > 0) {
            return;
        }

        $rotulo = 'Quem Somos';
        foreach ($itensSeed as $item) {
            if (($item['pagina'] ?? '') === self::SLUG) {
                $rotulo = $item['rotulo'];
            }
        }

        $ordem = (int) $this->db->table('site_menu_itens')->selectMax('ordem')->where('menu_id', $menu['id'])->get()->getRow()->ordem;
        $this->db->table('site_menu_itens')->insert([
            'menu_id'    => $menu['id'],
            'parent_id'  => null,
            'rotulo'     => $rotulo,
            'tipo'       => 'pagina',
            'pagina_id'  => $pagina['id'],
            'destaque'   => 0,
            'ordem'      => ++$ordem,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new SiteMenuRepository())->clearCache();
    }
}
