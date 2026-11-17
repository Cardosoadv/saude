<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinanceiroLancamentos extends Migration
{
    public function up()
    {
        // fin_despesas
        $this->forge->addField([
            'id_despesa'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'despesa'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'vencimento_dt' => ['type' => 'DATE', 'null' => true],
            'valor'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'categoria'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'fornecedor'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'contrato_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'comentario'    => ['type' => 'TEXT', 'null' => true],
            'rateio'        => ['type' => 'JSON', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_despesa');
        $this->forge->addKey('deleted_at', false, false, 'idx_fin_despesas_deleted');
        $this->forge->addKey(['vencimento_dt', 'deleted_at'], false, false, 'idx_fin_despesas_venc');
        $this->forge->addKey('categoria', false, false, 'idx_fin_despesas_categoria');
        $this->forge->addKey('fornecedor', false, false, 'idx_fin_despesas_fornecedor');
        $this->forge->addForeignKey('categoria', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('fornecedor', 'fin_fornecedores', 'id_fornecedor', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('contrato_id', 'contratos', 'id_contrato', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_despesas', true);

        // fin_receitas
        $this->forge->addField([
            'id_receita'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'receita'              => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'vencimento_dt'        => ['type' => 'DATE', 'null' => true],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'categoria'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'cliente_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'contrato_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'comentario'           => ['type' => 'TEXT', 'null' => true],
            'rateio'               => ['type' => 'JSON', 'null' => true],
            'despesa_reembolso_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'tipo'                 => ['type' => 'ENUM', 'constraint' => ['reembolso', 'receita'], 'default' => 'receita'],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_receita');
        $this->forge->addKey('deleted_at', false, false, 'idx_fin_receitas_deleted');
        $this->forge->addKey(['vencimento_dt', 'deleted_at'], false, false, 'idx_fin_receitas_venc');
        $this->forge->addKey('cliente_id', false, false, 'idx_fin_receitas_cliente');
        $this->forge->addKey('categoria', false, false, 'idx_fin_receitas_categoria');
        $this->forge->addForeignKey('categoria', 'fin_categorias', 'id_categoria', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('contrato_id', 'contratos', 'id_contrato', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('despesa_reembolso_id', 'fin_despesas', 'id_despesa', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_receitas', true);

        // fin_pgto_despesas
        $this->forge->addField([
            'id_pgto_despesa'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pagamento_despesa_dt' => ['type' => 'DATE', 'null' => true],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'despesa_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'conta_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'comentario'           => ['type' => 'TEXT', 'null' => true],
            'rateio'               => ['type' => 'JSON', 'null' => true],
            'cliente_reembolso'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_pgto_despesa');
        $this->forge->addKey('deleted_at', false, false, 'idx_fin_pgto_desp_deleted');
        $this->forge->addKey('pagamento_despesa_dt', false, false, 'idx_fin_pgto_desp_data');
        $this->forge->addKey('despesa_id', false, false, 'idx_fin_pgto_desp_despesa');
        $this->forge->addForeignKey('despesa_id', 'fin_despesas', 'id_despesa', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('conta_id', 'fin_contas', 'id_conta', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cliente_reembolso', 'clientes', 'id_cliente', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_pgto_despesas', true);

        // fin_pgto_receitas
        $this->forge->addField([
            'id_pgto_receita'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pagamento_receita_dt' => ['type' => 'DATE', 'null' => true],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0.00],
            'receita_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'conta_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'comentario'           => ['type' => 'TEXT', 'null' => true],
            'rateio'               => ['type' => 'JSON', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_pgto_receita');
        $this->forge->addKey('deleted_at', false, false, 'idx_fin_pgto_rec_deleted');
        $this->forge->addKey('pagamento_receita_dt', false, false, 'idx_fin_pgto_rec_data');
        $this->forge->addKey('receita_id', false, false, 'idx_fin_pgto_rec_receita');
        $this->forge->addForeignKey('receita_id', 'fin_receitas', 'id_receita', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('conta_id', 'fin_contas', 'id_conta', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fin_pgto_receitas', true);

        // cont_lancamentos
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_origem'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'origem_tabela'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'data_lancamento'           => ['type' => 'DATE'],
            'descricao'                 => ['type' => 'VARCHAR', 'constraint' => 255],
            'conta_contabil_credito_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'conta_contabil_debito_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'valor'                     => ['type' => 'BIGINT', 'constraint' => 20],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('conta_contabil_credito_id', 'cont_plano_contas', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('conta_contabil_debito_id', 'cont_plano_contas', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('cont_lancamentos', true);
    }

    public function down()
    {
        $this->forge->dropTable('cont_lancamentos', true);
        $this->forge->dropTable('fin_pgto_receitas', true);
        $this->forge->dropTable('fin_pgto_despesas', true);
        $this->forge->dropTable('fin_receitas', true);
        $this->forge->dropTable('fin_despesas', true);
    }
}
