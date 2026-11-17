<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use App\Repositories\Site\SiteConfiguracaoRepository;
use App\Repositories\Site\SiteMenuRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;

/**
 * Importa o conteúdo inicial de um template (`seed/content.json`) SEM
 * sobrescrever nada do que o escritório já tem:
 *
 *  - configuração: só as chaves que ainda não existem;
 *  - página: só os slugs que ainda não existem (as demais ficam como estão);
 *  - menu: só locais inexistentes ou vazios.
 *
 * Por isso pode rodar mais de uma vez com o mesmo resultado. Seções com dados
 * que não passam pelo schema do manifesto não são criadas e vão para `erros`.
 *
 * Formato do seed: ver docs/site-templates.md ("Conteúdo inicial").
 */
class SeedImporter
{
    public function __construct(
        private ?SiteConfiguracaoRepository $configuracoes = null,
        private ?SitePaginaModel $paginas = null,
        private ?SiteSecaoModel $secoes = null,
        private ?SiteMenuModel $menus = null,
        private ?SiteMenuItemModel $itensMenu = null,
        private ?SecaoDadosValidator $validator = null,
    ) {
        $this->configuracoes ??= (new SiteConfiguracaoRepository())->semAuditoria();
        $this->paginas       ??= new SitePaginaModel();
        $this->secoes        ??= new SiteSecaoModel();
        $this->menus         ??= new SiteMenuModel();
        $this->itensMenu     ??= new SiteMenuItemModel();
        $this->validator     ??= new SecaoDadosValidator();
    }

    /**
     * @param array{configuracoes?:array, paginas?:array, menus?:array} $seed
     * @param array $manifesto `template.json` já decodificado
     * @param string|null $statusPaginas Força o status das páginas criadas (ex.: 'rascunho'); null mantém o do seed
     * @return array{configuracoes:list<string>, paginas:list<string>, paginas_ignoradas:list<string>, menus:list<string>, erros:array<string,string>}
     */
    public function importar(array $seed, array $manifesto, ?string $statusPaginas = null): array
    {
        $relatorio = ['configuracoes' => [], 'paginas' => [], 'paginas_ignoradas' => [], 'menus' => [], 'erros' => []];
        $tipos = $manifesto['secoes'] ?? [];

        $db = \Config\Database::connect();
        $db->transStart();

        $this->importaConfiguracoes($seed['configuracoes'] ?? [], $relatorio);
        $secoesPendentes = $this->importaPaginas($seed['paginas'] ?? [], $statusPaginas, $relatorio);
        $this->importaSecoes($secoesPendentes, $tipos, $relatorio);
        $this->importaMenus($seed['menus'] ?? [], $relatorio);

        $db->transComplete();
        if (!$db->transStatus()) {
            throw new \RuntimeException('Falha ao gravar o conteúdo inicial do template.');
        }

        (new SitePaginaRepository())->clearCache();
        (new SiteSecaoRepository())->clearCache();
        (new SiteMenuRepository())->clearCache();
        $this->configuracoes->clearCache();
        service('cache')->delete('sitemap'); // callbacks desligados na importação: invalida à mão

        return $relatorio;
    }

    private function importaConfiguracoes(array $configs, array &$relatorio): void
    {
        $existentes = $this->configuracoes->mapa();
        foreach ($configs as $chave => $def) {
            if (array_key_exists($chave, $existentes)) {
                continue;
            }
            $this->configuracoes->definir($chave, $def['valor'] ?? null, $def['tipo'] ?? 'string');
            $relatorio['configuracoes'][] = $chave;
        }
    }

    /** @return list<array{pagina_id:int, secao:array}> */
    private function importaPaginas(array $paginas, ?string $statusForcado, array &$relatorio): array
    {
        $pendentes = [];
        foreach ($paginas as $pagina) {
            $slug = (string) ($pagina['slug'] ?? '');
            if ($this->paginas->where('slug', $slug)->withDeleted()->first()) {
                $relatorio['paginas_ignoradas'][] = $slug;
                continue;
            }

            $dados = array_diff_key($pagina, ['secoes' => 1]);
            if ($statusForcado !== null) {
                $dados['status'] = $statusForcado;
            }
            if (($dados['status'] ?? '') === 'publicado') {
                $dados['publicado_em'] = date('Y-m-d H:i:s');
            }

            $id = $this->paginas->allowCallbacks(false)->insert($dados);
            if (!$id) {
                $relatorio['erros']["paginas.{$slug}"] = implode(' ', $this->paginas->errors());
                continue;
            }
            $relatorio['paginas'][] = $slug;

            foreach (array_values($pagina['secoes'] ?? []) as $ordem => $secao) {
                $pendentes[] = ['pagina_id' => (int) $id, 'slug' => $slug, 'ordem' => $ordem + 1, 'secao' => $secao];
            }
        }
        return $pendentes;
    }

