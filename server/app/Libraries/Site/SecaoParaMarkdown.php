<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Texto de uma seção de página (`site_secoes.dados`) em markdown, para o /llms-full.txt.
 * Não conhece os tipos de seção de cada template: percorre os dados e escreve só o que é texto
 * (títulos, parágrafos, HTML do corpo, itens de lista e perguntas/respostas), ignorando
 * ícones, links, botões e opções de aparência.
 */
final class SecaoParaMarkdown
{
    /** Seções que só repetem dados de outras fontes ou são puramente de navegação. */
    private const TIPOS_IGNORADOS = ['lateral_outras_areas', 'ultimas_noticias', 'dados_controlador', 'dados_institucionais', 'contato'];

    /** Chaves de aparência, navegação e identificação: nunca viram texto. */
    private const CHAVES_IGNORADAS = [
        'sobretitulo', 'icone', 'link', 'botoes', 'botao_rotulo', 'botao_link', 'estilo', 'colunas', 'marcador',
        'menu', 'ancora', 'imagem', 'imagens', 'id', 'classe', 'cor', 'rotulo', 'url', 'layout', 'alt',
    ];

    /** Chaves que sempre guardam texto, mesmo de uma palavra só. */
    private const CHAVES_DE_TEXTO = ['texto', 'lead', 'subtitulo', 'descricao', 'corpo', 'resposta', 'curriculo', 'cargo', 'registro', 'areas'];

    /** Chaves que nomeiam o item de uma lista (vão em negrito). */
    private const CHAVES_DE_NOME = ['destaque', 'titulo', 'pergunta', 'nome'];

    public static function ehPerguntasFrequentes(string $tipo): bool
    {
        return str_contains(strtolower($tipo), 'faq');
    }

    /** @param array<string, mixed> $dados */
    public static function converter(string $tipo, array $dados): string
    {
        if (in_array($tipo, self::TIPOS_IGNORADOS, true)) {
            return '';
        }

        $blocos = [];
        $titulo = self::semMarcas($dados['titulo'] ?? '');
        if ($titulo !== '') {
            $blocos[] = '### ' . self::umaLinha($titulo);
        }

        foreach ($dados as $chave => $valor) {
            if ($chave === 'titulo' || in_array($chave, self::CHAVES_IGNORADAS, true)) {
                continue;
            }
            if (is_string($valor)) {
                $texto = self::paragrafo((string) $chave, $valor);
                if ($texto !== '') {
                    $blocos[] = $texto;
                }
            } elseif (is_array($valor) && array_is_list($valor)) {
                $lista = self::lista($valor);
                if ($lista !== '') {
                    $blocos[] = $lista;
                }
            }
        }

        return implode("\n\n", $blocos);
    }

    /** @param list<mixed> $itens */
    private static function lista(array $itens): string
    {
        $linhas = [];
        foreach ($itens as $item) {
            if (is_string($item)) {
                $texto = self::umaLinha(self::semMarcas($item));
                if ($texto !== '') {
                    $linhas[] = '- ' . $texto;
                }
                continue;
            }
            if (!is_array($item)) {
                continue;
            }

            $nome = '';
            $resto = [];
            foreach ($item as $chave => $valor) {
                if (!is_string($valor) || in_array($chave, self::CHAVES_IGNORADAS, true)) {
                    continue;
                }
                $texto = self::umaLinha(self::texto($valor));
                if ($texto === '') {
                    continue;
                }
                if ($nome === '' && in_array($chave, self::CHAVES_DE_NOME, true)) {
                    $nome = rtrim(self::semMarcas($valor), ': ');
                } elseif (self::ehTexto((string) $chave, $valor)) {
                    $resto[] = $texto;
                }
            }

            $corpo = implode(' — ', $resto);
            $linha = $nome !== '' ? '**' . $nome . '**' . ($corpo !== '' ? ': ' . $corpo : '') : $corpo;
            if ($linha !== '') {
                $linhas[] = '- ' . $linha;
            }
        }

        return implode("\n", $linhas);
    }

    /** Parágrafo de texto; valores de uma palavra só (`padrao`, `primario`) são opções, não conteúdo. */
    private static function paragrafo(string $chave, string $valor): string
    {
        return self::ehTexto($chave, $valor) ? HtmlParaMarkdown::converter($valor, 4) : '';
    }

    private static function ehTexto(string $chave, string $valor): bool
    {
        return in_array($chave, self::CHAVES_DE_TEXTO, true) || str_contains($valor, '<') || preg_match('/\s/u', trim($valor)) === 1;
    }

    /** Texto puro numa linha, sem tags nem marcas de markdown (títulos e nomes de item). */
    private static function semMarcas(mixed $valor): string
    {
        return is_string($valor) ? self::umaLinha(html_entity_decode(strip_tags($valor), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    /** Texto sem tags (para títulos e itens). */
    private static function texto(mixed $valor): string
    {
        return is_string($valor) ? HtmlParaMarkdown::converter($valor, 4) : '';
    }

    private static function umaLinha(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }
}
