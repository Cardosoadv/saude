<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use Config\SiteTemplate;

/**
 * Valida o `template.json` de um pacote (formato descrito em docs/site-templates.md).
 *
 * Esta classe é a fonte de verdade; docs/site-template.schema.json é só a
 * descrição para editores/IDEs.
 */
class ManifestValidator
{
    private const RE_SLUG   = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const RE_CHAVE  = '/^[a-z][a-z0-9_]*$/';
    private const RE_SEMVER = '/^\d+\.\d+\.\d+$/';
    private const RE_COR    = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private SiteTemplate $config;

    public function __construct(?SiteTemplate $config = null)
    {
        $this->config = $config ?? config(SiteTemplate::class);
    }

    public function validar(array $m): Resultado
    {
        $r = new Resultado();

        $this->identificacao($m, $r);
        $this->compatibilidade($m, $r);

        if (!isset($m['motor']) || !in_array($m['motor'], $this->config->motores, true)) {
            $r->erro('motor', 'Motor deve ser um destes: ' . implode(', ', $this->config->motores) . '.');
        }

        $this->listaDeChaves($m, 'layouts', $r, obrigatorio: true);
        $this->listaDeChaves($m, 'menus', $r);
        $this->tema($m['tema'] ?? [], $r);
        $this->textos($m['textos'] ?? [], $r);
        $this->secoes($m['secoes'] ?? null, $r);

        $obrigatorias = $m['paginas_obrigatorias'] ?? [];
        if (!is_array($obrigatorias) || !in_array('home', $obrigatorias, true)) {
            $r->erro('paginas_obrigatorias', "Deve incluir 'home'.");
        }

        return $r;
    }

    private function identificacao(array $m, Resultado $r): void
    {
        if (!is_string($m['slug'] ?? null) || !preg_match(self::RE_SLUG, $m['slug']) || strlen($m['slug']) > 60) {
            $r->erro('slug', 'Slug deve ter letras minúsculas, números e hífen (até 60 caracteres).');
        }
        foreach (['nome' => 100, 'autor' => 100, 'descricao' => 255] as $campo => $max) {
            $valor = $m[$campo] ?? null;
            if ($campo === 'nome' && (!is_string($valor) || trim($valor) === '')) {
                $r->erro('nome', 'Nome é obrigatório.');
            } elseif ($valor !== null && (!is_string($valor) || mb_strlen($valor) > $max)) {
                $r->erro($campo, "Deve ser texto de até {$max} caracteres.");
            }
        }
        if (!is_string($m['versao'] ?? null) || !preg_match(self::RE_SEMVER, $m['versao'])) {
            $r->erro('versao', 'Versão deve estar no formato X.Y.Z.');
        }
    }

    private function compatibilidade(array $m, Resultado $r): void
    {
        $exigida = $m['sistema'] ?? null;
        if ($exigida === null) {
            $r->aviso('sistema', 'Sem versão mínima declarada; a compatibilidade não pode ser verificada.');
            return;
        }
        if (!is_string($exigida) || !preg_match('/^>=\s*(\d+\.\d+\.\d+)$/', $exigida, $mt)) {
            $r->erro('sistema', "Use o formato '>=X.Y.Z'.");
            return;
        }
        if (version_compare($this->config->versaoSistema, $mt[1], '<')) {
            $r->erro('sistema', "Exige Saúde {$mt[1]} ou superior (instalado: {$this->config->versaoSistema}).");
        }
    }

    private function listaDeChaves(array $m, string $campo, Resultado $r, bool $obrigatorio = false): void
    {
        $lista = $m[$campo] ?? [];
        if (!is_array($lista) || !array_is_list($lista) || ($obrigatorio && $lista === [])) {
            $r->erro($campo, 'Deve ser uma lista' . ($obrigatorio ? ' com ao menos um item.' : '.'));
            return;
        }
        foreach ($lista as $i => $item) {
            if (!is_string($item) || !preg_match(self::RE_CHAVE, $item)) {
                $r->erro("{$campo}.{$i}", 'Use letras minúsculas, números e sublinhado, começando por letra.');
            }
        }
    }