    /** Seções entram depois de todas as páginas para os links por slug já se resolverem. */
    private function importaSecoes(array $pendentes, array $tipos, array &$relatorio): void
    {
        foreach ($pendentes as $p) {
            $secao  = $p['secao'];
            $tipo   = (string) ($secao['tipo'] ?? '');
            $local  = "paginas.{$p['slug']}.secoes.{$p['ordem']}";

            if (!isset($tipos[$tipo])) {
                $relatorio['erros'][$local] = "Tipo de seção '{$tipo}' não existe no manifesto.";
                continue;
            }

            $campos = $tipos[$tipo]['campos'] ?? [];
            $dados  = $this->validator->aplicarPadroes($campos, $this->resolveLinks($campos, $secao['dados'] ?? []));
            $res    = $this->validator->validar($campos, $dados);
            if (!$res->valido()) {
                foreach ($res->erros() as $caminho => $mensagem) {
                    $relatorio['erros']["{$local}.{$caminho}"] = $mensagem;
                }
                continue;
            }

            $this->secoes->allowCallbacks(false)->insert([
                'pagina_id' => $p['pagina_id'],
                'tipo'      => $tipo,
                'ancora'    => $secao['ancora'] ?? null,
                'ordem'     => $p['ordem'],
                'visivel'   => 1,
                'dados'     => json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    private function importaMenus(array $menus, array &$relatorio): void
    {
        foreach ($menus as $local => $itens) {
            $menu = $this->menus->where('local', $local)->first();
            if ($menu && $this->itensMenu->where('menu_id', $menu['id'])->countAllResults() > 0) {
                continue;
            }

            $menuId = $menu['id'] ?? $this->menus->allowCallbacks(false)->insert(['local' => $local]);
            $this->gravaItens((int) $menuId, $itens, null, "menus.{$local}", $relatorio);
            $relatorio['menus'][] = $local;
        }
    }

    /** @param list<array<string, mixed>> $itens (aceita `filhos`, um nível) */
    private function gravaItens(int $menuId, array $itens, ?int $paiId, string $caminho, array &$relatorio): void
    {
        foreach (array_values($itens) as $ordem => $item) {
            $paginaId = null;
            if (($item['tipo'] ?? '') === 'pagina') {
                $pagina = $this->paginas->where('slug', $item['pagina'] ?? '')->first();
                if (!$pagina) {
                    $relatorio['erros']["{$caminho}.{$ordem}"] = "Página '" . ($item['pagina'] ?? '') . "' não existe.";
                    continue;
                }
                $paginaId = $pagina['id'];
            }

            $id = $this->itensMenu->allowCallbacks(false)->insert([
                'menu_id'   => $menuId,
                'parent_id' => $paiId,
                'rotulo'    => $item['rotulo'],
                'tipo'      => $item['tipo'],
                'pagina_id' => $paginaId,
                'url'       => $item['url'] ?? null,
                'ancora'    => $item['ancora'] ?? null,
                'destaque'  => !empty($item['destaque']) ? 1 : 0,
                'ordem'     => $ordem + 1,
            ]);
            if ($id && !empty($item['filhos']) && $paiId === null) {
                $this->gravaItens($menuId, $item['filhos'], (int) $id, "{$caminho}.{$ordem}.filhos", $relatorio);
            }
        }
    }

    /**
     * Troca `{tipo:'pagina', slug}` por `{tipo:'pagina', pagina_id}` em todos
     * os campos `link` (inclusive dentro de listas).
     */
    private function resolveLinks(array $campos, array $dados): array
    {
        foreach ($campos as $nome => $def) {
            if (!isset($dados[$nome])) {
                continue;
            }
            if (($def['tipo'] ?? '') === 'link' && is_array($dados[$nome]) && ($dados[$nome]['tipo'] ?? '') === 'pagina' && isset($dados[$nome]['slug'])) {
                $pagina = $this->paginas->where('slug', $dados[$nome]['slug'])->first();
                $dados[$nome] = ['tipo' => 'pagina', 'pagina_id' => (int) ($pagina['id'] ?? 0)];
            } elseif (($def['tipo'] ?? '') === 'lista' && is_array($dados[$nome])) {
                $dados[$nome] = array_map(
                    fn ($item) => is_array($item) ? $this->resolveLinks($def['itens'] ?? [], $item) : $item,
                    $dados[$nome]
                );
            }
        }
        return $dados;
    }
}
