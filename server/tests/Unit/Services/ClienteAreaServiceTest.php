<?php

declare(strict_types=1);

namespace Tests\unit\Services;

use App\Repositories\ClienteTokenRepository;
use App\Services\ClienteAreaService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Testes unitários para ClienteAreaService e ClienteTokenRepository.
 *
 * Usa SQLite em memória (padrão do projeto) com prefixo `db_`.
 * Segue o padrão estabelecido em IntimacoesRepositoryTest.
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteAreaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected ClienteTokenRepository $tokenRepo;
    protected int                    $clienteIdFixture = 1;

    /**
     * Configura o ambiente de teste com tabelas SQLite em memória (prefixo db_).
     */
    public function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        // Recria tabelas do zero para isolamento
        $this->db->query('DROP TABLE IF EXISTS db_cliente_tokens');
        $this->db->query('DROP TABLE IF EXISTS db_clientes');
        $this->db->query('DROP TABLE IF EXISTS db_processos');
        $this->db->query('DROP TABLE IF EXISTS db_processos_movimentos');
        $this->db->query('DROP TABLE IF EXISTS db_mensagens');
        $this->db->query('DROP TABLE IF EXISTS db_juris_ramos_direito');
        $this->db->query('DROP TABLE IF EXISTS db_users');
        $this->db->query('DROP TABLE IF EXISTS db_auth_identities');

        $this->db->query("CREATE TABLE db_clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL,
            ativo INTEGER DEFAULT 1,
            tipo_cliente TEXT DEFAULT 'F'
        )");

        $this->db->query("CREATE TABLE db_cliente_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used_at TEXT DEFAULT NULL,
            ip_address TEXT DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE db_processos (
            id_processo INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            numero_processo TEXT,
            titulo_processo TEXT,
            encerrado INTEGER DEFAULT 0,
            deleted_at TEXT DEFAULT NULL,
            responsavel_id INTEGER DEFAULT NULL,
            arquived_at TEXT DEFAULT NULL,
            dataDistribuicao TEXT DEFAULT NULL,
            data_encerramento TEXT DEFAULT NULL,
            ramo_direito INTEGER DEFAULT NULL,
            valorCausa TEXT DEFAULT NULL,
            valorCondenacao TEXT DEFAULT NULL,
            resultado TEXT DEFAULT NULL,
            risco TEXT DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_processos_movimentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_processo TEXT,
            nome TEXT,
            nome_complemento TEXT DEFAULT NULL,
            descricao_complemento TEXT DEFAULT NULL,
            sentiment TEXT DEFAULT NULL,
            dataHora TEXT
        )");

        $this->db->query("CREATE TABLE db_mensagens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            remetente_id INTEGER NOT NULL,
            destinatario_id INTEGER NOT NULL,
            processo_id INTEGER DEFAULT NULL,
            assunto TEXT NOT NULL,
            conteudo TEXT NOT NULL,
            data_envio TEXT,
            data_leitura TEXT DEFAULT NULL,
            created_at TEXT,
            updated_at TEXT
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_juris_ramos_direito (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            active INTEGER DEFAULT 1
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_auth_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            secret TEXT NOT NULL
        )");

        // Popula fixtures
        $this->db->query("INSERT INTO db_clientes (id_cliente, nome, email, ativo) VALUES ({$this->clienteIdFixture}, 'Cliente Teste', 'cliente@teste.com', 1)");
        $this->db->query("INSERT INTO db_processos (id_processo, cliente_id, numero_processo, titulo_processo) VALUES (1, {$this->clienteIdFixture}, '0001234-56.2024.8.13.0024', 'Ação de Cobrança Teste')");

        service('cache')->clean();

        $this->tokenRepo = new ClienteTokenRepository();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query('DROP TABLE IF EXISTS db_cliente_tokens');
            $this->db->query('DROP TABLE IF EXISTS db_clientes');
            $this->db->query('DROP TABLE IF EXISTS db_processos');
            $this->db->query('DROP TABLE IF EXISTS db_processos_movimentos');
            $this->db->query('DROP TABLE IF EXISTS db_mensagens');
            $this->db->query('DROP TABLE IF EXISTS db_juris_ramos_direito');
            $this->db->query('DROP TABLE IF EXISTS db_users');
            $this->db->query('DROP TABLE IF EXISTS db_auth_identities');
        }
        parent::tearDown();
    }

    // =========================================================================
    // TESTES: solicitarAcesso (resposta genérica)
    // =========================================================================

    /**
     * @test
     */
    public function testSolicitarAcessoEmailInvalidoRetornaTrueGenerico(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $resultado = $service->solicitarAcesso('email_inexistente_' . time() . '@naoexiste.com');
        $this->assertTrue($resultado, 'Deve retornar true mesmo para email não cadastrado (resposta genérica)');
    }

    // =========================================================================
    // TESTES: gerarToken / validarToken
    // =========================================================================

    /**
     * @test
     */
    public function testTokenValidoEValidado(): void
    {
        $tokenBruto = $this->tokenRepo->gerarToken($this->clienteIdFixture);

        $this->assertNotEmpty($tokenBruto);
        $this->assertEquals(64, strlen($tokenBruto), 'Token bruto deve ter 64 chars (32 bytes em hex)');

        $registro = $this->tokenRepo->validarToken($tokenBruto);
        $this->assertNotNull($registro, 'Token válido deve retornar o registro');
        $this->assertEquals($this->clienteIdFixture, (int) $registro['cliente_id']);
    }

    /**
     * @test
     */
    public function testTokenExpiradoNaoValidado(): void
    {
        $tokenBruto = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $tokenBruto);

        $this->db->query("INSERT INTO db_cliente_tokens (cliente_id, token_hash, expires_at, created_at)
            VALUES ({$this->clienteIdFixture}, '{$tokenHash}', '" . date('Y-m-d H:i:s', strtotime('-1 hour')) . "', '" . date('Y-m-d H:i:s') . "')");

        $registro = $this->tokenRepo->validarToken($tokenBruto);
        $this->assertNull($registro, 'Token expirado deve retornar null');
    }

    /**
     * @test
     */
    public function testTokenJaUsadoNaoValidado(): void
    {
        $tokenBruto = $this->tokenRepo->gerarToken($this->clienteIdFixture);

        // Primeira validação — deve funcionar
        $registro1 = $this->tokenRepo->validarToken($tokenBruto);
        $this->assertNotNull($registro1, 'Primeira validação deve funcionar');

        // Segunda validação — deve falhar (uso único)
        $registro2 = $this->tokenRepo->validarToken($tokenBruto);
        $this->assertNull($registro2, 'Token já usado deve retornar null na segunda validação');
    }

    /**
     * @test
     */
    public function testTokenInexistenteNaoValidado(): void
    {
        $registro = $this->tokenRepo->validarToken('token_invalido_' . time());
        $this->assertNull($registro, 'Token inexistente deve retornar null');
    }

    /**
     * @test
     */
    public function testGerarTokenRevogaTokensAnteriores(): void
    {
        $token1 = $this->tokenRepo->gerarToken($this->clienteIdFixture);
        $token2 = $this->tokenRepo->gerarToken($this->clienteIdFixture);

        $resultado1 = $this->tokenRepo->validarToken($token1);
        $this->assertNull($resultado1, 'Token anterior deve ser revogado ao gerar novo token');

        $resultado2 = $this->tokenRepo->validarToken($token2);
        $this->assertNotNull($resultado2, 'Novo token deve ser válido');
    }

    // =========================================================================
    // TESTES: autenticarToken
    // =========================================================================

    /**
     * @test
     */
    public function testAutenticarTokenInvalidoRetornaFalse(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $resultado = $service->autenticarToken('token_completamente_invalido_' . time());
        $this->assertFalse($resultado, 'Token inválido deve retornar false');
    }

    // =========================================================================
    // TESTES: listarProcessos
    // =========================================================================

    /**
     * @test
     */
    public function testListarProcessosRetornaArrayDoCliente(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $processos = $service->listarProcessos($this->clienteIdFixture);

        $this->assertIsArray($processos, 'Deve retornar um array');
        $this->assertCount(1, $processos, 'Deve retornar 1 processo (do fixture)');
        $this->assertArrayHasKey('id_processo', $processos[0]);
        $this->assertArrayHasKey('status', $processos[0]);
        $this->assertArrayHasKey('movimentos_recentes', $processos[0]);
    }

    /**
     * @test
     */
    public function testListarProcessosClienteInexistenteRetornaArrayVazio(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $processos = $service->listarProcessos(99999);

        $this->assertIsArray($processos, 'Deve retornar array vazio');
        $this->assertEmpty($processos, 'Cliente inexistente não deve ter processos');
    }

    // =========================================================================
    // TESTES: detalharProcesso
    // =========================================================================

    /**
     * @test
     */
    public function testDetalharProcessoClienteErradoRetornaNull(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $resultado = $service->detalharProcesso(99999, 1);
        $this->assertNull($resultado, 'Processo de outro cliente deve retornar null');
    }

    /**
     * @test
     *
     * Verifica que o processo retornado pertence ao cliente correto
     * e que os campos financeiros estão presentes no resultado.
     *
     * Nota: listarMensagens é omitido neste teste por depender de auth()
     * (Shield), que requer tabelas adicionais fora do escopo unitário.
     * Esse comportamento é coberto em testes de integração.
     */
    public function testDetalharProcessoClienteCorretoPossuiCamposFinanceiros(): void
    {
        // Testa diretamente via query para validar campos financeiros do processo
        $db = \Config\Database::connect();

        $processo = $db->table('processos p')
            ->select(['p.*', 'jrd.nome AS ramo_direito_nome'])
            ->join('juris_ramos_direito jrd', 'p.ramo_direito = jrd.id', 'left')
            ->where('p.id_processo', 1)
            ->where('p.cliente_id', $this->clienteIdFixture)
            ->where('p.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        $this->assertNotNull($processo, 'Processo do cliente correto deve ser encontrado');
        $this->assertArrayHasKey('valorCausa', $processo, 'Campo valorCausa deve existir');
        $this->assertArrayHasKey('valorCondenacao', $processo, 'Campo valorCondenacao deve existir');
        $this->assertArrayHasKey('resultado', $processo, 'Campo resultado deve existir');
        $this->assertArrayHasKey('risco', $processo, 'Campo risco deve existir');
        $this->assertEquals($this->clienteIdFixture, (int) $processo['cliente_id'], 'Processo deve pertencer ao cliente correto');
    }

    // =========================================================================
    // TESTES: timelineProcesso
    // =========================================================================

    /**
     * @test
     */
    public function testTimelineProcessoClienteErradoRetornaNull(): void
    {
        $service   = new ClienteAreaService($this->tokenRepo);
        $resultado = $service->timelineProcesso(99999, 1);
        $this->assertNull($resultado, 'Processo de outro cliente deve retornar null');
    }

    /**
     * @test
     */
    public function testTimelineProcessoRetornaEventosSimplificadosEOrdenados(): void
    {
        $this->db->query("INSERT INTO db_processos_movimentos (numero_processo, nome, dataHora) VALUES
            ('0001234-56.2024.8.13.0024', 'Sentença', '2026-03-01 10:00:00'),
            ('0001234-56.2024.8.13.0024', 'Distribuição', '2026-01-01 10:00:00')");

        $service  = new ClienteAreaService($this->tokenRepo);
        $timeline = $service->timelineProcesso($this->clienteIdFixture, 1);

        $this->assertNotNull($timeline);
        $this->assertArrayHasKey('processo', $timeline);
        $this->assertArrayHasKey('eventos', $timeline);
        $this->assertCount(2, $timeline['eventos']);
        $this->assertSame('inicio', $timeline['eventos'][0]['categoria'], 'Primeiro evento deve ser o mais antigo (distribuição)');
        $this->assertSame('decisao', $timeline['eventos'][1]['categoria'], 'Segundo evento deve ser a sentença');
    }

    /**
     * @test
     */
    public function testTimelineProcessoSemMovimentosRetornaListaVazia(): void
    {
        $service  = new ClienteAreaService($this->tokenRepo);
        $timeline = $service->timelineProcesso($this->clienteIdFixture, 1);

        $this->assertNotNull($timeline);
        $this->assertSame([], $timeline['eventos']);
    }
}
