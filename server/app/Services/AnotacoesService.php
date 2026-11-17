<?php

namespace App\Services;

use App\Repositories\AnotacoesRepository;
use Exception;

/**
 * @autor Cardoso
 * @version 0.8.0
 * @since 2025-12-20
 *
 *
 * Serviço responsável por regras de negócio relacionadas a anotações.
 *
 * @property AnotacoesRepository $repository
 */
class AnotacoesService extends BaseService
{

    /**
     * Construtor do AnotacoesService com injeção de dependência.
     *
     * @param AnotacoesRepository|null $repository
     */
    public function __construct(?AnotacoesRepository $repository = null)
    {
        $this->repository = $repository ?? new AnotacoesRepository();
    }

    /**
     * Lista anotações de um documento.
     *
     * @param string $documento Documento bruto (pode conter caracteres especiais).
     * @param int $userId ID do usuário logado.
     * @return array Lista de anotações.
     */
    public function listarPorDocumento(string $documento, int $userId): array
    {
        // Decodifica o documento caso venha com caracteres especiais via URL
        $documento = urldecode($documento);

        return $this->repository->getByDocumento($userId, $documento);
    }

    /**
     * Salva uma nova anotação.
     *
     * @param array $data Dados da anotação (titulo, anotacao, documento, privacidade, user_id).
     * @return int ID da anotação criada.
     * @throws Exception Se houver erro de validação ou persistência.
     */
    public function salvar(array $data): int
    {
        return $this->create($data);
    }

    /**
     * Exclui uma anotação.
     *
     * @param int $id ID da anotação.
     * @param int $userId ID do usuário que está tentando excluir.
     * @return array Resultado da operação ['success' => bool, 'message' => stringCode, 'code' => int].
     */
    public function excluir(int $id, int $userId): array
    {
        $anotacao = $this->repository->findById($id);

        if (!$anotacao) {
            return [
                'success' => false,
                'message' => 'Anotação não encontrada',
                'code' => 404,
            ];
        }

        // Apenas quem criou pode excluir
        if ($anotacao['user_id'] != $userId) {
            return [
                'success' => false,
                'message' => 'Você não tem permissão para excluir esta anotação',
                'code' => 403,
            ];
        }

        if ($this->repository->delete($id)) {
            return [
                'success' => true,
                'message' => 'Anotação excluída com sucesso',
                'code' => 200,
            ];
        }

        throw new Exception('Erro ao excluir anotação no banco de dados');
    }
}
