<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabelas do conteúdo gerenciável do site público (épico #641, issue #643).
 *
 * Os campos de domínio fechado (tipo, status…) são VARCHAR validados nos
 * models, e não ENUM, para a migration rodar igual em MySQL e SQLite (testes).
 */
class CreateSiteConteudoTables extends Migration
{
    public function up()
    {
        $this->criaConfiguracoes();
        $this->criaTemplates();
        $this->criaMidias();
        $this->criaPaginas();
        $this->criaSecoes();
        $this->criaMenus();
    }

    public function down()
    {
        foreach (['site_menu_itens', 'site_menus', 'site_secoes', 'site_paginas', 'site_midias', 'site_templates', 'site_configuracoes'] as $tabela) {
            $this->forge->dropTable($tabela, true);
        }
    }

    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function datas(bool $softDelete = true): array
    {
        $campos = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        if ($softDelete) {
            $campos['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        return $campos;
    }

    private function criaConfiguracoes(): void
    {
        $this->forge->addField(array_merge([
            'id'    => $this->id(),
            'chave' => ['type' => 'VARCHAR', 'constraint' => 100],
            'valor' => ['type' => 'LONGTEXT', 'null' => true],
            'tipo'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'string'],
            'grupo' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'identidade'],
        ], $this->datas(false)));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('chave');
        $this->forge->addKey('grupo');
        $this->forge->createTable('site_configuracoes', true);
    }

    private function criaTemplates(): void
    {
        $this->forge->addField(array_merge([
            'id'        => $this->id(),
            'slug'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'nome'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'versao'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'autor'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'descricao' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'manifest'  => ['type' => 'LONGTEXT', 'null' => true],
            'origem'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'core'],
            'ativo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ], $this->datas(false)));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('site_templates', true);
    }

    private function criaMidias(): void
    {
        $this->forge->addField(array_merge([
            'id'            => $this->id(),
            'arquivo'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'nome_original' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'tamanho'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'largura'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'altura'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'alt'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ], $this->datas()));
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('site_midias', true);
    }

    private function criaPaginas(): void
    {
        $this->forge->addField(array_merge([
            'id'                => $this->id(),
            'slug'              => ['type' => 'VARCHAR', 'constraint' => 120],
            'titulo'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'tipo'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pagina'],
            'layout'            => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'main'],
            'meta_title'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_description'  => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'og_image_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'rascunho'],
            'publicado_em'      => ['type' => 'DATETIME', 'null' => true],
            'ordem'             => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'exibir_sitemap'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'prioridade_sitemap' => ['type' => 'DECIMAL', 'constraint' => '2,1', 'default' => 0.8],
            'changefreq'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'monthly'],
        ], $this->datas()));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['status', 'ordem']);
        $this->forge->addForeignKey('og_image_id', 'site_midias', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('site_paginas', true);
    }

    private function criaSecoes(): void
    {
        $this->forge->addField(array_merge([
            'id'        => $this->id(),
            'pagina_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'ancora'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'ordem'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'visivel'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'dados'     => ['type' => 'LONGTEXT', 'null' => true],
        ], $this->datas()));
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['pagina_id', 'ordem']);
        $this->forge->addForeignKey('pagina_id', 'site_paginas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('site_secoes', true);
    }

    private function criaMenus(): void
    {
        $this->forge->addField(array_merge([
            'id'    => $this->id(),
            'local' => ['type' => 'VARCHAR', 'constraint' => 40],
        ], $this->datas(false)));
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('local');
        $this->forge->createTable('site_menus', true);

        $this->forge->addField(array_merge([
            'id'        => $this->id(),
            'menu_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'parent_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'rotulo'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'tipo'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pagina'],
            'pagina_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'url'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'ancora'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'destaque'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'ordem'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        ], $this->datas(false)));
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['menu_id', 'ordem']);
        $this->forge->addForeignKey('menu_id', 'site_menus', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('parent_id', 'site_menu_itens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('pagina_id', 'site_paginas', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('site_menu_itens', true);
    }
}
