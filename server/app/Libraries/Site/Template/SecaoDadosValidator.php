<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use Config\SiteTemplate;

/**
 * Valida o JSON `dados` de uma seção contra os `campos` do tipo declarado no
 * manifesto do template. Os erros usam o caminho do campo (`botoes.1.rotulo`),
 * que a API devolve em 422 e a SPA liga ao input correspondente.
 *
 * Este validador confere tipo, tamanho e formato. A sanitização de HTML de
 * `rich_text` e a existência da mídia/página referenciada são feitas por quem
 * grava (#648) e por quem renderiza (#645).
 */
class SecaoDadosValidator
{
    private const TAGS_INLINE = ['<em>', '</em>', '<strong>', '</strong>'];
    private const MAX_TEXTO    = 1000;
    private const MAX_TEXTAREA = 5000;
    private const MAX_RICH     = 100000;

    private SiteTemplate $config;

    public function __construct(?SiteTemplate $config = null)
    {
        $this->config = $config ?? config(SiteTemplate::class);
    }

    /**
     * @param array<string, array> $campos Definição `campos` do tipo de seção
     * @param array<string, mixed> $dados
     */
    public function validar(array $campos, array $dados): Resultado
    {
        $r = new Resultado();
        $this->objeto($campos, $dados, '', $r);

        foreach (array_diff_key($dados, $campos) as $chave => $_) {
            $r->aviso((string) $chave, 'Campo não existe neste tipo de seção; será ignorado.');
        }
        return $r;
    }

    /**
     * Completa `dados` com o `padrao` dos campos ausentes (usado ao ativar um
     * template cujo tipo de seção ganhou campos novos). Nunca remove nem
     * altera valores existentes.
     */
    public function aplicarPadroes(array $campos, array $dados): array
    {
        foreach ($campos as $nome => $def) {
            if (!array_key_exists($nome, $dados) && array_key_exists('padrao', $def)) {
                $dados[$nome] = $def['padrao'];
            }
            if (($def['tipo'] ?? null) === 'lista' && isset($dados[$nome]) && is_array($dados[$nome])) {
                $dados[$nome] = array_map(
                    fn ($item) => is_array($item) ? $this->aplicarPadroes($def['itens'] ?? [], $item) : $item,
                    $dados[$nome]
                );
            }
        }
        return $dados;
    }

    private function objeto(array $campos, array $dados, string $prefixo, Resultado $r): void
    {
        foreach ($campos as $nome => $def) {
            $caminho = $prefixo . $nome;
            $tipo    = $def['tipo'] ?? '';

            if ($tipo === 'dinamico') {
                continue; // vem do provedor de dados, não do editor
            }

            $presente = array_key_exists($nome, $dados) && $dados[$nome] !== null && $dados[$nome] !== '' && $dados[$nome] !== [];
            if ($presente && $tipo === 'link' && self::linkVazio($dados[$nome])) {
                $presente = false; // o editor manda {tipo:'url', url:''} quando o campo fica em branco
            }
            if (!$presente) {
                if (!empty($def['obrigatorio'])) {
                    $r->erro($caminho, 'Campo obrigatório.');
                }
                continue;
            }

            $this->campo($tipo, $def, $dados[$nome], $caminho, $r);
        }
    }

    private function campo(string $tipo, array $def, mixed $v, string $caminho, Resultado $r): void
    {
        switch ($tipo) {
            case 'texto':
                $this->texto($v, $def['max'] ?? self::MAX_TEXTO, $caminho, $r, linhaUnica: true);
                break;
            case 'texto_inline':
                if ($this->texto($v, $def['max'] ?? self::MAX_TEXTO, $caminho, $r, linhaUnica: true)) {
                    $this->soTagsInline($v, $caminho, $r);
                }
                break;
            case 'textarea':
                $this->texto($v, $def['max'] ?? self::MAX_TEXTAREA, $caminho, $r);
                break;
            case 'rich_text':
                $this->texto($v, $def['max'] ?? self::MAX_RICH, $caminho, $r);
                break;
            case 'imagem':
                if (!is_int($v) || $v < 1) {
                    $r->erro($caminho, 'Informe o id de uma mídia da biblioteca.');
                }
                break;
            case 'icone':
                if (!is_string($v) || !preg_match('/^bi-[a-z0-9-]{1,60}$/', $v)) {
                    $r->erro($caminho, "Ícone inválido (use o nome do Bootstrap Icons, ex.: 'bi-award').");
                }
                break;
            case 'link':
                $this->link($v, $caminho, $r);
                break;
            case 'opcao':
                if (!in_array($v, $def['opcoes'] ?? [], true)) {
                    $r->erro($caminho, 'Valor não está entre as opções: ' . implode(', ', $def['opcoes'] ?? []) . '.');
                }
                break;
            case 'bool':
                if (!is_bool($v)) {
                    $r->erro($caminho, 'Deve ser verdadeiro ou falso.');
                }
                break;
            case 'referencia_menu':
                if (!is_string($v) || !preg_match('/^[a-z][a-z0-9_]*$/', $v)) {
                    $r->erro($caminho, 'Local de menu inválido.');
                }
                break;
            case 'lista':
                $this->lista($def, $v, $caminho, $r);
                break;
        }
    }

