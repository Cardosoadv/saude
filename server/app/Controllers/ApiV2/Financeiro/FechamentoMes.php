<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\SaldoMensalService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class FechamentoMes extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected SaldoMensalService $service;
    private string $permissaoNecessaria = 'financeiro';

    public function __construct(?SaldoMensalService $service = null)
    {
        $this->service = $service ?? new SaldoMensalService();
    }

    /**
     * Lista os fechamentos realizados.
     */
    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['status' => 'error', 'message' => 'Sem permissão.'], 403);
        }

        try {
            $limit = $this->request->getGet('limit') ? (int)$this->request->getGet('limit') : 20;
            $offset = $this->request->getGet('offset') ? (int)$this->request->getGet('offset') : 0;

            $fechamentos = $this->service->listarFechamentos($limit, $offset);

            return $this->respond([
                'status' => 'success',
                'data' => $fechamentos
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\FechamentoMes::listar] ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Erro ao listar fechamentos.'], 500);
        }
    }

    /**
     * Realiza o fechamento global de um mês.
     */
    public function fechar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['status' => 'error', 'message' => 'Sem permissão para fechar mês.'], 403);
        }

        try {
            $ano = (int)$this->request->getPost('ano');
            $mes = (int)$this->request->getPost('mes');
            $userId = user_id();

            if (!$ano || !$mes) {
                return $this->respond(['status' => 'error', 'message' => 'Ano e mês são obrigatórios.'], 400);
            }

            $resultados = $this->service->fecharMesGlobal($ano, $mes, $userId);

            return $this->respond([
                'status' => 'success',
                'message' => 'Processamento de fechamento concluído.',
                'detalhes' => $resultados
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\FechamentoMes::fechar] ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Erro ao fechar mês.'], 500);
        }
    }

    /**
     * Reabre um mês fechado.
     */
    public function reabrir(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['status' => 'error', 'message' => 'Sem permissão para reabrir mês.'], 403);
        }

        try {
            $ano = (int)$this->request->getPost('ano');
            $mes = (int)$this->request->getPost('mes');
            $contaId = $this->request->getPost('conta_id') ? (int)$this->request->getPost('conta_id') : null;
            $tipo = $contaId ? 'CONTA' : 'GLOBAL';

            if (!$ano || !$mes) {
                return $this->respond(['status' => 'error', 'message' => 'Ano e mês são obrigatórios.'], 400);
            }

            $resultado = $this->service->reabrirMes($tipo, $contaId, $ano, $mes);

            if ($resultado) {
                return $this->respond(['status' => 'success', 'message' => 'Mês reaberto com sucesso.']);
            }

            return $this->respond(['status' => 'error', 'message' => 'Não foi possível reabrir o mês ou registro não encontrado.'], 404);
        } catch (Exception $e) {
            log_message('error', '[Api\FechamentoMes::reabrir] ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Erro ao reabrir mês.'], 500);
        }
    }

    /**
     * Verifica se um mês está fechado (AJAX helper).
     */
    public function verificar(): ResponseInterface
    {
        try {
            $ano = (int)$this->request->getGet('ano');
            $mes = (int)$this->request->getGet('mes');
            $contaId = $this->request->getGet('conta_id') ? (int)$this->request->getGet('conta_id') : null;
            $tipo = $contaId ? 'CONTA' : 'GLOBAL';

            $fechado = $this->service->getRepository()->isMesFechado($tipo, $contaId, $ano, $mes);

            return $this->respond([
                'status' => 'success',
                'fechado' => $fechado
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\FechamentoMes::verificar] ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Erro ao verificar status do mês.'], 500);
        }
    }
    /**
     * Sugere o próximo mês para fechamento.
     */
    public function sugerirProximo(): ResponseInterface
    {
        try {
            $sugestao = $this->service->getSugestaoProximoFechamento();
            return $this->respond([
                'status' => 'success',
                'data' => $sugestao
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\FechamentoMes::sugerirProximo] ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Erro ao sugerir próximo fechamento.'], 500);
        }
    }
} 
