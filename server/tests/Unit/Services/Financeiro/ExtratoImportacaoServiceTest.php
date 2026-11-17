<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ImportacaoEmAndamentoException;
use App\Services\Financeiro\Extrato\ImportacaoRequerConfirmacaoException;
use App\Services\Financeiro\ExtratoImportacaoService;
use App\Services\Financeiro\SaldoMensalService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * Importação idempotente de extratos, com repositórios em memória.
 * Todos os dados são fictícios.
 */
class ExtratoImportacaoServiceTest extends CIUnitTestCase
{
    private FakeImportacoes $importacoes;
    private FakeMovimentos $movimentos;
    private FakeDb $conexao;
    private FakeSaldo $saldo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importacoes = new FakeImportacoes();
        $this->movimentos  = new FakeMovimentos();
        $this->conexao          = new FakeDb($this->importacoes, $this->movimentos);
        $this->saldo       = new FakeSaldo();
    }

    private function servico(array $conta = null): ExtratoImportacaoService
    {
        $contas = new FakeContas($conta ?? ['id_conta' => 3, 'conta' => 'Conta teste', 'banco' => '999', 'agencia' => '0001', 'numero_conta' => '12345-6']);

        return new ExtratoImportacaoService($this->importacoes, $this->movimentos, $contas, $this->saldo, null, $this->conexao);
    }

    /**
     * OFX SGML fictício. Cada item: [data AAAAMMDD, valor, FITID|null, memo].
     */
    private function ofx(array $itens, string $inicio = '20261001', string $fim = '20261031', string $acct = '00012345-6'): string
    {
        $t = "OFXHEADER:100\r\nDATA:OFXSGML\r\nVERSION:102\r\nSECURITY:NONE\r\nENCODING:USASCII\r\nCHARSET:1252\r\n\r\n<OFX>\r\n"
            . "<BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL<BANKACCTFROM><BANKID>999<BRANCHID>0001<ACCTID>{$acct}<ACCTTYPE>CHECKING</BANKACCTFROM>\r\n"
            . "<BANKTRANLIST><DTSTART>{$inicio}<DTEND>{$fim}\r\n";
        foreach ($itens as [$data, $valor, $fitid, $memo]) {
            $t .= "<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>{$data}<TRNAMT>{$valor}" . ($fitid !== null ? "<FITID>{$fitid}" : '') . "<MEMO>{$memo}</STMTTRN>\r\n";
        }

        return $t . "</BANKTRANLIST><LEDGERBAL><BALAMT>100.00<DTASOF>{$fim}</LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>\r\n";
    }

    private function importar(string $conteudo, bool $confirmar = false, ?ExtratoImportacaoService $s = null): array
    {
        return ($s ?? $this->servico())->importar(3, 'extrato.ofx', 'application/x-ofx', $conteudo, $confirmar, 7);
    }

    private function dias(int $de, int $ate, string $prefixo = 'F'): array
    {
        $itens = [];
        for ($d = $de; $d <= $ate; $d++) {
            $itens[] = [sprintf('202610%02d', $d), '-' . $d . '.00', $prefixo . $d, 'Tarifa ' . $d];
        }

        return $itens;
    }

    public function testImportarDuasVezesNaoDuplica(): void
    {
        $arq = $this->ofx($this->dias(1, 5));

        $primeira = $this->importar($arq);
        $segunda  = $this->importar($arq);

        $this->assertSame(5, $primeira['lidos']);
        $this->assertSame(5, $primeira['novos']);
        $this->assertSame(0, $segunda['novos']);
        $this->assertSame(5, $segunda['duplicados']);
        $this->assertCount(5, $this->movimentos->linhas);
        $this->assertSame(['2026-10-01', '2026-10-31'], $primeira['periodo']);
        $this->assertSame('arquivo_ja_enviado', $segunda['alertas'][0]['codigo']);
    }

    public function testArquivosComPeriodosSobrepostosGeramAUniao(): void
    {
        $this->importar($this->ofx($this->dias(1, 15), '20261001', '20261015'));
        $b = $this->importar($this->ofx($this->dias(10, 30), '20261010', '20261030'));

        $this->assertCount(30, $this->movimentos->linhas);
        $this->assertSame(21, $b['lidos']);
        $this->assertSame(15, $b['novos']);
        $this->assertSame(6, $b['duplicados']);
    }

    public function testSemFitidUsaHashEContaOperacoesIguaisNoMesmoDia(): void
    {
        $arq = $this->ofx([
            ['20261005', '-10.00', null, 'Cafe'],
            ['20261005', '-10.00', null, 'Cafe'],
        ]);

        $this->assertSame(2, $this->importar($arq)['novos']);
        $this->assertSame(0, $this->importar($arq)['novos']);
        $this->assertCount(2, $this->movimentos->linhas);
    }

    public function testContaDoArquivoDiferenteDaEscolhidaRetorna422SemGravar(): void
    {
        $arq = $this->ofx($this->dias(1, 3), acct: '00099999-9');

        try {
            $this->importar($arq);
            $this->fail('Era esperada ExtratoInvalidoException.');
        } catch (ExtratoInvalidoException $e) {
            $this->assertStringContainsString('não confere', $e->getMessage());
        }

        $this->assertSame([], $this->importacoes->linhas);
        $this->assertSame([], $this->movimentos->linhas);
        $this->assertSame(0, $this->conexao->begins);
    }

    public function testContaSemNumeroCadastradoNaoPodeSerConferida(): void
    {
        $this->expectException(ExtratoInvalidoException::class);
        $this->importar($this->ofx($this->dias(1, 2)), false, $this->servico(['id_conta' => 3, 'conta' => 'X', 'banco' => '', 'agencia' => '', 'numero_conta' => '']));
    }

    public function testContaCadastradaComNomeDeBancoComparaSoAgenciaEConta(): void
    {
        $s = $this->servico(['id_conta' => 3, 'conta' => 'X', 'banco' => 'Banco Fictício', 'agencia' => '1', 'numero_conta' => '123456']);

        $this->assertSame(2, $this->importar($this->ofx($this->dias(1, 2)), false, $s)['novos']);
    }

    public function testErroNoMeioFazRollbackCompleto(): void
    {
        $this->movimentos->falharNaInsercao = 3;

        try {
            $this->importar($this->ofx($this->dias(1, 5)));
            $this->fail('Era esperada exceção.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada', $e->getMessage());
        }

        $this->assertSame(1, $this->conexao->rollbacks);
        $this->assertSame(0, $this->conexao->commits);
        // O fake desfaz tudo no rollback, como o banco faria.
        $this->assertSame([], $this->importacoes->linhas);
        $this->assertSame([], $this->movimentos->linhas);
        $this->assertSame(0, $this->importacoes->locksAbertos);
    }

    public function testLockOcupadoRecusaAImportacao(): void
    {
        $this->importacoes->lockLivre = false;

        $this->expectException(ImportacaoEmAndamentoException::class);
        $this->importar($this->ofx($this->dias(1, 2)));
    }

    public function testSegundaImportacaoConcorrenteDoMesmoArquivoVeZeroNovos(): void
    {
        // O lock serializa as chamadas e a análise é refeita dentro dele: quem entra depois
        // enxerga o que a primeira gravou.
        $arq = $this->ofx($this->dias(1, 4));

        $a = $this->importar($arq);
        $b = $this->importar($arq);

        $this->assertSame([4, 0], [$a['novos'], $b['novos']]);
        $this->assertCount(4, $this->movimentos->linhas);
        $this->assertSame(0, $this->importacoes->locksAbertos);
    }

    public function testMesmoFitidComValorDiferenteViraConflitoENaoAltera(): void
    {
        $this->importar($this->ofx([['20261005', '-10.00', 'F1', 'Tarifa']]));
        $r = $this->importar($this->ofx([['20261005', '-99.00', 'F1', 'Tarifa']]));

        $this->assertSame(0, $r['novos']);
        $this->assertSame(1, $r['conflitos']);
        $this->assertSame(-1000, $this->movimentos->linhas[0]['valor_centavos']);
        $this->assertSame(-9900, $r['conflitos_detalhe'][0]['valor_centavos']);
        $this->assertArrayNotHasKey('memo', $r['conflitos_detalhe'][0]);
    }

    public function testReemissaoDeFitidExigeConfirmacao(): void
    {
        $this->importar($this->ofx($this->dias(1, 4, 'A')));
        $reemitido = $this->ofx($this->dias(1, 4, 'B'));

        try {
            $this->importar($reemitido);
            $this->fail('Era esperada ImportacaoRequerConfirmacaoException.');
        } catch (ImportacaoRequerConfirmacaoException $e) {
            $this->assertSame('possivel_reemissao_de_fitid', $e->alertas()[0]['codigo']);
        }
        $this->assertCount(4, $this->movimentos->linhas);

        $this->assertSame(4, $this->importar($reemitido, true)['novos']);
    }

    public function testPeriodoJaCobertoEInformativo(): void
    {
        $this->importar($this->ofx($this->dias(1, 5), '20261001', '20261031'));
        $r = $this->importar($this->ofx($this->dias(6, 7, 'G'), '20261005', '20261020'));

        $codigos = array_column($r['alertas'], 'codigo');
        $this->assertContains('periodo_ja_coberto', $codigos);
        $this->assertSame(2, $r['novos']);
    }

    public function testMesFechadoEntraComoPendenteESinaliza(): void
    {
        $this->saldo->fechados = ['2026-10'];

        $r = $this->importar($this->ofx($this->dias(1, 3)));

        $this->assertSame(3, $r['novos']);
        $alerta = array_values(array_filter($r['alertas'], fn ($a) => $a['codigo'] === 'mes_fechado'))[0];
        $this->assertSame(['2026-10'], $alerta['meses']);
        $this->assertSame('pendente', $this->movimentos->linhas[0]['status']);
    }

    public function testPreviaNaoGravaNada(): void
    {
        $r = $this->servico()->previa(3, 'extrato.ofx', 'application/x-ofx', $this->ofx($this->dias(1, 3)));

        $this->assertSame(3, $r['novos']);
        $this->assertNull($r['importacao_id']);
        $this->assertCount(3, $r['movimentos']);
        $this->assertSame([], $this->importacoes->linhas);
        $this->assertSame([], $this->movimentos->linhas);
        $this->assertSame(0, $this->conexao->begins);
    }

    public function testMovimentosSaoGravadosSemAuditoriaAutomaticaDeMemo(): void
    {
        $this->importar($this->ofx($this->dias(1, 2)));

        $this->assertSame([false, false], $this->movimentos->auditar);
    }

    public function testArquivoInvalidoNaoGrava(): void
    {
        $this->expectException(ExtratoInvalidoException::class);
        $this->servico()->importar(3, 'extrato.txt', 'text/plain', 'qualquer coisa');
    }
}

