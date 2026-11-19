<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Monta o robots.txt do site: bloqueia a API, o painel e as áreas do cliente,
 * mantém os rastreadores de IA liberados e informa o endereço do sitemap.
 * A clínica pode acrescentar regras próprias (`seo.robots_extra`), validadas aqui.
 */
final class RobotsTxt
{
    public const TAMANHO_MAXIMO_EXTRA = 2000;

    /** Rastreadores de IA liberados de propósito (a análise de GEO valoriza o acesso aberto). */
    private const ROBOS_DE_IA = ['GPTBot', 'Google-Extended', 'PerplexityBot', 'ClaudeBot'];

    private const DIRETIVAS_DO_EXTRA = ['user-agent', 'allow', 'disallow', 'crawl-delay'];

    public static function gerar(string $urlDoSitemap, string $extra = ''): string
    {
        $linhas = ['User-agent: *', 'Allow: /'];
        foreach (AreasRestritas::CAMINHOS_BLOQUEADOS as $caminho) {
            $linhas[] = 'Disallow: ' . $caminho;
        }
        $linhas[] = 'Disallow: /*?preview=';
        $linhas[] = '';
        $linhas[] = '# Crawlers de IA liberados';
        foreach (self::ROBOS_DE_IA as $robo) {
            $linhas[] = 'User-agent: ' . $robo;
            $linhas[] = 'Allow: /';
        }

        $extra = trim(str_replace("\r\n", "\n", $extra));
        if ($extra !== '' && self::erroNoExtra($extra) === null) {
            $linhas[] = '';
            $linhas[] = '# Regras adicionais da clínica';
            array_push($linhas, ...explode("\n", $extra));
        }

        $linhas[] = '';
        $linhas[] = 'Sitemap: ' . $urlDoSitemap;

        return implode("\n", $linhas) . "\n";
    }

    /**
     * Valida o texto adicional: só `User-agent`, `Allow`, `Disallow` e `Crawl-delay` (e comentários),
     * começando por um `User-agent`, para que as regras não caiam no grupo do robô anterior.
     *
     * @return string|null mensagem de erro, ou null se o texto é aceito
     */
    public static function erroNoExtra(string $texto): ?string
    {
        $texto = trim(str_replace("\r\n", "\n", $texto));
        if ($texto === '') {
            return null;
        }
        if (mb_strlen($texto) > self::TAMANHO_MAXIMO_EXTRA) {
            return 'Máximo de ' . self::TAMANHO_MAXIMO_EXTRA . ' caracteres.';
        }
        if (preg_match('/(?![\n\t])\p{C}/u', $texto)) {
            return 'Contém caracteres de controle.';
        }

        $primeira = true;
        foreach (explode("\n", $texto) as $numero => $linha) {
            $linha = trim($linha);
            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }
            if (!preg_match('/^([A-Za-z-]+)\s*:\s*\S.*$/', $linha, $m) || !in_array(strtolower($m[1]), self::DIRETIVAS_DO_EXTRA, true)) {
                return 'Linha ' . ($numero + 1) . ': use apenas User-agent, Allow, Disallow ou Crawl-delay.';
            }
            if ($primeira && strtolower($m[1]) !== 'user-agent') {
                return 'Linha ' . ($numero + 1) . ': comece por uma linha User-agent.';
            }
            $primeira = false;
        }

        return null;
    }
}
