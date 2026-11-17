<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientesModel;
use App\Models\DriveSyncJobModel;
use Exception;

/**
 * Regras da sincronização de pastas do Google Drive (clientes).
 *
 * Centraliza a fila `drive_sync_jobs` para ser usada tanto pelo comando
 * `php spark drive:sync` quanto pela tela de Configurações.
 *
 * @version 1.0.0
 * @since 2026-10-17
 */
class DriveSyncService
{
    public const TIPOS = ['cliente'];

    public const STATUS = ['pending', 'processing', 'completed', 'failed'];

    /** Minutos sem atualização para considerar um job "processing" travado. */
    public const MINUTOS_TRAVADO = 10;

    private DriveSyncJobModel $jobModel;
    private ClientesModel $clientesModel;
    private $db;

    public function __construct(
        ?DriveSyncJobModel $jobModel = null,
        ?ClientesModel $clientesModel = null
    ) {
        $this->jobModel       = $jobModel ?? new DriveSyncJobModel();
        $this->clientesModel  = $clientesModel ?? new ClientesModel();
        $this->db             = \Config\Database::connect();
    }

    // ------------------------------------------------------------------
    // Resumo
    // ------------------------------------------------------------------

    /**
     * Totais da fila e das entidades com/sem pasta.
     */
    public function resumo(): array
    {
        $fila = array_fill_keys(self::STATUS, 0);
        $linhas = $this->db->table('drive_sync_jobs')
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()->getResultArray();
        foreach ($linhas as $l) {
            $fila[$l['status']] = (int) $l['total'];
        }

        $travados = $this->db->table('drive_sync_jobs')
            ->where('status', 'processing')
            ->where('updated_at <', $this->limiteTravado())
            ->countAllResults();

        return [
            'configurado' => $this->pastaRaiz() !== '',
            'fila'        => $fila + ['travados' => $travados],
            'clientes'    => $this->contarEntidade('cliente'),
        ];
    }

    private function contarEntidade(string $tipo): array
    {
        [$tabela, $pk] = $this->tabelaDe($tipo);

        $total = $this->entidadesBase($tipo)->countAllResults();
        $semPasta = $this->entidadesBase($tipo)
            ->groupStart()->where("{$tabela}.pasta", null)->orWhere("{$tabela}.pasta", '')->groupEnd()
            ->countAllResults();

        $comJobPendente = $this->entidadesBase($tipo)
            ->groupStart()->where("{$tabela}.pasta", null)->orWhere("{$tabela}.pasta", '')->groupEnd()
            ->where("EXISTS (SELECT 1 FROM drive_sync_jobs j WHERE j.entity_type = '{$tipo}' AND j.entity_id = {$tabela}.{$pk} AND j.status IN ('pending','processing'))", null, false)
            ->countAllResults();

        return [
            'total'        => $total,
            'com_pasta'    => $total - $semPasta,
            'sem_pasta'    => $semPasta,
            'sem_job'      => $semPasta - $comJobPendente,
        ];
    }

    // ------------------------------------------------------------------
    // Listagens
    // ------------------------------------------------------------------

