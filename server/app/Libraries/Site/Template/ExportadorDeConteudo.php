<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use App\Models\Site\SiteConfiguracaoModel;
use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;

/**
 * Monta o `seed/content.json` a partir do conteúdo atual do site, para exportar um template
 * junto com o conteúdo (replicar o site em outra instalação). É o caminho inverso do SeedImporter:
 * links para páginas viram `slug`; campos de imagem e configurações de imagem ficam de fora,
 * porque o id da mídia não vale em outra instalação.
 */
class ExportadorDeConteudo
{
    public function __construct(
        private ?SiteConfiguracaoModel $configuracoes = null,
        private ?SitePaginaModel $paginas = null,
        private ?SiteSecaoModel $secoes = null,
        private ?SiteMenuModel $menus = null,
        private ?SiteMenuItemModel $itens = null,
    ) {
        $this->configuracoes ??= new SiteConfiguracaoModel();
        $this->paginas       ??= new SitePaginaModel();
        $this->secoes        ??= new SiteSecaoModel();
        $this->menus         ??= new SiteMenuModel();
        $this->itens         ??= new SiteMenuItemModel();
    }

    /** @return array{configuracoes: array, paginas: list<array>, menus: array} */
    public function seed(array $manifesto): array
    {
        $paginas = $this->paginas->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();
        $slugs = array_column($paginas, 'slug', 'id');
        $tipos = $manifesto['secoes'] ?? [];

        return [
            'configuracoes' => $this->exportaConfiguracoes(),
            'paginas'       => array_map(fn (array $p) => $this->exportaPagina($p, $slugs, $tipos), $paginas),
            'menus'         => $this->exportaMenus($slugs),
        ];
    }

    private function exportaConfiguracoes(): array
    {
        $saida = [];
        foreach ($this->configuracoes->orderBy('chave', 'ASC')->findAll() as $c) {
            if ($c['tipo'] === 'imagem' || $c['valor'] === null) {
                continue;
            }
            $valor = $c['tipo'] === 'json' ? json_decode($c['valor'], true) : ($c['tipo'] === 'bool' ? $c['valor'] === '1' : $c['valor']);
            $saida[$c['chave']] = ['valor' => $valor, 'tipo' => $c['tipo']];
        }
        return $saida;
    }

    private function exportaPagina(array $p, array $slugs, array $tipos): array
    {
        $secoes = [];
        foreach ($this->secoes->where('pagina_id', $p['id'])->where('visivel', 1)->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll() as $s) {
            $campos = $tipos[$s['tipo']]['campos'] ?? [];
            $secao = ['tipo' => $s['tipo'], 'dados' => $this->exportaDados($campos, json_decode((string) $s['dados'], true) ?? [], $slugs)];
            if ($s['ancora']) {
                $secao['ancora'] = $s['ancora'];
            }
            $secoes[] = $secao;
        }

        return array_filter([
            'slug' => $p['slug'], 'titulo' => $p['titulo'], 'tipo' => $p['tipo'], 'layout' => $p['layout'], 'ordem' => (int) $p['ordem'],
            'status' => $p['status'], 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'],
            'exibir_sitemap' => (bool) $p['exibir_sitemap'], 'prioridade_sitemap' => (float) $p['prioridade_sitemap'], 'changefreq' => $p['changefreq'],
        ], static fn ($v) => $v !== null) + ['secoes' => $secoes];
    }

    /** @param array<int, string> $slugs [id da página => slug] */
    private function exportaDados(array $campos, array $dados, array $slugs): array
    {
        foreach ($campos as $nome => $def) {
            if (!array_key_exists($nome, $dados)) {
                continue;
            }
            switch ($def['tipo'] ?? '') {
                case 'imagem':
                    unset($dados[$nome]);
                    break;
                case 'link':
                    if (is_array($dados[$nome]) && ($dados[$nome]['tipo'] ?? '') === 'pagina') {
                        $slug = $slugs[$dados[$nome]['pagina_id'] ?? 0] ?? null;
                        $dados[$nome] = $slug !== null ? ['tipo' => 'pagina', 'slug' => $slug] : null;
                        if ($dados[$nome] === null) {
                            unset($dados[$nome]);
                        }
                    }
                    break;
                case 'lista':
                    $dados[$nome] = array_map(
                        fn ($item) => is_array($item) ? $this->exportaDados($def['itens'] ?? [], $item, $slugs) : $item,
                        (array) $dados[$nome]
                    );
                    break;
            }
        }
        return $dados;
    }

    /** @param array<int, string> $slugs */
    private function exportaMenus(array $slugs): array
    {
        $saida = [];
        foreach ($this->menus->orderBy('local', 'ASC')->findAll() as $menu) {
            $linhas = $this->itens->where('menu_id', $menu['id'])->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();
            $porPai = [];
            foreach ($linhas as $l) {
                $porPai[$l['parent_id'] ?? 0][] = $l;
            }
            $monta = function (int $pai) use (&$monta, $porPai, $slugs): array {
                $itens = [];
                foreach ($porPai[$pai] ?? [] as $l) {
                    if ($l['tipo'] === 'pagina' && !isset($slugs[$l['pagina_id']])) {
                        continue;
                    }
                    $item = array_filter([
                        'rotulo' => $l['rotulo'], 'tipo' => $l['tipo'],
                        'pagina' => $l['tipo'] === 'pagina' ? $slugs[$l['pagina_id']] : null,
                        'url' => $l['url'], 'ancora' => $l['ancora'], 'destaque' => $l['destaque'] ? true : null,
                    ], static fn ($v) => $v !== null);
                    $filhos = $monta((int) $l['id']);
                    if ($filhos !== []) {
                        $item['filhos'] = $filhos;
                    }
                    $itens[] = $item;
                }
                return $itens;
            };
            $saida[$menu['local']] = $monta(0);
        }
        return $saida;
    }
}
