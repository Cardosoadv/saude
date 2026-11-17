<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoGeradoRepository;
use App\Repositories\DocumentoModeloRepository;
use App\Repositories\DocumentosArquivosRepository;
use App\Repositories\RamosDireitoRepository;
use Exception;

/**
 * Motor de geração e finalização de documentos a partir de modelos.
 */
class DocumentoGeradoService extends BaseService
{
    protected DocumentoModeloRepository $modeloRepository;
    protected DocumentoModeloService $modeloService;
    protected ProcessosService $processosService;
    protected CasosService $casosService;
    protected ClienteService $clienteService;
    protected ContratosService $contratosService;
    protected PdfService $pdfService;
    protected GoogleDriveService $googleDriveService;
    protected DocumentosArquivosRepository $documentosArquivosRepository;
    protected RamosDireitoRepository $ramosRepository;

    public function __construct(
        ?DocumentoGeradoRepository $repository = null,
        ?DocumentoModeloRepository $modeloRepository = null,
        ?DocumentoModeloService $modeloService = null,
        ?ProcessosService $processosService = null,
        ?CasosService $casosService = null,
        ?ClienteService $clienteService = null,
        ?ContratosService $contratosService = null,
        ?PdfService $pdfService = null,
        ?GoogleDriveService $googleDriveService = null,
        ?DocumentosArquivosRepository $documentosArquivosRepository = null,
        ?RamosDireitoRepository $ramosRepository = null
    ) {
        $this->repository                   = $repository ?? new DocumentoGeradoRepository();
        $this->modeloRepository             = $modeloRepository ?? new DocumentoModeloRepository();
        $this->modeloService                = $modeloService ?? new DocumentoModeloService();
        $this->processosService             = $processosService ?? new ProcessosService();
        $this->casosService                 = $casosService ?? new CasosService();
        $this->clienteService               = $clienteService ?? new ClienteService();
        $this->contratosService             = $contratosService ?? new ContratosService();
        $this->pdfService                   = $pdfService ?? new PdfService();
        $this->googleDriveService           = $googleDriveService ?? new GoogleDriveService();
        $this->documentosArquivosRepository = $documentosArquivosRepository ?? new DocumentosArquivosRepository();
        $this->ramosRepository              = $ramosRepository ?? new RamosDireitoRepository();
    }

    /**
     * Inicia a geração de um documento: resolve os dados da entidade, processa os
     * placeholders do modelo e persiste imediatamente o rascunho resultante.
     */
    public function iniciarGeracao(int $modeloId, string $entidadeTipo, int $entidadeId, int $userId): array
    {
        if (!in_array($entidadeTipo, ['processo', 'caso'], true)) {
            throw new Exception("Tipo de entidade inválido. Utilize 'processo' ou 'caso'.");
        }

        $modelo = $this->modeloRepository->findAtivo($modeloId);
        if (!$modelo) {
            throw new Exception('Modelo de documento não encontrado ou inativo.');
        }

        if (($modelo['tipo_alvo'] ?? 'ambos') !== 'ambos' && $modelo['tipo_alvo'] !== $entidadeTipo) {
            throw new Exception("Este modelo não está disponível para geração a partir de um {$entidadeTipo}.");
        }

        $dados        = $this->resolverDadosEntidade($entidadeTipo, $entidadeId);
        $mapaAchatado = $this->achatarPlaceholders($dados);

        $conteudoResolvido = $this->modeloService->processarPlaceholders($modelo['conteudo'], $mapaAchatado);

        $id = $this->create([
            'modelo_id'               => $modeloId,
            'modelo_titulo_snapshot'  => $modelo['titulo'],
            'titulo'                  => $modelo['titulo'] . ' - ' . date('d/m/Y H:i'),
            'entidade_tipo'           => $entidadeTipo,
            'entidade_id'             => $entidadeId,
            'cliente_id'              => $dados['cliente']['id_cliente'] ?? null,
            'ramo_id'                 => $modelo['ramo_id'] ?? null,
            'conteudo_html'           => $conteudoResolvido,
            'placeholders_utilizados' => $mapaAchatado,
            'status'                  => 'Rascunho',
            'created_by'              => $userId,
        ]);

        return $this->getById($id);
    }

