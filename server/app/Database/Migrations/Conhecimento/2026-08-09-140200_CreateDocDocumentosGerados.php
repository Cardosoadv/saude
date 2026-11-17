<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocDocumentosGerados extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'modelo_id'               => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'modelo_titulo_snapshot'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'titulo'                  => ['type' => 'VARCHAR', 'constraint' => 255],
            'entidade_tipo'           => ['type' => 'ENUM', 'constraint' => ['processo', 'caso']],
            'entidade_id'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'cliente_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'ramo_id'                 => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'conteudo_html'           => ['type' => 'LONGTEXT'],
            'placeholders_utilizados' => ['type' => 'LONGTEXT', 'null' => true],
            'status'                  => ['type' => 'ENUM', 'constraint' => ['Rascunho', 'Finalizado'], 'default' => 'Rascunho'],
            'documento_arquivo_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'finalizado_em'           => ['type' => 'DATETIME', 'null' => true],
            'finalizado_por'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('modelo_id', 'doc_modelos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id_cliente', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('ramo_id', 'juris_ramos_direito', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('documento_arquivo_id', 'documentos_arquivos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addKey(['entidade_tipo', 'entidade_id']);
        $this->forge->addKey('status');
        $this->forge->addKey('ramo_id');
        $this->forge->createTable('doc_documentos_gerados', true);

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                'ALTER TABLE `doc_documentos_gerados` ADD FULLTEXT INDEX `ft_doc_documentos_gerados_conteudo` (`titulo`, `conteudo_html`)'
            );
        }
    }

    public function down()
    {
        $this->forge->dropTable('doc_documentos_gerados', true);
    }
}
