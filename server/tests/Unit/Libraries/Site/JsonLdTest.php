<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\JsonLd;
use CodeIgniter\Test\CIUnitTestCase;

class JsonLdTest extends CIUnitTestCase
{
    private function config(): array
    {
        return [
            'identidade.nome'  => 'Clínica & Cia',
            'contato.telefone' => '(31) 9.9999-0000',
            'contato.email'    => 'oi@exemplo.com',
            'contato.enderecos' => [
                ['titulo' => 'BH', 'rua' => 'Rua A, 10', 'cidade' => 'Belo Horizonte', 'uf' => 'MG', 'cep' => '30000-000'],
                ['titulo' => 'Sem dados estruturados', 'linha1' => 'Rua B'],
            ],
            'redes.instagram' => 'https://instagram.com/x',
            'redes.linkedin'  => 'javascript:alert(1)',
            'redes.facebook'  => '',
        ];
    }

    public function testMontaMedicalClinicComOQueExiste(): void
    {
        $d = JsonLd::organizacao($this->config(), 'https://site.com/', 'https://site.com/logo.png');

        $this->assertSame('MedicalClinic', $d['@type']);
        $this->assertSame('Clínica & Cia', $d['name']);
        $this->assertSame('https://site.com/logo.png', $d['logo']);
        $this->assertSame('(31) 9.9999-0000', $d['telephone']);
        $this->assertSame('oi@exemplo.com', $d['email']);
        $this->assertSame(
            ['@type' => 'PostalAddress', 'streetAddress' => 'Rua A, 10', 'addressLocality' => 'Belo Horizonte', 'addressRegion' => 'MG', 'postalCode' => '30000-000', 'addressCountry' => 'BR'],
            $d['address'],
            'unidade sem rua/cidade estruturadas é ignorada; uma só vira objeto'
        );
        $this->assertSame(['https://instagram.com/x'], $d['sameAs'], 'só http(s)');
    }

    public function testOmiteCamposAusentesESemNomeNaoGeraNada(): void
    {
        $this->assertNull(JsonLd::organizacao(['contato.telefone' => '1'], 'https://site.com/'));
        $this->assertSame('', JsonLd::script(null));

        $d = JsonLd::organizacao(['identidade.nome' => 'X'], 'https://site.com/');
        $this->assertSame(['@context', '@type', '@id', 'name', 'url'], array_keys($d));
        $this->assertSame('https://site.com/#organizacao', $d['@id']);
    }

    public function testVariasUnidadesViramLista(): void
    {
        $cfg = ['identidade.nome' => 'X', 'contato.enderecos' => [
            ['rua' => 'A', 'cidade' => 'BH'], ['rua' => 'B', 'cidade' => 'Juatuba'],
        ]];

        $this->assertCount(2, JsonLd::organizacao($cfg, 'https://site.com/')['address']);
    }

    public function testScriptNaoPermiteFecharATagNemInjetarHtml(): void
    {
        $html = JsonLd::script(['name' => '</script><script>alert(1)</script>', 'x' => "a'b\"c&d"]);

        $this->assertSame(1, substr_count($html, '</script>'), 'só o fechamento da própria tag');
        $this->assertStringNotContainsString('<script>alert', $html);
        $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        $this->assertSame('</script><script>alert(1)</script>', json_decode($json, true)['name'], 'o conteúdo volta íntegro ao decodificar');
    }

    private function completo(): array
    {
        return $this->config() + [
            'identidade.nome_curto'   => 'Clínica',
            'identidade.razao_social' => 'Clínica & Cia Sociedade de Profissionais',
            'identidade.cnpj'         => '11222333000181',
            'identidade.faixa_preco'  => '$$',
            'contato.area_atendida'   => ['Belo Horizonte', 'Juatuba', ' ', 'Minas Gerais'],
            'contato.horario'         => [
                ['dias' => ['Monday', 'Friday', 'Funday'], 'abre' => '09:00', 'fecha' => '18:00'],
                ['dias' => ['Saturday'], 'abre' => '9h', 'fecha' => '12:00'],
            ],
            'contato.latitude'        => '-19.9',
            'contato.longitude'       => '-43.9',
            'contato.mapa_url'        => 'https://maps.google.com/?q=x',
            'identidade.profissionais'    => [['nome' => 'Fulana', 'registro' => 'CRM/MG 1'], ['nome' => ' '], ['nome' => 'Beltrano', 'cargo' => 'Diretor']],
        ];
    }