    /**
     * Atualiza o título do documento gerado. Assim como as etiquetas, permitido mesmo
     * após a finalização — apenas conteudo_html fica imutável.
     */
    public function atualizarTitulo(int $id, string $titulo): bool
    {
        return $this->update($id, ['titulo' => $titulo]);
    }

    /**
     * Substitui as etiquetas vinculadas a um documento gerado. Permitido mesmo após a
     * finalização — apenas o conteúdo (conteudo_html) fica imutável, não os metadados.
     */
    public function sincronizarEtiquetas(int $id, array $etiquetaIds): void
    {
        $this->repository->sincronizarEtiquetas($id, array_map('intval', $etiquetaIds));
    }

    /**
     * Retorna as etiquetas vinculadas a um documento gerado.
     */
    public function getEtiquetas(int $id): array
    {
        return $this->repository->joinEtiquetas($id);
    }

    /**
     * Busca um documento gerado por id, enriquecido com o drive_link do arquivo
     * finalizado (quando houver) — a tabela doc_documentos_gerados só guarda o
     * documento_arquivo_id, o link em si vive em documentos_arquivos.
     */
    public function getById(int $id): ?array
    {
        $documento = parent::getById($id);

        return $documento ? $this->enriquecerComArquivo($documento) : $documento;
    }

    /**
     * Anexa o drive_link (de documentos_arquivos) a um registro de documento gerado.
     */
    private function enriquecerComArquivo(array $documento): array
    {
        $documento['drive_link'] = null;

        if (!empty($documento['documento_arquivo_id'])) {
            $arquivo = $this->documentosArquivosRepository->findById((int) $documento['documento_arquivo_id']);
            $documento['drive_link'] = $arquivo['drive_link'] ?? null;
        }

        return $documento;
    }

    /**
     * Resolve os dados brutos de processo/cliente/caso/contrato a partir da entidade âncora.
     */
    protected function resolverDadosEntidade(string $tipo, int $id): array
    {
        $processo = null;
        $cliente  = null;
        $caso     = null;
        $contrato = null;

        if ($tipo === 'processo') {
            $detalhes = $this->processosService->buscarDetalhesProcesso($id);
            $processo = $detalhes['processo'] ?? null;

            if (!empty($processo['cliente_id'])) {
                $cliente = $this->clienteService->getById((int) $processo['cliente_id']);
            }
        } else {
            $caso = $this->casosService->getById($id);

            if (!$caso) {
                throw new Exception('Caso não encontrado.');
            }

            if (!empty($caso['cliente_id'])) {
                $cliente = $this->clienteService->getById((int) $caso['cliente_id']);
            }
            if (!empty($caso['contrato_id'])) {
                $contrato = $this->contratosService->getById((int) $caso['contrato_id']);
            }
            if (!empty($caso['processo_id'])) {
                $detalhesProcesso = $this->processosService->buscarDetalhesProcesso((int) $caso['processo_id']);
                $processo = $detalhesProcesso['processo'] ?? null;
            }
        }

        return [
            'processo' => $processo,
            'cliente'  => $cliente,
            'caso'     => $caso,
            'contrato' => $contrato,
            'hoje'     => [
                'data'         => date('d/m/Y'),
                'data_extenso' => $this->dataExtenso(),
            ],
        ];
    }

