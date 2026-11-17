<?php

declare(strict_types=1);

namespace App\Libraries\Site;

use App\Libraries\Site\Template\SecaoDadosValidator;
use App\Repositories\Site\SitePaginaRepository;

/**
 * Transforma o `dados` gravado de uma seção no que o template recebe, seguindo
 * os tipos de campo do manifesto:
 *
 *  - `rich_text`, `texto_inline`: HTML sanitizado (pode ser impresso sem escape)
 *  - `link`: ganha `href` resolvido (ou `null` se o destino não existe/é inseguro)
 *  - `imagem`: vira `{url, alt, largura, altura}` (ou `null`)
 *  - `dinamico`: preenchido pelo provedor de dados
 *  - demais tipos: valor como está (o template escapa)
 */
class SecaoPreparador
{
    public function __construct(
        private SiteContexto $contexto,
        private DadosDinamicos $dinamicos,
        private ?SitePaginaRepository $paginas = null,
    ) {
        $this->paginas ??= new SitePaginaRepository();
    }

    public function preparar(array $campos, array $dados): array
    {
        $saida = $dados;

        foreach ($campos as $nome => $def) {
            $tipo = $def['tipo'] ?? '';

            if ($tipo === 'dinamico') {
                $saida[$nome] = $this->dinamicos->obter((string) ($def['fonte'] ?? ''));
                continue;
            }
            if (!array_key_exists($nome, $dados) || $dados[$nome] === null) {
                continue;
            }

            $saida[$nome] = $this->campo($tipo, $def, $dados[$nome]);
        }

        return $saida;
    }

    private function campo(string $tipo, array $def, mixed $valor): mixed
    {
        return match ($tipo) {
            'rich_text'    => HtmlSanitizer::richText(is_string($valor) ? $valor : ''),
            'texto_inline' => HtmlSanitizer::inline(is_string($valor) ? $valor : ''),
            'link'         => $this->link($valor),
            'imagem'       => $this->imagem($valor),
            'lista'        => is_array($valor)
                ? array_map(fn ($item) => is_array($item) ? $this->preparar($def['itens'] ?? [], $item) : $item, $valor)
                : [],
            default        => $valor,
        };
    }

    private function link(mixed $link): array
    {
        helper('site');

        $link = is_array($link) ? $link : [];
        $href = SecaoDadosValidator::linkVazio($link) ? null : match ($link['tipo'] ?? null) {
            'pagina' => $this->hrefPagina((int) ($link['pagina_id'] ?? 0)),
            'ancora' => site_url_publica('#' . (string) ($link['ancora'] ?? '')),
            'url'    => $this->contexto->urlExterna((string) ($link['url'] ?? '')),
            default  => null,
        };

        return array_merge($link, ['href' => $href]);
    }

    private function hrefPagina(int $id): ?string
    {
        $pagina = $id > 0 ? $this->paginas->findById($id) : null;

        return ($pagina && $pagina['status'] === 'publicado') ? $this->contexto->urlDaPagina($pagina) : null;
    }

    private function imagem(mixed $id): ?array
    {
        return $this->contexto->midia($id);
    }
}
