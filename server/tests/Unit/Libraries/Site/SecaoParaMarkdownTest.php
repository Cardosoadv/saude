<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\SecaoParaMarkdown;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Texto das seções de página em markdown para o /llms-full.txt (issue #687).
 */
class SecaoParaMarkdownTest extends CIUnitTestCase
{
    public function testTituloLeadECorpoHtml(): void
    {
        $md = SecaoParaMarkdown::converter('intro_area', [
            'titulo' => 'Direito <em>Civil</em>', 'sobretitulo' => 'Áreas', 'lead' => 'Assessoria completa.', 'corpo' => '<p>Nossa <strong>equipe</strong>.</p>',
        ]);

        $this->assertSame("### Direito Civil\n\nAssessoria completa.\n\nNossa **equipe**.", $md);
    }

    public function testListaComDestaqueETextoEListaSimples(): void
    {
        $md = SecaoParaMarkdown::converter('lista_destaque', [
            'titulo' => 'Serviços:', 'marcador' => 'padrao',
            'itens' => [['icone' => 'bi-x', 'destaque' => 'Análise:', 'texto' => 'Estudo do caso.'], ['texto' => 'Indenização']],
        ]);

        $this->assertSame("### Serviços:\n\n- **Análise**: Estudo do caso.\n- Indenização", $md);
    }

    public function testIgnoraOpcoesDeAparenciaENavegacao(): void
    {
        $md = SecaoParaMarkdown::converter('cta_box', ['texto' => 'Precisa de ajuda?', 'botao_rotulo' => 'Agende', 'botao_link' => ['tipo' => 'ancora', 'ancora' => 'contato'], 'estilo' => 'primario']);

        $this->assertSame('Precisa de ajuda?', $md);
    }

    public function testSecoesSoDeNavegacaoSaemVazias(): void
    {
        foreach (['lateral_outras_areas', 'ultimas_noticias', 'dados_controlador', 'contato'] as $tipo) {
            $this->assertSame('', SecaoParaMarkdown::converter($tipo, ['titulo' => 'Qualquer', 'descricao' => 'texto longo aqui']), $tipo);
        }
    }

    public function testPerguntasEResposta(): void
    {
        $this->assertTrue(SecaoParaMarkdown::ehPerguntasFrequentes('faq'));
        $this->assertFalse(SecaoParaMarkdown::ehPerguntasFrequentes('rich_text'));

        $md = SecaoParaMarkdown::converter('faq', ['titulo' => 'Dúvidas', 'itens' => [['pergunta' => 'Quanto custa?', 'resposta' => '<p>Depende do caso.</p>']]]);

        $this->assertSame("### Dúvidas\n\n- **Quanto custa?**: Depende do caso.", $md);
    }
}
