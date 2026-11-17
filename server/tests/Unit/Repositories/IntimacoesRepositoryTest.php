<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\IntimacoesRepository;

class IntimacoesRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    public function setUp(): void
    {
        parent::setUp();

        $this->db->query('PRAGMA foreign_keys = OFF;');

        // Limpa tabelas antes de cada teste
        $this->db->query("DROP TABLE IF EXISTS db_intimacoes");
        $this->db->query("DROP TABLE IF EXISTS db_intimacoes_destinatario");
        $this->db->query("DROP TABLE IF EXISTS db_intimacoes_advogados");
        $this->db->query("DROP TABLE IF EXISTS db_intimacoes_tipos_tratamento");
        $this->db->query("DROP TABLE IF EXISTS db_intimacoes_tratamento");
        $this->db->query("DROP TABLE IF EXISTS db_tarefas");
        $this->db->query("DROP TABLE IF EXISTS db_processos");
        $this->db->query("DROP TABLE IF EXISTS db_settings");
        $this->db->query("DROP TABLE IF EXISTS db_auditoria");

        // Criação das tabelas necessárias
        $this->db->query("CREATE TABLE db_intimacoes (
            id_intimacao INTEGER PRIMARY KEY AUTOINCREMENT,
            data_disponibilizacao DATETIME,
            tipoComunicacao TEXT,
            texto TEXT,
            numero_processo TEXT,
            meio TEXT,
            link TEXT,
            numeroComunicacao TEXT,
            hash TEXT,
            synchronize_at DATETIME,
            deleted_at DATETIME,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_intimacoes_destinatario (
            id_pk INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT,
            polo TEXT,
            comunicacao_id INTEGER
        )");

        $this->db->query("CREATE TABLE db_intimacoes_advogados (
            id_pk INTEGER PRIMARY KEY AUTOINCREMENT,
            id INTEGER,
            comunicacao_id INTEGER,
            advogado_id INTEGER,
            advogado_nome TEXT,
            advogado_oab TEXT,
            advogado_oab_uf TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_intimacoes_tipos_tratamento (
            id_tratamento INTEGER PRIMARY KEY AUTOINCREMENT,
            tratamento TEXT
        )");

        $this->db->query("CREATE TABLE db_intimacoes_tratamento (
            intimacao_id INTEGER,
            tratamento_id INTEGER
        )");

        $this->db->query("CREATE TABLE db_tarefas (
            id_tarefa INTEGER PRIMARY KEY AUTOINCREMENT,
            processo_id INTEGER,
            status INTEGER,
            created_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_processos (
            id_processo INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_processo TEXT,
            titulo_processo TEXT,
            cliente_id INTEGER,
            arquived_at DATETIME,
            deleted_at DATETIME,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_settings (
            \"class\" TEXT,
            \"key\" TEXT,
            \"value\" TEXT,
            \"type\" TEXT,
            \"context\" TEXT
        )");

        $this->db->query("CREATE TABLE db_auditoria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            table_name TEXT,
            action_type TEXT,
            dados_antigos TEXT,
            dados_novos TEXT,
            ip_address TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query('PRAGMA foreign_keys = ON;');

        // Limpa o cache globalmente para garantir isolamento
        service('cache')->clean();
        \App\Repositories\BaseRepository::clearVersions();
    }

    public function testBuscarTodasIntimacoesUsesCache()
    {
        // Seed data
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, '12345')");
        $this->db->query("INSERT INTO db_intimacoes_destinatario (nome, comunicacao_id) VALUES ('Dest 1', 1)");

        $repository = new IntimacoesRepository();
        
        // Primeira chamada - deve popular o cache
        $result1 = $repository->buscarTodasIntimacoes();
        $this->assertCount(1, $result1);
        $this->assertEquals('Dest 1', $result1[0]['destinatarios'][0]['nome']);

        // Altera o banco de dados diretamente (sem passar pelo repositório)
        $this->db->query("UPDATE db_intimacoes_destinatario SET nome = 'Dest Alterado' WHERE comunicacao_id = 1");

        // Segunda chamada - deve vir do cache (valor antigo)
        $result2 = $repository->buscarTodasIntimacoes();
        $this->assertEquals('Dest 1', $result2[0]['destinatarios'][0]['nome']);
    }

    public function testSalvarIntimacaoClearsCache()
    {
        // Seed data inicial
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, '12345')");

        $repository = new IntimacoesRepository();
        
        // Popula o cache (deve conter 1 registro)
        $repository->buscarTodasIntimacoes();

        // Salva nova intimação - deve limpar o cache
        $repository->salvarIntimacao([
            'id_intimacao' => 2,
            'numero_processo' => '67890',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Seed destinatário para a nova intimação
        $this->db->query("INSERT INTO db_intimacoes_destinatario (nome, comunicacao_id) VALUES ('Dest 2', 2)");

        // Busca novamente - deve refletir a mudança e conter 2 registros
        $result = $repository->buscarTodasIntimacoes();
        $this->assertCount(2, $result);
    }

    public function testTratarIntimacaoClearsCache()
    {
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, '12345')");
        $this->db->query("INSERT INTO db_intimacoes_tipos_tratamento (id_tratamento, tratamento) VALUES (1, 'Lido')");
        
        $repository = new IntimacoesRepository();
        
        // Popula cache de detalhe
        $repository->detalharIntimacao(1);

        // Trata a intimação - deve limpar o cache
        $repository->tratarIntimacao(1, 1);

        // Busca detalhe novamente - deve vir com o tratamento
        $result = $repository->detalharIntimacao(1);
        $this->assertEquals(1, $result['tratamento_id']);
    }

    public function testBuscarIntimacoesNaoTratadasFilter()
    {
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, 'P1'), (2, 'P2')");
        $this->db->query("INSERT INTO db_intimacoes_tipos_tratamento (id_tratamento, tratamento) VALUES (1, 'Lido')");
        $this->db->query("INSERT INTO db_intimacoes_tratamento (intimacao_id, tratamento_id) VALUES (1, 1)");

        $repository = new IntimacoesRepository();
        $naoTratadas = $repository->buscarIntimacoesNaoTratadas();

        $this->assertCount(1, $naoTratadas);
        $this->assertEquals(2, $naoTratadas[0]['id_intimacao']);
    }

    public function testContarNaoTratadas()
    {
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, 'P1'), (2, 'P2'), (3, 'P3')");
        $this->db->query("INSERT INTO db_intimacoes_tipos_tratamento (id_tratamento, tratamento) VALUES (1, 'Lido')");
        $this->db->query("INSERT INTO db_intimacoes_tratamento (intimacao_id, tratamento_id) VALUES (1, 1)");

        $repository = new IntimacoesRepository();

        // Deve contar 2 (P2 e P3)
        $this->assertEquals(2, $repository->contarNaoTratadas());

        // Cache check
        $this->db->query("INSERT INTO db_intimacoes_tratamento (intimacao_id, tratamento_id) VALUES (2, 1)");
        // Ainda deve ser 2 por causa do cache
        $this->assertEquals(2, $repository->contarNaoTratadas());

        // Invalidação do cache via repositório
        $repository->tratarIntimacao(3, 1);
        // Agora deve ser 1 (P2 - embora tenhamos inserido tratamento direto no P2 acima,
        // a chamada tratarIntimacao(3, 1) limpou o cache e agora o banco reflete os tratamentos manuais + o do repo)
        // No banco: P1 (tratado manual), P2 (tratado manual), P3 (tratado repo).
        // Wait, if P1, P2, P3 are all treated, count should be 0.
        $this->assertEquals(0, $repository->contarNaoTratadas());
    }

    public function testRegistrarSincronizacaoClearsCache()
    {
        $this->db->query("INSERT INTO db_intimacoes (id_intimacao, numero_processo) VALUES (1, '12345')");
        
        $repository = new IntimacoesRepository();
        $repository->buscarTodasIntimacoes();

        // Sincroniza - deve limpar o cache
        $repository->registrarSincronizacao(1);

        $result = $repository->buscarTodasIntimacoes();
        $this->assertNotNull($result[0]['synchronize_at']);
    }
}