// ---------------------------------------------------------------------------
// Dublês em memória (sem banco)
// ---------------------------------------------------------------------------

class FakeContas extends ContasRepository
{
    public function __construct(private array $conta)
    {
    }

    public function findById(int $id): ?array
    {
        return $id === (int) $this->conta['id_conta'] ? $this->conta : null;
    }
}

class FakeSaldo extends SaldoMensalService
{
    /** @var string[] AAAA-MM */
    public array $fechados = [];

    public function __construct()
    {
    }

    public function verificarBloqueioMensal(string $tipo, ?int $contaId, string $data): void
    {
        if (in_array(substr($data, 0, 7), $this->fechados, true)) {
            throw new \Exception('fechado');
        }
    }
}

class FakeImportacoes extends ExtratosImportacoesRepository
{
    public array $linhas = [];
    public bool $lockLivre = true;
    public int $locksAbertos = 0;

    public function __construct()
    {
    }

    public function obterLockConta(int $contaId, int $segundosEspera = 15): bool
    {
        if (!$this->lockLivre) {
            return false;
        }
        $this->locksAbertos++;

        return true;
    }

    public function liberarLockConta(int $contaId): void
    {
        $this->locksAbertos--;
    }

    public function create(array $data): int
    {
        $data['id_importacao'] = count($this->linhas) + 1;
        $data['created_at']    = '2026-11-03 10:00:00';
        $this->linhas[]        = $data;

        return $data['id_importacao'];
    }

