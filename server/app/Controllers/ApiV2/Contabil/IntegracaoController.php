<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Contabil;

use App\Controllers\BaseController;
use App\Services\Contabil\AberturaContabilService;
use App\Services\Contabil\IntegracaoFinanceiraService;
use App\Services\Contabil\PlanoContasModeloService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CognitioTechny\Contabil\Domain\Exception\ConflictException;
use InvalidArgumentException;
use Throwable;

/**
 * Controller de Integração entre o Módulo Contábil e o Financeiro.
 *
 * @version 1.0.0
 */
class IntegracaoController extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected IntegracaoFinanceiraService $service;

    public function __construct(?IntegracaoFinanceiraService $service = null)
    {
        $this->service = $service ?? new IntegracaoFinanceiraService();
    }

    /**
     * Lista as transações financeiras para preview e importação contábil.
     */
    public function transacoes(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão de acesso ao módulo contábil.'], 403);
        }

        try {
            $dataInicio = $this->request->getGet('data_inicio');
            $dataFim    = $this->request->getGet('data_fim');
            $tipo       = $this->request->getGet('tipo');
            $status     = $this->request->getGet('status');
            $entityId   = (int) ($this->request->getGet('entity_id') ?: 1);

            $resultado = $this->service->obterTransacoes(
                dataInicio: $dataInicio ? (string) $dataInicio : null,
                dataFim: $dataFim ? (string) $dataFim : null,
                tipo: $tipo ? (string) $tipo : null,
                status: $status ? (string) $status : null,
                entityId: $entityId
            );

            return $this->respond([
                'success' => true,
                'data'    => $resultado['transacoes'],
                'kpis'    => $resultado['kpis'],
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::transacoes] ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'Erro ao listar transações financeiras para conciliação contábil: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna contas contábeis folha para opções de mapeamento de débito/crédito.
     */
    public function contas(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        try {
            $entityId = (int) ($this->request->getGet('entity_id') ?: 1);
            $contas   = $this->service->obterContasFolhas($entityId);

            return $this->respond([
                'success' => true,
                'data'    => $contas,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::contas] ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'Erro ao obter contas contábeis.',
            ], 500);
        }
    }

    /**
     * Lista o dicionário categoria financeira -> conta contábil usado nas sugestões de importação.
     */
    public function dicionarioCategorias(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        try {
            $entityId = (int) ($this->request->getGet('entity_id') ?: 1);

            return $this->respond(['success' => true, 'data' => $this->service->obterDicionarioCategorias($entityId)]);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::dicionarioCategorias] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao obter o dicionário de categorias.'], 500);
        }
    }

    /**
     * Grava associações categoria -> conta (conta vazia remove a associação).
     */
    public function salvarDicionarioCategorias(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'create') && ! $this->verificaPermissao('contabil', 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão para alterar o dicionário de categorias.'], 403);
        }

        try {
            $json  = $this->request->getJSON(true) ?? [];
            $itens = $json['itens'] ?? [];

            if (! is_array($itens) || count($itens) === 0) {
                return $this->respond(['success' => false, 'message' => 'Nenhuma associação informada.'], 400);
            }

            $entityId = (int) ($json['entity_id'] ?? 1);
            $total    = $this->service->salvarDicionarioCategorias($itens, $entityId);

            return $this->respond([
                'success' => true,
                'data'    => $this->service->obterDicionarioCategorias($entityId),
                'message' => sprintf('%d associação(ões) atualizada(s) no dicionário.', $total),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::salvarDicionarioCategorias] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Falha ao gravar o dicionário de categorias: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista os planos de contas modelo disponíveis para importação.
     */
    public function planosModelo(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        return $this->respond(['success' => true, 'data' => (new PlanoContasModeloService())->listarModelos()]);
    }

    /**
     * Importa um plano de contas modelo (cria a entidade se faltar). Repetível.
     */
    public function importarPlanoPadrao(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'create') && ! $this->verificaPermissao('contabil', 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão para importar o plano de contas.'], 403);
        }

        try {
            $json     = $this->request->getJSON(true) ?? [];
            $resultado = (new PlanoContasModeloService())->importar((string) ($json['model'] ?? ''), (int) ($json['entity_id'] ?? 1));

            return $this->respond([
                'success'   => true,
                'resultado' => $resultado,
                'message'   => sprintf('%d conta(s) criada(s); %d já existiam.', $resultado['created'], $resultado['skipped']),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::importarPlanoPadrao] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Falha ao importar o plano de contas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lança os saldos de abertura (balancete do período anterior) em um único lançamento.
     */
    public function abertura(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'create') && ! $this->verificaPermissao('contabil', 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão para lançar a abertura contábil.'], 403);
        }

        try {
            $json = $this->request->getJSON(true) ?? [];

            if (isset($json['postar']) && ! $json['postar']) {
                return $this->respond(['success' => false, 'message' => 'A abertura contábil é sempre lançada; não há abertura em rascunho.'], 400);
            }

            $resultado = (new AberturaContabilService())->abrir(
                is_array($json['saldos'] ?? null) ? $json['saldos'] : [],
                (string) ($json['data'] ?? ''),
                (int) ($json['entity_id'] ?? 1),
                (string) ($json['descricao'] ?? ''),
                (bool) ($json['substituir'] ?? false)
            );

            return $this->respond([
                'success'   => true,
                'resultado' => $resultado,
                'message'   => $resultado['created']
                    ? 'Abertura contábil lançada com sucesso.'
                    : 'A abertura com estes saldos já estava lançada; nada foi alterado.',
            ], $resultado['created'] ? 201 : 200);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (ConflictException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::abertura] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Falha ao lançar a abertura contábil: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Importa as transações financeiras selecionadas gerando os lançamentos contábeis.
     */
    public function importar(): ResponseInterface
    {
        if (! $this->verificaPermissao('contabil', 'create') && ! $this->verificaPermissao('contabil', 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão para gerar lançamentos contábeis.'], 403);
        }

        try {
            $json = $this->request->getJSON(true);
            $itens = $json['itens'] ?? [];
            $postar = isset($json['postar']) ? (bool) $json['postar'] : true;
            $entityId = (int) ($json['entity_id'] ?? 1);

            if (! is_array($itens) || count($itens) === 0) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Nenhuma transação selecionada para importação.',
                ], 400);
            }

            $resultado = $this->service->importarLote($itens, $entityId, $postar);

            return $this->respond([
                'success'   => true,
                'resultado' => $resultado,
                'message'   => sprintf('%d lançamento(s) importado(s) com sucesso na contabilidade.', $resultado['sucesso']),
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Contabil\IntegracaoController::importar] ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'Falha ao importar dados para a contabilidade: ' . $e->getMessage(),
            ], 500);
        }
    }
}
