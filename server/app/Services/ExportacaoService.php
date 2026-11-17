<?php

namespace App\Services;

use App\Repositories\ProcessSyncRepository;
use App\Services\IntimacoesService;
use Exception;
use PHPUnit\Util\Json;

/**
 * Serviço responsável pela exportação e sincronização de dados.
 *
 * Esta classe gerencia a sincronização de processos, intimações e movimentos
 * entre o sistema local e um sistema externo (remoto) via API.
 */
class ExportacaoService extends BaseService
{
    /**
     * Serviço de intimações.
     *
     * @var IntimacoesService
     */
    protected IntimacoesService $intimacoesService;

    /**
     * Serviço de movimentos.
     *
     * @var MovimentosService
     */
    protected MovimentosService $movimentosService;

    /**
     * Construtor do ExportacaoService.
     *
     * Inicializa as instâncias necessárias de repositórios e serviços.
     *
     * @param ProcessSyncRepository|null $repository
     * @param IntimacoesService|null $intimacoesService
     * @param MovimentosService|null $movimentosService
     */
    public function __construct(
        ?ProcessSyncRepository $repository = null,
        ?IntimacoesService $intimacoesService = null,
        ?MovimentosService $movimentosService = null
    ) {
        $this->repository               = $repository ?? new ProcessSyncRepository();
        $this->intimacoesService        = $intimacoesService ?? new IntimacoesService();
        $this->movimentosService        = $movimentosService ?? new MovimentosService();
    }

    /**
     * Retorna todos os processos formatados para exportação.
     *
     * Recupera a lista completa de processos do banco de dados, formatada
     * adequadamente para fins de exportação.
     *
     * @return array Lista de processos.
     */
    public function getAllProcessosForExport(): array
    {
        return $this->repository->buscarTodosProcessos();
    }

    /**
     * Sincroniza processos do sistema de origem para o sistema de destino.
     *
     * Busca processos que ainda não foram sincronizados e tenta enviá-los para o
     * sistema remoto. Se bem-sucedido, marca o processo como sincronizado localmente.
     *
     * @param int $limit O número máximo de processos a serem buscados e sincronizados por execução. Padrão 10000.
     * @return array Retorna um array com o resultado da sincronização:
     *               - 'success': Mensagem de resumo.
     *               - 'added_count': Quantidade de processos sincronizados com sucesso.
     *               - 'failed_count': Quantidade de falhas.
     *               - 'unsynchronized_count': Quantidade restante de processos não sincronizados.
     */
    public function synchronizeProcesses(int $limit = 10000): array
    {
        // 1. Busca um lote de processos que ainda não foram sincronizados.
        // Presume-se que o repositório tenha um método para buscar processos por status de sincronização.
        $processosNaoSincronizados = $this->repository->buscarProcessosNaoSincronizados($limit);
        $totalNaoSincronizados = count($processosNaoSincronizados);

        $addedCount = 0;
        $failedCount = 0;

        // 2. Verifica se há processos para sincronizar.
        if (empty($processosNaoSincronizados)) {
            log_message('info', 'Nenhum processo para sincronizar.');
            return [
                'success' => 'Nenhum processo para sincronizar.',
                'added_count' => 0,
                'failed_count' => 0,
                'unsynchronized_count' => $totalNaoSincronizados,
            ];
        }

        // 3. Itera sobre cada processo no lote para sincronização individual.
        foreach ($processosNaoSincronizados as $processoOrigem) {
            $numeroProcesso = null;
            if (!is_array($processoOrigem)) {
                log_message('warning', 'Formato inesperado de processo, ignorando: ' . json_encode($processoOrigem));
                $failedCount++;
                continue;
            }
            if (isset($processoOrigem['numero_processo']) && !empty($processoOrigem['numero_processo'])) {
                $numeroProcesso = $processoOrigem['numero_processo'];
            } else {
                $numeroProcesso = preg_replace('/[.-]/', '', $processoOrigem['numeroprocessocommascara']);
            }

            // Pula o processo se o número for inválido.
            if ($numeroProcesso === null) {
                log_message('warning', 'Processo de origem sem "numero_processo" ignorado: ' . json_encode($processoOrigem));
                $failedCount++;
                continue;
            }

            try {
                // Adiciona o novo processo ao destino.
                if ($this->addRemoteProcess($processoOrigem)) {
                    // Se a adição for bem-sucedida, registra a sincronização.
                    $this->repository->registrarSincronizacaoProcesso($numeroProcesso);
                    $addedCount++;
                    log_message('info', 'Novo processo adicionado com sucesso: ' . $numeroProcesso);
                } else {
                    log_message('error', 'Falha ao adicionar processo remoto: ' . $numeroProcesso);
                    $failedCount++;
                    continue;
                }
            } catch (Exception $e) {

                // Em caso de erro, registra o log e continua para o próximo processo.
                log_message('error', 'Erro ao adicionar processo ' . $numeroProcesso . ': ' . $e->getMessage());
                $failedCount++;
                continue;
            }
        }

        // 4. Obtém a nova contagem de processos não sincronizados.
        $newTotalNaoSincronizados = $this->repository->contarProcessosNaoSincronizados();

        // 5. Retorna o resultado final.
        return [
            'success'               => "Sincronização concluída. {$addedCount} adicionados, {$failedCount} falhas.",
            'added_count'           => $addedCount,
            'failed_count'          => $failedCount,
            'unsynchronized_count'  => $newTotalNaoSincronizados,
        ];
    }

