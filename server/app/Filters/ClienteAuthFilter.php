<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro de autenticação da Área do Cliente.
 *
 * Protege os endpoints da Área do Cliente verificando:
 *   1. Sessão Shield ativa (`auth()->loggedIn()`)
 *   2. Usuário pertencente ao grupo `cliente`
 *
 * Reutiliza a infraestrutura do CodeIgniter Shield — sem sessão paralela.
 * O logout via `auth()->logout()` é suficiente para encerrar a sessão.
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteAuthFilter implements FilterInterface
{
    /**
     * Verifica autenticação Shield e grupo 'cliente' antes de cada requisição.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     * @return ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // 1. Verifica se há sessão Shield ativa
        if (!auth()->loggedIn()) {
            return $this->responderNaoAutorizado('Sessão expirada. Solicite um novo link de acesso.');
        }

        // 2. Verifica se o usuário pertence ao grupo 'cliente'
        $user = auth()->user();

        if (!$user || !$user->inGroup('cliente')) {
            // Encerra qualquer sessão indevida
            auth()->logout();
            return $this->responderNaoAutorizado('Acesso restrito à Área do Cliente.');
        }

        // 3. Garante que o cliente_id está na sessão (segurança adicional de escopo)
        if (!session()->has('cliente_id')) {
            auth()->logout();
            return $this->responderNaoAutorizado('Sessão de cliente inválida. Solicite um novo link de acesso.');
        }

        // 4. Proteção CSRF: esta área autentica por cookie de sessão e fica fora
        // do filtro global de CSRF (que exclui todo o prefixo "api/*"), então
        // validamos a origem manualmente em requisições que alteram estado.
        if (in_array(strtolower($request->getMethod()), ['post', 'put', 'patch', 'delete'], true)) {
            if (!$this->origemPermitida($request)) {
                return $this->responderNaoAutorizado('Origem da requisição não permitida.');
            }
        }
    }

    /**
     * Verifica se a requisição vem de uma origem confiável, usando o cabeçalho
     * Origin (preferencial) ou Referer como alternativa.
     *
     * @param RequestInterface $request
     * @return bool
     */
    private function origemPermitida(RequestInterface $request): bool
    {
        $allowedOrigins = config('Cors')->default['allowedOrigins'] ?? [];

        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            return in_array(rtrim($origin, '/'), array_map(static fn ($o) => rtrim($o, '/'), $allowedOrigins), true);
        }

        $referer = $request->getHeaderLine('Referer');
        if ($referer !== '') {
            $refererOrigin = parse_url($referer, PHP_URL_SCHEME) . '://' . parse_url($referer, PHP_URL_HOST);
            $refererPort = parse_url($referer, PHP_URL_PORT);
            if ($refererPort) {
                $refererOrigin .= ':' . $refererPort;
            }

            return in_array(rtrim($refererOrigin, '/'), array_map(static fn ($o) => rtrim($o, '/'), $allowedOrigins), true);
        }

        return false;
    }

    /**
     * Não executa nada após a requisição.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Sem ação pós-requisição necessária
    }

    /**
     * Retorna resposta JSON 401 padronizada.
     *
     * @param string $mensagem Mensagem de erro amigável.
     * @return ResponseInterface
     */
    private function responderNaoAutorizado(string $mensagem): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setContentType('application/json')
            ->setBody(json_encode([
                'success' => false,
                'error'   => $mensagem,
            ]));
    }
}
