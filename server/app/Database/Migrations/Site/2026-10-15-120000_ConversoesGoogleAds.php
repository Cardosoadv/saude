<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\SeedImporter;
use CodeIgniter\Database\Migration;

/**
 * Conversões do Google Ads no site: cria, se ainda não existirem, as configurações
 * `integracoes.ads_id`, `integracoes.ads_conversao_formulario` e `integracoes.ads_conversao_whatsapp`.
 *
 * Idempotente e não destrutiva: nunca sobrescreve conteúdo da clínica.
 */
class ConversoesGoogleAds extends Migration
{
    private const CONFIGURACOES = ['integracoes.ads_id', 'integracoes.ads_conversao_formulario', 'integracoes.ads_conversao_whatsapp'];

    public function up()
    {
        if (!$this->db->tableExists('site_templates')) {
            return; // módulo do site ainda não instalado: o seed completo trará tudo
        }

        $diretorio = APPPATH . 'Templates/classico/';
        $seed      = json_decode((string) file_get_contents($diretorio . 'seed/content.json'), true);
        $manifesto = json_decode((string) file_get_contents($diretorio . 'template.json'), true);

        (new SeedImporter())->importar([
            'configuracoes' => array_intersect_key($seed['configuracoes'] ?? [], array_flip(self::CONFIGURACOES)),
        ], $manifesto);
    }

    public function down()
    {
        // As configurações ficam: removê-las apagaria conteúdo da clínica.
    }
}
