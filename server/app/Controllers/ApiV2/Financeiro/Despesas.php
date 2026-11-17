<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\DespesasService;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Services\ContratosService;
use App\Traits\FormataValorTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Despesas extends BaseController
{
    use ResponseTrait;
    use FormataValorTrait;
    use VerificaPermissoesTrait;

    protected DespesasService $service;
    protected ContratosService $contratosService;
    protected PagamentoDespesasService $pagtoService;
    private string $permissaoNecessaria = 'financeiro';

    public function __construct(
        ?DespesasService $service = null,
        ?ContratosService $contratosService = null,
        ?PagamentoDespesasService $pagtoService = null
    ) {
        $this->service = $service ?? new DespesasService();
        $this->contratosService = $contratosService ?? new ContratosService();
        $this->pagtoService = $pagtoService ?? new PagamentoDespesasService();
    }

    /**
     * GET api/v2/financeiro/despesas
     * Retorna listagem paginada de despesas não pagas
     */
    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão',
            ], 403);
        }

        try {
            $filtros = [
                'search'      => $this->request->getGet('search'),
                'sort'        => $this->request->getGet('sort'),
                'data_inicio' => $this->request->getGet('data_inicio'),
                'data_fim'    => $this->request->getGet('data_fim'),
                'fornecedor'  => $this->request->getGet('fornecedor'),
                'filtro'      => $this->request->getGet('filtro'),
            ];

            $perPage = 10;
            $page    = (int) ($this->request->getGet('page') ?? 1);
            $offset  = ($page - 1) * $perPage;

            $despesas = $this->service->listarDespesasNaoPagas($filtros, $perPage, $offset);
            $total    = $this->service->contarDespesasNaoPagas($filtros);

            return $this->respond([
                'success' => true,
                'data'    => $despesas,
                'pager'   => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::listar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao listar despesas.',
            ], 500);
        }
    }

    /**
     * GET api/v2/financeiro/despesas/detalhes/(:num)
     */
    public function detalhar(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão',
            ], 403);
        }

        try {
            $despesa = $this->service->find($id);

            if (!$despesa) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Despesa não encontrada',
                ], 404);
            }

            if (!empty($despesa['contrato_id'])) {
                $contrato = $this->contratosService->getDadosContrato((int)$despesa['contrato_id']);
                if ($contrato) {
                    $despesa['contrato_numero'] = $contrato['numero_contrato'];
                    $despesa['contrato_nome'] = $contrato['nome_cliente'];
                }
            }

            $despesa['pagamentos'] = $this->pagtoService->findByDespesaId($id);

            return $this->respond([
                'success' => true,
                'data'    => $despesa,
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::detalhar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar despesa.',
            ], 500);
        }
    }

    /**
     * DELETE /financeiro/despesas/excluir/(:num)
     */
    public function excluir(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir despesas',
            ], 403);
        }

        try {
            $this->service->delete($id);

            return $this->respond([
                'success' => true,
                'message' => 'Despesa excluída com sucesso',
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::excluir] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir despesa.';
            return $this->respond([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/despesas/salvar
     */
    public function salvar(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $id = $data['id_despesa'] ?? null;
        $id = is_numeric($id) ? (int)$id : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão',
            ], 403);
        }

        try {
            // Normalizar valor se vier string raw
            $valorRaw = $data['valor'] ?? null;
            $data['valor'] = is_string($valorRaw) ? $this->formatarValorParaBanco($valorRaw) : $valorRaw;

            // Normalizar campos de FK nullable — string vazia deve ser NULL
            if (array_key_exists('contrato_id', $data)) {
                $data['contrato_id'] = !empty($data['contrato_id']) ? (int) $data['contrato_id'] : null;
            }

            if ($id === null) {
                $newId = $this->service->create($data);
                $message = 'Despesa criada com sucesso';

                // Tratamento de Pagamento Imediato
                if (($data['pagarDespesa'] ?? 0) == 1) {
                    if (!empty($data['conta_id']) && !empty($data['pagamento_dt'])) {
                        $pagtoData = [
                            'despesa_id' => $newId,
                            'conta_id'   => $data['conta_id'],
                            'valor'      => $data['valor'],
                            'pagamento_despesa_dt' => $data['pagamento_dt'],
                            'observacoes' => $data['comentario'] ?? '',
                            'rateio'      => $data['rateio'] ?? null
                        ];
                        $this->pagtoService->create($pagtoData);
                        $message .= ' e pagamento registrado';
                    }
                }
            } else {
                $this->service->update($id, $data);
                $newId = $id;

                // Tratamento de Pagamento Imediato na edição
                if (($data['pagarDespesa'] ?? 0) == 1) {
                    if (!empty($data['conta_id']) && !empty($data['pagamento_dt'])) {
                        $pagtoData = [
                            'despesa_id' => $newId,
                            'conta_id'   => $data['conta_id'],
                            'valor'      => $data['valor'],
                            'pagamento_despesa_dt' => $data['pagamento_dt'],
                            'observacoes' => $data['comentario'] ?? '',
                            'rateio'      => $data['rateio'] ?? null
                        ];
                        $this->pagtoService->create($pagtoData);
                        $message = 'Despesa atualizada e pagamento registrado com sucesso';
                    } else {
                        $message = 'Despesa atualizada com sucesso';
                    }
                } else {
                    $message = 'Despesa atualizada com sucesso';
                }
            }

            return $this->respond([
                'success' => true,
                'message' => $message,
                'id'      => $newId
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::salvar] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao salvar despesa.';
            return $this->respond([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/despesas/resumo
     * Retorna resumo das despesas a pagar
     */
    public function resumo(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso',
            ], 403);
        }

        try {
            // Otimizado: Delega a lógica de agregação para o banco de dados
            $stats = $this->service->getResumoEstatisticas();

            return $this->respond([
                'success'        => true,
                'total_vencidas' => $stats['total_vencidas'],
                'valor_vencidas' => $stats['valor_vencidas'],
                'vence_hoje'     => $stats['vence_hoje'],
                'vence_semana'   => $stats['vence_semana'],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::resumo] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar resumo.',
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/despesas/ultimas-pagas
     * Retorna as últimas 5 despesas pagas
     */
    public function ultimasPagas(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso',
            ], 403);
        }

        try {
            $despesas = $this->service->listarUltimasPagas(5);

            $resultado = [];
            foreach ($despesas as $despesa) {
                $resultado[] = [
                    'despesa' => $despesa['despesa'],
                    'valor_formatado' => number_format($despesa['valor'], 2, ',', '.'),
                    'data_pagamento' => date('d/m/Y', strtotime($despesa['pagamento_despesa_dt'])),
                ];
            }

            return $this->respond($resultado);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::ultimasPagas] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar despesas pagas.',
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/despesas/excluir-lote
     * Exclui múltiplas despesas
     */
    public function excluirEmLote(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir despesas',
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhuma despesa selecionada',
                ], 400);
            }

            $deleted = $this->service->excluirEmLote($ids);

            return $this->respond([
                'success' => true,
                'message' => "{$deleted} despesa(s) excluída(s) com sucesso",
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::excluirEmLote] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao excluir despesas.',
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/despesas/exportar
     * Exporta despesas em CSV
     */
    public function exportar(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso',
            ], 403);
        }

        try {
            $despesas = $this->service->listarDespesasNaoPagas();

            $csv = "ID;Despesa;Valor;Vencimento;Fornecedor\n";
            foreach ($despesas as $despesa) {
                $csv .= sprintf(
                    "%d;%s;R$ %s;%s;%s\n",
                    $despesa['id_despesa'],
                    $despesa['despesa'],
                    number_format($despesa['valor'], 2, ',', '.'),
                    date('d/m/Y', strtotime($despesa['vencimento_dt'])),
                    $despesa['cliente_fornecedor'] ?? ''
                );
            }

            return $this->response
                ->setHeader('Content-Type', 'text/csv')
                ->setHeader('Content-Disposition', 'attachment; filename="despesas_' . date('Y-m-d') . '.csv"')
                ->setBody($csv);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::exportar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao exportar despesas.',
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/despesas/acao-lote
     * Executa ações em lote nas despesas
     */
    public function acaoEmLote(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para executar ações em lote',
            ], 403);
        }

        try {
            $acao = $this->request->getPost('acao');
            $despesas = $this->request->getPost('despesas');

            if (empty($acao) || empty($despesas)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Ação ou despesas não informadas',
                ], 400);
            }

            $count = 0;

            switch ($acao) {
                case 'pagar':
                    // Redirecionar para pagamento múltiplo
                    return redirect()->to(base_url('financeiro/pagamentoDespesas/pagar-multiplas?ids=' . implode(',', $despesas)));

                case 'exportar':
                    // Exportar apenas as selecionadas
                    return $this->exportarSelecionadas($despesas);

                default:
                    return $this->respond([
                        'success' => false,
                        'message' => 'Ação não reconhecida',
                    ], 400);
            }
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Despesas::acaoEmLote] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao executar ação.',
            ], 500);
        }
    }

    /**
     * Exporta apenas despesas selecionadas
     */
    private function exportarSelecionadas(array $ids): ResponseInterface
    {
        $despesas = $this->service->listarPorIds($ids);

        $csv = "ID;Despesa;Valor;Vencimento;Fornecedor\n";
        foreach ($despesas as $despesa) {
            $csv .= sprintf(
                "%d;%s;R$ %s;%s;%s\n",
                $despesa['id_despesa'],
                $despesa['despesa'],
                number_format($despesa['valor'], 2, ',', '.'),
                date('d/m/Y', strtotime($despesa['vencimento_dt'])),
                $despesa['cliente_fornecedor'] ?? ''
            );
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="despesas_selecionadas_' . date('Y-m-d') . '.csv"')
            ->setBody($csv);
    }
}
