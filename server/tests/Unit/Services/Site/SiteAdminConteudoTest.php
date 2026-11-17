<?php

namespace Tests\Unit\Services\Site;

use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\PreviewToken;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Repositories\Site\SitePaginaRepository;
use App\Services\Site\SiteConfiguracaoService;
use App\Services\Site\SiteMenuService;
use App\Services\Site\SitePaginaService;
use App\Services\Site\SiteSecaoService;
use App\Services\Site\SiteTemaService;
use App\Services\Site\SiteTemplateService;
use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\ApiV2\Site\admin\Configuracoes;
use App\Controllers\ApiV2\Site\admin\Menus;
use App\Controllers\ApiV2\Site\admin\Paginas;
use App\Controllers\ApiV2\Site\admin\Secoes;
use App\Controllers\ApiV2\Site\admin\TemplateAtivo;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\SiteTemplate;

/**
 * Serviços da API administrativa do site (issue #648), sobre o template Clássico instalado.
 */
class SiteAdminConteudoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $tmp;
    private PreviewToken $preview;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limpaCache();
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        (new CreateSiteConteudoTables($forge))->up();

        $forge->dropTable('auditoria', true);
        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'       => ['type' => 'INTEGER', 'null' => true],
            'table_name'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'action_type'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'dados_antigos' => ['type' => 'TEXT', 'null' => true],
            'dados_novos'   => ['type' => 'TEXT', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('auditoria');

        // `user_id()` do Shield consulta a tabela de configurações do CI4 (settings).
        $forge->dropTable('settings', true);
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'],
            'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('settings');

        $this->tmp = WRITEPATH . 'admin_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->tmp, 0777, true);
        $config = new SiteTemplate();
        $config->diretorioAssets = $this->tmp . 'assets';
        (new TemplateInstaller(assets: new TemplateAssets($config)))->instalarCore('classico');

        $this->preview = new PreviewToken('segredo-de-teste');
        $this->limpaCache();
    }

    protected function tearDown(): void
    {
        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        $forge->dropTable('auditoria', true);
        $forge->dropTable('settings', true);
        $this->apaga($this->tmp);
        parent::tearDown();
    }

    private function limpaCache(): void
    {
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
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

    private function paginas(): SitePaginaService
    {
        return new SitePaginaService(preview: $this->preview);
    }

    private function secoes(): SiteSecaoService
    {
        return new SiteSecaoService();
    }

    /** @return array<string,string> */
    private function erros(callable $acao): array
    {
        try {
            $acao();
        } catch (ErroDeValidacao $e) {
            return $e->erros();
        }
        $this->fail('Esperava ErroDeValidacao.');
    }

    private function idDe(string $slug): int
    {
        return (int) $this->db->table('site_paginas')->where('slug', $slug)->get()->getRow()->id;
    }

    // ============================================================ páginas

    public function testCriarPaginaNasceEmRascunhoComLayoutDoTipo(): void
    {
        $area = $this->paginas()->criar(['slug' => 'previdenciario', 'titulo' => 'Previdenciário', 'tipo' => 'area']);
        $comum = $this->paginas()->criar(['slug' => 'sobre', 'titulo' => 'Sobre', 'status' => 'publicado', 'tipo' => 'pagina']);

        $this->assertSame('rascunho', $area['status']);
        $this->assertSame('area', $area['layout']);
        $this->assertSame('rascunho', $comum['status'], 'status não é aceito na criação');
        $this->assertSame('main', $comum['layout']);
        $this->assertSame(base_url('pagina/sobre'), $comum['url']);
        $this->assertSame([], $comum['secoes']);
    }

    public function testCriarRecusaDadosInvalidos(): void
    {
        $this->paginas()->criar(['slug' => 'sobre', 'titulo' => 'Sobre']);

        $this->assertArrayHasKey('slug', $this->erros(fn () => $this->paginas()->criar(['slug' => 'Slug Ruim', 'titulo' => 'X'])));
        $this->assertArrayHasKey('slug', $this->erros(fn () => $this->paginas()->criar(['slug' => 'sobre', 'titulo' => 'Outra'])), 'slug duplicado');
        $this->assertArrayHasKey('titulo', $this->erros(fn () => $this->paginas()->criar(['slug' => 'x'])));
        $this->assertArrayHasKey('tipo', $this->erros(fn () => $this->paginas()->criar(['slug' => 'h', 'titulo' => 'H', 'tipo' => 'home'])));
        $this->assertArrayHasKey('layout', $this->erros(fn () => $this->paginas()->criar(['slug' => 'l', 'titulo' => 'L', 'layout' => 'inexistente'])));
        $this->assertArrayHasKey('og_image_id', $this->erros(fn () => $this->paginas()->criar(['slug' => 'o', 'titulo' => 'O', 'og_image_id' => 999])));
        $this->assertArrayHasKey('meta_description', $this->erros(fn () => $this->paginas()->criar(['slug' => 'm', 'titulo' => 'M', 'meta_description' => str_repeat('a', 161)])));
        $this->assertArrayHasKey('prioridade_sitemap', $this->erros(fn () => $this->paginas()->criar(['slug' => 'p', 'titulo' => 'P', 'prioridade_sitemap' => 2])));
    }

    public function testAtualizarPermiteOMesmoSlugELimpaMetaVazia(): void
    {
        $p = $this->paginas()->criar(['slug' => 'sobre', 'titulo' => 'Sobre', 'meta_title' => 'Meta']);

        $r = $this->paginas()->atualizar($p['id'], ['slug' => 'sobre', 'titulo' => 'Sobre nós', 'meta_title' => '  ']);

        $this->assertSame('Sobre nós', $r['pagina']['titulo']);
        $this->assertNull($r['pagina']['meta_title']);
        $this->assertSame([], $r['avisos']);
    }

    public function testTrocarOSlugDePaginaPublicadaAvisaEDeSistemaNaoPode(): void
    {
        $civil = $this->idDe('consultas');

        $r = $this->paginas()->atualizar($civil, ['slug' => 'civil']);
        $this->assertSame('civil', $r['pagina']['slug']);
        $this->assertStringContainsString('/pagina/consultas', $r['avisos'][0]);

        $rascunho = $this->paginas()->criar(['slug' => 'rasc', 'titulo' => 'R']);
        $this->assertSame([], $this->paginas()->atualizar($rascunho['id'], ['slug' => 'rasc-2'])['avisos'], 'rascunho não avisa');

        $this->assertArrayHasKey('slug', $this->erros(fn () => $this->paginas()->atualizar($this->idDe('noticias'), ['slug' => 'blog'])));
    }

    public function testHomeEPaginasDeSistemaNaoSaemNemSaoDespublicadas(): void
    {
        foreach (['inicio', 'noticias', 'area-cliente'] as $slug) {
            $id = $this->idDe($slug);
            $this->assertArrayHasKey('pagina', $this->erros(fn () => $this->paginas()->excluir($id)), "excluir {$slug}");
            $this->assertArrayHasKey('pagina', $this->erros(fn () => $this->paginas()->despublicar($id)), "despublicar {$slug}");
        }
        $this->assertArrayHasKey('pagina', $this->erros(fn () => $this->paginas()->publicar($this->idDe('noticias'))));
    }

    public function testPublicarExigeSecoesValidasEAvisaDeTipoAusente(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $this->db->table('site_secoes')->insert(['pagina_id' => $p['id'], 'tipo' => 'hero', 'ordem' => 1, 'dados' => json_encode(['subtitulo' => 'sem título'])]);

        $erros = $this->erros(fn () => $this->paginas()->publicar($p['id']));
        $this->assertCount(1, $erros);
        $this->assertStringEndsWith('.titulo', array_key_first($erros));
        $this->assertSame('rascunho', $this->paginas()->obter($p['id'])['status']);

        $this->db->table('site_secoes')->update(['dados' => json_encode(['titulo' => 'Ok'])]);
        $this->db->table('site_secoes')->insert(['pagina_id' => $p['id'], 'tipo' => 'tipo_antigo', 'ordem' => 2, 'dados' => '{}']);

        $r = $this->paginas()->publicar($p['id']);
        $this->assertSame('publicado', $r['pagina']['status']);
        $this->assertNotNull($r['pagina']['publicado_em']);
        $this->assertStringContainsString('tipo_antigo', $r['avisos'][0]);

        $this->assertSame('rascunho', $this->paginas()->despublicar($p['id'])['status']);
    }

    public function testPublicarApareceNoSiteEInvalidaOCacheDePublicadas(): void
    {
        $repo = new SitePaginaRepository();
        $antes = count($repo->publicadas());
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $this->assertCount($antes, $repo->publicadas());

        $this->paginas()->publicar($p['id']);
        $this->assertCount($antes + 1, (new SitePaginaRepository())->publicadas());
    }

    public function testExcluirApagaDeVezELiberaOSlug(): void
    {
        $p = $this->paginas()->criar(['slug' => 'temporaria', 'titulo' => 'T']);
        $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'dados' => ['corpo' => '<p>x</p>']]);

        $this->paginas()->excluir($p['id']);

        $this->assertSame(0, $this->db->table('site_paginas')->where('slug', 'temporaria')->countAllResults());
        $this->assertSame(0, $this->db->table('site_secoes')->where('pagina_id', $p['id'])->countAllResults(), 'seções saem por cascata');
        $this->paginas()->criar(['slug' => 'temporaria', 'titulo' => 'De novo']);
        $this->expectException(NaoEncontrado::class);
        $this->paginas()->obter($p['id']);
    }

    public function testDuplicarCopiaSecoesEGeraSlugsLivres(): void
    {
        $civil = $this->idDe('consultas');
        $original = $this->paginas()->obter($civil);

        $c1 = $this->paginas()->duplicar($civil);
        $c2 = $this->paginas()->duplicar($civil);
        $deHome = $this->paginas()->duplicar($this->idDe('inicio'));

        $this->assertSame('consultas-copia', $c1['slug']);
        $this->assertSame('consultas-copia-2', $c2['slug']);
        $this->assertSame('rascunho', $c1['status']);
        $this->assertSame('Consultas (cópia)', $c1['titulo']);
        $this->assertCount(count($original['secoes']), $c1['secoes']);
        $this->assertSame($original['secoes'][2]['dados'], $c1['secoes'][2]['dados']);
        $this->assertSame('pagina', $deHome['tipo'], 'cópia da home é página comum');
        $this->assertArrayHasKey('pagina', $this->erros(fn () => $this->paginas()->duplicar($this->idDe('noticias'))));
    }

    public function testPreviewTokenValeSoParaAQuelaPagina(): void
    {
        $p = $this->paginas()->criar(['slug' => 'rasc', 'titulo' => 'R']);

        $r = $this->paginas()->previewToken($p['id']);

        $this->assertStringStartsWith(base_url('pagina/rasc') . '?preview=', $r['url']);
        $this->assertTrue($this->preview->valido($r['token'], $p['id']));
        $this->assertFalse($this->preview->valido($r['token'], $p['id'] + 1));
    }

    public function testListarFiltraEConta(): void
    {
        $this->paginas()->criar(['slug' => 'rasc', 'titulo' => 'Rascunho teste']);

        $todas = $this->paginas()->listar();
        $this->assertSame('inicio', $todas[0]['slug'], 'ordem de exibição');
        $this->assertSame(5, array_column($todas, 'total_secoes', 'slug')['inicio']);

        $this->assertSame(['rasc'], array_column($this->paginas()->listar(['status' => 'rascunho']), 'slug'));
        $this->assertSame(['rasc'], array_column($this->paginas()->listar(['busca' => 'teste']), 'slug'));
        $this->assertCount(3, $this->paginas()->listar(['tipo' => 'sistema']));
    }

    public function testEscritasGeramAuditoria(): void
    {
        $p = $this->paginas()->criar(['slug' => 'audit', 'titulo' => 'A']);
        $this->paginas()->atualizar($p['id'], ['titulo' => 'B']);
        $this->paginas()->excluir($p['id']);

        $acoes = array_column($this->db->table('auditoria')->where('table_name', 'site_paginas')->get()->getResultArray(), 'action_type');
        $this->assertContains('CREATE', $acoes);
        $this->assertContains('UPDATE', $acoes);
        $this->assertContains('DELETE', $acoes);
    }

    // ============================================================ seções

    public function testCriarSecaoNormalizaSanitizaEAplicaPadroes(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);

        $r = $this->secoes()->criar($p['id'], ['tipo' => 'hero', 'ancora' => 'topo', 'dados' => [
            'titulo'  => 'Defesa <em>forte</em>',
            'inexistente' => 'x',
            'botoes'  => [['rotulo' => 'Ir', 'link' => ['tipo' => 'ancora', 'ancora' => 'contato'], 'extra' => 1]],
        ]]);

        $this->assertSame('primario', $r['secao']['dados']['botoes'][0]['estilo'], 'padrão aplicado em item de lista');
        $this->assertArrayNotHasKey('inexistente', $r['secao']['dados']);
        $this->assertArrayNotHasKey('extra', $r['secao']['dados']['botoes'][0]);
        $this->assertStringContainsString('inexistente', $r['avisos'][0]);
        $this->assertSame(1, $r['secao']['ordem']);
        $this->assertSame('topo', $r['secao']['ancora']);

        $texto = $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'dados' => ['corpo' => '<p onclick="x()">ok</p><script>alert(1)</script><a href="javascript:alert(1)">l</a>']]);
        $this->assertSame('<p>ok</p><a>l</a>', $texto['secao']['dados']['corpo'], 'rich_text sanitizado ao salvar');
        $this->assertSame(2, $texto['secao']['ordem'], 'entra no fim');
    }

    public function testCriarSecaoDevolveErrosPorCaminhoDeCampo(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $civil = $this->idDe('consultas');

        $erros = $this->erros(fn () => $this->secoes()->criar($p['id'], ['tipo' => 'hero', 'dados' => [
            'botoes' => [
                ['rotulo' => 'ok', 'link' => ['tipo' => 'pagina', 'pagina_id' => $civil]],
                ['rotulo' => '', 'link' => ['tipo' => 'url', 'url' => 'javascript:alert(1)'], 'estilo' => 'gigante'],
                ['rotulo' => 'x', 'link' => ['tipo' => 'pagina', 'pagina_id' => 99999]],
            ],
        ]]));

        $this->assertEqualsCanonicalizing(
            ['dados.titulo', 'dados.botoes.1.rotulo', 'dados.botoes.1.link', 'dados.botoes.1.estilo', 'dados.botoes.2.link'],
            array_keys($erros)
        );
        $this->assertSame('Página não encontrada.', $erros['dados.botoes.2.link']);
        $this->assertSame(0, $this->db->table('site_secoes')->where('pagina_id', $p['id'])->countAllResults(), 'nada é gravado');
    }

    public function testCriarSecaoRecusaTipoAncoraEMidiaInvalidos(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);

        $this->assertArrayHasKey('tipo', $this->erros(fn () => $this->secoes()->criar($p['id'], ['tipo' => 'tipo_inexistente'])));
        $this->assertArrayHasKey('tipo', $this->erros(fn () => $this->secoes()->criar($p['id'], [])));
        $this->assertArrayHasKey('ancora', $this->erros(fn () => $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'ancora' => 'Com Espaço', 'dados' => ['corpo' => 'x']])));

        $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'ancora' => 'dup', 'dados' => ['corpo' => 'x']]);
        $this->assertArrayHasKey('ancora', $this->erros(fn () => $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'ancora' => 'dup', 'dados' => ['corpo' => 'y']])));

        $this->expectException(NaoEncontrado::class);
        $this->secoes()->criar(99999, ['tipo' => 'rich_text', 'dados' => ['corpo' => 'x']]);
    }

    public function testAtualizarSecaoSubstituiDadosEMantemOTipo(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $s = $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'ancora' => 'a', 'dados' => ['titulo' => 'T', 'corpo' => '<p>1</p>']])['secao'];

        $r = $this->secoes()->atualizar($s['id'], ['dados' => ['corpo' => '<p>2</p>'], 'visivel' => false, 'ancora' => 'a']);

        $this->assertSame(['corpo' => '<p>2</p>'], $r['secao']['dados'], 'dados substituídos por inteiro');
        $this->assertSame(0, (int) $r['secao']['visivel']);
        $this->assertArrayHasKey('tipo', $this->erros(fn () => $this->secoes()->atualizar($s['id'], ['tipo' => 'hero'])));
        $this->assertArrayHasKey('dados.corpo', $this->erros(fn () => $this->secoes()->atualizar($s['id'], ['dados' => []])), 'campo obrigatório');
    }

    public function testSecaoDeTipoQueOTemplateNaoTemSoAceitaVisibilidade(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $this->db->table('site_secoes')->insert(['pagina_id' => $p['id'], 'tipo' => 'tipo_antigo', 'ordem' => 1, 'dados' => '{"x":1}']);
        $id = (int) $this->db->insertID();

        $this->assertFalse($this->paginas()->obter($p['id'])['secoes'][0]['compativel']);
        $this->assertArrayHasKey('tipo', $this->erros(fn () => $this->secoes()->atualizar($id, ['dados' => ['x' => 2]])));

        $r = $this->secoes()->atualizar($id, ['visivel' => false]);
        $this->assertSame(['x' => 1], $r['secao']['dados'], 'dados intactos');
    }

    public function testReordenarEExcluirSecoes(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);
        $ids = [];
        foreach (['a', 'b', 'c'] as $x) {
            $ids[$x] = $this->secoes()->criar($p['id'], ['tipo' => 'rich_text', 'dados' => ['corpo' => $x]])['secao']['id'];
        }

        $nova = $this->secoes()->reordenar($p['id'], [$ids['c'], $ids['a']]);
        $this->assertSame([$ids['c'], $ids['a'], $ids['b']], array_column($nova, 'id'), 'as que faltam vão para o fim');

        $outra = $this->paginas()->criar(['slug' => 'outra', 'titulo' => 'O']);
        $alheia = $this->secoes()->criar($outra['id'], ['tipo' => 'rich_text', 'dados' => ['corpo' => 'z']])['secao']['id'];
        $this->assertArrayHasKey('ordem', $this->erros(fn () => $this->secoes()->reordenar($p['id'], [$alheia])));
        $this->assertArrayHasKey('ordem', $this->erros(fn () => $this->secoes()->reordenar($p['id'], [$ids['a'], $ids['a']])));

        $this->secoes()->excluir($ids['a']);
        $this->assertSame([$ids['c'], $ids['b']], array_column($this->paginas()->obter($p['id'])['secoes'], 'id'));
        $this->expectException(NaoEncontrado::class);
        $this->secoes()->excluir($ids['a']);
    }

    // ============================================================ menus

    private function menus(): SiteMenuService
    {
        return new SiteMenuService();
    }

    public function testListarMenusIncluiOsLocaisDoTemplate(): void
    {
        $menus = $this->menus()->listar();

        $this->assertEqualsCanonicalizing(['cabecalho', 'rodape', 'lateral_areas'], array_keys($menus));
        $this->assertCount(7, $menus['cabecalho']);
        $this->assertTrue($menus['cabecalho'][6]['destaque']);
        $this->assertSame([], $menus['cabecalho'][0]['filhos']);
    }

    public function testSubstituirMenuGravaArvoreEApagaOAntigo(): void
    {
        $civil = $this->idDe('consultas');
        $rasc = $this->paginas()->criar(['slug' => 'rasc', 'titulo' => 'R'])['id'];

        $r = $this->menus()->substituir('rodape', [
            ['rotulo' => 'Áreas', 'tipo' => 'ancora', 'ancora' => '#expertise', 'filhos' => [
                ['rotulo' => 'Civil', 'tipo' => 'pagina', 'pagina_id' => $civil],
                ['rotulo' => 'Rascunho', 'tipo' => 'pagina', 'pagina_id' => $rasc],
            ]],
            ['rotulo' => 'Contato', 'tipo' => 'url', 'url' => '/contato', 'destaque' => true],
        ]);

        $this->assertSame(['Áreas', 'Contato'], array_column($r['itens'], 'rotulo'));
        $this->assertSame('expertise', $r['itens'][0]['ancora'], '# inicial é removido');
        $this->assertSame(['Civil', 'Rascunho'], array_column($r['itens'][0]['filhos'], 'rotulo'));
        $this->assertTrue($r['itens'][1]['destaque']);
        $this->assertStringContainsString('rascunho', $r['avisos'][0]);
        $this->assertSame(4, $this->db->table('site_menu_itens m')->join('site_menus x', 'x.id = m.menu_id')->where('x.local', 'rodape')->countAllResults(), 'os 4 itens antigos sumiram');
        $this->assertCount(7, $this->menus()->listar()['cabecalho'], 'outros menus não mudam');

        $this->assertSame([], $this->menus()->substituir('rodape', [])['itens'], 'menu pode ficar vazio');
    }

    public function testSubstituirMenuValidaPorCaminho(): void
    {
        $erros = $this->erros(fn () => $this->menus()->substituir('cabecalho', [
            ['rotulo' => '', 'tipo' => 'url', 'url' => 'javascript:alert(1)'],
            ['rotulo' => 'x', 'tipo' => 'pagina', 'pagina_id' => 999],
            ['rotulo' => 'y', 'tipo' => 'ancora', 'ancora' => 'Com Espaço'],
            ['rotulo' => 'z', 'tipo' => 'banana'],
            ['rotulo' => 'pai', 'tipo' => 'url', 'url' => '/a', 'filhos' => [
                ['rotulo' => 'filho', 'tipo' => 'url', 'url' => '/b', 'filhos' => [['rotulo' => 'neto', 'tipo' => 'url', 'url' => '/c']]],
            ]],
        ]));

        $this->assertEqualsCanonicalizing([
            'itens.0.rotulo', 'itens.0.url', 'itens.1.pagina_id', 'itens.2.ancora', 'itens.3.tipo', 'itens.4.filhos.0.filhos',
        ], array_keys($erros));
        $this->assertCount(7, $this->menus()->listar()['cabecalho'], 'nada foi alterado');

        $this->assertArrayHasKey('local', $this->erros(fn () => $this->menus()->substituir('inexistente', [])));
        $this->assertArrayHasKey('itens', $this->erros(fn () => $this->menus()->substituir('rodape', array_fill(0, 51, ['rotulo' => 'a', 'tipo' => 'url', 'url' => '/a']))));
    }

    // ============================================================ configurações

    private function config(): SiteConfiguracaoService
    {
        return new SiteConfiguracaoService();
    }

    private function valorDe(array $grupos, string $chave): mixed
    {
        foreach ($grupos as $linhas) {
            foreach ($linhas as $l) {
                if ($l['chave'] === $chave) {
                    return $l['valor'];
                }
            }
        }
        $this->fail("Chave {$chave} não encontrada.");
    }

    public function testConfiguracoesAgrupadasEAtualizacaoParcial(): void
    {
        $grupos = $this->config()->agrupadas();
        $this->assertSame('', $this->valorDe($grupos, 'contato.telefone'));

        $novo = $this->config()->atualizar([
            'contato.telefone' => '(11) 1111-1111',
            'redes.instagram'  => 'https://instagram.com/clinica',
            'integracoes.ga_id' => 'G-ABCD1234',
            'noticias.filtros' => ['Tributário'],
        ]);

        $this->assertSame('(11) 1111-1111', $this->valorDe($novo, 'contato.telefone'));
        $this->assertSame('seo', $this->db->table('site_configuracoes')->where('chave', 'seo.sufixo_titulo')->get()->getRow()->grupo);
        $this->assertSame('Clínica Saúde', $this->valorDe($novo, 'identidade.nome'), 'o que não foi enviado não muda');
        $this->assertSame(['Tributário'], $this->valorDe($novo, 'noticias.filtros'));
        $this->assertSame('json', $this->db->table('site_configuracoes')->where('chave', 'noticias.filtros')->get()->getRow()->tipo);
    }

    public function testConfiguracoesValidamPorChaveETudoOuNada(): void
    {
        $erros = $this->erros(fn () => $this->config()->atualizar([
            'contato.telefone' => '(11) 1111-1111',
            'Chave Ruim'       => 'x',
            'sistema.senha'    => 'x',
            'integracoes.ga_id' => 'UA-1',
            'contato.email'    => 'nao-e-email',
            'redes.facebook'   => 'javascript:alert(1)',
            'seo.descricao_padrao' => str_repeat('a', 161),
            'textos.nao.existe' => 'x',
            'identidade.logo'  => 999,
            'identidade.nome'  => ['valor' => 'Novo', 'tipo' => 'json'],
            'noticias.filtros' => 'texto solto',
            'contato.enderecos' => [['pais' => 'BR']],
            'seo.sufixo_titulo' => "duas\nlinhas",
        ]));

        $this->assertEqualsCanonicalizing([
            'Chave Ruim', 'sistema.senha', 'integracoes.ga_id', 'contato.email', 'redes.facebook', 'seo.descricao_padrao',
            'textos.nao.existe', 'identidade.logo', 'identidade.nome', 'noticias.filtros', 'contato.enderecos', 'seo.sufixo_titulo',
        ], array_keys($erros));
        $this->assertSame('', $this->valorDe($this->config()->agrupadas(), 'contato.telefone'), 'nada foi gravado');
    }

    public function testCnpjDaIdentidadeEValidadoEGravadoSoComDigitos(): void
    {
        $erros = $this->erros(fn () => $this->config()->atualizar(['identidade.cnpj' => '11.222.333/0001-82']));
        $this->assertArrayHasKey('identidade.cnpj', $erros);

        $novo = $this->config()->atualizar([
            'identidade.cnpj'         => '11.222.333/0001-81',
            'identidade.razao_social' => 'Clínica Saúde',
            'identidade.registro_profissional' => 'CRM/MG nº 1234',
        ]);
        $this->assertSame('11222333000181', $this->valorDe($novo, 'identidade.cnpj'));

        $limpo = $this->config()->atualizar(['identidade.cnpj' => '']);
        $this->assertSame('', $this->valorDe($limpo, 'identidade.cnpj'), 'limpar é permitido');
    }

    public function testConfiguracoesDoSchemaOrgSaoValidadas(): void
    {
        $erros = $this->erros(fn () => $this->config()->atualizar([
            'contato.latitude'     => '120',
            'contato.mapa_url'     => 'javascript:alert(1)',
            'contato.horario'      => [['dias' => ['Funday'], 'abre' => '09:00', 'fecha' => '18:00']],
            'contato.area_atendida' => ['ok', ''],
            'identidade.profissionais' => [['nome' => '', 'registro' => 'x']],
        ]));
        $this->assertEqualsCanonicalizing(['contato.latitude', 'contato.mapa_url', 'contato.horario', 'contato.area_atendida', 'identidade.profissionais'], array_keys($erros));

        $novo = $this->config()->atualizar([
            'contato.latitude'     => '-19.93',
            'contato.longitude'    => '-43.94',
            'contato.mapa_url'     => 'https://maps.google.com/?q=x',
            'contato.horario'      => [['dias' => ['Monday', 'Friday'], 'abre' => '09:00', 'fecha' => '18:00']],
            'identidade.profissionais' => [['nome' => 'Fulana', 'registro' => 'CRM/MG 1']],
        ]);
        $this->assertSame('09:00', $this->valorDe($novo, 'contato.horario')[0]['abre']);
    }

    public function testTextoEditadoPodeVoltarAoPadraoDoTemplate(): void
    {
        $this->config()->atualizar(['textos.contato.botao' => 'Falar conosco']);
        $this->assertSame('Falar conosco', $this->valorDe($this->config()->agrupadas(), 'textos.contato.botao'));

        $depois = $this->config()->atualizar(['textos.contato.botao' => '']);

        $linhas = array_column($depois['textos'], 'valor', 'chave');
        $this->assertArrayNotHasKey('textos.contato.botao', $linhas, 'linha removida: vale o padrão do manifesto');
    }

    public function testNovaChaveComTipoExplicitoEImagemDaBiblioteca(): void
    {
        $this->db->table('site_midias')->insert(['arquivo' => 'a.png', 'nome_original' => 'a.png', 'mime' => 'image/png']);
        $midia = (int) $this->db->insertID();

        $g = $this->config()->atualizar([
            'identidade.logo' => ['valor' => $midia, 'tipo' => 'imagem'],
            'tema.cores.gold' => ['valor' => '#B08E35', 'tipo' => 'cor'],
        ]);

        $this->assertSame($midia, $this->valorDe($g, 'identidade.logo'));
        $this->assertSame('#B08E35', $this->valorDe($g, 'tema.cores.gold'));
        $this->assertArrayHasKey('tema.cores.gold', $this->erros(fn () => $this->config()->atualizar(['tema.cores.gold' => 'dourado'])));
    }

    // ============================================================ schema

    public function testSchemaDoTemplateAtivo(): void
    {
        $s = (new SiteTemplateService())->schema();

        $this->assertSame('classico', $s['template']['slug']);
        $this->assertSame(['main', 'area'], $s['layouts']);
        $this->assertContains('lateral_areas', $s['menus']);
        $this->assertSame('Destaque principal', $s['secoes']['hero']['rotulo']);
        $this->assertSame('texto_inline', $s['secoes']['hero']['campos']['titulo']['tipo']);
        $this->assertSame('Enviar Consulta', $s['textos']['contato.botao']);
        $this->assertArrayHasKey('cores', $s['tema']);

        $this->db->table('site_templates')->update(['ativo' => 0]);
        $this->limpaCache();
        $this->assertNull((new SiteTemplateService())->schema());
    }

    // ============================================================ tema

    private function tema(): SiteTemaService
    {
        return new SiteTemaService();
    }

    public function testTemaListaTokensDoTemplateComPadraoEAtual(): void
    {
        $t = $this->tema()->obter();

        $this->assertSame('#C9A84C', $t['cores']['gold']['padrao']);
        $this->assertFalse($t['cores']['gold']['personalizado']);
        $this->assertSame('Cormorant Garamond', $t['fontes']['serif']['atual']);
        $this->assertSame('', $t['css']);
        $this->assertArrayHasKey('Lora', $t['fontes_disponiveis']);
    }

    public function testSalvarTemaGravaSoOQueMudouEGeraCss(): void
    {
        $t = $this->tema()->salvar(['cores' => ['gold' => '#B08E35', 'navy-dark' => '#102030'], 'fontes' => ['serif' => 'Lora']]);

        $this->assertTrue($t['cores']['gold']['personalizado']);
        $this->assertSame('#B08E35', $t['cores']['gold']['atual']);
        $this->assertFalse($t['cores']['cream']['personalizado']);
        $this->assertStringContainsString('--gold:#B08E35;--gold-rgb:176, 142, 53;', $t['css']);
        $this->assertStringContainsString("--font-serif:'Lora', serif;", $t['css']);
        $this->assertSame(3, $this->db->table('site_configuracoes')->like('chave', 'tema.', 'after')->countAllResults());
        $this->assertSame('cor', $this->db->table('site_configuracoes')->where('chave', 'tema.cores.gold')->get()->getRow()->tipo);
    }

    public function testSalvarTemaDevolveErrosPeloCaminhoDoCorpoETudoOuNada(): void
    {
        $erros = $this->erros(fn () => $this->tema()->salvar([
            'cores'  => ['gold' => 'dourado', 'inexistente' => '#123456', 'cream' => '#FFFFFF'],
            'fontes' => ['serif' => 'Comic Sans MS', 'sans' => 'Inter'],
        ]));

        $this->assertEqualsCanonicalizing(['cores.gold', 'cores.inexistente', 'fontes.serif'], array_keys($erros));
        $this->assertArrayHasKey('extra', $this->erros(fn () => $this->tema()->salvar(['extra' => 1])));
        $this->assertSame(0, $this->db->table('site_configuracoes')->like('chave', 'tema.', 'after')->countAllResults(), 'nada gravado');
        $this->assertArrayHasKey('cores', $this->erros(fn () => $this->tema()->salvar(['cores' => ['#fff']])));
    }

    public function testTemaVazioRestauraOPadraoDeUmTokenEDeTodos(): void
    {
        $this->tema()->salvar(['cores' => ['gold' => '#B08E35', 'cream' => '#FFFFFF'], 'fontes' => ['sans' => 'Inter']]);

        $t = $this->tema()->salvar(['cores' => ['gold' => null], 'fontes' => ['sans' => '']]);
        $this->assertFalse($t['cores']['gold']['personalizado']);
        $this->assertFalse($t['fontes']['sans']['personalizado']);
        $this->assertTrue($t['cores']['cream']['personalizado'], 'os outros ficam');

        $t = $this->tema()->restaurar();
        $this->assertSame('', $t['css']);
        $this->assertSame(0, $this->db->table('site_configuracoes')->like('chave', 'tema.', 'after')->countAllResults());
        $this->assertSame('Cormorant Garamond', $t['fontes']['serif']['atual']);
    }

    public function testTemaTambemPelaApiDeConfiguracoes(): void
    {
        $this->config()->atualizar(['tema.cores.navy-dark' => '#102030']);
        $this->assertSame('#102030', $this->tema()->obter()['cores']['navy-dark']['atual'], 'token com hífen');

        $this->assertArrayHasKey('tema.cores.outro', $this->erros(fn () => $this->config()->atualizar(['tema.cores.outro' => '#fff'])));
        $this->assertArrayHasKey('tema.x', $this->erros(fn () => $this->config()->atualizar(['tema.x' => '#fff'])));
    }

    // ============================================================ HTTP (controllers)

    private function json(string $classe, string $metodo, array|null $corpo = null, array $params = []): \CodeIgniter\Test\TestResponse
    {
        $this->resetServices();
        $t = $this->withBody($corpo === null ? '' : json_encode($corpo), 'json')->controller($classe);

        return $t->execute($metodo, ...$params);
    }

    private function corpo(\CodeIgniter\Test\TestResponse $r): array
    {
        return json_decode($r->getJSON(), true) ?? [];
    }

    public function testPaginasPelaApi(): void
    {
        $r = $this->json(Paginas::class, 'store', ['slug' => 'Slug Ruim', 'titulo' => '']);
        $this->assertSame(422, $r->response()->getStatusCode());
        $this->assertArrayHasKey('slug', $this->corpo($r)['messages']);

        $r = $this->json(Paginas::class, 'store', ['slug' => 'nova', 'titulo' => 'Nova']);
        $this->assertSame(201, $r->response()->getStatusCode());
        $id = $this->corpo($r)['data']['id'];

        $this->assertSame(200, $this->json(Paginas::class, 'show', null, [$id])->response()->getStatusCode());
        $this->assertSame(404, $this->json(Paginas::class, 'show', null, [99999])->response()->getStatusCode());
        $this->assertSame(404, $this->json(Paginas::class, 'publicar', null, [99999])->response()->getStatusCode());

        $r = $this->json(Paginas::class, 'destroy', null, [$this->idDe('inicio')]);
        $this->assertSame(422, $r->response()->getStatusCode(), 'a home não sai');

        $r = $this->json(Paginas::class, 'update', ['slug' => 'inicio-novo'], [$this->idDe('consultas')]);
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertCount(1, $this->corpo($r)['avisos'], 'aviso de troca de slug');
    }

    public function testSecoesEMenusEConfiguracoesPelaApi(): void
    {
        $p = $this->paginas()->criar(['slug' => 'nova', 'titulo' => 'Nova']);

        $r = $this->json(Secoes::class, 'store', ['tipo' => 'hero', 'dados' => ['subtitulo' => 'x']], [$p['id']]);
        $this->assertSame(422, $r->response()->getStatusCode());
        $this->assertArrayHasKey('dados.titulo', $this->corpo($r)['messages']);

        $r = $this->json(Secoes::class, 'store', ['tipo' => 'hero', 'dados' => ['titulo' => 'Oi']], [$p['id']]);
        $this->assertSame(201, $r->response()->getStatusCode());
        $secaoId = $this->corpo($r)['data']['id'];

        $this->assertSame(200, $this->json(Secoes::class, 'ordem', ['ids' => [$secaoId]], [$p['id']])->response()->getStatusCode());
        $this->assertSame(422, $this->json(Secoes::class, 'ordem', ['ids' => 'x'], [$p['id']])->response()->getStatusCode());
        $this->assertSame(200, $this->json(Secoes::class, 'destroy', null, [$secaoId])->response()->getStatusCode());
        $this->assertSame(404, $this->json(Secoes::class, 'destroy', null, [$secaoId])->response()->getStatusCode());

        $this->assertSame(200, $this->json(Menus::class, 'index')->response()->getStatusCode());
        $this->assertSame(422, $this->json(Menus::class, 'update', ['itens' => 'x'], ['rodape'])->response()->getStatusCode());
        $this->assertSame(200, $this->json(Menus::class, 'update', ['itens' => []], ['rodape'])->response()->getStatusCode());

        $r = $this->json(Configuracoes::class, 'update', ['contato.email' => 'ruim']);
        $this->assertSame(422, $r->response()->getStatusCode());
        $this->assertArrayHasKey('contato.email', $this->corpo($r)['messages']);
        $this->assertSame(200, $this->json(Configuracoes::class, 'update', ['contato.email' => 'a@b.com'])->response()->getStatusCode());

        $this->assertSame(200, $this->json(TemplateAtivo::class, 'tema')->response()->getStatusCode());
        $r = $this->json(TemplateAtivo::class, 'salvarTema', ['cores' => ['gold' => 'dourado']]);
        $this->assertSame(422, $r->response()->getStatusCode());
        $this->assertArrayHasKey('cores.gold', $this->corpo($r)['messages']);
        $r = $this->json(TemplateAtivo::class, 'salvarTema', ['cores' => ['gold' => '#B08E35']]);
        $this->assertSame('#B08E35', $this->corpo($r)['data']['cores']['gold']['atual']);
        $r = $this->json(TemplateAtivo::class, 'restaurarTema');
        $this->assertFalse($this->corpo($r)['data']['cores']['gold']['personalizado']);

        $r = $this->json(TemplateAtivo::class, 'schema');
        $this->assertSame('classico', $this->corpo($r)['data']['template']['slug']);
    }

    public function testErroInternoNaoVazaParaOCliente(): void
    {
        $this->db->table('site_paginas')->truncate();
        Database::forge($this->db)->dropTable('site_secoes', true);

        $r = $this->json(Paginas::class, 'index');

        $this->assertSame(500, $r->response()->getStatusCode());
        $this->assertStringNotContainsString('site_secoes', $r->getJSON());
    }
}