    /**
     * Achata os dados resolvidos em um mapa "origem.campo" => valor, pronto para
     * DocumentoModeloService::processarPlaceholders().
     */
    protected function achatarPlaceholders(array $dados): array
    {
        $mapa = [];

        if (!empty($dados['processo'])) {
            foreach (['numero_processo', 'titulo_processo', 'vara', 'tribunal', 'status', 'valorCausa', 'dataDistribuicao'] as $campo) {
                $mapa["processo.{$campo}"] = $dados['processo'][$campo] ?? '';
            }
        }

        if (!empty($dados['cliente'])) {
            $cliente = $dados['cliente'];
            foreach (['nome', 'documento', 'email', 'telefone', 'cidade', 'uf', 'nacionalidade', 'estado_civil', 'profissao'] as $campo) {
                $mapa["cliente.{$campo}"] = $cliente[$campo] ?? '';
            }
            $mapa['cliente.endereco'] = $this->montarEnderecoCliente($cliente);
        }

        if (!empty($dados['caso'])) {
            foreach (['titulo', 'tipo', 'descricao', 'status', 'area_do_direito', 'valor_causa'] as $campo) {
                $mapa["caso.{$campo}"] = $dados['caso'][$campo] ?? '';
            }
        }

        if (!empty($dados['contrato'])) {
            foreach (['numero_contrato', 'tipo_contrato', 'objeto', 'valor', 'data_inicio', 'data_fim', 'forma_pagamento'] as $campo) {
                $mapa["contrato.{$campo}"] = $dados['contrato'][$campo] ?? '';
            }
        }

        $mapa['hoje.data']         = $dados['hoje']['data'];
        $mapa['hoje.data_extenso'] = $dados['hoje']['data_extenso'];

        return $mapa;
    }

    /**
     * Concatena os campos de endereço separados do cliente em uma única linha.
     * Não é 1:1 com uma coluna — é um campo derivado só para fins de placeholder.
     */
    private function montarEnderecoCliente(array $cliente): string
    {
        $logradouro = trim(($cliente['endereco'] ?? '') . (!empty($cliente['numero']) ? ', ' . $cliente['numero'] : ''));

        $partes = array_filter([
            $logradouro,
            $cliente['complemento'] ?? null,
            $cliente['cidade'] ?? null,
            $cliente['uf'] ?? null,
            $cliente['cep'] ?? null,
        ], static fn ($valor) => !empty($valor));

        return implode(' - ', $partes);
    }

    /**
     * Data de hoje por extenso em pt-BR (sem depender de locale do servidor).
     */
    private function dataExtenso(): string
    {
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        $hoje = new \DateTime();

        return sprintf('%d de %s de %d', (int) $hoje->format('j'), $meses[(int) $hoje->format('n')], (int) $hoje->format('Y'));
    }

    /**
     * Logo da banca em base64, para embutir no timbrado sem depender de rede
     * (Dompdf carrega o arquivo direto do disco).
     */
    private function getLogoBase64(): ?string
    {
        return \App\Libraries\Site\DadosEscritorio::atual()->logoDataUri();
    }

    /**
     * Autosave/salvamento manual do rascunho. Documentos finalizados são imutáveis.
     */
    public function salvarRascunho(int $id, string $conteudoHtml, int $userId): bool
    {
        $documento = $this->repository->findById($id);
        if (!$documento) {
            throw new Exception('Documento gerado não encontrado.');
        }
        if ($documento['status'] !== 'Rascunho') {
            throw new Exception('Documento já finalizado não pode ser editado.');
        }

        return $this->update($id, ['conteudo_html' => $conteudoHtml]);
    }

