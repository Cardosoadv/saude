<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\HtmlSanitizer;
use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SitePaginaModel;

/**
 * Menus do site (API administrativa, issue #648).
 *
 * A árvore inteira de um local é substituída de uma vez (`substituir`), que é
 * o que a tela de menus faz ao salvar. Só existe um nível de submenu.
 */
class SiteMenuService
{
    private const MAX_ITENS = 50;

    public function __construct(
        private ?SiteMenuModel $menus = null,
        private ?SiteMenuItemModel $itens = null,
        private ?SitePaginaModel $paginas = null,
        private ?SiteTemplateService $templates = null,
    ) {
        $this->menus     ??= new SiteMenuModel();
        $this->itens     ??= new SiteMenuItemModel();
        $this->paginas   ??= new SitePaginaModel();
        $this->templates ??= new SiteTemplateService();
    }

    /**
     * Todos os locais de menu do template ativo (mesmo os ainda vazios) mais os que já existem.
     *
     * @return array<string, list<array<string, mixed>>> [local => árvore de itens]
     */
    public function listar(): array
    {
        $locais = $this->locaisDoTemplate();
        foreach ($this->menus->findAll() as $menu) {
            $locais[] = $menu['local'];
        }

        $saida = [];
        foreach (array_values(array_unique($locais)) as $local) {
            $saida[$local] = $this->arvore($local);
        }
        return $saida;
    }

    /**
     * Substitui os itens do menu do local.
     *
     * @param list<array<string, mixed>> $itens cada item: rotulo, tipo (pagina|url|ancora), pagina_id|url|ancora, destaque, filhos
     * @return array{itens: list<array<string, mixed>>, avisos: list<string>}
     */
    public function substituir(string $local, array $itens): array
    {
        $locais = $this->locaisDoTemplate();
        if ($locais !== [] && !in_array($local, $locais, true)) {
            throw ErroDeValidacao::campo('local', "O template ativo não tem o menu '{$local}'. Use: " . implode(', ', $locais) . '.');
        }

        $erros = [];
        $avisos = [];
        $total = 0;
        $limpos = $this->valida($itens, 'itens', 0, $erros, $avisos, $total);
        if ($total > self::MAX_ITENS) {
            $erros['itens'] = 'Máximo de ' . self::MAX_ITENS . ' itens por menu.';
        }
        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $menu = $this->menus->where('local', $local)->first();
        $menuId = $menu['id'] ?? $this->menus->insert(['local' => $local]);
        $this->itens->builder()->where('menu_id', $menuId)->delete();
        $this->grava((int) $menuId, $limpos, null);

        $db->transComplete();
        if (!$db->transStatus()) {
            throw new \RuntimeException('Não foi possível salvar o menu.');
        }
        SiteCache::limpar();

        return ['itens' => $this->arvore($local), 'avisos' => $avisos];
    }

    // ------------------------------------------------------------------ interno

    /** @return list<string> */
    private function locaisDoTemplate(): array
    {
        return $this->templates->manifestoAtivo()['menus'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    private function arvore(string $local): array
    {
        $menu = $this->menus->where('local', $local)->first();
        if (!$menu) {
            return [];
        }

        $linhas = $this->itens->where('menu_id', $menu['id'])->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();
        $porPai = [];
        foreach ($linhas as $linha) {
            $linha['destaque'] = (bool) $linha['destaque'];
            $porPai[$linha['parent_id'] ?? 0][] = $linha;
        }

        $monta = function (int $pai) use (&$monta, $porPai): array {
            return array_map(
                fn (array $i) => $i + ['filhos' => $monta((int) $i['id'])],
                $porPai[$pai] ?? []
            );
        };
        return $monta(0);
    }

    /**
     * @param list<mixed> $itens
     * @param array<string, string> $erros
     * @param list<string> $avisos
     * @return list<array<string, mixed>> itens limpos
     */
    private function valida(array $itens, string $caminho, int $nivel, array &$erros, array &$avisos, int &$total): array
    {
        if (!array_is_list($itens)) {
            $erros[$caminho] = 'Deve ser uma lista.';
            return [];
        }

        $limpos = [];
        foreach ($itens as $i => $item) {
            $c = "{$caminho}.{$i}";
            $total++;
            if (!is_array($item)) {
                $erros[$c] = 'Item inválido.';
                continue;
            }

            $rotulo = trim((string) ($item['rotulo'] ?? ''));
            if ($rotulo === '' || mb_strlen($rotulo) > 100) {
                $erros["{$c}.rotulo"] = 'Informe o rótulo (até 100 caracteres).';
            }

            $tipo = $item['tipo'] ?? '';
            $limpo = ['rotulo' => $rotulo, 'tipo' => $tipo, 'pagina_id' => null, 'url' => null, 'ancora' => null, 'destaque' => !empty($item['destaque'])];

            switch ($tipo) {
                case 'pagina':
                    $pagina = is_numeric($item['pagina_id'] ?? null) ? $this->paginas->find((int) $item['pagina_id']) : null;
                    if (!$pagina) {
                        $erros["{$c}.pagina_id"] = 'Página não encontrada.';
                    } else {
                        $limpo['pagina_id'] = (int) $pagina['id'];
                        if ($pagina['status'] !== 'publicado' && $pagina['tipo'] !== 'sistema') {
                            $avisos[] = "O item '{$rotulo}' aponta para uma página em rascunho e não aparecerá no site.";
                        }
                    }
                    break;
                case 'url':
                    $url = trim((string) ($item['url'] ?? ''));
                    $ok = mb_strlen($url) <= 500 && (HtmlSanitizer::hrefSeguro($url) && $url[0] !== '#');
                    if (!$ok) {
                        $erros["{$c}.url"] = 'URL inválida (use http, https, mailto, tel ou um caminho iniciado por /).';
                    }
                    $limpo['url'] = $url;
                    break;
                case 'ancora':
                    $ancora = ltrim(trim((string) ($item['ancora'] ?? '')), '#');
                    if (!preg_match('/^[a-z0-9_-]{1,60}$/', $ancora)) {
                        $erros["{$c}.ancora"] = 'Âncora inválida (letras minúsculas, números, hífen e sublinhado).';
                    }
                    $limpo['ancora'] = $ancora;
                    break;
                default:
                    $erros["{$c}.tipo"] = 'Use: pagina, url ou ancora.';
            }

            $filhos = $item['filhos'] ?? [];
            if (!empty($filhos)) {
                if ($nivel >= 1) {
                    $erros["{$c}.filhos"] = 'O menu aceita só um nível de submenu.';
                } else {
                    $limpo['filhos'] = $this->valida((array) $filhos, "{$c}.filhos", $nivel + 1, $erros, $avisos, $total);
                }
            }
            $limpos[] = $limpo;
        }
        return $limpos;
    }

    /** @param list<array<string, mixed>> $itens */
    private function grava(int $menuId, array $itens, ?int $paiId): void
    {
        foreach ($itens as $ordem => $item) {
            $id = $this->itens->insert([
                'menu_id'   => $menuId,
                'parent_id' => $paiId,
                'rotulo'    => $item['rotulo'],
                'tipo'      => $item['tipo'],
                'pagina_id' => $item['pagina_id'],
                'url'       => $item['url'],
                'ancora'    => $item['ancora'],
                'destaque'  => $item['destaque'] ? 1 : 0,
                'ordem'     => $ordem + 1,
            ]);
            if (!$id) {
                throw new ErroDeValidacao($this->itens->errors());
            }
            if (!empty($item['filhos'])) {
                $this->grava($menuId, $item['filhos'], (int) $id);
            }
        }
    }
}
