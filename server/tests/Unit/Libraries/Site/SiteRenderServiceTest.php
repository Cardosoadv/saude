<?php

namespace Tests\Unit\Libraries\Site;

use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\DadosDinamicos;
use App\Libraries\Site\PreviewToken;
use App\Libraries\Site\SecaoPreparador;
use App\Libraries\Site\SiteContexto;
use App\Libraries\Site\SiteRenderService;
use App\Models\Site\SiteConfiguracaoModel;
use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SiteMidiaModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use App\Models\Site\SiteTemplateModel;
use App\Repositories\Site\SiteConfiguracaoRepository;
use App\Repositories\Site\SiteMenuRepository;
use App\Repositories\Site\SiteMidiaRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Repositories\Site\SiteTemplateRepository;
use App\Services\Site\SiteConfiguracaoService;
use App\Services\Site\SiteTemplateService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\SiteTemplate;

/**
 * Renderização de páginas a partir do banco com templates PHP (core) e Twig (upload).
 */
class SiteRenderServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $raiz;
    private SiteTemplate $config;
    private PreviewToken $preview;
    private SitePaginaRepository $paginasRepo;

    protected function setUp(): void
    {
        parent::setUp();
        // Os repositories usam cache versionado persistente; sem limpar, um teste lê o estado do anterior.
        $this->limpaCache();
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $migration = new CreateSiteConteudoTables(Database::forge($this->db));
        $migration->down();
        $migration->up();

        $this->raiz = WRITEPATH . 'tpl_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->raiz . 'core', 0777, true);
        mkdir($this->raiz . 'upload', 0777, true);

        $this->config = new SiteTemplate();
        $this->config->diretorios = ['core' => $this->raiz . 'core', 'cli' => $this->raiz . 'core', 'upload' => $this->raiz . 'upload'];
        $this->config->diretorioAssets = $this->raiz . 'assets';
        $this->preview = new PreviewToken('segredo-de-teste');
    }

    protected function tearDown(): void
    {
        (new CreateSiteConteudoTables(Database::forge($this->db)))->down();
        $this->apaga($this->raiz);
        SiteContexto::definir(null);
        parent::tearDown();
    }

    /** Zera o cache persistente e a memória de versões do BaseRepository. */
    private function limpaCache(): void
    {
        service('cache')->clean();
        $versoes = new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions');
        $versoes->setValue(null, []);
    }

    private function apaga(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/{,.}*', GLOB_BRACE) ?: [] as $item) {
            if (in_array(basename($item), ['.', '..'], true)) {
                continue;
            }
            is_dir($item) ? $this->apaga($item) : @unlink($item);
        }
        @rmdir($dir);
    }

    private function model(string $classe)
    {
        $m = new $classe($this->db);
        foreach (['afterInsert', 'beforeUpdate', 'beforeDelete', 'afterDelete'] as $evento) {
            $this->setPrivateProperty($m, $evento, []);
        }
        return $m;
    }

    private function copia(string $origem, string $destino): void
    {
        @mkdir($destino, 0777, true);
        foreach (new \DirectoryIterator($origem) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $item->isDir() ? $this->copia($item->getPathname(), $destino . '/' . $item->getFilename()) : copy($item->getPathname(), $destino . '/' . $item->getFilename());
        }
    }

    private function arquivo(string $caminho, string $conteudo): void
    {
        @mkdir(dirname($this->raiz . $caminho), 0777, true);
        file_put_contents($this->raiz . $caminho, $conteudo);
    }

    private const MANIFESTO = [
        'slug' => 'teste', 'nome' => 'Teste', 'versao' => '1.0.0', 'motor' => 'php',
        'textos' => ['rodape.copy' => 'Direitos (padrão do template)'],
        'secoes' => [
            'hero' => ['rotulo' => 'Hero', 'campos' => [
                'titulo' => ['tipo' => 'texto_inline'],
                'sub'    => ['tipo' => 'texto'],
                'botao'  => ['tipo' => 'link'],
                'foto'   => ['tipo' => 'imagem'],
            ]],
            'corpo' => ['rotulo' => 'Corpo', 'campos' => ['html' => ['tipo' => 'rich_text']]],
            'areas' => ['rotulo' => 'Áreas', 'campos' => ['itens' => ['tipo' => 'dinamico', 'fonte' => 'areas']]],
            'sem_partial' => ['rotulo' => 'Sem arquivo', 'campos' => []],
        ],
    ];

    /** Instala um template e devolve o serviço de render ligado a ele. */
    private function ambiente(string $origem = 'core', array $manifestoExtra = [], bool $ativo = true): SiteRenderService
    {
        $manifesto = array_replace(self::MANIFESTO, $manifestoExtra);
        $this->model(SiteTemplateModel::class)->insert([
            'slug' => 'teste', 'nome' => 'Teste', 'versao' => '1.0.0', 'origem' => $origem,
            'ativo' => $ativo ? 1 : 0, 'manifest' => json_encode($manifesto),
        ]);

        $tpl = new SiteTemplateService(new SiteTemplateRepository($this->model(SiteTemplateModel::class)), $this->config);
        $paginas = $this->paginasRepo = new SitePaginaRepository($this->model(SitePaginaModel::class));
        $midias  = new SiteMidiaRepository($this->model(SiteMidiaModel::class));
        $menus   = new SiteMenuRepository($this->model(SiteMenuModel::class));
        $cfg     = new SiteConfiguracaoService(new SiteConfiguracaoRepository($this->model(SiteConfiguracaoModel::class)));

        $contexto = new SiteContexto($cfg, $menus, $paginas, $midias);
        SiteContexto::definir($contexto);

        $dinamicos = new DadosDinamicos();
        $dinamicos->registrar('areas', fn () => [['id' => 1, 'nome' => 'Civil & Família']]);

        return new SiteRenderService(
            $tpl, $paginas, new SiteSecaoRepository($this->model(SiteSecaoModel::class)), $contexto,
            new SecaoPreparador($contexto, $dinamicos, $paginas), $this->preview, $this->config
        );
    }

    private function pagina(string $slug, string $status = 'publicado', string $tipo = 'pagina'): int
    {
        return (int) $this->model(SitePaginaModel::class)->insert([
            'slug' => $slug, 'titulo' => ucfirst($slug), 'tipo' => $tipo, 'status' => $status,
            'meta_description' => 'Descrição da página',
        ]);
    }

    private function secao(int $paginaId, string $tipo, array $dados, int $ordem, bool $visivel = true): int
    {
        return (int) $this->model(SiteSecaoModel::class)->insert([
            'pagina_id' => $paginaId, 'tipo' => $tipo, 'ordem' => $ordem, 'visivel' => $visivel ? 1 : 0,
            'ancora' => $tipo === 'hero' ? 'topo' : null, 'dados' => json_encode($dados),
        ]);
    }

    private function templatesPhp(): void
    {
        $this->arquivo('core/teste/layouts/main.php', '<html><title><?= esc($seo["titulo"]) ?></title><meta d="<?= esc($seo["descricao"]) ?>"><body><?= $conteudo ?><footer><?= esc(site_texto("rodape.copy")) ?> | <?= esc(site_config("identidade.nome", "sem nome")) ?></footer></body></html>');
        $this->arquivo('core/teste/sections/hero.php', '<section id="<?= esc($secao["ancora"]) ?>"><h1><?= $dados["titulo"] ?></h1><p><?= esc($dados["sub"] ?? "") ?></p><a href="<?= esc($dados["botao"]["href"] ?? "") ?>">go</a></section>');
        $this->arquivo('core/teste/sections/corpo.php', '<div class="corpo"><?= $dados["html"] ?></div>');
        $this->arquivo('core/teste/sections/areas.php', '<ul><?php foreach ($dados["itens"] as $i): ?><li><?= esc($i["nome"]) ?></li><?php endforeach ?></ul>');
    }

    public function testRenderizaSecoesVisiveisEmOrdemDentroDoLayout(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $this->model(SiteConfiguracaoModel::class)->insert(['chave' => 'identidade.nome', 'valor' => 'Escritório X', 'tipo' => 'string', 'grupo' => 'identidade']);
        $this->model(SiteConfiguracaoModel::class)->insert(['chave' => 'seo.sufixo_titulo', 'valor' => ' | X', 'tipo' => 'string', 'grupo' => 'seo']);

        $p = $this->pagina('inicio', tipo: 'home');
        $this->secao($p, 'areas', [], 3);
        $this->secao($p, 'hero', ['titulo' => 'Defesa <em>Forte</em>', 'sub' => '<b>não é html</b>'], 1);
        $this->secao($p, 'corpo', ['html' => '<p>oculta</p>'], 2, visivel: false);

        $html = $render->renderHome();

        $this->assertStringContainsString('<title>Inicio | X</title>', $html);
        $this->assertStringContainsString('<h1>Defesa <em>Forte</em></h1>', $html);
        $this->assertStringContainsString('<p>&lt;b&gt;não é html&lt;/b&gt;</p>', $html, 'texto simples é escapado pelo template');
        $this->assertStringContainsString('id="topo"', $html);
        $this->assertStringNotContainsString('oculta', $html);
        $this->assertStringContainsString('<li>Civil &amp; Família</li>', $html, 'campo dinâmico vem do provedor');
        $this->assertLessThan(strpos($html, '<ul>'), strpos($html, '<h1>'), 'ordem das seções');
        $this->assertStringContainsString('Direitos (padrão do template) | Escritório X', $html);
    }

    public function testTextoEditadoSobrepoeOPadraoDoTemplate(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $this->model(SiteConfiguracaoModel::class)->insert(['chave' => 'textos.rodape.copy', 'valor' => 'Meu texto', 'tipo' => 'string', 'grupo' => 'textos']);
        $this->pagina('inicio', tipo: 'home');

        $this->assertStringContainsString('Meu texto', $render->renderHome());
    }

    public function testRichTextETextoInlineChegamSanitizados(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $p = $this->pagina('inicio', tipo: 'home');
        $this->secao($p, 'hero', ['titulo' => '<script>x()</script>Oi <em onclick="y()">você</em><a href="/x">l</a>'], 1);
        $this->secao($p, 'corpo', ['html' => '<p onclick="x()">ok</p><script>alert(1)</script><a href="javascript:alert(1)">l</a>'], 2);

        $html = $render->renderHome();

        $this->assertStringContainsString('<h1>Oi <em>você</em>l</h1>', $html);
        $this->assertStringContainsString('<div class="corpo"><p>ok</p><a>l</a></div>', $html);
        $this->assertStringNotContainsString('script', $html);
    }

    public function testLinksEImagensSaoResolvidos(): void
    {
        $this->templatesPhp();
        $this->arquivo('core/teste/sections/hero.php', '<?= esc($dados["botao"]["href"] ?? "NULO") ?>|<?= esc($dados["foto"]["url"] ?? "SEMFOTO") ?>|<?= esc($dados["foto"]["alt"] ?? "") ?>');
        $render = $this->ambiente();
        $civil = $this->pagina('direito-civil');
        $rascunho = $this->pagina('rascunho', 'rascunho');
        $foto = (int) $this->model(SiteMidiaModel::class)->insert(['arquivo' => '2026/10/a.png', 'nome_original' => 'a.png', 'mime' => 'image/png', 'alt' => 'Fachada']);
        $home = $this->pagina('inicio', tipo: 'home');

        $this->secao($home, 'hero', ['botao' => ['tipo' => 'pagina', 'pagina_id' => $civil], 'foto' => $foto], 1);
        $this->assertSame(base_url('pagina/direito-civil') . '|' . base_url('public/uploads/site/2026/10/a.png') . '|Fachada', $this->corpo($render->renderHome()));

        foreach ([['tipo' => 'pagina', 'pagina_id' => $rascunho], ['tipo' => 'url', 'url' => 'javascript:alert(1)'], ['tipo' => 'pagina', 'pagina_id' => 9999]] as $link) {
            $this->model(SiteSecaoModel::class)->where('pagina_id', $home)->set('dados', json_encode(['botao' => $link]))->update();
            $this->limpaCache(); // edição direta no banco, fora do repository
            $this->assertStringStartsWith('NULO|SEMFOTO|', $this->corpo($render->renderHome()), json_encode($link));
        }
    }

    /** Conteúdo entre <body> e <footer> do layout de teste. */
    private function corpo(?string $html): string
    {
        preg_match('#<body>(.*?)<footer>#s', (string) $html, $m);

        return trim($m[1] ?? '');
    }

    public function testPaginaEmRascunhoSoApareceComTokenValidoDaPropriaPagina(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $this->pagina('inicio', tipo: 'home');
        $rascunho = $this->pagina('novidade', 'rascunho');
        $outra = $this->pagina('outra', 'rascunho');
        $this->secao($rascunho, 'corpo', ['html' => '<p>segredo</p>'], 1);

        $this->assertNull($render->renderPagina('novidade'));
        $this->assertNull($render->renderPagina('novidade', 'lixo'));
        $this->assertNull($render->renderPagina('novidade', $this->preview->gerar($outra)), 'token de outra página');
        $this->assertNull($render->renderPagina('novidade', $this->preview->gerar($rascunho, -1)), 'expirado');
        $this->assertStringContainsString('segredo', $render->renderPagina('novidade', $this->preview->gerar($rascunho)));
        $this->assertNull($render->renderPagina('inexistente'));
    }

    public function testSecaoSemDefinicaoOuSemPartialEIgnoradaSemQuebrarAPagina(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $p = $this->pagina('inicio', tipo: 'home');
        $this->secao($p, 'tipo_removido', ['x' => 1], 1);
        $this->secao($p, 'sem_partial', [], 2);
        $this->secao($p, 'corpo', ['html' => '<p>fica</p>'], 3);

        $html = $render->renderHome();

        $this->assertStringContainsString('<p>fica</p>', $html);
        $this->assertStringNotContainsString('tipo_removido', $html);
    }

    public function testSemTemplateAtivoNaoRenderizaEDisponivelRefleteIsso(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente(ativo: false);
        $this->pagina('inicio', tipo: 'home');

        $this->assertFalse($render->disponivel());
        $this->assertNull($render->renderHome());
    }

    public function testDisponivelExigeHomePublicada(): void
    {
        $this->templatesPhp();
        $render = $this->ambiente();
        $this->assertFalse($render->disponivel(), 'sem home');

        $home = $this->pagina('inicio', 'rascunho', 'home');
        $this->assertFalse($render->disponivel());

        $this->paginasRepo->update($home, ['status' => 'publicado']);
        $this->assertTrue($render->disponivel());
    }

    public function testLayoutAusenteDevolveSoOConteudo(): void
    {
        $this->arquivo('core/teste/sections/corpo.php', '<p><?= $dados["html"] ?></p>');
        $render = $this->ambiente();
        $p = $this->pagina('inicio', tipo: 'home');
        $this->secao($p, 'corpo', ['html' => 'x'], 1);

        $this->assertSame('<p>x</p>', trim($render->renderHome()));
    }

    public function testNomeDeSecaoOuLayoutNaoVirauCaminho(): void
    {
        $this->templatesPhp();
        $this->arquivo('core/segredo.php', 'VAZOU');
        $render = $this->ambiente(manifestoExtra: ['secoes' => ['../../segredo' => ['rotulo' => 'x', 'campos' => []]]]);
        $p = $this->pagina('inicio', tipo: 'home');
        $this->model(SiteSecaoModel::class)->insert(['pagina_id' => $p, 'tipo' => '../../segredo', 'ordem' => 1]);

        $this->assertStringNotContainsString('VAZOU', (string) $render->renderHome());
    }

    public function testTemplateUploadSempreUsaTwigEmSandbox(): void
    {
        // Mesmo declarando "php" no manifesto e trazendo um .php, o upload só roda Twig.
        $this->arquivo('upload/teste/layouts/main.php', 'EXECUTOU PHP');
        $this->arquivo('upload/teste/layouts/main.twig', '<title>{{ seo.titulo }}</title>{{ conteudo|raw }}<i>{{ site_config("identidade.nome", "anônimo") }}|{{ site_texto("rodape.copy") }}</i>');
        $this->arquivo('upload/teste/sections/hero.twig', '<h1>{{ dados.titulo|raw }}</h1><p>{{ dados.sub }}</p>');
        $render = $this->ambiente('upload');
        $p = $this->pagina('inicio', tipo: 'home');
        $this->secao($p, 'hero', ['titulo' => 'Oi <script>x</script><em>você</em>', 'sub' => '<b>x</b>'], 1);

        $html = $render->renderHome();

        $this->assertStringNotContainsString('EXECUTOU', $html);
        $this->assertStringContainsString('<h1>Oi <em>você</em></h1>', $html);
        $this->assertStringContainsString('<p>&lt;b&gt;x&lt;/b&gt;</p>', $html, 'autoescape do Twig');
        $this->assertStringContainsString('<i>anônimo|Direitos (padrão do template)</i>', $html);
    }

    public function testTemplateUploadMaliciosoNaoConsegueChamarFuncaoNaoPermitida(): void
    {
        $this->arquivo('upload/teste/layouts/main.twig', '{{ constant("PHP_VERSION") }}');
        $render = $this->ambiente('upload');
        $this->pagina('inicio', tipo: 'home');

        $this->expectException(\Twig\Sandbox\SecurityError::class);
        $render->renderHome();
    }

    public function testMenuResolveHrefsEOcultaItensDePaginaNaoPublicada(): void
    {
        $this->arquivo('core/teste/layouts/main.php', '<?php foreach (site_menu("cabecalho") as $i): ?>[<?= esc($i["rotulo"]) ?>=<?= esc($i["href"]) ?><?= $i["destaque"] ? "*" : "" ?>]<?php endforeach ?>');
        $render = $this->ambiente();
        $home = $this->pagina('inicio', tipo: 'home');
        $civil = $this->pagina('direito-civil');
        $rascunho = $this->pagina('rascunho', 'rascunho');
        $menu = $this->model(SiteMenuModel::class)->insert(['local' => 'cabecalho']);
        $itens = $this->model(SiteMenuItemModel::class);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Início', 'tipo' => 'pagina', 'pagina_id' => $home, 'ordem' => 1]);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Civil', 'tipo' => 'pagina', 'pagina_id' => $civil, 'ordem' => 2, 'destaque' => 1]);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Rascunho', 'tipo' => 'pagina', 'pagina_id' => $rascunho, 'ordem' => 3]);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Contato', 'tipo' => 'ancora', 'ancora' => 'contato', 'ordem' => 4]);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Ruim', 'tipo' => 'url', 'url' => 'javascript:alert(1)', 'ordem' => 5]);

        $html = $render->renderHome();

        $this->assertSame(
            '[Início=' . base_url() . '][Civil=' . base_url('pagina/direito-civil') . '*][Contato=' . base_url('#contato') . ']',
            trim($html)
        );
    }

    public function testTelaEspecialUsaOTemplateAtivoEPaginaDeSistemaNaoAbreEmPaginaSlug(): void
    {
        $this->arquivo('core/teste/pages/area_cliente.php', '<?= esc($pagina["titulo"]) ?>|<?= esc($seo["titulo"]) ?>|<?= $noindex ? "noindex" : "indexa" ?>|<?= esc($extra) ?>');
        $render = $this->ambiente();
        $this->model(SitePaginaModel::class)->insert(['slug' => 'area-cliente', 'titulo' => 'Entrar', 'tipo' => 'sistema', 'status' => 'publicado', 'exibir_sitemap' => 0, 'meta_title' => 'Acesso do cliente']);

        $html = $render->renderTela('area_cliente', 'area-cliente', ['extra' => 'dado da rota'], 'Área do Cliente');

        $this->assertSame('Entrar|Acesso do cliente|noindex|dado da rota', trim($html));
        $this->assertNull($render->renderPagina('area-cliente'));
    }

    public function testTelaSemPaginaCadastradaUsaOTituloPadraoEOSufixo(): void
    {
        $this->arquivo('core/teste/pages/area_cliente.php', '<?= esc($seo["titulo"]) ?>');
        $render = $this->ambiente();
        $this->model(SiteConfiguracaoModel::class)->insert(['chave' => 'seo.sufixo_titulo', 'valor' => ' | X', 'tipo' => 'string', 'grupo' => 'seo']);

        $this->assertSame('Área do Cliente | X', trim($render->renderTela('area_cliente', 'area-cliente', [], 'Área do Cliente')));
    }

    public function testTelaAusenteNoTemplateCaiNaDoClassicoESemTemplateAtivoDevolveNulo(): void
    {
        $render = $this->ambiente();
        $this->copia(APPPATH . 'Templates/classico', $this->raiz . 'core/classico'); // o Clássico acompanha a aplicação

        $html = $render->renderTela('area_cliente', 'area-cliente', [], 'Área do Cliente');

        $this->assertStringContainsString('id="magic-link-form"', $html);
        $this->assertStringContainsString('area-cliente.css', $html);

        $this->model(SiteTemplateModel::class)->where('slug', 'teste')->set('ativo', 0)->update();
        $this->limpaCache();
        $this->assertNull($render->renderTela('area_cliente', 'area-cliente', [], 'x'));
    }

    public function testTelaDeTemplateEnviadoPeloPainelRodaEmTwigSandbox(): void
    {
        $this->arquivo('upload/teste/pages/noticias_lista.twig', '<h1>{{ pagina.titulo }}</h1>{% for a in articles %}<p>{{ a.title }}</p>{% endfor %}<i>{{ site_texto("noticias.ler_artigo") }}</i>');
        $this->arquivo('upload/teste/pages/noticias_lista.php', 'EXECUTOU PHP');
        $render = $this->ambiente('upload', ['textos' => ['noticias.ler_artigo' => 'Ler Artigo']]);

        $html = $render->renderTela('noticias_lista', 'noticias', ['articles' => [['title' => 'A & B']]], 'Notícias');

        $this->assertSame('<h1>Notícias</h1><p>A &amp; B</p><i>Ler Artigo</i>', trim($html));
    }
}
