<?php

namespace App\Services;

use Exception;
use Config\Services;
use App\Services\IntimacoesService;
use App\Libraries\Importacao\NormalizadorFontesProcesso;

/**
 * Serviço especializado para integração com a API do DJEN (CNJ).
 *
 * Lida com a busca estruturada de comunicações, paginação e
 * limites de requisição da API pública do PJe.
 *
 * @version 1.1.0
 */
class DjenService extends BaseService
{
    protected string $baseUrl = 'https://comunicaapi.pje.jus.br/api/v1/comunicacao';
    protected IntimacoesService $intimacoesService;
    protected $client;

    /**
     * Construtor do DjenService.
     *
     * @param IntimacoesService|null $intimacoesService
     */
    public function __construct(?IntimacoesService $intimacoesService = null)
    {
        $this->intimacoesService = $intimacoesService ?? new IntimacoesService();
        $this->client = Services::curlrequest([
            'base_uri' => $this->baseUrl,
            'timeout'  => 30,
        ]);
    }

    /**
     * Sincroniza todas as comunicações para uma OAB específica em um intervalo de datas.
     *
     * @param string $oab Número da OAB
     * @param string $uf UF da OAB
     * @param string|null $dataInicio Formato YYYY-MM-DD
     * @param string|null $dataFim Formato YYYY-MM-DD
     * @return array Resumo da sincronização
     */
    public function syncForOab(string $oab, string $uf, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $dataInicio = $dataInicio ?: date('Y-m-d', strtotime('-2 days'));
        $dataFim = $dataFim ?: date('Y-m-d');

        $pagina = 1;
        $itensPorPagina = 100; // Valor válido segundo a doc: 5 ou 100
        $totalProcessado = 0;
        $totalNovo = 0;
        $erro = null;

        log_message('info', "DJEN: Iniciando sincronização OAB {$oab}-{$uf} entre {$dataInicio} e {$dataFim}");

        do {
            $params = [
                'numeroOab'                     => $oab,
                'ufOab'                         => strtoupper($uf),
                'dataDisponibilizacaoInicio'    => $dataInicio,
                'dataDisponibilizacaoFim'       => $dataFim,
                'pagina'                        => $pagina,
                'itensPorPagina'                => $itensPorPagina,
            ];

            try {
                $response = $this->callApi($params);

                if (empty($response['items'])) {
                    break;
                }

                // Salva JSON bruto para auditoria (seguindo o padrão do sistema)
                $filename = $this->generateRawFilename($oab, $pagina);
                $this->saveRawJson($filename, json_encode($response));

                // Processa via IntimacoesService (que já lida com duplicatas e disparo de IA)
                $stats = $this->intimacoesService->processarIntimacoes($response, $filename, user_id() ?: 0);

                $totalProcessado += count($response['items']);
                $totalNovo += $stats['numero_intimacoes_processadas'] ?? 0;

                // Verifica se há mais páginas
                $countTotal = $response['count'] ?? 0;
                $temMaisPaginas = $totalProcessado < $countTotal && count($response['items']) == $itensPorPagina;

                $pagina++;

            } catch (Exception $e) {
                log_message('error', "DJEN Error: " . $e->getMessage());
                $erro = $e->getMessage();
                break;
            }

        } while ($temMaisPaginas);

        return [
            'oab'               => $oab,
            'total_encontrado'  => $totalProcessado,
            'total_novo'        => $totalNovo,
            'erro'              => $erro,
        ];
    }

