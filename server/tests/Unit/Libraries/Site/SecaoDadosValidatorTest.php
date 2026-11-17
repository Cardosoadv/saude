<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\Template\Compatibilidade;
use App\Libraries\Site\Template\SecaoDadosValidator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\SiteTemplate;

class SecaoDadosValidatorTest extends CIUnitTestCase
{
    private SecaoDadosValidator $validator;
    private array $secoes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SecaoDadosValidator(new SiteTemplate());
        $this->secoes = json_decode(file_get_contents(APPPATH . 'Templates/classico/template.json'), true)['secoes'];
    }

    private function hero(): array
    {
        return $this->secoes['hero']['campos'];
    }

    public function testDadosValidosPassam(): void
    {
        $r = $this->validator->validar($this->hero(), [
            'titulo' => 'Defesa <em>Estratégica</em>',
            'subtitulo' => "Linha 1\nLinha 2",
            'botoes' => [
                ['rotulo' => 'Iniciar', 'link' => ['tipo' => 'ancora', 'ancora' => 'contato'], 'estilo' => 'contorno'],
                ['rotulo' => 'Áreas', 'link' => ['tipo' => 'pagina', 'pagina_id' => 3]],
            ],
        ]);

        $this->assertSame([], $r->erros());
    }

    public function testErrosUsamCaminhoDentroDeListas(): void
    {
        $r = $this->validator->validar($this->hero(), [
            'titulo' => 'ok',
            'botoes' => [
                ['rotulo' => 'ok', 'link' => ['tipo' => 'ancora', 'ancora' => 'x']],
                ['rotulo' => '', 'link' => ['tipo' => 'url', 'url' => 'javascript:alert(1)'], 'estilo' => 'gigante'],
            ],
        ]);

        $this->assertSame(['botoes.1.rotulo', 'botoes.1.link', 'botoes.1.estilo'], array_keys($r->erros()));
    }

    public function testObrigatorioETextoInline(): void
    {
        $r = $this->validator->validar($this->hero(), ['titulo' => '']);
        $this->assertArrayHasKey('titulo', $r->erros());

        foreach (['<script>x</script>', '<em onclick="x">a</em>', 'a < b', "duas\nlinhas"] as $ruim) {
            $r = $this->validator->validar($this->hero(), ['titulo' => $ruim]);
            $this->assertArrayHasKey('titulo', $r->erros(), $ruim);
        }
    }

    public function testLimiteDeTamanhoEItens(): void
    {
        $r = $this->validator->validar($this->hero(), ['titulo' => 'ok', 'sobretitulo' => str_repeat('a', 121)]);
        $this->assertArrayHasKey('sobretitulo', $r->erros());

        $itens = array_fill(0, 101, ['rotulo' => 'a', 'link' => ['tipo' => 'ancora', 'ancora' => 'a']]);
        $r = $this->validator->validar($this->hero(), ['titulo' => 'ok', 'botoes' => $itens]);
        $this->assertArrayHasKey('botoes', $r->erros());
    }

    public function testTiposSimples(): void
    {
        $campos = [
            'img'   => ['tipo' => 'imagem'],
            'ico'   => ['tipo' => 'icone'],
            'ativo' => ['tipo' => 'bool'],
            'menu'  => ['tipo' => 'referencia_menu'],
            'url'   => ['tipo' => 'link'],
        ];
        $ok = ['img' => 7, 'ico' => 'bi-award', 'ativo' => false, 'menu' => 'lateral_areas',
               'url' => ['tipo' => 'url', 'url' => '/pagina/direito-civil']];
        $this->assertSame([], $this->validator->validar($campos, $ok)->erros());

        $ruim = ['img' => '7', 'ico' => 'fa-award', 'ativo' => 'sim', 'menu' => 'Lateral Áreas',
                 'url' => ['tipo' => 'url', 'url' => '//evil.com']];
        $this->assertSame(['img', 'ico', 'ativo', 'menu', 'url'], array_keys($this->validator->validar($campos, $ruim)->erros()));

        foreach (['https://a.com/x', 'mailto:a@b.com', 'tel:+5531999999999'] as $url) {
            $r = $this->validator->validar(['l' => ['tipo' => 'link']], ['l' => ['tipo' => 'url', 'url' => $url]]);
            $this->assertSame([], $r->erros(), $url);
        }
    }

    public function testLinkEmBrancoContaComoNaoPreenchido(): void
    {
        $campos = ['lattes' => ['tipo' => 'link'], 'botao' => ['tipo' => 'link', 'obrigatorio' => true]];
        foreach ([['tipo' => 'url', 'url' => ''], ['tipo' => 'url', 'url' => '  '], ['tipo' => 'url'], ['tipo' => 'ancora', 'ancora' => ''], ['tipo' => 'pagina', 'pagina_id' => null]] as $vazio) {
            $r = $this->validator->validar($campos, ['lattes' => $vazio, 'botao' => $vazio]);
            $this->assertSame(['botao'], array_keys($r->erros()), json_encode($vazio));
            $this->assertSame('Campo obrigatório.', $r->erros()['botao']);
        }
    }

    public function testCampoDinamicoENaoDeclaradoNaoBloqueiam(): void
    {
        $r = $this->validator->validar($this->secoes['ultimas_noticias']['campos'], [
            'titulo' => 'Artigos', 'artigos' => 'ignorado', 'velho' => 'x',
        ]);

        $this->assertSame([], $r->erros());
        $this->assertSame(['velho'], array_keys($r->avisos()));
    }

    public function testAplicarPadroesNaoSobrescreveENoTopoEEmItens(): void
    {
        $campos = $this->secoes['pilares']['campos'];
        $dados = $this->validator->aplicarPadroes($campos, ['itens' => [
            ['titulo' => 'A'],
            ['titulo' => 'B', 'icone' => 'bi-shield-check'],
        ]]);

        $this->assertSame('bi-award', $dados['itens'][0]['icone']);
        $this->assertSame('bi-shield-check', $dados['itens'][1]['icone']);

        $menu = $this->validator->aplicarPadroes($this->secoes['lateral_outras_areas']['campos'], []);
        $this->assertSame(['titulo' => 'Outras Áreas', 'menu' => 'lateral_areas'], $menu);
    }

    public function testCompatibilidadeMantemOcultaENaoApagaDados(): void
    {
        $secoes = [
            ['id' => 1, 'tipo' => 'hero', 'dados' => ['titulo' => 'Olá']],
            ['id' => 2, 'tipo' => 'carrossel_antigo', 'dados' => ['slides' => [1, 2]]],
            ['id' => 3, 'tipo' => 'lateral_outras_areas', 'dados' => []],
            ['id' => 4, 'tipo' => 'cta_box', 'dados' => ['texto' => 'x']],
        ];

        $plano = (new Compatibilidade($this->validator))->analisar($secoes, $this->secoes);
        $porId = array_column($plano, null, 'id');

        $this->assertSame('manter', $porId[1]['acao']);
        $this->assertSame([], $porId[1]['erros']);

        $this->assertSame('ocultar', $porId[2]['acao']);
        $this->assertSame(['slides' => [1, 2]], $porId[2]['dados']);

        $this->assertSame('Outras Áreas', $porId[3]['dados']['titulo'], 'padrão de campo novo aplicado');

        $this->assertSame('manter', $porId[4]['acao']);
        $this->assertSame(['botao_rotulo', 'botao_link'], array_keys($porId[4]['erros']), 'obrigatórios novos viram erro no relatório, sem apagar nada');
    }
}
