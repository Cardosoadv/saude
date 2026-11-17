<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSiteTables extends Migration
{
    public function up()
    {
        // site_crm_contacts
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'message'        => ['type' => 'TEXT'],
            'area_interesse' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['new', 'contacted', 'qualified', 'converted', 'lost'], 'default' => 'new'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('site_crm_contacts', true);

        // site_news_categories
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 120],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('slug', false, true);
        $this->forge->createTable('site_news_categories', true);

        // site_news
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'category_id'      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'author_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'summary'          => ['type' => 'TEXT', 'null' => true],
            'content'          => ['type' => 'LONGTEXT'],
            'featured_image'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['draft', 'published'], 'default' => 'draft'],
            'published_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('category_id', 'site_news_categories', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('author_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('site_news', true);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true,],
            'url'           => ['type' => 'VARCHAR', 'constraint' => 255,],
            'last_modified' => ['type' => 'DATETIME',],
            'priority'      => ['type' => 'DECIMAL', 'constraint' => '3,1',],
            'changefreq'    => ['type' => 'VARCHAR', 'constraint' => 20,],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('site_sitemap', true);
    }

    public function down()
    {
        $this->forge->dropTable('site_sitemap', true);
        $this->forge->dropTable('site_news', true);
        $this->forge->dropTable('site_news_categories', true);
        $this->forge->dropTable('site_crm_contacts', true);
    }
}
