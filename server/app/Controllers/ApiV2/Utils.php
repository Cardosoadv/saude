<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\CepService;

/**
 * Class Utils
 *
 * Controlador utilitário para funções de uso geral.
 *
 * @package App\Controllers
 */
class Utils extends BaseController
{
    /**
     * @var CepService
     */
    protected CepService $service;

    public function __construct(?CepService $service = null)
    {
        $this->service = $service ?? new CepService();
    }

    /**
     * Consulta CEP via API externa (ViaCEP) através do backend.
     *
     * @param string $cep O CEP a ser consultado (apenas números).
     * @return \CodeIgniter\HTTP\ResponseInterface JSON com os dados do endereço ou erro.
     */
    public function consultaCep($cep)
    {
        log_message('info', 'Iniciando consulta de CEP: ' . $cep);

        $data = $this->service->consultarCep($cep);

        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(400)->setJSON(['erro' => 'CEP inválido ou serviço indisponível']);
    }
}
