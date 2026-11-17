<?php

declare(strict_types=1);

namespace App\Services\Checklist;

use App\Repositories\Checklist\ProcedimentosRepository;
use App\Repositories\Checklist\TemplateRepository;
use App\Services\BaseService;
use Exception;

/**
 * Serviço para gestão de Procedimentos de Checklist.
 *
 * @property ProcedimentosRepository $repository
 */
class ProcedimentoService extends BaseService
{

    /**
     * @var TemplateRepository Repositório de templates.
     */
    protected TemplateRepository $templateRepository;

    /**
     * Construtor do serviço com injeção de dependência.
     *
     * @param ProcedimentosRepository|null $repository
     * @param TemplateRepository|null $templateRepository
     */
    public function __construct(
        ?ProcedimentosRepository $repository = null,
        ?TemplateRepository $templateRepository = null
    ) {
        $this->repository = $repository ?? new ProcedimentosRepository();
        $this->templateRepository = $templateRepository ?? new TemplateRepository();
    }

    /**
     * Lista procedimentos de forma paginada.
     */
    public function listarProcedimentos(int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        return $this->repository->getPaged($page, $perPage, ['search' => $search]);
    }

    /**
     * Busca um procedimento pelo ID.
     */
    public function buscarProcedimento(int $id): array
    {
        if (!$id) {
            throw new Exception('ID do procedimento não informado');
        }

        $procedimento = $this->repository->findById($id);
        if (!$procedimento) {
            throw new Exception('Procedimento não encontrado');
        }

        return $procedimento;
    }

    /**
     * Cria um novo procedimento.
     */
    public function criarProcedimento(array $dados, int $usuarioId): int
    {
        if (empty($dados['nome'])) {
            throw new Exception('O nome do procedimento é obrigatório');
        }

        if ($this->existeProcedimentoComNome($dados['nome'])) {
            throw new Exception('Já existe um procedimento com este nome');
        }

        $dados['created_by'] = $usuarioId;

        $id = $this->create($dados);

        if (!$id) {
            throw new Exception('Erro ao criar procedimento');
        }

        return $id;
    }

    /**
     * Atualiza um procedimento existente.
     */
    public function atualizarProcedimento(int $id, array $dados, int $usuarioId): bool
    {
        $procedimento = $this->buscarProcedimento($id);

        if (isset($dados['nome']) && $dados['nome'] !== $procedimento['nome']) {
            if ($this->existeProcedimentoComNome($dados['nome'], $id)) {
                throw new Exception('Já existe um procedimento com este nome');
            }
        }

        $dados['updated_by'] = $usuarioId;

        $resultado = $this->update($id, $dados);

        if (!$resultado) {
            throw new Exception('Erro ao atualizar procedimento');
        }

        return true;
    }

    /**
     * Exclui um procedimento (soft delete).
     */
    public function excluirProcedimento(int $id, int $usuarioId): bool
    {
        $this->buscarProcedimento($id);

        // Verificar se existem templates usando este procedimento
        $templates = $this->templateRepository->getByProcedimento($id);
        if (!empty($templates)) {
            throw new Exception('Não é possível excluir o procedimento pois existem templates associados a ele');
        }

        $resultado = $this->delete($id);

        if (!$resultado) {
            throw new Exception('Erro ao excluir procedimento');
        }

        return true;
    }

    /**
     * Retorna lista de procedimentos para componentes de seleção.
     */
    public function listarParaSelect(): array
    {
        $procedimentos = $this->repository->findAll();

        $options = [];
        foreach ($procedimentos as $proc) {
            $options[$proc['id']] = $proc['nome'];
        }

        return $options;
    }

    /**
     * Verifica se já existe um procedimento com o nome informado.
     */
    private function existeProcedimentoComNome(string $nome, ?int $ignoreId = null): bool
    {
        return $this->repository->existeComNome($nome, $ignoreId);
    }
}
