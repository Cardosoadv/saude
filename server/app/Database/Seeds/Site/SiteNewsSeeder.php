<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SiteNewsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'category_id'       => 6,
                'author_id'         => 1,
                'title'             => 'Teste',
                'slug'              => 'teste',
                'summary'           => 'Aqui fica o resumo das noticias',
                'content'           => 'Aqui vai todo o conteúdo não sei ao certo como vai funcionar, mas é isso.',
                'featured_image'    => null,
                'meta_title'        => '',
                'meta_description'  => '',
                'status'            => 'published',
                'published_at'      => '2026-03-12 10:21:54',
                'created_at'        => '2026-03-12 10:21:54',
                'updated_at'        => '2026-03-12 10:21:54',
                'deleted_at'        => null,
            ],
            [
                'category_id'       => 2,
                'author_id'         => 1,
                'title'             => 'Tendências do Contencioso Administrativo em 2026',
                'slug'              => 'tendencias-do-contencioso-administrativo-em-2026',
                'summary'           => 'Uma análise profunda sobre como as novas tecnologias estão moldando a relação entre o Estado e os particulares no âmbito judicial.',
                'content'           => 'Uma análise profunda sobre como as novas tecnologias estão moldando a relação entre o Estado e os particulares no âmbito judicial.',
                'featured_image'    => null,
                'meta_title'        => 'metadados para o titulo',
                'meta_description'  => 'sdf',
                'status'            => 'published',
                'published_at'      => '2026-03-12 10:26:09',
                'created_at'        => '2026-03-12 10:26:09',
                'updated_at'        => '2026-03-12 10:26:09',
                'deleted_at'        => null,
            ],
            [
                'category_id'       => 1,
                'author_id'         => 1,
                'title'             => 'Entendimento do STJ sobre a legalidade da locação por temporada',
                'slug'              => 'entendimento-do-stj-sobre-a-legalidade-da-locacao-por-temporada',
                'summary'           => '',
                'content'           => '<p>🏢📱 <b>Você aluga seu imóvel por aplicativos de temporada? Saiba o que o condomínio pode (e o que não pode) proibir.</b></p><p>O aluguel por curta temporada (estilo Airbnb) é uma excelente fonte de renda, mas costuma gerar atritos em condomínios. Em 2026, a justiça brasileira já possui critérios claros sobre esse tema.</p>',
                'featured_image'    => null,
                'meta_title'        => '',
                'meta_description'  => '',
                'status'            => 'published',
                'published_at'      => '2026-03-25 16:41:04',
                'created_at'        => '2026-03-25 16:41:04',
                'updated_at'        => '2026-03-25 16:41:04',
                'deleted_at'        => null,
            ],
        ];

        $this->db->table('site_news')->insertBatch($data);
    }
}