<?php

namespace App\Services;

use App\Repositories\DjeArquivoProcessadoRepository;
use App\Repositories\DjeRepository;
use App\Interfaces\AIServiceInterface;
use App\Services\ProcessosService;
use App\Services\TarefasService;

/**
 * Service para processamento de publicações do DJe.
 */
class DjeService extends BaseService
{
    private DjeArquivoProcessadoRepository $arquivoRepository;
    private ?ProcessosService $processosService = null;
    private ?TarefasService $tarefasService = null;
    private ?AIServiceInterface $aiService = null;

    /**
     * Construtor do DjeService.
     *
     * @param DjeRepository|null $repository
     * @param DjeArquivoProcessadoRepository|null $arquivoRepository
     * @param ProcessosService|null $processosService
     * @param TarefasService|null $tarefasService
     * @param AIServiceInterface|null $aiService
     */
    public function __construct(
        ?DjeRepository $repository = null,
        ?DjeArquivoProcessadoRepository $arquivoRepository = null,
        ?ProcessosService $processosService = null,
        ?TarefasService $tarefasService = null,
        ?AIServiceInterface $aiService = null
    ) {
        $this->repository               = $repository ?? new DjeRepository();
        $this->arquivoRepository        = $arquivoRepository ?? new DjeArquivoProcessadoRepository();
        $this->processosService         = $processosService;
        $this->tarefasService           = $tarefasService;
        $this->aiService                = $aiService;
    }

    protected function getProcessosService(): ProcessosService
    {
        if ($this->processosService === null) {
            $this->processosService = new ProcessosService();
        }
        return $this->processosService;
    }

    protected function getTarefasService(): TarefasService
    {
        if ($this->tarefasService === null) {
            $this->tarefasService = new TarefasService();
        }
        return $this->tarefasService;
    }

    protected function getAiService(): AIServiceInterface
    {
        if ($this->aiService === null) {
            $this->aiService = service('AIService');
        }
        return $this->aiService;
    }

    /**
     * Busca publicações que foram identificadas como decisões.
     *
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function buscarDecisoes(int $perPage = 15, int $page = 1): array
    {
        return $this->repository->buscarDecisoes($perPage, $page);
    }

    /**
     * Busca uma publicação pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Retorna o objeto de paginação.
     *
     * @return ?object
     */
    public function getPager(): ?object
    {
        return $this->repository->getPager();
    }

    /**
     * Busca os destinatários de uma publicação.
     *
     * @param int $id
     * @return array
     */
    public function getDestinatarios(int $id): array
    {
        return $this->repository->getDestinatarios($id);
    }

    /**
     * Busca os advogados de uma publicação.
     *
     * @param int $id
     * @return array
     */
    public function getAdvogados(int $id): array
    {
        return $this->repository->getAdvogados($id);
    }

    /**
     * Processa um arquivo JSON do DJe.
     *
     * @param string $filePath
     * @return array Resumo do processamento
     */
    public function processarArquivoJson(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        $jsonContent = file_get_contents($filePath);
        $hash = hash('sha256', $jsonContent);

        // Verifica se o arquivo já foi processado com sucesso
        $jaProcessado = $this->arquivoRepository->findByHash($hash);
        if ($jaProcessado && $jaProcessado['status'] === 'sucesso') {
            return [
                'status'    => 'pula',
                'mensagem'  => "Arquivo já processado anteriormente em " . date('d/m/Y H:i', strtotime($jaProcessado['created_at'])),
                'resultado' => $jaProcessado
            ];
        }

        $data = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->registrarProcessamento($filePath, $hash, 0, 0, 0, 0, 'erro', "Erro ao decodificar JSON: " . json_last_error_msg());
            throw new \Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }

        $items = $data['items'] ?? [];
        $total = count($items);
        $processados = 0;
        $erros = 0;
        $decisoesEncontradas = 0;

        foreach ($items as $item) {
            try {
                if ($this->processarItem($item)) {
                    $processados++;
                    if ($item['is_decisao_detectada'] ?? false) {
                        $decisoesEncontradas++;
                    }
                }
            } catch (\Exception $e) {
                log_message('error', "Erro ao processar item DJe {$item['id']}: " . $e->getMessage());
                $erros++;
            }
        }

        $resumo = [
            'status'               => 'sucesso',
            'total'                => $total,
            'processados'          => $processados,
            'erros'                => $erros,
            'decisoes_encontradas' => $decisoesEncontradas
        ];

        // Registra o processamento no banco
        $this->registrarProcessamento(
            $filePath, 
            $hash, 
            $total, 
            $processados, 
            $erros, 
            $decisoesEncontradas, 
            ($erros == 0 ? 'sucesso' : 'erro')
        );

