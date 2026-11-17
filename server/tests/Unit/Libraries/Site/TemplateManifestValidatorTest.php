<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\Template\ManifestValidator;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Config\SiteTemplate;

class TemplateManifestValidatorTest extends CIUnitTestCase
{
    private ManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ManifestValidator(new SiteTemplate());
    }

    private function base(): array
    {
        return json_decode(file_get_contents(APPPATH . 'Templates/classico/template.json'), true);
    }

    public function testManifestoDoTemplateClassicoEValido(): void
    {
        $r = $this->validator->validar($this->base());

        $this->assertSame([], $r->erros());
        $this->assertSame([], $r->avisos());
    }

    public function testSchemaJsonEManifestoDeExemploEstaoEmSincronia(): void
    {
        $schema = json_decode(file_get_contents(ROOTPATH . 'docs/site-template.schema.json'), true);
        $m = $this->base();

        foreach ($schema['required'] as $campo) {
            $this->assertArrayHasKey($campo, $m, "schema exige {$campo}");
        }
        $tiposDoSchema = $schema['$defs']['campo']['properties']['tipo']['enum'];
        $this->assertEqualsCanonicalizing((new SiteTemplate())->tiposCampo, $tiposDoSchema);
        $this->assertEqualsCanonicalizing((new SiteTemplate())->motores, $schema['properties']['motor']['enum']);
    }

    #[DataProvider('casosInvalidos')]
    public function testRejeitaManifestoInvalido(callable $mutacao, string $caminho): void
    {
        $m = $this->base();
        $mutacao($m);

        $r = $this->validator->validar($m);

        $this->assertFalse($r->valido());
        $this->assertArrayHasKey($caminho, $r->erros());
    }

    public static function casosInvalidos(): array
    {
        return [
            'slug com maiúscula'      => [function (&$m) { $m['slug'] = 'Classico'; }, 'slug'],
            'sem nome'                => [function (&$m) { unset($m['nome']); }, 'nome'],
            'versão fora de semver'   => [function (&$m) { $m['versao'] = '1.0'; }, 'versao'],
            'motor desconhecido'      => [function (&$m) { $m['motor'] = 'blade'; }, 'motor'],
            'sistema malformado'    => [function (&$m) { $m['sistema'] = '^6.0'; }, 'sistema'],
            'sistema mais novo'     => [function (&$m) { $m['sistema'] = '>=99.0.0'; }, 'sistema'],
            'sem layouts'             => [function (&$m) { $m['layouts'] = []; }, 'layouts'],
            'cor inválida'            => [function (&$m) { $m['tema']['cores']['gold'] = 'dourado'; }, 'tema.cores.gold'],
            'fonte com símbolos'      => [function (&$m) { $m['tema']['fontes']['sans'] = 'x;}body{'; }, 'tema.fontes.sans'],
            'sem home obrigatória'    => [function (&$m) { $m['paginas_obrigatorias'] = []; }, 'paginas_obrigatorias'],
            'sem seções'              => [function (&$m) { $m['secoes'] = []; }, 'secoes'],
            'seção sem rótulo'        => [function (&$m) { unset($m['secoes']['hero']['rotulo']); }, 'secoes.hero.rotulo'],
            'tipo de campo inexistente' => [function (&$m) { $m['secoes']['hero']['campos']['titulo']['tipo'] = 'cor'; }, 'secoes.hero.campos.titulo.tipo'],
            'opcao sem opcoes'        => [function (&$m) { unset($m['secoes']['hero']['campos']['botoes']['itens']['estilo']['opcoes']); }, 'secoes.hero.campos.botoes.itens.estilo.opcoes'],
            'padrão fora das opções'  => [function (&$m) { $m['secoes']['hero']['campos']['botoes']['itens']['estilo']['padrao'] = 'x'; }, 'secoes.hero.campos.botoes.itens.estilo.padrao'],
            'lista sem itens'         => [function (&$m) { unset($m['secoes']['pilares']['campos']['itens']['itens']); }, 'secoes.pilares.campos.itens.itens'],
            'lista dentro de lista'   => [function (&$m) { $m['secoes']['pilares']['campos']['itens']['itens']['sub'] = ['tipo' => 'lista', 'itens' => []]; }, 'secoes.pilares.campos.itens.itens.sub'],
            'dinâmico sem fonte'      => [function (&$m) { unset($m['secoes']['contato']['campos']['areas']['fonte']); }, 'secoes.contato.campos.areas.fonte'],
            'chave de texto inválida' => [function (&$m) { $m['textos']['Contato Botão'] = 'x'; }, 'textos.Contato Botão'],
            'max inválido'            => [function (&$m) { $m['secoes']['hero']['campos']['sobretitulo']['max'] = 0; }, 'secoes.hero.campos.sobretitulo.max'],
        ];
    }

    public function testSemVersaoMinimaGeraApenasAviso(): void
    {
        $m = $this->base();
        unset($m['sistema']);

        $r = $this->validator->validar($m);

        $this->assertTrue($r->valido());
        $this->assertArrayHasKey('sistema', $r->avisos());
    }
}
