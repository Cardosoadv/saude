<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\ContasService;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Traits\FormataValorTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class PagamentoDespesas extends BaseController
{
    use ResponseTrait;
    use FormataValorTrait;
    use VerificaPermissoesTrait;

    protected PagamentoDespesasService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?PagamentoDespesasService $service = null)
    {
        $this->service = $service ?? new PagamentoDespesasService();
    }

    /**
     * GET /api/financeiro/pagamento-despesas
     */
    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão'
            ], 403);
        }

        try {
            $filters = [
                'search'     => $this->request->getGet('search'),
                'periodo'    => $this->request->getGet('periodo'),
                'conta_id'   => $this->request->getGet('conta'),
                'sort'       => $this->request->getGet('sort'),
                'data_inicio' => $this->request->getGet('data_inicio'),
                'data_fim'    => $this->request->getGet('data_fim'),
                'categoria'   => $this->request->getGet('categoria'),
                'sem_comprovante' => filter_var($this->request->getGet('sem_comprovante'), FILTER_VALIDATE_BOOLEAN),
            ];

            log_message('info', '[Financeiro\PagamentoDespesas::listar] ' . json_encode($filters));

            $perPage = (int) ($this->request->getGet('perPage') ?? 25);
            $page = (int) ($this->request->getGet('page') ?? 1);
            $result = $this->service->listarComFiltros($filters, $perPage, $page);

            return $this->respond([
                'success' => true,
                'data'    => $result['data'],
                'pager'   => $result['pager']
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::listar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao listar pagamentos.'
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-despesas/detalhes/(:num)
     */
    public function detalhar(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão'
            ], 403);
        }

        try {
            $pagamento = $this->service->getById($id);

            if (!$pagamento) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Pagamento não encontrado'
                ], 404);
            }

            // Decode rateio
            if (isset($pagamento['rateio']) && is_string($pagamento['rateio'])) {
                $pagamento['rateio'] = json_decode($pagamento['rateio'], true) ?? [];
            }

            return $this->respond([
                'success' => true,
                'data'    => $pagamento
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::detalhar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar pagamento.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-despesas/salvar
     */
    public function salvar(): ResponseInterface
    {
        $data = $this->getRequestData();
        
        $id = $data['id_pgto_despesa'] ?? null;
        $id = is_numeric($id) ? (int)$id : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão'
            ], 403);
        }

        try {

            if (isset($data['rateio']) && is_array($data['rateio'])) {
                $data['rateio'] = json_encode(array_filter($data['rateio'], function ($item) {
                    return !empty($item['id']) && (!empty($item['valor']) || $item['valor'] === '0');
                }));
            }

            if(isset($data['contrato_id']) && (empty($data['contrato_id']) || $data['contrato_id'] === '0' || $data['contrato_id'] === '')) {
                $data['contrato_id'] = null;
            };

            if ($id === null) {
                $newId = $this->service->create($data);
                $message = 'Pagamento registrado com sucesso';
            } else {
                $this->service->update($id, $data);
                $newId = $id;
                $message = 'Pagamento atualizado com sucesso';
            }

            // Lógica de Reembolso
            if (!empty($data['cliente_reembolso']) && isset($data['despesa_id'])) {
                $this->service->lancarReembolso((int)$data['despesa_id'], (int)$data['cliente_reembolso']);
            }

            return $this->respond([
                'success' => true,
                'message' => $message,
                'id'      => $newId
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::salvar] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao salvar pagamento.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-despesas/estatisticas
     * Retorna estatísticas dos pagamentos
     */
    public function estatisticas(): ResponseInterface
    {
        // Verificar permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $estatisticas = $this->service->getEstatisticasMes();

            return $this->respond(array_merge(['success' => true], $estatisticas));
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::estatisticas] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas.'
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-despesas/resumo-por-conta
     * Retorna resumo de pagamentos por conta bancária
     */
    public function resumoPorConta(): ResponseInterface
    {
        // Verificar permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $resultado = $this->service->getResumoPorConta();
            return $this->respond($resultado);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::resumoPorConta] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar resumo.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-despesas/estornar/{id}
     * Estorna um pagamento
     */
    public function estornar($id = null): ResponseInterface
    {
        // Verificar permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para estornar pagamentos'
            ], 403);
        }

        try {
            if (!$id) {
                return $this->respond([
                    'success' => false,
                    'message' => 'ID do pagamento não informado'
                ], 400);
            }

            $pagamento = $this->service->getById($id);

            if (!$pagamento) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Pagamento não encontrado'
                ], 404);
            }

            // Excluir o pagamento (soft delete)
            $this->service->delete($id);

            return $this->respond([
                'success' => true,
                'message' => 'Pagamento estornado com sucesso'
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::estornar] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao estornar pagamento.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-despesas/excluir-lote
     * Exclui múltiplos pagamentos
     */
    public function excluirEmLote(): ResponseInterface
    {
        // Verificar permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para excluir pagamentos'
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhum pagamento selecionado'
                ], 400);
            }

            $deleted = $this->service->deleteBatch($ids);

            return $this->respond([
                'success' => true,
                'message' => "{$deleted} pagamento(s) excluído(s) com sucesso"
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::excluirEmLote] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir pagamentos.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-despesas/exportar
     * Exporta pagamentos em CSV (sem filtros - todos os registros)
     * Nota: Para exportação com filtros, use PagamentoDespesas::exportar()
     */
    public function exportar(): ResponseInterface
    {
        // Verificar permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $pagamentos = $this->service->listarParaExportacao([]); // Empty filters for all

            return $this->gerarCsv($pagamentos);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::exportar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao exportar pagamentos.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-despesas/acao-lote
     * Executa ações em lote nos pagamentos
     */
    public function acaoEmLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para ações em lote'
            ], 403);
        }

        try {
            $acao = $this->request->getPost('acao');
            $ids = $this->request->getPost('pagamentos');

            if (empty($acao) || empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhuma ação ou pagamento selecionado'
                ], 400);
            }

            switch ($acao) {
                case 'estornar':
                    $successCount = $this->service->deleteBatch($ids);
                    return $this->respond([
                        'success' => true,
                        'message' => "{$successCount} pagamento(s) estornado(s) com sucesso"
                    ]);

                case 'exportar':
                    $pagamentos = $this->service->getRepository()->findByIds($ids);
                    return $this->gerarCsv($pagamentos, 'pagamentos_selecionados');

                default:
                    return $this->respond([
                        'success' => false,
                        'message' => 'Ação não suportada'
                    ], 400);
            }
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoDespesas::acaoEmLote] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao processar ação em lote.'
            ], 500);
        }
    }

    /**
     * Helper para gerar CSV de pagamentos
     */
    private function gerarCsv(array $pagamentos, string $filename = 'pagamentos'): ResponseInterface
    {
        // Add BOM for Excel utf-8 compatibility
        $csv = "\xEF\xBB\xBF";
        $csv .= "Data Pagamento;Despesa;Fornecedor;Conta;Valor;Observações\n";

        foreach ($pagamentos as $pagamento) {
            $csv .= sprintf(
                "%s;%s;%s;%s;R$ %s;%s\n",
                date('d/m/Y', strtotime($pagamento['pagamento_despesa_dt'])),
                $pagamento['despesa'] ?? 'N/A',
                $pagamento['fornecedor_nome'] ?? $pagamento['fornecedor'] ?? 'N/A',
                $pagamento['conta_nome'] ?? $pagamento['conta'] ?? 'N/A',
                number_format((float)$pagamento['valor'], 2, ',', '.'),
                $pagamento['observacoes'] ?? ''
            );
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '_' . date('Y-m-d_H-i-s') . '.csv"')
            ->setBody($csv);
    }
}
