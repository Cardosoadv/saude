<?php

namespace App\Database\Migrations;

use App\Database\Seeds\ModulosSeeder;
use App\Repositories\ModulosRepository;
use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Corrige a tabela `modulos` criada pela migration consolidada CreateGestao.
 *
 * Em instalações novas ela nasceu com uma FK indevida `modulos.id → users.id`
 * (um módulo não é um usuário) e sem AUTO_INCREMENT no `id`. Instalações
 * antigas já tinham a tabela no formato correto e não são afetadas.
 *
 * Adiciona `slug`, o identificador estável do módulo usado pelo back e pelo
 * front (o `nome` é só rótulo e pode ter acento ou mudar), preenche o slug dos
 * registros existentes e cadastra os módulos do catálogo (ModulosSeeder).
 *
 * Idempotente: DDL no MySQL não é transacional, então cada passo só roda se
 * ainda for necessário (permite reexecutar após uma falha parcial).
 */
class CorrigeTabelaModulos extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            foreach ($this->chavesEstrangeiras('id') as $nome) {
                $this->forge->dropForeignKey('modulos', $nome);
            }

            if (!$this->idAutoIncremento()) {
                $this->forge->modifyColumn('modulos', [
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                ]);
            }
        }

        if (!$this->temColuna('slug')) {
            $this->forge->addColumn('modulos', [
                'slug' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'nome'],
            ]);
        }

        (new ModulosSeeder(config(Database::class), $this->db))->run();

        if (!$this->temIndice('slug')) {
            $this->forge->addUniqueKey('slug', 'uq_modulos_slug');
            $this->forge->processIndexes('modulos');
        }

        // O seeder grava direto no banco: invalida o cache do repositório, que
        // ainda guardaria as linhas sem slug por até uma hora.
        (new ModulosRepository())->clearCache();
    }

    public function down()
    {
        // A FK removida era um erro e o AUTO_INCREMENT é o formato correto: só o slug é desfeito.
        if ($this->temIndice('slug')) {
            $this->forge->dropKey('modulos', 'uq_modulos_slug', false);
        }

        if ($this->temColuna('slug')) {
            $this->forge->dropColumn('modulos', 'slug');
        }
    }

    private function temColuna(string $coluna): bool
    {
        // fieldExists() usa cache de nomes de colunas por conexão.
        $this->db->resetDataCache();

        return $this->db->fieldExists($coluna, 'modulos');
    }

    private function temIndice(string $coluna): bool
    {
        foreach ($this->db->getIndexData('modulos') as $indice) {
            if ((array) $indice->fields === [$coluna]) {
                return true;
            }
        }

        return false;
    }

    private function idAutoIncremento(): bool
    {
        $coluna = $this->db->query(
            'SHOW COLUMNS FROM ' . $this->db->escapeIdentifiers($this->db->prefixTable('modulos')) . ' WHERE Field = ?',
            ['id'],
        )->getRow();

        return $coluna !== null && str_contains(strtolower((string) $coluna->Extra), 'auto_increment');
    }

    /**
     * @return list<string> Nomes das FKs da tabela `modulos` sobre a coluna.
     */
    private function chavesEstrangeiras(string $coluna): array
    {
        $nomes = [];
        foreach ($this->db->getForeignKeyData('modulos') as $fk) {
            if (in_array($coluna, (array) $fk->column_name, true)) {
                $nomes[] = $fk->constraint_name;
            }
        }

        return $nomes;
    }
}
