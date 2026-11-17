<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Site\HostCanonico;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Redireciona (301, num único salto) o site público para o endereço canônico
 * de `App::$publicURL`: protocolo, host (www × sem www) e subpasta `/clinica/`.
 * Só atua em GET/HEAD e só quando `publicURL` está configurada.
 */
class HostCanonicoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return null;
        }

        $app = config('App');
        // O Host e a URI originais vêm de $_SERVER: a URI do framework já é montada a partir da baseURL.
        $https = ($request->getServer('HTTPS') ?? '') !== '' && strtolower((string) $request->getServer('HTTPS')) !== 'off'
            || strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';

        $destino = HostCanonico::destino(
            (string) $app->publicURL,
            (string) $app->baseURL,
            $https,
            (string) ($request->getServer('HTTP_HOST') ?? ''),
            (string) ($request->getServer('REQUEST_URI') ?? '/'),
        );

        return $destino === null ? null : service('response')->redirect($destino, 'auto', 301);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
