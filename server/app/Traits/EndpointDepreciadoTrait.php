<?php

declare(strict_types=1);

namespace App\Traits;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Marca todas as respostas de um controller como depreciadas, enviando o
 * header HTTP `Warning: 299`.
 *
 * @deprecated Uso restrito aos controllers do módulo de jurisprudência (Issue #677).
 */
trait EndpointDepreciadoTrait
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->response->setHeader(
            'Warning',
            '299 - "This endpoint is deprecated and will be removed in a future release"',
        );
    }
}
