<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lista de advogados com acesso a registros restritos (privacidade granular).
 *
 * Tabela polimórfica única para processos, clientes e contratos. Quem está aqui
 * tem acesso de leitura e edição ao registro; o responsável (`responsavel_id`
 * da própria entidade) e os usuários com `privacidade.bypass` não precisam
 * constar nela.
 */
class CreateAcessosEntidades extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entidade_tipo' => ['type' => 'ENUM', 'constraint' => ['processo', 'cliente', 'contrato']],
            'entidade_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'concedido_por' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['entidade_tipo', 'entidade_id', 'user_id'], 'uniq_acessos_entidade_usuario');
        $this->forge->addKey(['user_id', 'entidade_tipo'], false, false, 'idx_acessos_usuario_tipo');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('concedido_por', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('acessos_entidades', true);
    }

    public function down()
    {
        $this->forge->dropTable('acessos_entidades', true);
    }
}
