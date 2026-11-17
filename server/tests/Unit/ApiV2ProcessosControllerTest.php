<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Processos;
use App\Services\ProcessosService;
use App\Services\ClienteService;
use App\Services\MovimentosService;
use App\Services\EtiquetasService;
use App\Services\ObjetosService;
use App\Services\PdfService;
use App\Services\ConhecimentoService;
use App\Services\Checklist\ChecklistService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;

class ApiV2ProcessosControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $namespace = 'App';

    protected $processoServiceMock;
    protected $clienteServiceMock;
    protected $movimentosServiceMock;
    protected $etiquetasServiceMock;
    protected $objetosServiceMock;
    protected $pdfServiceMock;
    protected $conhecimentoServiceMock;
    protected $checklistServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        // Cria a tabela de configurações e settings para evitar qualquer bootstrap no setup de banco
        $this->db->query('CREATE TABLE IF NOT EXISTS db_configuracoes (id INTEGER PRIMARY KEY, nome VARCHAR(100), valor TEXT, descricao TEXT, created_at DATETIME, updated_at DATETIME, deleted_at DATETIME)');
        $this->db->query('CREATE TABLE IF NOT EXISTS db_settings (id INTEGER PRIMARY KEY, class VARCHAR(255), "key" VARCHAR(255), value TEXT, type VARCHAR(31) DEFAULT "string", context VARCHAR(255) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        $this->processoServiceMock = $this->getMockBuilder(ProcessosService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->clienteServiceMock = $this->getMockBuilder(ClienteService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->movimentosServiceMock = $this->getMockBuilder(MovimentosService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->etiquetasServiceMock = $this->getMockBuilder(EtiquetasService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objetosServiceMock = $this->getMockBuilder(ObjetosService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->pdfServiceMock = $this->getMockBuilder(PdfService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->conhecimentoServiceMock = $this->getMockBuilder(ConhecimentoService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->checklistServiceMock = $this->getMockBuilder(ChecklistService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockedController(bool $hasPermission = false): Processos
    {
        $controller = $this->getMockBuilder(Processos::class)
            ->setConstructorArgs([
                $this->processoServiceMock,
                $this->clienteServiceMock,
                $this->movimentosServiceMock,
                $this->etiquetasServiceMock,
                $this->objetosServiceMock,
                $this->pdfServiceMock,
                $this->conhecimentoServiceMock,
                $this->checklistServiceMock
            ])
            ->onlyMethods(['verificaPermissao'])
            ->getMock();

        $controller->method('verificaPermissao')->willReturn($hasPermission);

        return $controller;
    }

    public function testControllerInstantiatesCorrectly()
    {
        $controller = $this->getMockedController();

        $this->assertInstanceOf(Processos::class, $controller);
        $this->assertTrue(method_exists($controller, 'listarJson'));
        $this->assertTrue(method_exists($controller, 'detalhesJson'));
        $this->assertTrue(method_exists($controller, 'salvar'));
        $this->assertTrue(method_exists($controller, 'arquivar'));
        $this->assertTrue(method_exists($controller, 'desarquivar'));
        $this->assertTrue(method_exists($controller, 'etiquetaEmLote'));
        $this->assertTrue(method_exists($controller, 'desvincularJurisprudencia'));
        $this->assertTrue(method_exists($controller, 'vincularObjeto'));
        $this->assertTrue(method_exists($controller, 'desvincularObjeto'));
        $this->assertTrue(method_exists($controller, 'salvarObjeto'));
    }

    public function testListarJsonForbiddenWithoutPermissions()
    {
        $this->withURI('http://example.com/api/processos/listarJson')
            ->controller(Processos::class);
            
        // Injeta a instância mockada na propriedade protegida do trait
        $this->controller = $this->getMockedController(false);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $result = $this->execute('listarJson');

        $result->assertStatus(403);
        
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('permissão', $body['message']);
    }

    public function testDetalhesJsonForbiddenWithoutPermissions()
    {
        $this->withURI('http://example.com/api/processos/detalhesJson/1')
            ->controller(Processos::class);

        // Injeta a instância mockada na propriedade protegida do trait
        $this->controller = $this->getMockedController(false);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $result = $this->execute('detalhesJson', 1);

        $result->assertStatus(403);
        
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('permissão', $body['message']);
    }

    public function testRemoverParteHandlesEmptyJsonResiliently()
    {
        $this->withURI('http://example.com/api/processos/removerParte')
            ->controller(Processos::class);
            
        $this->controller = $this->getMockedController(true);
        
        // Mock headers and body
        $this->request->setHeader('Content-Type', 'application/json');
        $this->request->setBody('');
        
        $this->controller->initController($this->request, $this->response, $this->logger);
        
        $result = $this->execute('removerParte');
        
        $result->assertStatus(400);
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
        $this->assertEquals('ID do processo não fornecido.', $body['message']);
    }

    public function testRemoverParteHandlesInvalidJsonResiliently()
    {
        $this->withURI('http://example.com/api/processos/removerParte')
            ->controller(Processos::class);
            
        $this->controller = $this->getMockedController(true);
        
        // Mock headers and malformed body
        $this->request->setHeader('Content-Type', 'application/json');
        $this->request->setBody('{malformed: json"');
        
        $this->controller->initController($this->request, $this->response, $this->logger);
        
        $result = $this->execute('removerParte');
        
        $result->assertStatus(400);
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
        $this->assertEquals('ID do processo não fornecido.', $body['message']);
    }

    public function testAnalisarSentimentoMovimentoReturnsNotFoundWhenMovimentoInexistente()
    {
        $this->movimentosServiceMock->method('buscarMovimento')->willReturn(null);

        $this->withURI('http://example.com/api/processos/analisarSentimentoMovimento/99')
            ->controller(Processos::class);

        $this->controller = $this->getMockedController(true);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $result = $this->execute('analisarSentimentoMovimento', 99);

        $result->assertStatus(404);
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertEquals('Movimento não encontrado.', $body['messages']['error'] ?? null);
    }

    public function testAnalisarSentimentoMovimentoForbiddenWithoutPermissions()
    {
        $this->movimentosServiceMock->method('buscarMovimento')
            ->willReturn(['id_movimento' => 1, 'numero_processo' => '12345', 'nome' => 'Despacho']);
        $this->processoServiceMock->method('getIdporNumeroProcesso')->willReturn(10);

        $this->withURI('http://example.com/api/processos/analisarSentimentoMovimento/1')
            ->controller(Processos::class);

        $this->controller = $this->getMockedController(false);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $result = $this->execute('analisarSentimentoMovimento', 1);

        $result->assertStatus(403);
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertFalse($body['success']);
    }

    public function testAnalisarSentimentoMovimentoReturnsAnalysisWhenAllowed()
    {
        $this->movimentosServiceMock->method('buscarMovimento')
            ->willReturn(['id_movimento' => 1, 'numero_processo' => '12345', 'nome' => 'Liminar deferida']);
        $this->processoServiceMock->method('getIdporNumeroProcesso')->willReturn(10);
        $this->movimentosServiceMock->method('analisarSentimento')
            ->willReturn(['sentimento' => 'Positivo', 'justificativa' => 'Liminar favorável.', 'score_confianca' => 0.9]);

        $this->withURI('http://example.com/api/processos/analisarSentimentoMovimento/1')
            ->controller(Processos::class);

        $this->controller = $this->getMockedController(true);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $result = $this->execute('analisarSentimentoMovimento', 1);

        $result->assertStatus(200);
        $body = json_decode(html_entity_decode(strip_tags($result->getBody())), true);
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);
        $this->assertEquals('Positivo', $body['data']['sentimento']);
    }
}
