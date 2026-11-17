<?php

namespace App\Database\Migrations\Conhecimento;

use CodeIgniter\Database\Migration;

class AlterProcessoIdNullableInProcessoArgumentos extends Migration
{
    public function up()
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            $this->forge->modifyColumn('processo_argumentos', [
                'processo_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
            ]);
        } else {
            $this->db->query("ALTER TABLE processo_argumentos MODIFY processo_id INT(11) UNSIGNED NULL");
        }
    }

    public function down()
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            $this->forge->modifyColumn('processo_argumentos', [
                'processo_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                ],
            ]);
        } else {
            $this->db->query("ALTER TABLE processo_argumentos MODIFY processo_id INT(11) UNSIGNED NOT NULL");
        }
    }
}
