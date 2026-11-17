<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Documentacao\DocumentacaoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Leitura da documentação interna (issue #703). Contrato em docs/api-documentacao.md.
 *
 * Rotas atrás de `permission:documentacao.access` e `modulo:documentacao`.
 * Página de módulo inativo ou sem permissão responde 404, como se não existisse.
 */
class Documentacao extends BaseController
{
    use ResponseTrait;

    public function __construct(private ?DocumentacaoService $service = null)
    {
    }

    private function service(): DocumentacaoService
    {
        return $this->service ??= service('documentacao');
    }

    /** GET /api/v2/documentacao: índice (seções e páginas visíveis). */
    public function index()
    {
        return $this->respond(['success' => true, 'data' => $this->service()->arvore()]);
    }

    /** GET /api/v2/documentacao/paginas/{slug...} */
    public function pagina(string ...$segmentos)
    {
        $pagina = $this->service()->pagina(implode('/', $segmentos));

        return $pagina === null
            ? $this->naoEncontrada('Página da documentação não encontrada.')
            : $this->respond(['success' => true, 'data' => $pagina]);
    }

    /** GET /api/v2/documentacao/busca?q= */
    public function busca()
    {
        $q = trim((string) $this->request->getGet('q'));

        return $this->respond(['success' => true, 'data' => $this->service()->buscar(mb_substr($q, 0, 100))]);
    }

    /** GET /api/v2/documentacao/tela/{currentView}: página de ajuda contextual da tela. */
    public function tela(string $tela)
    {
        $pagina = $this->service()->porTela($tela);

        return $pagina === null
            ? $this->naoEncontrada('Esta tela não tem página de ajuda.')
            : $this->respond(['success' => true, 'data' => $pagina]);
    }

    /** GET /api/v2/documentacao/imagens/{secao}/img/{arquivo} */
    public function imagem(string ...$segmentos)
    {
        $caminho = $this->service()->caminhoDaImagem(implode('/', $segmentos));
        if ($caminho === null) {
            return $this->naoEncontrada('Imagem não encontrada.');
        }

        $etag = '"' . md5($caminho . '@' . filemtime($caminho) . '@' . filesize($caminho)) . '"';
        $this->response
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('ETag', $etag)
            ->setHeader('X-Content-Type-Options', 'nosniff');

        if ($this->request->getHeaderLine('If-None-Match') === $etag) {
            return $this->response->setStatusCode(304);
        }

        return $this->response
            ->setContentType((string) (mime_content_type($caminho) ?: 'application/octet-stream'))
            ->setBody((string) file_get_contents($caminho));
    }

    private function naoEncontrada(string $mensagem)
    {
        return $this->respond(['success' => false, 'message' => $mensagem], 404);
    }
}
