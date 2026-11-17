<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\PagamentoReceitasService;
use App\Traits\FormataValorTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class PagamentoReceitas extends BaseController
{
    use ResponseTrait;
    use FormataValorTrait;
    use VerificaPermissoesTrait;

    protected PagamentoReceitasService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?PagamentoReceitasService $service = null)
    {
        $this->service = $service ?? new PagamentoReceitasService();
    }

    /**
     * GET /api/financeiro/pagamento-receitas
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

            $perPage = (int) ($this->request->getGet('perPage') ?? 25);
            $page = (int) ($this->request->getGet('page') ?? 1);
            $result = $this->service->listarPaginado($filters, $perPage, $page);

            return $this->respond([
                'success' => true,
                'data'    => $result['data'],
                'pager'   => $result['pager']
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::listar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao listar recebimentos.'
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-receitas/detalhes/(:num)
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

            return $this->respond([
                'success' => true,
                'data'    => $pagamento
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::detalhar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar recebimento.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-receitas/salvar
     */
    public function salvar(): ResponseInterface
    {
        $data = $this->getRequestData();
        
        $id = $data['id_pgto_receita'] ?? null;
        $id = is_numeric($id) ? (int)$id : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão'
            ], 403);
        }

        try {

            // Format rateio if array
            if (isset($data['rateio']) && is_array($data['rateio'])) {
                // Filter empty entries
                $data['rateio'] = array_filter($data['rateio'], function ($item) {
                    return !empty($item['id']) && (!empty($item['valor']) || $item['valor'] === '0');
                });
            }

            if ($id === null) {
                $newId = $this->service->create($data);
                $message = 'Recebimento registrado com sucesso';
            } else {
                $this->service->update($id, $data);
                $newId = $id;
                $message = 'Recebimento atualizado com sucesso';
            }

            return $this->respond([
                'success' => true,
                'message' => $message,
                'id'      => $newId
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::salvar] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao salvar recebimento.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-receitas/estatisticas
     * Retorna estatísticas dos recebimentos
     */
    public function estatisticas(): ResponseInterface
    {
        // Verifica permissão
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $stats = $this->service->getEstatisticas();

            return $this->respond([
                'success' => true,
                'total_recebido_mes' => $stats['total_recebido'],
                'total_recebimentos_mes' => $stats['count'],
                'media_recebimento' => $stats['media']
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::estatisticas] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas.'
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-receitas/resumo-por-conta
     * Retorna resumo de recebimentos por conta bancária
     */
    public function resumoPorConta(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $resultado = $this->service->getResumoPorConta();
            return $this->respond($resultado);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::resumoPorConta] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar resumo.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-receitas/estornar/{id}
     * Estorna um recebimento
     */
    public function estornar($id = null): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para estornar'
            ], 403);
        }

        try {
            if (!$id) {
                return $this->respond([
                    'success' => false,
                    'message' => 'ID do recebimento não informado'
                ], 400);
            }

            $this->service->delete((int)$id);

            return $this->respond([
                'success' => true,
                'message' => 'Recebimento estornado com sucesso'
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::estornar] ' . $e->getMessage());
            $status = $e->getMessage() === 'Pagamento não encontrado.' ? 404 : 500;
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao estornar recebimento.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], $status);
        }
    }

    /**
     * POST /api/financeiro/pagamento-receitas/excluir-lote
     * Exclui múltiplos recebimentos
     */
    public function excluirEmLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir'
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhum recebimento selecionado'
                ], 400);
            }

            $deleted = 0;
            foreach ($ids as $id) {
                try {
                    $this->service->delete((int)$id);
                    $deleted++;
                } catch (\Exception $e) {
                    // Ignore fail on bulk? Or report? Original code checked return bool.
                    // Service throws Exception via Repo.
                }
            }

            return $this->respond([
                'success' => true,
                'message' => "{$deleted} recebimento(s) excluído(s) com sucesso"
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::excluirEmLote] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir recebimentos.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * GET /api/financeiro/pagamento-receitas/exportar
     * Exporta recebimentos em CSV
     */
    public function exportar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar este recurso'
            ], 403);
        }

        try {
            $recebimentos = $this->service->listarParaExportacao();

            return $this->gerarCsv($recebimentos);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::exportar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao exportar recebimentos.'
            ], 500);
        }
    }

    /**
     * POST /api/financeiro/pagamento-receitas/acao-lote
     * Executa ações em lote nos recebimentos
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
            $ids = $this->request->getPost('recebimentos'); // Consistent with view if it exists, or common pattern

            if (empty($acao) || empty($ids)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhuma ação ou recebimento selecionado'
                ], 400);
            }

            switch ($acao) {
                case 'estornar':
                    $successCount = 0;
                    foreach ($ids as $id) {
                        try {
                            if ($this->service->delete((int)$id)) {
                                $successCount++;
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    return $this->respond([
                        'success' => true,
                        'message' => "{$successCount} recebimento(s) estornado(s) com sucesso"
                    ]);

                case 'exportar':
                    $recebimentos = $this->service->listarPorIds($ids);
                    return $this->gerarCsv($recebimentos, 'recebimentos_selecionados');

                default:
                    return $this->respond([
                        'success' => false,
                        'message' => 'Ação não suportada'
                    ], 400);
            }
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\PagamentoReceitas::acaoEmLote] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao processar ação em lote.';
            return $this->respond([
                'success' => false,
                'message' => $msg
            ], 500);
        }
    }

    /**
     * Helper para gerar CSV de recebimentos
     */
    private function gerarCsv(array $recebimentos, string $filename = 'recebimentos'): ResponseInterface
    {
        // Add BOM for Excel utf-8 compatibility
        $csv = "\xEF\xBB\xBF";
        $csv .= "ID;Receita;Valor;Data Recebimento;Conta\n";

        foreach ($recebimentos as $recebimento) {
            $csv .= sprintf(
                "%d;%s;R$ %s;%s;%s\n",
                $recebimento['id_pgto_receita'],
                $recebimento['receita'] ?? 'N/A',
                number_format((float)$recebimento['valor'], 2, ',', '.'),
                date('d/m/Y', strtotime($recebimento['pagamento_receita_dt'])),
                $recebimento['conta'] ?? 'N/A'
            );
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '_' . date('Y-m-d_H-i-s') . '.csv"')
            ->setBody($csv);
    }
}
