<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Sanitizador de HTML do conteúdo do site (lista branca).
 *
 * A lista de tags de `richText()` é a mesma do editor da SPA
 * (sistema/src/composables/useDocumentoEditor.ts): se uma mudar, a outra
 * precisa acompanhar. Tudo fora da lista perde a tag e mantém o texto;
 * `script`, `style` e afins perdem também o conteúdo. Atributos são todos
 * removidos, exceto `href` em `<a>`, que só aceita http(s), mailto, tel,
 * caminho relativo ou âncora.
 */
final class HtmlSanitizer
{
    public const TAGS_RICH   = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'blockquote'];
    public const TAGS_INLINE = ['em', 'strong'];

    private const DESCARTA = ['script', 'style', 'iframe', 'object', 'embed', 'meta', 'link', 'title', 'head', 'template', 'noscript', 'svg', 'math', 'form', 'textarea', 'select'];

    public static function richText(?string $html): string
    {
        return self::limpar($html, self::TAGS_RICH);
    }

    public static function inline(?string $html): string
    {
        return self::limpar($html, self::TAGS_INLINE);
    }

    /** @param list<string> $permitidas */
    private static function limpar(?string $html, array $permitidas): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        // O prefixo XML força UTF-8; o wrapper evita que o libxml reorganize o fragmento.
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="raiz">' . $html . '</div></body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $doc->getElementById('raiz');
        if (!$raiz) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::limparNo($raiz, $permitidas);

        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $doc->saveHTML($filho);
        }
        return trim($saida);
    }

    private static function limparNo(\DOMNode $pai, array $permitidas): void
    {
        foreach (iterator_to_array($pai->childNodes) as $no) {
            if ($no instanceof \DOMText) {
                continue;
            }
            if (!$no instanceof \DOMElement) {
                $pai->removeChild($no); // comentários, instruções de processamento
                continue;
            }

            $tag = strtolower($no->tagName);

            if (in_array($tag, self::DESCARTA, true)) {
                $pai->removeChild($no);
                continue;
            }

            self::limparNo($no, $permitidas);

            if (!in_array($tag, $permitidas, true)) {
                while ($no->firstChild) {
                    $pai->insertBefore($no->firstChild, $no);
                }
                $pai->removeChild($no);
                continue;
            }

            self::limparAtributos($no, $tag);
        }
    }

    private static function limparAtributos(\DOMElement $no, string $tag): void
    {
        $href = ($tag === 'a') ? $no->getAttribute('href') : '';

        foreach (iterator_to_array($no->attributes) as $attr) {
            $no->removeAttribute($attr->name);
        }

        if ($tag === 'a' && self::hrefSeguro($href)) {
            $no->setAttribute('href', $href);
            if (preg_match('#^https?://#i', $href)) {
                $no->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    public static function hrefSeguro(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x20]/', $href) || str_starts_with($href, '//')) {
            return false;
        }
        if ($href[0] === '/' || $href[0] === '#') {
            return true;
        }
        return preg_match('#^(https?://[^/\s]+|mailto:[^@\s]+@[^@\s]+|tel:\+?[0-9()-]+)#i', $href) === 1;
    }
}
