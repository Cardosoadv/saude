<?php

namespace App\Database\Migrations;

use App\Database\Seeds\ModulosSeeder;
use App\Repositories\ModulosRepository;
use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Cadastra o módulo "Documentação" (manual interno, épico #699) no catálogo de
 * módulos, ativo por padrão, para que o administrador possa desligá-lo.
 * Idempotente (ModulosSeeder).
 *
 * As permissões `documentacao.*` vêm da matriz de Config\AuthGroups e não
 * precisam de migration.
 */
class CadastraModuloDocumentacao extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('modulos') || !$this->db->fieldExists('slug', 'modulos')) {
            return; // catálogo ainda não existe; o seeder da migration CorrigeTabelaModulos já inclui a Documentação.
        }

        (new ModulosSeeder(config(Database::class), $this->db))->run();
        (new ModulosRepository())->clearCache();
    }

    public function down()
    {
        // O módulo cadastrado é inofensivo; nada a desfazer.
    }
}