    /**
     * Finaliza um documento: gera o PDF, sobe ao Drive e grava o vínculo em documentos_arquivos.
     * O conteúdo é sempre envolvido no papel timbrado da banca (view documentos/timbrado)
     * antes da renderização do PDF.
     *
     * Antes de renderizar, reaplica processarPlaceholders() usando o snapshot
     * placeholders_utilizados da própria geração: cobre o caso de o usuário inserir
     * (via toolbar) um placeholder {{origem.campo}} durante a edição do rascunho —
     * a resolução original só roda uma vez, em iniciarGeracao(), então qualquer
     * token adicionado depois ficaria travado sem substituição.
     *
     * @param string|null $htmlFinal HTML alternativo vindo do frontend, substituindo o
     *                                conteudo_html persistido (ex.: edição de última hora).
     */
    public function finalizar(int $id, int $userId, ?string $htmlFinal = null): array
    {
        $documento = $this->repository->findById($id);
        if (!$documento) {
            throw new Exception('Documento gerado não encontrado.');
        }
        if ($documento['status'] === 'Finalizado') {
            throw new Exception('Este documento já foi finalizado.');
        }

        $placeholders = $documento['placeholders_utilizados'] ?? [];
        if (is_string($placeholders)) {
            $placeholders = json_decode($placeholders, true) ?? [];
        }

        $html = $htmlFinal ?? $documento['conteudo_html'];
        $html = $this->modeloService->processarPlaceholders($html, $placeholders);

        $this->repository->update($id, ['conteudo_html' => $html]);

        $htmlTimbrado = view('documentos/timbrado', [
            'conteudo'   => $html,
            'logoBase64' => $this->getLogoBase64(),
        ]);

        $pdfBytes = $this->pdfService->render($htmlTimbrado, $documento['titulo'] . '.pdf', [], 'A4', 'portrait', false);

        $nomeArquivo = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $documento['titulo']) . '.pdf';
        $caminhoTemp = WRITEPATH . 'uploads/' . uniqid('doc_gerado_') . '.pdf';
        file_put_contents($caminhoTemp, $pdfBytes);

        try {
            $upload = $this->googleDriveService->uploadFile($caminhoTemp, $nomeArquivo, 'application/pdf');

            $documentoArquivoId = $this->documentosArquivosRepository->create([
                'nome'       => $nomeArquivo,
                'drive_id'   => $upload['id'],
                'drive_link' => $upload['link'],
                'mime_type'  => 'application/pdf',
                'tamanho'    => strlen($pdfBytes),
            ]);

            $this->repository->marcarFinalizado($id, $documentoArquivoId, $userId);
        } finally {
            if (file_exists($caminhoTemp)) {
                unlink($caminhoTemp);
            }
        }

        return $this->getById($id) + ['drive_link' => $upload['link'] ?? null];
    }

    /**
     * Histórico de documentos gerados a partir de um processo/caso.
     */
    public function listarPorEntidade(string $tipo, int $id): array
    {
        $documentos = $this->repository->listarPorEntidade($tipo, $id);
        $ramosMap   = array_column($this->ramosRepository->findAll(), 'nome', 'id');

        return array_map(function (array $documento) use ($ramosMap) {
            $documento = $this->enriquecerComArquivo($documento);
            $documento['ramo_nome'] = $ramosMap[$documento['ramo_id']] ?? null;
            $documento['etiquetas'] = $this->repository->joinEtiquetas((int) $documento['id']);

            return $documento;
        }, $documentos);
    }

    /**
     * Lista documentos gerados com filtros, paginados.
     */
    public function listarComFiltros(array $filtros, int $perPage = 20, ?int $page = null): array
    {
        return $this->repository->listarComFiltros($filtros, $perPage, $page);
    }

    /**
     * Exclui um rascunho. Documentos finalizados não podem ser excluídos por aqui.
     */
    public function excluirRascunho(int $id): bool
    {
        $documento = $this->repository->findById($id);
        if (!$documento) {
            throw new Exception('Documento gerado não encontrado.');
        }
        if ($documento['status'] !== 'Rascunho') {
            throw new Exception('Apenas rascunhos podem ser excluídos.');
        }

        return $this->delete($id);
    }

    /**
     * Proxy para o catálogo de placeholders (fonte única backend/frontend).
     */
    public function getCatalogoPlaceholders(): array
    {
        return $this->modeloService->getCatalogoPlaceholders();
    }
}
