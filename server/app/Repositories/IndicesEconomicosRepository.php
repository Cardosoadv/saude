<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\IndicesEconomicosModel;
use App\Models\IndicesImportacoesModel;
use App\Models\IndicesValoresModel;

class IndicesEconomicosRepository extends BaseRepository
{
    protected IndicesValoresModel $valoresModel;
    protected IndicesImportacoesModel $importacoesModel;

    public function __construct(
        ?IndicesEconomicosModel $model = null,
        ?IndicesValoresModel $valoresModel = null,
        ?IndicesImportacoesModel $importacoesModel = null
    ) {
        parent::__construct();
        $this->model            = $model ?? new IndicesEconomicosModel();
        $this->valoresModel     = $valoresModel ?? new IndicesValoresModel();
        $this->importacoesModel = $importacoesModel ?? new IndicesImportacoesModel();
        $this->cacheKey         = 'indices_economicos';
    }

    public function porCodigo(string $codigo): ?array
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    /** Índices com a última competência importada e o resultado da última importação. */
    public function listarComResumo(): array
    {
        $indices = $this->model->orderBy('codigo')->findAll();

        foreach ($indices as &$indice) {
            $resumo = $this->valoresModel->selectMin('competencia', 'primeira')->selectMax('competencia', 'ultima')
                ->selectCount('id_valor', 'total')->where('indice_id', $indice['id_indice'])->first();
            $importacao = $this->importacoesModel->where('indice_id', $indice['id_indice'])
                ->orderBy('executado_em', 'DESC')->orderBy('id_importacao', 'DESC')->first();

            $indice['primeira_competencia'] = $resumo['primeira'] ?? null;
            $indice['ultima_competencia']   = $resumo['ultima'] ?? null;
            $indice['total_valores']        = (int) ($resumo['total'] ?? 0);
            $indice['ultima_importacao']    = $importacao;
        }

        return $indices;
    }

    /** @return array<string, string> competencia (AAAA-MM-DD) => valor, ordenado */
    public function valores(int $indiceId, ?string $de = null, ?string $ate = null): array
    {
        $consulta = $this->valoresModel->where('indice_id', $indiceId)->orderBy('competencia');
        if ($de !== null) {
            $consulta->where('competencia >=', $de);
        }
        if ($ate !== null) {
            $consulta->where('competencia <=', $ate);
        }

        $mapa = [];
        foreach ($consulta->findAll() as $linha) {
            $mapa[$linha['competencia']] = (string) $linha['valor'];
        }

        return $mapa;
    }

    public function ultimaCompetencia(int $indiceId): ?string
    {
        $linha = $this->valoresModel->selectMax('competencia', 'ultima')->where('indice_id', $indiceId)->first();

        return $linha['ultima'] ?? null;
    }

    /**
     * Grava os valores (insere os novos e atualiza os que mudaram).
     *
     * @param list<array{competencia: string, valor: string}> $linhas
     *
     * @return array{novos: int, atualizados: int}
     */
    public function gravarValores(int $indiceId, array $linhas, string $origem): array
    {
        if ($linhas === []) {
            return ['novos' => 0, 'atualizados' => 0];
        }

        $datas     = array_column($linhas, 'competencia');
        $existente = [];
        foreach ($this->valoresModel->where('indice_id', $indiceId)
            ->where('competencia >=', min($datas))->where('competencia <=', max($datas))->findAll() as $linha) {
            $existente[$linha['competencia']] = $linha;
        }

        $agora = date('Y-m-d H:i:s');
        $novos = [];
        $atualizados = 0;
        foreach ($linhas as $linha) {
            $atual = $existente[$linha['competencia']] ?? null;
            if ($atual === null) {
                $novos[] = [
                    'indice_id' => $indiceId, 'competencia' => $linha['competencia'],
                    'valor' => $linha['valor'], 'origem' => $origem, 'importado_em' => $agora,
                ];
            } elseif (bccomp((string) $atual['valor'], $linha['valor'], 8) !== 0) {
                $this->valoresModel->update($atual['id_valor'], ['valor' => $linha['valor'], 'origem' => $origem, 'importado_em' => $agora]);
                $atualizados++;
            }
        }

        if ($novos !== []) {
            $this->valoresModel->insertBatch($novos);
        }
        if ($novos !== [] || $atualizados > 0) {
            $this->clearCache();
        }

        return ['novos' => count($novos), 'atualizados' => $atualizados];
    }

    public function registrarImportacao(int $indiceId, string $status, int $novos, ?string $mensagem = null): void
    {
        $this->importacoesModel->insert([
            'indice_id'     => $indiceId,
            'executado_em'  => date('Y-m-d H:i:s'),
            'status'        => $status,
            'novos_valores' => $novos,
            'mensagem'      => $mensagem,
        ]);
    }

    public function getModelValores(): IndicesValoresModel
    {
        return $this->valoresModel;
    }
}
