<?php

namespace Tests\Unit\Libraries;

use App\Libraries\StjCampoParser;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Casos reais (e sujos) extraídos dos JSONs do STJ.
 */
class StjCampoParserTest extends CIUnitTestCase
{
    private StjCampoParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StjCampoParser();
    }

    // -------------------------------------------------------------------------
    // Referências legislativas
    // -------------------------------------------------------------------------

    public function testLeiComSiglaENomeEDispositivos()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED LEI:013105 ANO:2015\n*****  CPC-15    CÓDIGO DE PROCESSO CIVIL DE 2015\n        ART:00787 ART:00803 INC:00001 ART:01022",
        ]);

        $this->assertCount(1, $normas);
        $n = $normas[0];
        $this->assertSame('FED|LEI|13105|2015|', $n['chave']);
        $this->assertSame('FED', $n['esfera']);
        $this->assertSame('LEI', $n['tipo']);
        $this->assertSame('13105', $n['numero']);
        $this->assertSame('2015', $n['ano']);
        $this->assertSame('CPC-15', $n['sigla']);
        $this->assertSame('CÓDIGO DE PROCESSO CIVIL DE 2015', $n['nome']);

        // INC pertence ao último ART.
        $this->assertSame(
            [['787', null], ['803', '1'], ['1022', null]],
            array_map(static fn ($d) => [$d['artigo'], $d['inciso']], $n['dispositivos'])
        );
        $this->assertSame('ART:00803 INC:00001', $n['dispositivos'][1]['texto_original']);
    }

    public function testSumulaMultiplaGeraUmaLinhaPorSumula()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED SUM:******\n*****  SUM(STJ)    SÚMULA DO SUPERIOR TRIBUNAL DE JUSTIÇA\n        SUM:000005 SUM:000007",
        ]);

        $this->assertSame('FED|SUM||||SUM(STJ)', $normas[0]['chave']);
        $this->assertNull($normas[0]['numero']);
        $this->assertNull($normas[0]['ano']);
        $this->assertSame(['5', '7'], array_column($normas[0]['dispositivos'], 'sumula'));
    }

    public function testSumulasDeTribunaisDiferentesTemChavesDiferentes()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED SUM:******\n*****  SUM(STJ)    SÚMULA DO SUPERIOR TRIBUNAL DE JUSTIÇA\n        SUM:000461",
            "LEG:FED SUM:******\n*****  SUM(STF)    SÚMULA DO SUPREMO TRIBUNAL FEDERAL\n        SUM:000269 SUM:000271",
        ]);

        $this->assertCount(2, $normas);
        $this->assertNotSame($normas[0]['chave'], $normas[1]['chave']);
    }

    public function testCabecalhoSemEsferaEhAceito()
    {
        $normas = $this->parser->parseReferenciasLegislativas(["LEG: LEI:008383 ANO:1991\n        ART:00066"]);

        $this->assertNull($normas[0]['esfera']);
        $this->assertSame('LEI', $normas[0]['tipo']);
        $this->assertSame('8383', $normas[0]['numero']);
        $this->assertSame('66', $normas[0]['dispositivos'][0]['artigo']);
    }

    public function testEsferaSozinhaEAnoAsteriscos()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED\n*****  SUM(STF)  SUMULA DO SUPREMO TRIBUNAL FEDERAL\n        SUM:000282",
            "LEG:FED DEC:008380 ANO:****\n        ART:00352 ART:00271",
        ]);

        $this->assertSame('SUM(STF)', $normas[0]['sigla']);
        $this->assertSame('282', $normas[0]['dispositivos'][0]['sumula']);
        $this->assertNull($normas[1]['ano']);
        $this->assertSame(['352', '271'], array_column($normas[1]['dispositivos'], 'artigo'));
    }

    public function testSumulaNoCabecalhoComTribunalEmLinhaSolta()
    {
        // O número da súmula vem no cabeçalho e o tribunal em "(STF)."
        $normas = $this->parser->parseReferenciasLegislativas(["LEG:FED SUM:000282 ANO:****\n(STF)."]);

        $this->assertSame('FED|SUM||||SUM(STF)', $normas[0]['chave']);
        $this->assertSame('282', $normas[0]['dispositivos'][0]['sumula']);
    }

    public function testEntradasSoltasDoArrayPertencemAoBlocoAnterior()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:EST LEI:003796 ANO:1996\n        ART:00008 INC:00015",
            '(SE)',
        ]);

        $this->assertCount(1, $normas);
        $this->assertSame('SE', $normas[0]['uf']);
        $this->assertSame('EST|LEI|3796|1996|SE', $normas[0]['chave']);
    }

    public function testNomeQuebradoEmDuasLinhas()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED LEI:004024 ANO:1961\n*****  LDBE-61     LEI DE DIRETRIZES E BASES DA EDUCAÇÃO NACIONAL DE\n1961\n        ART:00011   ART:00100",
        ]);

        $this->assertSame('LEI DE DIRETRIZES E BASES DA EDUCAÇÃO NACIONAL DE 1961', $normas[0]['nome']);
        $this->assertSame(['11', '100'], array_column($normas[0]['dispositivos'], 'artigo'));
    }

    public function testParagrafoIncisoEAlineaSeguemOUltimoArtigo()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED CFD:****** ANO:1988\n*****  CF-1988   CONSTITUIÇÃO FEDERAL DE 1988\n"
            . "        ART:00155 PAR:00002 INC:00002 INC:00011 ART:00145\n        PAR:00001\n"
            . "        ART:00146 INC:00003 LET:B     ART:00150 PAR:ÚNICO",
        ]);

        $linhas = array_map(
            static fn ($d) => [$d['artigo'], $d['paragrafo'], $d['inciso'], $d['alinea']],
            $normas[0]['dispositivos']
        );

        $this->assertSame([
            ['155', '2', '2', null],
            ['155', '2', '11', null],
            ['145', '1', null, null],
            ['146', null, '3', 'B'],
            ['150', 'ÚNICO', null, null],
        ], $linhas);
    }

    public function testNormaCitadaComoUmTodoGeraLinhaSemDispositivo()
    {
        $normas = $this->parser->parseReferenciasLegislativas(["LEG:FED EMC:000037 ANO:2002"]);

        $this->assertCount(1, $normas[0]['dispositivos']);
        $this->assertNull($normas[0]['dispositivos'][0]['artigo']);
    }

    public function testComplementoEmParentesesFicaNoTextoOriginal()
    {
        $normas = $this->parser->parseReferenciasLegislativas([
            "LEG:FED           ANO:1988\n*****  CF-1988     CONSTITUIÇÃO FEDERAL DE 1988\n        ART:00100   PAR:00003   PAR:00004\n(ALTERADO PELA EC Nº 37/2002)",
        ]);

        $this->assertSame(['100', '100'], array_column($normas[0]['dispositivos'], 'artigo'));
        $this->assertSame(['3', '4'], array_column($normas[0]['dispositivos'], 'paragrafo'));
        $this->assertStringContainsString('ALTERADO PELA EC Nº 37/2002', $normas[0]['dispositivos'][0]['texto_original']);
    }

    public function testTrechoSemCabecalhoNaoEhDescartado()
    {
        $normas = $this->parser->parseReferenciasLegislativas(['algo que não é uma norma']);

        $this->assertCount(1, $normas);
        $this->assertSame(StjCampoParser::CHAVE_NORMA_DESCONHECIDA, $normas[0]['chave']);
        $this->assertSame('algo que não é uma norma', $normas[0]['dispositivos'][0]['texto_original']);
    }

    public function testReferenciasVaziasRetornamListaVazia()
    {
        $this->assertSame([], $this->parser->parseReferenciasLegislativas(null));
        $this->assertSame([], $this->parser->parseReferenciasLegislativas([]));
    }

    // -------------------------------------------------------------------------
    // Jurisprudência citada
    // -------------------------------------------------------------------------

    public function testCitadaSimples()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(HONORÁRIOS ADVOCATÍCIOS - FIXAÇÃO - APRECIAÇÃO EQÜITATIVA DO JUIZ)\n     STJ - <<AGRG NO AG 266551>>-PR"
        );

        $this->assertCount(1, $c);
        $this->assertSame('HONORÁRIOS ADVOCATÍCIOS - FIXAÇÃO - APRECIAÇÃO EQÜITATIVA DO JUIZ', $c[0]['topico']);
        $this->assertSame('STJ', $c[0]['tribunal']);
        $this->assertSame('AGRG NO AG', $c[0]['classe']);
        $this->assertSame('266551', $c[0]['numero']);
        $this->assertSame('PR', $c[0]['uf']);
    }

    public function testTopicoEmVariasLinhasEVariosTopicos()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(EMBARGOS DE DECLARAÇÃO - PREQUESTIONAMENTO - MATÉRIA\nCONSTITUCIONAL)\n"
            . "     STJ - <<RESP 447791>>-GO, <<EDCL NO RESP 397568>>-RS\n"
            . "(FGTS - EXPURGOS INFLACIONÁRIOS -\nAÇÃO\nRESCISÓRIA)\n"
            . "     STJ - <<RESP 620826>>-DF"
        );

        $this->assertCount(3, $c);
        $this->assertSame('EMBARGOS DE DECLARAÇÃO - PREQUESTIONAMENTO - MATÉRIA CONSTITUCIONAL', $c[0]['topico']);
        $this->assertSame('EMBARGOS DE DECLARAÇÃO - PREQUESTIONAMENTO - MATÉRIA CONSTITUCIONAL', $c[1]['topico']);
        $this->assertSame('EDCL NO RESP', $c[1]['classe']);
        $this->assertSame('FGTS - EXPURGOS INFLACIONÁRIOS - AÇÃO RESCISÓRIA', $c[2]['topico']);
        $this->assertSame('620826', $c[2]['numero']);
    }

    public function testCitacaoQuebradaNoMeioDoToken()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(REEXAME DE PROVA)\n     STJ - <<EDCL NO AGRG NOS ERESP 63864>>-PR, <<EDCL NO MS\n7200>>-DF,\n           <<EDCL NO RESP 97534>>-RJ"
        );

        $this->assertSame(['63864', '7200', '97534'], array_column($c, 'numero'));
        $this->assertSame('EDCL NO MS', $c[1]['classe']);
        $this->assertSame('DF', $c[1]['uf']);
    }

    public function testMarcacaoDeLinkComChavesEColchetes()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(TOPICO)\n     STJ - <<EDcl no RESP 400600>>-SC, {{RESP 463136}}-SC\n     STF - [[ADI 712]]-DF"
        );

        $this->assertSame(['400600', '463136', '712'], array_column($c, 'numero'));
        $this->assertSame(['STJ', 'STJ', 'STF'], array_column($c, 'tribunal'));
    }

    public function testCitacaoSemMarcacaoNemUf()
    {
        $c = $this->parser->parseJurisprudenciaCitada("(REPETIÇÃO)\n     STF - RE 92.400-1, RE 96.803-3, RE 108010-PB");

        $this->assertSame(['92.400-1', '96.803-3', '108010'], array_column($c, 'numero'));
        $this->assertSame([null, null, 'PB'], array_column($c, 'uf'));
        $this->assertSame(['STF', 'STF', 'STF'], array_column($c, 'tribunal'));
    }

    public function testReferenciaDePublicacaoAposAUf()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(TOPICO)\n     STJ - <<RESP 138778>>-SC (JSTJ 3/239)\n     STJ - <<RMS 9677>>-MS (RET 19/51, LEXSTJ 144/41)"
        );

        $this->assertSame('JSTJ 3/239', $c[0]['referencia_publicacao']);
        $this->assertSame('RET 19/51, LEXSTJ 144/41', $c[1]['referencia_publicacao']);
    }

    public function testReferenciaDePublicacaoQuebradaEmDuasLinhas()
    {
        $c = $this->parser->parseJurisprudenciaCitada(
            "(CITAÇÃO)\n     STJ - <<AGRG NO RESP 487528>>-SP, <<RESP 354357>>-RS (RDDP\n5/231)\n(OUTRO)\n     STJ - <<RESP 1>>-SP"
        );

        $this->assertCount(3, $c);
        $this->assertSame('RDDP 5/231', $c[1]['referencia_publicacao']);
        $this->assertSame('OUTRO', $c[2]['topico']);
    }

    public function testTextoSemTopico()
    {
        $c = $this->parser->parseJurisprudenciaCitada("     STJ - {{AR 1459}}-PR\n     STF - RE 91369");

        $this->assertCount(2, $c);
        $this->assertNull($c[0]['topico']);
        $this->assertSame('AR', $c[0]['classe']);
    }

    public function testCitacaoIlegivelNaoEhDescartada()
    {
        $c = $this->parser->parseJurisprudenciaCitada("(TOPICO)\n     TRF1 - 900103686-4");

        $this->assertNotEmpty($c);
        $this->assertNull($c[0]['numero']);
        $this->assertStringContainsString('900103686-4', $c[0]['texto_original']);
        $this->assertSame('TOPICO', $c[0]['topico']);
    }

    public function testCitadaVazia()
    {
        $this->assertSame([], $this->parser->parseJurisprudenciaCitada(null));
        $this->assertSame([], $this->parser->parseJurisprudenciaCitada("  \n"));
    }

    // -------------------------------------------------------------------------
    // Acórdãos similares
    // -------------------------------------------------------------------------

    public function testSimilarEraNovaEmUmaLinha()
    {
        $s = $this->parser->parseAcordaosSimilares([
            'AgInt no AREsp 3080972 MG 2025/0409072-3 Decisão:23/03/2026 / DJEN DATA:30/03/2026',
        ]);

        $this->assertSame('AgInt no AREsp', $s[0]['classe']);
        $this->assertSame('3080972', $s[0]['numero']);
        $this->assertSame('MG', $s[0]['uf']);
        $this->assertSame('202504090723', $s[0]['numero_registro']);
        $this->assertSame('2026-03-23', $s[0]['data_decisao']);
        $this->assertSame('2026-03-30', $s[0]['data_publicacao']);
    }

    public function testSimilarEmDuasLinhasComEspacosExtras()
    {
        $s = $this->parser->parseAcordaosSimilares([
            "EDcl no AgRg no  REsp  756186  RJ  2005/0091773-4\nDecisão:17/06/2008\nDJE        DATA:05/08/2008",
        ]);

        $this->assertSame('EDcl no AgRg no REsp', $s[0]['classe']);
        $this->assertSame('756186', $s[0]['numero']);
        $this->assertSame('200500917734', $s[0]['numero_registro']);
        $this->assertSame('2008-08-05', $s[0]['data_publicacao']);
    }

    public function testSimilarEraAntigaComDecisaoSemAcentoEPg()
    {
        $s = $this->parser->parseAcordaosSimilares([
            "RESP    130808  SP  1997/0031635-1  DECISAO:16/10/1997\nDJ     DATA:19/12/1997 PG:67474",
        ]);

        $this->assertSame('RESP', $s[0]['classe']);
        $this->assertSame('1997-10-16', $s[0]['data_decisao']);
        $this->assertSame('1997-12-19', $s[0]['data_publicacao']);
    }

    public function testSimilarIlegivelNaoEhDescartado()
    {
        $s = $this->parser->parseAcordaosSimilares(['texto sem padrão algum']);

        $this->assertCount(1, $s);
        $this->assertNull($s[0]['numero_registro']);
        $this->assertSame('texto sem padrão algum', $s[0]['texto_original']);
    }

    // -------------------------------------------------------------------------
    // Campos simples
    // -------------------------------------------------------------------------

    public function testDataPublicacaoPrefereALinhaDj()
    {
        $this->assertSame('2005-04-25', $this->parser->parseDataPublicacao('DJ         DATA:25/04/2005      PG:00268'));
        $this->assertSame('2022-05-06', $this->parser->parseDataPublicacao('DJE        DATA:06/05/2022'));
        $this->assertSame(
            '1990-06-04',
            $this->parser->parseDataPublicacao("REPDJ      DATA:25/06/1990 PG:06045\nDJ         DATA:04/06/1990 PG:05055\nRSTJ       VOL.:00019           PG:00384")
        );
        $this->assertNull($this->parser->parseDataPublicacao(null));
    }

    public function testNumeroRegistroSoDigitos()
    {
        $this->assertSame('202504090723', $this->parser->normalizarNumeroRegistro('2025/0409072-3'));
        $this->assertSame('200200728356', $this->parser->normalizarNumeroRegistro('200200728356'));
        $this->assertNull($this->parser->normalizarNumeroRegistro(null));
    }

    public function testExtrairTermosDeListaEmMaiusculas()
    {
        $termos = $this->parser->extrairTermos(
            "     ILEGALIDADE, LEI ESTADUAL, SP, INCLUSÃO, BASE DE\nCÁLCULO / DECORRÊNCIA, AUMENTO.",
            true
        );

        $this->assertSame(['ILEGALIDADE', 'LEI ESTADUAL', 'SP', 'INCLUSÃO', 'BASE DE CÁLCULO', 'DECORRÊNCIA', 'AUMENTO'], $termos);
    }

    public function testExtrairTermosIgnoraCitacaoLivreEAguardandoAnalise()
    {
        $this->assertSame([], $this->parser->extrairTermos('"[...] em observância aos princípios da economia processual".', true));
        $this->assertSame([], $this->parser->extrairTermos('Aguardando análise.', true));
    }

    public function testTermosAuxiliaresSaoNormalizados()
    {
        $this->assertSame(
            ['IMPOSTO SOBRE CIRCULAÇÃO DE MERCADORIAS E SERVIÇOS (ICMS)'],
            $this->parser->extrairTermos('IMPOSTO SOBRE CIRCULAÇÃO DE MERCADORIAS E SERVIÇOS (ICMS).')
        );
    }
}
