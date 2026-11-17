<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ApiBrasilCpfService;

/**
 * Class Consultas
 *
 * Controlador para consultas externas (CPF, endereço, etc.) via API Brasil.
 *
 * @package App\Controllers\ApiV2
 */
class Consultas extends BaseController
{
    protected ApiBrasilCpfService $service;

    public function __construct(?ApiBrasilCpfService $service = null)
    {
        $this->service = $service ?? new ApiBrasilCpfService();
    }

    /**
     * Consulta CPF (dados cadastrais, endereço e informações de crédito).
     *
     * Aceita o CPF via corpo JSON (POST) ou via segmento de rota (GET).
     */
    public function cpf($cpf = null)
    {
        if ($cpf === null) {
            $payload = $this->request->getJSON(true) ?? [];
            $cpf = $payload['cpf'] ?? $this->request->getPost('cpf');
        }

        if (!$cpf) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Informe o CPF a ser consultado.']);
        }

        $result = $this->service->consultarCpf((string) $cpf);

        return $this->response
            ->setStatusCode($result['success'] ? 200 : $result['statusCode'])
            ->setJSON([
                'success' => $result['success'],
                'message' => $result['message'],
                'error'   => !$result['success'],
                'data'    => $result['data'],
            ]);
    }
}
