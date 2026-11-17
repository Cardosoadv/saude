<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\DocumentoModeloService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API V2 para Gerenciamento de Modelos de Documentos.
 */
class Modelos extends BaseController
{
    use ResponseTrait;

    protected DocumentoModeloService $service;

    public function __construct(?DocumentoModeloService $service = null)
    {
        $this->service = $service ?? new DocumentoModeloService();
    }

    /**
     * Lista os modelos cadastrados, com filtros e paginação.
     */
    public function index()
    {
        try {
            $perPage = (int) ($this->request->getGet('per_page') ?? 20);
            $filtros = [
                'ramo_id'     => $this->request->getGet('ramo_id'),
                'status'      => $this->request->getGet('status'),
                'tipo_alvo'   => $this->request->getGet('tipo_alvo'),
                'ativo'       => $this->request->getGet('ativo'),
                'etiqueta_id' => $this->request->getGet('etiqueta_id'),
                'q'           => $this->request->getGet('q'),
            ];

            $modelos = $this->service->listarComFiltros($filtros, $perPage);
            $pager = $this->service->getPager();

            return $this->respond([
                'data'       => $modelos,
                'pagination' => [
                    'page'        => $pager->getCurrentPage('default'),
                    'per_page'    => $perPage,
                    'total'       => $pager->getTotal('default'),
                    'total_pages' => $pager->getPageCount('default'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao listar modelos: ' . $e->getMessage());
        }
    }

    /**
     * Exibe um modelo específico com as etiquetas vinculadas.
     */
    public function exibir(int $id)
    {
        try {
            $modelo = $this->service->getById($id);
            if (!$modelo) {
                return $this->failNotFound('Modelo não encontrado.');
            }

            $modelo['etiquetas'] = $this->service->getEtiquetas($id);
            return $this->respond($modelo);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao exibir modelo: ' . $e->getMessage());
        }
    }

    /**
     * Retorna o catálogo de placeholders disponíveis para uso nos modelos.
     */
    public function placeholders()
    {
        try {
            return $this->respond($this->service->getCatalogoPlaceholders());
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao carregar placeholders: ' . $e->getMessage());
        }
    }

    /**
     * Retorna a lista de ramos do direito para preencher selects.
     */
    public function ramosOptions()
    {
        try {
            return $this->respond($this->service->getRamosOptions());
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao carregar ramos: ' . $e->getMessage());
        }
    }

    /**
     * Salva um novo modelo.
     */
    public function salvar()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($data['titulo']) || empty($data['conteudo'])) {
            return $this->failValidationErrors(['mensagem' => 'Título e Conteúdo são obrigatórios.']);
        }

        try {
            $this->service->salvar($data);
            return $this->respondCreated(['success' => true, 'message' => 'Modelo de documento salvo com sucesso.']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao salvar modelo: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza um modelo existente.
     */
    public function atualizar(int $id)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $data['id'] = $id;

        if (empty($data['titulo']) || empty($data['conteudo'])) {
            return $this->failValidationErrors(['mensagem' => 'Título e Conteúdo são obrigatórios.']);
        }

        try {
            $this->service->salvar($data);
            return $this->respond(['success' => true, 'message' => 'Modelo de documento atualizado com sucesso.']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao atualizar modelo: ' . $e->getMessage());
        }
    }

    /**
     * Deleta um modelo.
     */
    public function deletar(int $id)
    {
        try {
            $this->service->delete($id);
            return $this->respondDeleted(['success' => true, 'message' => 'Modelo excluído com sucesso.']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao excluir modelo: ' . $e->getMessage());
        }
    }
}
