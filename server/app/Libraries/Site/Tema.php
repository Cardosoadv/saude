<?php

declare(strict_types=1);

namespace App\Libraries\Site;

use Config\SiteTemplate;

/**
 * Tema visual do site: cores e fontes que o escritório pode trocar sem mexer no template.
 *
 * O template declara os tokens e seus padrões em `template.json → tema`
 * (`cores`, `fontes`, `fontes_url`); o escritório sobrescreve em
 * `tema.cores.<token>` e `tema.fontes.<token>` nas configurações. Aqui saem:
 *  - o CSS (`:root{--token:valor}`) só dos tokens sobrescritos;
 *  - a URL do Google Fonts com as famílias em uso;
 *  - a lista de tokens (padrão × atual) para a tela de aparência.
 *
 * Tudo que vira CSS é revalidado aqui (hexadecimal estrito, nome de fonte da lista
 * fechada, token do manifesto), então o resultado é seguro para imprimir sem escape.
 */
class Tema
{
    public const RE_COR   = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
    public const RE_TOKEN = '/^[a-z][a-z0-9-]*$/';

    private SiteTemplate $config;

    public function __construct(?SiteTemplate $config = null)
    {
        $this->config = $config ?? config(SiteTemplate::class);
    }

    /**
     * @param array<string, mixed> $tema `tema` do manifesto
     * @param array<string, mixed> $configuracoes mapa de configurações do site
     * @return array{cores: array<string, array{padrao:string, atual:string, personalizado:bool}>, fontes: array<string, array{padrao:string, atual:string, personalizado:bool}>, fontes_disponiveis: array<string, string>}
     */
    public function tokens(array $tema, array $configuracoes): array
    {
        $saida = ['cores' => [], 'fontes' => [], 'fontes_disponiveis' => $this->config->fontesPermitidas];

        foreach ((array) ($tema['cores'] ?? []) as $token => $padrao) {
            $custom = $this->corValida($configuracoes["tema.cores.{$token}"] ?? null);
            $custom = (strcasecmp((string) $custom, (string) $padrao) === 0) ? null : $custom; // igual ao padrão não é personalização
            $saida['cores'][$token] = ['padrao' => $padrao, 'atual' => $custom ?? $padrao, 'personalizado' => $custom !== null];
        }
        foreach ((array) ($tema['fontes'] ?? []) as $token => $padrao) {
            $custom = $this->fontePermitida($configuracoes["tema.fontes.{$token}"] ?? null);
            $custom = $custom === $padrao ? null : $custom;
            $saida['fontes'][$token] = ['padrao' => $padrao, 'atual' => $custom ?? $padrao, 'personalizado' => $custom !== null];
        }

        return $saida;
    }

    /** CSS dos tokens sobrescritos; '' se nada foi personalizado. */
    public function css(array $tema, array $configuracoes): string
    {
        $tokens = $this->tokens($tema, $configuracoes);
        $linhas = [];

        foreach ($tokens['cores'] as $token => $t) {
            if ($t['personalizado'] && preg_match(self::RE_TOKEN, (string) $token)) {
                $linhas[] = "--{$token}:{$t['atual']};";
                $linhas[] = "--{$token}-rgb:" . implode(', ', $this->rgb($t['atual'])) . ';';
            }
        }
        foreach ($tokens['fontes'] as $token => $t) {
            if ($t['personalizado'] && preg_match(self::RE_TOKEN, (string) $token)) {
                $linhas[] = "--font-{$token}:'{$t['atual']}', {$this->config->fontesPermitidas[$t['atual']]};";
            }
        }

        return $linhas === [] ? '' : ':root{' . implode('', $linhas) . '}';
    }

    /**
     * URL do Google Fonts. Sem personalização, a URL do manifesto (pesos e estilos do design
     * original); com troca, a família escolhida entra com os pesos 400 e 700, que todas as da
     * lista têm (pedir um peso que a família não tem faz o Google recusar a folha inteira).
     */
    public function urlFontes(array $tema, array $configuracoes): ?string
    {
        $base = (string) ($tema['fontes_url'] ?? '');
        $tokens = $this->tokens($tema, $configuracoes)['fontes'];
        if ($tokens === []) {
            return $base !== '' ? $base : null;
        }
        if (!array_filter($tokens, static fn ($t) => $t['personalizado'])) {
            return $base !== '' ? $base : $this->monta(array_map(static fn ($t) => [$t['atual'], null], $tokens));
        }

        $originais = $this->segmentosDaUrl($base);
        $familias = [];
        foreach ($tokens as $t) {
            $familias[] = [$t['atual'], $t['personalizado'] ? null : ($originais[$t['atual']] ?? null)];
        }

        return $this->monta($familias);
    }

    public function corValida(mixed $valor): ?string
    {
        return (is_string($valor) && preg_match(self::RE_COR, $valor)) ? $valor : null;
    }

    public function fontePermitida(mixed $valor): ?string
    {
        return (is_string($valor) && isset($this->config->fontesPermitidas[$valor])) ? $valor : null;
    }

    /** @return array{0:int, 1:int, 2:int} */
    public function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param list<array{0:string, 1:?string}> $familias [nome, trecho original da URL ou null] */
    private function monta(array $familias): string
    {
        $partes = [];
        foreach ($familias as [$nome, $original]) {
            $partes[] = $original ?? ('family=' . str_replace(' ', '+', $nome) . ':wght@400;700');
        }

        return 'https://fonts.googleapis.com/css2?' . implode('&', array_unique($partes)) . '&display=swap';
    }

    /** @return array<string, string> [família => 'family=Nome:pesos'] da URL original */
    private function segmentosDaUrl(string $url): array
    {
        $saida = [];
        // `parse_str` junta `family=` repetido; por isso quebramos a query à mão.
        foreach (explode('&', (string) parse_url($url, PHP_URL_QUERY)) as $parte) {
            if (str_starts_with($parte, 'family=')) {
                $nome = urldecode(explode(':', substr($parte, 7), 2)[0]);
                $saida[$nome] = $parte;
            }
        }
        return $saida;
    }
}
