<?php

namespace Tests\Support\Liquidacao;

/**
 * Cria, em SQLite, as tabelas usadas pelos testes do módulo de liquidação.
 * Mantenha as colunas em dia com as migrations de Processos/*Liquidacao*.
 */
trait ComTabelasLiquidacao
{
    protected function criarTabelasLiquidacao(): void
    {
        $tabelas = [
            'liquidacoes_documentos', 'liquidacoes_pagamentos', 'liquidacoes_historico_calculos', 'itens_liquidacao',
            'liquidacoes', 'processos', 'auditoria', 'indices_importacoes', 'indices_valores', 'indices_economicos',
        ];
        foreach ($tabelas as $tabela) {
            $this->db->query("DROP TABLE IF EXISTS db_{$tabela}");
        }

        $this->db->query('CREATE TABLE db_auditoria (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, table_name VARCHAR(100), action_type VARCHAR(20), dados_antigos TEXT, dados_novos TEXT, ip_address VARCHAR(50), created_at DATETIME, updated_at DATETIME)');
        $this->db->query('CREATE TABLE db_processos (id_processo INTEGER PRIMARY KEY AUTOINCREMENT, numero_processo VARCHAR(50), cliente_id INTEGER, responsavel_id INTEGER, arquived_at DATETIME, deleted_at DATETIME)');
        $this->db->query('CREATE TABLE db_liquidacoes (liquidacao_id INTEGER PRIMARY KEY AUTOINCREMENT, processo_id INTEGER, tipo_liquidacao VARCHAR(50), status VARCHAR(50), data_liquidacao DATE, data_homologacao DATE, valor_total BIGINT DEFAULT 0, observacoes TEXT, liquidador_id INTEGER, data_base DATE, criterio_padrao TEXT, calculado_em DATETIME, lancar_honorarios INTEGER DEFAULT 0, honorarios_percentual DECIMAL(7,4), contrato_id INTEGER, created_at DATETIME, updated_at DATETIME)');
        $this->db->query('CREATE TABLE db_itens_liquidacao (item_id INTEGER PRIMARY KEY AUTOINCREMENT, liquidacao_id INTEGER, tipo_item VARCHAR(50), descricao TEXT, base_calculo TEXT, periodo_inicio DATE, periodo_fim DATE, data_inicio DATE, data_fim DATE, taxa INTEGER, valor_base BIGINT, valor_calculado BIGINT, fundamento_legal TEXT, criterio TEXT, memoria_calculo TEXT)');
        $this->db->query('CREATE TABLE db_liquidacoes_historico_calculos (historico_id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, data_calculo DATETIME, valor_anterior BIGINT, valor_novo BIGINT, usuario_id INTEGER, motivo_alteracao TEXT)');
        $this->db->query('CREATE TABLE db_liquidacoes_pagamentos (pagamento_id INTEGER PRIMARY KEY AUTOINCREMENT, liquidacao_id INTEGER, numero_parcela INTEGER, valor_parcela BIGINT, data_vencimento DATE, data_pagamento DATE, status_pagamento VARCHAR(50), forma_pagamento VARCHAR(50), comprovante_url VARCHAR(255), aviso_enviado_em DATETIME, lancamentos_financeiros TEXT, observacao TEXT)');
        $this->db->query('CREATE TABLE db_liquidacoes_documentos (documento_id INTEGER PRIMARY KEY AUTOINCREMENT, processo_id INTEGER, liquidacao_id INTEGER, tipo_documento VARCHAR(50), nome_arquivo VARCHAR(255), url_arquivo VARCHAR(255), drive_id VARCHAR(100), mime_type VARCHAR(100), tamanho INTEGER, pagamento_id INTEGER, data_upload DATETIME, usuario_id INTEGER)');
        $this->db->query('CREATE TABLE db_indices_economicos (id_indice INTEGER PRIMARY KEY AUTOINCREMENT, codigo VARCHAR(30) UNIQUE, nome VARCHAR(120), periodicidade VARCHAR(10), fonte VARCHAR(20), codigo_serie INTEGER, ativo INTEGER DEFAULT 1, created_at DATETIME, updated_at DATETIME)');
        $this->db->query('CREATE TABLE db_indices_valores (id_valor INTEGER PRIMARY KEY AUTOINCREMENT, indice_id INTEGER, competencia DATE, valor DECIMAL(18,8), origem VARCHAR(10), importado_em DATETIME, UNIQUE (indice_id, competencia))');
        $this->db->query('CREATE TABLE db_indices_importacoes (id_importacao INTEGER PRIMARY KEY AUTOINCREMENT, indice_id INTEGER, executado_em DATETIME, status VARCHAR(10), novos_valores INTEGER, mensagem TEXT)');
    }

    /** @param array<string, array<string, string>> $series codigo => [AAAA-MM => percentual] */
    protected function semearIndices(array $series): void
    {
        $id = 0;
        foreach ($series as $codigo => $valores) {
            $id++;
            $this->db->table('indices_economicos')->insert([
                'id_indice' => $id, 'codigo' => $codigo, 'nome' => $codigo, 'periodicidade' => 'mensal',
                'fonte' => 'manual', 'ativo' => 1,
            ]);
            foreach ($valores as $mes => $valor) {
                $this->db->table('indices_valores')->insert([
                    'indice_id' => $id, 'competencia' => "{$mes}-01", 'valor' => $valor, 'origem' => 'manual',
                ]);
            }
        }
        service('cache')->clean();
    }
}
