<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ContratosService;
use App\Traits\AcessoRegistroTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller API para fornecer dados de Contratos via JSON.
 *
 * @version 1.0.0
 * @since 2026-04-26
 */
class Contratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use AcessoRegistroTrait;

    private ContratosService $service;
    protected $permissaoNecessaria = 'contratos';

    public function __construct(?ContratosService $service = null)
    {
        $this->service = $service ?? new ContratosService();
    }

    /**
     * Retorna a listagem de contratos em formato JSON.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function listar()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar o módulo de Contratos.',
            ], 403);
        }

        try {
            $filtros = [
                's'           => $this->request->getGet('s'),
                'status'      => $this->request->getGet('status'),
                'data_inicio' => $this->request->getGet('data_inicio'),
                'ramo_id'     => $this->request->getGet('ramo_id') ?? $this->request->getGet('area_atuacao'),
                'ordenar'     => $this->request->getGet('ordenar') ?? 'recente',
                'responsavel_id' => $this->request->getGet('responsavel_id'),
            ];

            $perPage = (int) ($this->request->getGet('perPage') ?? 15);

            $resultado = $this->service->listar($filtros, $perPage);

            $contratos = $resultado['contratos'] ?? [];
            $pager     = $resultado['pager'] ?? null;

            return $this->respond([
                'success' => true,
                'data'    => $contratos,
                'pagination' => [
                    'current_page' => $pager->getCurrentPage(),
                    'total_pages'  => $pager->getPageCount(),
                    'total'        => $pager->getTotal(),
                    'per_page'     => $pager->getPerPage(),
                    'links'        => $pager->links(),
                ],
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao listar contratos API: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao carregar contratos.',
            ], 500);
        }
    }
    public function estatisticas()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) return $this->failForbidden();
        try {
            return $this->respond(['success' => true, 'data' => $this->service->getEstatisticas()]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar estatísticas');
        }
    }

    public function proximosAVencer(int $limit = 5)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) return $this->failForbidden();
        try {
            return $this->respond(['success' => true, 'data' => $this->service->getProximosVencimento($limit)]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar contratos próximos ao vencimento');
        }
    }

    public function rentabilidade()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) return $this->failForbidden();
        try {
            $limit = (int) ($this->request->getGet('limit') ?? 5);
            $rentaveis = $this->service->getContratosMaisRentaveis($limit);
            $prejuizos = $this->service->getContratosMaioresPrejuizos($limit);

            return $this->respond([
                'success' => true,
                'data' => [
                    'mais_rentaveis' => $rentaveis,
                    'maiores_prejuizos' => $prejuizos
                ]
            ]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar estatísticas de rentabilidade');
        }
    }

    public function detalhes(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) return $this->failForbidden();
        if ($negado = $this->acessoAoRegistroNegado('contrato', $id)) return $negado;
        try {
            $contrato = $this->service->buscarPorId($id);
            if (!$contrato) return $this->failNotFound('Contrato não encontrado');
            return $this->respond(['success' => true, 'data' => $contrato]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar detalhes do contrato');
        }
    }

    public function salvar()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'create')) return $this->failForbidden();
        $dados = $this->request->getJSON(true) ?? [];
        $idContrato = !empty($dados['id_contrato']) ? (int) $dados['id_contrato'] : null;
        if ($idContrato && ($negado = $this->acessoAoRegistroNegado('contrato', $idContrato))) return $negado;
        $dados = $this->filtrarCamposDePrivacidade('contrato', $idContrato, $dados);
        try {
            if ($idContrato) {
                $responsavelAnterior = $this->responsavelAtual('contrato', $idContrato);
                $this->service->atualizar($idContrato, $dados);
                $this->preservarAcessoDoResponsavelAnterior('contrato', $idContrato, $responsavelAnterior);
                return $this->respond(['success' => true, 'message' => 'Contrato atualizado com sucesso']);
            } else {
                $id = $this->service->criar($dados);
                return $this->respondCreated(['success' => true, 'id' => $id, 'message' => 'Contrato criado com sucesso']);
            }
        } catch (Exception $e) {
            return $this->failServerError('Erro ao salvar contrato: ' . $e->getMessage());
        }
    }

    public function excluir(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) return $this->failForbidden();
        if ($negado = $this->acessoAoRegistroNegado('contrato', $id)) return $negado;
        try {
            $this->service->excluir($id);
            return $this->respondDeleted(['success' => true, 'message' => 'Contrato excluído com sucesso']);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao excluir contrato');
        }
    }

    public function buscar()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) return $this->failForbidden();
        $term = $this->request->getGet('term') ?? '';
        try {
            $contratos = $this->service->buscarPorTermo((string)$term);
            return $this->respond([
                'success' => true,
                'data' => $contratos
            ]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar contratos: ' . $e->getMessage());
        }
    }

}
