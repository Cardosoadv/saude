<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFunction;

/**
 * Política do sandbox Twig para templates enviados pelo painel (sem PHP).
 *
 * Lista branca: o que não está aqui é bloqueado pelo Twig com
 * `SecurityError`. Nenhum método de objeto PHP é liberado: o renderizador
 * (#645) entrega só arrays e escalares.
 *
 * As funções `site_config`, `site_menu`, `site_texto` e `asset` são
 * registradas pelo renderizador; aqui só declaramos que podem ser chamadas.
 */
final class TwigSandboxPolicy
{
    public const TAGS = ['if', 'for', 'set', 'include', 'block', 'extends', 'embed', 'apply', 'with'];

    public const FILTROS = [
        'escape', 'e', 'raw', 'default', 'length', 'first', 'last', 'join', 'split',
        'upper', 'lower', 'capitalize', 'title', 'trim', 'nl2br', 'striptags',
        'slice', 'merge', 'keys', 'date', 'number_format', 'url_encode', 'json_encode',
    ];

    public const FUNCOES = ['range', 'cycle', 'max', 'min', 'site_config', 'site_menu', 'site_texto', 'site_midia', 'site_jsonld', 'site_tema_css', 'site_fontes_url', 'asset'];

    /**
     * Funções do site com corpo vazio, para validar/pré-renderizar um template na instalação
     * sem consultar o banco. Só os nomes importam (o Twig recusa função desconhecida).
     *
     * @return array<string, callable>
     */
    public static function funcoesParaValidacao(): array
    {
        $nomes = array_diff(self::FUNCOES, ['range', 'cycle', 'max', 'min']);

        return array_combine($nomes, array_map(
            static fn (string $nome) => static fn () => in_array($nome, ['site_menu'], true) ? [] : '',
            $nomes
        ));
    }

    public static function politica(): SecurityPolicy
    {
        // 4º argumento (métodos) e 5º (propriedades): vazios de propósito.
        return new SecurityPolicy(self::TAGS, self::FILTROS, [], [], self::FUNCOES);
    }

    /**
     * Ambiente Twig restrito, lendo apenas de $diretorio. O `autoescape` é
     * `html`; `raw` fica disponível porque o conteúdo `rich_text` já chega
     * sanitizado (#645).
     *
     * @param array<string, callable> $funcoes Funções do site (`site_config`…)
     */
    public static function ambiente(string $diretorio, array $funcoes = []): Environment
    {
        $twig = new Environment(new FilesystemLoader($diretorio), [
            'autoescape'       => 'html',
            'strict_variables' => false,
            'cache'            => false,
        ]);
        foreach ($funcoes as $nome => $callable) {
            $twig->addFunction(new TwigFunction($nome, $callable));
        }
        $twig->addExtension(new SandboxExtension(self::politica(), true));

        return $twig;
    }
}
