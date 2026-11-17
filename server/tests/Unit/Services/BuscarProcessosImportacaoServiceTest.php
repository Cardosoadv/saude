<?php

namespace Tests\Unit\Services;

use App\Repositories\BuscarProcessosRepository;
use App\Services\BuscarProcessosImportacaoService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BuscarProcessosImportacaoServiceTest extends TestCase
{
    private const VALIDO_1 = '0801234-04.2025.8.26.0100';
    private const VALIDO_2 = '1004567-38.2024.5.02.0001';
    private const VALIDO_3 = '5009876-08.2025.4.04.7100';

    private function service(array $cadastrados = [], ?callable $aoInserir = null): BuscarProcessosImportacaoService
    {
        $repo = $this->createMock(BuscarProcessosRepository::class);
        $repo->method('numerosCadastrados')->willReturn($cadastrados);
        if ($aoInserir) {
            $repo->method('insertBatch')->willReturnCallback($aoInserir);
        }

        return new BuscarProcessosImportacaoService($repo);
    }

    public function testCsvComCabecalhoPontoEVirgulaEBom(): void
    {
        $csv = "\xEF\xBB\xBFnumero_processo;observacao\n"
            . self::VALIDO_1 . ";Cliente em prospecção\n"
            . self::VALIDO_2 . ";\"Parte contrária; a pedido do sócio\"\n";

        $r = $this->service()->analisar($csv, 'lote.csv');

        $this->assertSame('csv', $r['formato']);
        $this->assertSame(2, $r['total']);
        $this->assertCount(2, $r['validos']);
        $this->assertSame('08012340420258260100', $r['validos'][0]['numero_processo']);
        $this->assertSame('Parte contrária; a pedido do sócio', $r['validos'][1]['observacao']);
        $this->assertSame(3, $r['validos'][1]['linha']);
    }

    public function testCsvSemCabecalhoComVirgulaEColunasInvertidasPorCabecalho(): void
    {
        $semCabecalho = self::VALIDO_1 . ",Motivo A\n";
        $r = $this->service()->analisar($semCabecalho, 'lote.csv');
        $this->assertCount(1, $r['validos']);
        $this->assertSame('Motivo A', $r['validos'][0]['observacao']);

        $invertido = "Observação;Número\nMotivo B;" . self::VALIDO_2 . "\n";
        $r = $this->service()->analisar($invertido, 'lote.csv');
        $this->assertCount(1, $r['validos']);
        $this->assertSame('10045673820245020001', $r['validos'][0]['numero_processo']);
        $this->assertSame('Motivo B', $r['validos'][0]['observacao']);
    }

    public function testCsvEmWindows1252EConvertidoParaUtf8(): void
    {
        $csv = mb_convert_encoding("numero_processo;observacao\n" . self::VALIDO_1 . ";Indicação\n", 'Windows-1252', 'UTF-8');

        $r = $this->service()->analisar($csv, 'lote.csv');

        $this->assertSame('Indicação', $r['validos'][0]['observacao']);
    }

    public function testJsonListaEObjetoComChaveProcessos(): void
    {
        $lista = json_encode([
            ['numero_processo' => self::VALIDO_1, 'observacao' => 'A'],
            ['numero' => self::VALIDO_2, 'obs' => 'B'],
        ]);
        $r = $this->service()->analisar($lista, 'lote.json');
        $this->assertSame('json', $r['formato']);
        $this->assertCount(2, $r['validos']);
        $this->assertSame('B', $r['validos'][1]['observacao']);

        $objeto = json_encode(['processos' => [self::VALIDO_3]]);
        $r = $this->service()->analisar($objeto, 'lote.json', 'Observação padrão');
        $this->assertCount(1, $r['validos']);
        $this->assertSame('Observação padrão', $r['validos'][0]['observacao']);
    }

    public function testErrosEIgnorados(): void
    {
        $csv = "numero_processo;observacao\n"
            . self::VALIDO_1 . ";Já existe\n"            // linha 2: já cadastrado
            . self::VALIDO_2 . ";Ok\n"                   // linha 3: válido
            . self::VALIDO_2 . ";Repetido\n"             // linha 4: repetido
            . "0801234-05.2025.8.26.0100;DV errado\n"    // linha 5: DV inválido
            . "123;Curto\n"                              // linha 6: menos de 20 dígitos
            . self::VALIDO_3 . ";\n";                    // linha 7: sem observação

        $r = $this->service(['08012340420258260100'])->analisar($csv, 'lote.csv');

        $this->assertSame(6, $r['total']);
        $this->assertCount(1, $r['validos']);
        $this->assertSame([2, 4], array_column($r['ignorados'], 'linha'));
        $this->assertStringContainsString('linha 3', $r['ignorados'][1]['motivo']);
        $this->assertSame([5, 6, 7], array_column($r['erros'], 'linha'));
        $this->assertStringContainsString('verificador', $r['erros'][0]['motivo']);
        $this->assertStringContainsString('20 dígitos', $r['erros'][1]['motivo']);
        $this->assertStringContainsString('Observação', $r['erros'][2]['motivo']);
    }

    public function testImportarGravaSomenteValidos(): void
    {
        $gravados = null;
        $service  = $this->service([], function (array $dados) use (&$gravados) {
            $gravados = $dados;
            return count($dados);
        });

        $csv = "numero_processo;observacao\n" . self::VALIDO_1 . ";A\n123;B\n";
        $r   = $service->importar($csv, 'lote.csv');

        $this->assertSame(1, $r['importados']);
        $this->assertSame([['numero_processo' => '08012340420258260100', 'observacao' => 'A']], $gravados);
    }

    public function testJsonInvalidoLancaExcecao(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->analisar('{"processos": [', 'lote.json');
    }

    public function testArquivoVazioLancaExcecao(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->analisar("numero_processo;observacao\n", 'lote.csv');
    }
}
