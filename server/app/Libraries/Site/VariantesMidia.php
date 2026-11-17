<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Variantes responsivas (WebP e, se a biblioteca GD suportar, AVIF) das imagens da
 * biblioteca de mídias (issue #694).
 *
 * Ficam ao lado do original, sem coluna nova no banco: `<nome>-w640.webp`,
 * `<nome>-w640.avif`. Só se gera largura menor que a do original, e o próprio
 * original também ganha uma variante (no máximo 1920 px) para trocar PNG/JPG pesado por WebP/AVIF.
 */
final class VariantesMidia
{
    public const LARGURAS = [320, 640, 960, 1280, 1920];

    private const FORMATOS = ['avif' => 'imageavif', 'webp' => 'imagewebp'];
    private const ORIGENS  = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];

    /** Larguras (px) das variantes de uma imagem de `$largura` px de largura. */
    public static function larguras(int $largura): array
    {
        $lista = array_filter(self::LARGURAS, static fn (int $w): bool => $w < $largura);
        $lista[] = min($largura, max(self::LARGURAS));

        return array_values(array_unique($lista));
    }

    /** Caminho (ou URL) relativo da variante: `a/b/hash.png` → `a/b/hash-w640.webp`. */
    public static function nome(string $relativo, int $largura, string $formato): string
    {
        return (string) preg_replace('/\.[a-z0-9]+$/i', "-w{$largura}.{$formato}", $relativo);
    }

    /**
     * Gera as variantes; devolve os arquivos criados. Sem GD, ou com formato de origem
     * que não pode ser redimensionado (GIF animado, ICO), não gera nada.
     *
     * @return list<string> caminhos absolutos
     */
    public static function gerar(string $arquivo, string $relativo, string $mime, int $largura, int $altura): array
    {
        $abrir = self::ORIGENS[$mime] ?? null;
        if ($abrir === null || ! extension_loaded('gd') || ! function_exists($abrir) || $largura < 1 || $altura < 1) {
            return [];
        }

        try {
            $origem = @$abrir($arquivo);
            if (! $origem) {
                return [];
            }
            imagepalettetotruecolor($origem);
            imagealphablending($origem, false);
            imagesavealpha($origem, true);

            $criados = [];
            foreach (self::larguras($largura) as $w) {
                $h = max(1, (int) round($altura * $w / $largura));
                $copia = $w === $largura ? $origem : imagescale($origem, $w, $h, IMG_BICUBIC);
                if ($copia === false) {
                    continue;
                }
                foreach (self::FORMATOS as $formato => $funcao) {
                    if (! function_exists($funcao)) {
                        continue;
                    }
                    $destino = dirname($arquivo) . '/' . basename(self::nome($relativo, $w, $formato));
                    if (@$funcao($copia, $destino, $formato === 'webp' ? 82 : 55)) {
                        @chmod($destino, 0644);
                        $criados[] = $destino;
                    }
                }
                if ($copia !== $origem) {
                    imagedestroy($copia);
                }
            }
            imagedestroy($origem);

            return $criados;
        } catch (\Throwable $e) {
            log_message('warning', 'Site: não foi possível gerar variantes da mídia. ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Variantes que existem em disco, por formato.
     *
     * @return array<string, array<int, string>> `['webp' => [640 => 'a/hash-w640.webp', …], 'avif' => […]]`
     */
    public static function existentes(string $diretorioUploads, string $relativo, int $largura): array
    {
        $saida = [];
        if ($largura < 1) {
            return $saida;
        }
        foreach (array_keys(self::FORMATOS) as $formato) {
            foreach (self::larguras($largura) as $w) {
                $rel = self::nome($relativo, $w, $formato);
                if (is_file(rtrim($diretorioUploads, '/\\') . '/' . ltrim($rel, '/'))) {
                    $saida[$formato][$w] = $rel;
                }
            }
        }

        return $saida;
    }

    /** Apaga todas as variantes de uma mídia. */
    public static function apagar(string $diretorioUploads, string $relativo): void
    {
        $base = rtrim($diretorioUploads, '/\\') . '/' . ltrim((string) preg_replace('/\.[a-z0-9]+$/i', '', $relativo), '/');
        foreach (glob($base . '-w*.{webp,avif}', GLOB_BRACE) ?: [] as $arquivo) {
            @unlink($arquivo);
        }
    }
}
