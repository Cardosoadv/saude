<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBaseConhecimento extends Migration
{
    public function up()
    {
        // doc_modelos
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['Rascunho', 'Revisão', 'Publicado'], 'default' => 'Rascunho'],
            'titulo'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'ramo_id'           => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'conteudo'          => ['type' => 'LONGTEXT'],
            'placeholders'      => ['type' => 'LONGTEXT', 'null' => true],
            'ultima_revisao_em' => ['type' => 'DATETIME', 'null' => true],
            'alerta_vencimento' => ['type' => 'INT', 'constraint' => 11, 'default' => 365],
            'versao'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1.0.0'],
            'ativo'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('ramo_id', 'juris_ramos_direito', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('doc_modelos', true);

        // kb_favoritos
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'entidade_type'    => ['type' => 'ENUM', 'constraint' => ['jurisprudencia', 'modelo']],
            'entidade_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'anotacao_privada' => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('kb_favoritos', true);

        // kb_prazos_estrategicos
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'titulo'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'prazo_legal'     => ['type' => 'INT', 'constraint' => 11],
            'prazo_seguranca' => ['type' => 'INT', 'constraint' => 11],
            'descricao'       => ['type' => 'TEXT', 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['Ativo', 'Inativo'], 'default' => 'Ativo'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('kb_prazos_estrategicos', true);
    }

    public function down()
    {
        $this->forge->dropTable('kb_prazos_estrategicos', true);
        $this->forge->dropTable('kb_favoritos', true);
        $this->forge->dropTable('doc_modelos', true);
    }
}
