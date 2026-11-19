<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ClienteAreaService;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller de Dados da Área do Cliente.
 *
 * Todos os endpoints exigem sessão Shield com grupo 'cliente'
 * (protegidos pelo ClienteAuthFilter com alias 'clienteAuth').
 *
 * Escopo de autorização:
 *   - O cliente_id é lido da sessão (definido pelo ClienteAreaService::criarSessaoShield)
 *   - Todos os dados são filtrados por esse cliente_id
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteArea extends BaseController
{
    use ResponseTrait;

    protected ClienteAreaService $clienteAreaService;

    /**
     * Construtor com injeção de dependência.
     *
     * @param ClienteAreaService|null $clienteAreaService
     */
    public function __construct(?ClienteAreaService $clienteAreaService = null)
    {
        $this->clienteAreaService = $clienteAreaService ?? new ClienteAreaService();
    }

    // =========================================================================
    // PERFIL
    // =========================================================================

    /**
     * Retorna os dados do perfil do cliente logado.
     *
     * GET /api/v2/cliente/perfil
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function perfil(): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');
        $cliente   = $this->clienteAreaService->getById($clienteId);

        if (!$cliente) {
            return $this->respond([
                'success' => false,
                'message' => 'Perfil não encontrado.',
            ], 404);
        }

        return $this->respond([
            'success' => true,
            'data'    => [
                'id_cliente'   => $cliente['id_cliente'],
                'nome'         => $cliente['nome'] ?? $cliente['razao_social'] ?? '',
                'email'        => $cliente['email'],
                'telefone'     => $cliente['telefone'] ?? null,
                'tipo_cliente' => $cliente['tipo_cliente'] ?? null,
                'documento'    => $cliente['documento'] ?? null,
                'cidade'       => $cliente['cidade'] ?? null,
                'uf'           => $cliente['uf'] ?? null,
            ],
        ]);
    }

}
