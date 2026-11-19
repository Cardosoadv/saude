<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ContratosArquivosService;
use App\Traits\AcessoRegistroTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Arquivos de contratos no Google Drive (subpasta "Contratos" da pasta do cliente).
 */
class ContratosArquivos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use AcessoRegistroTrait;

    protected ContratosArquivosService $service;
    protected $permissaoNecessaria = 'contratos';

    public function __construct(?ContratosArquivosService $service = null)
    {
        $this->service = $service ?? new ContratosArquivosService();
    }

    /**
     * GET /api/v2/contratos/{id}/arquivos
     */
    public function listar(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }
        if ($negado = $this->acessoAoRegistroNegado('contrato', $id)) {
            return $negado;
        }

        try {
            return $this->respond(['success' => true, 'data' => $this->service->listarPorContrato($id)]);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[ContratosArquivos::listar] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao listar os arquivos do contrato.'], 500);
        }
    }

    /**
     * POST /api/v2/contratos/{id}/arquivos
     *
     * Multipart com um ou mais arquivos em `arquivos[]` e, opcionalmente, `categoria`.
     */
    public function enviar(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }
        if ($negado = $this->acessoAoRegistroNegado('contrato', $id)) {
            return $negado;
        }

        $arquivos = [];
        foreach ($this->arquivosRecebidos() as $arquivo) {
            if (!$arquivo->isValid()) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Arquivo "' . $arquivo->getClientName() . '" inválido: ' . $arquivo->getErrorString(),
                ], 400);
            }
            $arquivos[] = ['path' => $arquivo->getTempName(), 'nome' => $arquivo->getClientName()];
        }

        $categoria = (string) ($this->request->getPost('categoria') ?: 'Contrato original');

        try {
            $resultado = $this->service->enviar($id, $arquivos, $categoria, auth()->id() ? (int) auth()->id() : null);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[ContratosArquivos::enviar] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => $e->getMessage()], 502);
        }

        $enviados = count($resultado['enviados']);
        $total    = $enviados + count($resultado['erros']);

        if ($enviados === 0) {
            return $this->respond([
                'success' => false,
                'message' => 'Falha ao enviar para o Google Drive: ' . $resultado['erros'][0]['mensagem'],
                'erros'   => $resultado['erros'],
            ], 502);
        }

        return $this->respond([
            'success' => true,
            'message' => $enviados === $total
                ? ($enviados === 1 ? 'Arquivo enviado com sucesso.' : "{$enviados} arquivos enviados com sucesso.")
                : "{$enviados} de {$total} arquivos enviados.",
            'data'    => $resultado['enviados'],
            'erros'   => $resultado['erros'],
        ]);
    }

    /**
     * DELETE /api/v2/contratos/arquivos/{id}
     * Move o arquivo para a lixeira do Drive e remove-o da lista.
     */
    public function excluir(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            if ($negado = $this->acessoAoRegistroNegado('contrato', $this->service->contratoDoArquivo($id))) {
                return $negado;
            }
            $this->service->excluir($id);

            return $this->respond(['success' => true, 'message' => 'Arquivo removido.']);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[ContratosArquivos::excluir] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    /**
     * @return \CodeIgniter\HTTP\Files\UploadedFile[]
     */
    private function arquivosRecebidos(): array
    {
        $recebidos = [];
        foreach (['arquivos', 'arquivo'] as $campo) {
            $envio = $this->request->getFiles()[$campo] ?? null;
            if ($envio === null) {
                continue;
            }
            foreach (is_array($envio) ? $envio : [$envio] as $arquivo) {
                $recebidos[] = $arquivo;
            }
        }

        return $recebidos;
    }
}
