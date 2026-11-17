<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Decide para onde uma requisição do site público deve ser redirecionada (301)
 * para chegar ao endereço canônico: o host e o protocolo de `App::$publicURL`,
 * sem a subpasta da instalação (`/clinica/`) quando a baseURL a tem.
 *
 * É a rede de segurança em PHP das regras do .htaccess: vale também quando o
 * servidor ignora o .htaccess ou termina o TLS antes da aplicação.
 */
final class HostCanonico
{
    /**
     * @param string $publicUrl  `App::$publicURL` (ex.: https://exemplo.com.br/); vazio desliga o redirecionamento
     * @param string $baseUrl    `App::$baseURL` (ex.: https://exemplo.com.br/clinica/)
     * @param bool   $https      a requisição chegou por HTTPS
     * @param string $host       cabeçalho Host da requisição (pode trazer porta)
     * @param string $requestUri caminho e query originais (`REQUEST_URI`)
     *
     * @return string|null URL de destino, ou null se a requisição já é canônica
     */
    public static function destino(string $publicUrl, string $baseUrl, bool $https, string $host, string $requestUri): ?string
    {
        $publica = parse_url(trim($publicUrl));
        $hostPublico = strtolower((string) ($publica['host'] ?? ''));
        $host = strtolower(trim($host));
        if ($hostPublico === '' || $host === '' || self::ehLocal($host)) {
            return null;
        }

        $esquema = strtolower((string) ($publica['scheme'] ?? 'https'));
        $autoridade = $hostPublico . (isset($publica['port']) ? ':' . $publica['port'] : '');
        $caminho = $requestUri === '' ? '/' : $requestUri;
        if ($caminho[0] !== '/') {
            $caminho = '/' . $caminho;
        }

        $semSubpasta = self::semSubpasta($caminho, $baseUrl, (string) ($publica['path'] ?? ''));
        $hostCorreto = $host === $autoridade;
        $esquemaCorreto = $https === ($esquema === 'https');
        if ($hostCorreto && $esquemaCorreto && $semSubpasta === $caminho) {
            return null;
        }

        return $esquema . '://' . $autoridade . $semSubpasta;
    }

    /** Tira da frente do caminho a subpasta da baseURL, se a URL pública não a usa. */
    private static function semSubpasta(string $caminho, string $baseUrl, string $caminhoPublico): string
    {
        $subpasta = rtrim((string) (parse_url($baseUrl, PHP_URL_PATH) ?? ''), '/');
        if ($subpasta === '' || $subpasta === rtrim($caminhoPublico, '/')) {
            return $caminho;
        }

        $path = (string) parse_url($caminho, PHP_URL_PATH);
        if ($path !== $subpasta && !str_starts_with($path, $subpasta . '/')) {
            return $caminho;
        }

        return '/' . ltrim(substr($caminho, strlen($subpasta)), '/');
    }

    private static function ehLocal(string $host): bool
    {
        $nome = preg_replace('/:\d+$/', '', $host);

        return in_array($nome, ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