    public function update(int $id, array $data): bool
    {
        $this->linhas[$id - 1] = $data + $this->linhas[$id - 1];

        return true;
    }

    public function findById(int $id): ?array
    {
        return $this->linhas[$id - 1] ?? null;
    }

    public function buscarPorHash(int $contaId, string $arquivoHash): array
    {
        return array_values(array_filter($this->linhas, fn ($l) => $l['conta_id'] === $contaId && $l['arquivo_hash'] === $arquivoHash));
    }

    public function listarConcluidasPorConta(int $contaId): array
    {
        return array_values(array_filter($this->linhas, fn ($l) => $l['conta_id'] === $contaId && $l['status'] === 'concluida'));
    }
}

class FakeMovimentos extends ExtratosMovimentosRepository
{
    public array $linhas = [];
    public array $auditar = [];
    public int $falharNaInsercao = 0;

    public function __construct()
    {
    }

    public function buscarPorChaves(int $contaId, array $chaves): array
    {
        $r = [];
        foreach ($this->linhas as $l) {
            if ($l['conta_id'] === $contaId && in_array($l['chave'], $chaves, true)) {
                $r[$l['chave']] = $l;
            }
        }

        return $r;
    }

    public function inserirSeNovo(array $dados, bool $auditar = true): ?int
    {
        if ($this->falharNaInsercao && count($this->linhas) + 1 === $this->falharNaInsercao) {
            throw new RuntimeException('falha simulada');
        }
        $this->auditar[] = $auditar;
        if ($this->buscarPorChaves($dados['conta_id'], [$dados['chave']])) {
            return null;
        }
        $dados['id_movimento'] = count($this->linhas) + 1;
        $this->linhas[]        = $dados;

        return $dados['id_movimento'];
    }

    public function listarPorPeriodo(int $contaId, string $dataInicio, string $dataFim): array
    {
        return array_values(array_filter($this->linhas, fn ($l) => $l['conta_id'] === $contaId && $l['data'] >= $dataInicio && $l['data'] <= $dataFim));
    }
}

/** Conexão que só conta begin/commit/rollback; no rollback desfaz o que os fakes gravaram. */
class FakeDb
{
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    private array $copia = [];

    public function __construct(private FakeImportacoes $importacoes, private FakeMovimentos $movimentos)
    {
    }

    public function transBegin(): bool
    {
        $this->begins++;
        $this->copia = [$this->importacoes->linhas, $this->movimentos->linhas];

        return true;
    }

    public function transCommit(): bool
    {
        $this->commits++;

        return true;
    }

    public function transRollback(): bool
    {
        $this->rollbacks++;
        [$this->importacoes->linhas, $this->movimentos->linhas] = $this->copia;

        return true;
    }
}
