<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Notificações push (PWA):
 *  - `push_inscricoes`: um registro por navegador/aparelho inscrito, com as preferências daquele aparelho.
 *  - `push_envios`: o que já foi avisado a cada usuário, para nunca repetir o mesmo aviso.
 */
class CreatePushNotificacoes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'endpoint'             => ['type' => 'TEXT'],
            'endpoint_hash'        => ['type' => 'CHAR', 'constraint' => 64],
            'p256dh'               => ['type' => 'VARCHAR', 'constraint' => 120],
            'auth'                 => ['type' => 'VARCHAR', 'constraint' => 40],
            'user_agent'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notificar_prazos'     => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'falhas'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'ultimo_envio_em'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('endpoint_hash', 'uq_push_inscricoes_endpoint');
        $this->forge->addKey('user_id', false, false, 'idx_push_inscricoes_user');
        $this->forge->createTable('push_inscricoes', true);

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'referencia' => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'tipo', 'referencia'], 'uq_push_envios');
        $this->forge->addKey('created_at', false, false, 'idx_push_envios_created');
        $this->forge->createTable('push_envios', true);
    }

    public function down()
    {
        $this->forge->dropTable('push_envios', true);
        $this->forge->dropTable('push_inscricoes', true);
    }
}
