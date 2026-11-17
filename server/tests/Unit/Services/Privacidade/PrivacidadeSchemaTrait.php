<?php

namespace Tests\Unit\Services\Privacidade;

use CodeIgniter\Database\BaseConnection;

/**
 * Esquema mínimo (SQLite em memória) para os testes de privacidade granular.
 *
 * Os testes não usam `$migrate = true` porque a cadeia completa de migrations
 * hoje não roda no SQLite; recriamos só as colunas envolvidas.
 */
trait PrivacidadeSchemaTrait
{
    protected function criarEsquemaPrivacidade(BaseConnection $db): void
    {
        $forge = \Config\Database::forge($db);

        $tabelas = [
            // Lida pelo Shield ao resolver user_id()/auth().
            'settings' => [
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
                'value'      => ['type' => 'TEXT', 'null' => true],
                'type'       => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'],
                'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ],
            'users' => [
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'username'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'active'     => ['type' => 'INTEGER', 'default' => 1],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ],
            'auth_groups_users' => [
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id'    => ['type' => 'INTEGER'],
                'group'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ],
            'auth_identities' => [
                'id'      => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id' => ['type' => 'INTEGER'],
                'type'    => ['type' => 'VARCHAR', 'constraint' => 255],
                'secret'  => ['type' => 'VARCHAR', 'constraint' => 255],
            ],
            'clientes' => [
                'id_cliente'     => ['type' => 'INTEGER', 'auto_increment' => true],
                'nome'           => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'status_crm'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'responsavel_id' => ['type' => 'INTEGER', 'null' => true],
                'visibilidade'   => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'restrito'],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ],
            'processos' => [
                'id_processo'     => ['type' => 'INTEGER', 'auto_increment' => true],
                'numero_processo' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'titulo_processo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'cliente_id'      => ['type' => 'INTEGER', 'null' => true],
                'responsavel_id'  => ['type' => 'INTEGER', 'null' => true],
                'encerrado'       => ['type' => 'INTEGER', 'default' => 0],
                'visibilidade'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'restrito'],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ],
            'contratos' => [
                'id_contrato'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'numero_contrato'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'cliente_id'          => ['type' => 'INTEGER', 'null' => true],
                'responsavel_interno' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'responsavel_id'      => ['type' => 'INTEGER', 'null' => true],
                'visibilidade'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'restrito'],
                'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            ],
            'acessos_entidades' => [
                'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
                'entidade_tipo' => ['type' => 'VARCHAR', 'constraint' => 10],
                'entidade_id'   => ['type' => 'INTEGER'],
                'user_id'       => ['type' => 'INTEGER'],
                'concedido_por' => ['type' => 'INTEGER', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ],
            'auditoria' => [
                'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
                'user_id'       => ['type' => 'INTEGER', 'null' => true],
                'table_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'action_type'   => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'dados_antigos' => ['type' => 'TEXT', 'null' => true],
                'dados_novos'   => ['type' => 'TEXT', 'null' => true],
                'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ],
        ];

        foreach ($tabelas as $tabela => $campos) {
            $forge->dropTable($tabela, true);
            $forge->addField($campos);
            $forge->addPrimaryKey(array_key_first($campos));
            $forge->createTable($tabela);
        }
    }
}
