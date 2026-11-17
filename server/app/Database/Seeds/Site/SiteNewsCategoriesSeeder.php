<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SiteNewsCategoriesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name'             => 'Direito Civil',
                'slug'             => 'direito-civil',
                'meta_description' => 'Direito Civil',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Direito Administrativo',
                'slug'             => 'direito-administrativo',
                'meta_description' => 'Direito Administrativo',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Contratos',
                'slug'             => 'contratos',
                'meta_description' => 'Contratos',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Família e Sucessões',
                'slug'             => 'familia-e-sucessoes',
                'meta_description' => 'Família e Sucessões',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Advocacia Colaborativa',
                'slug'             => 'advocacia-colaborativa',
                'meta_description' => 'Advocacia Colaborativa',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Outro',
                'slug'             => 'outro',
                'meta_description' => 'Outro',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
        ];

        $this->db->table('site_news_categories')->insertBatch($data);
    }
}