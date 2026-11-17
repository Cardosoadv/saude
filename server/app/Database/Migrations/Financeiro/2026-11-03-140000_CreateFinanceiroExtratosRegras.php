<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Regras de classificação de movimentos de extrato (`fin_extratos_regras`).
 *
 * Uma regra casa um trecho de texto (contraparte e/ou memo) e o sinal do
 * movimento (entrada/saída) e propõe fornecedor ou cliente, categoria e rateio
 * padrão. As regras só geram sugestão: quem lança é sempre o usuário.
 *
 * `rateio` guarda JSON com percentuais por usuário: [{"id": 1, "percentual": 60}, ...].
 * `conta_id`, `fornecedor_id`, `cliente_id` e `categoria_id` são referências lógicas (sem FK).
 */
class CreateFinanceiroExtratosRegras extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_regra'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'conta_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'campo'         => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'ambos'],
            'padrao'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'sinal'         => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => 'ambos'],
            'fornecedor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'cliente_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'categoria_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'rateio'        => ['type' => 'TEXT', 'null' => true],
            'ativo'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'criado_por'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_regra', true);
        $this->forge->addKey(['ativo', 'conta_id'], false, false, 'idx_fin_extratos_regras_ativo_conta');
        $this->forge->createTable('fin_extratos_regras', true);
    }

    public function down()
    {
        $this->forge->dropTable('fin_extratos_regras', true);
    }
}