    /**
     * Sincroniza todas as comunicações para um número de processo específico em um intervalo de datas.
     *
     * @param string $numeroProcesso Número do processo (com ou sem máscara)
     * @param string|null $dataInicio Formato YYYY-MM-DD
     * @param string|null $dataFim Formato YYYY-MM-DD
     * @return array Resumo da sincronização
     */
    public function syncForProcesso(string $numeroProcesso, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $numeroProcessoLimpo = preg_replace('/[^0-9]/', '', $numeroProcesso);

        if (empty($numeroProcessoLimpo)) {
            return [
                'numero_processo'  => $numeroProcesso,
                'total_encontrado' => 0,
                'total_novo'        => 0,
            ];
        }

        $dataInicio = $dataInicio ?: date('Y-m-d', strtotime('-30 days'));
        $dataFim = $dataFim ?: date('Y-m-d');

        $pagina = 1;
        $itensPorPagina = 100;
        $totalProcessado = 0;
        $totalNovo = 0;
        $erro = null;

        log_message('info', "DJEN: Iniciando sincronização Processo {$numeroProcessoLimpo} entre {$dataInicio} e {$dataFim}");

        do {
            $params = [
                'numeroProcesso'                => $numeroProcessoLimpo,
                'dataDisponibilizacaoInicio'    => $dataInicio,
                'dataDisponibilizacaoFim'       => $dataFim,
                'pagina'                        => $pagina,
                'itensPorPagina'                => $itensPorPagina,
            ];

            try {
                $response = $this->callApi($params);

                if (empty($response['items'])) {
                    break;
                }

                // Salva JSON bruto para auditoria
                $filename = $this->generateRawProcessFilename($numeroProcessoLimpo, $pagina);
                $this->saveRawJson($filename, json_encode($response));

                // Processa via IntimacoesService
                $stats = $this->intimacoesService->processarIntimacoes($response, $filename, user_id() ?: 0);

                $totalProcessado += count($response['items']);
                $totalNovo += $stats['numero_intimacoes_processadas'] ?? 0;

                $countTotal = $response['count'] ?? 0;
                $temMaisPaginas = $totalProcessado < $countTotal && count($response['items']) == $itensPorPagina;

                $pagina++;

            } catch (Exception $e) {
                log_message('error', "DJEN Processo Error: " . $e->getMessage());
                $erro = $e->getMessage();
                break;
            }

        } while ($temMaisPaginas);

        return [
            'numero_processo'   => $numeroProcessoLimpo,
            'total_encontrado'  => $totalProcessado,
            'total_novo'        => $totalNovo,
            'erro'              => $erro,
        ];
    }

    /**
     * Consulta as comunicações de um processo no DJEN sem gravar nada (não
     * salva intimações, não chama a IA e não cria tarefas).
     *
     * Usada na conferência da importação em lote. Se o DJEN responder com
     * limite de consultas atingido, devolve erro em vez de aguardar.
     *
     * @return array{status: string, mensagem: ?string, dados: array}
     *         status: "encontrado", "nao_encontrado" ou "erro".
     */
    public function consultarProcesso(string $numeroProcesso, int $maxPaginas = 5): array
    {
        $numero = preg_replace('/[^0-9]/', '', $numeroProcesso);
        $itens  = [];

        try {
            for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
                $response = $this->callApi([
                    'numeroProcesso'             => $numero,
                    'dataDisponibilizacaoInicio' => BuscarProcessosService::DATA_INICIO_CONSULTA,
                    'dataDisponibilizacaoFim'    => date('Y-m-d'),
                    'pagina'                     => $pagina,
                    'itensPorPagina'             => 100,
                ], false);

                $lote  = $response['items'] ?? [];
                $itens = array_merge($itens, $lote);

                if (count($lote) < 100 || count($itens) >= (int) ($response['count'] ?? 0)) {
                    break;
                }
            }
        } catch (Exception $e) {
            log_message('error', "DJEN: falha ao consultar {$numero}: " . $e->getMessage());

            return [
                'status'   => 'erro',
                'mensagem' => str_contains($e->getMessage(), 'limite de consultas')
                    ? $e->getMessage()
                    : 'Não foi possível consultar o DJEN agora. Tente novamente.',
                'dados'    => NormalizadorFontesProcesso::deDjen([]),
            ];
        }

