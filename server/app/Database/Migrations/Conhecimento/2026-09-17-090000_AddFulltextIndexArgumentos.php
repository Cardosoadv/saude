<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFulltextIndexArgumentos extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                'ALTER TABLE `argumentos` ADD FULLTEXT INDEX `ft_argumentos_titulo_estrategia` (`titulo`, `estrategia_recomendada`)'
            );
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE `argumentos` DROP INDEX `ft_argumentos_titulo_estrategia`');
        }
    }
}
