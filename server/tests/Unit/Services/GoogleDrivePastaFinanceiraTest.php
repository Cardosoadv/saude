<?php

namespace Tests\Unit\Services;

use App\Services\ConfiguracoesService;
use App\Services\GoogleDriveService;
use CodeIgniter\Test\CIUnitTestCase;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files;

/**
 * Testes da pasta do financeiro (raiz configurável e subpastas mensais AAAA-MM).
 */
class GoogleDrivePastaFinanceiraTest extends CIUnitTestCase
{
    private const RAIZ = 'pasta-financeiro-de-teste-0000';

    private function criarServico(?string $pastaFinanceira, Files $files): GoogleDriveService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturnCallback(
            static fn (string $nome, $padrao = null) => $nome === 'GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID' ? $pastaFinanceira : $padrao
        );

        $drive = $this->getMockBuilder(Drive::class)->disableOriginalConstructor()->getMock();
        $drive->files = $files;

        return new GoogleDriveService($config, $this->createMock(Client::class), $drive);
    }

    private function listaCom(array $arquivos): FileList
    {
        $lista = new FileList();
        $lista->setFiles($arquivos);

        return $lista;
    }

    public function testPastaMensalCriaSubpastaNaRaizDoFinanceiro(): void
    {
        $files = $this->createMock(Files::class);
        $files->method('listFiles')->willReturn($this->listaCom([]));
        $files->expects($this->once())->method('create')
            ->with(
                $this->callback(static fn (DriveFile $f) => $f->getName() === '2026-10'
                    && $f->getParents() === [self::RAIZ]
                    && $f->getMimeType() === 'application/vnd.google-apps.folder'),
                $this->anything()
            )
            ->willReturn(new DriveFile(['id' => 'pasta-outubro']));

        $this->assertSame('pasta-outubro', $this->criarServico(self::RAIZ, $files)->pastaMensal('2026-10'));
    }

    public function testPastaMensalReaproveitaPastaExistente(): void
    {
        $files = $this->createMock(Files::class);
        $files->method('listFiles')->willReturn($this->listaCom([new DriveFile(['id' => 'ja-existe', 'name' => '2026-10'])]));
        $files->expects($this->never())->method('create');

        $this->assertSame('ja-existe', $this->criarServico(self::RAIZ, $files)->pastaMensal('2026-10'));
    }

    public function testPastaMensalConsultaODriveUmaVezPorMes(): void
    {
        $files = $this->createMock(Files::class);
        $files->expects($this->once())->method('listFiles')
            ->willReturn($this->listaCom([new DriveFile(['id' => 'ja-existe'])]));

        $servico = $this->criarServico(self::RAIZ, $files);
        $servico->pastaMensal('2026-10');
        $this->assertSame('ja-existe', $servico->pastaMensal('2026-10'));
    }

    public function testAceitaUrlDaPastaComoConfiguracao(): void
    {
        $servico = $this->criarServico('https://drive.google.com/drive/folders/' . self::RAIZ, $this->createMock(Files::class));

        $this->assertSame(self::RAIZ, $servico->getPastaFinanceiraId());
    }

    public function testSemConfiguracaoFalhaComMensagemClara(): void
    {
        $servico = $this->criarServico(null, $this->createMock(Files::class));

        $this->expectExceptionMessage('GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID');
        $servico->pastaMensal('2026-10');
    }

    /**
     * @dataProvider mesesInvalidos
     */
    public function testRejeitaMesInvalido(string $mes): void
    {
        $servico = $this->criarServico(self::RAIZ, $this->createMock(Files::class));

        $this->expectExceptionMessage('Mês inválido');
        $servico->pastaMensal($mes);
    }

    public static function mesesInvalidos(): array
    {
        return [['2026-13'], ['2026-1'], ['10-2026'], ['../2026-10'], ['']];
    }

    public function testTestarGravacaoCriaEApagaArquivoDeTeste(): void
    {
        $files = $this->createMock(Files::class);
        $files->expects($this->once())->method('create')
            ->with($this->callback(static fn (DriveFile $f) => $f->getParents() === [self::RAIZ]), $this->anything())
            ->willReturn(new DriveFile(['id' => 'arquivo-teste']));
        $files->expects($this->once())->method('delete')->with('arquivo-teste');

        $this->criarServico(self::RAIZ, $files)->testarGravacao(self::RAIZ);
    }

    public function testTestarGravacaoInformaFalhaDoDrive(): void
    {
        $files = $this->createMock(Files::class);
        $files->method('create')->willThrowException(new \Exception('File not found'));

        $this->expectExceptionMessage('Não foi possível gravar na pasta do Google Drive');
        $this->criarServico(self::RAIZ, $files)->testarGravacao(self::RAIZ);
    }

    public function testTrashFileMarcaOArquivoComoNaLixeira(): void
    {
        $files = $this->createMock(Files::class);
        $files->expects($this->once())->method('update')
            ->with('arquivo-1', $this->callback(static fn (DriveFile $f) => $f->getTrashed() === true), $this->anything());

        $this->criarServico(self::RAIZ, $files)->trashFile('arquivo-1');
    }

    public function testTrashFileInformaFalhaDoDrive(): void
    {
        $files = $this->createMock(Files::class);
        $files->method('update')->willThrowException(new \Exception('404'));

        $this->expectExceptionMessage('lixeira do Google Drive');
        $this->criarServico(self::RAIZ, $files)->trashFile('arquivo-1');
    }

    public function testSubpastaCriaUmaVezEReaproveitaNaMesmaRequisicao(): void
    {
        $files = $this->createMock(Files::class);
        $files->expects($this->once())->method('listFiles')->willReturn($this->listaCom([]));
        $files->expects($this->once())->method('create')
            ->with(
                $this->callback(static fn (DriveFile $f) => $f->getName() === 'Contratos' && $f->getParents() === ['pasta-cliente-de-teste']),
                $this->anything()
            )
            ->willReturn(new DriveFile(['id' => 'pasta-contratos']));

        $servico = $this->criarServico(null, $files);

        $this->assertSame('pasta-contratos', $servico->subpasta('pasta-cliente-de-teste', 'Contratos'));
        $this->assertSame('pasta-contratos', $servico->subpasta('pasta-cliente-de-teste', 'Contratos'));
    }
}
