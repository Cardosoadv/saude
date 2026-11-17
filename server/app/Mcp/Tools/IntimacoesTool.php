<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\IntimacoesService;
use App\Mcp\Support\ModulosMcp;
use App\Mcp\Support\PrivacidadeMcp;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

final class IntimacoesTool
{
    private IntimacoesService $intimacoesService;

    public function __construct()
    {
        $this->intimacoesService = new IntimacoesService();
    }

    /**
     * Lista intimações. Por padrão retorna apenas as não tratadas.
     */
    #[McpTool(name: 'listar_intimacoes')]
    public function listarIntimacoes(bool $apenasNaoTratadas = true): array
    {
        ModulosMcp::exigir('intimacoes', 'intimacoes.access');

        return PrivacidadeMcp::filtrarIntimacoes($apenasNaoTratadas
            ? $this->intimacoesService->listarIntimacoes(true)
            : $this->intimacoesService->listarTodasIntimacoes());
    }

    /**
     * Retorna o detalhe completo de uma intimação.
     */
    #[McpTool(name: 'obter_intimacao')]
    public function obterIntimacao(int $intimacaoId): array
    {
        ModulosMcp::exigir('intimacoes', 'intimacoes.access');

        $intimacao = $this->intimacoesService->detalharIntimacao($intimacaoId);

        // Intimação de processo restrito segue a privacidade do processo.
        if (PrivacidadeMcp::filtrarIntimacoes([$intimacao]) === []) {
            throw new ToolCallException('Registro não encontrado ou sem permissão de acesso.');
        }

        return $intimacao;
    }
}
