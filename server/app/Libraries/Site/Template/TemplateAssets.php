<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use Config\SiteTemplate;

/**
 * Publica a pasta `assets/` de um template em um diretório servido pelo
 * servidor web (`Config\SiteTemplate::$diretorioAssets/<slug>/assets`).
 *
 * Só copia extensões de uma lista branca (nunca `.php`) e ignora links
 * simbólicos. Um arquivo `.versao` registra o que foi publicado: se a versão
 * do template mudar (por exemplo após um deploy), `garantir()` republica.
 */
class TemplateAssets
{
    private const EXTENSOES = ['css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot'];

    private SiteTemplate $config;

    public function __construct(?SiteTemplate $config = null)
    {
        $this->config = $config ?? config(SiteTemplate::class);
    }

    public function destino(string $slug): string
    {
        return rtrim($this->config->diretorioAssets, '/\\') . DIRECTORY_SEPARATOR . $slug;
    }

    /** Publica se ainda não foi publicado nesta versão. @return bool true se publicou agora */
    public function garantir(string $slug, string $versao, string $diretorioPacote): bool
    {
        $marcador = $this->destino($slug) . DIRECTORY_SEPARATOR . '.versao';
        if (is_file($marcador) && trim((string) file_get_contents($marcador)) === $versao) {
            return false;
        }

        $this->publicar($slug, $versao, $diretorioPacote);

        return true;
    }

    public function publicar(string $slug, string $versao, string $diretorioPacote): void
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException("Slug de template inválido: {$slug}");
        }

        $origem  = rtrim($diretorioPacote, '/\\') . DIRECTORY_SEPARATOR . 'assets';
        $destino = $this->destino($slug);
        $final   = $destino . DIRECTORY_SEPARATOR . 'assets';

        $this->remove($final);
        if (!is_dir($final) && !mkdir($final, 0775, true) && !is_dir($final)) {
            throw new \RuntimeException("Não foi possível criar {$final}.");
        }
        if (is_dir($origem)) {
            $this->copia($origem, $final);
        }

        // A imagem de prévia da galeria fica ao lado de assets/, não dentro.
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $preview = rtrim($diretorioPacote, '/\\') . DIRECTORY_SEPARATOR . "preview.{$ext}";
            if (is_file($preview) && !is_link($preview)) {
                copy($preview, $destino . DIRECTORY_SEPARATOR . "preview.{$ext}");
            }
        }

        file_put_contents($destino . DIRECTORY_SEPARATOR . '.versao', $versao);
    }

    /**
     * Garante que a imagem de prévia do pacote esteja publicada (copia se faltar ou estiver diferente).
     * Cobre templates publicados antes de a prévia existir no pacote: `garantir()` não republica
     * quando a versão não mudou. @return string|null nome do arquivo publicado (ex.: `preview.png`)
     */
    public function garantirPreview(string $slug, string $diretorioPacote): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $origem = rtrim($diretorioPacote, '/\\') . DIRECTORY_SEPARATOR . "preview.{$ext}";
            if (!is_file($origem) || is_link($origem)) {
                continue;
            }
            $destino = $this->destino($slug);
            $alvo    = $destino . DIRECTORY_SEPARATOR . "preview.{$ext}";
            if (!is_file($alvo) || filesize($alvo) !== filesize($origem) || filemtime($alvo) < filemtime($origem)) {
                if (!is_dir($destino) && !@mkdir($destino, 0775, true) && !is_dir($destino)) {
                    return null;
                }
                if (!@copy($origem, $alvo)) {
                    return null;
                }
            }
            return "preview.{$ext}";
        }
        return null;
    }

    private function copia(string $origem, string $destino): void
    {
        foreach (new \DirectoryIterator($origem) as $item) {
            if ($item->isDot() || $item->isLink()) {
                continue;
            }
            $alvo = $destino . DIRECTORY_SEPARATOR . $item->getFilename();

            if ($item->isDir()) {
                mkdir($alvo, 0775, true);
                $this->copia($item->getPathname(), $alvo);
            } elseif (in_array(strtolower($item->getExtension()), self::EXTENSOES, true)) {
                copy($item->getPathname(), $alvo);
            }
        }
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isDot()) {
                continue;
            }
            ($item->isDir() && !$item->isLink()) ? $this->remove($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
