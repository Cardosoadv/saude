<?php

namespace Tests\Unit\Services\Site;

use App\Controllers\ApiV2\Site\admin\Midias;
use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Services\Site\SiteMidiaService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\SiteTemplate;

/**
 * Biblioteca de mídias (issue #649).
 */
class SiteMidiaTest extends CIUnitTestCase
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

        $this->tmp = WRITEPATH . 'midia_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->tmp . 'envios', 0777, true);
        $this->config = new SiteTemplate();
        $this->config->diretorioAssets  = $this->tmp . 'assets';
        $this->config->diretorioUploads = $this->tmp . 'uploads';
        (new TemplateInstaller(assets: new TemplateAssets($this->config)))->instalarCore('classico');
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
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

    private function servico(): SiteMidiaService
    {
        return new SiteMidiaService(config: $this->config);
    }

    /** Cria um arquivo de teste com o conteúdo dado e devolve o caminho. */
    private function arquivo(string $conteudo, string $nome = 'foto'): string
    {
        $caminho = $this->tmp . 'envios/' . $nome . bin2hex(random_bytes(3));
        file_put_contents($caminho, $conteudo);

        return $caminho;
    }

    private function imagem(string $tipo, int $largura = 1, int $altura = 1): string
    {
        $img = imagecreatetruecolor($largura, $altura);
        imagefill($img, 0, 0, imagecolorallocate($img, 201, 168, 76));
        ob_start();
        match ($tipo) {
            'png'  => imagepng($img),
            'jpeg' => imagejpeg($img),
            'gif'  => imagegif($img),
            'webp' => imagewebp($img),
        };

        return (string) ob_get_clean();
    }

    private function erros(callable $acao): array
    {
        try {
            $acao();
        } catch (ErroDeValidacao $e) {
            return $e->erros();
        }
        $this->fail('Esperava ErroDeValidacao.');
    }

    public function testEnviaImagemSalvaComNomeAleatorioEExtensaoDoTipoReal(): void
    {
        $m = $this->servico()->enviar($this->arquivo($this->imagem('png', 40, 20)), '../../Fachada do Escritório.JPG', '  Fachada  ');

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-f]{32}\.png$#', $m['arquivo'], 'extensão vem do conteúdo, não do nome informado');
        $this->assertFileExists($this->config->diretorioUploads . '/' . $m['arquivo']);
        $this->assertSame('Fachada do Escritório.JPG', $m['nome_original'], 'só o nome do arquivo, sem caminho');
        $this->assertSame('image/png', $m['mime']);
        $this->assertSame([40, 20], [(int) $m['largura'], (int) $m['altura']]);
        $this->assertSame('Fachada', $m['alt']);
        $this->assertSame(base_url('public/uploads/site/' . $m['arquivo']), $m['url']);
        $this->assertStringNotContainsString('Fachada', $m['arquivo']);

        $htaccess = (string) file_get_contents($this->config->diretorioUploads . '/.htaccess');
        $this->assertStringContainsString('php_flag engine off', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
    }

    public function testObterDevolveAMidiaComUrls(): void
    {
        $enviada = $this->servico()->enviar($this->arquivo($this->imagem('png', 40, 20)), 'a.png', 'Texto');

        $this->assertSame($enviada, $this->servico()->obter((int) $enviada['id']));
        $this->expectException(NaoEncontrado::class);
        $this->servico()->obter(9999);
    }

    public function testAceitaJpegGifEWebp(): void
    {
        foreach (['jpeg' => 'jpg', 'gif' => 'gif', 'webp' => 'webp'] as $tipo => $ext) {
            $m = $this->servico()->enviar($this->arquivo($this->imagem($tipo, 3, 3)), "x.{$ext}");
            $this->assertStringEndsWith(".{$ext}", $m['arquivo'], $tipo);
        }
    }

    public function testRecusaOQueNaoEImagemAceitaPeloConteudo(): void
    {
        $casos = [
            'PHP disfarçado de PNG' => ['<?php system($_GET["c"]);', 'logo.png'],
            'texto'                 => ['só texto', 'nota.jpg'],
            'SVG com script'        => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.svg'],
            'HTML'                  => ['<html><body>oi</body></html>', 'a.gif'],
            'vazio'                 => ['', 'v.png'],
            'PNG truncado'          => [substr($this->imagem('png'), 0, 20), 'cortado.png'],
        ];
        foreach ($casos as $nome => [$conteudo, $arquivo]) {
            $this->assertArrayHasKey('arquivo', $this->erros(fn () => $this->servico()->enviar($this->arquivo($conteudo), $arquivo)), $nome);
        }
        $this->assertSame(0, $this->db->table('site_midias')->countAllResults());
        $this->assertArrayHasKey('arquivo', $this->erros(fn () => $this->servico()->enviar($this->tmp . 'nao-existe', 'x.png')));
    }

    public function testLimitesDeTamanhoEDimensoes(): void
    {
        $png = $this->imagem('png', 4, 4);

        $this->config->maxUploadBytes = strlen($png) - 1;
        $this->assertArrayHasKey('arquivo', $this->erros(fn () => $this->servico()->enviar($this->arquivo($png), 'a.png')));

        $this->config->maxUploadBytes = 5 * 1048576;
        $this->config->maxLadoPx = 3;
        $this->assertArrayHasKey('arquivo', $this->erros(fn () => $this->servico()->enviar($this->arquivo($png), 'a.png')));

        $this->config->maxLadoPx = 8000;
        $this->assertArrayHasKey('alt', $this->erros(fn () => $this->servico()->enviar($this->arquivo($png), 'a.png', str_repeat('a', 256))));
    }

    public function testGeraMiniaturaSoParaImagemMaiorQueOLimite(): void
    {
        $grande = $this->servico()->enviar($this->arquivo($this->imagem('png', 1000, 500)), 'g.png');
        $pequena = $this->servico()->enviar($this->arquivo($this->imagem('png', 100, 50)), 'p.png');

        $thumb = $this->config->diretorioUploads . '/' . preg_replace('/\.png$/', '-thumb.png', $grande['arquivo']);
        $this->assertFileExists($thumb);
        $this->assertSame([480, 240], array_slice(getimagesize($thumb), 0, 2));
        $this->assertStringEndsWith('-thumb.png', $grande['miniatura_url']);
        $this->assertSame($pequena['url'], $pequena['miniatura_url'], 'sem miniatura, usa o original');
    }

    public function testListarPaginaEBusca(): void
    {
        foreach (['fachada', 'recepcao', 'equipe'] as $nome) {
            $this->servico()->enviar($this->arquivo($this->imagem('png')), "{$nome}.png", "Foto da {$nome}");
        }

        $r = $this->servico()->listar(1, 2);
        $this->assertCount(2, $r['data']);
        $this->assertSame(['page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $r['pager']);
        $this->assertSame('equipe.png', $r['data'][0]['nome_original'], 'mais recentes primeiro');
        $this->assertCount(1, $this->servico()->listar(2, 2)['data']);

        $busca = $this->servico()->listar(1, 24, 'recepcao');
        $this->assertSame(1, $busca['pager']['total']);
        $this->assertSame('recepcao.png', $busca['data'][0]['nome_original']);
        $this->assertSame(100, $this->servico()->listar(1, 5000)['pager']['per_page'], 'limite de itens por página');
    }

    public function testAtualizarSoOTextoAlternativo(): void
    {
        $m = $this->servico()->enviar($this->arquivo($this->imagem('png')), 'a.png');

        $this->assertSame('Novo', $this->servico()->atualizarAlt($m['id'], ' Novo ')['alt']);
        $this->assertNull($this->servico()->atualizarAlt($m['id'], '')['alt']);
        $this->assertArrayHasKey('alt', $this->erros(fn () => $this->servico()->atualizarAlt($m['id'], str_repeat('a', 256))));
        $this->expectException(NaoEncontrado::class);
        $this->servico()->atualizarAlt(9999, 'x');
    }

    public function testUsosEExclusaoBloqueadaEnquantoEmUso(): void
    {
        $m = $this->servico()->enviar($this->arquivo($this->imagem('png', 1000, 500)), 'logo.png');
        $id = $m['id'];
        $pagina = $this->db->table('site_paginas')->where('slug', 'direito-civil')->get()->getRow()->id;

        $this->db->table('site_configuracoes')->insert(['chave' => 'identidade.logo', 'valor' => (string) $id, 'tipo' => 'imagem', 'grupo' => 'identidade']);
        $this->db->table('site_paginas')->where('id', $pagina)->update(['og_image_id' => $id]);
        $this->db->table('site_secoes')->insert(['pagina_id' => $pagina, 'tipo' => 'tipo_antigo', 'ordem' => 99, 'dados' => json_encode(['galeria' => [['foto' => $id]]])]);
        // O número aparece em texto de uma seção comum, mas não é campo de imagem: não conta.
        $this->db->table('site_secoes')->insert(['pagina_id' => $pagina, 'tipo' => 'rich_text', 'ordem' => 100, 'dados' => json_encode(['titulo' => (string) $id, 'corpo' => "Sala {$id}"])]);

        $usos = $this->servico()->usos($id);
        $this->assertEqualsCanonicalizing(['configuracao', 'pagina', 'secao'], array_column($usos, 'tipo'));

        $erros = $this->erros(fn () => $this->servico()->excluir($id));
        $this->assertStringContainsString('identidade.logo', $erros['midia']);
        $this->assertStringContainsString('Direito Civil', $erros['midia']);
        $this->assertFileExists($this->config->diretorioUploads . '/' . $m['arquivo']);

        $this->db->table('site_configuracoes')->where('chave', 'identidade.logo')->delete();
        $this->db->table('site_paginas')->where('id', $pagina)->update(['og_image_id' => null]);
        $this->db->table('site_secoes')->where('tipo', 'tipo_antigo')->delete();
        $this->assertSame([], $this->servico()->usos($id));

        $thumb = $this->config->diretorioUploads . '/' . preg_replace('/\.png$/', '-thumb.png', $m['arquivo']);
        $this->assertFileExists($thumb);
        $this->servico()->excluir($id);
        $this->assertFileDoesNotExist($this->config->diretorioUploads . '/' . $m['arquivo']);
        $this->assertFileDoesNotExist($thumb);
        $this->assertSame(0, $this->db->table('site_midias')->countAllResults());
    }

    public function testApiRespondeStatusEsperados(): void
    {
        $this->resetServices();
        $r = $this->controller(Midias::class)->execute('store');
        $this->assertSame(422, $r->response()->getStatusCode(), 'sem arquivo');

        $this->resetServices();
        $this->assertSame(200, $this->controller(Midias::class)->execute('index')->response()->getStatusCode());

        $this->resetServices();
        $this->assertSame(404, $this->controller(Midias::class)->execute('destroy', 9999)->response()->getStatusCode());

        $this->resetServices();
        $this->assertSame(404, $this->controller(Midias::class)->execute('usos', 9999)->response()->getStatusCode());

        $this->resetServices();
        $this->assertSame(404, $this->controller(Midias::class)->execute('show', 9999)->response()->getStatusCode());
    }
}
