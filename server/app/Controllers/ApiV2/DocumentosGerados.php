<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\DocumentoGeradoService;
use App\Traits\AcessoProcessoTrait;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API V2 para geração e finalização de documentos a partir de modelos.
 */
class DocumentosGerados extends BaseController
{
    use ResponseTrait;
    use AcessoProcessoTrait;

    protected DocumentoGeradoService $service;

    public function __construct(?DocumentoGeradoService $service = null)
    {
        $this->service = $service ?? new DocumentoGeradoService();
    }

    /**
     * Lista documentos gerados com filtros e paginação.
     */
    public function index()
    {
        try {
            $filtros = [
                'status'      => $this->request->getGet('status'),
                'modelo_id'   => $this->request->getGet('modelo_id'),
                'cliente_id'  => $this->request->getGet('cliente_id'),
                'ramo_id'     => $this->request->getGet('ramo_id'),
                'etiqueta_id' => $this->request->getGet('etiqueta_id'),
                'data_inicio' => $this->request->getGet('data_inicio'),
                'data_fim'    => $this->request->getGet('data_fim'),
            ];
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);

            $documentos = $this->service->listarComFiltros(array_filter($filtros), $perPage);

            return $this->respond($documentos);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao listar documentos gerados: ' . $e->getMessage());
        }
    }

    /**
     * Histórico de documentos gerados a partir de um processo ou caso.
     */
    public function porEntidade(string $tipo, int $id)
    {
        if ($tipo === 'processo' && ($negado = $this->acessoAoProcessoNegado($id))) {
            return $negado;
        }

        try {
            return $this->respond($this->service->listarPorEntidade($tipo, $id));
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao listar documentos da entidade: ' . $e->getMessage());
        }
    }

    /**
     * Exibe o detalhe de um documento gerado.
     */
    public function exibir(int $id)
    {
        $documento = $this->service->getById($id);

        if (!$documento) {
            return $this->failNotFound('Documento gerado não encontrado.');
        }

        if ($negado = $this->acessoAoDocumentoNegado($documento)) {
            return $negado;
        }

        $documento['etiquetas'] = $this->service->getEtiquetas($id);

        return $this->respond($documento);
    }

    /**
     * Inicia a geração de um documento a partir de um modelo e uma entidade âncora.
     */
    public function iniciar()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['modelo_id']) || empty($data['entidade_tipo']) || empty($data['entidade_id'])) {
            return $this->failValidationErrors(['mensagem' => 'modelo_id, entidade_tipo e entidade_id são obrigatórios.']);
        }

        if ($data['entidade_tipo'] === 'processo' && ($negado = $this->acessoAoProcessoNegado((int) $data['entidade_id']))) {
            return $negado;
        }

        try {
            $documento = $this->service->iniciarGeracao(
                (int) $data['modelo_id'],
                $data['entidade_tipo'],
                (int) $data['entidade_id'],
                (int) user_id()
            );

            return $this->respondCreated($documento);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao iniciar geração do documento: ' . $e->getMessage());
        }
    }

    /**
     * Autosave/salvamento manual do rascunho.
     */
    public function salvar(int $id)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if ($negado = $this->acessoAoDocumentoNegado($this->service->getById($id))) {
            return $negado;
        }

        try {
            if (array_key_exists('conteudo_html', $data)) {
                $this->service->salvarRascunho($id, $data['conteudo_html'], (int) user_id());
            }

            if (!empty($data['titulo'])) {
                $this->service->atualizarTitulo($id, $data['titulo']);
            }

            if (array_key_exists('etiqueta_ids', $data)) {
                $this->service->sincronizarEtiquetas($id, $data['etiqueta_ids']);
            }

            return $this->respond(['success' => true, 'message' => 'Documento salvo com sucesso.']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao salvar documento: ' . $e->getMessage());
        }
    }

    /**
     * Finaliza o documento: gera o PDF, envia ao Drive e vincula o arquivo definitivo.
     */
    public function finalizar(int $id)
    {
        if ($negado = $this->acessoAoDocumentoNegado($this->service->getById($id))) {
            return $negado;
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        try {
            $documento = $this->service->finalizar($id, (int) user_id(), $data['html_final'] ?? null);
            return $this->respond($documento);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao finalizar documento: ' . $e->getMessage());
        }
    }

    /**
     * Exclui um rascunho.
     */
    public function deletar(int $id)
    {
        if ($negado = $this->acessoAoDocumentoNegado($this->service->getById($id))) {
            return $negado;
        }

        try {
            $this->service->excluirRascunho($id);
            return $this->respondDeleted(['success' => true, 'message' => 'Rascunho excluído com sucesso.']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao excluir documento: ' . $e->getMessage());
        }
    }

    /**
     * Documento gerado a partir de um processo segue a privacidade do processo.
     */
    private function acessoAoDocumentoNegado(?array $documento): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if ($documento === null || ($documento['entidade_tipo'] ?? null) !== 'processo') {
            return null;
        }

        return $this->acessoAoProcessoNegado((int) $documento['entidade_id']);
    }
}
