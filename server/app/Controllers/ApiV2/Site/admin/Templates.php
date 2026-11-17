<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Services\Site\SiteTemplateGestaoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Templates instalados (issue #650): galeria, envio de `.zip`, ativação, páginas de exemplo,
 * exportação e remoção.
 *
 * Instalar código de template é a ação mais sensível do site: exige a permissão `site.templates`,
 * que por padrão só o grupo `superadmin` possui.
 */
class Templates extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    /** @var callable():bool */
    private $autorizador;

    public function __construct(private ?SiteTemplateGestaoService $service = null, ?callable $autorizador = null)
    {
        $this->service ??= new SiteTemplateGestaoService();
        $this->autorizador = $autorizador ?? static fn (): bool => auth()->loggedIn() && auth()->user()->can('site.templates');
    }

    private function permitido(callable $acao)
    {
        return ($this->autorizador)()
            ? $this->executar($acao)
            : $this->failForbidden('Sem permissão para gerenciar templates do site.');
    }

    public function index()
    {
        return $this->permitido(fn () => $this->respond(['data' => $this->service->listar()]));
    }

    /** Multipart: campo `arquivo` com o `.zip`. Não ativa o template. */
    public function store()
    {
        return $this->permitido(function () {
            $arquivo = $this->request->getFile('arquivo');
            if ($arquivo === null) {
                throw ErroDeValidacao::campo('arquivo', 'Envie o pacote no campo "arquivo" (multipart).');
            }
            if (!$arquivo->isValid()) {
                throw ErroDeValidacao::campo('arquivo', 'Falha no envio: ' . $arquivo->getErrorString() . '.');
            }
            if (strtolower($arquivo->getClientExtension()) !== 'zip') {
                throw ErroDeValidacao::campo('arquivo', 'O pacote deve ser um arquivo .zip.');
            }

            $r = $this->service->instalarZip($arquivo->getTempName());

            return $this->respondCreated(['data' => $r['template'], 'atualizado' => $r['atualizado'], 'avisos' => $r['avisos']]);
        });
    }

    /** Ativa o template sem alterar o conteúdo; devolve o relatório de compatibilidade. */
    public function ativar($slug)
    {
        return $this->permitido(fn () => $this->respond(['data' => $this->service->ativar((string) $slug)]));
    }

    /** O que as páginas de exemplo criariam (nada é gravado). */
    public function paginasExemploPrevia($slug)
    {
        return $this->permitido(fn () => $this->respond(['data' => $this->service->previaPaginasExemplo((string) $slug)]));
    }

    /** Cria, como rascunho, as páginas do conteúdo inicial que ainda não existem. */
    public function paginasExemplo($slug)
    {
        return $this->permitido(fn () => $this->respond(['data' => $this->service->paginasExemplo((string) $slug)]));
    }

    /** Download do `.zip` do template; `?conteudo=1` inclui o conteúdo atual do site como seed. */
    public function exportar($slug)
    {
        return $this->permitido(function () use ($slug) {
            $comConteudo = filter_var($this->request->getGet('conteudo'), FILTER_VALIDATE_BOOLEAN);
            $bytes = $this->service->exportar((string) $slug, $comConteudo);

            return $this->response
                ->setHeader('Content-Type', 'application/zip')
                ->setHeader('Content-Disposition', 'attachment; filename="' . preg_replace('/[^a-z0-9-]/', '', (string) $slug) . ($comConteudo ? '-com-conteudo' : '') . '.zip"')
                ->setBody($bytes);
        });
    }

    public function destroy($slug)
    {
        return $this->permitido(function () use ($slug) {
            $this->service->remover((string) $slug);

            return $this->respondDeleted(['slug' => $slug, 'message' => 'Template removido.']);
        });
    }
}
