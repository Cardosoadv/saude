<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site;

use App\Controllers\BaseController;
use App\Repositories\Site\SiteConfiguracaoRepository;
use App\Services\CrmContactService;
use App\Services\NewsService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller fornecendo a API REST para a área pública em Vue.js.
 *
 * Segue o padrão do sistema: Controller → Service → Repository → Model.
 * Nenhuma lógica de query deve existir aqui.
 *
 * @version 1.1.0
 */
class ApiController extends BaseController
{
    use ResponseTrait;

    protected NewsService $newsService;
    protected CrmContactService $crmContactService;

    public function __construct()
    {
        $this->newsService = new NewsService();
        $this->crmContactService = new CrmContactService();
    }

    /**
     * Retorna a lista de ramos do direito (áreas de atuação).
     */
    public function areas()
    {
        $areas = $this->crmContactService->getRamosDireito();
        return $this->respond($areas);
    }

    /**
     * Retorna a lista de notícias publicadas com paginação e busca.
     * A lógica de pesquisa é delegada ao NewsService/NewsRepository.
     */
    public function noticias()
    {
        $search = $this->request->getGet('search') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);
        $limit = (int) ($this->request->getGet('limit') ?? 12);

        $result = $this->newsService->buscarPublicadas($search, $page, $limit);

        return $this->respond($result);
    }

    /**
     * Retorna uma notícia específica com seus artigos relacionados.
     */
    public function noticiaBySlug(string $slug)
    {
        $news = $this->newsService->getBySlug($slug);

        if (!$news || $news['status'] !== 'published') {
            return $this->failNotFound('Artigo não encontrado.');
        }

        $related = $this->newsService->getLatestPublishedExcept($slug, 3);

        return $this->respond([
            'article' => $news,
            'related' => $related
        ]);
    }

    /**
     * Processa a submissão de mensagens de contato.
     */
    public function submitContact()
    {

        $data = $this->getRequestData();


        $rules = [
            'nome' => 'required|max_length[100]',
            'email' => 'required|valid_email|max_length[100]',
            'telefone' => 'permit_empty|max_length[20]',
            'mensagem' => 'required',
            'area_interesse' => 'permit_empty|integer',
            'aceite_privacidade' => 'required',
        ];
        $messages = [
            'aceite_privacidade' => ['required' => 'Para enviar, confirme que leu a Política de Privacidade.'],
        ];

        // Ciência da Política de Privacidade (LGPD): checkbox do formulário ("1"/"on") ou booleano no JSON.
        $data['aceite_privacidade'] = filter_var($data['aceite_privacidade'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : '';

        // Se o valor de 'area' for numérico, define no campo correspondente
        $areaInteresse = isset($data['area']) && is_numeric($data['area']) ? (int) $data['area'] : 1;
        $data['area_interesse'] = $areaInteresse;

        $validation = \Config\Services::validation();
        $validation->setRules($rules, $messages);

        if (!$validation->run($data)) {
            return $this->failValidationErrors($validation->getErrors());
        }

        try {
            $id = $this->crmContactService->create([
                'name' => $data['nome'],
                'email' => $data['email'],
                'phone' => $data['telefone'] ?? null,
                'message' => $data['mensagem'],
                'area_interesse' => $data['area_interesse'],
                'status' => 'new',
                'privacidade_aceite_em' => date('Y-m-d H:i:s'),
                'privacidade_versao' => $this->versaoPoliticaPrivacidade(),
            ]);

            $isJson = strpos($this->request->header('Content-Type') ? $this->request->header('Content-Type')->getValue() : '', 'application/json') !== false;

            if ($id) {
                if (!$isJson && !$this->request->isAJAX()) {
                    return redirect()->back()->with('success', 'Sua mensagem foi enviada com sucesso! Entraremos em contato em breve.');
                }
                return $this->respondCreated([
                    'status' => 'success',
                    'message' => 'Sua mensagem foi enviada com sucesso! Entraremos em contato em breve.'
                ]);
            } else {
                if (!$isJson && !$this->request->isAJAX()) {
                    return redirect()->back()->with('error', 'Erro ao salvar o contato. Tente novamente mais tarde.');
                }
                return $this->fail('Erro ao salvar o contato. Tente novamente mais tarde.');
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /** Versão vigente da Política de Privacidade (configuração `lgpd.politica_versao`), registrada com o aceite. */
    private function versaoPoliticaPrivacidade(): ?string
    {
        $versao = (new SiteConfiguracaoRepository())->mapa()['lgpd.politica_versao'] ?? null;

        return is_scalar($versao) && (string) $versao !== '' ? mb_substr((string) $versao, 0, 20) : null;
    }
}
