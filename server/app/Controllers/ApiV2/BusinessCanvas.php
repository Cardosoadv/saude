<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\BusinessCanvasService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador para o Business Model Canvas.
 *
 * Gerencia as requisições web e chamadas AJAX relacionadas ao canvas,
 * delegando a lógica de negócio para o BusinessCanvasService.
 */
class BusinessCanvas extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    /**
     * @var BusinessCanvasService Instância do serviço.
     */
    protected BusinessCanvasService $service;

    /**
     * @var string Permissão base do módulo.
     */
    protected string $permissao = 'business_canvas';

    /**
     * Construtor do controlador.
     *
     * @param BusinessCanvasService|null $service
     */
    public function __construct(?BusinessCanvasService $service = null)
    {
        $this->service = $service ?? new BusinessCanvasService();
    }

    /**
     * Renderiza a página principal do Business Canvas.
     *
     * @return string|ResponseInterface
     */
    public function index()
    {
        if (!$this->verificaPermissao($this->permissao, 'access') && !$this->verificaPermissao($this->permissao, 'exclusive')) {
            return $this->failForbidden('Você não tem permissão para acessar o Business Model Canvas.');
        }

        $data = [
            'titulo' => 'Business Model Canvas',
            'js_modules' => ['BusinessCanvas'],
            'blocks' => $this->service->getBlockStructure()
        ];

        return view('businessCanvas/index', $data);
    }

    /**
     * Retorna a lista de canvases do usuário logado via AJAX.
     *
     * @return ResponseInterface
     */
    public function ajaxGetCanvases(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!$this->verificaPermissao($this->permissao, 'access') && !$this->verificaPermissao($this->permissao, 'exclusive')) {
            return $this->failForbidden('Sem permissão.');
        }

        $userId = auth()->user()->id;
        $canvases = (array) $this->service->getUserCanvases((int)$userId);

        return $this->respond([
            'success' => true,
            'data'    => $canvases
        ]);
    }

    /**
     * Busca os detalhes de um canvas específico via AJAX.
     *
     * @param int $id ID do canvas.
     * @return ResponseInterface
     */
    public function ajaxGetCanvas($id): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $allowed = $this->checkOwnership((int)$id, 'access');
        if ($allowed !== true) {
            return $allowed;
        }

        $canvas = $this->service->getCanvas((int) $id);

        if (!$canvas) {
            return $this->failNotFound('Canvas não encontrado');
        }

        return $this->respond([
            'success' => true,
            'data'    => $canvas
        ]);
    }

    /**
     * Cria um novo canvas via AJAX.
     *
     * @return ResponseInterface
     */
    public function ajaxCreateCanvas(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!$this->verificaPermissao($this->permissao, 'create') && !$this->verificaPermissao($this->permissao, 'exclusive')) {
            return $this->failForbidden('Sem permissão para criar canvas.');
        }

        $rules = [
            'title'       => 'required|min_length[3]|max_length[255]',
            'description' => 'permit_empty|max_length[1000]',
        ];

        $data = $this->request->getPost();
        if (empty($data)) {
            $data = $this->request->getJSON(true) ?? [];
        }

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $userId = auth()->user()->id;
            $canvasId = $this->service->createCanvas((int)$userId, (string) $data['title'], $data['description'] ?? null);

            return $this->respond([
                'success'   => true,
                'message'   => 'Canvas criado com sucesso',
                'canvas_id' => $canvasId
            ], 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Atualiza os metadados de um canvas via AJAX.
     *
     * @param int $id ID do canvas.
     * @return ResponseInterface
     */
    public function ajaxUpdateCanvas($id): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $allowed = $this->checkOwnership((int)$id, 'edit');
        if ($allowed !== true) {
            return $allowed;
        }

        $rules = [
            'title'       => 'permit_empty|min_length[3]|max_length[255]',
            'description' => 'permit_empty|max_length[1000]',
        ];

        $data = $this->request->getJSON(true) ?? [];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $userId = auth()->user()->id;
        $result = $this->service->updateCanvas((int) $id, $data, (int)$userId);

        return $this->respond([
            'success' => $result,
            'message' => $result ? 'Canvas atualizado com sucesso' : 'Erro ao atualizar canvas'
        ]);
    }

    /**
     * Exclui um canvas via AJAX.
     *
     * @param int $id ID do canvas.
     * @return ResponseInterface
     */
    public function ajaxDeleteCanvas($id): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $allowed = $this->checkOwnership((int)$id, 'delete');
        if ($allowed !== true) {
            return $allowed;
        }

        $userId = auth()->user()->id;
        $result = $this->service->deleteCanvas((int) $id, (int)$userId);

        return $this->respond([
            'success' => $result,
            'message' => $result ? 'Canvas excluído com sucesso' : 'Erro ao excluir canvas'
        ]);
    }

    /**
     * Salva o conteúdo de um bloco via AJAX.
     *
     * @return ResponseInterface
     */
    public function ajaxSaveBlock(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $canvasId = (int) $this->request->getPost('canvas_id');
        $allowed = $this->checkOwnership($canvasId, 'edit');
        if ($allowed !== true) {
            return $allowed;
        }

        $userId = (int) auth()->user()->id;
        $blockType = $this->request->getPost('block_type');
        $content = $this->request->getPost('content');

        $result = $this->service->saveBlock($canvasId, (string) $blockType, $content, $userId);

        return $this->respond([
            'success' => $result,
            'message' => $result ? 'Bloco salvo com sucesso' : 'Erro ao salvar bloco'
        ]);
    }

    /**
     * Verifica se o usuário tem permissão para realizar uma ação em um canvas específico (IDOR).
     *
     * @param int    $canvasId ID do canvas.
     * @param string $action   Ação a ser verificada (access, create, edit, delete).
     * @return bool|ResponseInterface
     */
    private function checkOwnership(int $canvasId, string $action)
    {
        if (!$this->verificaPermissao($this->permissao, $action) && !$this->verificaPermissao($this->permissao, 'exclusive')) {
            return $this->failForbidden('Sem permissão para esta ação.');
        }

        // Se o usuário tem apenas permissão exclusive, verificamos a propriedade
        if (!$this->verificaPermissao($this->permissao, $action) && $this->verificaPermissao($this->permissao, 'exclusive')) {
            $ownerId = $this->service->getCanvasUserId($canvasId);
            $userId  = (int) auth()->user()->id;

            if ($ownerId !== $userId) {
                return $this->failForbidden('Você não tem permissão para acessar este Business Model Canvas.');
            }
        }

        return true;
    }
}
