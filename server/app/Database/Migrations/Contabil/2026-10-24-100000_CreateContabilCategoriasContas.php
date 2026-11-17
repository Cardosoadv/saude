<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dicionário categoria financeira -> conta contábil usado na importação do financeiro.
 *
 * Cada linha diz qual conta recebe a perna "da categoria" de um lançamento:
 * o crédito em receitas e o débito em despesas. A mesma categoria pode ter uma
 * conta para receitas e outra para despesas, por isso `tipo` faz parte da chave.
 *
 * @version 1.0.0
 */
class CreateContabilCategoriasContas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entity_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'categoria_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'account_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['entity_id', 'categoria_id', 'tipo'], 'uk_contabil_cat_contas');
        $this->forge->createTable('contabil_categorias_contas', true);
    }

    public function down()
    {
        $this->forge->dropTable('contabil_categorias_contas', true);
    }
}
