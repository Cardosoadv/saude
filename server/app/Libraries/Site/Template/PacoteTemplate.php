<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use Twig\Error\Error as TwigError;
use Twig\Sandbox\SecurityError;
use Twig\Source;

/**
 * Valida um pacote de template já extraído em uma pasta (issue #650).
 *
 * Verifica a estrutura e, sobretudo, o que pode entrar: só extensões de uma lista branca por
 * pasta; nada de `.php` (e variações) a menos que o pacote venha pela linha de comando;
 * nenhum arquivo de texto com `<?php`; nomes de arquivo restritos; sem links simbólicos.
 * Para templates Twig, cada `.twig` é renderizado uma vez no sandbox (com funções vazias)
 * para pegar erro de sintaxe e uso de tag, filtro ou função fora da lista branca.
 */
class PacoteTemplate
{
    /** Pastas do pacote que guardam views. */
    private const PASTAS_DE_VIEW = ['layouts', 'sections', 'pages', 'shell', 'partials'];

    private const EXT_ASSETS = ['css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot'];
    private const EXT_IMAGENS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const EXT_TEXTO = ['twig', 'js', 'css', 'json', 'map', 'md', 'txt', 'php'];

    /** Qualquer destes "pedaços" no nome do arquivo é recusado (execução por extensão dupla, ex.: `x.php.png`). */
    private const NOME_PERIGOSO = '/\.(php\d?|phtml|phar|pht|phps|pl|cgi|sh|py|rb|asp|aspx|jsp|exe|bat|cmd|ini|htaccess|htpasswd|svg|html?|xml)(\.|$)/i';

    public function __construct(private ?ManifestValidator $manifestos = null, private ?SecaoDadosValidator $secoes = null)
    {
        $this->manifestos ??= new ManifestValidator();
        $this->secoes     ??= new SecaoDadosValidator();
    }

    /**
     * @param bool $permitePhp true só para pacotes instalados por linha de comando ou deploy
     * @return array{resultado: Resultado, manifesto: ?array<string, mixed>}
     */
    public function validar(string $pasta, bool $permitePhp = false): array
    {
        $r = new Resultado();
        $pasta = rtrim($pasta, '/\\');

        $arquivos = $this->arquivos($pasta, $permitePhp, $r);
        if (!isset($arquivos['template.json'])) {
            $r->erro('template.json', 'O pacote precisa ter um template.json na raiz.');
            return ['resultado' => $r, 'manifesto' => null];
        }

        $manifesto = json_decode((string) file_get_contents($pasta . '/template.json'), true);
        if (!is_array($manifesto)) {
            $r->erro('template.json', 'JSON inválido.');
            return ['resultado' => $r, 'manifesto' => null];
        }

        $res = $this->manifestos->validar($manifesto);
        foreach ($res->erros() as $caminho => $mensagem) {
            $r->erro("template.json:{$caminho}", $mensagem);
        }
        foreach ($res->avisos() as $caminho => $mensagem) {
            $r->aviso("template.json:{$caminho}", $mensagem);
        }
        if (!$permitePhp && ($manifesto['motor'] ?? '') !== 'twig') {
            $r->erro('template.json:motor', 'Templates enviados pelo painel precisam usar o motor "twig" (sem PHP).');
        }

        $ext = ($manifesto['motor'] ?? 'twig') === 'php' ? 'php' : 'twig';
        $this->estrutura($manifesto, $arquivos, $ext, $r);
        $this->seed($pasta, $arquivos, $manifesto, $r);

        if ($ext === 'twig' && $r->valido()) {
            $this->preRenderiza($pasta, $arquivos, $r);
        }

        return ['resultado' => $r, 'manifesto' => $manifesto];
    }

    /**
     * Percorre a pasta aplicando as regras de nome, extensão e conteúdo.
     *
     * @return array<string, true> caminhos relativos (com `/`) aceitos
     */
    private function arquivos(string $pasta, bool $permitePhp, Resultado $r): array
    {
        $aceitos = [];
        if (!is_dir($pasta)) {
            $r->erro('pacote', 'Pasta do pacote não encontrada.');
            return [];
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($pasta) + 1));

