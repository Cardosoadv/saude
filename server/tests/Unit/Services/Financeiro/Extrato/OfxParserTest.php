<?php

namespace Tests\Unit\Services\Financeiro\Extrato;

use App\DTO\ExtratoArquivo;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\OfxParser;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Leitor de OFX. Fixtures 100% fictícias em tests/_support/Extrato.
 */
class OfxParserTest extends CIUnitTestCase
{
    private function fixture(string $nome): string
    {
        $caminho = dirname(__DIR__, 4) . '/_support/Extrato/' . $nome;
        $this->assertFileExists($caminho);

        return (string) file_get_contents($caminho);
    }

    private function ler(string $nome): ExtratoArquivo
    {
        return (new OfxParser())->parse($this->fixture($nome));
    }

    /** Casos de erro: devolve a mensagem da exceção. */
    private function erroDe(string $conteudo): string
    {
        try {
            (new OfxParser())->parse($conteudo);
        } catch (ExtratoInvalidoException $e) {
            return $e->getMessage();
        }
        $this->fail('Era esperada ExtratoInvalidoException.');
    }

    public function testSgmlCp1252LeContaPeriodoESaldo(): void
    {
        $e = $this->ler('sgml_cp1252.ofx');

        $this->assertSame('999', $e->banco);
        $this->assertSame('0001', $e->agencia);
        $this->assertSame('00012345-6', $e->conta);
        $this->assertSame('CHECKING', $e->tipoConta);
        $this->assertSame('BRL', $e->moeda);
        $this->assertSame('2026-10-01', $e->periodoInicio);
        $this->assertSame('2026-10-31', $e->periodoFim);
        $this->assertSame(123456, $e->saldoFinalCentavos);
        $this->assertSame('2026-10-31', $e->saldoData);
        $this->assertCount(5, $e->movimentos);
    }

    public function testSgmlCp1252ConverteAcentosParaUtf8(): void
    {
        $e = $this->ler('sgml_cp1252.ofx');

        $this->assertSame('Pix enviado Joaquim Ávila', $e->movimentos[0]->memo);
        $this->assertSame('Banco Fictício', $e->movimentos[1]->nome);
        $this->assertSame('Tarifa — manutenção', $e->movimentos[3]->memo);
        $this->assertTrue(mb_check_encoding($e->movimentos[2]->memo, 'UTF-8'));
    }

    public function testSgmlEXmlComMesmoConteudoGeramDtosIdenticos(): void
    {
        $this->assertEquals($this->ler('sgml_cp1252.ofx'), $this->ler('xml_utf8.ofx'));
    }

    public function testArquivoJaEmUtf8NaoECorrompidoMesmoComCharsetDeclarado(): void
    {
        $sgml = $this->fixture('sgml_cp1252.ofx');
        $utf8 = mb_convert_encoding($sgml, 'UTF-8', 'Windows-1252'); // continua declarando CHARSET:1252

        $e = (new OfxParser())->parse($utf8);

        $this->assertSame('Pix enviado Joaquim Ávila', $e->movimentos[0]->memo);
    }

    public function testValoresViramCentavosExatos(): void
    {
        $e = $this->ler('sgml_cp1252.ofx');

        $this->assertSame(-1050, $e->movimentos[0]->valorCentavos);
        $this->assertSame(10, $e->movimentos[1]->valorCentavos);      // 0.10
        $this->assertSame(123456, $e->movimentos[2]->valorCentavos);  // 1234.56
        $this->assertSame(-20, $e->movimentos[3]->valorCentavos);
        $this->assertSame(-123456, $e->movimentos[4]->valorCentavos); // vírgula decimal
    }

    public function testSomaDeDezECentavosComVinteCentavosNaoPassaPorFloat(): void
    {
        $e = $this->ler('sgml_cp1252.ofx');

        // 0.10 + 0.20 em float daria 0.30000000000000004
        $this->assertSame(30, $e->movimentos[1]->valorCentavos + abs($e->movimentos[3]->valorCentavos));
    }

    public function testZerosAMaisNasCasasDecimaisSaoAceitos(): void
    {
        $ofx = str_replace('<TRNAMT>-10.50', '<TRNAMT>-10.500', $this->fixture('sgml_cp1252.ofx'));

        $this->assertSame(-1050, (new OfxParser())->parse($ofx)->movimentos[0]->valorCentavos);
    }

    public function testDatasComHoraEFusoMantemODiaDoArquivo(): void
    {
        $datas = array_map(static fn ($m) => $m->data, $this->ler('datas_hora_fuso.ofx')->movimentos);

        $this->assertSame(['2026-10-05', '2026-10-05', '2026-10-06', '2026-10-07'], $datas);
    }

    public function testFitidPresenteEUnicoEConfiavel(): void
    {
        $m = $this->ler('sgml_cp1252.ofx')->movimentos[0];

        $this->assertTrue($m->fitidConfiavel);
        $this->assertSame('F0001', $m->fitid);
        $this->assertSame(1, $m->ordemNoDia);
    }

    public function testFitidAusenteOuVazioNaoEConfiavelEOrdemNoDiaCresce(): void
    {
        $m = $this->ler('sem_fitid.ofx')->movimentos;

        $this->assertFalse($m[0]->fitidConfiavel);
        $this->assertNull($m[0]->fitid);
        $this->assertFalse($m[1]->fitidConfiavel); // FITID vazio
        $this->assertFalse($m[2]->fitidConfiavel);
        $this->assertSame([1, 2, 3], [$m[0]->ordemNoDia, $m[1]->ordemNoDia, $m[2]->ordemNoDia]);

        // Com FITID confiável, não entra na contagem do fallback.
        $this->assertTrue($m[3]->fitidConfiavel);
        $this->assertSame(1, $m[3]->ordemNoDia);
    }

