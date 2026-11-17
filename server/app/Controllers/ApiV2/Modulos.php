<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ModulosService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;

/**
 * Ativação e desativação dos módulos do sistema.
 */
class Modulos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ModulosService $service;

    public function __construct(?ModulosService $service = null)
    {
        $this->service = $service ?? service('modulos');
    }

    /**
     * GET api/v2/modulos
     */
    public function index()
    {
        if (!$this->verificaPermissao('module', 'access')) {
            return $this->failForbidden('Você não tem permissão para ver os módulos.');
        }

        return $this->respond([
            'success' => true,
            'data'    => $this->service->listar(),
        ]);
    }

    /**
     * POST api/v2/modulos/toggleStatus
     * Corpo (JSON ou form): { "slug": "contratos", "ativo": false }
     */
    public function toggleStatus()
    {
        if (!$this->verificaPermissao('module', 'edit')) {
            return $this->failForbidden('Você não tem permissão para ativar ou desativar módulos.');
        }

        $dados = $this->request->is('json') ? (array) $this->request->getJSON(true) : $this->request->getPost();
        $slug  = is_string($dados['slug'] ?? null) ? trim($dados['slug']) : '';
        $ativo = filter_var($dados['ativo'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $erros = [];
        if ($slug === '') {
            $erros['slug'] = 'Informe o slug do módulo.';
        }
        if ($ativo === null) {
            $erros['ativo'] = 'Informe se o módulo deve ficar ativo (true ou false).';
        }
        if ($erros !== []) {
            return $this->failValidationErrors($erros);
        }

        try {
            $modulo = $this->service->alterarStatus($slug, $ativo);
        } catch (\DomainException $e) {
            return $this->failValidationErrors(['ativo' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return $this->failServerError($e->getMessage());
        }

        if ($modulo === null) {
            return $this->failNotFound('Módulo não encontrado.');
        }

        return $this->respond([
            'success' => true,
            'message' => $ativo ? 'Módulo ativado.' : 'Módulo desativado.',
            'data'    => $modulo,
        ]);
    }
}
