<?php

namespace App\Database\Migrations;

use App\Database\Seeds\ModulosSeeder;
use App\Repositories\ModulosRepository;
use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Cadastra o módulo "Site" no catálogo de módulos (ativo por padrão), para que
 * instalações sem site público possam desligá-lo. Idempotente (ModulosSeeder).
 */
class CadastraModuloSite extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('modulos') || !$this->db->fieldExists('slug', 'modulos')) {
            return; // catálogo ainda não existe; o seeder da migration CorrigeTabelaModulos já inclui o Site.
        }

        (new ModulosSeeder(config(Database::class), $this->db))->run();
        (new ModulosRepository())->clearCache();
    }

    public function down()
    {
        // O módulo cadastrado é inofensivo; nada a desfazer.
    }
}