    /**
     * Envia um novo processo para ser adicionado no sistema de destino via API.
     *
     * Faz uma requisição POST para a API externa com os dados do processo.
     *
     * @param array $processo O array de dados do processo a ser adicionado.
     * @return bool True se a adição for bem-sucedida (HTTP 2xx), false caso contrário.
     */
    private function addRemoteProcess(array $processo): bool
    {
        $urlAdicionar = getenv('URL_SYNC') . 'processos/adicionar';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlAdicionar);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('API_KEY_SYNC'),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($processo));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            log_message('error', 'Erro cURL ao adicionar processo remoto (' . ($processo['numero_processo'] ?? 'N/A') . '): ' . $error_msg);
            curl_close($ch);
            return false;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            log_message('info', 'Processo remoto adicionado com sucesso: ' . ($processo['numero_processo'] ?? 'N/A'));
            return true;
        } else {
            log_message('error', 'Falha ao adicionar processo remoto (' . ($processo['numero_processo'] ?? 'N/A') . '). HTTP Code: ' . $http_code . ', Resposta: ' . $response);
            return false;
        }
    }

    /**
     * Sincroniza as intimações pendentes.
     *
     * Busca intimações não sincronizadas, envia para o sistema remoto e atualiza o status local.
     *
     * @return array Retorna um array com o resultado da sincronização:
     *               - 'success': Mensagem de resumo.
     *               - 'added_count': Quantidade de intimações sincronizadas.
     *               - 'failed_count': Quantidade de falhas.
     *               - 'unsynchronized_count': Quantidade restante não sincronizada.
     */
    public function synchronizeIntimacoes()
    {
        $intimacoesNaoSincronizadas = $this->intimacoesService->listarIntimacoesNaoSincronizadas(100);

        $addedCount = 0;
        $failedCount = 0;

        foreach ($intimacoesNaoSincronizadas as $intimacao) {
            $numeroProcesso = $intimacao['numero_processo'] ?? null;
            if ($numeroProcesso === null) {
                log_message('error', 'Intimação sem numero_processo, ignorando: ' . json_encode($intimacao));
                $failedCount++;
                continue;
            }

            log_message('info', 'Sincronizando intimação para o processo: ' . $numeroProcesso);
            $this->addRemoteIntimacao($intimacao);
            $this->intimacoesService->registrarSincronizacao($intimacao['id_intimacao']);
            $addedCount++;
        }
        $newTotalNaoSincronizados = count($this->intimacoesService->listarIntimacoesNaoSincronizadas());
        // Retorna o resultado final.
        return [
            'success'               => "Sincronização concluída. {$addedCount} adicionados, {$failedCount} falhas.",
            'added_count'           => $addedCount,
            'failed_count'          => $failedCount,
            'unsynchronized_count'  => $newTotalNaoSincronizados,
        ];
    }

    /**
     * Envia uma nova intimação para ser adicionada no sistema de destino via API.
     *
     * @param array $intimacao O array de dados da intimação a ser adicionada.
     * @return bool True se a adição for bem-sucedida, false caso contrário.
     */
    public function addRemoteIntimacao(array $intimacao): bool
    {
        $urlAdicionar = getenv('URL_SYNC') . 'intimacoes/adicionar';
        log_message('info', 'URL de destino para adicionar intimação: ' . $urlAdicionar);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlAdicionar);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('API_KEY_SYNC'),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($intimacao));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            log_message('error', 'Erro cURL ao adicionar intimação remota: ' . $error_msg);
            curl_close($ch);
            return false;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            log_message('info', 'Intimação remota adicionada com sucesso.');
            return true;
        } else {
            log_message('error', 'Falha ao adicionar intimação remota. HTTP Code: ' . $http_code . ', Resposta: ' . $response);
            return false;
        }
    }

    /**
     * Sincroniza os movimentos processuais.
     *
     * Delega a sincronização de movimentos para o MovimentosService.
     *
     * @return array Resultado da sincronização de movimentos.
     */
    public function synchronizeMovimentos()
    {
        return $this->movimentosService->synchronizeMovimentos();
    }
}
