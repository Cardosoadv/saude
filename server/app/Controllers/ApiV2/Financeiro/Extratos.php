<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\Financeiro\Extrato\ImportacaoEmAndamentoException;
use App\Services\Financeiro\Extrato\ImportacaoRequerConfirmacaoException;
use App\Services\Financeiro\ExtratoImportacaoService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Importação de extratos bancários (OFX).
 *
 * Rotas em /api/v2/financeiro/extratos/*.
 */
class Extratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ExtratoImportacaoService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?ExtratoImportacaoService $service = null)
    {
        $this->service = $service ?? new ExtratoImportacaoService();
    }

    /**
     * POST /api/v2/financeiro/extratos/previa
     * Multipart: `conta_id` e `arquivo`. Não grava nada.
     */
    public function previa(): ResponseInterface
    {
        return $this->processar(false);
    }

    /**
     * POST /api/v2/financeiro/extratos/importar
     * Multipart: `conta_id`, `arquivo` e, se houver alerta de reemissão, `confirmar=true`.
     */
    public function importar(): ResponseInterface
    {
        return $this->processar(true);
    }

    /** GET /api/v2/financeiro/extratos/importacoes?conta_id=&page=&per_page= */
    public function importacoes(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $conta     = (int) $this->request->getGet('conta_id');
            $pagina    = max(1, (int) $this->request->getGet('page'));
            $porPagina = (int) ($this->request->getGet('per_page') ?: 20);
            $r         = $this->service->listarImportacoes($conta > 0 ? $conta : null, $pagina, $porPagina);

            return $this->respond(['success' => true, 'data' => $r['itens'], 'total' => $r['total'], 'page' => $pagina]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Extratos::importacoes] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao listar importações.'], 500);
        }
    }

    /** GET /api/v2/financeiro/extratos/importacoes/{id} */
    public function detalharImportacao(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            return $this->respond(['success' => true, 'data' => $this->service->detalharImportacao($id)]);
        } catch (ExtratoNaoEncontradoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Extratos::detalharImportacao] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao consultar a importação.'], 500);
        }
    }

    /** GET /api/v2/financeiro/extratos/movimentos?conta_id=&data_inicio=&data_fim=&status=&q=&page=&per_page= */
    public function movimentos(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $status = (string) $this->request->getGet('status');
        if ($status !== '' && !in_array($status, \App\Models\Financeiro\FinanceiroExtratosMovimentosModel::STATUS, true)) {
            return $this->respond(['success' => false, 'message' => 'Status inválido.'], 422);
        }
        foreach (['data_inicio', 'data_fim'] as $campo) {
            $valor = (string) $this->request->getGet($campo);
            if ($valor !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                return $this->respond(['success' => false, 'message' => "Data inválida em {$campo}: use AAAA-MM-DD."], 422);
            }
        }

        try {
            $pagina = max(1, (int) $this->request->getGet('page'));
            $r      = $this->service->listarMovimentos([
                'conta_id'    => (int) $this->request->getGet('conta_id'),
                'data_inicio' => $this->request->getGet('data_inicio'),
                'data_fim'    => $this->request->getGet('data_fim'),
                'status'      => $status,
                'texto'       => $this->request->getGet('q'),
            ], $pagina, (int) ($this->request->getGet('per_page') ?: 50));

            return $this->respond(['success' => true, 'data' => $r['itens'], 'total' => $r['total'], 'page' => $pagina]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Extratos::movimentos] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao listar movimentos.'], 500);
        }
    }

    private function processar(bool $gravar): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'extratos_importar')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $contaId = (int) $this->request->getPost('conta_id');
        if ($contaId <= 0) {
            return $this->respond(['success' => false, 'message' => 'Informe a conta (conta_id).'], 422);
        }

        $arquivo = $this->request->getFile('arquivo');
        if ($arquivo === null || !$arquivo->isValid()) {
            return $this->respond([
                'success' => false,
                'message' => $arquivo ? 'Arquivo inválido: ' . $arquivo->getErrorString() : 'Envie o arquivo OFX no campo "arquivo".',
            ], 400);
        }

        $conteudo = file_get_contents($arquivo->getTempName());
        if ($conteudo === false) {
            return $this->respond(['success' => false, 'message' => 'Não foi possível ler o arquivo enviado.'], 400);
        }

        $nome = $arquivo->getClientName();
        $mime = $arquivo->getClientMimeType();

        try {
            if (!$gravar) {
                $dados = $this->service->previa($contaId, $nome, $mime, $conteudo);

                return $this->respond(['success' => true, 'message' => 'Prévia gerada. Nada foi gravado.', 'data' => $dados]);
            }

            $confirmar = filter_var($this->request->getPost('confirmar'), FILTER_VALIDATE_BOOLEAN);
            $userId    = auth()->id() ? (int) auth()->id() : null;
            $dados     = $this->service->importar($contaId, $nome, $mime, $conteudo, $confirmar, $userId);

            return $this->respond(['success' => true, 'message' => 'Extrato importado.', 'data' => $dados]);
        } catch (ExtratoInvalidoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ImportacaoRequerConfirmacaoException $e) {
            return $this->respond([
                'success'            => false,
                'message'            => $e->getMessage(),
                'requer_confirmacao' => true,
                'alertas'            => $e->alertas(),
            ], 409);
        } catch (ImportacaoEmAndamentoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (ExtratoNaoEncontradoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Extratos::processar] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao importar o extrato.'], 500);
        }
    }
}
