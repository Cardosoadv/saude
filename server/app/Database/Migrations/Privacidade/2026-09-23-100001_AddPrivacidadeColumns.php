<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Privacidade granular: visibilidade e responsável em processos, clientes e contratos.
 *
 * - `visibilidade`: 'restrito' por padrão (inclusive para os registros já
 *   existentes). O filtro só passa a valer quando a configuração
 *   `privacidade_granular_ativa` for ligada, depois de rodar
 *   `php spark privacidade:backfill` e revisar o relatório.
 * - `clientes.responsavel_id` / `contratos.responsavel_id`: advogado (users.id)
 *   responsável. Não confundir com `clientes.usuario_id`, que é o login do
 *   próprio cliente na Área do Cliente. `contratos.responsavel_interno` (texto
 *   livre) é mantido por enquanto apenas para leitura/migração.
 *
 * Idempotente: DDL no MySQL não é transacional, então cada coluna, índice e
 * chave estrangeira só é criado se ainda não existir (permite reexecutar após
 * uma falha parcial).
 */
class AddPrivacidadeColumns extends Migration
{
    private const VISIBILIDADE = [
        'type'       => 'ENUM',
        'constraint' => ['publico', 'restrito'],
        'default'    => 'restrito',
        'null'       => false,
    ];

    public function up()
    {
        foreach (['processos', 'clientes', 'contratos'] as $tabela) {
            if (!$this->temColuna('visibilidade', $tabela)) {
                $this->forge->addColumn($tabela, ['visibilidade' => self::VISIBILIDADE]);
            }
            if (!$this->temIndice($tabela, 'visibilidade')) {
                $this->forge->addKey('visibilidade', false, false, "idx_{$tabela}_visibilidade");
                $this->forge->processIndexes($tabela);
            }
        }

        foreach (['clientes', 'contratos'] as $tabela) {
            if (!$this->temColuna('responsavel_id', $tabela)) {
                $this->forge->addColumn($tabela, [
                    'responsavel_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                ]);
            }
            if (!$this->temIndice($tabela, 'responsavel_id')) {
                $this->forge->addKey('responsavel_id', false, false, "idx_{$tabela}_responsavel_id");
                $this->forge->processIndexes($tabela);
            }

            if ($this->db->DBDriver !== 'SQLite3' && !$this->temChaveEstrangeira($tabela, 'responsavel_id')) {
                // Ao criar a FK o MySQL reconstrói a tabela e revalida todas as FKs
                // existentes; dados legados inconsistentes (ex.: clientes.origem_id
                // apontando para origem excluída) abortariam a migration. A coluna
                // nova está vazia, então não há o que validar nela.
                $this->db->disableForeignKeyChecks();
                try {
                    $this->forge->addForeignKey('responsavel_id', 'users', 'id', 'CASCADE', 'SET NULL');
                    $this->forge->processIndexes($tabela);
                } finally {
                    $this->db->enableForeignKeyChecks();
                }
            }
        }

        $existe = $this->db->table('configuracoes')
            ->where('nome', 'privacidade_granular_ativa')
            ->countAllResults();

        if ($existe === 0) {
            $this->db->table('configuracoes')->insert([
                'nome'       => 'privacidade_granular_ativa',
                'valor'      => '0',
                'descricao'  => 'Ativa a privacidade granular de processos, clientes e contratos (1 = ativo). '
                    . 'Rode "php spark privacidade:backfill" e revise o relatório antes de ativar.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('configuracoes')->where('nome', 'privacidade_granular_ativa')->delete();

        // Seguindo o padrão do projeto (ver AddProcessoIdToMensagens), o rollback
        // de colunas é pulado em SQLite.
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        foreach (['clientes', 'contratos'] as $tabela) {
            if ($this->temChaveEstrangeira($tabela, 'responsavel_id')) {
                $this->forge->dropForeignKey($tabela, "{$tabela}_responsavel_id_foreign");
            }
            if ($this->temColuna('responsavel_id', $tabela)) {
                $this->forge->dropColumn($tabela, 'responsavel_id');
            }
        }

        foreach (['processos', 'clientes', 'contratos'] as $tabela) {
            if ($this->temColuna('visibilidade', $tabela)) {
                $this->forge->dropColumn($tabela, 'visibilidade');
            }
        }
    }

    private function temColuna(string $coluna, string $tabela): bool
    {
        // fieldExists() usa cache de nomes de colunas por conexão.
        $this->db->resetDataCache();

        return $this->db->fieldExists($coluna, $tabela);
    }

    private function temIndice(string $tabela, string $coluna): bool
    {
        foreach ($this->db->getIndexData($tabela) as $indice) {
            if ((array) $indice->fields === [$coluna]) {
                return true;
            }
        }

        return false;
    }

    private function temChaveEstrangeira(string $tabela, string $coluna): bool
    {
        foreach ($this->db->getForeignKeyData($tabela) as $fk) {
            if (in_array($coluna, (array) $fk->column_name, true)) {
                return true;
            }
        }

        return false;
    }
}
