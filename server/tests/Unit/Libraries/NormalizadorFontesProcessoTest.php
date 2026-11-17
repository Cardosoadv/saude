<?php

namespace Tests\Unit\Libraries;

use App\Libraries\Importacao\NormalizadorFontesProcesso as Fontes;
use App\Services\DatajudService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorFontesProcessoTest extends TestCase
{
    public function testDjenUsaPublicacaoMaisRecenteEJuntaPartesSemRepetir(): void
    {
        $itens = [
            [
                'data_disponibilizacao' => '2025-01-10',
                'siglaTribunal'         => 'TRT3',
                'nomeOrgao'             => '1ª Vara antiga',
                'nomeClasse'            => 'AçãO TRABALHISTA - RITO ORDINáRIO',
                'codigoClasse'          => '985',
                'link'                  => 'https://pje.trt3.jus.br/antigo',
                'numeroprocessocommascara' => '0010553-41.2021.5.03.0106',
                'destinatarios'         => [
                    ['polo' => 'A', 'nome' => 'Maria da Silva'],
                    ['polo' => 'P', 'nome' => 'Empresa X Ltda'],
                ],
            ],
            [
                'data_disponibilizacao' => '2026-05-27',
                'siglaTribunal'         => 'TRT3',
                'nomeOrgao'             => '27ª Vara do Trabalho de Belo Horizonte',
                'nomeClasse'            => '',
                'link'                  => 'https://pje.trt3.jus.br/novo',
                'destinatarios'         => [
                    ['polo' => 'A', 'nome' => 'MARIA DA SILVA'],
                    ['polo' => 'P', 'nome' => 'Empresa Y S/A'],
                    ['polo' => 'X', 'nome' => 'Ignorado'],
                ],
            ],
        ];

        $d = Fontes::deDjen($itens);

        $this->assertSame('27ª VARA DO TRABALHO DE BELO HORIZONTE', $d['nomeOrgao']);
        $this->assertSame('https://pje.trt3.jus.br/novo', $d['link']);
        // Classe vazia na mais recente: usa a anterior, com caixa corrigida.
        $this->assertSame('AÇÃO TRABALHISTA - RITO ORDINÁRIO', $d['tipoDocumento']);
        $this->assertSame('985', $d['codigoClasse']);
        $this->assertSame(['MARIA DA SILVA'], $d['poloAtivo']);
        $this->assertSame(['Empresa Y S/A', 'Empresa X Ltda'], $d['poloPassivo']);
        $this->assertSame(2, $d['total_publicacoes']);
        $this->assertSame('2026-05-27', $d['ultima_publicacao']);
    }

    public function testDjenVazio(): void
    {
        $d = Fontes::deDjen([]);
        $this->assertNull($d['siglaTribunal']);
        $this->assertSame([], $d['poloAtivo']);
    }

    public function testDatajudJuntaGrausESinalizaSigilo(): void
    {
        $hits = [
            ['_source' => [
                'numeroProcesso'            => '00503313320038130231',
                'tribunal'                  => 'TJMG',
                'grau'                      => 'G1',
                'dataAjuizamento'           => '20030515135200',
                'dataHoraUltimaAtualizacao' => '2024-01-01T00:00:00Z',
                'classe'                    => ['codigo' => 65, 'nome' => 'Ação Civil Pública'],
                'orgaoJulgador'             => ['nome' => 'Vara da Fazenda Pública'],
                'assuntos'                  => [['codigo' => 1, 'nome' => 'Dano ao Erário']],
                'nivelSigilo'               => 0,
            ]],
            ['_source' => [
                'numeroProcesso'            => '00503313320038130231',
                'tribunal'                  => 'TJMG',
                'grau'                      => 'G2',
                'dataAjuizamento'           => '2010-02-03T10:00:00.000Z',
                'dataHoraUltimaAtualizacao' => '2025-03-21T22:51:51Z',
                'classe'                    => ['codigo' => 198, 'nome' => 'Apelação Cível'],
                'orgaoJulgador'             => ['nome' => '5ª Câmara Cível'],
                'assuntos'                  => [[['codigo' => 2, 'nome' => 'Improbidade']]],
                'nivelSigilo'               => 1,
                'sistema'                   => ['nome' => 'PJe'],
            ]],
        ];

        $d = Fontes::deDatajud($hits);

        $this->assertSame('0050331-33.2003.8.13.0231', $d['numeroprocessocommascara']);
        $this->assertSame('Apelação Cível', $d['tipoDocumento']);
        $this->assertSame('198', $d['codigoClasse']);
        $this->assertSame('5ª Câmara Cível', $d['nomeOrgao']);
        $this->assertSame('2003-05-15', $d['dataDistribuicao']);
        $this->assertSame(['G2', 'G1'], $d['graus']);
        $this->assertSame(['Improbidade', 'Dano ao Erário'], $d['assuntos']);
        $this->assertTrue($d['sigilo']);
        $this->assertSame([], $d['poloAtivo']);
    }

    public function testComparavelIgnoraAcentoCaixaEEspacos(): void
    {
        $this->assertSame(Fontes::comparavel('27ª Vara  do Trabalho'), Fontes::comparavel(' 27ª VARA DO TRABALHO '));
        $this->assertSame('acao civil publica', Fontes::comparavel('AÇÃO Civil Pública'));
    }

    #[DataProvider('aliasesProvider')]
    public function testAliasDatajudPorNumero(string $numero, ?string $alias): void
    {
        $this->assertSame($alias, (new DatajudService())->aliasPorNumero($numero));
    }

    public static function aliasesProvider(): array
    {
        return [
            'TJMG'       => ['0050331-33.2003.8.13.0231', 'tjmg'],
            'TJDFT'      => ['0700001-00.2024.8.07.0001', 'tjdft'],
            'TJSP'       => ['0801234-04.2025.8.26.0100', 'tjsp'],
            'TJTO'       => ['0000001-00.2024.8.27.0001', 'tjto'],
            'TRF6'       => ['0000001-00.2024.4.06.3800', 'trf6'],
            'TRT3'       => ['0010553-41.2021.5.03.0106', 'trt3'],
            'TRT24'      => ['0000001-00.2024.5.24.0001', 'trt24'],
            'TST'        => ['0000001-00.2024.5.00.0000', 'tst'],
            'TRE-SP'     => ['0600001-00.2024.6.26.0001', 'tre-sp'],
            'TSE'        => ['0600001-00.2024.6.00.0000', 'tse'],
            'STJ'        => ['0000001-00.2024.3.00.0000', 'stj'],
            'STM'        => ['7000001-00.2024.7.00.0000', 'stm'],
            'TJMMG'      => ['0000001-00.2024.9.13.0001', 'tjmmg'],
            'STF (sem)'  => ['0000001-00.2024.1.00.0000', null],
            'TRF7 (não)' => ['0000001-00.2024.4.07.0001', null],
            'curto'      => ['123', null],
        ];
    }

    private function datajudCom(array $respostas): DatajudService
    {
        $client = new class ($respostas) {
            public int $chamadas = 0;
            public function __construct(private array $respostas) {}
            public function post(string $url, array $opcoes): object
            {
                [$status, $corpo] = $this->respostas[$this->chamadas++];
                return new class ($status, $corpo) {
                    public function __construct(private int $s, private string $b) {}
                    public function getStatusCode(): int { return $this->s; }
                    public function getBody(): string { return $this->b; }
                };
            }
        };

        return new class ($client) extends DatajudService {
            protected function aguardar(int $segundos): void {}
        };
    }

    public function testDatajudTentaDeNovoQuandoSobrecarregado(): void
    {
        $ok = json_encode(['hits' => ['hits' => [['_source' => ['tribunal' => 'TRT3', 'classe' => ['nome' => 'ATOrd']]]]]]);
        $r  = $this->datajudCom([[429, '{}'], [503, '{}'], [200, $ok]])->consultarProcesso('0010553-41.2021.5.03.0106');

        $this->assertSame('encontrado', $r['status']);
        $this->assertSame('TRT3', $r['dados']['siglaTribunal']);
    }

    public function testDatajudDesisteDepoisDasTentativasComMensagemPropria(): void
    {
        $r = $this->datajudCom([[429, '{}'], [429, '{}'], [429, '{}']])->consultarProcesso('0010553-41.2021.5.03.0106');

        $this->assertSame('erro', $r['status']);
        $this->assertStringContainsString('sobrecarregado', $r['mensagem']);
    }

    public function testDatajudTribunalSemIndice(): void
    {
        $r = $this->datajudCom([])->consultarProcesso('0000001-00.2024.1.00.0000');
        $this->assertSame('indisponivel', $r['status']);
    }

    public function testUrlPorCodigoMantemCompatibilidadeComMovimentos(): void
    {
        $s = new DatajudService();
        $this->assertSame('https://api-publica.datajud.cnj.jus.br/api_publica_tjmg/_search', $s->urlPorCodigo('813'));
        $this->assertSame('https://api-publica.datajud.cnj.jus.br/api_publica_trt3/_search', $s->urlPorCodigo('503'));
        $this->assertNull($s->urlPorCodigo('999'));
    }
}
