<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\ExtratoImportacaoService;
use App\Services\Financeiro\SaldoMensalService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Regressão de idempotência da importação de extratos, de ponta a ponta contra um banco real
 * (SQLite em memória), sem dublês de repositório: vale também o índice único (conta, chave).
 * Só dados fictícios.
 */
class ExtratoIdempotenciaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    /** @var string[] AAAA-MM */
    private array $mesesFechados = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['120000_CreateFinanceiroExtratos', '130000_AddConciliacaoExtratosMovimentos', '150000_AddSaldoDataExtratosImportacoes'] as $m) {
            require_once APPPATH . "Database/Migrations/Financeiro/2026-11-03-{$m}.php";
        }

        $this->derrubarTabelas();
        $forge = Database::forge($this->db);
        (new \App\Database\Migrations\CreateFinanceiroExtratos($forge))->up();
        (new \App\Database\Migrations\AddConciliacaoExtratosMovimentos($forge))->up();
        (new \App\Database\Migrations\AddSaldoDataExtratosImportacoes($forge))->up();

        $this->mesesFechados = [];
    }

    protected function tearDown(): void
    {
        $this->derrubarTabelas();
        parent::tearDown();
    }

    private function derrubarTabelas(): void
    {
        $forge = Database::forge($this->db);
        $forge->dropTable('fin_extratos_movimentos', true);
        $forge->dropTable('fin_extratos_importacoes', true);
    }

    /** Serviço com repositórios reais; só conta e fechamento de mês são dublês. */
    private function servico(int $contaId = 3, string $numero = '12345-6'): ExtratoImportacaoService
    {
        $conta = ['id_conta' => $contaId, 'conta' => 'Conta teste', 'banco' => '999', 'agencia' => '0001', 'numero_conta' => $numero];

        $contas = new class ($conta) extends ContasRepository {
            public function __construct(private array $conta)
            {
            }

            public function findById(int $id): ?array
            {
                return $id === (int) $this->conta['id_conta'] ? $this->conta : null;
            }
        };

        $fechados = $this->mesesFechados;
        $saldo    = new class ($fechados) extends SaldoMensalService {
            public function __construct(private array $fechados)
            {
            }

            public function verificarBloqueioMensal(string $tipo, ?int $contaId, string $data): void
            {
                if (in_array(substr($data, 0, 7), $this->fechados, true)) {
                    throw new \Exception('fechado');
                }
            }
        };

        // A auditoria do model depende da sessão do usuário (Shield), que não existe nestes testes.
        $importacoes = new class () extends ExtratosImportacoesRepository {
            public function create(array $data): int
            {
                $this->model->allowCallbacks(false);

                return parent::create($data);
            }

            public function update(int $id, array $data): bool
            {
                $this->model->allowCallbacks(false);

                return parent::update($id, $data);
            }
        };

        return new ExtratoImportacaoService($importacoes, new ExtratosMovimentosRepository(), $contas, $saldo);
    }

    /** @param array<int,array{0:string,1:string,2:?string,3:string}> $itens [data AAAAMMDD, valor, FITID|null, memo] */
    private function ofx(array $itens, string $acct = '00012345-6', string $inicio = '20261001', string $fim = '20261031'): string
    {
        $t = "OFXHEADER:100\r\nDATA:OFXSGML\r\nVERSION:102\r\nSECURITY:NONE\r\nENCODING:USASCII\r\nCHARSET:1252\r\n\r\n<OFX>\r\n"
            . "<BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>BRL<BANKACCTFROM><BANKID>999<BRANCHID>0001<ACCTID>{$acct}<ACCTTYPE>CHECKING</BANKACCTFROM>\r\n"
            . "<BANKTRANLIST><DTSTART>{$inicio}<DTEND>{$fim}\r\n";
        foreach ($itens as [$data, $valor, $fitid, $memo]) {
            $t .= "<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>{$data}<TRNAMT>{$valor}" . ($fitid !== null ? "<FITID>{$fitid}" : '') . "<MEMO>{$memo}</STMTTRN>\r\n";
        }

        return $t . "</BANKTRANLIST><LEDGERBAL><BALAMT>100.00<DTASOF>{$fim}</LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>\r\n";
    }

    private function dias(int $de, int $ate): array
    {
        $itens = [];
        for ($d = $de; $d <= $ate; $d++) {
            $itens[] = [sprintf('202610%02d', $d), '-' . $d . '.00', 'F' . $d, 'Tarifa ' . $d];
        }

        return $itens;
    }

    private function importar(string $conteudo, ?ExtratoImportacaoService $s = null, int $contaId = 3): array
    {
        return ($s ?? $this->servico($contaId))->importar($contaId, 'extrato.ofx', 'application/x-ofx', $conteudo, false, 7);
    }

    private function total(?int $contaId = null): int
    {
        $b = $this->db->table('fin_extratos_movimentos');
        if ($contaId !== null) {
            $b->where('conta_id', $contaId);
        }

        return $b->countAllResults();
    }

    /** Foto estável dos movimentos de uma conta, para provar que "nada foi alterado". */
    private function foto(int $contaId): array
    {
        return $this->db->table('fin_extratos_movimentos')->where('conta_id', $contaId)->orderBy('chave')->get()->getResultArray();
    }

    public function testMesmoArquivoVariasVezesSoGravaNaPrimeira(): void
    {
        $arq = $this->ofx($this->dias(1, 6));

        $this->assertSame(6, $this->importar($arq)['novos']);
        $foto = $this->foto(3);

        foreach ([2, 3] as $vez) {
            $r = $this->importar($arq);
            $this->assertSame(0, $r['novos'], "importação nº {$vez}");
            $this->assertSame(6, $r['duplicados']);
        }

        $this->assertSame(6, $this->total());
        $this->assertSame($foto, $this->foto(3));
    }

    /** @return array<string,array{0:bool}> Ordem de importação dos dois arquivos. */
    public static function ordens(): array
    {
        return ['A depois B' => [true], 'B depois A' => [false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ordens')]
    public function testArquivosSobrepostosDaoOMesmoResultadoEmQualquerOrdem(bool $aPrimeiro): void
    {
        $a = $this->ofx($this->dias(1, 15), '00012345-6', '20261001', '20261015');
        $b = $this->ofx($this->dias(10, 30), '00012345-6', '20261010', '20261030');

        $this->importar($aPrimeiro ? $a : $b);
        $this->importar($aPrimeiro ? $b : $a);

        $this->assertSame(30, $this->total());
        $chaves = array_column($this->foto(3), 'chave');
        $this->assertCount(30, array_unique($chaves));
    }

    public function testMesmoFitidEmContasDiferentesImportaNasDuas(): void
    {
        $itens = [['20261005', '-10.00', 'MESMO-FITID', 'Tarifa']];

        $this->assertSame(1, $this->importar($this->ofx($itens, '00012345-6'), $this->servico(3, '12345-6'), 3)['novos']);
        $this->assertSame(1, $this->importar($this->ofx($itens, '00077777-7'), $this->servico(4, '77777-7'), 4)['novos']);

        $this->assertSame(1, $this->total(3));
        $this->assertSame(1, $this->total(4));
        $this->assertNotSame($this->foto(3)[0]['chave'], $this->foto(4)[0]['chave']);
    }

    public function testMesmoFitidComValorDiferenteViraConflitoENadaMuda(): void
    {
        $this->importar($this->ofx([['20261005', '-10.00', 'F1', 'Tarifa']]));
        $foto = $this->foto(3);

        $r = $this->importar($this->ofx([['20261005', '-99.00', 'F1', 'Tarifa']]));

        $this->assertSame(0, $r['novos']);
        $this->assertSame(1, $r['conflitos']);
        $this->assertSame($foto, $this->foto(3));
    }

    public function testSemFitidDoisItensIguaisNoMesmoDiaViramDoisEReimportarNaoDuplica(): void
    {
        $arq = $this->ofx([
            ['20261005', '-10.00', null, 'Cafe'],
            ['20261005', '-10.00', null, 'Cafe'],
        ]);

        $this->assertSame(2, $this->importar($arq)['novos']);
        $this->assertSame(0, $this->importar($arq)['novos']);
        $this->assertSame(2, $this->total());
    }

    public function testDuasImportacoesSeguidasDoMesmoArquivoNaoDuplicamMesmoSemOLockDoServico(): void
    {
        // Simula a corrida: a segunda requisição fez a análise antes de a primeira gravar,
        // então o serviço tentaria inserir tudo de novo. O índice único (conta, chave) barra.
        $arq   = $this->ofx($this->dias(1, 4));
        $repo  = new ExtratosMovimentosRepository();
        $this->importar($arq);

        $existente = $this->foto(3)[0];
        unset($existente['id_movimento'], $existente['deleted_at']);

        $this->assertNull($repo->inserirSeNovo($existente, false));
        $this->assertSame(4, $this->total());
    }

    public function testArquivoDeOutraContaEhRecusadoSemGravar(): void
    {
        try {
            $this->importar($this->ofx($this->dias(1, 3), '00099999-9'));
            $this->fail('Era esperada ExtratoInvalidoException.');
        } catch (ExtratoInvalidoException) {
            $this->assertSame(0, $this->total());
            $this->assertSame(0, $this->db->table('fin_extratos_importacoes')->countAllResults());
        }
    }

    public function testExcluirPagamentoVinculadoDevolveParaPendenteENaoReimportaNemDuplica(): void
    {
        $arq = $this->ofx([['20261005', '-10.00', 'F1', 'Tarifa']]);
        $this->importar($arq);

        // Movimento lançado, ligado a um pagamento fictício.
        $this->db->table('fin_extratos_movimentos')->where('conta_id', 3)->update([
            'status' => 'lancado', 'pagamento_tipo' => 'despesa', 'pagamento_id' => 55,
        ]);
        $id = (int) $this->foto(3)[0]['id_movimento'];

        // Exclusão do pagamento (é o que LiberaMovimentosExtratoTrait faz na mesma transação).
        $this->assertSame([$id], (new ExtratosMovimentosRepository())->liberarPorPagamentos('despesa', [55]));

        $linha = $this->foto(3)[0];
        $this->assertSame('pendente', $linha['status']);
        $this->assertNull($linha['pagamento_id']);

        $r = $this->importar($arq);
        $this->assertSame(0, $r['novos']);
        $this->assertSame(1, $r['duplicados']);
        $this->assertSame(1, $this->total());
        $this->assertSame('pendente', $this->foto(3)[0]['status']);
    }

    public function testMovimentoExcluidoContinuaOcupandoAChave(): void
    {
        $arq = $this->ofx([['20261005', '-10.00', 'F1', 'Tarifa']]);
        $this->importar($arq);
        $this->db->table('fin_extratos_movimentos')->update(['deleted_at' => '2026-10-07 09:00:00']);

        $this->assertSame(0, $this->importar($arq)['novos']);
        $this->assertSame(1, $this->total());
    }

    public function testMesFechadoImportaComoHistoricoPendente(): void
    {
        $this->mesesFechados = ['2026-10'];

        $r = $this->importar($this->ofx($this->dias(1, 3)));

        $this->assertSame(3, $r['novos']);
        $this->assertContains('mes_fechado', array_column($r['alertas'], 'codigo'));
        foreach ($this->foto(3) as $linha) {
            $this->assertSame('pendente', $linha['status']);
        }
    }

    public function testImportacaoNaoGuardaOArquivoBruto(): void
    {
        $this->importar($this->ofx($this->dias(1, 2)));

        $colunas = $this->db->getFieldNames('fin_extratos_importacoes');
        foreach (['conteudo', 'arquivo', 'arquivo_bruto', 'ofx'] as $proibida) {
            $this->assertNotContains($proibida, $colunas);
        }
        // Só o hash do conteúdo é guardado.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->db->table('fin_extratos_importacoes')->get()->getRowArray()['arquivo_hash']);
    }
}
