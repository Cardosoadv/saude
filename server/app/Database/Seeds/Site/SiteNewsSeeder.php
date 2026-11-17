<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Artigos de exemplo (substitua pelos da sua clínica no painel).
 * Requer ao menos um usuário (author_id = 1) e as categorias de SiteNewsCategoriesSeeder.
 */
class SiteNewsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'category_id'       => 1,
                'author_id'         => 1,
                'title'             => 'Check-up anual: por que fazer e o que esperar',
                'slug'              => 'check-up-anual-por-que-fazer-e-o-que-esperar',
                'summary'           => 'Entenda a importância das avaliações periódicas e como se preparar para a consulta.',
                'content'           => '<p>O check-up anual permite identificar riscos precocemente e acompanhar a evolução da sua saúde. Converse com o seu profissional sobre quais exames fazem sentido para a sua idade e histórico.</p><p>Este conteúdo é informativo e não substitui a avaliação de um profissional de saúde.</p>',
                'featured_image'    => null,
                'meta_title'        => '',
                'meta_description'  => '',
                'status'            => 'published',
                'published_at'      => date('Y-m-d H:i:s'),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
                'deleted_at'        => null,
            ],
            [
                'category_id'       => 2,
                'author_id'         => 1,
                'title'             => 'Hábitos simples que ajudam na prevenção',
                'slug'              => 'habitos-simples-que-ajudam-na-prevencao',
                'summary'           => 'Sono, alimentação e atividade física: pequenas mudanças fazem diferença.',
                'content'           => '<p>Manter uma rotina de sono regular, alimentação equilibrada e atividade física frequente contribui para a prevenção de diversas condições.</p><p>Este conteúdo é informativo e não substitui a avaliação de um profissional de saúde.</p>',
                'featured_image'    => null,
                'meta_title'        => '',
                'meta_description'  => '',
                'status'            => 'published',
                'published_at'      => date('Y-m-d H:i:s'),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
                'deleted_at'        => null,
            ],
            [
                'category_id'       => 4,
                'author_id'         => 1,
                'title'             => 'Novo canal de agendamento',
                'slug'              => 'novo-canal-de-agendamento',
                'summary'           => 'Agora você pode solicitar seu atendimento pelo site.',
                'content'           => '<p>Preencha o formulário de contato e a nossa equipe retornará para confirmar o melhor horário.</p>',
                'featured_image'    => null,
                'meta_title'        => '',
                'meta_description'  => '',
                'status'            => 'published',
                'published_at'      => date('Y-m-d H:i:s'),
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
                'deleted_at'        => null,
            ],
        ];

        $this->db->table('site_news')->insertBatch($data);
    }
}
