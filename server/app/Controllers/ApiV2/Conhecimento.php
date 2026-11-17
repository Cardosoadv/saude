<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ConhecimentoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API V2 para a Base do Conhecimento (Knowledge Hub).
 */
class Conhecimento extends BaseController
{
    use ResponseTrait;

    protected ConhecimentoService $service;

    public function __construct(?ConhecimentoService $service = null)
    {
        $this->service = $service ?? new ConhecimentoService();
    }

    /**
     * Retorna os dados para o dashboard principal do Hub de Conhecimento.
     */
    public function index()
    {
        try {
            $data = [
                'ramos'         => $this->service->getArvoreRamos(),
                'estatisticas'  => $this->service->getEstatisticasDashboard(),
                'atividades'    => $this->service->getAtividadeRecente(5),
                'modelos'       => $this->service->getModelosDestaque(5),
            ];

            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao carregar dados do conhecimento: ' . $e->getMessage());
        }
    }

    /**
     * Busca global na base de conhecimento.
     */
    public function buscar()
    {
        $termo = $this->request->getGet('q');
        if (empty($termo)) {
            return $this->fail('Termo de busca não informado.', 400);
        }

        try {
            $resultados = $this->service->buscaGlobal($termo);
            return $this->respond([
                'success' => true,
                'data'    => $resultados
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Erro na busca: ' . $e->getMessage());
        }
    }

    /**
     * Lista os favoritos do usuário logado.
     */
    public function favoritos()
    {
        try {
            return $this->respond([
                'success' => true,
                'data'    => $this->service->listarFavoritos((int) auth()->id()),
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao carregar favoritos: ' . $e->getMessage());
        }
    }

    /**
     * Retorna se uma entidade específica já está favoritada pelo usuário logado.
     */
    public function favoritoStatus()
    {
        $tipo       = $this->request->getGet('tipo');
        $entidadeId = (int) $this->request->getGet('entidade_id');

        if (!in_array($tipo, ['modelo'], true) || !$entidadeId) {
            return $this->fail('Parâmetros inválidos.', 400);
        }

        $favorito = $this->service->statusFavorito((int) auth()->id(), $tipo, $entidadeId);

        return $this->respond([
            'success'  => true,
            'favorito' => $favorito !== null,
            'id'       => $favorito['id'] ?? null,
        ]);
    }

    /**
     * Favorita um modelo de documento.
     */
    public function favoritar()
    {
        $dados      = $this->request->getJSON(true) ?? $this->request->getPost();
        $tipo       = $dados['entidade_type'] ?? null;
        $entidadeId = isset($dados['entidade_id']) ? (int) $dados['entidade_id'] : null;

        if (!in_array($tipo, ['modelo'], true) || !$entidadeId) {
            return $this->fail('Dados inválidos para favoritar.', 400);
        }

        try {
            $favorito = $this->service->favoritar(
                (int) auth()->id(),
                $tipo,
                $entidadeId,
                $dados['anotacao_privada'] ?? null
            );

            return $this->respondCreated([
                'success' => true,
                'message' => 'Favorito salvo com sucesso.',
                'data'    => $favorito,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao favoritar: ' . $e->getMessage());
        }
    }

    /**
     * Atualiza a anotação privada de um favorito.
     */
    public function atualizarFavorito(int $id)
    {
        $dados = $this->request->getJSON(true) ?? $this->request->getPost();

        $ok = $this->service->atualizarAnotacaoFavorito((int) auth()->id(), $id, $dados['anotacao_privada'] ?? null);
        if (!$ok) {
            return $this->failNotFound('Favorito não encontrado.');
        }

        return $this->respond(['success' => true, 'message' => 'Favorito atualizado com sucesso.']);
    }

    /**
     * Remove um favorito.
     */
    public function desfavoritar(int $id)
    {
        $ok = $this->service->desfavoritar((int) auth()->id(), $id);
        if (!$ok) {
            return $this->failNotFound('Favorito não encontrado.');
        }

        return $this->respondDeleted(['success' => true, 'message' => 'Favorito removido com sucesso.']);
    }
}