    private function tema(mixed $tema, Resultado $r): void
    {
        if (!is_array($tema)) {
            $r->erro('tema', 'Deve ser um objeto.');
            return;
        }
        foreach ($tema['cores'] ?? [] as $nome => $cor) {
            if (!preg_match('/^[a-z][a-z0-9-]*$/', (string) $nome)) {
                $r->erro("tema.cores.{$nome}", 'Nome de token inválido.');
            } elseif (!is_string($cor) || !preg_match(self::RE_COR, $cor)) {
                $r->erro("tema.cores.{$nome}", 'Cor deve ser hexadecimal (#RGB ou #RRGGBB).');
            }
        }
        foreach ($tema['fontes'] ?? [] as $nome => $familia) {
            if (!is_string($familia) || !preg_match('/^[\p{L}0-9 ]{1,60}$/u', $familia)) {
                $r->erro("tema.fontes.{$nome}", 'Nome de família inválido (apenas letras, números e espaço).');
            }
        }
    }

    private function textos(mixed $textos, Resultado $r): void
    {
        if (!is_array($textos)) {
            $r->erro('textos', 'Deve ser um objeto chave → texto padrão.');
            return;
        }
        foreach ($textos as $chave => $padrao) {
            if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/', (string) $chave)) {
                $r->erro("textos.{$chave}", 'Chave inválida (use partes separadas por ponto).');
            } elseif (!is_string($padrao)) {
                $r->erro("textos.{$chave}", 'O texto padrão deve ser texto.');
            }
        }
    }

    private function secoes(mixed $secoes, Resultado $r): void
    {
        if (!is_array($secoes) || $secoes === []) {
            $r->erro('secoes', 'Declare ao menos um tipo de seção.');
            return;
        }
        foreach ($secoes as $tipo => $def) {
            $base = "secoes.{$tipo}";
            if (!preg_match(self::RE_CHAVE, (string) $tipo) || strlen((string) $tipo) > 60) {
                $r->erro($base, 'Nome de tipo inválido (minúsculas, números e sublinhado).');
                continue;
            }
            if (!is_array($def) || !is_string($def['rotulo'] ?? null) || trim($def['rotulo']) === '') {
                $r->erro("{$base}.rotulo", 'Rótulo é obrigatório.');
            }
            $this->campos($def['campos'] ?? null, "{$base}.campos", $r);
        }
    }

    private function campos(mixed $campos, string $base, Resultado $r, int $nivel = 0): void
    {
        if (!is_array($campos)) {
            $r->erro($base, 'Deve ser um objeto de campos.');
            return;
        }
        foreach ($campos as $nome => $def) {
            $caminho = "{$base}.{$nome}";
            if (!preg_match(self::RE_CHAVE, (string) $nome)) {
                $r->erro($caminho, 'Nome de campo inválido.');
                continue;
            }
            $tipo = is_array($def) ? ($def['tipo'] ?? null) : null;
            if (!in_array($tipo, $this->config->tiposCampo, true)) {
                $r->erro("{$caminho}.tipo", 'Tipo de campo desconhecido.');
                continue;
            }
            $this->detalhesDoCampo($tipo, $def, $caminho, $r, $nivel);
        }
    }

    private function detalhesDoCampo(string $tipo, array $def, string $caminho, Resultado $r, int $nivel): void
    {
        if (isset($def['max']) && (!is_int($def['max']) || $def['max'] < 1)) {
            $r->erro("{$caminho}.max", 'Deve ser inteiro positivo.');
        }

        switch ($tipo) {
            case 'opcao':
                $opcoes = $def['opcoes'] ?? null;
                if (!is_array($opcoes) || $opcoes === [] || array_filter($opcoes, fn ($o) => !is_string($o)) !== []) {
                    $r->erro("{$caminho}.opcoes", 'Informe a lista de opções (textos).');
                } elseif (isset($def['padrao']) && !in_array($def['padrao'], $opcoes, true)) {
                    $r->erro("{$caminho}.padrao", 'O padrão deve ser uma das opções.');
                }
                break;
            case 'lista':
                if ($nivel >= 1) {
                    $r->erro($caminho, 'Listas não podem ser aninhadas em listas.');
                    break;
                }
                $this->campos($def['itens'] ?? null, "{$caminho}.itens", $r, $nivel + 1);
                break;
            case 'dinamico':
                if (!in_array($def['fonte'] ?? null, $this->config->fontesDinamicas, true)) {
                    $r->erro("{$caminho}.fonte", 'Fonte dinâmica desconhecida (' . implode(', ', $this->config->fontesDinamicas) . ').');
                }
                break;
        }
    }
}
