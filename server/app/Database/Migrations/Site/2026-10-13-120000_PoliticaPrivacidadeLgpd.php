<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\SeedImporter;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Repositories\Site\SiteMenuRepository;
use CodeIgniter\Database\Migration;

/**
 * LGPD no site (issue #692).
 *
 *  - `site_crm_contacts` ganha a data/hora do aceite da Política de Privacidade
 *    e a versão aceita, gravadas pelo formulário de contato;
 *  - reinstala o Clássico (seção `dados_controlador`, textos, banner de cookies);
 *  - cria, se ainda não existirem, as configurações `identidade.cnpj` e `lgpd.*`
 *    e as páginas `politica-de-privacidade` e `termos-de-uso`;
 *  - acrescenta as duas páginas ao fim do menu `rodape`, se ainda não estiverem lá.
 *
 * Idempotente e não destrutiva: nunca sobrescreve conteúdo do escritório.
 */
class PoliticaPrivacidadeLgpd extends Migration
{
    private const PAGINAS = ['politica-de-privacidade', 'termos-de-uso'];
    private const CONFIGURACOES = ['identidade.cnpj', 'lgpd.encarregado_nome', 'lgpd.encarregado_email', 'lgpd.politica_versao'];

    public function up()
    {
        $this->adicionaColunasDeAceite();

        if (!$this->db->tableExists('site_templates')) {
            return; // módulo do site ainda não instalado: o seed completo trará tudo
        }

        (new TemplateInstaller())->instalarCore('classico');

        $diretorio = APPPATH . 'Templates/classico/';
        $seed      = json_decode((string) file_get_contents($diretorio . 'seed/content.json'), true);
        $manifesto = json_decode((string) file_get_contents($diretorio . 'template.json'), true);

        (new SeedImporter())->importar([
            'configuracoes' => array_intersect_key($seed['configuracoes'] ?? [], array_flip(self::CONFIGURACOES)),
            'paginas'       => array_values(array_filter($seed['paginas'] ?? [], static fn ($p) => in_array($p['slug'], self::PAGINAS, true))),
        ], $manifesto);

        $this->linksNoRodape($seed['menus']['rodape'] ?? []);
    }

    public function down()
    {
        // Páginas, menus e configurações ficam: removê-los apagaria conteúdo do escritório.
        foreach (['privacidade_aceite_em', 'privacidade_versao'] as $coluna) {
            if ($this->temColuna($coluna)) {
                $this->forge->dropColumn('site_crm_contacts', $coluna);
            }
        }
    }

    private function adicionaColunasDeAceite(): void
    {
        if (!$this->db->tableExists('site_crm_contacts')) {
            return;
        }
        if (!$this->temColuna('privacidade_aceite_em')) {
            $this->forge->addColumn('site_crm_contacts', [
                'privacidade_aceite_em' => ['type' => 'DATETIME', 'null' => true, 'after' => 'status'],
            ]);
        }
        if (!$this->temColuna('privacidade_versao')) {
            $this->forge->addColumn('site_crm_contacts', [
                'privacidade_versao' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'privacidade_aceite_em'],
            ]);
        }
    }

    /** O SeedImporter não mexe em menu que já tem itens; aqui só acrescentamos os que faltam. */
    private function linksNoRodape(array $itensSeed): void
    {
        $menu = $this->db->table('site_menus')->where('local', 'rodape')->get()->getRowArray();
        if (!$menu) {
            return;
        }

        $ordem = (int) $this->db->table('site_menu_itens')->selectMax('ordem')->where('menu_id', $menu['id'])->get()->getRow()->ordem;
        foreach ($itensSeed as $item) {
            if (($item['tipo'] ?? '') !== 'pagina' || !in_array($item['pagina'] ?? '', self::PAGINAS, true)) {
                continue;
            }
            $pagina = $this->db->table('site_paginas')->where('slug', $item['pagina'])->where('deleted_at', null)->get()->getRowArray();
            if (!$pagina) {
                continue;
            }
            $jaTem = $this->db->table('site_menu_itens')->where(['menu_id' => $menu['id'], 'pagina_id' => $pagina['id']])->countAllResults() > 0;
            if ($jaTem) {
                continue;
            }
            $this->db->table('site_menu_itens')->insert([
                'menu_id'   => $menu['id'],
                'parent_id' => null,
                'rotulo'    => $item['rotulo'],
                'tipo'      => 'pagina',
                'pagina_id' => $pagina['id'],
                'destaque'  => 0,
                'ordem'     => ++$ordem,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        (new SiteMenuRepository())->clearCache();
    }

    private function temColuna(string $coluna): bool
    {
        // fieldExists() usa cache de nomes de colunas por conexão.
        $this->db->resetDataCache();

        return $this->db->fieldExists($coluna, 'site_crm_contacts');
    }
}
