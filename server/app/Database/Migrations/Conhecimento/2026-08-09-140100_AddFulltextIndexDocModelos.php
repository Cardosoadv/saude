<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFulltextIndexDocModelos extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                'ALTER TABLE `doc_modelos` ADD FULLTEXT INDEX `ft_doc_modelos_conteudo` (`titulo`, `conteudo`)'
            );
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE `doc_modelos` DROP INDEX `ft_doc_modelos_conteudo`');
        }
    }
}
