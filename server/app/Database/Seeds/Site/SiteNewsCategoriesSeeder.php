<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SiteNewsCategoriesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name'             => 'Saúde',
                'slug'             => 'saude',
                'meta_description' => 'Saúde',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Prevenção',
                'slug'             => 'prevencao',
                'meta_description' => 'Prevenção',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Bem-estar',
                'slug'             => 'bem-estar',
                'meta_description' => 'Bem-estar',
                'created_at'       => null,
                'updated_at'       => null,
                'deleted_at'       => null,
            ],
            [
                'name'             => 'Novidades da clínica',
                'slug'             => 'novidades-da-clinica',
                'meta_description' => 'Novidades da clínica',
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
