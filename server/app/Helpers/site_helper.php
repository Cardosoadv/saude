<?php

use App\Libraries\Site\SiteContexto;

if (!function_exists('site_url_publica')) {
    /**
     * URL pública de um caminho do site: usa `App::$publicURL` (sem a subpasta da instalação,
     * que o .htaccess da raiz já resolve) e, se vazio, a baseURL.
     */
    function site_url_publica(string $caminho = ''): string
    {
        $publica = trim((string) config('App')->publicURL);
        if ($publica === '') {
            return base_url($caminho);
        }

        return rtrim($publica, '/') . '/' . ltrim($caminho, '/');
    }
}

if (!function_exists('site_equipe_usuarios')) {
    /**
     * Membros da equipe cadastrados em Usuários e marcados para aparecer em Quem Somos,
     * já no formato da seção `equipe` (a foto vira uma URL pública própria). [] se a consulta falhar.
     *
     * @return array<int, array<string, mixed>>
     */
    function site_equipe_usuarios(): array
    {
        try {
            $membros = (new \App\Services\UsuariosService())->getEquipeSite();
        } catch (\Throwable $e) {
            log_message('warning', 'Equipe do site indisponível: ' . $e->getMessage());
            return [];
        }

        foreach ($membros as &$m) {
            $m['foto'] = $m['tem_foto'] ? ['url' => site_url_publica('equipe/foto/' . $m['id'])] : null;
        }

        return $membros;
    }
}

if (!function_exists('site_config')) {
    /** Configuração do site (`contato.telefone`, `identidade.nome`…). */
    function site_config(string $chave, mixed $padrao = null): mixed
    {
        return SiteContexto::atual()->config($chave, $padrao);
    }
}

if (!function_exists('site_telefone')) {
    /**
     * Telefone pronto para imprimir (já escapado). Com `contato.telefone_whatsapp` ligado vira link
     * para `wa.me`; sem número válido (DDD + 8 ou 9 dígitos, sem letras como "ramal") fica texto puro.
     */
    function site_telefone(string $telefone): string
    {
        $texto = esc($telefone);
        if (!site_config('contato.telefone_whatsapp', false) || preg_match('/[a-z]/i', $telefone) === 1) {
            return $texto;
        }
        $digitos = preg_replace('/\D+/', '', $telefone) ?? '';
        $digitos = ltrim($digitos, '0');
        if (preg_match('/^(55)?\d{10,11}$/', $digitos) === 1 && strlen($digitos) <= 11) {
            $digitos = '55' . $digitos;
        }
        if (preg_match('/^55\d{10,11}$/', $digitos) !== 1) {
            return $texto;
        }

        return '<a class="telefone-link" href="https://wa.me/' . $digitos . '" target="_blank" rel="noopener">' . $texto . '</a>';
    }
}

if (!function_exists('site_menu')) {
    /** Itens do menu do local, em árvore e com `href` resolvido. */
    function site_menu(string $local): array
    {
        return SiteContexto::atual()->menu($local);
    }
}

if (!function_exists('site_texto')) {
    /** Texto de interface editável (`contato.botao`), com padrão do template. */
    function site_texto(string $chave, string $padrao = ''): string
    {
        return SiteContexto::atual()->texto($chave, $padrao);
    }
}

if (!function_exists('site_asset')) {
    /** URL de um arquivo em `assets/` do template ativo. */
    function site_asset(string $caminho): string
    {
        return SiteContexto::atual()->asset($caminho);
    }
}

if (!function_exists('site_midia')) {
    /** Mídia da biblioteca pelo id: `{url, alt, largura, altura}` ou null. */
    function site_midia(mixed $id): ?array
    {
        return SiteContexto::atual()->midia($id);
    }
}

if (!function_exists('site_imagem')) {
    /**
     * `<img>` (ou `<picture>` com AVIF/WebP, quando a mídia tem variantes) pronto para imprimir.
     * Sempre com `width`/`height` (evita salto de layout) e `decoding="async"`.
     *
     * @param array|null $midia Retorno de `site_midia()`
     * @param array{alt?:string, class?:string, sizes?:string, loading?:string, fetchpriority?:string} $opcoes
     *        `loading`: `lazy` (padrão) ou `eager`; `fetchpriority`: `high` para a imagem principal da página.
     */
    function site_imagem(?array $midia, array $opcoes = []): string
    {
        if (empty($midia['url'])) {
            return '';
        }

        $alt     = (string) ($opcoes['alt'] ?? $midia['alt'] ?? '');
        $sizes   = (string) ($opcoes['sizes'] ?? '100vw');
        $atributos = ['src' => $midia['url'], 'alt' => $alt];
        if (! empty($midia['largura']) && ! empty($midia['altura'])) {
            $atributos['width']  = (int) $midia['largura'];
            $atributos['height'] = (int) $midia['altura'];
        }
        if (! empty($opcoes['class'])) {
            $atributos['class'] = (string) $opcoes['class'];
        }
        $atributos['decoding'] = 'async';
        if (($opcoes['fetchpriority'] ?? '') === 'high') {
            $atributos['fetchpriority'] = 'high';
        } elseif (($opcoes['loading'] ?? 'lazy') !== 'eager') {
            $atributos['loading'] = 'lazy';
        }

        $img = '<img';
        foreach ($atributos as $nome => $valor) {
            $img .= ' ' . $nome . '="' . esc((string) $valor, 'attr') . '"';
        }
        $img .= '>';

        $fontes = '';
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $formato => $mime) {
            if (! empty($midia['srcset'][$formato])) {
                $fontes .= '<source type="' . $mime . '" srcset="' . esc($midia['srcset'][$formato], 'attr') . '" sizes="' . esc($sizes, 'attr') . '">';
            }
        }

        return $fontes === '' ? $img : '<picture>' . $fontes . $img . '</picture>';
    }
}

if (!function_exists('site_jsonld')) {
    /** `<script type="application/ld+json">` da clínica (já seguro para imprimir sem escape). */
    function site_jsonld(array $seo = [], array $pagina = [], ?array $artigo = null): string
    {
        return SiteContexto::atual()->jsonLd($seo, $pagina, $artigo);
    }
}

if (!function_exists('site_tema_css')) {
    /** CSS `:root{...}` das cores e fontes personalizadas (já seguro para imprimir em `<style>`). */
    function site_tema_css(): string
    {
        return SiteContexto::atual()->temaCss();
    }
}

if (!function_exists('site_fontes_url')) {
    /** URL do Google Fonts com as famílias do tema, ou '' se o template não usa fontes do tema. */
    function site_fontes_url(): string
    {
        return SiteContexto::atual()->fontesUrl() ?? '';
    }
}
