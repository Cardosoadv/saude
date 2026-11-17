<?php

namespace Tests\Unit\Services;

use App\Models\ClientesModel;
use App\Repositories\ContratosArquivosRepository;
use App\Repositories\ContratosRepository;
use App\Services\ConfiguracoesService;
use App\Services\ContratosArquivosService;
use App\Services\GoogleDriveService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * Arquivos de contratos no Drive: pasta do cliente, validação e registro, com Drive e
 * repositórios simulados.
 */
class ContratosArquivosServiceTest extends CIUnitTestCase
{
    /** @var string[] */
    private array $temporarios = [];

    private ContratosArquivosRepository $arquivos;
    private ContratosRepository $contratos;
    private ClientesModel $clientes;
    private GoogleDriveService $drive;
    private array $config = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->arquivos  = $this->createMock(ContratosArquivosRepository::class);
        $this->contratos = $this->createMock(ContratosRepository::class);
        $this->clientes  = $this->createMock(ClientesModel::class);
        $this->drive     = $this->createMock(GoogleDriveService::class);
        $this->config    = [];

        $this->arquivos->method('listarPorContrato')->willReturn([]);
        $this->drive->method('extractFolderIdFromUrl')->willReturnCallback(
            static fn (string $url) => preg_match('/folders\/([A-Za-z0-9_-]+)/', $url, $m) ? $m[1] : (preg_match('/^[A-Za-z0-9_-]{10,}$/', $url) ? $url : null)
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $arquivo) {
            @unlink($arquivo);
        }
        parent::tearDown();
    }

    private function servico(): ContratosArquivosService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturnCallback(fn (string $nome, $padrao = null) => $this->config[$nome] ?? $padrao);

        return new ContratosArquivosService($this->arquivos, $this->drive, $this->contratos, $this->clientes, $config);
    }

    private function arquivo(string $conteudo, string $nome): array
    {
        $caminho = tempnam(sys_get_temp_dir(), 'ctr');
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;

        return ['path' => $caminho, 'nome' => $nome];
    }

    private function pdf(string $nome = 'contrato.pdf'): array
    {
        return $this->arquivo("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n", $nome);
    }

    private function contratoDoCliente(array $cliente = ['id_cliente' => 8, 'nome' => 'Maria Silva', 'pasta' => 'https://drive.google.com/drive/folders/pastaCliente1234']): void
    {
        $this->contratos->method('findById')->willReturn(['id_contrato' => 5, 'numero_contrato' => '2026/0005', 'cliente_id' => 8]);
        $this->clientes->method('find')->willReturn($cliente);
    }

    public function testEnviaParaSubpastaContratosDaPastaDoCliente(): void
    {
        $this->contratoDoCliente();
        $this->drive->expects($this->once())->method('subpasta')->with('pastaCliente1234', 'Contratos')->willReturn('pasta-contratos');
        $this->drive->expects($this->once())->method('uploadFileToFolder')
            ->with($this->anything(), $this->matchesRegularExpression('/^Contrato-2026-0005_Aditivo_\d{8}-\d{6}_1\.pdf$/'), 'application/pdf', 'pasta-contratos')
            ->willReturn(['id' => 'drive-1', 'link' => 'https://drive.google.com/file/d/drive-1/view']);
        $this->arquivos->expects($this->once())->method('create')
            ->with($this->callback(static fn (array $r) => $r['contrato_id'] === 5
                && $r['categoria'] === 'Aditivo'
                && $r['drive_id'] === 'drive-1'
                && $r['enviado_por'] === 7))
            ->willReturn(33);

        $resultado = $this->servico()->enviar(5, [$this->pdf()], 'Aditivo', 7);

        $this->assertCount(1, $resultado['enviados']);
        $this->assertSame(33, $resultado['enviados'][0]['id_contrato_arquivo']);
        $this->assertSame([], $resultado['erros']);
    }

    public function testCriaAPastaDoClienteQuandoAindaNaoExiste(): void
    {
        $this->contratoDoCliente(['id_cliente' => 8, 'nome' => 'Maria Silva', 'pasta' => '']);
        $this->config['GOOGLE_DRIVE_FOLDER_ID'] = 'raizFicticia12345';
        $this->drive->expects($this->once())->method('getOrCreateFolder')
            ->with('Maria Silva - 8', 'raizFicticia12345')
            ->willReturn(['id' => 'novaPastaCliente', 'link' => 'https://drive.google.com/drive/folders/novaPastaCliente']);
        $this->clientes->expects($this->once())->method('update')
            ->with(8, ['pasta' => 'https://drive.google.com/drive/folders/novaPastaCliente']);
        $this->drive->expects($this->once())->method('subpasta')->with('novaPastaCliente', 'Contratos')->willReturn('pasta-contratos');
        $this->drive->method('uploadFileToFolder')->willReturn(['id' => 'd', 'link' => 'https://x/y']);
        $this->arquivos->method('create')->willReturn(1);

        $this->servico()->enviar(5, [$this->pdf()]);
    }

    public function testSemPastaRaizConfiguradaFalhaAntesDoUpload(): void
    {
        $this->contratoDoCliente(['id_cliente' => 8, 'nome' => 'Maria Silva', 'pasta' => null]);
        $this->drive->expects($this->never())->method('uploadFileToFolder');

        $this->expectExceptionMessage('GOOGLE_DRIVE_FOLDER_ID');
        $this->servico()->enviar(5, [$this->pdf()]);
    }

    public function testContratoSemClienteValidoNaoEnvia(): void
    {
        $this->contratos->method('findById')->willReturn(['id_contrato' => 5, 'cliente_id' => null]);
        $this->drive->expects($this->never())->method('uploadFileToFolder');

        $this->expectExceptionMessage('cliente válido');
        $this->servico()->enviar(5, [$this->pdf()]);
    }

    public function testFalhaDoDriveNaoCriaRegistro(): void
    {
        $this->contratoDoCliente();
        $this->drive->method('subpasta')->willReturn('pasta-contratos');
        $this->drive->method('uploadFileToFolder')->willThrowException(new \Exception('Drive fora do ar'));
        $this->arquivos->expects($this->never())->method('create');

        $resultado = $this->servico()->enviar(5, [$this->pdf()]);

        $this->assertSame([], $resultado['enviados']);
        $this->assertSame('Drive fora do ar', $resultado['erros'][0]['mensagem']);
    }

    public function testRejeitaTipoNaoPermitidoSemTocarNoDrive(): void
    {
        $this->contratoDoCliente();
        $this->drive->expects($this->never())->method('subpasta');
        $this->drive->expects($this->never())->method('uploadFileToFolder');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não é permitido');
        $this->servico()->enviar(5, [$this->arquivo('<?php echo 1;', 'contrato.pdf')]);
    }

    public function testAceitaDocxPeloConteudoZipComExtensaoConfirmada(): void
    {
        $this->contratoDoCliente();
        $this->drive->method('subpasta')->willReturn('pasta-contratos');
        $this->drive->expects($this->once())->method('uploadFileToFolder')
            ->with($this->anything(), $this->stringEndsWith('_1.docx'), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pasta-contratos')
            ->willReturn(['id' => 'd', 'link' => 'https://x/y']);
        $this->arquivos->method('create')->willReturn(1);

        $zip = tempnam(sys_get_temp_dir(), 'ctr');
        $this->temporarios[] = $zip;
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('word/document.xml', '<w:document/>');
        $z->close();

        $this->servico()->enviar(5, [['path' => $zip, 'nome' => 'minuta.docx']]);
    }

    public function testZipComOutraExtensaoNaoPassa(): void
    {
        $this->contratoDoCliente();
        $zip = tempnam(sys_get_temp_dir(), 'ctr');
        $this->temporarios[] = $zip;
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('a.txt', 'x');
        $z->close();

        $this->expectException(InvalidArgumentException::class);
        $this->servico()->enviar(5, [['path' => $zip, 'nome' => 'contrato.pdf']]);
    }

    public function testRejeitaArquivoMaiorQueOLimite(): void
    {
        $this->contratoDoCliente();
        $grande = $this->pdf();
        $h = fopen($grande['path'], 'ab');
        ftruncate($h, 20 * 1024 * 1024 + 1);
        fclose($h);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('excede o limite de 20 MB');
        $this->servico()->enviar(5, [$grande]);
    }

    public function testRejeitaMaisDeCincoArquivosPorEnvio(): void
    {
        $this->contratoDoCliente();
        $lista = [];
        for ($i = 0; $i < 6; $i++) {
            $lista[] = $this->pdf();
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no máximo 5');
        $this->servico()->enviar(5, $lista);
    }

    public function testRejeitaCategoriaInvalida(): void
    {
        $this->contratoDoCliente();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Categoria');
        $this->servico()->enviar(5, [$this->pdf()], 'Qualquer');
    }

    public function testContratoInexistenteLancaNaoEncontrado(): void
    {
        $this->contratos->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->servico()->enviar(999, [$this->pdf()]);
    }

    public function testExcluirMoveParaLixeiraERemoveORegistro(): void
    {
        $this->arquivos->method('findById')->willReturn(['id_contrato_arquivo' => 9, 'drive_id' => 'drive-9', 'contrato_id' => 5]);
        $this->drive->expects($this->once())->method('trashFile')->with('drive-9');
        $this->arquivos->expects($this->once())->method('delete')->with(9)->willReturn(true);

        $this->servico()->excluir(9);
    }

    public function testExcluirNaoRemoveORegistroSeODriveFalhar(): void
    {
        $this->arquivos->method('findById')->willReturn(['id_contrato_arquivo' => 9, 'drive_id' => 'drive-9', 'contrato_id' => 5]);
        $this->drive->method('trashFile')->willThrowException(new \Exception('falhou'));
        $this->arquivos->expects($this->never())->method('delete');

        $this->expectExceptionMessage('falhou');
        $this->servico()->excluir(9);
    }
}
