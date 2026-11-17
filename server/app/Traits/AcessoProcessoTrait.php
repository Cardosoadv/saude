<?php

namespace App\Traits;

use App\Services\AcessoEntidadeService;
use App\Services\ClienteService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Checagem de acesso a um processo específico para controllers.
 *
 * Cobre a privacidade granular (responsável, advogados com acesso, bypass) e o
 * escopo dos usuários `processos.exclusive`, restritos aos processos do próprio
 * cliente. Em ambos os casos responde 404, para não revelar a existência de
 * processos restritos.
 */
trait AcessoProcessoTrait
{
    private ?AcessoEntidadeService $acessoEntidadeService = null;

    /**
     * @return ResponseInterface|null Resposta 404 quando o acesso é negado; null quando permitido.
     */
    protected function acessoAoProcessoNegado(?int $processoId): ?ResponseInterface
    {
        if (!$processoId) {
            return $this->processoNaoEncontrado();
        }

        $usuario = auth()->user();
        if ($usuario !== null && !$usuario->can('processos.access') && $usuario->can('processos.exclusive')) {
            $processo = db_connect()->table('processos')
                ->select('cliente_id')
                ->where('id_processo', $processoId)
                ->get()
                ->getRowArray();

            $clienteUsuario = (new ClienteService())->getUsuarioCliente() ?? 0;
            if ($processo === null || (int) $processo['cliente_id'] !== (int) $clienteUsuario) {
                return $this->processoNaoEncontrado();
            }
        }

        if (!$this->acessoEntidade()->podeAcessar('processo', $processoId)) {
            return $this->processoNaoEncontrado();
        }

        return null;
    }

    /**
     * Mesma checagem para clientes e contratos (privacidade granular).
     *
     * @return ResponseInterface|null Resposta 404 quando o acesso é negado; null quando permitido.
     */
    protected function acessoAoRegistroNegado(string $tipo, ?int $id): ?ResponseInterface
    {
        if (!$id || !$this->acessoEntidade()->podeAcessar($tipo, $id)) {
            return service('response')
                ->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Registro não encontrado ou sem permissão de acesso.']);
        }

        return null;
    }

    /**
     * Numa edição, responsável e visibilidade só mudam por quem pode gerenciar
     * os acessos (responsável, admin ou `privacidade.manage`). No cadastro,
     * quem cria escolhe livremente.
     */
    protected function filtrarCamposDePrivacidade(string $tipo, ?int $id, array $dados): array
    {
        if ($id && !$this->acessoEntidade()->podeGerenciar($tipo, $id)) {
            unset($dados['responsavel_id'], $dados['visibilidade']);
        }

        if (array_key_exists('responsavel_id', $dados) && ($dados['responsavel_id'] === '' || $dados['responsavel_id'] === null)) {
            $dados['responsavel_id'] = null;
        }
        if (array_key_exists('visibilidade', $dados) && !in_array($dados['visibilidade'], AcessoEntidadeService::VISIBILIDADES, true)) {
            unset($dados['visibilidade']);
        }

        return $dados;
    }

    protected function responsavelAtual(string $tipo, int $id): ?int
    {
        $meta = AcessoEntidadeService::TIPOS[$tipo];
        $row  = db_connect()->table($meta['tabela'])->select('responsavel_id')->where($meta['pk'], $id)->get()->getRowArray();

        return empty($row['responsavel_id']) ? null : (int) $row['responsavel_id'];
    }

    /**
     * Quem deixa de ser responsável continua com acesso até alguém removê-lo.
     */
    protected function preservarAcessoDoResponsavelAnterior(string $tipo, int $id, ?int $anterior): void
    {
        $atual = $this->responsavelAtual($tipo, $id);
        if ($anterior && $anterior !== $atual) {
            $this->acessoEntidade()->conceder($tipo, $id, $anterior, user_id());
        }
    }

    protected function acessoEntidade(): AcessoEntidadeService
    {
        return $this->acessoEntidadeService ??= new AcessoEntidadeService();
    }

    protected function processoNaoEncontrado(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(404)
            ->setJSON(['success' => false, 'message' => 'Processo não encontrado ou sem permissão de acesso.']);
    }
}
