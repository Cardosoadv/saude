<?php

namespace App\Database\Migrations\Conhecimento;

use CodeIgniter\Database\Migration;

/**
 * Evita duplicidade de favoritos para o mesmo usuário/entidade
 * (ex.: cliques duplos no botão de favoritar).
 */
class AddUniqueConstraintToKbFavoritos extends Migration
{
    public function up()
    {
        $this->forge->addKey(['user_id', 'entidade_type', 'entidade_id'], false, true, 'uniq_kb_favoritos_usuario_entidade');
        $this->forge->processIndexes('kb_favoritos');
    }

    public function down()
    {
        $this->forge->dropKey('kb_favoritos', 'uniq_kb_favoritos_usuario_entidade');
    }
}
