<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * SEO (`titulo`, `descricao`, `url`) de um artigo, passado como `$seo` a
 * `SiteRenderService::renderTela()` (a lista usa a página de sistema `noticias`). Título = `meta_title` (ou título)
 * + `seo.sufixo_titulo` das configurações.
 */
final class SeoNoticias
{
    public function __construct(private SiteContexto $contexto)
    {
    }

    /** @return array{titulo:string, descricao:?string, url:string} */
    public function artigo(array $artigo): array
    {
        helper('site');

        $titulo    = trim((string) ($artigo['meta_title'] ?? '')) ?: (string) ($artigo['title'] ?? '');
        $descricao = trim((string) ($artigo['meta_description'] ?? '')) ?: trim((string) ($artigo['summary'] ?? ''));

        return [
            'titulo'       => $titulo . $this->sufixo(),
            'descricao' => $descricao !== '' ? mb_substr($descricao, 0, 160) : $this->descricaoPadrao(),
            'url'         => site_url_publica('noticias/' . ($artigo['slug'] ?? '')),
        ];
    }

    private function sufixo(): string
    {
        return (string) $this->contexto->config('seo.sufixo_titulo', '');
    }

    private function descricaoPadrao(): ?string
    {
        $descricao = $this->contexto->config('seo.descricao_padrao');

        return $descricao !== null ? (string) $descricao : null;
    }
}
