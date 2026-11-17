<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\ComprovantesExtratoService;
use App\Services\Financeiro\ComprovantesService;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

class Comprovantes extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ComprovantesService $service;
    private $permissaoNecessaria = 'financeiro';

    private ?ComprovantesExtratoService $extrato;

    public function __construct(?ComprovantesService $service = null, ?ComprovantesExtratoService $extrato = null)
    {
        $this->service = $service ?? new ComprovantesService();
        $this->extrato = $extrato;
    }

    private function extrato(): ComprovantesExtratoService
    {
        return $this->extrato ??= new ComprovantesExtratoService();
    }

    /**
     * POST /api/v2/financeiro/comprovantes/testar-pasta
     * Confirma que a pasta do financeiro está configurada e aceita gravação.
     */
    public function testarPasta(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $resultado = $this->service->testarPasta();

            return $this->respond([
                'success' => true,
                'message' => 'A pasta do financeiro está acessível para gravação.',
                'data'    => $resultado,
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::testarPasta] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v2/financeiro/pagamentoDespesas/{id}/comprovantes
     * GET /api/v2/financeiro/pagamentoReceitas/{id}/comprovantes
     *
     * @param string $tipo 'despesa' ou 'receita' (definido na rota).
     */
    public function listar(string $tipo, int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            return $this->respond([
                'success' => true,
                'data'    => $this->service->listarPorPagamento($tipo, $id),
            ]);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::listar] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao listar comprovantes.'], 500);
        }
    }

    /**
     * POST /api/v2/financeiro/pagamentoDespesas/{id}/comprovantes
     * POST /api/v2/financeiro/pagamentoReceitas/{id}/comprovantes
     *
     * Multipart com um ou mais arquivos no campo `arquivos[]`. Os arquivos vão para a
     * subpasta do mês do pagamento, dentro da pasta do financeiro no Google Drive.
     *
     * @param string $tipo 'despesa' ou 'receita' (definido na rota).
     */
    public function enviar(string $tipo, int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
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

        try {
            $resultado = $this->service->enviar($tipo, $id, $arquivos, auth()->id() ? (int) auth()->id() : null);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::enviar] ' . $e->getMessage());
            $fechado = strpos($e->getMessage(), 'já está fechado') !== false;

            return $this->respond(['success' => false, 'message' => $e->getMessage()], $fechado ? 409 : 502);
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
                ? ($enviados === 1 ? 'Comprovante enviado com sucesso.' : "{$enviados} comprovantes enviados com sucesso.")
                : "{$enviados} de {$total} comprovantes enviados.",
            'data'    => $resultado['enviados'],
            'erros'   => $resultado['erros'],
        ]);
    }

    /**
     * GET /api/v2/financeiro/comprovantes/pendencias?ano=AAAA&mes=MM
     * Pagamentos do mês sem comprovante, por conta (informativo para o fechamento).
     */
    public function pendencias(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            return $this->respond([
                'success' => true,
                'data'    => $this->service->pendenciasDoMes((int) $this->request->getGet('ano'), (int) $this->request->getGet('mes')),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::pendencias] ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao consultar pendências.'], 500);
        }
    }

    /**
     * POST /api/v2/financeiro/comprovantes/lote
     *
     * Multipart: `pagamentos[]` com itens `despesa:12` / `receita:7` e/ou `movimentos[]` com ids de movimentos
     * do extrato bancário já conciliados ou lançados, e os arquivos em `arquivos[]`.
     * Os mesmos arquivos passam a ser comprovante de todos os pagamentos informados.
     */
    public function enviarLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $pagamentos = [];
        foreach ((array) $this->request->getPost('pagamentos') as $item) {
            [$tipo, $id] = array_pad(explode(':', (string) $item, 2), 2, '');
            $pagamentos[] = ['tipo' => $tipo, 'id' => (int) $id];
        }

        $movimentos = (array) $this->request->getPost('movimentos');
        if ($movimentos) {
            try {
                $pagamentos = array_merge($pagamentos, $this->extrato()->pagamentosDosMovimentos($movimentos));
            } catch (ExtratoNaoEncontradoException $e) {
                return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
            } catch (ExtratoInvalidoException $e) {
                return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        return $this->enviarParaPagamentos($pagamentos);
    }

    /**
     * POST /api/v2/financeiro/extratos/movimentos/{id}/comprovantes
     *
     * Anexa comprovantes ao pagamento que o movimento do extrato conciliou ou lançou (arquivos em `arquivos[]`).
     */
    public function enviarDoMovimento(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $pagamentos = $this->extrato()->pagamentosDosMovimentos([$id]);
        } catch (ExtratoNaoEncontradoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (ExtratoInvalidoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->enviarParaPagamentos($pagamentos);
    }

    /** @param array<int,array{tipo:string,id:int}> $pagamentos */
    private function enviarParaPagamentos(array $pagamentos): ResponseInterface
    {

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

        try {
            $resultado = $this->service->enviarLote($pagamentos, $arquivos, auth()->id() ? (int) auth()->id() : null);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::enviarLote] ' . $e->getMessage());
            $fechado = strpos($e->getMessage(), 'já está fechado') !== false;

            return $this->respond(['success' => false, 'message' => $e->getMessage()], $fechado ? 409 : 502);
        }

        if (!$resultado['enviados']) {
            return $this->respond([
                'success' => false,
                'message' => 'Falha ao enviar para o Google Drive: ' . $resultado['erros'][0]['mensagem'],
                'erros'   => $resultado['erros'],
            ], 502);
        }

        $total = $resultado['pagamentos'];
        $msg   = "Comprovante anexado a {$total} " . ($total === 1 ? 'pagamento.' : 'pagamentos.');

        return $this->respond([
            'success' => true,
            'message' => $resultado['erros'] ? $msg . ' Alguns arquivos falharam.' : $msg,
            'data'    => $resultado['enviados'],
            'erros'   => $resultado['erros'],
        ]);
    }

    /**
     * DELETE /api/v2/financeiro/comprovantes/{id}
     * Move o arquivo para a lixeira do Drive e remove o comprovante da lista.
     */
    public function excluir(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $this->service->excluir($id);

            return $this->respond(['success' => true, 'message' => 'Comprovante removido.']);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Comprovantes::excluir] ' . $e->getMessage());
            $fechado = strpos($e->getMessage(), 'já está fechado') !== false;

            return $this->respond(['success' => false, 'message' => $e->getMessage()], $fechado ? 409 : 502);
        }
    }

    /**
     * Junta os arquivos dos campos `arquivos[]` (ou `arquivo`) da requisição.
     *
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
