<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Documentacao;
use App\Services\Documentacao\CatalogoDocumentacao;
use App\Services\Documentacao\DocumentacaoService;
use App\Services\ModulosService;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;

/**
 * Respostas da API de leitura da documentação sobre as páginas de app/Docs (issue #703).
 */
class DocumentacaoControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogoDocumentacao::limparCache();

        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('isActive')->willReturn(true);
        Services::injectMock('modulos', $modulos);
        // Sem banco no ambiente de teste: as permissões do usuário são simuladas (nenhuma).
        Services::injectMock('documentacao', new DocumentacaoService(null, $modulos, static fn (string $p): bool => false));
    }

    protected function tearDown(): void
    {
        Services::resetSingle('modulos');
        Services::resetSingle('documentacao');
        parent::tearDown();
    }

    private function json($resultado): array
    {
        return json_decode($resultado->response()->getBody(), true);
    }

    public function testIndice(): void
    {
        $r = $this->controller(Documentacao::class)->execute('index');

        $this->assertSame(200, $r->response()->getStatusCode());
        $corpo = $this->json($r);
        $this->assertTrue($corpo['success']);
        $this->assertSame('primeiros-passos', $corpo['data'][0]['slug']);
    }

    public function testPaginaComSlugEmSegmentos(): void
    {
        $r = $this->controller(Documentacao::class)->execute('pagina', 'primeiros-passos', 'navegacao');

        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame('Navegação pelo sistema', $this->json($r)['data']['titulo']);
    }

    public function testPaginaInexistente404(): void
    {
        $r = $this->controller(Documentacao::class)->execute('pagina', 'nao', 'existe');

        $this->assertSame(404, $r->response()->getStatusCode());
        $this->assertFalse($this->json($r)['success']);
    }

    public function testTela(): void
    {
        $this->assertSame('primeiros-passos/painel', $this->json($this->controller(Documentacao::class)->execute('tela', 'dashboard'))['data']['slug']);
        $this->assertSame(404, $this->controller(Documentacao::class)->execute('tela', 'tela-sem-ajuda')->response()->getStatusCode());
    }

    public function testPaginaComPermissaoQueOUsuarioNaoTemEh404(): void
    {
        $this->assertSame(404, $this->controller(Documentacao::class)->execute('pagina', 'administracao', 'etiquetas')->response()->getStatusCode());
    }

    public function testBusca(): void
    {
        $request = Services::incomingrequest(null, false);
        $request->setGlobal('get', ['q' => 'menu']);
        $r = $this->withRequest($request)->controller(Documentacao::class)->execute('busca');

        $this->assertContains('primeiros-passos/navegacao', array_column($this->json($r)['data'], 'slug'));
    }

    public function testImagemRecusaCaminhoForaDaPasta(): void
    {
        $this->assertSame(404, $this->controller(Documentacao::class)->execute('imagem', '..', '..', 'Config', 'App.php')->response()->getStatusCode());
    }
}
