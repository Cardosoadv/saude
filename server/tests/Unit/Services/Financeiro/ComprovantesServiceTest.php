<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ComprovantesRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Services\ConfiguracoesService;
use App\Services\Financeiro\ComprovantesService;
use App\Services\Financeiro\SaldoMensalService;
use App\Services\GoogleDriveService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * Upload de comprovantes: validação, pasta mensal e registro, com Drive e
 * repositórios simulados.
 */
class ComprovantesServiceTest extends CIUnitTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var string[] */
    private array $temporarios = [];

    private ComprovantesRepository $comprovantes;
    private PagamentoDespesasRepository $despesas;
    private PagamentoReceitasRepository $receitas;
    private ContasRepository $contas;
    private SaldoMensalService $saldo;
    private GoogleDriveService $drive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comprovantes = $this->createMock(ComprovantesRepository::class);
        $this->despesas     = $this->createMock(PagamentoDespesasRepository::class);
        $this->receitas     = $this->createMock(PagamentoReceitasRepository::class);
        $this->contas       = $this->createMock(ContasRepository::class);
        $this->saldo        = $this->createMock(SaldoMensalService::class);
        $this->drive        = $this->createMock(GoogleDriveService::class);

        $this->contas->method('findById')->willReturn(['id_conta' => 3, 'conta' => 'Banco do Brasil']);
        $this->comprovantes->method('listarPorPagamento')->willReturn([]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $arquivo) {
            @unlink($arquivo);
        }
        parent::tearDown();
    }

    private function servico(): ComprovantesService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturnCallback(static fn (string $nome, $padrao = null) => $padrao);

        return new ComprovantesService(
            $this->comprovantes,
            $this->drive,
            $this->despesas,
            $this->receitas,
            $this->contas,
            $this->saldo,
            $config
        );
    }

    private function arquivo(string $conteudo, string $nome = 'comprovante'): array
    {
        $caminho = tempnam(sys_get_temp_dir(), 'comp');
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;

        return ['path' => $caminho, 'nome' => $nome];
    }

    private function pdf(): array
    {
        return $this->arquivo("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n", 'pix.pdf');
    }

    private function despesaPaga(): void
    {
        $this->despesas->method('findById')->willReturn([
            'id_pgto_despesa'      => 15,
            'pagamento_despesa_dt' => '2026-10-05',
            'valor'                => '150.50',
            'conta_id'             => 3,
        ]);
    }

    public function testEnviaParaASubpastaDoMesDoPagamentoComNomePadronizado(): void
    {
        $this->despesaPaga();
        $this->drive->expects($this->once())->method('pastaMensal')->with('2026-10')->willReturn('pasta-2026-10');
        $this->drive->expects($this->once())->method('uploadFileToFolder')
            ->with($this->anything(), '2026-10-05_Banco-do-Brasil_150-50_D15_1.pdf', 'application/pdf', 'pasta-2026-10')
            ->willReturn(['id' => 'drive-1', 'link' => 'https://drive.google.com/file/d/drive-1/view']);
        $this->comprovantes->expects($this->once())->method('create')
            ->with($this->callback(static fn (array $r) => $r['tipo'] === 'despesa'
                && $r['pagamento_id'] === 15
                && $r['pasta_mes'] === '2026-10'
                && $r['drive_id'] === 'drive-1'
                && $r['enviado_por'] === 7))
            ->willReturn(99);

        $resultado = $this->servico()->enviar('despesa', 15, [$this->pdf()], 7);

        $this->assertCount(1, $resultado['enviados']);
        $this->assertSame(99, $resultado['enviados'][0]['id_comprovante']);
        $this->assertSame([], $resultado['erros']);
    }

    public function testReceitaUsaACampoDeDataDaReceita(): void
    {
        $this->receitas->method('findById')->willReturn([
            'pagamento_receita_dt' => '2026-09-30',
            'valor'                => '1000',
            'conta_id'             => 3,
        ]);
        $this->drive->expects($this->once())->method('pastaMensal')->with('2026-09')->willReturn('pasta-2026-09');
        $this->drive->method('uploadFileToFolder')->willReturn(['id' => 'd', 'link' => 'https://x/y']);
        $this->comprovantes->method('create')->willReturn(1);

        $resultado = $this->servico()->enviar('receita', 4, [$this->pdf()]);

        $this->assertSame('2026-09', $resultado['enviados'][0]['pasta_mes']);
        $this->assertStringContainsString('_R4_', $resultado['enviados'][0]['nome']);
    }

    public function testAceitaPngPeloConteudoMesmoComExtensaoErrada(): void
    {
        $this->despesaPaga();
        $this->drive->method('pastaMensal')->willReturn('pasta');
        $this->drive->expects($this->once())->method('uploadFileToFolder')
            ->with($this->anything(), $this->stringEndsWith('.png'), 'image/png', 'pasta')
            ->willReturn(['id' => 'd', 'link' => 'https://x/y']);
        $this->comprovantes->method('create')->willReturn(1);

        $this->servico()->enviar('despesa', 15, [$this->arquivo(base64_decode(self::PNG), 'foto.txt')]);
    }

    public function testRejeitaTipoNaoPermitidoSemTocarNoDrive(): void
    {
        $this->despesaPaga();
        $this->drive->expects($this->never())->method('pastaMensal');
        $this->drive->expects($this->never())->method('uploadFileToFolder');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não é permitido');
        $this->servico()->enviar('despesa', 15, [$this->arquivo('<?php echo 1;', 'comprovante.pdf')]);
    }

    public function testRejeitaArquivoMaiorQueODoLimite(): void
    {
        $this->despesaPaga();
        $grande = $this->pdf();
        $h = fopen($grande['path'], 'ab');
        ftruncate($h, 10 * 1024 * 1024 + 1);
        fclose($h);
        $this->drive->expects($this->never())->method('uploadFileToFolder');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('excede o limite de 10 MB');
        $this->servico()->enviar('despesa', 15, [$grande]);
    }

    public function testRejeitaMaisDeCincoArquivosPorEnvio(): void
    {
        $this->despesaPaga();
        $arquivos = [];
        for ($i = 0; $i < 6; $i++) {
            $arquivos[] = $this->pdf();
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no máximo 5');
        $this->servico()->enviar('despesa', 15, $arquivos);
    }

    public function testRejeitaEnvioSemArquivos(): void
    {
        $this->despesaPaga();

        $this->expectException(InvalidArgumentException::class);
        $this->servico()->enviar('despesa', 15, []);
    }

    public function testMesFechadoBloqueiaEnvioAntesDeQualquerChamadaAoDrive(): void
    {
        $this->despesaPaga();
        $this->saldo->method('verificarBloqueioMensal')
            ->willThrowException(new \Exception('O período 10/2026 já está fechado financeiramente.'));
        $this->drive->expects($this->never())->method('pastaMensal');

        $this->expectExceptionMessage('já está fechado');
        $this->servico()->enviar('despesa', 15, [$this->pdf()]);
    }

    public function testPagamentoInexistenteLancaNaoEncontrado(): void
    {
        $this->despesas->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->servico()->enviar('despesa', 999, [$this->pdf()]);
    }

    public function testTipoInvalidoELancadoComoArgumentoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servico()->enviar('transferencia', 1, [$this->pdf()]);
    }

    public function testFalhaDoDriveNaoCriaRegistroEEInformaOErro(): void
    {
        $this->despesaPaga();
        $this->drive->method('pastaMensal')->willReturn('pasta');
        $this->drive->method('uploadFileToFolder')->willThrowException(new \Exception('Quota exceeded'));
        $this->comprovantes->expects($this->never())->method('create');

        $resultado = $this->servico()->enviar('despesa', 15, [$this->pdf()]);

        $this->assertSame([], $resultado['enviados']);
        $this->assertSame('Quota exceeded', $resultado['erros'][0]['mensagem']);
    }

    public function testVariosArquivosRecebemSequenciaNoNome(): void
    {
        $this->despesaPaga();
        $this->drive->method('pastaMensal')->willReturn('pasta');
        $nomes = [];
        $this->drive->method('uploadFileToFolder')->willReturnCallback(
            static function (string $path, string $nome) use (&$nomes) {
                $nomes[] = $nome;

                return ['id' => $nome, 'link' => 'https://x/' . $nome];
            }
        );
        $this->comprovantes->method('create')->willReturn(1);

        $this->servico()->enviar('despesa', 15, [$this->pdf(), $this->pdf()]);

        $this->assertSame(['2026-10-05_Banco-do-Brasil_150-50_D15_1.pdf', '2026-10-05_Banco-do-Brasil_150-50_D15_2.pdf'], $nomes);
    }

    public function testAnexarTotaisUsaUmaConsultaParaTodasAsLinhas(): void
    {
        $this->comprovantes->expects($this->once())->method('contarPorPagamentos')
            ->with('despesa', [10, 11])->willReturn([10 => 2]);

        $linhas = $this->servico()->anexarTotais('despesa', [['id_pgto_despesa' => 10], ['id_pgto_despesa' => 11]], 'id_pgto_despesa');

        $this->assertSame(2, $linhas[0]['total_comprovantes']);
        $this->assertSame(0, $linhas[1]['total_comprovantes']);
    }

    private function comprovanteGravado(): array
    {
        return ['id_comprovante' => 99, 'tipo' => 'despesa', 'pagamento_id' => 15, 'drive_id' => 'drive-1'];
    }

    public function testExcluirMoveParaLixeiraDoDriveERemoveORegistro(): void
    {
        $this->despesaPaga();
        $this->comprovantes->method('findById')->with(99)->willReturn($this->comprovanteGravado());
        $this->drive->expects($this->once())->method('trashFile')->with('drive-1');
        $this->comprovantes->expects($this->once())->method('delete')->with(99)->willReturn(true);

        $this->servico()->excluir(99);
    }

    public function testExcluirMantemORegistroSeODriveFalhar(): void
    {
        $this->despesaPaga();
        $this->comprovantes->method('findById')->willReturn($this->comprovanteGravado());
        $this->drive->method('trashFile')->willThrowException(new \Exception('Falha ao mover o arquivo'));
        $this->comprovantes->expects($this->never())->method('delete');

        $this->expectExceptionMessage('Falha ao mover o arquivo');
        $this->servico()->excluir(99);
    }

    public function testExcluirEmMesFechadoNaoMexeNoDriveNemNoRegistro(): void
    {
        $this->despesaPaga();
        $this->comprovantes->method('findById')->willReturn($this->comprovanteGravado());
        $this->saldo->method('verificarBloqueioMensal')
            ->willThrowException(new \Exception('O período 10/2026 já está fechado financeiramente.'));
        $this->drive->expects($this->never())->method('trashFile');
        $this->comprovantes->expects($this->never())->method('delete');

        $this->expectExceptionMessage('já está fechado');
        $this->servico()->excluir(99);
    }

    public function testExcluirComprovanteDePagamentoJaEstornadoLimpaOAnexo(): void
    {
        $this->despesas->method('findById')->willReturn(null);
        $this->comprovantes->method('findById')->willReturn($this->comprovanteGravado());
        $this->drive->expects($this->once())->method('trashFile')->with('drive-1');
        $this->comprovantes->expects($this->once())->method('delete')->with(99)->willReturn(true);

        $this->servico()->excluir(99);
    }

    public function testExcluirComprovanteInexistente(): void
    {
        $this->comprovantes->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->servico()->excluir(1);
    }

    public function testEstornoRemoveOsAnexosMesmoQuandoODriveFalha(): void
    {
        $this->comprovantes = $this->createMock(ComprovantesRepository::class);
        $this->comprovantes->method('listarPorPagamentos')->with('despesa', [15, 16])->willReturn([
            ['id_comprovante' => 1, 'tipo' => 'despesa', 'pagamento_id' => 15, 'drive_id' => 'a'],
            ['id_comprovante' => 2, 'tipo' => 'despesa', 'pagamento_id' => 16, 'drive_id' => 'b'],
        ]);
        $this->drive->method('trashFile')->willReturnCallback(static function (string $id) {
            if ($id === 'a') {
                throw new \Exception('Drive indisponível');
            }
        });
        $this->comprovantes->expects($this->exactly(2))->method('delete')->willReturn(true);

        $this->assertSame(2, $this->servico()->removerPorPagamentos('despesa', [15, 16]));
    }

    private function pagamentosEmDoisMeses(): void
    {
        $this->despesas->method('findById')->willReturnCallback(static fn (int $id) => [
            'pagamento_despesa_dt' => $id === 15 ? '2026-10-05' : '2026-09-20',
            'valor'                => '100',
            'conta_id'             => 3,
        ]);
    }

    public function testLoteSobeOArquivoUmaVezPorMesECompartilhaOMesmoArquivo(): void
    {
        $this->pagamentosEmDoisMeses();
        $this->drive->expects($this->exactly(2))->method('pastaMensal')->willReturnCallback(static fn (string $m) => 'pasta-' . $m);
        $this->drive->expects($this->exactly(2))->method('uploadFileToFolder')
            ->willReturnCallback(static fn ($p, $n, $m, string $pasta) => ['id' => 'drive-' . $pasta, 'link' => 'https://x/' . $pasta]);
        $this->comprovantes->expects($this->exactly(3))->method('create')->willReturn(1);

        $resultado = $this->servico()->enviarLote([
            ['tipo' => 'despesa', 'id' => 15],
            ['tipo' => 'despesa', 'id' => 16],
            ['tipo' => 'despesa', 'id' => 17],
        ], [$this->pdf()], 7);

        $this->assertSame(3, $resultado['pagamentos']);
        $this->assertCount(3, $resultado['enviados']);
        $this->assertSame([], $resultado['erros']);
    }

    public function testLoteComMesFechadoNaoEnviaNada(): void
    {
        $this->pagamentosEmDoisMeses();
        $this->saldo->method('verificarBloqueioMensal')->willReturnCallback(static function ($t, $c, string $data) {
            if (str_starts_with($data, '2026-09')) {
                throw new \Exception('O período 09/2026 já está fechado financeiramente.');
            }
        });
        $this->drive->expects($this->never())->method('uploadFileToFolder');
        $this->comprovantes->expects($this->never())->method('create');

        $this->expectExceptionMessage('já está fechado');
        $this->servico()->enviarLote([['tipo' => 'despesa', 'id' => 15], ['tipo' => 'despesa', 'id' => 16]], [$this->pdf()]);
    }

    public function testLoteRejeitaListaVaziaTipoInvalidoEExcesso(): void
    {
        foreach ([[], [['tipo' => 'outro', 'id' => 1]]] as $lista) {
            try {
                $this->servico()->enviarLote($lista, [$this->pdf()]);
                $this->fail('Deveria rejeitar a lista.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $muitos = [];
        for ($i = 1; $i <= ComprovantesService::MAX_PAGAMENTOS_LOTE + 1; $i++) {
            $muitos[] = ['tipo' => 'despesa', 'id' => $i];
        }
        $this->expectException(InvalidArgumentException::class);
        $this->servico()->enviarLote($muitos, [$this->pdf()]);
    }

    public function testLoteContinuaQuandoUmEnvioFalha(): void
    {
        $this->pagamentosEmDoisMeses();
        $this->drive->method('pastaMensal')->willReturnCallback(static fn (string $m) => 'pasta-' . $m);
        $this->drive->method('uploadFileToFolder')->willReturnCallback(static function ($p, $n, $m, string $pasta) {
            if ($pasta === 'pasta-2026-09') {
                throw new \Exception('Drive indisponível');
            }

            return ['id' => 'ok', 'link' => 'https://x/ok'];
        });
        $this->comprovantes->method('create')->willReturn(1);

        $resultado = $this->servico()->enviarLote([['tipo' => 'despesa', 'id' => 15], ['tipo' => 'despesa', 'id' => 16]], [$this->pdf()]);

        $this->assertCount(1, $resultado['enviados']);
        $this->assertCount(1, $resultado['erros']);
        $this->assertSame('2026-09', $resultado['erros'][0]['mes']);
    }

    public function testExcluirNaoMandaParaLixeiraArquivoUsadoPorOutroPagamento(): void
    {
        $this->despesaPaga();
        $this->comprovantes->method('findById')->willReturn(['id_comprovante' => 5, 'tipo' => 'despesa', 'pagamento_id' => 15, 'drive_id' => 'compartilhado']);
        $this->comprovantes->method('contarPorDriveId')->with('compartilhado', 5)->willReturn(1);
        $this->drive->expects($this->never())->method('trashFile');
        $this->comprovantes->expects($this->once())->method('delete')->with(5)->willReturn(true);

        $this->servico()->excluir(5);
    }

    public function testAnexarTotaisExtratoIgnoraTransferencias(): void
    {
        $this->comprovantes->method('contarPorPagamentos')->willReturnCallback(
            static fn (string $tipo, array $ids) => $tipo === 'despesa' ? [10 => 2] : []
        );

        $linhas = $this->servico()->anexarTotaisExtrato([
            ['tipo' => 'despesa', 'id' => 10],
            ['tipo' => 'receita', 'id' => 10],
            ['tipo' => 'transferencia', 'id' => 10],
        ]);

        $this->assertSame(2, $linhas[0]['total_comprovantes']);
        $this->assertSame(0, $linhas[1]['total_comprovantes']);
        $this->assertArrayNotHasKey('total_comprovantes', $linhas[2]);
    }

    public function testPendenciasDoMesSomaPorTipoEAgrupaPorConta(): void
    {
        $this->comprovantes->method('pendenciasDoMes')->with(2026, 10)->willReturn([
            ['conta_id' => 3, 'conta' => 'Banco A', 'tipo' => 'despesa', 'total' => 4, 'valor' => 400.5, 'do_extrato' => 3],
            ['conta_id' => 3, 'conta' => 'Banco A', 'tipo' => 'receita', 'total' => 1, 'valor' => 100.0, 'do_extrato' => 1],
            ['conta_id' => 8, 'conta' => 'Caixa', 'tipo' => 'despesa', 'total' => 2, 'valor' => 50.0, 'do_extrato' => 0],
        ]);

        $r = $this->servico()->pendenciasDoMes(2026, 10);

        $this->assertSame(7, $r['total']);
        $this->assertSame(4, $r['do_extrato']);
        $this->assertSame(6, $r['despesas']);
        $this->assertSame(1, $r['receitas']);
        $this->assertSame(450.5, $r['valor_despesas']);
        $this->assertCount(2, $r['contas']);
        $this->assertSame(['conta_id' => 3, 'conta' => 'Banco A', 'despesas' => 4, 'receitas' => 1, 'do_extrato' => 4], $r['contas'][0]);
    }

    public function testPendenciasDoMesSemPendenciaDevolveZeros(): void
    {
        $this->comprovantes->method('pendenciasDoMes')->willReturn([]);

        $r = $this->servico()->pendenciasDoMes(2026, 10);

        $this->assertSame(0, $r['total']);
        $this->assertSame([], $r['contas']);
    }

    public function testPendenciasDoMesRejeitaMesInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servico()->pendenciasDoMes(2026, 13);
    }
}