    public function testMedicalClinicAmpliadoComIdentidadeAreaEHorario(): void
    {
        $d = JsonLd::organizacao($this->completo(), 'https://site.com/', 'https://site.com/logo.png', ['Direito Civil', 'Direito Civil', 'Contratos'], 'https://site.com/og.png');

        $this->assertSame('Clínica & Cia Sociedade de Profissionais', $d['legalName']);
        $this->assertSame('11.222.333/0001-81', $d['taxID']);
        $this->assertSame('https://site.com/og.png', $d['image']);
        $this->assertSame('$$', $d['priceRange']);
        $this->assertSame(['Belo Horizonte', 'Juatuba', 'Minas Gerais'], $d['areaServed']);
        $this->assertSame(['Direito Civil', 'Contratos'], $d['knowsAbout']);
        $this->assertSame([['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Friday'], 'opens' => '09:00', 'closes' => '18:00']], $d['openingHoursSpecification'], 'dia ou hora inválidos são descartados');
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => -19.9, 'longitude' => -43.9], $d['geo']);
        $this->assertSame('https://maps.google.com/?q=x', $d['hasMap']);
    }

    public function testCamposNovosVaziosOuInvalidosSaem(): void
    {
        $d = JsonLd::organizacao(['identidade.nome' => 'X', 'identidade.cnpj' => '123', 'contato.latitude' => '99', 'contato.longitude' => '1', 'contato.mapa_url' => 'javascript:x', 'contato.horario' => [], 'contato.area_atendida' => []], 'https://site.com/');

        foreach (['legalName', 'taxID', 'image', 'priceRange', 'geo', 'hasMap', 'areaServed', 'knowsAbout', 'openingHoursSpecification'] as $campo) {
            $this->assertArrayNotHasKey($campo, $d);
        }
    }

    /** @return array<string, array<string, mixed>> nós do grafo por @type (ou @id para listas) */
    private function nos(array $grafo): array
    {
        $por = [];
        foreach ($grafo['@graph'] as $no) {
            $por[$no['@type']][] = $no;
        }

        return $por;
    }

    public function testGrafoDaHomeTemOrganizacaoSiteEProfissionais(): void
    {
        $g = JsonLd::grafo($this->completo(), 'https://site.com/', null, ['pagina' => ['titulo' => 'Início', 'url' => 'https://site.com/', 'tipo' => 'home']]);
        $this->assertSame('https://schema.org', $g['@context']);
        $n = $this->nos($g);

        $this->assertSame(['MedicalClinic', 'WebSite', 'Person'], array_keys($n));
        $this->assertSame('https://site.com/#website', $n['WebSite'][0]['@id']);
        $this->assertSame('pt-BR', $n['WebSite'][0]['inLanguage']);
        $this->assertSame(['@id' => 'https://site.com/#organizacao'], $n['WebSite'][0]['publisher']);
        $this->assertSame('Clínica', $n['WebSite'][0]['name']);
        $this->assertCount(2, $n['Person'], 'profissional sem nome é ignorado');
        $this->assertSame(['@type' => 'PropertyValue', 'propertyID' => 'Registro profissional', 'value' => 'CRM/MG 1'], $n['Person'][0]['identifier']);
        $this->assertSame('Profissional de saúde', $n['Person'][0]['jobTitle']);
        $this->assertSame('Diretor', $n['Person'][1]['jobTitle']);
        $this->assertCount(2, $n['MedicalClinic'][0]['member']);
    }

    public function testPaginaInternaGanhaWebPageEBreadcrumb(): void
    {
        $g = JsonLd::grafo(['identidade.nome' => 'X'], 'https://site.com/', null, ['pagina' => ['titulo' => 'Direito Civil', 'url' => 'https://site.com/pagina/direito-civil', 'tipo' => 'area']]);
        $n = $this->nos($g);

        $this->assertSame('https://site.com/pagina/direito-civil#webpage', $n['WebPage'][0]['@id']);
        $this->assertSame(['@id' => 'https://site.com/#website'], $n['WebPage'][0]['isPartOf']);
        $this->assertSame(['Início', 'Direito Civil'], array_column($n['BreadcrumbList'][0]['itemListElement'], 'name'));
        $this->assertSame([1, 2], array_column($n['BreadcrumbList'][0]['itemListElement'], 'position'));
        $this->assertArrayNotHasKey('BlogPosting', $n);
    }

    public function testArtigoViraBlogPostingSobNoticias(): void
    {
        $g = JsonLd::grafo(['identidade.nome' => 'X'], 'https://site.com/', 'https://site.com/logo.png', [
            'pagina' => ['titulo' => 'Notícias', 'url' => 'https://site.com/noticias/a', 'tipo' => 'sistema'],
            'artigo' => ['titulo' => 'Meu artigo', 'url' => 'https://site.com/noticias/a', 'resumo' => 'Resumo', 'publicado' => '2026-10-01 10:00:00', 'modificado' => '2026-10-02 11:00:00', 'imagem' => null],
        ]);
        $n = $this->nos($g);
        $post = $n['BlogPosting'][0];

        $this->assertSame('Meu artigo', $post['headline']);
        $this->assertStringStartsWith('2026-10-01T10:00:00', $post['datePublished']);
        $this->assertStringStartsWith('2026-10-02T11:00:00', $post['dateModified']);
        $this->assertSame(['@id' => 'https://site.com/#organizacao'], $post['author']);
        $this->assertSame(['@id' => 'https://site.com/#organizacao'], $post['publisher']);
        $this->assertSame('https://site.com/logo.png', $post['image'], 'sem imagem própria usa o logo');
        $this->assertSame(['Início', 'Notícias', 'Meu artigo'], array_column($n['BreadcrumbList'][0]['itemListElement'], 'name'));
    }

    public function testFaqPageSoComPerguntasCompletas(): void
    {
        $ctx = ['pagina' => ['titulo' => 'P', 'url' => 'https://site.com/pagina/p', 'tipo' => 'pagina'], 'faq' => [['pergunta' => 'Q?', 'resposta' => '<p>R</p>'], ['pergunta' => 'Sem resposta', 'resposta' => ' ']]];
        $n = $this->nos(JsonLd::grafo(['identidade.nome' => 'X'], 'https://site.com/', null, $ctx));

        $this->assertCount(1, $n['FAQPage'][0]['mainEntity']);
        $this->assertSame('R', $n['FAQPage'][0]['mainEntity'][0]['acceptedAnswer']['text']);

        unset($ctx['faq']);
        $this->assertArrayNotHasKey('FAQPage', $this->nos(JsonLd::grafo(['identidade.nome' => 'X'], 'https://site.com/', null, $ctx)));
        $this->assertNull(JsonLd::grafo([], 'https://site.com/'));
    }

    public function testGrafoEscapaTagsNoScript(): void
    {
        $g = JsonLd::grafo(['identidade.nome' => '</script><b>'], 'https://site.com/');

        $this->assertSame(1, substr_count(JsonLd::script($g), '</script>'));
    }
}
