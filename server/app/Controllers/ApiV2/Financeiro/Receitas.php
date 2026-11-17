<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\BaseService;
use App\Services\Financeiro\PagamentoReceitasService;
use App\Services\Financeiro\ReceitasService;
use App\Traits\FormataValorTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Receitas extends BaseController
{
    use ResponseTrait;
    use FormataValorTrait;
    use VerificaPermissoesTrait;

    protected ReceitasService $service;
    protected PagamentoReceitasService $pagamentoReceitasService;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?ReceitasService $service = null, ?PagamentoReceitasService $pagamentoReceitasService = null)
    {
        $this->service = $service ?? new ReceitasService();
        $this->pagamentoReceitasService = $pagamentoReceitasService ?? new PagamentoReceitasService();
    }

    /**
     * GET /financeiro/receitas/resumo
     * Retorna resumo das receitas a receber
     */
    public function resumo(): ResponseInterface
    {
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
                'success'         => true,
                'total_atrasadas' => $stats['total_atrasadas'],
                'valor_receber'   => $stats['valor_receber'],
                'vence_hoje'      => $stats['vence_hoje'],
                'vence_semana'    => $stats['vence_semana'],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::resumo] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar resumo.',
            ], 500);
        }
    }

    /**
     * GET /financeiro/receitas/ultimas-recebidas
     * Retorna as últimas 5 receitas recebidas
     */
    public function ultimasRecebidas(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso',
            ], 403);
        }

        try {
            // Usa PagamentoReceitasService para obter os últimos recebimentos (pagamentos de receitas)
            $receitas = $this->pagamentoReceitasService->getUltimosRecebimentos();

            $resultado = [];
            foreach ($receitas as $receita) {
                $resultado[] = [
                    'receita' => $receita['receita'],
                    'valor_formatado' => number_format((float) $receita['valor'], 2, ',', '.'),
                    'data_recebimento' => date('d/m/Y', strtotime($receita['pagamento_receita_dt'])),
                ];
            }

            return $this->respond($resultado);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::ultimasRecebidas] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar receitas recebidas.',
            ], 500);
        }
    }

    /**
     * POST /financeiro/receitas/excluirEmLote
     * Exclui múltiplas receitas
     */
    public function excluirEmLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir receitas',
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhuma receita selecionada',
                ], 400);
            }

            $deleted = 0;
            foreach ($ids as $id) {
                try {
                    $this->service->delete((int) $id);
                    $deleted++;
                } catch (\Exception $e) {
                    // Ignore or log
                }
            }

            return $this->respond([
                'success' => true,
                'message' => "{$deleted} receita(s) excluída(s) com sucesso",
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::excluirEmLote] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir receitas.';
            return $this->respond([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * GET /financeiro/receitas/exportar
     * Exporta receitas em CSV
     */
    public function exportar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso',
            ], 403);
        }

        try {
            $receitas = $this->service->listarReceitasNaoRecebidas();

            $csv = "ID;Receita;Valor;Vencimento;Cliente\n";
            foreach ($receitas as $receita) {
                $csv .= sprintf(
                    "%d;%s;R$ %s;%s;%s\n",
                    $receita['id_receita'],
                    $receita['receita'],
                    number_format((float) $receita['valor'], 2, ',', '.'),
                    date('d/m/Y', strtotime($receita['vencimento_dt'])),
                    $receita['cliente'] ?? '' // Ensure key matches Repo result
                );
            }

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="receitas_' . date('Y-m-d') . '.csv"')
                ->setBody("\xEF\xBB\xBF" . $csv); // BOM para UTF-8

        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::exportar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao exportar receitas.',
            ], 500);
        }
    }

    /**
     * POST /financeiro/receitas/acaoEmLote
     * Executa ações em lote nas receitas
     */
    public function acaoEmLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para executar ações em lote',
            ], 403);
        }

        try {
            $acao = $this->request->getPost('acao');
            $receitas = $this->request->getPost('receitas');

            if (empty($acao) || empty($receitas)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Ação ou receitas não informadas',
                ], 400);
            }

            switch ($acao) {
                case 'receber':
                    return redirect()->to(base_url('financeiro/pagamentoReceitas/receber-multiplas?ids=' . implode(',', $receitas)));

                case 'exportar':
                    return $this->exportarSelecionadas($receitas);

                default:
                    return $this->respond([
                        'success' => false,
                        'message' => 'Ação não reconhecida',
                    ], 400);
            }
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::acaoEmLote] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao executar ação.',
            ], 500);
        }
    }

    /**
     * GET /financeiro/receitas/listar
     * Retorna listagem paginada de receitas não recebidas
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
                'cliente_id'  => $this->request->getGet('cliente_id'),
                'filtro'      => $this->request->getGet('filtro'),
            ];

            // Tratamento especial para filtro rápido "vencidas"
            if ($filtros['filtro'] === 'vencidas') {
                $filtros['vencidas'] = true;
            }

            $perPage = 10;
            $page    = (int) ($this->request->getGet('page') ?? 1);
            $offset  = ($page - 1) * $perPage;

            $receitas = $this->service->listarReceitasNaoRecebidas($filtros, $perPage, $offset);
            $total    = $this->service->contarReceitasNaoRecebidas($filtros);

            return $this->respond([
                'success' => true,
                'data'    => $receitas,
                'pager'   => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::listar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao listar receitas.',
            ], 500);
        }
    }

    /**
     * GET /financeiro/receitas/detalhes/(:num)
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
            $receita = $this->service->find($id);

            $receita['recebimento'] = $this->pagamentoReceitasService->findByReceitaId($id);

            if (!$receita) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Receita não encontrada',
                ], 404);
            }

            return $this->respond([
                'success' => true,
                'data'    => $receita,
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::detalhar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar receita.',
            ], 500);
        }
    }

    /**
     * DELETE /financeiro/receitas/excluir/(:num)
     */
    public function excluir(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir receitas',
            ], 403);
        }

        try {
            $this->service->delete($id);

            return $this->respond([
                'success' => true,
                'message' => 'Receita excluída com sucesso',
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Receitas::excluir] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir receita.';
            return $this->respond([
                'success' => false,
                'message' => $msg,
            ], 500);
        }
    }

    /**
     * POST /financeiro/receitas/salvar
     */
    public function salvar(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $id = $data['id_receita'] ?? null;
        $id = is_numeric($id) ? (int)$id : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão',
            ], 403);
        }

        try {
            // Normalizar campos de FK nullable — string vazia deve ser NULL
            if (array_key_exists('contrato_id', $data)) {
                $data['contrato_id'] = !empty($data['contrato_id']) ? (int) $data['contrato_id'] : null;
            }

            if ($id === null) {
                if (($data['receberReceita'] ?? 0) == 1) {
                    $pagamentoData = [
                        'conta_id'             => $data['conta_id'] ?? null,
                        'pagamento_receita_dt' => $data['recebimento_dt'] ?? $data['pagamento_dt'] ?? null,
                        'rateio'               => $data['rateio'] ?? [],
                        'comentario'           => $data['comentario'] ?? null,
                    ];

                    $newId = $this->service->createWithPayment($data, $pagamentoData);
                    $message = 'Receita salva e recebimento registrado com sucesso';
                } else {
                    $newId = $this->service->create($data);
                    $message = 'Receita criada com sucesso';
                }
            } else {
                $this->service->update($id, $data);
                $newId = $id;

                if (($data['receberReceita'] ?? 0) == 1) {
                    $pagamentoData = [
                        'receita_id'           => $id,
                        'conta_id'             => $data['conta_id'] ?? null,
                        'pagamento_receita_dt' => $data['recebimento_dt'] ?? $data['pagamento_dt'] ?? null,
                        'valor'                => $data['valor'] ?? null,
                        'rateio'               => $data['rateio'] ?? [],
                        'comentario'           => $data['comentario'] ?? null,
                    ];

                    // Call the PagamentoReceitasService to register the payment.
                    // Nota: o rateio já vem do frontend em valores absolutos (R$), não em
                    // percentuais — não deve ser recalculado aqui.
                    $pagamentoService = new \App\Services\Financeiro\PagamentoReceitasService();
                    $pagamentoService->create($pagamentoData);
                    $message = 'Receita atualizada e recebimento registrado com sucesso';
                } else {
                    $message = 'Receita atualizada com sucesso';
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
            log_message('error', '[Financeiro\Receitas::salvar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao salvar receita: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Exporta apenas receitas selecionadas
     */
    private function exportarSelecionadas(array $ids): ResponseInterface
    {
        $receitas = $this->service->findByIds($ids);

        $csv = "ID;Receita;Valor;Vencimento;Cliente\n";
        foreach ($receitas as $receita) {
            $csv .= sprintf(
                "%d;%s;R$ %s;%s;%s\n",
                $receita['id_receita'],
                $receita['receita'],
                number_format((float) $receita['valor'], 2, ',', '.'),
                date('d/m/Y', strtotime($receita['vencimento_dt'])),
                $receita['cliente_nome'] ?? ''
            );
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="receitas_selecionadas_' . date('Y-m-d') . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }
}