        return [
            'status'   => $itens === [] ? 'nao_encontrado' : 'encontrado',
            'mensagem' => $itens === [] ? 'Nenhuma publicação deste processo no DJEN.' : null,
            'dados'    => NormalizadorFontesProcesso::deDjen($itens),
        ];
    }

    /**
     * Pesquisa no DJEN as comunicações em que aparece uma parte com o nome
     * informado, sem gravar nada (não salva intimações nem chama a IA).
     *
     * O DJEN compara o nome por aproximação; quem chama deve conferir se a
     * parte aparece mesmo entre os destinatários. Com limite de consultas
     * atingido, devolve erro em vez de aguardar.
     *
     * @return array{status: string, mensagem: ?string, total: int, truncado: bool, itens: array}
     *         status: "encontrado", "nao_encontrado" ou "erro"; total é o
     *         número de comunicações informado pelo DJEN (limitado a 10.000 por ele).
     */
    public function pesquisarPorNomeParte(
        string $nome,
        string $dataInicio,
        string $dataFim,
        ?string $siglaTribunal = null,
        int $maxPaginas = 5
    ): array {
        $params = [
            'nomeParte'                  => $nome,
            'dataDisponibilizacaoInicio' => $dataInicio,
            'dataDisponibilizacaoFim'    => $dataFim,
            'itensPorPagina'             => 100,
        ];
        if ($siglaTribunal !== null && $siglaTribunal !== '') {
            $params['siglaTribunal'] = strtoupper($siglaTribunal);
        }

        $itens = [];
        $total = 0;

        try {
            for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
                $response = $this->callApi($params + ['pagina' => $pagina], false);
                $lote     = $response['items'] ?? [];
                $total    = (int) ($response['count'] ?? 0);
                $itens    = array_merge($itens, $lote);

                if (count($lote) < 100 || count($itens) >= $total) {
                    break;
                }
            }
        } catch (Exception $e) {
            log_message('error', 'DJEN: falha ao pesquisar parte: ' . $e->getMessage());
            $mensagem = str_contains($e->getMessage(), 'limite de consultas')
                ? $e->getMessage()
                : 'Não foi possível consultar o DJEN agora. Tente novamente.';

            // Falha numa página seguinte: devolve o que já veio, avisando.
            if ($itens !== []) {
                return [
                    'status'   => 'encontrado',
                    'mensagem' => 'A consulta foi interrompida e o resultado está incompleto. ' . $mensagem,
                    'total'    => max($total, count($itens)),
                    'truncado' => true,
                    'itens'    => $itens,
                ];
            }

            return ['status' => 'erro', 'mensagem' => $mensagem, 'total' => 0, 'truncado' => false, 'itens' => []];
        }

        return [
            'status'   => $itens === [] ? 'nao_encontrado' : 'encontrado',
            'mensagem' => $itens === [] ? 'Nenhuma publicação com essa parte no período.' : null,
            'total'    => max($total, count($itens)),
            'truncado' => count($itens) < $total,
            'itens'    => $itens,
        ];
    }

    /**
     * Gera nome de arquivo para o JSON bruto de busca por processo.
     */
    private function generateRawProcessFilename(string $numeroProcesso, int $pagina): string
    {
        $basePath = WRITEPATH . 'jsons/Intimacoes/' . date('Y/m/d');
        if (!is_dir($basePath)) {
            mkdir($basePath, 0o755, true);
        }
        return "{$basePath}/DJEN_PROC_{$numeroProcesso}_P{$pagina}_" . date('His') . ".json";
    }

    /**
     * Realiza a chamada HTTP tratando rate limits.
     */
    private function callApi(array $params, bool $aguardarLimite = true): array
    {
        $maxRetries = 2;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            $response = $this->client->get($this->baseUrl, [
                'query'           => $params,
                'headers'         => [
                    'Accept'      => 'application/json',
                ],
                'http_errors'     => false,
            ]);

            $statusCode = $response->getStatusCode();
            $this->checkRateLimit($response);

            if ($statusCode === 200) {
                return json_decode($response->getBody(), true);
            }

            if ($statusCode === 429) {
                if (!$aguardarLimite) {
                    throw new Exception('DJEN: limite de consultas atingido. Aguarde um minuto e tente novamente.');
                }
                log_message('warning', "DJEN: Rate limit atingido. Aguardando 1 minuto...");
                sleep(61);
                $retryCount++;
                continue;
            }

            throw new Exception("Erro na API DJEN (HTTP $statusCode): " . $response->getBody());
        }

        throw new Exception("DJEN: Máximo de tentativas excedido após Rate Limit.");
    }

    /**
     * Monitora os headers de rate limit.
     */
    private function checkRateLimit($response): void
    {
        $remaining = $response->getHeaderLine('x-ratelimit-remaining');

        if ($remaining !== '' && (int) $remaining < 10) {
            log_message('debug', "DJEN: Rate limit baixo ($remaining). Aplicando pausa preventiva.");
            usleep(500000); // 0.5s pause
        }
    }

    /**
     * Gera nome de arquivo para o JSON bruto.
     */
    private function generateRawFilename(string $oab, int $pagina): string
    {
        $basePath = WRITEPATH . 'jsons/Intimacoes/' . date('Y/m/d');
        if (!is_dir($basePath)) {
            mkdir($basePath, 0o755, true);
        }
        return "{$basePath}/DJEN_OAB_{$oab}_P{$pagina}_" . date('His') . ".json";
    }

    /**
     * Salva o JSON no disco.
     */
    private function saveRawJson(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }
}