            if ($item->isLink()) {
                $r->erro($rel, 'Links simbólicos não são permitidos.');
                continue;
            }
            foreach (explode('/', $rel) as $segmento) {
                if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $segmento)) {
                    $r->erro($rel, 'Nome inválido (use letras, números, ponto, hífen e sublinhado; sem começar por ponto).');
                    continue 2;
                }
            }
            if ($item->isDir()) {
                if (!str_contains($rel, '/') && !in_array($rel, [...self::PASTAS_DE_VIEW, 'assets', 'seed'], true)) {
                    $r->erro($rel, 'Pasta não reconhecida no pacote.');
                }
                continue;
            }

            $erro = $this->regraDoArquivo($rel, $permitePhp);
            if ($erro !== null) {
                $r->erro($rel, $erro);
                continue;
            }
            if (!$permitePhp && $this->temCodigoPhp($item->getPathname(), $rel)) {
                $r->erro($rel, 'Código PHP não é permitido em templates enviados pelo painel.');
                continue;
            }
            $aceitos[$rel] = true;
        }

        return $aceitos;
    }

    private function regraDoArquivo(string $rel, bool $permitePhp): ?string
    {
        $partes = explode('/', $rel);
        $nome = end($partes);
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $raiz = $partes[0];

        $ehPhpDeView = $permitePhp && $ext === 'php' && in_array($raiz, self::PASTAS_DE_VIEW, true) && substr_count($nome, '.') === 1;
        if (!$ehPhpDeView && preg_match(self::NOME_PERIGOSO, $nome)) {
            return 'Tipo de arquivo não permitido.';
        }

        if (count($partes) === 1) {
            return match (true) {
                $nome === 'template.json' => null,
                (bool) preg_match('/^preview\.(png|jpe?g|webp)$/i', $nome) => null,
                (bool) preg_match('/^(README|LICENSE)(\.(md|txt))?$/', $nome) => null,
                default => 'Arquivo não reconhecido na raiz do pacote.',
            };
        }
        if (in_array($raiz, self::PASTAS_DE_VIEW, true)) {
            return ($ext === 'twig' || $ehPhpDeView) ? null : 'Nesta pasta só são permitidos arquivos .twig' . ($permitePhp ? ' ou .php' : '') . '.';
        }
        if ($raiz === 'assets') {
            return in_array($ext, self::EXT_ASSETS, true) ? null : 'Tipo de arquivo não permitido em assets (SVG não é aceito).';
        }
        if ($raiz === 'seed') {
            if ($rel === 'seed/content.json') {
                return null;
            }
            return ($partes[1] ?? '') === 'media' && count($partes) >= 3 && in_array($ext, self::EXT_IMAGENS, true)
                ? null : 'Em seed/ só são permitidos content.json e imagens em seed/media/.';
        }

        return 'Pasta não reconhecida no pacote.';
    }

    private function temCodigoPhp(string $caminho, string $rel): bool
    {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT_TEXTO, true) || filesize($caminho) > 2 * 1048576) {
            return false;
        }

        return (bool) preg_match('/<\?(php|=)|<\?\s|<script[^>]+language\s*=\s*["\']?php/i', (string) file_get_contents($caminho));
    }

    /** @param array<string, true> $arquivos */
    private function estrutura(array $manifesto, array $arquivos, string $ext, Resultado $r): void
    {
        foreach ((array) ($manifesto['layouts'] ?? []) as $layout) {
            if (!isset($arquivos["layouts/{$layout}.{$ext}"])) {
                $r->erro("layouts/{$layout}.{$ext}", "O layout '{$layout}' é declarado no manifesto, mas o arquivo não existe.");
            }
        }
        foreach (array_keys((array) ($manifesto['secoes'] ?? [])) as $tipo) {
            if (!isset($arquivos["sections/{$tipo}.{$ext}"])) {
                $r->aviso("sections/{$tipo}.{$ext}", "Sem partial para a seção '{$tipo}'; ela não será exibida.");
            }
        }
        foreach (['noticias_lista', 'noticias_artigo', 'area_cliente', 'painel_cliente'] as $tela) {
            if (!isset($arquivos["pages/{$tela}.{$ext}"])) {
                $r->aviso("pages/{$tela}.{$ext}", "Sem a tela '{$tela}'; será usada a do template Clássico.");
            }
        }
        if (!isset($arquivos['preview.png']) && !isset($arquivos['preview.jpg']) && !isset($arquivos['preview.jpeg']) && !isset($arquivos['preview.webp'])) {
            $r->aviso('preview', 'Sem imagem de prévia (preview.png).');
        }
    }

    /** @param array<string, true> $arquivos */
    private function seed(string $pasta, array $arquivos, array $manifesto, Resultado $r): void
    {
        if (!isset($arquivos['seed/content.json'])) {
            $r->aviso('seed/content.json', 'Sem conteúdo inicial; um site novo começará vazio.');
            return;
        }

        $seed = json_decode((string) file_get_contents($pasta . '/seed/content.json'), true);
        if (!is_array($seed)) {
            $r->erro('seed/content.json', 'JSON inválido.');
            return;
        }

        $tipos = $manifesto['secoes'] ?? [];
        $slugs = array_column((array) ($seed['paginas'] ?? []), 'slug');
        foreach ((array) ($seed['paginas'] ?? []) as $i => $pagina) {
            foreach (array_values((array) ($pagina['secoes'] ?? [])) as $j => $secao) {
                $local = "seed/content.json:paginas.{$pagina['slug']}.secoes.{$j}";
                $campos = $tipos[$secao['tipo'] ?? '']['campos'] ?? null;
                if ($campos === null) {
                    $r->erro($local, "Tipo de seção '" . ($secao['tipo'] ?? '') . "' não existe no manifesto.");
                    continue;
                }
                $res = $this->secoes->validar($campos, $this->semLinksPorSlug($campos, (array) ($secao['dados'] ?? []), $slugs));
                foreach ($res->erros() as $caminho => $mensagem) {
                    $r->erro("{$local}.{$caminho}", $mensagem);
                }
            }
        }
        if (!in_array('home', array_column((array) ($seed['paginas'] ?? []), 'tipo'), true)) {
            $r->aviso('seed/content.json', 'O conteúdo inicial não tem uma página do tipo "home".');
        }
    }

    /** Troca `{tipo:pagina, slug}` por um id fictício para o validador de formato aceitar o link. */
    private function semLinksPorSlug(array $campos, array $dados, array $slugs): array
    {
        foreach ($campos as $nome => $def) {
            if (!isset($dados[$nome])) {
                continue;
            }
            if (($def['tipo'] ?? '') === 'link' && is_array($dados[$nome]) && isset($dados[$nome]['slug'])) {
                $dados[$nome] = in_array($dados[$nome]['slug'], $slugs, true) ? ['tipo' => 'pagina', 'pagina_id' => 1] : $dados[$nome];
            } elseif (($def['tipo'] ?? '') === 'lista' && is_array($dados[$nome])) {
                $dados[$nome] = array_map(fn ($item) => is_array($item) ? $this->semLinksPorSlug($def['itens'] ?? [], $item, $slugs) : $item, $dados[$nome]);
            }
        }
        return $dados;
    }

    /** @param array<string, true> $arquivos */
    private function preRenderiza(string $pasta, array $arquivos, Resultado $r): void
    {
        $twig = TwigSandboxPolicy::ambiente($pasta, TwigSandboxPolicy::funcoesParaValidacao());
        $vars = ['dados' => [], 'secao' => ['id' => 0, 'tipo' => '', 'ancora' => null], 'pagina' => ['id' => 0, 'slug' => '', 'titulo' => '', 'tipo' => 'pagina'],
                 'seo' => ['titulo' => '', 'descricao' => '', 'url' => '', 'palavras' => '', 'imagem' => null], 'conteudo' => '', 'partes' => [],
                 'rascunho' => false, 'noindex' => false, 'articles' => [], 'article' => [], 'related' => [], 'searchQuery' => '',
                 'currentPage' => 1, 'lastPage' => 1, 'total' => 0];

        foreach (array_keys($arquivos) as $rel) {
            if (!str_ends_with($rel, '.twig')) {
                continue;
            }
            try {
                $twig->render($rel, $vars);
            } catch (SecurityError $e) {
                $r->erro($rel, 'Recurso do Twig não permitido: ' . $e->getRawMessage());
            } catch (TwigError $e) {
                // Erro de sintaxe ou include inexistente invalida o pacote; erro de execução com dados vazios, não.
                if ($e instanceof \Twig\Error\RuntimeError) {
                    $r->aviso($rel, 'Aviso ao pré-renderizar: ' . $e->getRawMessage());
                } else {
                    $r->erro($rel, $e->getRawMessage());
                }
            }
        }
    }
}
