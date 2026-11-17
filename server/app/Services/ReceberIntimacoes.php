<?php

namespace App\Services;

use Exception;
use App\Services\DjenService;

/**
 * Serviço para recebimento de intimações do DJEN (Diário de Justiça Eletrônico Nacional).
 *
 * @deprecated Use DjenService para novas implementações. Esta classe foi mantida para
 * compatibilidade com a interface web e delega a lógica para o DjenService.
 *
 * @version 1.1.0
 */
class ReceberIntimacoes extends BaseService
{
    /**
     * Instância do serviço DJEN.
     */
    protected DjenService $djenService;

    /**
     * Construtor da classe ReceberIntimacoes.
     *
     * @param DjenService|null $djenService
     */
    public function __construct(?DjenService $djenService = null)
    {
        $this->djenService = $djenService ?? new DjenService();
    }

    /**
     * Busca as intimações no DJEN.
     *
     * @param array|string $params Parâmetros de filtro para a busca na API.
     * @return array Retorna um array com 'status', 'message' e 'intimacoesProcessadas'.
     */
    public function getIntimacoes(array|string $params)
    {
        try {
            // Se os parâmetros contiverem OAB e UF, usamos a lógica robusta do DjenService
            if (is_array($params) && isset($params['numeroOab']) && isset($params['ufOab'])) {
                $resultado = $this->djenService->syncForOab(
                    $params['numeroOab'],
                    $params['ufOab'],
                    $params['dataDisponibilizacaoInicio'] ?? null,
                    $params['dataDisponibilizacaoFim'] ?? null
                );

                return [
                    'status' => 'success',
                    'message' => 'Intimações processadas com sucesso via DJEN.',
                    'intimacoesProcessadas' => $resultado['total_novo'],
                    'total_encontrado' => $resultado['total_encontrado'],
                ];
            }

            // Se for passado número do processo (ou chave correspondente)
            $numeroProcesso = null;
            if (is_array($params)) {
                $numeroProcesso = $params['numeroProcesso'] ?? $params['numero_processo'] ?? null;
            } elseif (is_string($params)) {
                $numeroProcesso = $params;
            }

            if (!empty($numeroProcesso)) {
                $resultado = $this->djenService->syncForProcesso(
                    $numeroProcesso,
                    is_array($params) ? ($params['dataDisponibilizacaoInicio'] ?? null) : null,
                    is_array($params) ? ($params['dataDisponibilizacaoFim'] ?? null) : null
                );

                return [
                    'status' => 'success',
                    'message' => 'Intimações processadas com sucesso via DJEN para o processo.',
                    'intimacoesProcessadas' => $resultado['total_novo'],
                    'total_encontrado' => $resultado['total_encontrado'],
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Parâmetros insuficientes para busca (OAB/UF ou número do processo são obrigatórios).',
            ];

        } catch (Exception $e) {
            log_message('error', 'Erro em ReceberIntimacoes: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Erro ao processar intimações: ' . $e->getMessage(),
            ];
        }
    }
}
