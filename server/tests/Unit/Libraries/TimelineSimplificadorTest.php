<?php

declare(strict_types=1);

namespace Tests\unit\Libraries;

use App\Libraries\TimelineSimplificador;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testes unitários para TimelineSimplificador (linha do tempo do Portal do Cliente).
 *
 * @version 1.0.0
 * @since   2026-08-06
 */
class TimelineSimplificadorTest extends CIUnitTestCase
{
    /**
     * @test
     */
    public function testMovimentoDeDistribuicaoEhReconhecidoComoMarcoDeInicio(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Distribuição', 'dataHora' => '2026-01-10 10:00:00'],
        ]);

        $this->assertCount(1, $eventos);
        $this->assertSame('inicio', $eventos[0]['categoria']);
        $this->assertTrue($eventos[0]['marco']);
        $this->assertSame('Processo distribuído', $eventos[0]['titulo']);
    }

    /**
     * @test
     */
    public function testMovimentoDeTransitoEmJulgadoEhReconhecidoComAcento(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Trânsito em Julgado', 'dataHora' => '2026-03-01 10:00:00'],
        ]);

        $this->assertSame('encerramento', $eventos[0]['categoria']);
        $this->assertTrue($eventos[0]['marco']);
    }

    /**
     * @test
     */
    public function testMovimentoDesconhecidoCaiNaCategoriaGenericaSemQuebrar(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Algo Totalmente Novo E Sem Precedentes', 'dataHora' => '2026-02-01 10:00:00'],
        ]);

        $this->assertSame('movimentacao', $eventos[0]['categoria']);
        $this->assertFalse($eventos[0]['marco']);
        $this->assertSame('Algo Totalmente Novo E Sem Precedentes', $eventos[0]['titulo']);
    }

    /**
     * @test
     */
    public function testEventosSaoOrdenadosCronologicamente(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Sentença', 'dataHora' => '2026-05-01 10:00:00'],
            ['nome' => 'Distribuição', 'dataHora' => '2026-01-01 10:00:00'],
            ['nome' => 'Audiência', 'dataHora' => '2026-03-01 10:00:00'],
        ]);

        $datas = array_column($eventos, 'data');
        $this->assertSame([
            '2026-01-01 10:00:00',
            '2026-03-01 10:00:00',
            '2026-05-01 10:00:00',
        ], $datas);
    }

    /**
     * @test
     */
    public function testMarcoDeArquivamentoDoProcessoNaoDuplicaMovimentoOficial(): void
    {
        $processo = ['arquived_at' => '2026-06-01 10:00:00'];
        $eventos  = TimelineSimplificador::montar($processo, [
            ['nome' => 'Arquivamento Definitivo', 'dataHora' => '2026-06-01 10:00:00'],
        ]);

        $this->assertCount(1, $eventos, 'Não deve duplicar o marco de arquivamento já presente nos movimentos.');
    }

    /**
     * @test
     */
    public function testMarcoDeInicioDoProcessoEhAdicionadoQuandoNaoHaMovimentoDeDistribuicao(): void
    {
        $processo = ['dataDistribuicao' => '2026-01-05'];
        $eventos  = TimelineSimplificador::montar($processo, [
            ['nome' => 'Juntada de Petição', 'dataHora' => '2026-02-01 10:00:00'],
        ]);

        $this->assertCount(2, $eventos);
        $this->assertSame('inicio', $eventos[0]['categoria']);
        $this->assertSame('Processo iniciado', $eventos[0]['titulo']);
    }

    /**
     * @test
     */
    public function testSentimentoJaAnalisadoEhNormalizadoParaMinusculo(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Sentença', 'dataHora' => '2026-05-01 10:00:00', 'sentiment' => 'Positivo'],
        ]);

        $this->assertSame('positivo', $eventos[0]['sentimento']);
    }

    /**
     * @test
     */
    public function testMovimentosSemDataSaoIgnorados(): void
    {
        $eventos = TimelineSimplificador::montar([], [
            ['nome' => 'Sentença', 'dataHora' => null],
        ]);

        $this->assertCount(0, $eventos);
    }
}
