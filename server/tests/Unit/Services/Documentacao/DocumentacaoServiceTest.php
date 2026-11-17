<?php

namespace Tests\Unit\Services\Documentacao;

use App\Libraries\Documentacao\RenderizadorDocumentacao;
use App\Services\Documentacao\CatalogoDocumentacao;
use App\Services\Documentacao\DocumentacaoService;
use App\Services\ModulosService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Leitura da documentação com módulos e permissões (issue #703).
 */
class DocumentacaoServiceTest extends CIUnitTestCase
{
    private string $raiz;

    /** @var list<string> */
    private array $ativos = ['processos', 'financeiro'];

    /** @var list<string> */
    private array $permissoes = ['processos.access'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->raiz = sys_get_temp_dir() . '/docs-' . bin2hex(random_bytes(4));
        mkdir($this->raiz);
        CatalogoDocumentacao::limparCache();

        $this->pagina('primeiros-passos/index.md', "titulo: Primeiros passos\nordem: 0\ntelas: [dashboard]", "Bem-vindo ao sistema.\n");
        $this->pagina('primeiros-passos/navegacao.md', "titulo: Navegação\nresumo: Menu lateral e barra superior.", "## Menu\n\nO menu lateral agrupa as telas.\n");
        $this->pagina('processos/index.md', "titulo: Processos\nordem: 10\nmodulo: processos\npermissao: processos.access");
        $this->pagina('processos/cadastro.md', "titulo: Cadastro de processos\ntelas: [list, process-form]\npalavras_chave: [CNJ, número único]", "Informe o número CNJ da ação.\n");
        $this->pagina('financeiro/index.md', "titulo: Financeiro\nordem: 20\nmodulo: financeiro\npermissao: financeiro.access", "Receitas e despesas.\n");
        $this->pagina('objetos/index.md', "titulo: Objetos\nordem: 30\nmodulo: objetos", "Imóveis vinculados.\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->raiz . '/*/img/*') ?: [] as $f) {
            unlink($f);
        }
        foreach (glob($this->raiz . '/*/img') ?: [] as $d) {
            rmdir($d);
        }
        foreach (glob($this->raiz . '/*/*') ?: [] as $f) {
            unlink($f);
        }
        foreach (glob($this->raiz . '/*') ?: [] as $d) {
            rmdir($d);
        }
        rmdir($this->raiz);
        parent::tearDown();
    }

    private function pagina(string $caminho, string $cabecalho, string $corpo = "Texto.\n"): void
    {
        @mkdir(dirname($this->raiz . '/' . $caminho));
        file_put_contents($this->raiz . '/' . $caminho, "---\n{$cabecalho}\n---\n{$corpo}");
    }

    private function service(): DocumentacaoService
    {
        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('isActive')->willReturnCallback(fn (string $s): bool => in_array($s, $this->ativos, true));

        return new DocumentacaoService(
            new CatalogoDocumentacao($this->raiz, new RenderizadorDocumentacao('https://x.test/img/')),
            $modulos,
            fn (string $p): bool => in_array($p, $this->permissoes, true),
        );
    }

    public function testArvoreRespeitaModuloEPermissao(): void
    {
        // financeiro: módulo ativo, sem permissão; objetos: módulo inativo
        $this->assertSame(['primeiros-passos', 'processos'], array_column($this->service()->arvore(), 'slug'));

        $this->permissoes[] = 'financeiro.access';
        $this->ativos[] = 'objetos';
        $this->assertSame(['primeiros-passos', 'processos', 'financeiro', 'objetos'], array_column($this->service()->arvore(), 'slug'));
    }

    public function testPaginaInvisivelEhInexistente(): void
    {
        $service = $this->service();

        $this->assertNull($service->pagina('financeiro'), 'sem permissão');
        $this->assertNull($service->pagina('objetos'), 'módulo inativo');
        $this->assertNull($service->pagina('nao/existe'));
    }

    public function testHerancaDaCapaEsconde(): void
    {
        $this->permissoes = [];
        $this->assertNull($this->service()->pagina('processos/cadastro'), 'herda processos.access da capa');
    }

    public function testPaginaCompletaComVizinhas(): void
    {
        $p = $this->service()->pagina('primeiros-passos/navegacao');

        $this->assertSame('Navegação', $p['titulo']);
        $this->assertSame('primeiros-passos', $p['secao']);
        $this->assertFalse($p['capa']);
        $this->assertSame('Menu lateral e barra superior.', $p['resumo']);
        $this->assertStringContainsString('<h2 id="menu">Menu</h2>', $p['html']);
        $this->assertSame([['nivel' => 2, 'texto' => 'Menu', 'ancora' => 'menu']], $p['sumario']);
        $this->assertSame(['slug' => 'primeiros-passos', 'titulo' => 'Primeiros passos'], $p['anterior']);
        $this->assertSame(['slug' => 'processos', 'titulo' => 'Processos'], $p['proxima']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $p['atualizado_em']);

        $ultima = $this->service()->pagina('processos/cadastro');
        $this->assertNull($ultima['proxima'], 'financeiro e objetos estão invisíveis');
        $this->assertNull($this->service()->pagina('primeiros-passos')['anterior']);
    }

    public function testPorTela(): void
    {
        $service = $this->service();

        $this->assertSame('processos/cadastro', $service->porTela('process-form')['slug']);
        $this->assertSame('primeiros-passos', $service->porTela('dashboard')['slug']);
        $this->assertNull($service->porTela('extrato'));

        $this->ativos = [];
        $this->assertNull($this->service()->porTela('list'), 'tela de módulo inativo não tem ajuda');
    }

    public function testBusca(): void
    {
        $service = $this->service();

        $this->assertSame(['processos/cadastro'], array_column($service->buscar('cnj'), 'slug'));
        $this->assertSame(['processos/cadastro'], array_column($service->buscar('NUMERO unico'), 'slug'), 'sem acento e sem caixa');
        $this->assertSame([], $service->buscar('cnj inexistente'), 'todos os termos precisam aparecer');
        $this->assertSame([], $service->buscar('receitas'), 'página sem permissão não aparece');
        $this->assertSame([], $service->buscar('a'), 'termo curto é ignorado');

        $r = $service->buscar('menu');
        $this->assertSame('primeiros-passos/navegacao', $r[0]['slug']);
        $this->assertSame('Primeiros passos', $r[0]['secao']);
        $this->assertStringContainsString('menu lateral', $r[0]['trecho']);
    }

    public function testBuscaOrdenaPorRelevancia(): void
    {
        $this->pagina('primeiros-passos/atalhos.md', 'titulo: Atalhos', "Use o menu, o menu e o menu.\n");

        $this->assertSame('primeiros-passos/navegacao', $this->service()->buscar('lateral')[0]['slug']);
        $this->assertSame(['primeiros-passos/navegacao', 'primeiros-passos/atalhos'], array_column($this->service()->buscar('menu'), 'slug'), 'resumo vale mais que o corpo');
    }

    public function testTrechoLongoEAcentuado(): void
    {
        $this->pagina('primeiros-passos/longa.md', 'titulo: Longa', str_repeat('Texto de preenchimento. ', 20) . "Ação rescisória aqui.\n" . str_repeat('Mais texto. ', 20));

        $trecho = $this->service()->buscar('rescisoria')[0]['trecho'];

        $this->assertStringStartsWith('…', $trecho);
        $this->assertStringEndsWith('…', $trecho);
        $this->assertStringContainsString('Ação rescisória', $trecho);
    }

    public function testCaminhoDaImagem(): void
    {
        mkdir($this->raiz . '/processos/img');
        file_put_contents($this->raiz . '/processos/img/tela.webp', 'x');
        $service = $this->service();

        $this->assertSame(realpath($this->raiz . '/processos/img/tela.webp'), $service->caminhoDaImagem('processos/img/tela.webp', $this->raiz));
        $this->assertNull($service->caminhoDaImagem('processos/img/nao-existe.webp', $this->raiz));
        $this->assertNull($service->caminhoDaImagem('processos/cadastro.md', $this->raiz));
        $this->assertNull($service->caminhoDaImagem('../processos/img/tela.webp', $this->raiz));
        $this->assertNull($service->caminhoDaImagem('processos/img/../../x.webp', $this->raiz));
        $this->assertNull($service->caminhoDaImagem('processos/img/tela.svg', $this->raiz));
    }
}
