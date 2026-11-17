<?php

namespace App\Services;

use App\Repositories\BuscarProcessosRepository;
use App\Services\IntimacoesService;
use App\Services\ProcessosService;
use DateTime;

/**
 * @autor Cardoso
 * @version 0.9.1
 * @since 2026-01-01
 *
 * Serviço responsável por regras de negócio relacionadas a busca de processos.
 */
class BuscarProcessosService extends BaseService
{
    /** Tamanho máximo da observação (justificativa) de inclusão. */
    public const OBSERVACAO_MAX = 1000;

    /** Início do período consultado no DJEN para um processo pendente. */
    public const DATA_INICIO_CONSULTA = '2000-01-01';

    protected IntimacoesService $intimacoesService;
    protected ProcessosService  $processoService;

    /**
     * Construtor do BuscarProcessosService.
     *
     * @param BuscarProcessosRepository|null $repository
     * @param IntimacoesService|null $intimacoesService
     * @param ProcessosService|null $processoService
     */
    public function __construct(
        ?BuscarProcessosRepository $repository = null,
        ?IntimacoesService $intimacoesService = null,
        ?ProcessosService $processoService = null
    ) {
        $this->repository                   = $repository ?? new BuscarProcessosRepository();
        $this->intimacoesService            = $intimacoesService ?? new IntimacoesService();
        $this->processoService              = $processoService ?? new ProcessosService();
    }

    /**
     * Lista todos os processos cadastrados para busca.
     *
     * @return array
     */
    public function listar(): array
    {
        return $this->repository->findAllOrderedByNumero();
    }

    /**
     * Salva um novo processo para busca.
     *
     * @param array $data
     * @return bool|int
     */
    public function salvar(array $data)
    {
        if (isset($data['id_busca_processo'])) {
            $id = $data['id_busca_processo'];
            unset($data['id_busca_processo']);
            return $this->repository->update($id, $data);
        }
        return $this->repository->create($data);
    }

    /**
     * Executa a consulta de processos pendentes.
     *
     * @return void
     */
    public function consultarPendentes()
    {
        $buscarProcessos = $this->listar();
        log_message('info', 'Iniciando consulta de processos. Total de processos a consultar: ' . json_encode($buscarProcessos));

        $processosNaoLancados = [];
        foreach ($buscarProcessos as $buscarProcesso) {
            $numeroProcesso = preg_replace('/[^0-9]/', '', $buscarProcesso['numero_processo']);
            if ($this->processoService->processoJaExiste($numeroProcesso)) {
                log_message('info', 'Processo já existe: ' . $numeroProcesso);
                continue;
            } else {
                $processosNaoLancados[] = $buscarProcesso;
            }
        }

        log_message('info', 'Processos não lançados: ' . json_encode($processosNaoLancados));

        foreach ($processosNaoLancados as $processo) {
            $numeroProcesso = preg_replace('/[^0-9]/', '', $processo['numero_processo']);
            log_message('info', 'Consultando processo: ' . $numeroProcesso);

            $params = ['numeroProcesso' => $numeroProcesso];
            log_message('info', 'Parâmetros de consulta: ' . json_encode($params));

            // Consulta o processo
            $resultado = $this->intimacoesService->receberIntimacoes($params);
            log_message('info', 'Resultado da consulta: ' . json_encode($resultado));

            $updateData = [
                'data_tentativa' => date('Y-m-d H:i:s'),
                'resultado' => $resultado['status'] === 'success' ? 1 : 0,
            ];

            $this->repository->update($processo['id_busca_processo'], $updateData);
        }
    }

