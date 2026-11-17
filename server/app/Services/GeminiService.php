<?php

namespace App\Services;

use App\Interfaces\AIServiceInterface;
use App\Traits\GeminiTrait;
use Exception;

/**
 * Implementação do serviço de IA utilizando o GeminiTrait para chamadas ao Google Gemini API.
 */
class GeminiService extends BaseService implements AIServiceInterface
{
    use GeminiTrait;

    public function __construct()
    {
        $this->bootGeminiTrait();
    }

    /**
     * {@inheritdoc}
     */
    public function analyzeNotification(string $text, array $context = []): array
    {
        if (empty($text)) {
            return [];
        }

        $contextString = "";
        if (!empty($context)) {
            $contextString = "\nContexto do Processo:\n";
            if (!empty($context['processo'])) {
                $p = $context['processo'];
                $contextString .= "- Título: " . ($p['titulo_processo'] ?? 'N/A') . "\n";
                $contextString .= "- Comentários: " . ($p['comentario'] ?? 'N/A') . "\n";
            }
            if (!empty($context['argumentos'])) {
                $contextString .= "- Argumentos Estratégicos: " . implode(', ', array_column($context['argumentos'], 'titulo')) . "\n";
            }
            if (!empty($context['tarefas'])) {
                $contextString .= "- Tarefas já existentes (EVITE DUPLICAR): " . implode(', ', array_column($context['tarefas'], 'tarefa')) . "\n";
            }
        }

        $prompt = "Você é um assistente jurídico brasileiro especializado em triagem de intimações. 
        Analise o texto da intimação fornecido e extraia informações estruturadas. 
        Considere o contexto do processo fornecido para melhor precisão e para evitar sugerir tarefas que já existam.
        Retorne APENAS um objeto JSON válido (sem markdown) com a seguinte estrutura:
        {
            \"categoria\": \"(prazo|audiencia|decisao|ciencia)\",
            \"resumo\": \"(máximo 100 caracteres)\",
            \"tipo_ato\": \"(tipo do ato, ex.: sentença, despacho, decisão, citação; senão null)\",
            \"prazo_dias\": \"(número inteiro de dias do prazo conferido pelo ato, ex.: 15; senão null. NÃO calcule a data de vencimento)\",
            \"data_critica\": \"(YYYY-MM-DD somente para data certa indicada no texto, como audiência ou perícia; para prazos em dias use prazo_dias e deixe null)\",
            \"valor_condenacao\": \"(valor numérico se houver condenação mencionada, senão null)\",
            \"urgencia\": \"(alta|media|baixa)\",
            \"partes_relevantes\": [\"nome1\", \"nome2\"],
            \"sugestao_tarefa\": {
                \"tarefa\": \"(Título sugerido para a tarefa)\",
                \"detalhes\": \"(Instruções sugeridas para o advogado)\"
            },
            \"detalhes_extracao\": {
                \"peritos\": [\"nome1\"],
                \"testemunhas\": [\"nome1\"],
                \"fundamentacao_legal\": [\"Art. X da Lei Y\"]
            },
            \"score_confianca\": 0.0 a 1.0
        }
        
        {$contextString}

        Texto da Intimação:
        " . $text;

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return [];
            }

            $result = $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, [
                'categoria' => 'ciencia',
                'resumo' => 'Não foi possível extrair o resumo.',
                'data_critica' => null,
                'tipo_ato' => null,
                'prazo_dias' => null,
                'valor_condenacao' => null,
                'urgencia' => 'baixa',
                'partes_relevantes' => [],
                'sugestao_tarefa' => [],
                'detalhes_extracao' => [],
                'score_confianca' => 0.0,
            ]);

            return $result;
        } catch (Exception $e) {
            log_message('error', 'GeminiService::analyzeNotification failure: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkRelevance(string $text, array $context): array
    {
        $officeData = json_encode($context);
        $prompt = "Você é um assistente jurídico brasileiro. 
        Verifique se a intimação abaixo pertence ao escritório jurídico baseado no contexto fornecido (nomes de advogados e clientes).
        A intimação pode não conter a OAB, mas pode citar nomes de clientes ou integrantes da equipe.
        Retorne APENAS um JSON válido:
        {
            \"is_relevant\": true/false,
            \"reason\": \"Breve explicação em português\",
            \"score\": 0.0 a 1.0
        }

        Contexto do Escritório:
        " . $officeData . "

        Texto da Intimação:
        " . $text;

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return ['is_relevant' => false, 'reason' => 'Falha na resposta da IA', 'score' => 0.0];
            }

            $result = $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, [
                'is_relevant' => false,
                'reason' => 'Erro na análise da IA',
                'score' => 0.0,
            ]);

            return $result;
        } catch (Exception $e) {
            log_message('error', 'GeminiService::checkRelevance failure: ' . $e->getMessage());
            return ['is_relevant' => false, 'reason' => 'Falha interna', 'score' => 0.0];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generatePetition(string $tipoPeticao, array $processo): string
    {
        $prompt = $this->construirPromptPeticao($tipoPeticao, $processo);

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return $response->candidates[0]->content->parts[0]->text;
            }

            throw new Exception("Falha ao gerar a petição com IA.");
        } catch (Exception $e) {
            log_message('error', 'GeminiService::generatePetition failure: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateCaseStudy(array $caso): string
    {
        $prompt = "Você é um assistente jurídico experiente e muito preocupado com a qualidade técnica do trabalho.

        Com base nos dados do caso abaixo, elabore um Estudo de Caso completo seguindo ESTRITAMENTE este formato:\n\n"
            . "TITULO\n"
            . "1. Identificação das Partes:\n"
            . "2. Resumo dos Fatos:\n"
            . "3. Questão Jurídica:\n"
            . "4. Fundamentação Legal e Doutrinária:\n"
            . "5. Análise do Caso Concreto:\n"
            . "6. Conclusão e Parecer:\n\n"
            . "7. Proposta Financeira:\n\n"
            . "Dados do Caso:\n"
            . "Título: " . $caso['titulo'] . "\n"
            . "Cliente: " . ($caso['cliente_nome'] ?? 'N/A') . "\n"
            . "Tipo: " . $caso['tipo'] . "\n"
            . "Descrição: " . $caso['descricao'] . "\n"
            . "Status: " . $caso['status'] . "\n";

        $prompt .= "Faça checagem dupla de qualquer jurisprudencia ou lei citada no caso e
        certifique-se de que todas as leis e jurisprudencias estão atualizadas e
        que não haja nenhuma lei ou jurisprudencia que possa ter sido alterada ou revogada.
        Responda em HTML para que eu possa formatar o texto. Avalie casos semelhante e a complexidade para elaborar a melhor proposta financeira possível";

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 8192,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return trim($response->candidates[0]->content->parts[0]->text);
            }

            throw new Exception("Não foi possível gerar o estudo de caso com IA.");
        } catch (Exception $e) {
            log_message('error', 'GeminiService::generateCaseStudy failure: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateSummary(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $prompt = "Você é um assistente jurídico brasileiro especializado em síntese de documentos. 
        Gere um resumo executivo, técnico e objetivo do seguinte texto jurídico.
        O resumo deve destacar os pontos principais, decisões, prazos e obrigações mencionadas.
        
        Texto Jurídico:
        " . $text;

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2048,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return trim($response->candidates[0]->content->parts[0]->text);
            }

            return 'Não foi possível gerar o resumo do texto.';
        } catch (Exception $e) {
            log_message('error', 'GeminiService::generateSummary failure: ' . $e->getMessage());
            return 'Erro ao processar resumo com IA.';
        }
    }

    /**
     * Constrói o prompt adequado para cada tipo de petição.
     */
    private function construirPromptPeticao(string $tipoPeticao, array $processo): string
    {
        $basePrompt = "Você é um advogado experiente. Escreva uma **{$tipoPeticao}** completa em formato html para que possa usar a formatação para o seguinte caso:\n\n";

        $basePrompt .= "Resumo do Caso (Comentários do Processo):\n";
        $basePrompt .= ($processo['processo']['comentario'] ?? 'Sem comentários disponíveis.') . "\n\n";

        $basePrompt .= "Dados do Processo:\n";
        $basePrompt .= "Número: " . ($processo['processo']['numeroprocessocommascara'] ?? 'N/A') . "\n";
        $basePrompt .= "Título: " . ($processo['processo']['titulo_processo'] ?? 'N/A') . "\n";
        $basePrompt .= "Cliente: " . ($processo['processo']['nome_cliente'] ?? 'N/A') . "\n";
        $basePrompt .= "Órgão: " . ($processo['processo']['nomeOrgao'] ?? 'N/A') . "\n\n";

        if (!empty($processo['partes'])) {
            $basePrompt .= "Partes do Processo:\n";
            foreach ($processo['partes'] as $parte) {
                $polo = $parte['polo'] === 'A' ? 'Ativo' : 'Passivo';
                $basePrompt .= "- {$parte['nome']} (Polo {$polo})\n";
            }
            $basePrompt .= "\n";
        }

        $basePrompt .= "\nInstruções Específicas:\n";

        switch ($tipoPeticao) {
            case 'Petição Inicial':
                $basePrompt .= "1. Use a estrutura padrão de petição inicial (Endereçamento, Qualificação, Fatos, Direito, Pedidos, Valor da Causa).\n";
                $basePrompt .= "2. Seja detalhado na exposição dos fatos.\n";
                $basePrompt .= "3. Fundamente juridicamente com base nos fatos e na legislação aplicável.\n";
                break;

            case 'Contestação':
                $basePrompt .= "1. Refute os argumentos da parte contrária ponto a ponto.\n";
                $basePrompt .= "2. Apresente preliminares se aplicável (ilegitimidade, prescrição, etc).\n";
                $basePrompt .= "3. No mérito, contra-argumente com base jurídica sólida.\n";
                break;

            case 'Apelação':
                $basePrompt .= "1. Demonstre o erro da decisão recorrida.\n";
                $basePrompt .= "2. Apresente razões de fato e de direito.\n";
                $basePrompt .= "3. Formule pedidos claros (reforma total ou parcial).\n";
                break;

            case 'Embargos de Declaração':
                $basePrompt .= "1. Aponte objetivamente a obscuridade, contradição ou omissão.\n";
                $basePrompt .= "2. Seja conciso e específico.\n";
                $basePrompt .= "3. Evite rediscutir o mérito (exceto embargos infringentes).\n";
                break;

            default:
                $basePrompt .= "1. Use a estrutura adequada para este tipo de petição.\n";
                $basePrompt .= "2. Seja técnico e fundamentado.\n";
                $basePrompt .= "3. Utilize a legislação aplicável quando pertinente.\n";
        }

        $basePrompt .= "4. O formato deve ser html.\n";
        $basePrompt .= "5. Seja formal, técnico, objetivo e faça dupla checagem de todas as jurisprudencias e lei citadas.\n";

        return $basePrompt;
    }

    /**
     * {@inheritdoc}
     */
    public function generateChecklist(string $context): array
    {
        if (empty($context)) {
            return [];
        }

        $prompt = "Você é um especialista em Legal Operations e gestão de processos jurídicos.
        Com base na descrição fornecida, sugira um checklist estruturado de providências necessárias.
        Retorne APENAS um objeto JSON válido (sem markdown) com a seguinte estrutura:
        {
            \"checklists\": [
                {
                    \"secao\": \"Nome da Seção (ex: Documentação, Prazos, Diligências)\",
                    \"itens\": [
                        {
                            \"titulo\": \"Título curto da tarefa\",
                            \"descricao\": \"Breve explicação do que deve ser feito\"
                        }
                    ]
                }
            ]
        }

        Contexto do Caso/Processo:
        " . $context;

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return [];
            }

            $result = $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, [
                'checklists' => [],
            ]);

            return $result['checklists'] ?? [];
        } catch (Exception $e) {
            log_message('error', 'GeminiService::generateChecklist failure: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function predictProcessOutcome(array $processData): array
    {
        $defaults = [
            'risco_predito' => 'Possível',
            'resultado_predito' => 'Não Finalizado',
            'justificativa_estrategica' => 'Não foi possível realizar a análise preditiva.',
            'score_confianca' => 0.0,
        ];

        if (empty($processData['processo'])) {
            return $defaults;
        }

        $p = $processData['processo'];
        $movimentos = array_map(fn($m) => $m['nome'], array_slice($processData['movimentacoes'] ?? [], 0, 10));

        $prompt = "Você é um especialista em Estratégia Jurídica e Análise de Dados.
        Analise os dados do processo abaixo e preveja o risco financeiro e o desfecho provável.
        Retorne APENAS um objeto JSON válido:
        {
            \"risco_predito\": \"(Provável|Possível|Remoto)\",
            \"resultado_predito\": \"(Sucesso|Sucesso Parcial|Derrota)\",
            \"justificativa_estrategica\": \"(Breve análise técnica baseada nos fatos e movimentos)\",
            \"score_confianca\": 0.0 a 1.0
        }

        Dados do Processo:
        Título: " . ($p['titulo_processo'] ?? 'N/A') . "
        Órgão: " . ($p['nomeOrgao'] ?? 'N/A') . "
        Valor da Causa: " . ($p['valorCausa'] ?? '0.00') . "
        Comentários: " . ($p['comentario'] ?? 'N/A') . "
        Últimos Movimentos: " . implode('; ', $movimentos);

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return $defaults;
            }

            return $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, $defaults);
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::predictProcessOutcome failure: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateClosingReport(array $processData): string
    {
        if (empty($processData['processo'])) {
            return '<p>Dados do processo não encontrados.</p>';
        }

        $p = $processData['processo'];
        $movimentos = array_map(fn($m) => $m['nome'] . ' (' . (($m['dataHora'] ?? $m['data'] ?? null) ? date('d/m/Y', strtotime($m['dataHora'] ?? $m['data'])) : 'N/D') . ')', array_slice($processData['movimentacoes'] ?? [], 0, 15));

        $prompt = "Você é um advogado sênior escrevendo um relatório final de encerramento de processo para o seu cliente.
        O tom deve ser profissional, empático e claro (evite juridiquês excessivo).
        
        Escreva um Relatório de Encerramento contendo:
        1. Título do Relatório (Ex: Relatório de Encerramento de Processo)
        2. Saudação ao cliente
        3. Resumo do Caso (qual era o objetivo)
        4. Desfecho (o que foi decidido/alcançado)
        5. Próximos passos (se houver, ex: arquivamento definitivo, levantamento de alvará) ou conclusão final.
        
        Retorne o texto formatado em HTML para ser exibido na área do cliente. Utilize tags como <h3>, <p>, <ul>, <li>, <strong>. Não inclua a tag <html> ou <body>, apenas o conteúdo.
        
        Dados do Processo:
        Título: " . ($p['titulo_processo'] ?? 'N/A') . "
        Órgão: " . ($p['nomeOrgao'] ?? 'N/A') . "
        Cliente: " . ($p['nome_cliente'] ?? 'Prezado Cliente') . "
        Comentários: " . ($p['comentario'] ?? 'N/A') . "
        Resultado (Status Interno): " . ($p['resultado'] ?? 'Finalizado') . "
        Últimos Movimentos Relevantes: " . implode('; ', $movimentos);

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 4096,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);

            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return trim($response->candidates[0]->content->parts[0]->text);
            }

            return '<p>Ocorreu um erro ao gerar o relatório automático.</p>';
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::generateClosingReport failure: ' . $e->getMessage());
            return '<p>Ocorreu um erro ao gerar o relatório automático. Por favor, contate o escritório.</p>';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function suggestStrategicArguments(array $processData): array
    {
        $defaults = [
            'argumentos' => [],
            'teses_relacionadas' => [],
            'estrategia_recomendada' => 'Não foi possível gerar sugestões estratégicas.',
        ];

        if (empty($processData['processo'])) {
            return $defaults;
        }

        $p = $processData['processo'];
        $ramo = $p['ramo_direito_nome'] ?? 'Direito Geral';

        $prompt = "Você é um estrategista jurídico sênior especializado em {$ramo}.
        Com base nos dados do processo e seu histórico, sugira os melhores argumentos jurídicos e teses aplicáveis.
        Retorne APENAS um objeto JSON válido:
        {
            \"argumentos\": [
                {
                    \"titulo\": \"Título do argumento\",
                    \"descricao\": \"Explicação resumida do argumento e por que usá-lo\"
                }
            ],
            \"teses_relacionadas\": [\"Tese X do STJ\", \"Súmula Y\"],
            \"estrategia_recomendada\": \"Resumo da melhor conduta processual no momento\"
        }

        Dados do Processo:
        Título: " . ($p['titulo_processo'] ?? 'N/A') . "
        Órgão: " . ($p['nomeOrgao'] ?? 'N/A') . "
        Comentários: " . ($p['comentario'] ?? 'N/A') . "
        Ramo do Direito: {$ramo}";

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return $defaults;
            }
            return $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, $defaults);
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::suggestStrategicArguments failure: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function interpretMovement(string $movementText, array $context = []): string
    {
        if (empty($movementText)) {
            return 'Texto do movimento não fornecido.';
        }

        $prompt = "Você é um tradutor jurídico que converte termos técnicos complexos para linguagem simples e acessível para o cidadão comum.
        Explique o significado do movimento processual abaixo de forma clara, curta e objetiva.
        Evite juridiquês. Comece com 'Isso significa que...'.

        Movimento:
        " . $movementText;

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return trim($response->candidates[0]->content->parts[0]->text);
            }
            return 'Não foi possível interpretar o movimento no momento.';
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::interpretMovement failure: ' . $e->getMessage());
            return 'Erro ao processar interpretação com IA.';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function analyzeMovementSentiment(string $text): array
    {
        $defaults = [
            'sentimento' => 'Neutro',
            'justificativa' => 'Não foi possível analisar o sentimento do movimento.',
            'score_confianca' => 0.0,
        ];

        if (empty($text)) {
            return $defaults;
        }

        $prompt = "Você é um analista jurídico especializado em processamento de linguagem natural.
        Analise o sentimento do movimento processual abaixo para o polo que está acompanhando o processo.
        O sentimento deve ser 'Positivo' (se for favorável, como liminares deferidas, recursos providos), 'Negativo' (se for desfavorável, como improcedência, recursos negados) ou 'Neutro' (despachos de mero expediente, citações, juntas de documentos).

        Retorne APENAS um objeto JSON válido:
        {
            \"sentimento\": \"(Positivo|Negativo|Neutro)\",
            \"justificativa\": \"(Breve explicação técnica do porquê desta classificação)\",
            \"score_confianca\": 0.0 a 1.0
        }

        Texto do Movimento:
        " . $text;

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return $defaults;
            }
            return $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, $defaults);
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::analyzeMovementSentiment failure: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function classifyMessageUrgency(string $conteudo, string $assunto, array $context = []): array
    {
        $defaults = [
            'urgencia' => 'Normal',
            'sentimento' => 'Neutro',
            'justificativa' => 'Não foi possível classificar a urgência da mensagem.',
            'score_confianca' => 0.0,
        ];

        if (empty($conteudo)) {
            return $defaults;
        }

        $prompt = "Você é um assistente jurídico responsável por triar mensagens recebidas de clientes em um escritório de advocacia.
        Classifique a urgência da mensagem abaixo como 'Urgente' (prazo processual iminente, pedido de socorro, risco de perda de direito, cliente muito insatisfeito), 'Normal' (dúvida ou solicitação de rotina) ou 'Baixa' (agradecimento ou informação que não exige resposta imediata).
        Identifique também o sentimento predominante do cliente (Positivo, Negativo ou Neutro).

        Retorne APENAS um objeto JSON válido:
        {
            \"urgencia\": \"(Urgente|Normal|Baixa)\",
            \"sentimento\": \"(Positivo|Negativo|Neutro)\",
            \"justificativa\": \"(Breve explicação do porquê desta classificação)\",
            \"score_confianca\": 0.0 a 1.0
        }

        Assunto: " . $assunto . "
        Mensagem do cliente:
        " . $conteudo . $this->formatMessageContext($context);

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return $defaults;
            }
            return $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, $defaults);
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::classifyMessageUrgency failure: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function draftMessageReply(string $conteudo, string $assunto, array $context = []): string
    {
        if (empty($conteudo)) {
            return 'Conteúdo da mensagem não fornecido.';
        }

        $prompt = "Você é um advogado redigindo uma resposta cordial e objetiva para um cliente do escritório.
        Com base na mensagem recebida abaixo (e no contexto do processo vinculado, se houver), redija um rascunho de resposta que o advogado responsável possa revisar e editar antes do envio.
        Seja cordial, direto e evite prometer resultados ou fazer promessas jurídicas categóricas. Não inclua saudação de assinatura ao final.

        Assunto: " . $assunto . "
        Mensagem do cliente:
        " . $conteudo . $this->formatMessageContext($context);

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 1024,
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if ($response && isset($response->candidates[0]->content->parts[0]->text)) {
                return trim($response->candidates[0]->content->parts[0]->text);
            }
            return 'Não foi possível gerar uma sugestão de resposta no momento.';
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::draftMessageReply failure: ' . $e->getMessage());
            return 'Erro ao gerar sugestão de resposta com IA.';
        }
    }

    /**
     * Formata o contexto do processo vinculado a uma mensagem para ser incluído no prompt.
     */
    private function formatMessageContext(array $context): string
    {
        if (empty($context['processo'])) {
            return '';
        }

        $p = $context['processo'];

        return "\n\n        Processo vinculado:
        Título: " . ($p['titulo_processo'] ?? 'N/A') . "
        Número: " . ($p['numero_processo'] ?? 'N/A') . "
        Órgão: " . ($p['nomeOrgao'] ?? 'N/A');
    }

    /**
     * {@inheritdoc}
     */
    public function suggestLiquidacaoCriteria(string $texto, array $indicesDisponiveis = []): array
    {
        $defaults = [
            'indice_correcao'      => null,
            'termo_inicial_correcao' => null,
            'juros_tipo'           => null,
            'juros_taxa_mensal'    => null,
            'juros_indice'         => null,
            'termo_inicial_juros'  => null,
            'multa_percentual'     => null,
            'honorarios_percentual' => null,
            'trechos'              => [],
            'observacoes'          => 'Não foi possível extrair critérios do texto.',
        ];

        if (trim($texto) === '') {
            return $defaults;
        }

        $prompt = "Você é um assistente de um advogado brasileiro. Leia o trecho de decisão (sentença ou acórdão) abaixo e identifique APENAS o que estiver expresso nele sobre os critérios de atualização do crédito: índice de correção monetária, termo inicial da correção, juros (simples ou compostos, taxa mensal ou índice, termo inicial), multa e honorários (percentuais).
        Não invente nada: se o texto não disser, use null. Índices permitidos (use exatamente o código): " . implode(', ', $indicesDisponiveis) . "; se a decisão citar outro índice, use null e explique em observacoes.
        Datas no formato AAAA-MM-DD, percentuais como número (ex.: 1 para 1%). juros_tipo: 'simples', 'compostos' ou null.
        Retorne APENAS um objeto JSON válido:
        {
            \"indice_correcao\": \"CODIGO ou null\",
            \"termo_inicial_correcao\": \"AAAA-MM-DD ou null\",
            \"juros_tipo\": \"simples|compostos|null\",
            \"juros_taxa_mensal\": 0.0 ou null,
            \"juros_indice\": \"CODIGO ou null\",
            \"termo_inicial_juros\": \"AAAA-MM-DD ou null\",
            \"multa_percentual\": 0.0 ou null,
            \"honorarios_percentual\": 0.0 ou null,
            \"trechos\": [\"citação literal curta que fundamenta cada critério\"],
            \"observacoes\": \"dúvidas, lacunas ou pontos que o advogado deve conferir\"
        }

        Texto da decisão:
        " . $texto;

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->callGeminiApi($payload);
            if (!$response || !isset($response->candidates[0]->content->parts[0]->text)) {
                return $defaults;
            }
            return $this->parseJsonResponse($response->candidates[0]->content->parts[0]->text, $defaults);
        } catch (\Exception $e) {
            log_message('error', 'GeminiService::suggestLiquidacaoCriteria failure: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Limpa e interpreta a resposta JSON, mesclando com valores default.
     */
    private function parseJsonResponse(string $response, array $defaults): array
    {
        $cleanResponse = trim($response);

        if (preg_match('/^```json\s+(.*)\s+```$/s', $cleanResponse, $matches)) {
            $cleanResponse = $matches[1];
        } elseif (preg_match('/^```\s+(.*)\s+```$/s', $cleanResponse, $matches)) {
            $cleanResponse = $matches[1];
        }

        $data = json_decode(trim($cleanResponse), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'JSON parse error: ' . json_last_error_msg() . ' | Content: ' . $cleanResponse);
            return $defaults;
        }

        return array_merge($defaults, $data ?? []);
    }
}