        return $resumo;
    }

    /**
     * Registra o processamento do arquivo no banco de dados.
     */
    private function registrarProcessamento($filePath, $hash, $total, $processados, $erros, $decisoes, $status, $mensagem = null): void
    {
        $dados = [
            'nome_arquivo'         => basename($filePath),
            'hash_arquivo'         => $hash,
            'caminho_completo'     => $filePath,
            'total_itens'          => $total,
            'processados'          => $processados,
            'erros'                => $erros,
            'decisoes_encontradas' => $decisoes,
            'status'               => $status,
        ];

        $existente = $this->arquivoRepository->findByHash($hash);
        
        if ($existente) {
            $this->arquivoRepository->update($existente['id'], $dados);
        } else {
            $this->arquivoRepository->create($dados);
        }
    }

    /**
     * Processa um único item da publicação.
     *
     * @param array $item
     * @return bool
     */
    private function processarItem(array &$item): bool
    {
        // 1. Identificação de Decisão Judicial
        $this->detectarDecisao($item);

        // 2. Preparação dos dados para o banco
        $dadosPublicacao = [
            'comunicacao_id'        => $item['id'],
            'numero_processo'       => $item['numero_processo'],
            'data_disponibilizacao' => $item['data_disponibilizacao'],
            'sigla_tribunal'        => $item['siglaTribunal'] ?? null,
            'tipo_comunicacao'      => $item['tipoComunicacao'] ?? null,
            'texto'                 => $item['texto'] ?? null,
            'tipo_documento'        => $item['tipoDocumento'] ?? null,
            'nome_classe'           => $item['nomeClasse'] ?? null,
            'hash'                  => $item['hash'] ?? null,
            'is_decisao'            => $item['is_decisao_detectada'] ? 1 : 0,
            'decisao_tipo'          => $item['decisao_tipo'] ?? null,
        ];

        // 3. Upsert no Banco (Verifica se já existe pelo comunicacao_id)
        $existente = $this->repository->getModel()->where('comunicacao_id', $item['id'])->first();
        
        if ($existente) {
            $publicacaoId = $existente['id'];
            $this->repository->update($publicacaoId, $dadosPublicacao);
        } else {
            $publicacaoId = $this->repository->create($dadosPublicacao);
        }

        if ($publicacaoId) {
            // 4. Salvar Destinatários e Advogados
            $this->repository->salvarDestinatarios($publicacaoId, $item['destinatarios'] ?? []);
            $this->repository->salvarAdvogados($publicacaoId, $item['destinatarioadvogados'] ?? []);

            if ($item['is_decisao_detectada'] ?? false) {
                // 5. Dje-to-Calendar Sync via IA
                $this->sincronizarDjeAoCalendario($item);
            }

            return true;
        }

        return false;
    }

    /**
     * Analisa o texto e metadados para identificar decisões judiciais.
     *
     * @param array $item
     */
    private function detectarDecisao(array &$item): void
    {
        $texto = $item['texto'] ?? '';
        $tipoDoc = $item['tipoDocumento'] ?? '';
        
        $item['is_decisao_detectada'] = false;
        $item['decisao_tipo'] = null;

        // Filtro: Ignorar textos genéricos de placeholder do TJMG
        $placeholderPattern = '/A íntegra do despacho\/decisão poderá ser consultada no portal do TJMG/i';
        if (preg_match($placeholderPattern, $texto)) {
            return;
        }

        // Padrões de Tipo de Documento
        $tiposDecisao = [
            'Sentença'               => '/sentença|sentenca/i',
            'Acórdão'                => '/acórdão|acordao/i',
            'Decisão Interlocutória' => '/decisão interlocutória|decisao interlocutoria/i',
            'Decisão'                => '/^decisão$|^decisao$/i',
            'Despacho'               => '/despacho/i',
        ];

        foreach ($tiposDecisao as $tipo => $pattern) {
            if (preg_match($pattern, $tipoDoc)) {
                $item['is_decisao_detectada'] = true;
                $item['decisao_tipo'] = $tipo;
                return;
            }
        }

        // Padrões de Texto (Keywords de mérito ou decisão)
        $keywords = [
            'JULGO'               => '/\bjulgo\b/i',
            'SENTENÇA'            => '/\bsentença\b|\bsentenca\b/i',
            'ACÓRDÃO'             => '/\bacórdão\b|\bacordao\b/i',
            'DECISÃO'             => '/\bdecisão\b|\bdecisao\b/i',
            'ANTE O EXPOSTO'      => '/ante\s+o\s+exposto/i',
            'POSTO ISSO'          => '/posto\s+isso/i',
            'PELO EXPOSTO'        => '/pelo\s+exposto/i',
            'VISTOS'              => '/vistos,?(\s+etc)?\.?/i',
            'CONCEDO A LIMINAR'   => '/concedo\s+a\s+liminar|defiro\s+a\s+liminar/i',
            'INDEFIRO'            => '/\bindefiro\b/i',
            'DEFIRO'              => '/\bdefiro\b/i',
            'HOMOLOGO'            => '/\bhomologo\b/i',
            'EXTINGO O PROCESSO'  => '/extingo\s+o\s+processo|extinto\s+o\s+processo/i',
            'DETERMINO'           => '/\bdetermino\b/i',
            'SÚMULA'              => '/súmula\s+de\s+despacho|sumula\s+de\s+despacho/i',
        ];

        foreach ($keywords as $tipo => $pattern) {
            if (preg_match($pattern, $texto)) {
                $item['is_decisao_detectada'] = true;
                $item['decisao_tipo'] = $tipo;
                return;
            }
        }
    }

    /**
     * Analisa a publicação do DJe por IA para extrair prazos e criar tarefas no calendário.
     *
     * @param array $item Dados da publicação do DJe.
     */
    private function sincronizarDjeAoCalendario(array $item): void
    {
        try {
            $numeroProcesso = $item['numero_processo'] ?? null;
            if (empty($numeroProcesso)) {
                return;
            }

            // Verifica se o processo existe na base de dados
            $processo = $this->getProcessosService()->processoJaExiste($numeroProcesso);
            if (!$processo) {
                return;
            }

            // verificarProcessoExistente() busca inclusive processos excluídos (withDeleted());
            // ignora processos arquivados ou excluídos para não criar tarefas indevidas.
            if (!empty($processo['arquived_at']) || !empty($processo['deleted_at'])) {
                log_message('info', "Dje-to-Calendar Sync: Processo {$numeroProcesso} está arquivado ou excluído. Pulando sincronização.");
                return;
            }

            $texto = $item['texto'] ?? '';
            if (empty($texto)) {
                return;
            }

            // Prepara o contexto para evitar duplicação ou enriquecer o prompt
            $context = [
                'numero_processo' => $numeroProcesso,
                'is_existing'     => true,
                'process_details' => $processo,
            ];

            // Executa a análise por IA utilizando o AIService
            $dadosIA = $this->getAiService()->analyzeNotification($texto, $context);

            // Se for de fato um prazo ou conter sugestão de tarefa acionável, criamos a tarefa no calendário/Tarefas
            if (!empty($dadosIA) && isset($dadosIA['categoria']) && $dadosIA['categoria'] === 'prazo') {
                $sugestao  = $dadosIA['sugestao_tarefa'] ?? [];
                $titulo    = "DJE: " . ($sugestao['tarefa'] ?? ($sugestao['titulo'] ?? ("Tratar Publicação: " . ($dadosIA['resumo'] ?? $numeroProcesso))));
                $descricao = $sugestao['detalhes'] ?? ($sugestao['descricao'] ?? ("IA detectou um prazo em publicação do DJe com urgência " . ($dadosIA['urgencia'] ?? 'média') . "."));

                if (!empty($dadosIA['data_critica'])) {
                    $descricao .= "\nData Crítica identificada: " . date('d/m/Y', strtotime($dadosIA['data_critica']));
                }

                $dadosTarefa = [
                    'tarefa'          => mb_strtoupper($titulo),
                    'detalhes'        => $descricao,
                    'status'          => 2, // Pendente
                    'prioridade'      => ($dadosIA['urgencia'] ?? 'media') === 'alta' ? 3 : 2,
                    'processo_id'     => $processo['id_processo'] ?? $processo['id'] ?? null,
                    'cliente_id'      => $processo['cliente_id'] ?? null,
                    'responsavel'     => $processo['responsavel_id'] ?? (function_exists('user_id') && user_id() ? user_id() : 1),
                    'prazo'           => $dadosIA['data_critica'] ?? date('Y-m-d', strtotime('+5 days')),
                    'created_by'      => function_exists('user_id') && user_id() ? user_id() : 1,
                ];

                $this->getTarefasService()->salvarTarefa($dadosTarefa);
                log_message('info', "Dje-to-Calendar Sync: Tarefa automática criada para publicação {$item['id']} no processo {$numeroProcesso}.");
            }

        } catch (\Exception $e) {
            log_message('error', "Dje-to-Calendar Sync: Erro ao sincronizar publicação {$item['id']} ao calendário: " . $e->getMessage());
        }
    }
}

