<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ConfiguracoesService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Controller API V2 para gerenciar configurações do sistema e limpar cache.
 * 
 * @version 1.0.0
 * @since 2026-05-25
 */
class Configuracoes extends BaseController
{
    use ResponseTrait;

    protected ConfiguracoesService $service;

    public function __construct(?ConfiguracoesService $service = null)
    {
        $this->service = $service ?? new ConfiguracoesService();
    }

    /**
     * Retorna a listagem de todas as configurações do sistema.
     * 
     * @return ResponseInterface
     */
    public function listar()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para listar configurações.',
            ], 403);
        }

        try {
            $configs = $this->service->listAll();
            return $this->respond([
                'success' => true,
                'data'    => $configs,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao listar configurações API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao carregar configurações.',
            ], 500);
        }
    }

    /**
     * Salva ou atualiza uma configuração do sistema.
     * 
     * @return ResponseInterface
     */
    public function salvar()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para salvar configurações.',
            ], 403);
        }

        $id = $this->request->getPost('id');
        $data = [
            'nome'      => $this->request->getPost('nome'),
            'valor'     => $this->request->getPost('valor'),
            'descricao' => $this->request->getPost('descricao'),
        ];

        // Validations
        if (empty($data['nome'])) {
            return $this->respond([
                'success' => false,
                'message' => 'O nome da configuração é obrigatório.',
            ], 400);
        }

        try {
            if ($id && is_numeric($id)) {
                $idVal = (int) $id;
                $data['id'] = $idVal;
                $this->service->update($idVal, $data);
                $message = 'Configuração atualizada com sucesso.';
            } else {
                $this->service->create($data);
                $message = 'Configuração criada com sucesso.';
            }

            return $this->respond([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao salvar configuração API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao salvar configuração: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Limpa o cache da aplicação.
     * 
     * @return ResponseInterface
     */
    public function limparCache()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para limpar o cache do sistema.',
            ], 403);
        }

        try {
            cache()->clean();
            return $this->respond([
                'success' => true,
                'message' => 'Todo o cache do backend foi limpo com sucesso.',
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao limpar cache API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao limpar cache: ' . $e->getMessage(),
            ], 500);
        }
    }
}
