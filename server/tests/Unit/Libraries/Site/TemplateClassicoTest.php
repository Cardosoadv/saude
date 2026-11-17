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
 * Template Clássico (issue #646): instalação/seed e paridade com as views legadas.
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
        $this->assertSame(['inicio', 'direito-civil', 'direito-administrativo', 'contratos-negocios', 'advocacia-colaborativa', 'noticias', 'area-cliente', 'painel-cliente', 'politica-de-privacidade', 'termos-de-uso', 'quem-somos'], array_column($seed['paginas'], 'slug'));
        foreach ($seed['paginas'] as $p) {
            $this->assertLessThanOrEqual(160, mb_strlen($p['meta_description'] ?? ''), $p['slug']);
        }
    }

    public function testInstalacaoRegistraAtivaPublicaAssetsEImportaOConteudo(): void
    {
        $r = $this->instalador()->instalarCore('classico');

        $this->assertTrue($r['ativado']);
        $this->assertSame([], $r['seed']['erros'], json_encode($r['seed']['erros'], JSON_UNESCAPED_UNICODE));
        $this->assertCount(11, $r['seed']['paginas']);
        $this->assertSame(1, $this->db->table('site_templates')->where('ativo', 1)->countAllResults());
        $this->assertSame(11, $this->db->table('site_paginas')->where('status', 'publicado')->countAllResults());
        $this->assertSame(5 + 7 * 4 + 11 + 9 + 6, $this->db->table('site_secoes')->countAllResults(), '5 da home + 7 por área + 11 da política + 9 dos termos + 6 do Quem Somos');
        $menus = array_column(
            $this->db->table('site_menus m')->select('m.local, COUNT(i.id) AS n')->join('site_menu_itens i', 'i.menu_id = m.id')->groupBy('m.local')->get()->getResultArray(), 'n', 'local'
        );
        ksort($menus);
        $this->assertEquals(['cabecalho' => 7, 'lateral_areas' => 4, 'rodape' => 7], $menus);
        $this->assertFileExists($this->config->diretorioAssets . '/classico/assets/css/site.css');
        $this->assertFileExists($this->config->diretorioAssets . '/classico/assets/css/areas.css');
        $this->assertFileExists($this->config->diretorioAssets . '/classico/.versao');
    }

    public function testLinksDoSeedViramIdsDePagina(): void
    {
        $this->instalador()->instalarCore('classico');

        $civil = $this->db->table('site_paginas')->where('slug', 'direito-civil')->get()->getRow()->id;
        $cards = $this->db->table('site_secoes')->where('tipo', 'cards_areas')->get()->getRow();

        $this->assertSame($civil, json_decode($cards->dados, true)['itens'][0]['link']['pagina_id']);
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

    public function testSeedImporterSoCriaOQueNaoExisteEReportaSecaoInvalida(): void
    {
        $this->db->table('site_paginas')->insert(['slug' => 'direito-civil', 'titulo' => 'Civil do escritório', 'tipo' => 'area', 'status' => 'publicado']);
        $this->db->table('site_configuracoes')->insert(['chave' => 'contato.telefone', 'valor' => '(11) 1111-1111', 'tipo' => 'string', 'grupo' => 'contato']);

        $seed = $this->seed();
        $seed['paginas'][0]['secoes'][] = ['tipo' => 'hero', 'dados' => ['titulo' => '']]; // obrigatório vazio
        $seed['paginas'][0]['secoes'][] = ['tipo' => 'inexistente', 'dados' => []];

        $r = (new SeedImporter())->importar($seed, $this->manifesto(), 'rascunho');

        $this->assertSame(['direito-civil'], $r['paginas_ignoradas']);
        $this->assertSame('Civil do escritório', $this->db->table('site_paginas')->where('slug', 'direito-civil')->get()->getRow()->titulo);
        $this->assertSame('(11) 1111-1111', $this->db->table('site_configuracoes')->where('chave', 'contato.telefone')->get()->getRow()->valor);
        $this->assertNotContains('contato.telefone', $r['configuracoes']);
        $this->assertSame(10, $this->db->table('site_paginas')->where('status', 'rascunho')->countAllResults(), 'status forçado nas novas');
        $this->assertArrayHasKey('paginas.inicio.secoes.6.titulo', $r['erros']);
        $this->assertArrayHasKey('paginas.inicio.secoes.7', $r['erros']);
        $this->assertSame(5, $this->db->table('site_secoes')->join('site_paginas', 'site_paginas.id = site_secoes.pagina_id')->where('slug', 'inicio')->countAllResults(), 'seções inválidas não entram');
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

    private const AREAS = [['id' => 1, 'nome' => 'Direito Civil'], ['id' => 2, 'nome' => 'Direito Administrativo']];

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
        $dinamicos->registrar('areas', fn () => self::AREAS);
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

    private function renderTela(string $tela, string $slug, array $dados, string $titulo, array $seo = []): string
    {
        $html = $this->servicoRender()->renderTela($tela, $slug, $dados, $titulo, $seo);
        $this->assertNotNull($html, "tela {$tela} não renderizou");

        return $html;
    }

    /** Corpo da página, normalizado para comparar estrutura e texto (sem espaços/atributos de formatação). */
    private function normaliza(string $html): string
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        $xp = new \DOMXPath($doc);

        foreach ($xp->query('//comment() | //script | //style | //head | //input[@type="hidden"]') as $no) {
            $no->parentNode?->removeChild($no);
        }
        // Mudança intencional (LGPD, #692): links da política e dos termos no rodapé, aviso de cookies
        // e ciência da política no formulário não existiam no site legado.
        $lgpd = '//li[a[contains(@href, "/pagina/politica-de-privacidade") or contains(@href, "/pagina/termos-de-uso")]]'
            . ' | //div[@id="cookie-banner"] | //button[@data-cookies-abrir] | //div[div[@class="form-check-custom"]]'
            . ' | //p[contains(@class, "form-privacy-note")]';
        foreach ($xp->query($lgpd) as $no) {
            $no->parentNode?->removeChild($no);
        }
        // Mudança intencional (acessibilidade, Palette): link "pular para o conteúdo", landmarks nomeados,
        // aria-* do menu e autocomplete do formulário não existiam no site legado.
        foreach ($xp->query('//a[contains(@class, "skip-link")]') as $no) {
            $no->parentNode?->removeChild($no);
        }
        // Mudança intencional (SEO, #689): âncoras descritivas no lugar de "Ver Detalhes", "Ver Todas
        // Notícias" e "Ler Artigo" soltos. Volta ao texto do legado para o resto da paridade valer.
        foreach (iterator_to_array($xp->query('//a[contains(@class, "news-card-link")]//span[contains(@class, "visually-hidden")]')) as $no) {
            $no->parentNode->removeChild($no);
        }
        foreach ($xp->query('//a[contains(@class, "expertise-link")] | //section[contains(@class, "highlights-section")]//a[contains(@class, "btn-outline-gold")]') as $a) {
            $a->firstChild->nodeValue = str_contains($a->getAttribute('class'), 'expertise-link') ? 'Ver Detalhes ' : 'Ver Todas Notícias ';
        }
        // <nav> do rodapé envolve a lista de links: desembrulha para manter a estrutura do legado.
        foreach (iterator_to_array($xp->query('//footer//nav')) as $nav) {
            while ($nav->firstChild) {
                $nav->parentNode->insertBefore($nav->firstChild, $nav);
            }
            $nav->parentNode->removeChild($nav);
        }
        foreach ($xp->query('//*[@aria-controls or (@aria-current and ancestor::nav[contains(@class, "main-navbar")]) or @autocomplete or @id="conteudo-principal" or @id="contact-errors" or self::nav[contains(@class, "main-navbar")]]') as $no) {
            foreach (['aria-controls', 'autocomplete'] as $attr) {
                $no->removeAttribute($attr);
            }
            if ($no->nodeName === 'a') {
                $no->removeAttribute('aria-current');
            }
            if ($no->nodeName === 'nav' || $no->getAttribute('id') === 'conteudo-principal' || $no->getAttribute('id') === 'contact-errors') {
                $no->removeAttribute('aria-label');
                $no->removeAttribute('tabindex');
            }
            if ($no->getAttribute('id') === 'conteudo-principal') {
                $no->removeAttribute('id');
            }
        }
        // Mudança intencional (imagens responsivas, #694): <picture> com fontes AVIF/WebP e
        // width/height/loading/decoding/fetchpriority no <img>. Volta ao <img> simples do legado.
        foreach (iterator_to_array($xp->query('//picture')) as $picture) {
            foreach (iterator_to_array($xp->query('.//img', $picture)) as $img) {
                $picture->parentNode->insertBefore($img, $picture);
            }
            $picture->parentNode->removeChild($picture);
        }
        foreach ($xp->query('//img') as $img) {
            foreach (['width', 'height', 'loading', 'decoding', 'fetchpriority', 'srcset', 'sizes'] as $attr) {
                $img->removeAttribute($attr);
            }
        }
        foreach ($xp->query('//a[contains(@class, "admin-login-link")]') as $a) {
            $a->removeAttribute('rel');
        }
        // Mudança intencional (Quem Somos, #691): item novo nos menus.
        foreach (iterator_to_array($xp->query('//li[a[contains(@href, "/pagina/quem-somos")]]')) as $no) {
            $no->parentNode->removeChild($no);
        }
        foreach ($xp->query('//footer//i[@aria-hidden]') as $no) {
            $no->removeAttribute('aria-hidden');
        }
        foreach ($xp->query('//text()') as $texto) {
            $limpo = preg_replace('/\s+/u', ' ', $texto->nodeValue);
            if (trim($limpo) === '') {
                $texto->parentNode->removeChild($texto);
            } else {
                $texto->nodeValue = $limpo;
            }
        }
        $corpo = $doc->getElementsByTagName('body')->item(0);
        $saida = '';
        foreach ($corpo->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }
        // Espaço colado a uma tag de abertura/fechamento não muda o que o visitante vê.
        $saida = preg_replace(['/(<[a-z][^>]*>) /i', '/ (<\/[a-z]+>)/i'], ['$1', '$1'], $saida);

        // Espaço sobrando dentro de class="..." não tem efeito.
        $saida = preg_replace('/class="\s*([^"]*?)\s*"/', 'class="$1"', $saida);

        // Âncora da própria página: `#contato` e `<base_url>#contato` levam ao mesmo lugar.
        $saida = str_replace('href="' . rtrim(base_url(), '/') . '/#', 'href="#', $saida);

        // Mudança intencional: os arquivos visuais agora ficam na pasta de assets do template.
        $saida = str_replace('public/templates/classico/assets/', 'public/assets/', $saida);

        // Endereços dependem do ambiente (baseURL, frontURL); a fixture guarda marcadores.
        return str_replace([config('App')->frontURL, rtrim(base_url(), '/')], ['{FRONT}', '{BASE}'], $saida);
    }

    private function assertParidade(string $esperado, string $obtido, string $nome): void
    {
        $a = $esperado; // fixture: já normalizada
        $b = $this->normaliza($obtido);
        if ($a !== $b) {
            $i = 0;
            $max = min(strlen($a), strlen($b));
            while ($i < $max && $a[$i] === $b[$i]) {
                $i++;
            }
            $this->fail("{$nome}: HTML diverge na posição {$i}\n--- legado: ..." . substr($a, max(0, $i - 120), 300) . "\n--- novo:   ..." . substr($b, max(0, $i - 120), 300));
        }
        $this->assertSame($a, $b);
    }

    /**
     * As fixtures em tests/_support/fixtures/site-legado são o corpo das views
     * antigas (site/home.php e site/pagina_*.php, removidas nesta issue),
     * normalizado e gerado com os mesmos dados de teste. O site novo tem de
     * produzir a mesma estrutura e o mesmo texto.
     */
    private function legado(string $slug): string
    {
        return file_get_contents(ROOTPATH . "tests/_support/fixtures/site-legado/{$slug}.html");
    }

    public function testHomeIgualAoSiteLegado(): void
    {
        $this->assertParidade($this->legado('inicio'), $this->renderNovo('inicio'), 'home');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("areas")]
    public function testPaginasDeAreaIguaisAoSiteLegado(string $slug): void
    {
        $this->assertParidade($this->legado($slug), $this->renderNovo($slug), $slug);
    }

    public static function areas(): array
    {
        return [
            'civil'         => ['direito-civil'],
            'administrativo'=> ['direito-administrativo'],
            'contratos'     => ['contratos-negocios'],
            'colaborativa'  => ['advocacia-colaborativa'],
        ];
    }

    public function testSeoDaHomeEDasAreas(): void
    {
        $home = $this->renderNovo('inicio');

        $this->assertStringContainsString('<title>Cardoso &amp; Bruno Sociedade de Advogados | Excelência Jurídica</title>', $home);
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url() . '">', $home);
        $this->assertStringContainsString('<meta name="keywords" content="advogado belo horizonte,', $home);
        $this->assertStringContainsString('<meta name="description" content="Cardoso &amp; Bruno Sociedade de Advogados: Direito Civil,', $home);
        $this->assertStringContainsString('<script async src="https://www.googletagmanager.com/gtag/js?id=G-X56XSQ5ZB6">', $home);

        $civil = $this->renderNovo('direito-civil');
        $this->assertStringContainsString('<title>Direito Civil | Cardoso &amp; Bruno Sociedade de Advogados</title>', $civil, 'meta_title vale como está, sem sufixo duplicado');
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('pagina/direito-civil') . '">', $civil);
    }

    public function testGa4SoGravaCookiesDepoisDoConsentimento(): void
    {
        $home = $this->renderNovo('inicio');

        $padrao = strpos($home, "gtag('consent', 'default'");
        $config = strpos($home, "gtag('config', \"G-X56XSQ5ZB6\")");
        $this->assertNotFalse($padrao, 'Consent Mode v2 configurado');
        $this->assertNotFalse($config);
        $this->assertLessThan($config, $padrao, 'consentimento negado antes do config');
        $this->assertMatchesRegularExpression("/'default', \\{\\s*analytics_storage: 'denied',\\s*ad_storage: 'denied',\\s*ad_user_data: 'denied',\\s*ad_personalization: 'denied'/", $home);
        $this->assertStringContainsString('id="cookie-banner"', $home);
        $this->assertStringContainsString('data-cookies="recusado"', $home);
        $this->assertStringContainsString('data-cookies="aceito"', $home);
        $this->assertStringContainsString('data-cookies-abrir', $home, 'rodapé reabre as preferências');
    }

    public function testSemGoogleAnalyticsNaoHaAvisoDeCookies(): void
    {
        $this->instalador()->instalarCore('classico');
        $this->db->table('site_configuracoes')->whereIn('chave', ['integracoes.ga_id', 'integracoes.ads_id'])->update(['valor' => '']);
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $home = $this->renderNovo('inicio');
        $this->assertStringNotContainsString('googletagmanager', $home);
        $this->assertStringNotContainsString('cookie-banner', $home);
        $this->assertStringNotContainsString('data-cookies-abrir', $home);
    }

    public function testFormularioDeContatoPedeCienciaDaPolitica(): void
    {
        $home = $this->renderNovo('inicio');

        $this->assertMatchesRegularExpression('/<input type="checkbox" id="aceite_privacidade" name="aceite_privacidade" value="1" required/', $home);
        $this->assertStringContainsString('<a href="' . base_url('pagina/politica-de-privacidade') . '" target="_blank" rel="noopener">Política de Privacidade</a>', $home);
        $this->assertStringContainsString('class="form-privacy-note', $home);
    }

    public function testPoliticaDePrivacidadeIdentificaControladorEEncarregado(): void
    {
        $html = $this->renderNovo('politica-de-privacidade');

        $this->assertStringContainsString('<title>Política de Privacidade | Cardoso &amp; Bruno Sociedade de Advogados</title>', $html);
        $this->assertStringContainsString('<dd>Cardoso &amp; Bruno Sociedade de Advogados</dd>', $html);
        $this->assertStringContainsString('<dd>Fabiano Dias Cardoso</dd>', $html);
        $this->assertStringContainsString('href="mailto:fabiano@cardosoebruno.adv.br"', $html);
        $this->assertStringNotContainsString('<dt>CNPJ</dt>', $html, 'CNPJ vazio não aparece');
        $this->assertStringContainsString('art. 18', $html);
        $this->assertStringContainsString('href="' . base_url('pagina/politica-de-privacidade') . '">Política de Privacidade</a>', $html, 'link no rodapé');
        $this->assertStringContainsString('href="' . base_url('pagina/termos-de-uso') . '">Termos de Uso</a>', $html, 'link no rodapé');

        $this->db->table('site_configuracoes')->where('chave', 'identidade.cnpj')->update(['valor' => '12.345.678/0001-90']);
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
        $this->assertStringContainsString('<dt>CNPJ</dt>', $this->renderNovo('politica-de-privacidade'));

        $this->assertStringContainsString('não constituem consultoria', $this->renderNovo('termos-de-uso'));
    }

    public function testMigrationLgpdCriaPaginasELinksSemTocarNoConteudo(): void
    {
        require_once APPPATH . 'Database/Migrations/Site/2026-10-13-120000_PoliticaPrivacidadeLgpd.php';
        $this->instalador()->instalarCore('classico');
        // Instalação anterior à #692: sem as páginas, os links e as configurações.
        $ids = array_column($this->db->table('site_paginas')->select('id')->whereIn('slug', ['politica-de-privacidade', 'termos-de-uso'])->get()->getResultArray(), 'id');
        $this->db->table('site_menu_itens')->whereIn('pagina_id', $ids)->delete();
        $this->db->table('site_secoes')->whereIn('pagina_id', $ids)->delete();
        $this->db->table('site_paginas')->whereIn('id', $ids)->delete();
        $this->db->table('site_configuracoes')->like('chave', 'lgpd.', 'after')->delete();
        $this->db->table('site_configuracoes')->where('chave', 'identidade.cnpj')->delete();
        $this->db->table('site_menu_itens')->where('rotulo', 'Contato')->update(['rotulo' => 'Fale conosco']);

        $migration = new \App\Database\Migrations\PoliticaPrivacidadeLgpd(Database::forge($this->db));
        $migration->up();

        $this->assertSame(2, $this->db->table('site_paginas')->whereIn('slug', ['politica-de-privacidade', 'termos-de-uso'])->where('status', 'publicado')->countAllResults());
        $this->assertSame('Fabiano Dias Cardoso', $this->db->table('site_configuracoes')->where('chave', 'lgpd.encarregado_nome')->get()->getRow()->valor);
        $rodape = $this->db->table('site_menu_itens i')->select('i.rotulo')->join('site_menus m', 'm.id = i.menu_id')->where('m.local', 'rodape')->orderBy('i.ordem')->get()->getResultArray();
        $this->assertSame(['Início', 'Quem Somos', 'Áreas de Atuação', 'Notícias', 'Fale conosco', 'Política de Privacidade', 'Termos de Uso'], array_column($rodape, 'rotulo'), 'itens do escritório preservados; novos no fim');

        $total = [$this->db->table('site_paginas')->countAllResults(), $this->db->table('site_menu_itens')->countAllResults()];
        $migration->up();
        $this->assertSame($total, [$this->db->table('site_paginas')->countAllResults(), $this->db->table('site_menu_itens')->countAllResults()], 'segunda execução não muda nada');
    }

    public function testRodapeMostraRazaoSocialCnpjEOabSoQuandoPreenchidos(): void
    {
        $vazio = $this->renderNovo('inicio');
        $this->assertStringNotContainsString('CNPJ ', $vazio);
        $this->assertMatchesRegularExpression('#&copy; \d{4} Cardoso &amp; Bruno Sociedade de Advogados\. #', $vazio);

        foreach (['identidade.razao_social' => 'Cardoso & Bruno Advogados Associados', 'identidade.cnpj' => '11222333000181', 'identidade.registro_oab' => 'OAB/MG nº 1234'] as $chave => $valor) {
            $this->db->table('site_configuracoes')->where('chave', $chave)->update(['valor' => $valor]);
        }
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $html = $this->renderNovo('inicio');
        $this->assertStringContainsString('Cardoso &amp; Bruno Advogados Associados &middot; CNPJ 11.222.333/0001-81 &middot; Registro OAB/MG nº 1234. ', $html);
    }

    public function testLinksDaHomeNaoTemTextoAncoraGenerico(): void
    {
        $html = $this->renderNovo('inicio');

        $this->assertStringContainsString('Conheça nossa atuação em Direito Civil', $html);
        $this->assertStringContainsString('Ver todos os artigos jurídicos', $html);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $genericos = ['ver detalhes', 'saiba mais', 'clique aqui', 'leia mais', 'ver mais', 'ler artigo'];
        foreach ($doc->getElementsByTagName('a') as $a) {
            $texto = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $a->textContent)));
            $this->assertNotContains($texto, $genericos, 'link com texto genérico: ' . $a->getAttribute('href'));
        }
    }

    public function testJsonLdDoEscritorioSaiDasConfiguracoes(): void
    {
        $this->renderNovo('inicio');
        $html = $this->renderNovo('direito-civil');

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $g = json_decode($m[1] ?? 'null', true);
        $this->assertSame(1, substr_count($html, 'application/ld+json'), 'um único bloco por página');
        $tipos = array_column($g['@graph'], null, '@type');
        $d = $tipos['LegalService'];

        $this->assertSame(['LegalService', 'WebSite', 'WebPage', 'BreadcrumbList'], array_keys($tipos));
        $this->assertSame(['Início', 'Direito Civil'], array_column($tipos['BreadcrumbList']['itemListElement'], 'name'));
        $this->assertContains('Direito Civil', $d['knowsAbout'], 'áreas publicadas viram knowsAbout');
        $this->assertSame(['Belo Horizonte', 'Juatuba', 'Minas Gerais'], $d['areaServed']);
        $this->assertSame('Cardoso & Bruno Sociedade de Advogados', $d['name']);
        $this->assertSame('Rua Roberto Lúcio Aroeira, 417, Sala 01', $d['address']['streetAddress']);
        $this->assertSame('MG', $d['address']['addressRegion']);
        $this->assertSame('contato@cardosoebruno.adv.br', $d['email']);
    }

    public function testTemaPersonalizadoEntraNoHeadESemEleOCssFicaIntacto(): void
    {
        $padrao = $this->renderNovo('inicio');
        $this->assertStringNotContainsString('tema-do-site', $padrao);
        $this->assertStringContainsString('family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&amp;family=Outfit:wght@300;400;500;600&amp;display=swap', $padrao);

        foreach ([['tema.cores.gold', '#B08E35', 'cor'], ['tema.fontes.serif', 'Lora', 'string'], ['tema.cores.cream', 'red;} body{display:none', 'cor']] as [$chave, $valor, $tipo]) {
            $this->db->table('site_configuracoes')->insert(['chave' => $chave, 'valor' => $valor, 'tipo' => $tipo, 'grupo' => 'tema']);
        }
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $html = $this->renderNovo('inicio');

        $this->assertStringContainsString("<style id=\"tema-do-site\">:root{--gold:#B08E35;--gold-rgb:176, 142, 53;--font-serif:'Lora', serif;}</style>", $html);
        $this->assertStringContainsString('family=Lora:wght@400;700&amp;family=Outfit:wght@300;400;500;600&amp;display=swap', $html);
        $this->assertStringNotContainsString('display:none', $html, 'valor inválido gravado direto no banco não chega ao CSS');
        $this->assertLessThan(strpos($html, 'tema-do-site'), strpos($html, 'css/site.css'), 'o tema vem depois do CSS do template');
    }

    public function testSemGaIdNaoHaScriptDeAnalytics(): void
    {
        $this->instalador()->instalarCore('classico');
        $this->db->table('site_configuracoes')->whereIn('chave', ['integracoes.ga_id', 'integracoes.ads_id'])->update(['valor' => '']);
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $this->assertStringNotContainsString('googletagmanager', $this->renderNovo('inicio'));
    }

    // ------------------------------------------------------- telas especiais (#652)

    private function artigo(int $i): array
    {
        return ['title' => "Artigo {$i} & mais", 'summary' => "Resumo <{$i}>", 'slug' => "artigo-{$i}", 'published_at' => "2026-0{$i}-15 10:00:00", 'created_at' => '2026-01-01 00:00:00'];
    }

    public function testListaDeNoticiasIgualAoSiteLegado(): void
    {
        $html = $this->renderTela('noticias_lista', 'noticias', [
            'articles' => [$this->artigo(1), $this->artigo(2), $this->artigo(3)], 'searchQuery' => 'Direito Civil',
            'currentPage' => 3, 'lastPage' => 8, 'total' => 70,
        ], 'Artigos e Notícias');

        $this->assertParidade($this->legado('tela-noticias'), $html, 'noticias');
    }

    public function testListaVaziaIgualAoSiteLegado(): void
    {
        $html = $this->renderTela('noticias_lista', 'noticias', [
            'articles' => [], 'searchQuery' => '', 'currentPage' => 1, 'lastPage' => 1, 'total' => 0,
        ], 'Artigos e Notícias');

        $this->assertParidade($this->legado('tela-noticias-vazia'), $html, 'noticias vazia');
    }

    public function testArtigoIgualAoSiteLegado(): void
    {
        $dados = ['article' => $this->artigo(1) + ['category_name' => 'Civil', 'content' => '<p>Corpo <strong>do</strong> artigo</p>'], 'related' => [$this->artigo(2), $this->artigo(3)]];
        $this->assertParidade($this->legado('tela-artigo'), $this->renderTela('noticias_artigo', 'noticias', $dados, 'Artigos e Notícias', ['url' => base_url()]), 'artigo');

        $simples = ['article' => $this->artigo(4) + ['content' => '<p>x</p>'], 'related' => []];
        $this->assertParidade($this->legado('tela-artigo-simples'), $this->renderTela('noticias_artigo', 'noticias', $simples, 'Artigos e Notícias', ['url' => base_url()]), 'artigo simples');
    }

    public function testAreaEPainelDoClienteIguaisAoSiteLegado(): void
    {
        $this->assertParidade($this->legado('tela-area-cliente'), $this->renderTela('area_cliente', 'area-cliente', [], 'Área do Cliente'), 'area-cliente');
        $this->assertParidade($this->legado('tela-painel-cliente'), $this->renderTela('painel_cliente', 'painel-cliente', [], 'Painel do Cliente'), 'painel-cliente');
    }

    public function testSeoDasTelas(): void
    {
        $lista = $this->renderTela('noticias_lista', 'noticias', ['articles' => [], 'searchQuery' => '', 'currentPage' => 1, 'lastPage' => 1, 'total' => 0], 'Artigos e Notícias');
        $this->assertStringContainsString('<title>Artigos e Notícias | Cardoso &amp; Bruno</title>', $lista);
        $this->assertStringNotContainsString('noindex', $lista, 'notícias entram no sitemap, então são indexáveis');
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('noticias') . '">', $lista);

        $artigo = $this->renderTela('noticias_artigo', 'noticias', ['article' => $this->artigo(1) + ['content' => 'x'], 'related' => []], 'Artigos e Notícias',
            ['titulo' => 'Meu artigo | Cardoso & Bruno', 'descricao' => 'Resumo do artigo', 'url' => base_url('noticias/artigo-1')]);
        $this->assertStringContainsString('<title>Meu artigo | Cardoso &amp; Bruno</title>', $artigo);
        $this->assertStringContainsString('<meta name="description" content="Resumo do artigo">', $artigo);
        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('noticias/artigo-1') . '">', $artigo);
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $artigo, $m);
        $post = array_column(json_decode($m[1] ?? 'null', true)['@graph'], null, '@type')['BlogPosting'] ?? [];
        $this->assertSame('Artigo 1 & mais', $post['headline'] ?? null, 'artigo vira BlogPosting no grafo');
        $this->assertStringStartsWith('2026-01-15T10:00:00', $post['datePublished'] ?? '');

        foreach (['area_cliente' => ['area-cliente', 'Área do Cliente'], 'painel_cliente' => ['painel-cliente', 'Painel do Cliente']] as $tela => [$slug, $titulo]) {
            $html = $this->renderTela($tela, $slug, [], $titulo);
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, $tela);
            $this->assertStringContainsString("<title>{$titulo} | Cardoso &amp; Bruno</title>", str_replace('Cardoso & Bruno', 'Cardoso &amp; Bruno', $html), $tela);
        }
    }

    public function testTextosDasTelasSaoEditaveisEFiltrosVemDaConfiguracao(): void
    {
        $this->instalador()->instalarCore('classico');
        $this->db->table('site_configuracoes')->insert(['chave' => 'textos.noticias.filtro_todos', 'valor' => 'Ver tudo', 'tipo' => 'string', 'grupo' => 'textos']);
        $this->db->table('site_configuracoes')->where('chave', 'noticias.filtros')->update(['valor' => json_encode(['Tributário', 'Família & Sucessões'])]);
        $this->db->table('site_configuracoes')->insert(['chave' => 'textos.painel.js_equipe', 'valor' => 'Equipe "Jurídica"', 'tipo' => 'string', 'grupo' => 'textos']);
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $lista = $this->renderTela('noticias_lista', 'noticias', ['articles' => [], 'searchQuery' => 'Tributário', 'currentPage' => 1, 'lastPage' => 1, 'total' => 0], 'x');
        $this->assertStringContainsString('Ver tudo', $lista);
        $this->assertStringContainsString('value="Família &amp; Sucessões"', $lista);
        $this->assertStringNotContainsString('value="Administrativo"', $lista, 'filtros antigos não ficam no template');
        $this->assertMatchesRegularExpression('/class="btn-filter active"\s*>\s*Tributário/', $lista);

        $painel = $this->renderTela('painel_cliente', 'painel-cliente', [], 'x');
        $this->assertStringContainsString('Equipe \"Jurídica\"', $painel, 'texto do JavaScript sai escapado com segurança');
    }

    public function testMigrationCompletaInstalacaoAnteriorSemTocarNoConteudo(): void
    {
        require_once APPPATH . 'Database/Migrations/Site/2026-10-08-120000_CompletaConteudoSistemaClassico.php';
        $this->instalador()->instalarCore('classico');
        // Instalação feita antes da #652: sem as páginas de sistema nem os filtros.
        $this->db->table('site_paginas')->whereIn('slug', ['noticias', 'area-cliente', 'painel-cliente'])->delete();
        $this->db->table('site_configuracoes')->where('chave', 'noticias.filtros')->delete();
        $this->db->table('site_paginas')->where('slug', 'inicio')->update(['titulo' => 'Home editada']);
        $this->db->table('site_templates')->update(['versao' => '1.0.0', 'manifest' => '{}']);

        $migration = new \App\Database\Migrations\CompletaConteudoSistemaClassico(Database::forge($this->db));
        $migration->up();

        $this->assertSame(['area-cliente', 'noticias', 'painel-cliente'], array_column(
            $this->db->table('site_paginas')->select('slug')->where('tipo', 'sistema')->orderBy('slug')->get()->getResultArray(), 'slug'
        ));
        $this->assertSame(1, $this->db->table('site_configuracoes')->where('chave', 'noticias.filtros')->countAllResults());
        $this->assertSame('Home editada', $this->db->table('site_paginas')->where('slug', 'inicio')->get()->getRow()->titulo);
        $this->assertSame($this->manifesto()['versao'], $this->db->table('site_templates')->where('slug', 'classico')->get()->getRow()->versao);

        $total = $this->db->table('site_paginas')->countAllResults();
        $migration->up();
        $this->assertSame($total, $this->db->table('site_paginas')->countAllResults(), 'segunda execução não muda nada');
    }

    public function testPaginaDeSistemaNaoAbreEmPaginaSlug(): void
    {
        $render = $this->servicoRender();

        $this->assertNull($render->renderPagina('noticias'));
        $this->assertNull($render->renderPagina('area-cliente'));
    }

    public function testNenhumDadoDoEscritorioFicaNoCodigoDoTemplate(): void
    {
        $achados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH . 'Templates/classico', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arquivo) {
            $rel = str_replace(APPPATH . 'Templates/classico/', '', $arquivo->getPathname());
            // O seed e o manifesto são justamente os dados iniciais; assets visuais (css/img) podem citar a marca no cabeçalho.
            if (str_starts_with($rel, 'seed/') || str_starts_with($rel, 'assets/') || $rel === 'template.json') {
                continue;
            }
            if (preg_match('/Cardoso|Aroeira|9224|cardosoebruno|G-X56/i', file_get_contents($arquivo->getPathname()))) {
                $achados[] = $rel;
            }
        }
        $this->assertSame([], $achados);
    }
}