    /**
     * Lista paginada de jobs, já com o nome da entidade e o link da pasta.
     *
     * @param array{status?:string,tipo?:string,busca?:string} $filtros
     */
    public function listarJobs(array $filtros, int $pagina, int $porPagina): array
    {
        $porPagina = max(1, min(100, $porPagina));
        $pagina    = max(1, $pagina);

        $builder = $this->db->table('drive_sync_jobs j')
            ->select("j.id, j.entity_type, j.entity_id, j.status, j.error_message, j.created_at, j.updated_at,
                      c.nome AS entidade_nome, c.pasta AS pasta", false)
            ->join('clientes c', "c.id_cliente = j.entity_id AND j.entity_type = 'cliente'", 'left');

        if (!empty($filtros['status']) && in_array($filtros['status'], self::STATUS, true)) {
            $builder->where('j.status', $filtros['status']);
        }
        if (!empty($filtros['tipo']) && in_array($filtros['tipo'], self::TIPOS, true)) {
            $builder->where('j.entity_type', $filtros['tipo']);
        }
        if (!empty($filtros['busca'])) {
            $builder->groupStart()
                ->like('c.nome', $filtros['busca'])
                ->orLike('j.error_message', $filtros['busca'])
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $itens = $builder->orderBy('j.id', 'DESC')
            ->limit($porPagina, ($pagina - 1) * $porPagina)
            ->get()->getResultArray();

        return $this->paginado($itens, $total, $pagina, $porPagina);
    }

    /**
     * Lista paginada de clientes com a situação da pasta e do último job.
     *
     * @param array{situacao?:string,busca?:string} $filtros situacao: com_pasta|sem_pasta
     */
    public function listarEntidades(string $tipo, array $filtros, int $pagina, int $porPagina): array
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new Exception('Tipo de entidade inválido.');
        }
        $porPagina = max(1, min(100, $porPagina));
        $pagina    = max(1, $pagina);
        [$tabela, $pk] = $this->tabelaDe($tipo);

        $nome = "{$tabela}.nome";

        $builder = $this->entidadesBase($tipo)
            ->select("{$tabela}.{$pk} AS id, {$nome} AS nome, {$tabela}.pasta,
                      (SELECT j.status FROM drive_sync_jobs j WHERE j.entity_type = '{$tipo}' AND j.entity_id = {$tabela}.{$pk} ORDER BY j.id DESC LIMIT 1) AS ultimo_job_status,
                      (SELECT j.error_message FROM drive_sync_jobs j WHERE j.entity_type = '{$tipo}' AND j.entity_id = {$tabela}.{$pk} ORDER BY j.id DESC LIMIT 1) AS ultimo_job_erro", false);

        if (($filtros['situacao'] ?? '') === 'sem_pasta') {
            $builder->groupStart()->where("{$tabela}.pasta", null)->orWhere("{$tabela}.pasta", '')->groupEnd();
        } elseif (($filtros['situacao'] ?? '') === 'com_pasta') {
            $builder->where("{$tabela}.pasta IS NOT NULL", null, false)->where("{$tabela}.pasta !=", '');
        }

        if (!empty($filtros['busca'])) {
            $builder->like("{$tabela}.nome", $filtros['busca']);
        }

        $total = (clone $builder)->countAllResults(false);
        $itens = $builder->orderBy("{$tabela}.{$pk}", 'DESC')
            ->limit($porPagina, ($pagina - 1) * $porPagina)
            ->get()->getResultArray();

        return $this->paginado($itens, $total, $pagina, $porPagina);
    }

    // ------------------------------------------------------------------
    // Enfileiramento
    // ------------------------------------------------------------------

    /**
     * Enfileira todos os clientes que ainda não têm pasta.
     *
     * @return array{clientes:int}
     */
    public function enfileirarPendentes(): array
    {
        $resultado = ['clientes' => 0];

        foreach (self::TIPOS as $tipo) {
            [$tabela, $pk] = $this->tabelaDe($tipo);
            $ids = $this->entidadesBase($tipo)
                ->select("{$tabela}.{$pk} AS id")
                ->groupStart()->where("{$tabela}.pasta", null)->orWhere("{$tabela}.pasta", '')->groupEnd()
                ->get()->getResultArray();

            $chave = 'clientes';
            foreach ($ids as $linha) {
                if ($this->enfileirar($tipo, (int) $linha['id'])) {
                    $resultado[$chave]++;
                }
            }
        }

        return $resultado;
    }

    /**
     * Enfileira entidades específicas (inclusive as que já têm pasta, para
     * recriar/atualizar o vínculo). Ignora as que já têm job pendente.
     *
     * @param int[] $ids
     */
    public function enfileirarEntidades(string $tipo, array $ids): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new Exception('Tipo de entidade inválido.');
        }
        [$tabela, $pk] = $this->tabelaDe($tipo);

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }
        $existentes = array_column(
            $this->entidadesBase($tipo)->select("{$tabela}.{$pk} AS id")->whereIn("{$tabela}.{$pk}", $ids)->get()->getResultArray(),
            'id'
        );

        $criados = 0;
        foreach ($existentes as $id) {
            if ($this->enfileirar($tipo, (int) $id)) {
                $criados++;
            }
        }

        return $criados;
    }

    private function enfileirar(string $tipo, int $id): bool
    {
        $existe = $this->db->table('drive_sync_jobs')
            ->where('entity_type', $tipo)
            ->where('entity_id', $id)
            ->whereIn('status', ['pending', 'processing'])
            ->countAllResults() > 0;
        if ($existe) {
            return false;
        }

        $this->jobModel->insert(['entity_type' => $tipo, 'entity_id' => $id, 'status' => 'pending']);

        return true;
    }

    // ------------------------------------------------------------------
    // Gestão dos jobs
    // ------------------------------------------------------------------

    /**
     * Devolve jobs à fila (status pending). Aceita ids ou um status inteiro
     * (ex.: 'failed'). Jobs em processamento só voltam se estiverem travados.
     */
    public function reprocessar(array $ids = [], ?string $status = null): int
    {
        $builder = $this->db->table('drive_sync_jobs');
        $this->aplicarSelecao($builder, $ids, $status);
        $builder->whereIn('status', ['failed', 'completed', 'processing']);
        $builder->groupStart()
            ->where('status !=', 'processing')
            ->orGroupStart()->where('status', 'processing')->where('updated_at <', $this->limiteTravado())->groupEnd()
            ->groupEnd();

        $builder->set(['status' => 'pending', 'error_message' => null, 'updated_at' => date('Y-m-d H:i:s')])->update();

        return $this->db->affectedRows();
    }

    /**
     * Remove jobs da fila (nunca os que estão sendo processados agora).
     */
    public function excluir(array $ids = [], ?string $status = null): int
    {
        $builder = $this->db->table('drive_sync_jobs');
        $this->aplicarSelecao($builder, $ids, $status);
        $builder->groupStart()
            ->where('status !=', 'processing')
            ->orGroupStart()->where('status', 'processing')->where('updated_at <', $this->limiteTravado())->groupEnd()
            ->groupEnd();
        $builder->delete();

        return $this->db->affectedRows();
    }

    private function aplicarSelecao($builder, array $ids, ?string $status): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            $builder->whereIn('id', $ids);
        } elseif ($status !== null && in_array($status, self::STATUS, true)) {
            $builder->where('status', $status);
        } else {
            // Sem seleção explícita nada deve ser afetado.
            $builder->where('1 = 0', null, false);
        }
    }

    // ------------------------------------------------------------------
    // Processamento
    // ------------------------------------------------------------------

    /**
     * Processa até $limite jobs pendentes .
     *
     * @param callable|null $log function(string $mensagem, string $nivel): void
     * @return array{processados:int,sucesso:int,falhas:int,restantes:int,itens:array}
     * @throws Exception Quando a pasta raiz do Drive não está configurada.
     */
    public function processar(int $limite = 20, ?callable $log = null): array
    {
        $raiz = $this->pastaRaiz();
        if ($raiz === '') {
            throw new Exception('GOOGLE_DRIVE_FOLDER_ID não está configurado.');
        }

        $limite = max(1, min(100, $limite));
        $jobs = $this->jobModel->where('status', 'pending')
            ->orderBy('id', 'ASC')
            ->findAll($limite);

        $drive = new GoogleDriveService();
        $saida = ['processados' => 0, 'sucesso' => 0, 'falhas' => 0, 'restantes' => 0, 'itens' => []];

        foreach ($jobs as $job) {
            $saida['processados']++;
            $this->jobModel->update($job['id'], ['status' => 'processing']);

            try {
                $link = $this->processarJob($job, $drive, $raiz);
                $this->jobModel->update($job['id'], ['status' => 'completed', 'error_message' => null]);
                $saida['sucesso']++;
                $saida['itens'][] = ['id' => (int) $job['id'], 'status' => 'completed', 'pasta' => $link];
                if ($log) {
                    $log("Job {$job['id']} concluído.", 'green');
                }
            } catch (Exception $e) {
                $this->jobModel->update($job['id'], ['status' => 'failed', 'error_message' => $e->getMessage()]);
                $saida['falhas']++;
                $saida['itens'][] = ['id' => (int) $job['id'], 'status' => 'failed', 'erro' => $e->getMessage()];
                if ($log) {
                    $log("Erro no Job {$job['id']}: " . $e->getMessage(), 'red');
                }
            }
        }

        $saida['restantes'] = $this->jobModel->where('status', 'pending')->countAllResults();

        return $saida;
    }

    /**
     * Cria/vincula a pasta de um job e devolve o link.
     *
     * @throws Exception
     */
    private function processarJob(array $job, GoogleDriveService $drive, string $raiz): string
    {
        if ($job['entity_type'] === 'cliente') {
            $cliente = $this->clientesModel->find($job['entity_id']);
            if (!$cliente) {
                throw new Exception('Cliente não encontrado.');
            }

            $pasta = $drive->getOrCreateFolder($cliente['nome'] . ' - ' . $cliente['id_cliente'], $raiz);
            $this->clientesModel->update($cliente['id_cliente'], ['pasta' => $pasta['link']]);

            return $pasta['link'];
        }

        throw new Exception("Tipo de entidade desconhecido: {$job['entity_type']}");
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private function pastaRaiz(): string
    {
        return (string) (new ConfiguracoesService())->getValor('GOOGLE_DRIVE_FOLDER_ID', '');
    }

    private function limiteTravado(): string
    {
        return date('Y-m-d H:i:s', time() - self::MINUTOS_TRAVADO * 60);
    }

    /** @return array{0:string,1:string} [tabela, chave primária] */
    private function tabelaDe(string $tipo): array
    {
        return ['clientes', 'id_cliente'];
    }

    /** Builder base da entidade. */
    private function entidadesBase(string $tipo)
    {
        [$tabela] = $this->tabelaDe($tipo);

        return $this->db->table($tabela);
    }

    private function paginado(array $itens, int $total, int $pagina, int $porPagina): array
    {
        return [
            'itens'       => $itens,
            'total'       => $total,
            'pagina'      => $pagina,
            'por_pagina'  => $porPagina,
            'total_paginas' => (int) max(1, ceil($total / $porPagina)),
        ];
    }
}
