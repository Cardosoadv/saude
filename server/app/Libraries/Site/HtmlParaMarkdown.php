<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Converte o HTML do conteúdo do site (seções e notícias) em markdown limpo para o /llms-full.txt:
 * mantém títulos, parágrafos, ênfase, listas, citações e links; descarta scripts, estilos, atributos
 * e qualquer tag desconhecida (o texto dentro dela fica). Links relativos viram absolutos.
 */
final class HtmlParaMarkdown
{
    private const DESCARTAR = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'form', 'button', 'input', 'select', 'textarea', 'head'];

    /**
     * @param int $nivelMinimo nível do menor título gerado (um `<h1>` vira este nível; 4 = `####`)
     */
    public static function converter(string $html, int $nivelMinimo = 3): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!str_contains($html, '<')) {
            return self::limpar(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $dom->getElementById('raiz');
        if ($raiz === null) {
            return self::limpar(strip_tags($html));
        }

        return self::limpar(self::filhos($raiz, max(1, min(6, $nivelMinimo)), 0));
    }

    private static function filhos(\DOMNode $no, int $nivelMinimo, int $profundidadeDeLista): string
    {
        $saida = '';
        foreach ($no->childNodes as $filho) {
            $saida .= self::no($filho, $nivelMinimo, $profundidadeDeLista);
        }

        return $saida;
    }

    private static function no(\DOMNode $no, int $nivelMinimo, int $profundidade): string
    {
        if ($no instanceof \DOMText) {
            return preg_replace('/\s+/u', ' ', $no->nodeValue ?? '') ?? '';
        }
        if (!$no instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($no->nodeName);
        if (in_array($tag, self::DESCARTAR, true)) {
            return '';
        }
        $dentro = fn (?int $p = null) => self::filhos($no, $nivelMinimo, $p ?? $profundidade);

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                $nivel = min(6, $nivelMinimo + (int) $tag[1] - 1);
                $texto = trim(preg_replace('/\s+/u', ' ', $no->textContent) ?? '');

                return $texto === '' ? '' : "\n\n" . str_repeat('#', $nivel) . ' ' . $texto . "\n\n";
            case 'p': case 'div': case 'section': case 'article': case 'figure': case 'figcaption': case 'table': case 'tr':
                return "\n\n" . trim($dentro()) . "\n\n";
            case 'br':
                return "\n";
            case 'hr':
                return "\n\n---\n\n";
            case 'strong': case 'b':
                return self::envolve($dentro(), '**');
            case 'em': case 'i':
                return self::envolve($dentro(), '*');
            case 'code':
                return self::envolve($dentro(), '`');
            case 'blockquote':
                $linhas = preg_split('/\n/', trim(self::limpar($dentro()))) ?: [];

                return "\n\n" . implode("\n", array_map(static fn ($l) => '> ' . $l, $linhas)) . "\n\n";
            case 'ul': case 'ol':
                return "\n\n" . self::lista($no, $tag === 'ol', $nivelMinimo, $profundidade) . "\n\n";
            case 'td': case 'th':
                return trim($dentro()) . ' ';
            case 'a':
                return self::link($no, trim($dentro()));
            default:
                return $dentro();
        }
    }

    private static function lista(\DOMElement $lista, bool $ordenada, int $nivelMinimo, int $profundidade): string
    {
        $linhas = [];
        $n = 0;
        foreach ($lista->childNodes as $item) {
            if (!$item instanceof \DOMElement || strtolower($item->nodeName) !== 'li') {
                continue;
            }
            $n++;
            $marcador = str_repeat('  ', $profundidade) . ($ordenada ? "{$n}." : '-') . ' ';
            $texto = trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', self::limparSemQuebrasDeLista(self::filhos($item, $nivelMinimo, $profundidade + 1))) ?? '');
            $linhas[] = $marcador . $texto;
            // Listas aninhadas ficam em linhas próprias, com o recuo da profundidade.
            foreach ($item->childNodes as $sub) {
                if ($sub instanceof \DOMElement && in_array(strtolower($sub->nodeName), ['ul', 'ol'], true)) {
                    $linhas[] = self::lista($sub, strtolower($sub->nodeName) === 'ol', $nivelMinimo, $profundidade + 1);
                }
            }
        }

        return implode("\n", array_filter($linhas, static fn ($l) => trim($l) !== '-'));
    }

    /** Texto de um `<li>` sem o das listas aninhadas (que são tratadas à parte). */
    private static function limparSemQuebrasDeLista(string $texto): string
    {
        $linhas = array_filter(explode("\n", $texto), static fn ($l) => !preg_match('/^\s*(?:[-]|\d+\.)\s/', $l));

        return implode(' ', $linhas);
    }

    private static function link(\DOMElement $a, string $texto): string
    {
        $href = trim($a->getAttribute('href'));
        if ($texto === '' || $href === '' || str_starts_with($href, '#') || !HtmlSanitizer::hrefSeguro($href)) {
            return $texto;
        }
        if ($href[0] === '/') {
            helper('site');
            $href = site_url_publica(ltrim($href, '/'));
        }

        return '[' . str_replace(['[', ']'], ['(', ')'], $texto) . '](' . $href . ')';
    }

    private static function envolve(string $texto, string $marca): string
    {
        $miolo = trim($texto);

        return $miolo === '' ? '' : $marca . $miolo . $marca;
    }

    /** Remove espaços sobrando e deixa no máximo uma linha em branco entre blocos. */
    private static function limpar(string $texto): string
    {
        $texto = str_replace("\xC2\xA0", ' ', $texto);
        $texto = preg_replace('/[ \t]+\n/', "\n", $texto) ?? $texto;
        $texto = preg_replace('/\n[ \t]+(?=\S)(?![-*>#\d])/', "\n", $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}