    public function testFitidRepetidoNoArquivoNaoEConfiavelEmNenhumaOcorrencia(): void
    {
        $m = $this->ler('fitid_duplicado.ofx')->movimentos;

        $this->assertFalse($m[0]->fitidConfiavel);
        $this->assertFalse($m[1]->fitidConfiavel);
        $this->assertNull($m[0]->fitid);
        $this->assertTrue($m[2]->fitidConfiavel);
        $this->assertSame('UNICO', $m[2]->fitid);
    }

    public function testChecknumERefnumConstantesSaoIgnorados(): void
    {
        foreach ($this->ler('sgml_cp1252.ofx')->movimentos as $m) {
            $this->assertNull($m->documento);
        }
    }

    public function testChecknumERefnumQueVariamSaoAproveitados(): void
    {
        $m = $this->ler('documento_variavel.ofx')->movimentos;

        $this->assertSame('9001', $m[0]->documento);
        $this->assertSame('9002', $m[1]->documento);
    }

    public function testArquivoVazioEErroClaro(): void
    {
        $this->assertSame('arquivo OFX inválido: arquivo vazio.', $this->erroDe($this->fixture('vazio.ofx')));
    }

    public function testArquivoTruncadoEErroClaro(): void
    {
        $this->assertStringStartsWith('arquivo OFX inválido: arquivo truncado', $this->erroDe($this->fixture('truncado.ofx')));
    }

    public function testCartaoDeCreditoERejeitado(): void
    {
        $this->assertStringContainsString('cartão de crédito', $this->erroDe($this->fixture('cartao.ofx')));
    }

    public function testMaisDeUmaContaERejeitado(): void
    {
        $ofx   = $this->fixture('sgml_cp1252.ofx');
        $ini   = strpos($ofx, '<STMTTRNRS>');
        $bloco = substr($ofx, $ini, strpos($ofx, '</STMTTRNRS>') + 12 - $ini);
        $dobro = str_replace($bloco, $bloco . $bloco, $ofx);

        $this->assertStringContainsString('mais de uma conta', $this->erroDe($dobro));
    }

    public function testSemBankAcctFromERejeitado(): void
    {
        $ofx = preg_replace('/<BANKACCTFROM>.*?<\/BANKACCTFROM>/s', '', $this->fixture('sgml_cp1252.ofx'));

        $this->assertStringContainsString('BANKACCTFROM', $this->erroDe($ofx));
    }

    public function testTextoQualquerNaoEOfx(): void
    {
        $this->assertStringContainsString('não parece ser OFX', $this->erroDe('apenas um texto qualquer'));
    }

    public function testDataEValorInvalidosGeramErro(): void
    {
        $ofx = $this->fixture('sgml_cp1252.ofx');

        $this->assertStringContainsString('data inválida', $this->erroDe(str_replace('<DTPOSTED>20261005', '<DTPOSTED>20261305', $ofx)));
        $this->assertStringContainsString('inválido', $this->erroDe(str_replace('<TRNAMT>-10.50', '<TRNAMT>abc', $ofx)));
    }

    public function testXmlComDoctypeEEntidadeExternaNaoEResolvida(): void
    {
        $xml = $this->fixture('xml_utf8.ofx');
        $xml = str_replace(
            '<OFX>',
            "<!DOCTYPE OFX [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]>\n<OFX>",
            $xml
        );
        $xml = str_replace('<MEMO>Pix enviado Joaquim Ávila</MEMO>', '<MEMO>&xxe;</MEMO>', $xml);

        $e = (new OfxParser())->parse($xml);

        $this->assertStringNotContainsString('root:', $e->movimentos[0]->memo);
        $this->assertSame('&xxe;', $e->movimentos[0]->memo);
    }

    public function testValidarArquivoAceitaOfxEMimesComuns(): void
    {
        $p = new OfxParser();
        $p->validarArquivo('extrato.OFX', 'application/x-ofx', 1000);
        $p->validarArquivo('extrato.ofx', 'text/plain', 1000);
        $this->addToAssertionCount(1);
    }

    public function testValidarArquivoRejeitaExtensaoMimeETamanho(): void
    {
        $p = new OfxParser(1024);

        foreach ([['extrato.csv', 'text/plain', 10, 'extensão'], ['a.ofx', 'image/png', 10, 'tipo de arquivo'],
                  ['a.ofx', 'text/plain', 2048, 'limite'], ['a.ofx', 'text/plain', 0, 'vazio']] as [$nome, $mime, $tam, $trecho]) {
            try {
                $p->validarArquivo($nome, $mime, $tam);
                $this->fail("Deveria rejeitar {$nome}/{$mime}/{$tam}");
            } catch (ExtratoInvalidoException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
                $this->assertStringStartsWith('arquivo OFX inválido: ', $e->getMessage());
            }
        }
    }

    public function testConteudoMaiorQueOLimiteERejeitado(): void
    {
        $this->expectException(ExtratoInvalidoException::class);

        (new OfxParser(100))->parse(str_repeat('x', 101));
    }
}