    private function texto(mixed $v, int $max, string $caminho, Resultado $r, bool $linhaUnica = false): bool
    {
        if (!is_string($v)) {
            $r->erro($caminho, 'Deve ser texto.');
            return false;
        }
        if (mb_strlen($v) > $max) {
            $r->erro($caminho, "Máximo de {$max} caracteres.");
            return false;
        }
        if ($linhaUnica && preg_match('/[\r\n]/', $v)) {
            $r->erro($caminho, 'Não pode ter quebra de linha.');
            return false;
        }
        return true;
    }

    private function soTagsInline(string $v, string $caminho, Resultado $r): void
    {
        preg_match_all('/<[^>]*>|[<>]/', $v, $tags);
        foreach ($tags[0] as $tag) {
            if (!in_array(strtolower($tag), self::TAGS_INLINE, true)) {
                $r->erro($caminho, 'Só são permitidos <em> e <strong>.');
                return;
            }
        }
    }

    /** Link sem destino (url, âncora ou página em branco): conta como campo não preenchido. */
    public static function linkVazio(mixed $v): bool
    {
        if (!is_array($v)) {
            return false;
        }
        $destino = match ($v['tipo'] ?? null) {
            'pagina' => $v['pagina_id'] ?? null,
            'ancora' => $v['ancora'] ?? null,
            'url'    => $v['url'] ?? null,
            default  => null,
        };
        if (is_string($destino)) {
            $destino = trim($destino);
        }

        return $destino === null || $destino === '' || $destino === 0;
    }

    private function link(mixed $v, string $caminho, Resultado $r): void
    {
        $tipo = is_array($v) ? ($v['tipo'] ?? null) : null;
        $ok = match ($tipo) {
            'pagina' => is_int($v['pagina_id'] ?? null) && $v['pagina_id'] > 0,
            'ancora' => is_string($v['ancora'] ?? null) && preg_match('/^[a-z0-9_-]{1,60}$/', $v['ancora']) === 1,
            'url'    => is_string($v['url'] ?? null) && $this->urlSegura($v['url']),
            default  => false,
        };
        if (!$ok) {
            $r->erro($caminho, "Link inválido. Use {tipo:'pagina', pagina_id}, {tipo:'ancora', ancora} ou {tipo:'url', url} (http, https, mailto, tel ou caminho iniciado por /).");
        }
    }

    private function urlSegura(string $url): bool
    {
        if (mb_strlen($url) > 500 || preg_match('/[\x00-\x20]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        return preg_match('#^(https?://[^/]+|mailto:[^@]+@[^@]+|tel:\+?[0-9()-]+)#i', $url) === 1;
    }

    private function lista(array $def, mixed $v, string $caminho, Resultado $r): void
    {
        if (!is_array($v) || !array_is_list($v)) {
            $r->erro($caminho, 'Deve ser uma lista.');
            return;
        }
        if (count($v) > $this->config->maxItensLista) {
            $r->erro($caminho, "Máximo de {$this->config->maxItensLista} itens.");
            return;
        }
        foreach ($v as $i => $item) {
            if (!is_array($item)) {
                $r->erro("{$caminho}.{$i}", 'Item inválido.');
                continue;
            }
            $this->objeto($def['itens'] ?? [], $item, "{$caminho}.{$i}.", $r);
        }
    }
}
