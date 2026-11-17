<?php

namespace App\Services;

use App\Services\ProcessosService;
use App\Services\ClienteService;
use App\Services\PdfService;
use App\Repositories\DocumentosArquivosRepository;

class DocumentoService extends BaseService
{
    /**
     * @var ProcessosService
     */
    protected $processoService;

    /**
     * @var ClienteService
     */
    protected $clienteService;

    /**
     * @var PdfService
     */
    protected $pdfService;

    public function __construct(
        ?DocumentosArquivosRepository $repository = null,
        ?ProcessosService $processoService = null,
        ?ClienteService $clienteService = null,
        ?PdfService $pdfService = null
    ) {
        $this->repository       = $repository       ?? new DocumentosArquivosRepository();
        $this->processoService  = $processoService  ?? new ProcessosService();
        $this->clienteService   = $clienteService   ?? new ClienteService();
        $this->pdfService       = $pdfService       ?? new PdfService();
    }

    /**
     * Lista todos os documentos.
     *
     * @return array
     */
    public function listarTodos(): array
    {
        return $this->repository->listarTodos();
    }

    /**
     * Salva metadados de um documento.
     *
     * @param array $data
     * @return int|string
     */
    public function salvar(array $data)
    {
        return $this->create($data);
    }

    /**
     * Gera o PDF da procuração para um processo específico.
     *
     * @param int $idProcesso ID do processo.
     * @return void
     */
    public function gerarProcuracao(int $idProcesso)
    {
        log_message('info', 'Gerando procuracao para o processo ' . $idProcesso);
        // Busca os dados do processo
        $detalhesProcesso = $this->processoService->buscarDetalhesProcesso($idProcesso);
        $processo = $detalhesProcesso['processo'];

        $cliente = null;
        if (!empty($processo['cliente_id'])) {
            $cliente = $this->clienteService->getById((int) $processo['cliente_id']);
        }

        $data = [
            'processo'  => $processo,
            'cliente'   => $cliente,
            'advogados' => $detalhesProcesso['advogados'] ?? [],
            'data_hoje' => date('d \d\e F \d\e Y'),
        ];

        $data['logoEmpresa'] = env('logoEmpresa');

        $html = view('documentos/procuracao', $data);

        $this->pdfService->render($html, "procuracao_" . ($processo['numero_processo'] ?? 'documento') . ".pdf");
        exit();
    }

    /**
     * Gera o PDF da procuração para um cliente específico.
     *
     * @param int $idCliente ID do cliente.
     * @return void
     * @throws \Exception
     */
    public function gerarProcuracaoCliente(int $idCliente)
    {
        log_message('info', 'Gerando procuracao para o cliente ' . $idCliente);
        // Busca os dados do cliente
        $cliente = $this->clienteService->getById($idCliente);

        if (!$cliente) {
            log_message('error', 'Cliente não encontrado para o ID ' . $idCliente);
            throw new \Exception("Cliente não encontrado.");
        }

        $data = [
            'processo'  => null,
            'cliente'   => $cliente,
            'advogados' => [],
            'data_hoje' => date('d \d\e F \d\e Y'),
        ];

        $data['logoEmpresa'] = env('logoEmpresa');

        $html = view('documentos/procuracao', $data);

        $this->pdfService->render($html, "procuracao_" . preg_replace('/[^a-zA-Z0-9]/', '_', $cliente['nome']) . ".pdf");
        exit();
    }
}