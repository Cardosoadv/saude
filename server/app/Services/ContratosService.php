<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContratosRepository;
use App\Traits\FormataValorTrait;
use Exception;
use CodeIgniter\Database\BaseConnection;

/**
 * Service para gerenciar regras de negócio de contratos.
 */
class ContratosService extends BaseService
{
    use FormataValorTrait;


    protected ContratosRepository $contratosRepository;
    protected BaseConnection $db;

    public function __construct(?ContratosRepository $repository = null)
    {
        $this->repository   = $repository ?? new ContratosRepository();
        $this->db           = \Config\Database::connect();
    }

    /**
     * @return array{contratos: array, pager: \CodeIgniter\Pager\Pager}
     */
    public function listar(array $filtros = [], int $perPage = 25): array
    {
        return $this->repository->buscarContratos($filtros, $perPage);
    }

    public function listarTodos(array $filtros = []): array
    {
        return $this->repository->buscarTodos($filtros);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function criar(array $dados): int
    {
        if (isset($dados['valor'])) {
            $dados['valor'] = $this->formatarValorParaBanco($dados['valor']);
        }

        if (!isset($dados['status'])) {
            $dados['status'] = 'Ativo';
        }

        $this->db->transStart();
        try {
            $id = $this->repository->create($dados);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception("Falha ao criar contrato (Erro de Transação).");
            }

            return $id;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function atualizar(int $id, array $dados): bool
    {
        if (isset($dados['valor'])) {
            $dados['valor'] = $this->formatarValorParaBanco($dados['valor']);
        }

        $this->db->transStart();
        try {
            $sucesso = $this->repository->update($id, $dados);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception("Falha ao atualizar contrato (Erro de Transação).");
            }

            return $sucesso;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function excluir(int $id): bool
    {
        $this->db->transStart();
        try {
            $sucesso = $this->repository->delete($id);
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception("Falha ao excluir contrato (Erro de Transação).");
            }

            return $sucesso;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function getEstatisticas(): array
    {
        $dados = $this->repository->getEstatisticas();

        return [
            'total'       => (int) $dados['total'],
            'ativos'      => (int) $dados['ativos'],
            'vencidos'    => (int) $dados['vencidos'],
            'valor_total' => floatval($dados['valor_total']),
        ];
    }

    public function getProximosVencimento(int $limit = 5): array
    {
        $contratos = $this->repository->getContratosProximosVencimento($limit);

        foreach ($contratos as &$contrato) {
            $dataFim = new \DateTime($contrato['data_fim']);
            $hoje = new \DateTime();
            $diff = $hoje->diff($dataFim);

            if ($dataFim < $hoje) {
                $contrato['dias_restantes'] = -$diff->days;
            } else {
                $contrato['dias_restantes'] = $diff->days;
            }

            $contrato['data_fim_formatada'] = $dataFim->format('d/m/Y');
        }

        return $contratos;
    }

    public function getContratosRecentes(int $limit = 5): array
    {
        $contratos = $this->repository->getContratosRecentes($limit);

        foreach ($contratos as &$contrato) {
            if (isset($contrato['data_inicio'])) {
                $contrato['data_formatada'] = date('d/m/Y', strtotime($contrato['data_inicio']));
            }
        }

        return $contratos;
    }

    public function buscarPorTermo(string $term): array
    {
        $resultados = $this->repository->buscarPorTermo($term);
        $data = [];
        foreach ($resultados as $row) {
            $objeto = $row['objeto'] ?? '';
            $objetoTruncado = mb_strlen($objeto) > 30 ? mb_substr($objeto, 0, 30) . '...' : $objeto;

            $data[] = [
                'id' => $row['id_contrato'],
                'text' => 'Contrato #' . $row['numero_contrato'] . ' - ' . $row['nome_cliente'],
                'nome_cliente' => $row['nome_cliente'],
                'numero_contrato' => $row['numero_contrato'],
                'objeto' => $objetoTruncado
            ];
        }
        return $data;
    }

    /**
     * @deprecated Use FinanceiroService::getMovimentacoesContrato with filters instead.
     */
    public function filtrarMovimentacoes(array $movimentacoes, array $filtros): array
    {
        return array_filter($movimentacoes, function ($mov) use ($filtros) {
            $passou = true;

            if (!empty($filtros['categoria']) && $mov['categoria'] !== $filtros['categoria']) {
                $passou = false;
            }

            if (!empty($filtros['data_inicio']) && $mov['data'] < $filtros['data_inicio']) {
                $passou = false;
            }

            if (!empty($filtros['data_fim']) && $mov['data'] > $filtros['data_fim']) {
                $passou = false;
            }

            if (!empty($filtros['pesquisa']) || !empty($filtros['search'])) {
                $termo                  = mb_strtolower($filtros['search'] ?? $filtros['pesquisa']);
                $descricao              = mb_strtolower($mov['descricao'] ?? '');
                $clienteFornecedor      = mb_strtolower($mov['cliente_fornecedor'] ?? '');
                $categoria              = mb_strtolower($mov['categoria'] ?? '');

                if (
                    strpos($descricao, $termo) === false
                    && strpos($clienteFornecedor, $termo) === false
                    && strpos($categoria, $termo) === false
                ) {
                    $passou = false;
                }
            }

            return $passou;
        });
    }

    public function gerarDadosCsv(array $contratos): array
    {
        $dadosCsv = [];
        // Header
        $dadosCsv[] = ['ID', 'Nº Contrato', 'Cliente', 'Data Início', 'Data Fim', 'Valor (R$)', 'Status', 'Observações'];

        foreach ($contratos as $c) {
            $dadosCsv[] = [
                $c['id_contrato'],
                $c['numero_contrato'],
                $c['cliente_nome'],
                date('d/m/Y', strtotime($c['data_inicio'])),
                $c['data_fim'] ? date('d/m/Y', strtotime($c['data_fim'])) : '-',
                number_format((float) $c['valor'], 2, ',', '.'),
                ucfirst($c['status']),
                $c['comentarios'],
            ];
        }
        return $dadosCsv;
    }

    /**
     * Obtém dados simplificados do contrato (Número e Nome do Cliente).
     * Útil para exibição em listagens e detalhes de outros módulos (Financeiro, etc).
     *
     * @param int $id ID do contrato
     * @return array|null
     */
    public function getDadosContrato(int $id): ?array
    {
        return $this->repository->getDadosContrato($id);
    }

    public function getContratosMaisRentaveis(int $limit = 5): array
    {
        $contratos = $this->repository->getContratosRentabilidade('rentaveis', $limit);
        return $this->formatarSaldosRentabilidade($contratos);
    }

    public function getContratosMaioresPrejuizos(int $limit = 5): array
    {
        $contratos = $this->repository->getContratosRentabilidade('prejuizos', $limit);
        return $this->formatarSaldosRentabilidade($contratos);
    }

    private function formatarSaldosRentabilidade(array $contratos): array
    {
        foreach ($contratos as &$contrato) {
            $contrato['saldo_formatado'] = 'R$ ' . number_format((float) $contrato['saldo'], 2, ',', '.');
        }
        return $contratos;
    }
}
