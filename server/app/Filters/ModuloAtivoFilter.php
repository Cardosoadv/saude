<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bloqueia a rota quando o módulo está desativado.
 *
 * Uso: 'filter' => ['permission:objetos.access', 'modulo:objetos']
 * Com vários slugs ('modulo:processos,objetos'), todos precisam estar ativos.
 *
 * Responde 403 com `code: modulo_inativo`, que o front trata como
 * "módulo desativado pelo administrador" (diferente de falta de permissão).
 */
class ModuloAtivoFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments Slugs dos módulos exigidos.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $modulos = service('modulos');

        foreach ($arguments ?? [] as $slug) {
            if (!$modulos->isActive($slug)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'code'    => 'modulo_inativo',
                        'modulo'  => $slug,
                        'message' => 'Este módulo está desativado.',
                    ]);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
