<?php

namespace App\Controllers;

use App\Services\Financeiro\DespesasService;
use App\Services\TarefasService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @var array Propriedade para armazenar os dados globais de template.
     */
    protected $templateData;

    /**
     * Constructor.
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
        // Disponibiliza as informações da clínica de forma global para todas as views
        $renderer = \Config\Services::renderer();
        $renderer->setVar('templateData', $this->getTemplateData());
    }


    /**
     * Obtém os dados de navegação e notificações globais.
     *
     * Carrega contadores de mensagens não lidas, tarefas pendentes e despesas não pagas
     * para exibir na barra de navegação ou sidebar.
     *
     * @return array Dados de template.
     */
    protected function getTemplateData()
    {

        $data = [
            'nomeEmpresa'       => env('nomeEmpresa') ?? 'Minha Empresa',
            'logoEmpresa'       => env('logoEmpresa') ?? 'public/dist/assets/img/logo.png',
            'cnpjEmpresa'       => env('cnpjEmpresa')?? '00.000.000/0001-00',
            'enderecoEmpresa'   => env('enderecoEmpresa'),
        ];

        return $data;
    }

    /**
     * Obtém os dados da requisição de forma segura, suportando JSON e formulários tradicionais (POST/GET),
     * sem disparar exceções de análise de JSON do framework sob strings malformadas ou vazias.
     *
     * @return array
     */
    protected function getRequestData(): array
    {
        $data = [];
        $contentType = $this->request->header('Content-Type') ? $this->request->header('Content-Type')->getValue() : '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $body = $this->request->getBody();
            if (!empty($body)) {
                try {
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data = $decoded;
                    }
                } catch (\Exception $e) {
                    $data = [];
                }
            }
        }
        
        return array_merge($data, $this->request->getPost() ?? [], $this->request->getGet() ?? []);
    }
}
