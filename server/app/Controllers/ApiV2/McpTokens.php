<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Mcp as McpConfig;
use Exception;

/**
 * Gerencia os Personal Access Tokens (Shield) que o próprio usuário gera
 * para plugar um chat de IA externo (Claude Desktop, ChatGPT etc.) no
 * endpoint /mcp — ver App\Filters\McpAuthFilter.
 *
 * Cada usuário só vê/gerencia seus próprios tokens. O token nunca sai do
 * escopo `mcp`, então mesmo vazado ele não concede acesso ao restante da
 * API v2 (que exige `token->can('*')` implicitamente via chain 'tokens'
 * apenas nas rotas que optarem por aceitá-lo — este endpoint continua
 * exigindo sessão/login para ser gerenciado).
 */
class McpTokens extends BaseController
{
    use ResponseTrait;

    /**
     * Lista os tokens de IA do usuário autenticado. O valor do token nunca
     * é retornado aqui — apenas no momento da criação (gerar()).
     */
    public function listar(): ResponseInterface
    {
        $config = config(McpConfig::class);
        $tokens = auth()->user()->accessTokens();

        $data = [];
        foreach ($tokens as $token) {
            if (!$token->can($config->tokenScope)) {
                continue;
            }

            $data[] = [
                'id'            => $token->id,
                'nome'          => $token->name,
                'criado_em'     => $token->created_at,
                'ultimo_uso_em' => $token->last_used_at?->toDateTimeString(),
                'expira_em'     => $token->expires?->toDateTimeString(),
            ];
        }

        return $this->respond(['success' => true, 'data' => $data]);
    }

    /**
     * Gera um novo token de IA para o usuário autenticado, com escopo
     * restrito a `mcp`. O valor bruto do token só é exposto nesta resposta
     * — não é recuperável depois, apenas o usuário deve guardá-lo.
     */
    public function gerar(): ResponseInterface
    {
        $config = config(McpConfig::class);
        $dados  = $this->request->getJSON(true) ?? [];
        $nome   = trim((string) ($dados['nome'] ?? ''));

        if ($nome === '') {
            return $this->respond([
                'success' => false,
                'message' => 'Informe um nome para identificar o token (ex.: "Claude Desktop").',
            ], 400);
        }

        try {
            $token = auth()->user()->generateAccessToken($nome, [$config->tokenScope]);

            return $this->respond([
                'success' => true,
                'data'    => [
                    'id'    => $token->id,
                    'nome'  => $token->name,
                    'token' => $token->raw_token,
                ],
                'message' => 'Copie o token agora — ele não será exibido novamente.',
            ], 201);
        } catch (Exception $e) {
            log_message('error', 'Erro ao gerar token de IA (MCP): ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao gerar token.'], 500);
        }
    }

    /**
     * Revoga um token de IA do usuário autenticado.
     */
    public function revogar(int $id): ResponseInterface
    {
        $usuario = auth()->user();
        $token   = $usuario->getAccessTokenById($id);

        if ($token === null) {
            return $this->respond(['success' => false, 'message' => 'Token não encontrado.'], 404);
        }

        model(UserIdentityModel::class)->delete($id);

        return $this->respond(['success' => true, 'message' => 'Token revogado.']);
    }
}
