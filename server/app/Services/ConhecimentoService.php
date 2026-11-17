<?php

namespace App\Services;

use App\Repositories\RamosDireitoRepository;
use App\Repositories\DocumentoModeloRepository;
use App\Repositories\ArgumentosSugeridosRepository;
use App\Repositories\FavoritoConhecimentoRepository;
use InvalidArgumentException;

/**
 * Serviço unificado para a Base do Conhecimento.
 */
class ConhecimentoService extends BaseService
{
    /**
     * Tipos de entidade suportados pelos favoritos da Base do Conhecimento.
     */
    private const TIPOS_FAVORITO = ['modelo'];

    protected RamosDireitoRepository $ramosRepository;
    protected DocumentoModeloRepository $modelosRepository;
    protected ArgumentosSugeridosRepository $argumentosRepository;
    protected FavoritoConhecimentoRepository $favoritosRepository;

    public function __construct(
        ?RamosDireitoRepository         $ramosRepository = null,
        ?DocumentoModeloRepository      $modelosRepository = null,
        ?ArgumentosSugeridosRepository  $argumentosRepository = null,
        ?FavoritoConhecimentoRepository $favoritosRepository = null
    ) {
        $this->ramosRepository      = $ramosRepository      ?? new RamosDireitoRepository();
        $this->modelosRepository    = $modelosRepository    ?? new DocumentoModeloRepository();
        $this->argumentosRepository = $argumentosRepository ?? new ArgumentosSugeridosRepository();
        $this->favoritosRepository  = $favoritosRepository  ?? new FavoritoConhecimentoRepository();
    }

    /**
     * Busca global na base do conhecimento: modelos de documentos, argumentos e ramos do direito.
     */
    public function buscaGlobal(string $termo): array
    {
        $argumentos = $this->argumentosRepository->buscarTodosPaginado($termo, null, 1, 5);

        $resultados = [
            'modelos'         => $this->modelosRepository->buscarPorTermo($termo, 5),
            'argumentos'      => $argumentos['data'],
            'ramos'           => $this->ramosRepository->buscarPorNome($termo, 5),
        ];

        return $resultados;
    }

    /**
     * Retorna a árvore de ramos do direito.
     */
    public function getArvoreRamos(): array
    {
        return $this->ramosRepository->listarTodosHierarquico();
    }

    /**
     * Retorna modelos de documentos por ramo.
     */
    public function getModelosPorRamo(?int $ramoId = null): array
    {
        return $this->modelosRepository->listarAtivos($ramoId);
    }

    /**
     * Salva um ramo do direito.
     */
    public function salvarRamo(array $data): bool
    {
        if (!empty($data['id'])) {
            return $this->ramosRepository->update((int)$data['id'], $data);
        }
        return $this->ramosRepository->create($data) > 0;
    }

    /**
     * Deleta um ramo do direito.
     */
    public function deletarRamo(int $id): bool
    {
        return $this->ramosRepository->delete($id);
    }

    /**
     * Busca um ramo por ID.
     */
    public function getRamo(int $id): ?array
    {
        return $this->ramosRepository->findById($id);
    }

    /**
     * Retorna estatísticas consolidadas para o Dashboard.
     */
    public function getEstatisticasDashboard(): array
    {
        return [
            'modelos' => [
                'total' => $this->modelosRepository->contarAtivos(),
            ],
            'argumentos' => [
                'total' => $this->argumentosRepository->contarTodos(),
            ],
            'ramos' => [
                'total' => $this->ramosRepository->contarTodos(),
            ],
        ];
    }

    /**
     * Retorna as atividades recentes (adições/atualizações).
     */
    public function getAtividadeRecente(int $limit = 5): array
    {
        return [];
    }

    /**
     * Retorna modelos de peças mais acessados (ou recentes como fallback).
     */
    public function getModelosDestaque(int $limit = 5): array
    {
        return $this->modelosRepository->listarAtivos(); // Por enquanto retorna todos os ativos, idealmente limitados
    }

    // -------------------------------------------------------------------------
    // Favoritos
    // -------------------------------------------------------------------------

    /**
     * Lista os favoritos do usuário, já enriquecidos com o título/ementa da entidade.
     */
    public function listarFavoritos(int $userId): array
    {
        $favoritos = $this->favoritosRepository->buscarPorUsuario($userId);
        if (empty($favoritos)) {
            return [];
        }

        $modeloIds = [];
        foreach ($favoritos as $fav) {
            if ($fav['entidade_type'] === 'modelo') {
                $modeloIds[] = (int) $fav['entidade_id'];
            }
        }

        $modelosPorId = [];
        foreach ($this->modelosRepository->findByIds($modeloIds) as $modelo) {
            $modelosPorId[(int) $modelo['id']] = $modelo;
        }

        $resultado = [];
        foreach ($favoritos as $fav) {
            $entidadeId = (int) $fav['entidade_id'];
            // Favoritos legados de jurisprudência não são mais resolvidos (Issue #679).
            $entidade   = $fav['entidade_type'] === 'modelo'
                ? ($modelosPorId[$entidadeId] ?? null)
                : null;

            $resultado[] = [
                'id'               => (int) $fav['id'],
                'entidade_type'    => $fav['entidade_type'],
                'entidade_id'      => $entidadeId,
                'anotacao_privada' => $fav['anotacao_privada'],
                'created_at'       => $fav['created_at'],
                'titulo'           => $entidade['ementa'] ?? $entidade['titulo'] ?? null,
                'numero_processo'  => $entidade['numero_processo'] ?? null,
                'existe'           => $entidade !== null,
            ];
        }

        return $resultado;
    }

    /**
     * Marca uma entidade (modelo) como favorita do usuário.
     * Idempotente: se já existir, retorna o favorito existente.
     */
    public function favoritar(int $userId, string $tipo, int $entidadeId, ?string $anotacao = null): array
    {
        if (!in_array($tipo, self::TIPOS_FAVORITO, true)) {
            throw new InvalidArgumentException('Tipo de entidade inválido para favorito.');
        }

        $existente = $this->favoritosRepository->buscarUm($userId, $tipo, $entidadeId);
        if ($existente) {
            return $existente;
        }

        $id = $this->favoritosRepository->create([
            'user_id'          => $userId,
            'entidade_type'    => $tipo,
            'entidade_id'      => $entidadeId,
            'anotacao_privada' => $anotacao,
        ]);

        return $this->favoritosRepository->findById($id);
    }

    /**
     * Remove um favorito, garantindo que pertence ao usuário logado.
     */
    public function desfavoritar(int $userId, int $id): bool
    {
        $favorito = $this->favoritosRepository->findById($id);
        if (!$favorito || (int) $favorito['user_id'] !== $userId) {
            return false;
        }

        return $this->favoritosRepository->delete($id);
    }

    /**
     * Atualiza a anotação privada de um favorito, garantindo que pertence ao usuário logado.
     */
    public function atualizarAnotacaoFavorito(int $userId, int $id, ?string $anotacao): bool
    {
        $favorito = $this->favoritosRepository->findById($id);
        if (!$favorito || (int) $favorito['user_id'] !== $userId) {
            return false;
        }

        return $this->favoritosRepository->update($id, ['anotacao_privada' => $anotacao]);
    }

    /**
     * Retorna o favorito de uma entidade específica para o usuário (ou null).
     */
    public function statusFavorito(int $userId, string $tipo, int $entidadeId): ?array
    {
        return $this->favoritosRepository->buscarUm($userId, $tipo, $entidadeId);
    }
}
