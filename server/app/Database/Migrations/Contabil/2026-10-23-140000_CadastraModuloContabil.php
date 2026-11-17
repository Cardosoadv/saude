<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Seeds\ModulosSeeder;
use App\Repositories\ModulosRepository;
use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Cadastra o módulo "Contábil" no catálogo de módulos do sistema.
 * Idempotente via ModulosSeeder.
 *
 * @version 1.0.0
 */
class CadastraModuloContabil extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('modulos') || ! $this->db->fieldExists('slug', 'modulos')) {
            return;
        }

        (new ModulosSeeder(config(Database::class), $this->db))->run();
        (new ModulosRepository())->clearCache();
    }

    public function down()
    {
        // O módulo cadastrado é inofensivo; nada a desfazer.
    }
}
