<?php

declare(strict_types=1);

namespace App\Libraries\Site\Motor;

use App\Libraries\Site\SiteContexto;
use App\Libraries\Site\Template\TwigSandboxPolicy;

/**
 * Motor para templates enviados pelo painel: Twig em sandbox (lista branca em
 * TwigSandboxPolicy). Só recebe arrays e escalares.
 */
final class TwigMotor implements MotorDeView
{
    public function __construct(private SiteContexto $contexto)
    {
    }

    public function secao(string $diretorio, string $tipo, array $vars): ?string
    {
        return NomeSeguro::valido($tipo) ? $this->renderiza($diretorio, "sections/{$tipo}.twig", $vars) : null;
    }

    public function layout(string $diretorio, string $nome, array $vars): ?string
    {
        return NomeSeguro::valido($nome) ? $this->renderiza($diretorio, "layouts/{$nome}.twig", $vars) : null;
    }

    public function pagina(string $diretorio, string $nome, array $vars): ?string
    {
        return NomeSeguro::valido($nome) ? $this->renderiza($diretorio, "pages/{$nome}.twig", $vars) : null;
    }

    private function renderiza(string $diretorio, string $arquivo, array $vars): ?string
    {
        if (!is_file(rtrim($diretorio, '/\\') . '/' . $arquivo)) {
            return null;
        }

        $twig = TwigSandboxPolicy::ambiente($diretorio, [
            'site_config' => fn (string $chave, mixed $padrao = null) => $this->contexto->config($chave, $padrao),
            'site_menu'   => fn (string $local) => $this->contexto->menu($local),
            'site_texto'  => fn (string $chave, string $padrao = '') => $this->contexto->texto($chave, $padrao),
            'site_jsonld' => fn (array $seo = [], array $pagina = [], ?array $artigo = null) => new \Twig\Markup($this->contexto->jsonLd($seo, $pagina, $artigo), 'UTF-8'),
            'site_tema_css'   => fn () => new \Twig\Markup($this->contexto->temaCss(), 'UTF-8'),
            'site_fontes_url' => fn () => $this->contexto->fontesUrl() ?? '',
            'site_midia'  => fn (mixed $id) => $this->contexto->midia($id),
            'asset'       => fn (string $caminho) => $this->contexto->asset($caminho),
        ]);

        return $twig->render($arquivo, $vars);
    }
}
