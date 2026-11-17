<?php

namespace Tests\Unit\Services;

use App\Libraries\Importacao\NormalizadorFontesProcesso as Fontes;
use App\Repositories\ProcessosImportacaoRepository;
use App\Services\DatajudService;
use App\Services\DjenService;
use App\Services\EtiquetasService;
use App\Services\ProcessosImportacaoService;
use App\Services\ProcessosService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProcessosImportacaoServiceTest extends TestCase
{
    private const NUM_1 = '0801234-04.2025.8.26.0100';
    private const NUM_2 = '1004567-38.2024.5.02.0001';

    private const CLIENTES = [
        ['id_cliente' => 10, 'nome' => 'Maria da Silva', 'razao_social' => null, 'documento' => '123.456.789-09'],
        ['id_cliente' => 11, 'nome' => 'João Pereira Souza', 'razao_social' => null, 'documento' => null],
        ['id_cliente' => 12, 'nome' => 'Banco Exemplo', 'razao_social' => 'Banco Exemplo S.A.', 'documento' => '11.222.333/0001-81'],
    ];

    private function repo(?array $carteira = null, array $partesCarteira = [], array $etiquetasCarteira = []): ProcessosImportacaoRepository
    {
        $repo = $this->createMock(ProcessosImportacaoRepository::class);
        $repo->method('clientes')->willReturn(self::CLIENTES);
        $repo->method('usuarios')->willReturn([['id' => 5, 'username' => 'fabiano']]);
        $repo->method('ramos')->willReturn([['id' => 3, 'nome' => 'Direito do Consumidor']]);
        $repo->method('processoPorNumero')->willReturn($carteira);
        $repo->method('partesDoProcesso')->willReturn($partesCarteira);
        $repo->method('etiquetasDoProcesso')->willReturn($etiquetasCarteira);

        return $repo;
    }

    private function service(?ProcessosImportacaoRepository $repo = null, ?ProcessosService $processos = null, ?EtiquetasService $etiquetas = null): ProcessosImportacaoService
    {
        return new ProcessosImportacaoService(
            $this->createMock(DjenService::class),
            new DatajudService(),
            $repo ?? $this->repo(),
            $processos ?? $this->createMock(ProcessosService::class),
            $etiquetas ?? $this->createMock(EtiquetasService::class)
        );
    }

    private function djen(array $itens): array
    {
        return ['status' => $itens ? 'encontrado' : 'nao_encontrado', 'mensagem' => null, 'dados' => Fontes::deDjen($itens)];
    }

    private function datajud(array $hits): array
    {
        return ['status' => $hits ? 'encontrado' : 'nao_encontrado', 'mensagem' => null, 'dados' => Fontes::deDatajud($hits)];
    }

    private function campo(array $conf, string $nome): array
    {
        foreach ($conf['campos'] as $c) {
            if ($c['campo'] === $nome) {
                return $c;
            }
        }
        $this->fail("Campo {$nome} ausente.");
    }

    // ---------------------------------------------------------------- leitura

    public function testLerExigeSoONumeroEValidaCnjERepetidos(): void
    {
        $csv = "numero_processo;cliente;valor_causa;data_distribuicao;risco;polo_ativo\n"
            . self::NUM_1 . ";Maria da Silva;R$ 1.234,56;15/03/2025;provavel;Maria da Silva|José Lima\n"
            . self::NUM_2 . ";;;;;\n"
            . "123;;;;;\n"
            . self::NUM_1 . ";;;;;\n";

        $r = $this->service()->ler($csv, 'lote.csv');

        $this->assertSame(4, $r['total']);
        $this->assertCount(2, $r['processos']);
        $this->assertCount(2, $r['erros']);
        $this->assertStringContainsString('20 dígitos', $r['erros'][0]['motivo']);
        $this->assertStringContainsString('Repetido', $r['erros'][1]['motivo']);

        $a = $r['processos'][0]['arquivo'];
        $this->assertSame('08012340420258260100', $r['processos'][0]['numero_processo']);
        $this->assertSame(self::NUM_1, $r['processos'][0]['numeroprocessocommascara']);
        $this->assertSame('Maria da Silva', $a['cliente_nome']);
        $this->assertSame('1234.56', $a['valorCausa']);
        $this->assertSame('2025-03-15', $a['dataDistribuicao']);
        $this->assertSame('Provável', $a['risco']);
        $this->assertSame(['Maria da Silva', 'José Lima'], $a['poloAtivo']);
    }

    public function testLerSoComNumerosSemCabecalhoEJson(): void
    {
        $r = $this->service()->ler(self::NUM_1 . "\n" . self::NUM_2 . "\n", 'lote.csv');
        $this->assertCount(2, $r['processos']);

        $json = json_encode(['processos' => [
            ['numero' => self::NUM_1, 'polo_passivo' => ['Banco Exemplo S.A.', 'Outro']],
            self::NUM_2,
        ]]);
        $r = $this->service()->ler($json, 'lote.json');
        $this->assertSame('json', $r['formato']);
        $this->assertSame(['Banco Exemplo S.A.', 'Outro'], $r['processos'][0]['arquivo']['poloPassivo']);
    }

    public function testLerLimiteDe50(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limite por importação é 50');
        $this->service()->ler(str_repeat(self::NUM_1 . "\n", 51), 'lote.csv');
    }

    public function testValoresInvalidosViramAvisoSemBloquear(): void
    {
        [$arquivo, $avisos] = $this->service()->normalizarLinha([
            'numero_processo'  => self::NUM_1,
            'dataDistribuicao' => '31/02/2025',
            'valorCausa'       => 'abc',
            'risco'            => 'alto',
            'visibilidade'     => 'Restrito',
        ]);

        $this->assertNull($arquivo['dataDistribuicao']);
        $this->assertNull($arquivo['valorCausa']);
        $this->assertNull($arquivo['risco']);
        $this->assertSame('restrito', $arquivo['visibilidade']);
        $this->assertCount(3, $avisos);
    }

    // ------------------------------------------------------------ conferência

    public function testConferenciaProcessoNovoComDivergenciaESugestoes(): void
    {
        $service = $this->service();
        [$arquivo] = $service->normalizarLinha([
            'numero_processo' => self::NUM_1,
            'cliente_nome'    => 'Maria da Silva',
            'nomeOrgao'       => '1ª Vara Cível de São Paulo',
            'poloAtivo'       => 'Maria da Silva',
        ]);

        $djen = $this->djen([[
            'data_disponibilizacao' => '2026-01-10',
            'siglaTribunal'         => 'TJSP',
            'nomeOrgao'             => '2ª Vara Cível de São Paulo',
            'nomeClasse'            => 'PROCEDIMENTO COMUM CíVEL',
            'link'                  => 'https://esaj.tjsp.jus.br/x',
            'destinatarios'         => [
                ['polo' => 'A', 'nome' => 'MARIA DA SILVA'],
                ['polo' => 'P', 'nome' => 'BANCO EXEMPLO S.A.'],
            ],
        ]]);
        $datajud = $this->datajud([['_source' => [
            'numeroProcesso'  => '08012340420258260100',
            'tribunal'        => 'TJSP',
            'classe'          => ['codigo' => 7, 'nome' => 'Procedimento Comum Cível'],
            'orgaoJulgador'   => ['nome' => '2ª Vara Cível de São Paulo'],
            'dataAjuizamento' => '20250110000000',
        ]]]);

        $c = $service->montarConferencia('08012340420258260100', $arquivo, $djen, $datajud, null);

        $this->assertSame('novo', $c['situacao']);
        $this->assertTrue($c['importavel']);

        $orgao = $this->campo($c, 'nomeOrgao');
        $this->assertTrue($orgao['divergente']);                         // arquivo 1ª x fontes 2ª
        $this->assertSame('2ª Vara Cível de São Paulo', $orgao['sugestao']); // oficial: DataJud

        $classe = $this->campo($c, 'tipoDocumento');
        $this->assertFalse($classe['divergente']);                       // só muda caixa/acento

        $cliente = $this->campo($c, 'cliente_id');
        $this->assertSame(10, $cliente['sugestao']);                     // achado pelo nome no arquivo

        $titulo = $this->campo($c, 'titulo_processo');
        $this->assertSame('Maria da Silva - Procedimento Comum Cível', $titulo['sugestao']);
        $this->assertTrue($titulo['gerado']);

        // Partes: "Maria da Silva" do arquivo e do DJEN viram uma só.
        $this->assertCount(2, $c['partes']);
        $this->assertSame(['arquivo', 'djen'], $c['partes'][0]['fontes']);
        $this->assertTrue($c['partes'][0]['incluir']);

        // O banco do polo passivo é sugerido como cliente (razão social igual).
        $ids = array_column($c['clientes_sugeridos'], 'id_cliente');
        $this->assertContains(12, $ids);
        $this->assertNotContains(10, $ids); // já é o cliente do arquivo
    }

    public function testTituloPlaceholderSemClienteOuClasse(): void
    {
        $c = $this->service()->montarConferencia('08012340420258260100', [], $this->djen([]), $this->datajud([]), null);

        $this->assertSame(ProcessosImportacaoService::TITULO_PADRAO, $this->campo($c, 'titulo_processo')['sugestao']);
        $this->assertNull($this->campo($c, 'cliente_id')['sugestao']);
        $this->assertSame([], $c['partes']);
    }

    public function testProcessoCadastradoMantemCarteiraComoSugestaoEMarcaPartesExistentes(): void
    {
        $carteira = [
            'id_processo' => 99, 'deleted_at' => null, 'titulo_processo' => 'Maria x Banco', 'cliente_id' => 10,
            'cliente_nome' => 'Maria da Silva', 'nomeOrgao' => '1ª Vara', 'siglaTribunal' => 'TJSP',
            'responsavel_id' => 5, 'responsavel_nome' => 'fabiano', 'valorCausa' => '1000.00',
            'partes' => [['nome' => 'Maria da Silva', 'polo' => 'A']],
            'etiquetas' => ['Urgente'],
        ];
        $djen = $this->djen([[
            'data_disponibilizacao' => '2026-01-10',
            'nomeOrgao'             => '2ª Vara',
            'destinatarios'         => [['polo' => 'A', 'nome' => 'MARIA DA SILVA'], ['polo' => 'P', 'nome' => 'Banco Exemplo S.A.']],
        ]]);
        [$arquivo] = $this->service()->normalizarLinha(['valorCausa' => '1.000,00', 'responsavel' => 'outro']);

        $c = $this->service()->montarConferencia('08012340420258260100', $arquivo, $djen, $this->datajud([]), $carteira);

        $this->assertSame('cadastrado', $c['situacao']);
        $this->assertSame(99, $c['id_processo']);

        $orgao = $this->campo($c, 'nomeOrgao');
        $this->assertTrue($orgao['divergente']);
        $this->assertSame('1ª Vara', $orgao['sugestao']);
        $this->assertSame('1ª Vara', $orgao['atual']);

        $this->assertFalse($this->campo($c, 'valorCausa')['divergente']); // 1.000,00 == 1000.00
        $this->assertSame('Maria x Banco', $this->campo($c, 'titulo_processo')['sugestao']);
        $this->assertTrue($this->campo($c, 'responsavel_id')['bloqueado']);

        $maria = $c['partes'][0];
        $this->assertTrue($maria['na_carteira']);
        $this->assertFalse($maria['incluir']);
        $this->assertSame('Maria da Silva', $maria['nome']); // grafia da carteira
        $this->assertTrue($c['partes'][1]['incluir']);
        $this->assertSame(['Urgente'], $c['etiquetas']['carteira']);
    }

    public function testProcessoExcluidoNaoEImportavel(): void
    {
        $c = $this->service()->montarConferencia(
            '08012340420258260100', [], $this->djen([]), $this->datajud([]),
            ['id_processo' => 7, 'deleted_at' => '2026-01-01 00:00:00']
        );

        $this->assertSame('excluido', $c['situacao']);
        $this->assertFalse($c['importavel']);
    }

    public function testSigiloComIniciaisSugereClienteComConfiancaBaixa(): void
    {
        $djen = $this->djen([[
            'data_disponibilizacao' => '2026-01-10',
            'destinatarios'         => [['polo' => 'A', 'nome' => 'J. P. S.'], ['polo' => 'P', 'nome' => 'M.S.']],
        ]]);

        $c = $this->service()->montarConferencia('08012340420258260100', [], $djen, $this->datajud([]), null);

        $this->assertTrue($c['sigilo']);
        $porId = array_column($c['clientes_sugeridos'], 'confianca', 'id_cliente');
        $this->assertSame('baixa', $porId[11] ?? null); // João Pereira Souza
        $this->assertSame('baixa', $porId[10] ?? null); // Maria (da) Silva
        // Confiança baixa não é pré-selecionada.
        $this->assertNull($this->campo($c, 'cliente_id')['sugestao']);
    }

    public function testSugestaoPorNomeContido(): void
    {
        $s = $this->service()->sugerirClientes([['nome' => 'JOÃO PEREIRA SOUZA E OUTROS', 'polo' => 'A']]);
        $this->assertSame(11, $s[0]['id_cliente']);
        $this->assertSame('media', $s[0]['confianca']);
    }

    // --------------------------------------------------------------- gravação

    // ------------------------------------------------- item sem conferência (MCP)

    public function testItemParaConfirmarProcessoNovoAceitaSugestoesEPrefereInformado(): void
    {
        $service = $this->service();
        [$informado] = $service->normalizarLinha(['pasta' => 'A-1', 'nomeOrgao' => '1ª Vara', 'etiquetas' => 'Urgente']);
        $djen = $this->djen([[
            'data_disponibilizacao' => '2026-01-10',
            'siglaTribunal'         => 'TJSP',
            'nomeOrgao'             => '2ª Vara',
            'destinatarios'         => [['polo' => 'A', 'nome' => 'Maria da Silva'], ['polo' => 'P', 'nome' => 'Fulano']],
        ]]);
        $datajud = $this->datajud([['_source' => [
            'numeroProcesso' => '08012340420258260100',
            'classe'         => ['codigo' => 7, 'nome' => 'Procedimento Comum Cível'],
        ]]]);

        $c    = $service->montarConferencia('08012340420258260100', $informado, $djen, $datajud, null);
        $item = $service->itemParaConfirmar($c);

        $this->assertNull($item['id_processo']);
        $this->assertSame('TJSP', $item['campos']['siglaTribunal']);
        $this->assertSame('1ª Vara', $item['campos']['nomeOrgao']);       // informado prevalece
        $this->assertSame('A-1', $item['campos']['pasta']);
        $this->assertSame(10, $item['campos']['cliente_id']);              // sugestão de confiança alta
        $this->assertSame('Maria da Silva - Procedimento Comum Cível', $item['campos']['titulo_processo']);
        $this->assertCount(2, $item['partes']);
        $this->assertSame(['Urgente'], $item['etiquetas']);

        // Cliente escolhido substitui a sugestão e refaz o título gerado.
        $item = $service->itemParaConfirmar($c, 11);
        $this->assertSame(11, $item['campos']['cliente_id']);
        $this->assertSame('João Pereira Souza - Procedimento Comum Cível', $item['campos']['titulo_processo']);
    }

    public function testItemParaConfirmarCadastradoSoCompletaSemApagarNemMudarBloqueados(): void
    {
        $carteira = [
            'id_processo' => 99, 'deleted_at' => null, 'titulo_processo' => 'Maria x Banco', 'cliente_id' => 10,
            'cliente_nome' => 'Maria da Silva', 'nomeOrgao' => '1ª Vara', 'siglaTribunal' => null,
            'responsavel_id' => 5, 'pasta' => 'B-2',
            'partes' => [['nome' => 'Maria da Silva', 'polo' => 'A']],
            'etiquetas' => [],
        ];
        $djen = $this->djen([[
            'data_disponibilizacao' => '2026-01-10',
            'siglaTribunal'         => 'TJSP',
            'nomeOrgao'             => '2ª Vara',
            'destinatarios'         => [['polo' => 'A', 'nome' => 'MARIA DA SILVA'], ['polo' => 'P', 'nome' => 'Banco Exemplo S.A.']],
        ]]);
        [$informado] = $this->service()->normalizarLinha(['responsavel' => 'fabiano', 'pasta' => 'C-3']);

        $service = $this->service();
        $item    = $service->itemParaConfirmar($service->montarConferencia('08012340420258260100', $informado, $djen, $this->datajud([]), $carteira));

        $this->assertSame(99, $item['id_processo']);
        $this->assertSame(['siglaTribunal' => 'TJSP', 'pasta' => 'C-3'], array_intersect_key($item['campos'], ['siglaTribunal' => 1, 'pasta' => 1]));
        $this->assertArrayNotHasKey('nomeOrgao', $item['campos']);        // mantém a carteira
        $this->assertArrayNotHasKey('titulo_processo', $item['campos']);
        $this->assertArrayNotHasKey('cliente_id', $item['campos']);
        $this->assertArrayNotHasKey('responsavel_id', $item['campos']);   // bloqueado
        $this->assertSame([['nome' => 'Banco Exemplo S.A.', 'polo' => 'P']], $item['partes']);
    }

    public function testItemParaConfirmarProcessoExcluido(): void
    {
        $service = $this->service();
        $c = $service->montarConferencia('08012340420258260100', [], $this->djen([]), $this->datajud([]), ['id_processo' => 7, 'deleted_at' => '2026-01-01']);

        $this->expectException(InvalidArgumentException::class);
        $service->itemParaConfirmar($c);
    }

    public function testConfirmarCriaProcessoNovoComTituloPadraoEPartes(): void
    {
        $processos = $this->createMock(ProcessosService::class);
        $processos->expects($this->once())->method('salvarProcesso')
            ->with(
                $this->callback(function (array $d): bool {
                    $this->assertSame('08012340420258260100', $d['numero_processo']);
                    $this->assertSame(self::NUM_1, $d['numeroprocessocommascara']);
                    $this->assertSame('Maria da Silva - Procedimento Comum', $d['titulo_processo']);
                    $this->assertSame(10, $d['cliente_id']);
                    $this->assertSame('Possível', $d['risco']);
                    $this->assertSame('2025-01-10', $d['dataDistribuicao']);
                    $this->assertArrayNotHasKey('inventado', $d);
                    return true;
                }),
                null,
                [['nome' => 'Maria da Silva']],
                [['nome' => 'Banco Exemplo S.A.']]
            )
            ->willReturn(123);
        $processos->expects($this->once())->method('gerenciarEtiquetas')->with(123, 8, true, false);

        $etiquetas = $this->createMock(EtiquetasService::class);
        $etiquetas->method('encontrarOuCriar')->with('Consumidor')->willReturn(8);

        $r = $this->service(null, $processos, $etiquetas)->confirmar([[
            'numero_processo' => self::NUM_1,
            'id_processo'     => null,
            'campos'          => [
                'titulo_processo'  => '',
                'cliente_id'       => '10',
                'tipoDocumento'    => 'Procedimento Comum',
                'dataDistribuicao' => '10/01/2025',
                'inventado'        => 'x',
            ],
            'partes'    => [['nome' => 'Maria da Silva', 'polo' => 'A'], ['nome' => 'Banco Exemplo S.A.', 'polo' => 'P'], ['nome' => 'MARIA DA SILVA', 'polo' => 'A']],
            'etiquetas' => ['Consumidor'],
        ]]);

        $this->assertSame(1, $r['criados']);
        $this->assertSame('criado', $r['resultados'][0]['status']);
        $this->assertSame(123, $r['resultados'][0]['id_processo']);
    }

    public function testConfirmarAtualizaCadastradoSemApagarPartesEIgnoraCamposBloqueados(): void
    {
        $repo = $this->repo(
            ['id_processo' => 99, 'deleted_at' => null],
            [['nome' => 'Maria da Silva', 'polo' => 'A']],
            ['Urgente']
        );

        $processos = $this->createMock(ProcessosService::class);
        $processos->expects($this->never())->method('salvarProcesso');
        $processos->expects($this->once())->method('atualizarProcessoSemRemoverPartes')
            ->with(99, ['nomeOrgao' => '2ª Vara'], [['nome' => 'Banco Exemplo S.A.', 'polo' => 'P']]);
        $processos->expects($this->never())->method('gerenciarEtiquetas'); // "urgente" já existe

        $r = $this->service($repo, $processos)->confirmar([[
            'numero_processo' => self::NUM_1,
            'id_processo'     => 99,
            'campos'          => ['nomeOrgao' => '2ª Vara', 'responsavel_id' => 5, 'visibilidade' => 'restrito', 'titulo_processo' => ''],
            'partes'          => [['nome' => 'MARIA DA SILVA', 'polo' => 'A'], ['nome' => 'Banco Exemplo S.A.', 'polo' => 'P']],
            'etiquetas'       => ['urgente'],
        ]], fn (int $id): bool => $id === 99);

        $this->assertSame(1, $r['atualizados']);
    }

    public function testConfirmarErrosPorItemNaoInterrompemOLote(): void
    {
        $repoExistente = $this->repo(['id_processo' => 99, 'deleted_at' => null]);
        $processos = $this->createMock(ProcessosService::class);
        $processos->expects($this->never())->method('salvarProcesso');
        $processos->expects($this->never())->method('atualizarProcessoSemRemoverPartes');

        $r = $this->service($repoExistente, $processos)->confirmar([
            ['numero_processo' => self::NUM_1, 'id_processo' => null],                     // cadastrado depois da conferência
            ['numero_processo' => self::NUM_1, 'id_processo' => 98],                       // id não confere
            ['numero_processo' => self::NUM_1, 'id_processo' => 99],                       // sem permissão
            ['numero_processo' => '123', 'id_processo' => null],                           // número inválido
        ], fn (int $id): bool => false);

        $this->assertSame(4, $r['falhas']);
        $this->assertStringContainsString('depois da conferência', $r['resultados'][0]['mensagem']);
        $this->assertStringContainsString('não corresponde', $r['resultados'][1]['mensagem']);
        $this->assertStringContainsString('permissão', $r['resultados'][2]['mensagem']);
        $this->assertStringContainsString('CNJ', $r['resultados'][3]['mensagem']);
    }

    public function testConfirmarSemAlteracao(): void
    {
        $repo = $this->repo(['id_processo' => 99, 'deleted_at' => null], [['nome' => 'Maria', 'polo' => 'A']]);
        $processos = $this->createMock(ProcessosService::class);
        $processos->expects($this->never())->method('atualizarProcessoSemRemoverPartes');

        $r = $this->service($repo, $processos)->confirmar([[
            'numero_processo' => self::NUM_1, 'id_processo' => 99, 'campos' => [], 'partes' => [['nome' => 'maria', 'polo' => 'A']],
        ]]);

        $this->assertSame(1, $r['sem_alteracao']);
    }

    public function testResponsavelPrecisaSerAdvogadoAtivoNoProcessoNovo(): void
    {
        $r = $this->service()->confirmar(
            [['numero_processo' => self::NUM_1, 'campos' => ['responsavel_id' => 5]]],
            null,
            fn (int $id): bool => false
        );

        $this->assertSame(1, $r['falhas']);
        $this->assertStringContainsString('advogado ativo', $r['resultados'][0]['mensagem']);
    }

    public function testSanitizarCamposRejeitaValoresInvalidos(): void
    {
        $s = $this->service();

        foreach ([
            ['cliente_id' => 999],
            ['risco' => 'Alto'],
            ['dataDistribuicao' => '2025-13-01'],
            ['link' => 'javascript:alert(1)'],
            ['visibilidade' => 'secreto'],
        ] as $campos) {
            try {
                $s->sanitizarCampos($campos);
                $this->fail('Deveria rejeitar ' . json_encode($campos));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(
            ['valorCausa' => '1500.00', 'ramo_direito' => 3, 'pasta' => null],
            $s->sanitizarCampos(['valorCausa' => '1.500', 'ramo_direito' => '3', 'pasta' => ''])
        );
    }
}
