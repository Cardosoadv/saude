<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Importação de extratos bancários (OFX): uma linha por envio de arquivo
 * (`fin_extratos_importacoes`) e uma por operação bancária
 * (`fin_extratos_movimentos`).
 *
 * A idempotência é garantida pelo índice único (conta_id, chave). A linha
 * excluída (soft delete) continua ocupando a chave de propósito: excluir um
 * movimento não permite reimportá-lo sem aviso; para reabrir ou ignorar usa-se
 * o status.
 *
 * `conta_id` e `importacao_id` são referências lógicas (sem FK), como nos demais
 * registros auxiliares do módulo, para o histórico não depender de exclusões.
 */
class CreateFinanceiroExtratos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_importacao'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'formato'             => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ofx'],
            'arquivo_nome'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'arquivo_hash'        => ['type' => 'CHAR', 'constraint' => 64],
            'periodo_inicio'      => ['type' => 'DATE', 'null' => true],
            'periodo_fim'         => ['type' => 'DATE', 'null' => true],
            'saldo_final_arquivo' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'total_lidos'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'total_novos'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'total_duplicados'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'total_conflitos'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'processando'],
            'enviado_por'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_importacao', true);
        $this->forge->addKey(['conta_id', 'arquivo_hash']);
        $this->forge->createTable('fin_extratos_importacoes', true);

        $this->forge->addField([
            'id_movimento'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conta_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'importacao_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'chave'           => ['type' => 'CHAR', 'constraint' => 64],
            'chave_tipo'      => ['type' => 'VARCHAR', 'constraint' => 5],
            'fitid'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'data'            => ['type' => 'DATE'],
            'valor_centavos'  => ['type' => 'BIGINT', 'constraint' => 20],
            'tipo_banco'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'memo'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'nome_contraparte' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => 'pendente'],
            'pagamento_tipo'  => ['type' => 'VARCHAR', 'constraint' => 15, 'null' => true],
            'pagamento_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'conciliado_por'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'conciliado_em'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id_movimento', true);
        $this->forge->addUniqueKey(['conta_id', 'chave'], 'uq_fin_extratos_mov_conta_chave');
        $this->forge->addKey(['conta_id', 'data'], false, false, 'idx_fin_extratos_mov_conta_data');
        $this->forge->addKey('status', false, false, 'idx_fin_extratos_mov_status');
        $this->forge->addKey('importacao_id', false, false, 'idx_fin_extratos_mov_importacao');
        $this->forge->createTable('fin_extratos_movimentos', true);
    }

    public function down()
    {
        $this->forge->dropTable('fin_extratos_movimentos', true);
        $this->forge->dropTable('fin_extratos_importacoes', true);
    }
}
