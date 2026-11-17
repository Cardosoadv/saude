<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroExtratosImportacoesModel;
use App\Repositories\BaseRepository;

/**
 * Repositório das importações de extrato (um envio de arquivo por linha).
 */
class ExtratosImportacoesRepository extends BaseRepository
{
    protected $cacheKey = 'fin_extratos_importacoes';

    public function __construct(?FinanceiroExtratosImportacoesModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new FinanceiroExtratosImportacoesModel();
    }

    /**
     * Importações anteriores da mesma conta com o mesmo arquivo (hash do
     * conteúdo). Serve apenas para o aviso informativo "este arquivo já foi
     * enviado em ..."; não substitui a checagem por movimento.
     */
    public function buscarPorHash(int $contaId, string $arquivoHash): array
    {
        return $this->model
            ->where('conta_id', $contaId)
            ->where('arquivo_hash', $arquivoHash)
            ->orderBy('id_importacao', 'DESC')
            ->findAll();
    }

    /**
     * Lista as importações de uma conta, da mais recente para a mais antiga.
     */
    public function listarPorConta(int $contaId, int $limite = 50): array
    {
        return $this->model
            ->where('conta_id', $contaId)
            ->orderBy('id_importacao', 'DESC')
            ->findAll($limite);
    }

    /**
     * Importação concluída com o saldo mais recente (LEDGERBAL) dentro do intervalo, ou null.
     * Arquivos importados antes de a data do saldo ser guardada não entram.
     */
    public function ultimoSaldoNoPeriodo(int $contaId, string $dataInicio, string $dataFim): ?array
    {
        $this->model->resetQuery();

        return $this->model
            ->where('conta_id', $contaId)
            ->where('status', 'concluida')
            ->where('saldo_final_arquivo IS NOT NULL', null, false)
            ->where('saldo_final_data >=', $dataInicio)
            ->where('saldo_final_data <=', $dataFim)
            ->orderBy('saldo_final_data', 'DESC')
            ->orderBy('id_importacao', 'DESC')
            ->first() ?: null;
    }

    /** Importações concluídas da conta (para saber se o período do arquivo já está coberto). */
    public function listarConcluidasPorConta(int $contaId): array
    {
        return $this->model
            ->where('conta_id', $contaId)
            ->where('status', 'concluida')
            ->findAll();
    }

    /**
     * Histórico paginado, da mais recente para a mais antiga.
     *
     * @return array{itens:array,total:int}
     */
    public function pesquisar(?int $contaId, int $pagina = 1, int $porPagina = 20): array
    {
        $pagina    = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));

        $filtrar = function () use ($contaId) {
            $this->model->resetQuery();
            if ($contaId) {
                $this->model->where('conta_id', $contaId);
            }

            return $this->model;
        };

        $total = $filtrar()->countAllResults(false);
        $itens = $filtrar()->orderBy('id_importacao', 'DESC')->findAll($porPagina, ($pagina - 1) * $porPagina);

        return ['itens' => $itens, 'total' => (int) $total];
    }

    /**
     * Lock por conta durante a importação (GET_LOCK do MySQL). Em outros bancos
     * (ex.: SQLite dos testes) não há lock e devolve true.
     */
    public function obterLockConta(int $contaId, int $segundosEspera = 15): bool
    {
        $db = $this->model->db;
        if (!$this->suportaLock($db)) {
            return true;
        }

        $linha = $db->query('SELECT GET_LOCK(?, ?) AS ok', [self::nomeLock($contaId), $segundosEspera])->getRowArray();

        return (int) ($linha['ok'] ?? 0) === 1;
    }

    public function liberarLockConta(int $contaId): void
    {
        $db = $this->model->db;
        if ($this->suportaLock($db)) {
            $db->query('SELECT RELEASE_LOCK(?)', [self::nomeLock($contaId)]);
        }
    }

    private static function nomeLock(int $contaId): string
    {
        return 'fin_extrato_importacao_conta_' . $contaId;
    }

    private function suportaLock(object $db): bool
    {
        return ($db->DBDriver ?? '') === 'MySQLi';
    }
}
