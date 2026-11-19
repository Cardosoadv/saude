<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Introduz o rateio de categorias em despesas e receitas: uma mesma despesa
 * ou receita passa a poder ser classificada em mais de uma categoria
 * financeira, cada uma com um valor próprio que deve somar o valor total
 * do lançamento.
 *
 * Substitui a antiga coluna única `categoria` (1:N) de fin_despesas e
 * fin_receitas pelas tabelas de junção fin_despesas_categorias e
 * fin_receitas_categorias (N:N), migrando os dados existentes (1 categoria
 * = 100% do valor) antes de remover a coluna antiga.
 */
class CreateFinanceiroCategoriasSplit extends Migration
{
    public function up()
    {
        // fin_despesas_categorias
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'despesa_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'categoria_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'valor'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('despesa_id', false, false, 'idx_fin_desp_cat_despesa');
        $this->forge->addKey('categoria_id', false, false, 'idx_fin_desp_cat_categoria');
        $this->forge->addUniqueKey(['despesa_id', 'categoria_id'], 'uk_fin_desp_cat_despesa_categoria');
        $this->forge->addForeignKey('despesa_id', 'fin_despesas', 'id_despesa', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('categoria_id', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_despesas_categorias', true);

        // fin_receitas_categorias
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'receita_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'categoria_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'valor'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('receita_id', false, false, 'idx_fin_rec_cat_receita');
        $this->forge->addKey('categoria_id', false, false, 'idx_fin_rec_cat_categoria');
        $this->forge->addUniqueKey(['receita_id', 'categoria_id'], 'uk_fin_rec_cat_receita_categoria');
        $this->forge->addForeignKey('receita_id', 'fin_receitas', 'id_receita', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('categoria_id', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_receitas_categorias', true);

        // Migra os dados existentes: a categoria única de cada lançamento vira
        // o primeiro (e único, por enquanto) item do rateio, com 100% do valor.
        $now = date('Y-m-d H:i:s');
        $prefix = $this->db->DBPrefix;

        $this->db->query(
            "INSERT INTO {$prefix}fin_despesas_categorias (despesa_id, categoria_id, valor, created_at, updated_at)
             SELECT d.id_despesa, d.categoria, d.valor, ?, ?
             FROM {$prefix}fin_despesas d
             INNER JOIN {$prefix}fin_categorias c ON c.id_categoria = d.categoria
             WHERE d.categoria IS NOT NULL",
            [$now, $now]
        );

        $this->db->query(
            "INSERT INTO {$prefix}fin_receitas_categorias (receita_id, categoria_id, valor, created_at, updated_at)
             SELECT r.id_receita, r.categoria, r.valor, ?, ?
             FROM {$prefix}fin_receitas r
             INNER JOIN {$prefix}fin_categorias c ON c.id_categoria = r.categoria
             WHERE r.categoria IS NOT NULL",
            [$now, $now]
        );

        // Remove a antiga coluna única `categoria`, agora substituída pelo rateio.
        // No SQLite (usado nos testes), Forge precisa recriar a tabela inteira para
        // dropar coluna/FK, o que é instável neste projeto — mesmo padrão já adotado
        // em outras migrations. Nesse driver a coluna
        // antiga simplesmente deixa de ser usada pela aplicação.
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropForeignKey('fin_despesas', 'fin_despesas_categoria_foreign');
            $this->forge->dropColumn('fin_despesas', 'categoria');

            $this->forge->dropForeignKey('fin_receitas', 'fin_receitas_categoria_foreign');
            $this->forge->dropColumn('fin_receitas', 'categoria');
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->addColumn('fin_despesas', [
                'categoria' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            ]);
            $this->forge->addColumn('fin_receitas', [
                'categoria' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            ]);

            // Restaura a categoria única a partir do primeiro item do rateio.
            $prefix = $this->db->DBPrefix;
            $this->db->query(
                "UPDATE {$prefix}fin_despesas SET categoria = (
                    SELECT categoria_id FROM {$prefix}fin_despesas_categorias
                    WHERE {$prefix}fin_despesas_categorias.despesa_id = {$prefix}fin_despesas.id_despesa
                    ORDER BY {$prefix}fin_despesas_categorias.id ASC LIMIT 1
                )"
            );
            $this->db->query(
                "UPDATE {$prefix}fin_receitas SET categoria = (
                    SELECT categoria_id FROM {$prefix}fin_receitas_categorias
                    WHERE {$prefix}fin_receitas_categorias.receita_id = {$prefix}fin_receitas.id_receita
                    ORDER BY {$prefix}fin_receitas_categorias.id ASC LIMIT 1
                )"
            );

            $this->forge->addKey('categoria', false, false, 'idx_fin_despesas_categoria');
            $this->forge->addForeignKey('categoria', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
            $this->forge->processIndexes('fin_despesas');

            $this->forge->addKey('categoria', false, false, 'idx_fin_receitas_categoria');
            $this->forge->addForeignKey('categoria', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
            $this->forge->processIndexes('fin_receitas');
        }

        $this->forge->dropTable('fin_receitas_categorias', true);
        $this->forge->dropTable('fin_despesas_categorias', true);
    }
}
