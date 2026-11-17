<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\TemplateInstaller;
use CodeIgniter\Database\Migration;

/**
 * Conversão do site para o conteúdo gerenciável (épico #641, issue #646).
 *
 * Instala o template "Clássico" (o site atual), ativa-o se ainda não houver
 * outro e, em site sem nenhuma página, grava o conteúdo atual no banco.
 * Idempotente e não destrutiva: instalações com conteúdo não são alteradas.
 */
class InstalaTemplateClassico extends Migration
{
    public function up()
    {
        (new TemplateInstaller())->instalarCore('classico');
    }

    public function down()
    {
        // Intencionalmente vazio: remover o template apagaria conteúdo do escritório.
    }
}
