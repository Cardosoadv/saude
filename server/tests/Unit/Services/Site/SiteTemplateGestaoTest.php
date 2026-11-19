<?php

namespace Tests\Unit\Services\Site;

use App\Controllers\ApiV2\Site\admin\Templates;
use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\Template\PacoteTemplate;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Libraries\Site\Template\ZipExtrator;
use App\Services\Site\SiteTemplateGestaoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\SiteTemplate;

/**
 * Instalação, ativação, exportação e remoção de templates (issue #650).
 */
class SiteTemplateGestaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

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

        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        (new CreateSiteConteudoTables($forge))->up();
        $forge->dropTable('auditoria', true);
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true], 'user_id' => ['type' => 'INTEGER', 'null' => true],
            'table_name' => ['type' => 'VARCHAR', 'constraint' => 100], 'action_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'dados_antigos' => ['type' => 'TEXT', 'null' => true], 'dados_novos' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('auditoria');
        $forge->dropTable('settings', true);
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true], 'class' => ['type' => 'VARCHAR', 'constraint' => 255],
            'key' => ['type' => 'VARCHAR', 'constraint' => 255], 'value' => ['type' => 'TEXT', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'], 'context' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('settings');

        $this->tmp = WRITEPATH . 'gestao_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->tmp . 'envios', 0777, true);
        $this->config = new SiteTemplate();
        $this->config->diretorioAssets = $this->tmp . 'assets';
        $this->config->diretorios['upload'] = $this->tmp . 'templates';
        $this->config->diretorios['cli'] = $this->tmp . 'templates';
        (new TemplateInstaller(assets: new TemplateAssets($this->config)))->instalarCore('classico');
        $this->limpaCache();
    }

    protected function tearDown(): void
    {
        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        $forge->dropTable('auditoria', true);
        $forge->dropTable('settings', true);
        $this->apaga($this->tmp);
        $this->resetServices(); // descarta o 'auth' simulado, que vazaria para os outros testes
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

    private function servico(?ZipExtrator $zip = null): SiteTemplateGestaoService
    {
        return new SiteTemplateGestaoService(zip: $zip, config: $this->config);
    }

    // ------------------------------------------------------------ pacotes de teste

    private function manifesto(array $extra = []): array
    {
        return array_replace([
            'slug' => 'moderno', 'nome' => 'Moderno', 'versao' => '1.0.0', 'autor' => 'Teste', 'motor' => 'twig',
            'layouts' => ['main'], 'menus' => ['cabecalho'], 'paginas_obrigatorias' => ['home'],
            'textos' => ['rodape.copy' => 'Direitos reservados'],
            'secoes' => [
                'faixa' => ['rotulo' => 'Faixa', 'campos' => ['titulo' => ['tipo' => 'texto', 'obrigatorio' => true], 'estilo' => ['tipo' => 'opcao', 'opcoes' => ['claro', 'escuro'], 'padrao' => 'claro']]],
                'rich_text' => ['rotulo' => 'Texto', 'campos' => ['corpo' => ['tipo' => 'rich_text']]],
            ],
        ], $extra);
    }

    /** @return array<string, string> arquivos de um pacote válido */
    private function arquivos(array $manifesto = []): array
    {
        $m = $this->manifesto($manifesto);

        return [
            'template.json'           => json_encode($m),
            'layouts/main.twig'       => '<html><title>{{ seo.titulo }}</title><body>{{ conteudo|raw }}<i>{{ site_texto("rodape.copy") }}</i></body></html>',
            'sections/faixa.twig'     => '<h2 class="{{ dados.estilo }}">{{ dados.titulo }}</h2>',
            'sections/rich_text.twig' => '<div>{{ dados.corpo|raw }}</div>',
            'assets/css/app.css'      => 'body{color:#333}',
            'preview.png'             => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            'seed/content.json'       => json_encode([
                'configuracoes' => ['contato.telefone' => ['valor' => '000', 'tipo' => 'string']],
                'paginas' => [
                    ['slug' => 'inicio-moderno', 'titulo' => 'Início', 'tipo' => 'home', 'layout' => 'main', 'status' => 'publicado', 'secoes' => [['tipo' => 'faixa', 'dados' => ['titulo' => 'Olá']]]],
                    ['slug' => 'sobre-moderno', 'titulo' => 'Sobre', 'tipo' => 'pagina', 'layout' => 'main', 'status' => 'publicado', 'secoes' => []],
                ],
                'menus' => ['cabecalho' => [['rotulo' => 'Início', 'tipo' => 'pagina', 'pagina' => 'inicio-moderno']]],
            ]),
        ];
    }

    /** Cria um .zip com os arquivos; `$topo` é a pasta única de nível superior, se houver. */
    private function zip(array $arquivos, string $topo = '', ?callable $ajuste = null): string
    {
        $caminho = $this->tmp . 'envios/pacote-' . bin2hex(random_bytes(3)) . '.zip';
        $z = new \ZipArchive();
        $z->open($caminho, \ZipArchive::CREATE);
        foreach ($arquivos as $nome => $conteudo) {
            $z->addFromString($topo . $nome, $conteudo);
        }
        if ($ajuste !== null) {
            $ajuste($z);
        }
        $z->close();

        return $caminho;
    }

    private function recusa(callable $acao): ErroDeValidacao
    {
        try {
            $acao();
        } catch (ErroDeValidacao $e) {
            return $e;
        }
        $this->fail('Esperava o pacote ser recusado.');
    }

    private function semResiduos(): void
    {
        $this->assertSame(0, $this->db->table('site_templates')->where('slug', 'moderno')->countAllResults(), 'nada foi registrado');
        $base = $this->config->diretorios['upload'];
        $sobras = is_dir($base) ? array_diff(scandir($base), ['.', '..']) : [];
        $this->assertSame([], array_values($sobras), 'nenhuma pasta (nem temporária) foi deixada');
    }

    // ------------------------------------------------------------ instalar

    public function testInstalaZipComPastaUnicaRegistraPublicaAssetsENaoAtiva(): void
    {
        $r = $this->servico()->instalarZip($this->zip($this->arquivos(), 'moderno-v1/'));

        $this->assertFalse($r['atualizado']);
        $this->assertSame(['slug' => 'moderno', 'nome' => 'Moderno', 'versao' => '1.0.0', 'origem' => 'upload', 'ativo' => false], $r['template']);
        $this->assertFileExists($this->config->diretorios['upload'] . '/moderno/template.json');
        $this->assertFileExists($this->config->diretorioAssets . '/moderno/assets/css/app.css');
        $this->assertFileExists($this->config->diretorioAssets . '/moderno/preview.png');
        $this->assertSame('classico', $this->db->table('site_templates')->where('ativo', 1)->get()->getRow()->slug, 'instalar não ativa');
        $this->assertContains('moderno', array_column($this->servico()->listar(), 'slug'));
        $this->assertNotNull(array_column($this->servico()->listar(), 'preview_url', 'slug')['moderno']);

        $avisos = implode("\n", $r['avisos']);
        $this->assertStringContainsString('pages/area_cliente.twig', $avisos);
    }

    public function testListarRepublicaPreviewAusenteDaPastaPublica(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos(), 'moderno-v1/'));
        unlink($this->config->diretorioAssets . '/moderno/preview.png');

        $url = array_column($this->servico()->listar(), 'preview_url', 'slug')['moderno'];

        $this->assertNotNull($url);
        $this->assertStringEndsWith('/moderno/preview.png', $url);
        $this->assertFileExists($this->config->diretorioAssets . '/moderno/preview.png');
    }

    public function testPacoteSemPastaUnicaTambemFunciona(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));

        $this->assertFileExists($this->config->diretorios['upload'] . '/moderno/layouts/main.twig');
    }

    public static function pacotesRecusados(): array
    {
        return [
            'view PHP em layouts'         => [['layouts/main.php' => '<?php echo 1;'], 'layouts/main.php'],
            'PHP com extensão dupla'      => [['assets/x.php.png' => 'GIF89a'], 'assets/x.php.png'],
            'phtml'                       => [['sections/a.phtml' => 'x'], 'sections/a.phtml'],
            '.htaccess'                   => [['assets/.htaccess' => 'php_flag engine on'], 'assets/.htaccess'],
            'SVG em assets'               => [['assets/logo.svg' => '<svg/>'], 'assets/logo.svg'],
            'HTML em assets'              => [['assets/a.html' => '<b>x</b>'], 'assets/a.html'],
            'executável'                  => [['assets/a.exe' => 'MZ'], 'assets/a.exe'],
            'twig com <?php'              => [['sections/faixa.twig' => '<?php system($_GET["c"]); ?>{{ dados.titulo }}'], 'sections/faixa.twig'],
            'js com <?php'                => [['assets/app.js' => "var x = '<?php echo 1;'"], 'assets/app.js'],
            'pasta desconhecida'          => [['bin/run.sh' => 'echo'], 'bin/run.sh'],
            'arquivo solto na raiz'       => [['index.php' => 'x'], 'index.php'],
            'nome com espaço'             => [['assets/css/meu arquivo.css' => 'a{}'], 'assets/css/meu arquivo.css'],
            'twig: tag proibida'          => [['sections/faixa.twig' => '{% macro m() %}x{% endmacro %}'], 'sections/faixa.twig'],
            'twig: função proibida'       => [['sections/faixa.twig' => '{{ source("template.json") }}'], 'sections/faixa.twig'],
            'twig: constant'              => [['sections/faixa.twig' => '{{ constant("PHP_VERSION") }}'], 'sections/faixa.twig'],
            'twig: filtro proibido'       => [['sections/faixa.twig' => '{{ [2,1]|sort|join(",") }}'], 'sections/faixa.twig'],
            'twig: erro de sintaxe'       => [['sections/faixa.twig' => '{% if %}'], 'sections/faixa.twig'],
            'twig: include fora do pacote' => [['sections/faixa.twig' => '{% include "../../etc/passwd" %}'], 'sections/faixa.twig'],
            'layout declarado e ausente'  => [['layouts/main.twig' => null], 'layouts/main.twig'],
            'seed com seção inválida'     => [['seed/content.json' => json_encode(['paginas' => [['slug' => 'a', 'titulo' => 'A', 'tipo' => 'home', 'secoes' => [['tipo' => 'faixa', 'dados' => ['titulo' => '']]]]]])], 'seed/content.json'],
            'seed com tipo inexistente'   => [['seed/content.json' => json_encode(['paginas' => [['slug' => 'a', 'titulo' => 'A', 'tipo' => 'home', 'secoes' => [['tipo' => 'carrossel', 'dados' => []]]]]])], 'seed/content.json'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pacotesRecusados')]
    public function testRecusaPacoteInseguroOuInvalidoSemDeixarResiduos(array $alteracoes, string $caminhoEsperado): void
    {
        $arquivos = array_filter(array_replace($this->arquivos(), $alteracoes), static fn ($v) => $v !== null);

        $e = $this->recusa(fn () => $this->servico()->instalarZip($this->zip($arquivos)));

        $chaves = implode(' | ', array_keys($e->erros()));
        $this->assertStringContainsString($caminhoEsperado, $chaves);
        $this->semResiduos();
    }

    public function testManifestoComMotorPhpERecusadoNoUploadMasAceitoPelaLinhaDeComando(): void
    {
        $php = $this->arquivos(['motor' => 'php']) + [];
        unset($php['layouts/main.twig'], $php['sections/faixa.twig'], $php['sections/rich_text.twig']);
        $php += ['layouts/main.php' => '<html><?= $conteudo ?></html>', 'sections/faixa.php' => '<h2><?= esc($dados["titulo"]) ?></h2>'];

        $e = $this->recusa(fn () => $this->servico()->instalarZip($this->zip($php)));
        $this->assertArrayHasKey('template.json:motor', $e->erros());
        $this->assertArrayHasKey('layouts/main.php', $e->erros());
        $this->semResiduos();

        $r = $this->servico()->instalarZip($this->zip($php), 'cli');
        $this->assertSame('cli', $r['template']['origem']);
        $this->assertFileExists($this->config->diretorios['cli'] . '/moderno/layouts/main.php');
    }

    public function testInstalarPastaDaLinhaDeComandoCopiaSemApagarAOrigem(): void
    {
        $pasta = $this->tmp . 'envios/origem/';
        foreach ($this->arquivos() as $nome => $conteudo) {
            @mkdir(dirname($pasta . $nome), 0777, true);
            file_put_contents($pasta . $nome, $conteudo);
        }

        $r = $this->servico()->instalarPasta($pasta);

        $this->assertSame('cli', $r['template']['origem']);
        $this->assertFileExists($pasta . 'template.json', 'a pasta de origem continua lá');
        $this->assertFileExists($this->config->diretorios['cli'] . '/moderno/template.json');
    }

    public function testZipMaliciosoEmCamadaDeExtracao(): void
    {
        $base = $this->arquivos();
        $casos = [
            'zip slip'          => fn (\ZipArchive $z) => $z->addFromString('../fora.txt', 'x'),
            'caminho absoluto'  => fn (\ZipArchive $z) => $z->addFromString('/etc/cron.d/x', 'x'),
            'barra invertida'   => fn (\ZipArchive $z) => $z->addFromString('assets\\..\\x.css', 'x'),
            'link simbólico'    => function (\ZipArchive $z) {
                $z->addFromString('assets/link.css', '/etc/passwd');
                $z->setExternalAttributesName('assets/link.css', \ZipArchive::OPSYS_UNIX, 0120777 << 16);
            },
        ];
        foreach ($casos as $nome => $ajuste) {
            $e = $this->recusa(fn () => $this->servico()->instalarZip($this->zip($base, '', $ajuste)));
            $this->assertNotSame([], $e->erros(), $nome);
        }
        $this->assertFileDoesNotExist($this->tmp . 'fora.txt');
        $this->semResiduos();
    }

    public function testLimitesDoExtratorEZipInvalido(): void
    {
        $e = $this->recusa(fn () => $this->servico(new ZipExtrator(maxArquivos: 3))->instalarZip($this->zip($this->arquivos())));
        $this->assertArrayHasKey('zip', $e->erros());

        $e = $this->recusa(fn () => $this->servico(new ZipExtrator(maxBytesArquivo: 50))->instalarZip($this->zip($this->arquivos())));
        $this->assertNotSame([], $e->erros());

        $falso = $this->tmp . 'envios/falso.zip';
        file_put_contents($falso, 'isto não é um zip');
        $this->assertArrayHasKey('zip', $this->recusa(fn () => $this->servico()->instalarZip($falso))->erros());

        $vazio = $this->tmp . 'envios/vazio.zip';
        (new \ZipArchive())->open($vazio, \ZipArchive::CREATE);
        file_put_contents($vazio, "PK\x05\x06" . str_repeat("\0", 18));
        $this->assertArrayHasKey('zip', $this->recusa(fn () => $this->servico()->instalarZip($vazio))->erros());
        $this->semResiduos();
    }

    public function testSlugDeTemplateDoSistemaNaoPodeSerSobrescrito(): void
    {
        $e = $this->recusa(fn () => $this->servico()->instalarZip($this->zip($this->arquivos(['slug' => 'classico']))));

        $this->assertArrayHasKey('slug', $e->erros());
        $this->assertSame(json_decode(file_get_contents(APPPATH . 'Templates/classico/template.json'), true)['versao'], $this->db->table('site_templates')->where('slug', 'classico')->get()->getRow()->versao, 'o Clássico continua intacto');
    }

    public function testAtualizarTemplateInstaladoSubstituiArquivosEPreservaAtivo(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos() + ['assets/css/velho.css' => 'a{}']));
        $this->servico()->ativar('moderno');

        $novo = $this->arquivos(['versao' => '1.1.0']);
        $r = $this->servico()->instalarZip($this->zip($novo));

        $this->assertTrue($r['atualizado']);
        $this->assertTrue($r['template']['ativo'], 'continua ativo');
        $this->assertFileDoesNotExist($this->config->diretorios['upload'] . '/moderno/assets/css/velho.css');
        $this->assertFileDoesNotExist($this->config->diretorioAssets . '/moderno/assets/css/velho.css');

        $r = $this->servico()->instalarZip($this->zip($this->arquivos(['versao' => '1.0.5'])));
        $this->assertStringContainsString('mais antiga', implode(' ', $r['avisos']));
    }

    // ------------------------------------------------------------ ativar

    public function testAtivarSiteVazioImportaOConteudoInicial(): void
    {
        $this->db->table('site_paginas')->truncate();
        $this->db->table('site_configuracoes')->truncate();
        $this->servico()->instalarZip($this->zip($this->arquivos()));

        $r = $this->servico()->ativar('moderno');

        $this->assertTrue($r['ativado']);
        $this->assertSame(['inicio-moderno', 'sobre-moderno'], $r['seed']['paginas']);
        $this->assertSame('moderno', $this->db->table('site_templates')->where('ativo', 1)->get()->getRow()->slug);
        $this->assertSame(1, $this->db->table('site_templates')->where('ativo', 1)->countAllResults());
    }

    public function testAtivarSiteComConteudoNaoSobrescreveNemApagaNada(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $antes = [
            'paginas' => $this->db->table('site_paginas')->countAllResults(),
            'secoes' => $this->db->table('site_secoes')->countAllResults(),
            'itens' => $this->db->table('site_menu_itens')->countAllResults(),
            'config' => $this->db->table('site_configuracoes')->countAllResults(),
        ];
        $home = $this->db->table('site_secoes')->where('tipo', 'hero')->get()->getRow();

        $r = $this->servico()->ativar('moderno');

        $this->assertNull($r['seed'], 'site com conteúdo não recebe o seed');
        $this->assertSame($antes, [
            'paginas' => $this->db->table('site_paginas')->countAllResults(),
            'secoes' => $this->db->table('site_secoes')->countAllResults(),
            'itens' => $this->db->table('site_menu_itens')->countAllResults(),
            'config' => $this->db->table('site_configuracoes')->countAllResults(),
        ]);
        $this->assertSame($home->dados, $this->db->table('site_secoes')->where('id', $home->id)->get()->getRow()->dados, 'dados intactos');
        $this->assertSame(1, $this->db->table('site_secoes')->where('id', $home->id)->get()->getRow()->visivel, 'não oculta nada');

        $tipos = array_column($r['secoes'], 'acao', 'tipo');
        $this->assertSame('incompativel', $tipos['hero']);
        $this->assertSame('incompativel', $tipos['contato']);
        $this->assertArrayNotHasKey('rich_text', $tipos, 'compatível e válida não entra no relatório');
        $this->assertStringContainsString("layout 'area'", implode(' ', $r['avisos']), 'páginas com layout que o novo template não tem');
    }

    public function testAtivarAplicaPadroesDeCamposNovosEReportaSecaoQueFicouInvalida(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $pagina = (int) $this->db->table('site_paginas')->where('slug', 'consultas')->get()->getRow()->id;
        $this->db->table('site_secoes')->insert(['pagina_id' => $pagina, 'tipo' => 'faixa', 'ordem' => 50, 'dados' => json_encode(['titulo' => 'Ok'])]);
        $this->db->table('site_secoes')->insert(['pagina_id' => $pagina, 'tipo' => 'faixa', 'ordem' => 51, 'dados' => json_encode(['subtitulo' => 'sem título'])]);
        $ids = array_column($this->db->table('site_secoes')->where('tipo', 'faixa')->get()->getResultArray(), 'id');

        $r = $this->servico()->ativar('moderno');

        $dados = json_decode($this->db->table('site_secoes')->where('id', $ids[0])->get()->getRow()->dados, true);
        $this->assertSame('claro', $dados['estilo'], 'padrão de campo novo aplicado');
        $this->assertSame('Ok', $dados['titulo']);
        $revisar = array_values(array_filter($r['secoes'], fn ($s) => $s['acao'] === 'revisar'));
        $this->assertCount(1, $revisar);
        $this->assertSame((int) $ids[1], (int) $revisar[0]['id']);
        $this->assertArrayHasKey('titulo', $revisar[0]['erros']);
        $invalida = json_decode($this->db->table('site_secoes')->where('id', $ids[1])->get()->getRow()->dados, true);
        $this->assertSame('sem título', $invalida['subtitulo'], 'nada do que a clínica escreveu é apagado, só reportado');
    }

    public function testVoltarAoClassicoRestauraAExibicaoDasSecoes(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $this->servico()->ativar('moderno');
        $r = $this->servico()->ativar('classico');

        $this->assertSame([], array_filter($r['secoes'], fn ($s) => $s['acao'] === 'incompativel'), 'tudo é compatível de novo');
        $this->assertSame('classico', $this->db->table('site_templates')->where('ativo', 1)->get()->getRow()->slug);
    }

    public function testAtivarSlugInexistente(): void
    {
        $this->expectException(NaoEncontrado::class);
        $this->servico()->ativar('nao-existe');
    }

    // ------------------------------------------------------------ páginas de exemplo

    public function testPaginasDeExemploCriamSoOQueNaoExisteComoRascunho(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $this->db->table('site_paginas')->insert(['slug' => 'sobre-moderno', 'titulo' => 'Minha página Sobre', 'tipo' => 'pagina', 'status' => 'publicado']);
        $telefone = $this->db->table('site_configuracoes')->where('chave', 'contato.telefone')->get()->getRow()->valor;
        $itens = $this->db->table('site_menu_itens')->countAllResults();

        $r = $this->servico()->paginasExemplo('moderno');

        $this->assertSame(['inicio-moderno'], $r['paginas']);
        $this->assertSame(['sobre-moderno'], $r['paginas_ignoradas']);
        $this->assertSame('rascunho', $this->db->table('site_paginas')->where('slug', 'inicio-moderno')->get()->getRow()->status);
        $this->assertSame('Minha página Sobre', $this->db->table('site_paginas')->where('slug', 'sobre-moderno')->get()->getRow()->titulo);
        $this->assertSame($telefone, $this->db->table('site_configuracoes')->where('chave', 'contato.telefone')->get()->getRow()->valor, 'configurações não mudam');
        $this->assertSame($itens, $this->db->table('site_menu_itens')->countAllResults(), 'menus não mudam');

        $sem = $this->arquivos();
        unset($sem['seed/content.json']);
        $this->servico()->instalarZip($this->zip($sem + [], ''));
        $this->assertArrayHasKey('template', $this->recusa(fn () => $this->servico()->paginasExemplo('moderno'))->erros());
    }

    public function testPreviaDasPaginasDeExemploListaOQueSeriaCriadoSemCriar(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $this->db->table('site_paginas')->insert(['slug' => 'sobre-moderno', 'titulo' => 'Minha página Sobre', 'tipo' => 'pagina', 'status' => 'publicado']);
        $antes = $this->db->table('site_paginas')->countAllResults();

        $previa = $this->servico()->previaPaginasExemplo('moderno');

        $this->assertSame(['inicio-moderno'], array_column($previa['criar'], 'slug'));
        $this->assertSame(['sobre-moderno'], $previa['ignoradas']);
        $this->assertArrayHasKey('titulo', $previa['criar'][0]);
        $this->assertSame($antes, $this->db->table('site_paginas')->countAllResults(), 'a prévia não grava nada');

        // a prévia coincide com o que a criação faz de fato
        $this->assertSame(array_column($previa['criar'], 'slug'), $this->servico()->paginasExemplo('moderno')['paginas']);
    }

    public function testPreviaDasPaginasDeExemploRecusaTemplateSemConteudoOuInexistente(): void
    {
        $sem = $this->arquivos();
        unset($sem['seed/content.json']);
        $this->servico()->instalarZip($this->zip($sem));

        $this->assertArrayHasKey('template', $this->recusa(fn () => $this->servico()->previaPaginasExemplo('moderno'))->erros());
        $this->expectException(NaoEncontrado::class);
        $this->servico()->previaPaginasExemplo('nao-existe');
    }

    // ------------------------------------------------------------ remover

    public function testRemoverApagaArquivosAssetsERegistroMasNaoOAtivoNemOCore(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));

        $this->servico()->ativar('moderno');
        $this->assertArrayHasKey('template', $this->recusa(fn () => $this->servico()->remover('moderno'))->erros(), 'ativo');
        $this->assertArrayHasKey('template', $this->recusa(fn () => $this->servico()->remover('classico'))->erros(), 'do sistema');

        $this->servico()->ativar('classico');
        $this->servico()->remover('moderno');

        $this->assertDirectoryDoesNotExist($this->config->diretorios['upload'] . '/moderno');
        $this->assertDirectoryDoesNotExist($this->config->diretorioAssets . '/moderno');
        $this->assertSame(0, $this->db->table('site_templates')->where('slug', 'moderno')->countAllResults());
        $this->expectException(NaoEncontrado::class);
        $this->servico()->remover('moderno');
    }

    public function testInstalarAtivarEAtualizarRemoverFicamNaAuditoriaComORelatorio(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $this->servico()->ativar('moderno');
        $this->servico()->instalarZip($this->zip($this->arquivos()));
        $this->servico()->ativar('classico');
        $this->servico()->remover('moderno');

        $acoes = [];
        foreach ($this->db->table('auditoria')->where('table_name', 'site_templates')->get()->getResultArray() as $linha) {
            $dados = json_decode((string) $linha['dados_novos'], true);
            if (isset($dados['acao'])) {
                $acoes[] = [$dados['acao'], $linha['action_type'], $dados['slug']];
            }
        }

        $this->assertSame([
            ['INSTALAR_TPL', 'CREATE', 'moderno'],
            ['ATIVAR_TPL', 'UPDATE', 'moderno'],
            ['ATUALIZAR_TPL', 'UPDATE', 'moderno'],
            ['ATIVAR_TPL', 'UPDATE', 'classico'],
            ['REMOVER_TPL', 'DELETE', 'moderno'],
        ], $acoes);
    }

    // ------------------------------------------------------------ exportar

    private function listaZip(string $bytes): array
    {
        $arq = $this->tmp . 'envios/export-' . bin2hex(random_bytes(3)) . '.zip';
        file_put_contents($arq, $bytes);
        $z = new \ZipArchive();
        $z->open($arq);
        $nomes = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $nomes[$z->getNameIndex($i)] = $z->getFromIndex($i);
        }
        $z->close();

        return $nomes;
    }

    public function testExportarGeraZipComOsArquivosDoTemplateEPodeSerReinstalado(): void
    {
        $this->servico()->instalarZip($this->zip($this->arquivos()));

        $arquivos = $this->listaZip($this->servico()->exportar('moderno'));

        $this->assertArrayHasKey('moderno/template.json', $arquivos);
        $this->assertArrayHasKey('moderno/layouts/main.twig', $arquivos);
        $this->assertArrayHasKey('moderno/seed/content.json', $arquivos);

        // O que foi exportado é aceito pelo instalador (roda sobre o mesmo slug: vira atualização).
        $zip = $this->tmp . 'envios/volta.zip';
        file_put_contents($zip, $this->servico()->exportar('moderno'));
        $this->assertTrue($this->servico()->instalarZip($zip)['atualizado']);

        $this->expectException(NaoEncontrado::class);
        $this->servico()->exportar('nao-existe');
    }

    public function testExportarComConteudoLevaPaginasMenusELinksPorSlugSemImagens(): void
    {
        $this->db->table('site_configuracoes')->insert(['chave' => 'identidade.logo', 'valor' => '7', 'tipo' => 'imagem', 'grupo' => 'identidade']);

        $bytes = $this->servico()->exportar('classico', comConteudo: true);
        $seed = json_decode($this->listaZip($bytes)['classico/seed/content.json'], true);

        $this->assertContains('consultas', array_column($seed['paginas'], 'slug'));
        $home = array_values(array_filter($seed['paginas'], fn ($p) => $p['tipo'] === 'home'))[0];
        $cards = array_values(array_filter($home['secoes'], fn ($s) => $s['tipo'] === 'cards_areas'))[0];
        $this->assertSame(['tipo' => 'pagina', 'slug' => 'consultas'], $cards['dados']['itens'][0]['link'], 'link por slug, não por id');
        $this->assertSame('Início', $seed['menus']['rodape'][0]['rotulo']);
        $this->assertSame('inicio', $seed['menus']['rodape'][0]['pagina']);
        $this->assertArrayHasKey('contato.telefone', $seed['configuracoes']);
        $this->assertArrayNotHasKey('identidade.logo', $seed['configuracoes'], 'id de mídia não vale em outra instalação');

        // O pacote exportado com conteúdo continua válido, inclusive o seed gerado.
        $zip = $this->tmp . 'envios/exportado.zip';
        file_put_contents($zip, $bytes);
        $pasta = $this->tmp . 'exportado';
        $this->assertSame([], (new ZipExtrator())->extrair($zip, $pasta));
        $this->assertSame([], (new PacoteTemplate())->validar($pasta, permitePhp: true)['resultado']->erros());
    }

    // ------------------------------------------------------------ HTTP

    /** Simula (ou não) um usuário logado com a permissão site.templates. */
    private function comUsuario(bool $superadmin): void
    {
        $this->resetServices();
        if (!$superadmin) {
            return;
        }

        $usuario = new class () extends \CodeIgniter\Shield\Entities\User {
            public function can(string ...$permissoes): bool
            {
                return in_array('site.templates', $permissoes, true);
            }
        };
        // O Auth do Shield repassa loggedIn() e user() ao autenticador; aqui ele responde direto.
        $auth = new class ($usuario) extends \CodeIgniter\Shield\Auth {
            public function __construct(private object $usuario)
            {
            }

            public function setAuthenticator(?string $alias = null): static
            {
                return $this;
            }

            public function user(): ?\CodeIgniter\Shield\Entities\User
            {
                return $this->usuario;
            }

            public function __call(string $nome, array $args): mixed
            {
                return $nome === 'loggedIn' ? true : null;
            }
        };
        \Config\Services::injectMock('auth', $auth);
    }

    private function api(string $metodo, array $params = []): \CodeIgniter\Test\TestResponse
    {
        $this->response = new \CodeIgniter\HTTP\Response(config('App')); // o status de uma chamada não vaza para a seguinte

        return $this->controller(Templates::class)->execute($metodo, ...$params);
    }

    public function testApiNegaAQuemNaoESuperadmin(): void
    {
        foreach ([['index', []], ['store', []], ['ativar', ['classico']], ['paginasExemplo', ['classico']], ['exportar', ['classico']], ['destroy', ['classico']]] as [$acao, $params]) {
            $this->comUsuario(false);
            $this->assertSame(403, $this->api($acao, $params)->response()->getStatusCode(), $acao);
        }
        $this->assertSame(1, $this->db->table('site_templates')->where('slug', 'classico')->countAllResults());
    }

    public function testApiComSuperadmin(): void
    {
        $this->comUsuario(true);
        $r = $this->api('index');
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame('classico', json_decode($r->getJSON(), true)['data'][0]['slug']);

        $this->comUsuario(true);
        $this->assertSame(422, $this->api('store')->response()->getStatusCode(), 'sem arquivo');

        $this->comUsuario(true);
        $this->assertSame(404, $this->api('ativar', ['nao-existe'])->response()->getStatusCode());

        $this->comUsuario(true);
        $this->assertSame(422, $this->api('destroy', ['classico'])->response()->getStatusCode(), 'template do sistema');

        $r = $this->api('exportar', ['classico']);
        $r = $this->api('exportar', ['classico']);
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame('application/zip', $r->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('classico.zip', $r->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('PK', (string) $r->response()->getBody());

        $this->comUsuario(true);
        $r = $this->api('ativar', ['classico']);
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertTrue(json_decode($r->getJSON(), true)['data']['ativado']);
    }
}