    /**
     * Separa os processos da busca automática que ainda precisam ser
     * consultados no DJEN dos que já estão na carteira.
     *
     * Os que já existem na carteira têm o registro de busca marcado como
     * localizado, mesmo que tenham sido cadastrados por outro meio (ex.:
     * manualmente ou por outra sincronização) e nunca tenham passado por uma
     * consulta da busca automática.
     *
     * @return array{pendentes: array, repetidos: int}
     */
    public function separarPendentes(): array
    {
        $pendentes = [];
        $repetidos = 0;

        foreach ($this->listar() as $buscarProcesso) {
            $numeroProcesso = preg_replace('/[^0-9]/', '', (string) $buscarProcesso['numero_processo']);

            $processoExistente = $this->processoService->processoJaExiste($numeroProcesso);
            if ($processoExistente) {
                $repetidos++;

                if ((int) ($buscarProcesso['id_processo_salvo'] ?? 0) !== (int) $processoExistente['id_processo']) {
                    $this->repository->update((int) $buscarProcesso['id_busca_processo'], [
                        'data_tentativa'    => $buscarProcesso['data_tentativa'] ?? date('Y-m-d H:i:s'),
                        'resultado'         => 1,
                        'id_processo_salvo' => $processoExistente['id_processo'],
                    ]);
                }

                continue;
            }

            $buscarProcesso['numero_processo'] = $numeroProcesso;
            $pendentes[] = $buscarProcesso;
        }

        return ['pendentes' => $pendentes, 'repetidos' => $repetidos];
    }

    /**
     * Gera os dados para a busca automática.
     *
     * @param string $apiUrl
     * @return array
     */
    public function gerarDadosBuscaAutomatica(string $apiUrl): array
    {
        $urls = [];
        $itens = [];
        $separados = $this->separarPendentes();

        foreach ($separados['pendentes'] as $buscarProcesso) {
            $numeroProcesso = $buscarProcesso['numero_processo'];
            $params = $this->montarParametrosConsulta($numeroProcesso);
            $url = $apiUrl . '?' . http_build_query($params);
            $urls[] = $url;
            $itens[] = [
                'id_busca_processo' => $buscarProcesso['id_busca_processo'],
                'numero_processo'   => $numeroProcesso,
                'url'               => $url,
            ];
        }

        return [
            'urls' => $urls,
            'itens' => $itens,
            'processosParaLancar' => count($itens),
            'processoRepetido' => $separados['repetidos'],
        ];
    }

    /**
     * Registra o resultado de uma consulta feita pelo servidor (ex.: comando
     * sync:djen) para um processo da busca automática.
     *
     * @param int    $id             ID do registro de busca.
     * @param string $numeroProcesso Número do processo (apenas dígitos).
     * @param bool   $sucesso        false quando a consulta ao DJEN falhou.
     * @return int|null ID do processo na carteira, se ele foi localizado.
     */
    public function registrarResultadoSincronizacao(int $id, string $numeroProcesso, bool $sucesso): ?int
    {
        $processoSalvo = $sucesso ? $this->processoService->processoJaExiste($numeroProcesso) : null;
        $idProcesso    = $processoSalvo ? (int) $processoSalvo['id_processo'] : null;

        $this->repository->update($id, [
            'data_tentativa'    => date('Y-m-d H:i:s'),
            'resultado'         => $sucesso ? 1 : 0,
            'id_processo_salvo' => $idProcesso,
        ]);

        return $idProcesso;
    }

    /**
     * Registra o resultado de uma consulta feita localmente (via navegador) para
     * um processo cadastrado na busca automática.
     *
     * @param int $id
     * @param string $numeroProcesso
     * @param array $items
     * @return array
     */
    public function registrarResultadoLocal(int $id, string $numeroProcesso, array $items): array
    {
        $processados = 0;

        foreach ($items as $item) {
            $resultado = $this->intimacoesService->processarItem($item);
            if (($resultado['status'] ?? 'error') !== 'error') {
                $processados++;
            }
        }

        $processoSalvo = $this->processoService->processoJaExiste($numeroProcesso);

        $this->repository->update($id, [
            'data_tentativa'    => date('Y-m-d H:i:s'),
            'resultado'         => 1,
            'id_processo_salvo' => $processoSalvo['id_processo'] ?? null,
        ]);

        return [
            'processados' => $processados,
            'total'       => count($items),
        ];
    }

    /**
     * Monta os parâmetros para a consulta de um processo.
     *
     * @param string $numeroProcesso
     * @return array
     */
    private function montarParametrosConsulta($numeroProcesso)
    {
        $hoje = date('Y-m-d', time());

        return [
            'numeroProcesso'                => $numeroProcesso,
            'dataDisponibilizacaoInicio'    => self::DATA_INICIO_CONSULTA,
            'dataDisponibilizacaoFim'       => $hoje,
        ];
    }
}
