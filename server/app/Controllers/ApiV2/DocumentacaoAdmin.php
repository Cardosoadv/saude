<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\ApiV2\Site\admin\RespondeErrosDoSiteTrait;
use App\Controllers\BaseController;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Services\Documentacao\EdicaoDocumentacaoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Edição das páginas da documentação (issue #711). Contrato em docs/api-documentacao.md.
 *
 * Rotas atrás de `permission:documentacao.editar` e `modulo:documentacao`.
 * Erros: 422 por campo (`messages`), 404 para página inexistente.
 */
class DocumentacaoAdmin extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?EdicaoDocumentacaoService $service = null)
    {
    }

    private function service(): EdicaoDocumentacaoService
    {
        return $this->service ??= new EdicaoDocumentacaoService();
    }

    /** GET admin/paginas: páginas do banco e de arquivo. */
    public function index()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service()->listar()]));
    }

    public function show($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service()->obter((int) $id)]));
    }

    public function create()
    {
        return $this->executar(fn () => $this->respondCreated($this->service()->criar($this->entrada())));
    }

    public function update($id)
    {
        return $this->executar(fn () => $this->respond($this->service()->atualizar((int) $id, $this->entrada())));
    }

    public function publicar($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service()->publicar((int) $id)]));
    }

    public function despublicar($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service()->despublicar((int) $id)]));
    }

    public function delete($id)
    {
        return $this->executar(function () use ($id) {
            $this->service()->excluir((int) $id);

            return $this->respondDeleted(['data' => ['id' => (int) $id]]);
        });
    }

    /** POST admin/paginas/de-arquivo/{slug...}: cópia editável de uma página de arquivo. */
    public function deArquivo(string ...$segmentos)
    {
        return $this->executar(fn () => $this->respondCreated($this->service()->copiarDoArquivo(implode('/', $segmentos))));
    }

    /** POST admin/preview: `{markdown, slug}` → `{html, sumario, avisos}`, sem gravar. */
    public function preview()
    {
        return $this->executar(function () {
            $entrada = $this->entrada();

            return $this->respond(['data' => $this->service()->preview((string) ($entrada['markdown'] ?? ''), (string) ($entrada['slug'] ?? ''))]);
        });
    }

    /** POST admin/imagens (multipart): `arquivo` e `secao`. */
    public function imagem()
    {
        return $this->executar(function () {
            $arquivo = $this->request->getFile('arquivo');
            if ($arquivo === null) {
                throw ErroDeValidacao::campo('arquivo', 'Envie a imagem no campo "arquivo" (multipart).');
            }
            if (!$arquivo->isValid()) {
                throw ErroDeValidacao::campo('arquivo', 'Falha no envio: ' . $arquivo->getErrorString() . '.');
            }

            return $this->respondCreated(['data' => $this->service()->enviarImagem(
                $arquivo->getTempName(),
                $arquivo->getClientName(),
                (string) $this->request->getPost('secao'),
            )]);
        });
    }
}
