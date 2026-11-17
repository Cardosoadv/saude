<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\SeedImporter;
use App\Libraries\Site\Template\TemplateInstaller;
use CodeIgniter\Database\Migration;

/**
 * Telas de notícias e da área do cliente no template Clássico (issue #652).
 *
 * Reinstala o Clássico (atualiza versão e assets) e cria, se ainda não
 * existirem, as páginas de sistema `noticias`, `area-cliente` e
 * `painel-cliente` e a configuração `noticias.filtros`. Em instalações que já
 * tinham o seed da #646 essas peças não existiam; nas novas elas já vêm
 * no seed e nada é alterado. Nunca sobrescreve conteúdo da clínica.
 */
class CompletaConteudoSistemaClassico extends Migration
{
    private const PAGINAS = ['noticias', 'area-cliente', 'painel-cliente'];
    private const CONFIGURACOES = ['noticias.filtros'];

    public function up()
    {
        (new TemplateInstaller())->instalarCore('classico');

        $diretorio = APPPATH . 'Templates/classico/';
        $seed      = json_decode((string) file_get_contents($diretorio . 'seed/content.json'), true);
        $manifesto = json_decode((string) file_get_contents($diretorio . 'template.json'), true);

        $parcial = [
            'configuracoes' => array_intersect_key($seed['configuracoes'] ?? [], array_flip(self::CONFIGURACOES)),
            'paginas'       => array_values(array_filter($seed['paginas'] ?? [], static fn ($p) => in_array($p['slug'], self::PAGINAS, true))),
        ];

        (new SeedImporter())->importar($parcial, $manifesto);
    }

    public function down()
    {
        // Intencionalmente vazio: remover apagaria conteúdo da clínica.
    }
}
