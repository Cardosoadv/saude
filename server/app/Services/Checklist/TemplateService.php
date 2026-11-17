<?php

declare(strict_types=1);

namespace App\Services\Checklist;

use App\Repositories\Checklist\TemplateRepository;
use App\Services\BaseService;
use Exception;

/**
 * Serviço para gestão de Templates de Checklist.
 *
 * @property TemplateRepository $repository
 */
class TemplateService extends BaseService
{

    /**
     * @var ProcedimentoService Serviço de procedimentos.
     */
    protected ProcedimentoService $procedimentoService;

    /**
     * Construtor do serviço com injeção de dependência.
     *
     * @param TemplateRepository|null $repository
     * @param ProcedimentoService|null $procedimentoService
     */
    public function __construct(
        ?TemplateRepository $repository = null,
        ?ProcedimentoService $procedimentoService = null
    ) {
        $this->repository           = $repository ?? new TemplateRepository();
        $this->procedimentoService  = $procedimentoService ?? new ProcedimentoService();
    }

    /**
     * Lista templates de forma paginada com filtros.
     */
    public function listarTemplates(int $page = 1, int $perPage = 10, array $filtros = []): array
    {
        return $this->repository->getPaged($page, $perPage, $filtros);
    }

    /**
     * Busca um template pelo ID com suas seções e itens.
     */
    public function buscarTemplate(int $id): array
    {
        if (!$id) {
            throw new Exception('ID do template não informado');
        }

        $template = $this->repository->getById($id);
        if (!$template) {
            throw new Exception('Template não encontrado');
        }

        return $template;
    }

    /**
     * Cria um novo template.
     */
    public function criarTemplate(array $dados, int $usuarioId): int
    {
        if (empty($dados['titulo'])) {
            throw new Exception('O título do template é obrigatório');
        }

        if (empty($dados['procedimento_id'])) {
            throw new Exception('O procedimento é obrigatório');
        }

        try {
            $this->procedimentoService->buscarProcedimento((int) $dados['procedimento_id']);
        } catch (Exception $e) {
            throw new Exception('Procedimento inválido');
        }

        $dados['created_by'] = $usuarioId;

        $id = $this->create($dados);

        if (!$id) {
            throw new Exception('Erro ao criar template');
        }

        return $id;
    }

    /**
     * Atualiza um template existente.
     */
    public function atualizarTemplate(int $id, array $dados, int $usuarioId): bool
    {
        $template = $this->buscarTemplate($id);

        if (isset($dados['procedimento_id']) && $dados['procedimento_id'] != $template['procedimento_id']) {
            try {
                $this->procedimentoService->buscarProcedimento((int) $dados['procedimento_id']);
            } catch (Exception $e) {
                throw new Exception('Procedimento inválido');
            }
        }

        $dados['updated_by'] = $usuarioId;

        $resultado = $this->update($id, $dados);

        if (!$resultado) {
            throw new Exception('Erro ao atualizar template');
        }

        return true;
    }

    /**
     * Exclui um template (soft delete) se não estiver em uso.
     */
    public function excluirTemplate(int $id, int $usuarioId): bool
    {
        $this->buscarTemplate($id);

        $emUso = $this->repository->countUsages($id);

        if ($emUso > 0) {
            throw new Exception('Não é possível excluir o template pois ele já foi utilizado em casos');
        }

        $resultado = $this->delete($id);

        if (!$resultado) {
            throw new Exception('Erro ao excluir template');
        }

        return true;
    }

    /**
     * Duplica um template, incluindo suas seções e itens.
     */
    public function duplicarTemplate(int $id, int $usuarioId): int
    {
        $this->buscarTemplate($id);

        $novoId = $this->repository->duplicar($id, $usuarioId);

        if (!$novoId) {
            throw new Exception('Erro ao duplicar template');
        }

        return $novoId;
    }

    /**
     * Adiciona uma nova seção ao template.
     */
    public function adicionarSecao(int $templateId, array $dados, int $usuarioId): int
    {
        $this->buscarTemplate($templateId);

        if (empty($dados['titulo'])) {
            throw new Exception('O título da seção é obrigatório');
        }

        $secaoId = $this->repository->salvarSecao($templateId, $dados);

        if (!$secaoId) {
            throw new Exception('Erro ao adicionar seção');
        }

        return $secaoId;
    }

    /**
     * Adiciona um novo item ao template.
     */
    public function adicionarItem(int $secaoId, array $dados, int $usuarioId): int
    {
        if (empty($dados['titulo'])) {
            throw new Exception('O título do item é obrigatório');
        }

        $secao = $this->repository->getSecaoById($secaoId);

        if (!$secao) {
            throw new Exception('Seção não encontrada');
        }

        $itemId = $this->repository->salvarItem($secaoId, $dados);

        if (!$itemId) {
            throw new Exception('Erro ao adicionar item');
        }

        return $itemId;
    }

    /**
     * Duplica uma seção e seus itens dentro de um template.
     */
    public function duplicarSecao(int $secaoId, int $usuarioId): int
    {
        $secao = $this->repository->getSecaoById($secaoId);

        if (!$secao) {
            throw new Exception('Seção não encontrada');
        }

        $novaSecaoId = $this->repository->duplicarSecao($secaoId);

        if (!$novaSecaoId) {
            throw new Exception('Erro ao duplicar seção');
        }

        return $novaSecaoId;
    }

    /**
     * Reordena as seções de um template.
     */
    public function reordenarSecoes(array $ordens, int $usuarioId): bool
    {
        if (empty($ordens)) {
            throw new Exception('Dados de ordenação inválidos');
        }

        $resultado = $this->repository->reordenarSecoes($ordens);

        if (!$resultado) {
            throw new Exception('Erro ao reordenar seções');
        }

        return true;
    }

    /**
     * Reordena os itens de uma seção.
     */
    public function reordenarItens(array $ordens, int $usuarioId): bool
    {
        if (empty($ordens)) {
            throw new Exception('Dados de ordenação inválidos');
        }

        $resultado = $this->repository->reordenarItens($ordens);

        if (!$resultado) {
            throw new Exception('Erro ao reordenar itens');
        }

        return true;
    }

    /**
     * Lista templates associados a um procedimento.
     */
    public function listarPorProcedimento(int $procedimentoId): array
    {
        return $this->repository->getByProcedimento($procedimentoId);
    }
}
