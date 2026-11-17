<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\DadosEscritorio;
use App\Libraries\Site\SiteContexto;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Dados do escritório nos documentos gerados (issue #656): vêm das configurações do site e
 * nenhuma view carrega nome, telefone ou advogado fixos.
 */
class DadosEscritorioTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        SiteContexto::definir(null);
        parent::tearDown();
    }

    private function contexto(array $config, ?string $logo = null): SiteContexto
    {
        $contexto = new class ($config, $logo) extends SiteContexto {
            public function __construct(private array $dados, private ?string $logo)
            {
            }

            public function config(string $chave, mixed $padrao = null): mixed
            {
                $v = $this->dados[$chave] ?? null;

                return ($v === null || $v === '') ? $padrao : $v;
            }

            public function logoArquivo(): ?string
            {
                return $this->logo;
            }
        };
        SiteContexto::definir($contexto);

        return $contexto;
    }

    private function outroEscritorio(?string $logo = null): array
    {
        return [
            'identidade.nome'      => 'Silva & Souza Advogados Associados',
            'identidade.nome_curto' => 'Silva & Souza',
            'contato.telefone'     => '(11) 4000-1111',
            'contato.site'         => 'https://www.silvaesouza.example/',
            'contato.enderecos'    => [['cidade' => 'São Paulo', 'uf' => 'SP']],
        ];
    }

    public function testLeDadosDasConfiguracoesDoSite(): void
    {
        $dados = new DadosEscritorio($this->contexto($this->outroEscritorio()));

        $this->assertSame('Silva & Souza Advogados Associados', $dados->nome());
        $this->assertSame('Silva & Souza', $dados->nomeCurto());
        $this->assertSame(['(11) 4000-1111'], $dados->telefones());
        $this->assertSame('www.silvaesouza.example', $dados->site());
        $this->assertSame('São Paulo', $dados->cidade());
        $this->assertSame(DadosEscritorio::COR_PADRAO, $dados->cor());
    }

    public function testCorSoAceitaHexadecimalValido(): void
    {
        $this->assertSame('#336699', (new DadosEscritorio($this->contexto(['identidade.cor_documentos' => '#336699'])))->cor());
        $this->assertSame(DadosEscritorio::COR_PADRAO, (new DadosEscritorio($this->contexto(['identidade.cor_documentos' => 'red;}</style>'])))->cor());
    }

    public function testSegundoTelefoneEOpcional(): void
    {
        $dados = new DadosEscritorio($this->contexto($this->outroEscritorio() + ['contato.telefone2' => '(11) 4000-2222']));

        $this->assertSame(['(11) 4000-1111', '(11) 4000-2222'], $dados->telefones());
    }

    public function testLogoViraDataUri(): void
    {
        $png = sys_get_temp_dir() . '/logo_teste_' . uniqid() . '.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        try {
            $uri = (new DadosEscritorio($this->contexto(['identidade.logo' => 1], $png)))->logoDataUri();
            $this->assertStringStartsWith('data:image/png;base64,', $uri);
        } finally {
            @unlink($png);
        }
    }

    public function testOutorganteInformadoOuVazioSemUsuarioLogado(): void
    {
        $this->assertSame(['nome' => 'Maria Souza', 'oab' => 'OAB/SP 123.456'], DadosEscritorio::outorgante(['nome' => ' Maria Souza ', 'oab' => 'OAB/SP 123.456']));
        $this->assertSame(['nome' => '', 'oab' => ''], DadosEscritorio::outorgante());
    }

    public function testViewsDeDocumentosNaoTemNadaDoCardosoEBrunoFixo(): void
    {
        $this->contexto($this->outroEscritorio() + ['contato.telefone2' => '(11) 4000-2222']);

        $timbrado = view('documentos/timbrado', ['conteudo' => '<p>Corpo</p>', 'logoBase64' => null]);
        $subst = view('subestabelecimento', [
            'representados' => 'Fulano', 'numero_processo' => '1234', 'outorgante' => ['nome' => 'Maria Souza', 'oab' => 'OAB/SP 123.456'],
        ]);
        $lote = view('subestabelecimento_lote', [
            'documentos' => [['representados' => 'Fulano', 'numero_processo' => '1234']],
            'outorgante' => ['nome' => 'Maria Souza', 'oab' => 'OAB/SP 123.456'],
        ]);

        foreach (['timbrado' => $timbrado, 'substabelecimento' => $subst, 'lote' => $lote] as $nome => $html) {
            $this->assertDoesNotMatchRegularExpression('/cardoso|bruno|rodrigo|99224|99217|61\.061/i', $html, $nome);
            $this->assertStringContainsString('(11) 4000-1111', $html, $nome);
            $this->assertStringContainsString('www.silvaesouza.example', $html, $nome);
        }
        $this->assertStringContainsString('Silva &amp; Souza', $timbrado);
        $this->assertStringContainsString('Maria Souza', $subst);
        $this->assertStringContainsString('OAB/SP 123.456', $subst);
        $this->assertStringContainsString('São Paulo, ', $subst);
        $this->assertStringContainsString('color: #1b4965;', $timbrado, 'cor padrão preservada');
    }

    public function testAssinaturaSemAdvogadoNaoInventaNome(): void
    {
        $this->contexto($this->outroEscritorio());

        $html = view('subestabelecimento', ['representados' => 'Fulano', 'numero_processo' => '1']);

        $this->assertDoesNotMatchRegularExpression('/rodrigo|61\.061/i', $html);
    }
}
