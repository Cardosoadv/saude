<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Mcp\Support\ModulosMcp;
use App\Services\LiquidacoesService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Consulta de liquidações (valor atualizado, parcelas e saldo) para a IA interna.
 * Somente leitura; o acesso ao processo segue as regras de LiquidacoesService.
 */
final class LiquidacoesTool
{
    private LiquidacoesService $service;

    public function __construct(?LiquidacoesService $service = null)
    {
        $this->service = $service ?? new LiquidacoesService();
    }

    /**
     * Retorna uma liquidação (valor atualizado na data-base, itens com critérios, parcelas e saldo a
     * receber). Informe `liquidacaoId` ou, para a mais recente de um processo, `processoId`.
     * Valores em centavos.
     */
    #[McpTool(name: 'obter_liquidacao')]
    public function obterLiquidacao(?int $liquidacaoId = null, ?int $processoId = null): array
    {
        ModulosMcp::exigir('processos', 'liquidacoes.access', 'liquidacoes.exclusive');

        try {
            if ($liquidacaoId === null && $processoId !== null) {
                $liquidacaoId = $this->service->resumoDoProcesso($processoId)['liquidacao_id'] ?? null;
                if ($liquidacaoId === null) {
                    throw new ToolCallException('Este processo não tem liquidação.');
                }
            }
            if ($liquidacaoId === null) {
                throw new ToolCallException('Informe liquidacaoId ou processoId.');
            }

            $liquidacao = $this->service->getById($liquidacaoId);
        } catch (LiquidacaoException $e) {
            throw new ToolCallException($e->getMessage());
        }

        if (!$liquidacao) {
            throw new ToolCallException('Liquidação não encontrada.');
        }

        // Sem o histórico completo de cálculos (pesado); a memória de cálculo vem pela API/PDF.
        unset($liquidacao['historico'], $liquidacao['processo']);

        return $liquidacao;
    }
}
