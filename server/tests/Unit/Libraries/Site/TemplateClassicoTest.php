<?php

namespace Tests\Unit\Libraries\Site;

use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\DadosDinamicos;
use App\Libraries\Site\PreviewToken;
use App\Libraries\Site\SecaoPreparador;
use App\Libraries\Site\SiteContexto;
use App\Libraries\Site\SiteRenderService;
use App\Libraries\Site\Template\ManifestValidator;
use App\Libraries\Site\Template\SeedImporter;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Models\Site\SitePaginaModel;
use App\Services\Site\SiteTemplateService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\SiteTemplate;

/**
 * Template Clássico (issue #646): instalação, seed e renderização.
 */
class TemplateClassicoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $tmp;
    private SiteTemplate $config;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $migration = new CreateSiteConteudoTables(Database::forge($this->db));
        $migration->down();
        $migration->up();

        $this->tmp = WRITEPATH . 'classico_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->tmp, 0777, true);
        $this->config = new SiteTemplate();
        $this->config->diretorioAssets = $this->tmp . 'assets';
    }

    protected function tearDown(): void
    {
        (new CreateSiteConteudoTables(Database::forge($this->db)))->down();
        $this->apaga($this->tmp);
        SiteContexto::definir(null);
        parent::tearDown();
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

    private function instalador(): TemplateInstaller
    {
        return new TemplateInstaller(assets: new TemplateAssets($this->config), service: new SiteTemplateService(config: $this->config));
    }

    private function manifesto(): array
    {
        return json_decode(file_get_contents(APPPATH . 'Templates/classico/template.json'), true);
    }

    private function seed(): array
    {
        return json_decode(file_get_contents(APPPATH . 'Templates/classico/seed/content.json'), true);
    }


    public function testManifestoESeedSaoValidos(): void
    {
        $this->assertSame([], (new ManifestValidator())->validar($this->manifesto())->erros());

        $seed = $this->seed();
        $this->assertSame(['inicio', 'consultas', 'exames-e-procedimentos', 'acompanhamento-e-prevencao', 'quem-somos', 'noticias', 'area-cliente', 'painel-cliente', 'politica-de-privacidade', 'termos-de-uso'], array_column($seed['paginas'], 'slug'));
        foreach ($seed['paginas'] as $p) {
            $this->assertLessThanOrEqual(160, mb_strlen($p['meta_description'] ?? ''), $p['slug']);
        }
    }

    public function testInstalacaoRegistraAtivaPublicaAssetsEImportaOConteudo(): void
    {
        $seed = $this->seed();
        $r = $this->instalador()->instalarCore('classico');

        $this->assertTrue($r['ativado']);
        $this->assertSame([], $r['seed']['erros'], json_encode($r['seed']['erros'], JSON_UNESCAPED_UNICODE));
        $this->assertCount(count($seed['paginas']), $r['seed']['paginas']);
        $this->assertSame(1, $this->db->table('site_templates')->where('ativo', 1)->countAllResults());
        $this->assertSame(count($seed['paginas']), $this->db->table('site_paginas')->where('status', 'publicado')->countAllResults());
        $this->assertSame(
            array_sum(array_map(static fn ($p) => count($p['secoes'] ?? []), $seed['paginas'])),
            $this->db->table('site_secoes')->countAllResults()
        );
        $this->assertFileExists($this->config->diretorioAssets . '/classico/assets/css/site.css');
        $this->assertFileExists($this->config->diretorioAssets . '/classico/.versao');
    }

    public function testSeedImporterSoCriaOQueNaoExisteEReportaSecaoInvalida(): void
    {
        $this->db->table('site_paginas')->insert(['slug' => 'consultas', 'titulo' => 'Consultas da clínica', 'tipo' => 'area', 'status' => 'publicado']);
        $this->db->table('site_configuracoes')->insert(['chave' => 'contato.telefone', 'valor' => '(11) 1111-1111', 'tipo' => 'string', 'grupo' => 'contato']);

        $seed = $this->seed();
        $seed['paginas'][0]['secoes'][] = ['tipo' => 'hero', 'dados' => ['titulo' => '']]; // obrigatório vazio
        $seed['paginas'][0]['secoes'][] = ['tipo' => 'inexistente', 'dados' => []];
        $validas = count($this->seed()['paginas'][0]['secoes']);

        $r = (new SeedImporter())->importar($seed, $this->manifesto(), 'rascunho');

        $this->assertSame(['consultas'], $r['paginas_ignoradas']);
        $this->assertSame('Consultas da clínica', $this->db->table('site_paginas')->where('slug', 'consultas')->get()->getRow()->titulo);
        $this->assertSame('(11) 1111-1111', $this->db->table('site_configuracoes')->where('chave', 'contato.telefone')->get()->getRow()->valor);
        $this->assertNotContains('contato.telefone', $r['configuracoes']);
        $this->assertSame(count($seed['paginas']) - 1, $this->db->table('site_paginas')->where('status', 'rascunho')->countAllResults(), 'status forçado nas novas');
        $this->assertArrayHasKey('paginas.inicio.secoes.' . ($validas + 1) . '.titulo', $r['erros']);
        $this->assertArrayHasKey('paginas.inicio.secoes.' . ($validas + 2), $r['erros']);
        $this->assertSame($validas, $this->db->table('site_secoes')->join('site_paginas', 'site_paginas.id = site_secoes.pagina_id')->where('slug', 'inicio')->countAllResults(), 'seções inválidas não entram');
    }

    public function testHomeERenderizaComSeoGenerico(): void
    {
        $home = $this->renderNovo('inicio');

        $this->assertStringContainsString('<title>Clínica Saúde', $home);
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url() . '">', $home);
        $this->assertStringContainsString('"@type":"MedicalClinic"', $home);

        $consultas = $this->renderNovo('consultas');
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('pagina/consultas') . '">', $consultas);
    }

    public function testFormularioDeContatoPedeCienciaDaPolitica(): void
    {
        $home = $this->renderNovo('inicio');

        $this->assertMatchesRegularExpression('/<input type="checkbox" id="aceite_privacidade" name="aceite_privacidade" value="1" required/', $home);
        $this->assertStringContainsString('href="' . base_url('pagina/politica-de-privacidade') . '"', $home);
    }

    public function testPoliticaETermosSaoPublicados(): void
    {
        $this->assertStringContainsString('Política de Privacidade', $this->renderNovo('politica-de-privacidade'));
        $this->assertStringContainsString('Termos de Uso', $this->renderNovo('termos-de-uso'));
    }

    public function testReinstalarNaoAlteraNada(): void
    {
        $this->instalador()->instalarCore('classico');
        $antes = [
            $this->db->table('site_paginas')->countAllResults(),
            $this->db->table('site_secoes')->countAllResults(),
            $this->db->table('site_menu_itens')->countAllResults(),
            $this->db->table('site_configuracoes')->countAllResults(),
        ];

        $r = $this->instalador()->instalarCore('classico');

        $this->assertFalse($r['ativado']);
        $this->assertNull($r['seed'], 'site com páginas não recebe o seed');
        $this->assertSame($antes, [
            $this->db->table('site_paginas')->countAllResults(),
            $this->db->table('site_secoes')->countAllResults(),
            $this->db->table('site_menu_itens')->countAllResults(),
            $this->db->table('site_configuracoes')->countAllResults(),
        ]);
        $this->assertSame(1, $this->db->table('site_templates')->countAllResults());
    }

    public function testSiteComConteudoEOutroTemplateAtivoNaoEMexido(): void
    {
        $this->db->table('site_templates')->insert(['slug' => 'outro', 'nome' => 'Outro', 'versao' => '1.0.0', 'origem' => 'upload', 'ativo' => 1]);
        $this->db->table('site_paginas')->insert(['slug' => 'inicio', 'titulo' => 'Minha home', 'tipo' => 'home', 'status' => 'publicado']);

        $r = $this->instalador()->instalarCore('classico');

        $this->assertFalse($r['ativado'], 'não troca o template ativo');
        $this->assertNull($r['seed']);
        $this->assertSame('Minha home', $this->db->table('site_paginas')->where('slug', 'inicio')->get()->getRow()->titulo);
        $this->assertSame('outro', $this->db->table('site_templates')->where('ativo', 1)->get()->getRow()->slug);
    }


    public function testAssetsSoRepublicamQuandoAVersaoMuda(): void
    {
        $assets = new TemplateAssets($this->config);
        $pacote = APPPATH . 'Templates/classico';

        $this->assertTrue($assets->garantir('classico', '1.0.0', $pacote));
        $this->assertFalse($assets->garantir('classico', '1.0.0', $pacote));
        $this->assertTrue($assets->garantir('classico', '1.0.1', $pacote));
    }

    public function testAssetsNaoCopiamPhpNemSeguemLinks(): void
    {
        $pacote = $this->tmp . 'pacote/';
        mkdir($pacote . 'assets/css', 0777, true);
        file_put_contents($pacote . 'assets/css/a.css', 'a{}');
        file_put_contents($pacote . 'assets/shell.php', '<?php evil();');
        symlink('/etc/hostname', $pacote . 'assets/link.css');

        (new TemplateAssets($this->config))->publicar('t', '1.0.0', $pacote);

        $this->assertFileExists($this->config->diretorioAssets . '/t/assets/css/a.css');
        $this->assertFileDoesNotExist($this->config->diretorioAssets . '/t/assets/shell.php');
        $this->assertFileDoesNotExist($this->config->diretorioAssets . '/t/assets/link.css');
        $this->expectException(\InvalidArgumentException::class);
        (new TemplateAssets($this->config))->publicar('../fora', '1.0.0', $pacote);
    }

    // ---------------------------------------------------------------- paridade
    private function noticias(): array
    {
        $n = [];
        foreach ([1, 2, 3, 4] as $i) {
            $n[] = ['title' => "Artigo {$i} & mais", 'summary' => "Resumo <{$i}>", 'slug' => "artigo-{$i}", 'published_at' => "2026-0{$i}-15 10:00:00"];
        }
        return $n;
    }

    private function servicoRender(): SiteRenderService
    {
        $this->instalador()->instalarCore('classico');

        $contexto = new SiteContexto();
        SiteContexto::definir($contexto);
        $dinamicos = new DadosDinamicos();
        $dinamicos->registrar('areas', fn () => [['id' => 1, 'nome' => 'Consultas'], ['id' => 2, 'nome' => 'Exames e Procedimentos']]);
        $dinamicos->registrar('ultimas_noticias', fn () => array_map(static fn ($a) => [
            'titulo' => $a['title'], 'resumo' => $a['summary'], 'slug' => $a['slug'],
            'data' => $a['published_at'], 'href' => base_url('noticias/' . $a['slug']),
        ], $this->noticias()));

        return new SiteRenderService(
            new SiteTemplateService(config: $this->config), null, null, $contexto,
            new SecaoPreparador($contexto, $dinamicos), new PreviewToken('x'), $this->config, new TemplateAssets($this->config)
        );
    }

    private function renderNovo(string $slug): string
    {
        $render = $this->servicoRender();
        $html = $slug === 'inicio' ? $render->renderHome() : $render->renderPagina($slug);
        $this->assertNotNull($html, "página {$slug} não renderizou");

        return $html;
    }

    public function testPaginaDeSistemaNaoAbreEmPaginaSlug(): void
    {
        $render = $this->servicoRender();

        $this->assertNull($render->renderPagina('noticias'));
        $this->assertNull($render->renderPagina('area-cliente'));
    }

    public function testNenhumDadoDeOutraEmpresaFicaNoCodigoDoTemplate(): void
    {
        $achados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH . 'Templates/classico', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arquivo) {
            $rel = str_replace(APPPATH . 'Templates/classico/', '', $arquivo->getPathname());
            // O seed e o manifesto são justamente os dados iniciais; assets visuais (css/img) podem citar a marca no cabeçalho.
            if (str_starts_with($rel, 'seed/') || str_starts_with($rel, 'assets/') || $rel === 'template.json') {
                continue;
            }
            if (preg_match('/OAB|advog|jur[ií]dic|pagead|googlesyndication/i', file_get_contents($arquivo->getPathname()))) {
                $achados[] = $rel;
            }
        }
        $this->assertSame([], $achados);
    }
}

