<?php

declare(strict_types=1);

namespace App\Libraries\Site;

use App\Services\NewsService;

/**
 * Provedores dos campos `dinamico` do manifesto (`fonte`). Novos provedores
 * entram com `registrar()`; os padrões só consultam o banco quando a seção que
 * os usa é renderizada.
 */
class DadosDinamicos
{
    /** @var array<string, callable():array> */
    private array $provedores = [];

    public function __construct()
    {
        helper('site');

        $this->provedores['ultimas_noticias'] = static fn (): array => array_map(
            static fn (array $a) => [
                'titulo'  => (string) ($a['title'] ?? ''),
                'resumo'  => (string) ($a['summary'] ?? ''),
                'slug'    => (string) ($a['slug'] ?? ''),
                'data'    => $a['published_at'] ?? null,
                'href'    => site_url_publica('noticias/' . ($a['slug'] ?? '')),
            ],
            (new NewsService())->getLatestPublishedExcept('', 3)
        );

        // Serviços oferecidos (opções do formulário de contato), editáveis em `contato.servicos`.
        $this->provedores['areas'] = static fn (): array => array_map(
            static fn (string $nome) => ['id' => $nome, 'nome' => $nome],
            array_values(array_filter((array) site_config('contato.servicos', []), 'is_string'))
        );
    }

    public function registrar(string $fonte, callable $provedor): void
    {
        $this->provedores[$fonte] = $provedor;
    }

    public function obter(string $fonte): array
    {
        return isset($this->provedores[$fonte]) ? ($this->provedores[$fonte])() : [];
    }
}
