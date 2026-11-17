<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Services\NewsService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller administrativo para CRUD de notícias via API REST JSON.
 *
 * @version 1.2.0
 */
class Noticias extends BaseController
{
    use ResponseTrait;

    protected NewsService $service;

    public function __construct(?NewsService $service = null)
    {
        $this->service = $service ?? new NewsService();
    }

    public function index()
    {
        $search = $this->request->getGet('search') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);
        $limit = (int) ($this->request->getGet('limit') ?? 20);

        try {
            $news = $this->service->buscarPainelAdmin($search, $page, $limit);
            return $this->respond($news);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function show($id)
    {
        $news = $this->service->getById((int) $id);
        if (!$news) {
            return $this->failNotFound('Notícia não encontrada');
        }
        return $this->respond(['data' => $news]);
    }

    public function store()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'title'            => 'required|max_length[255]',
            'category_id'      => 'required|integer|is_not_unique[site_news_categories.id]',
            'status'           => 'required|in_list[draft,published]',
            'summary'          => 'required|max_length[500]',
            'content'          => 'required',
            'meta_title'       => 'permit_empty|max_length[255]',
            'meta_description' => 'permit_empty|max_length[160]',
            'published_at'     => 'permit_empty|valid_date[Y-m-d\TH:i]'
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $id = $this->service->createNews($data);
            return $this->respondCreated(['id' => $id, 'message' => 'Notícia criada com sucesso']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function update($id)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'title'            => 'permit_empty|max_length[255]',
            'category_id'      => 'permit_empty|integer|is_not_unique[site_news_categories.id]',
            'status'           => 'permit_empty|in_list[draft,published]',
            'summary'          => 'permit_empty|max_length[500]',
            'content'          => 'permit_empty',
            'meta_title'       => 'permit_empty|max_length[255]',
            'meta_description' => 'permit_empty|max_length[160]',
            'published_at'     => 'permit_empty|valid_date[Y-m-d\TH:i]'
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $this->service->updateNews((int) $id, $data);
            return $this->respond(['message' => 'Notícia atualizada com sucesso']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->deleteNews((int) $id);
            return $this->respondDeleted(['id' => $id, 'message' => 'Notícia excluída com sucesso']);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
