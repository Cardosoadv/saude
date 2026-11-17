<?php

namespace App\Interfaces;

/**
 * Interface para serviços de Inteligência Artificial.
 */
interface AIServiceInterface
{
    /**
     * Analisa o texto de uma intimação e extrai metadados estruturados.
     *
     * @param string $text Texto bruto da intimação.
     * @param array $context Contexto adicional (processo, tarefas, etc).
     * @return array Dados estruturados.
     */
    public function analyzeNotification(string $text, array $context = []): array;

    /**
     * Verifica se a intimação é relevante para o escritório baseada em análise semântica.
     *
     * @param string $text Texto da intimação.
     * @param array $context Contexto adicional (nomes de advogados e clientes).
     * @return array [is_relevant => bool, reason => string, score => float]
     */
    public function checkRelevance(string $text, array $context): array;

    /**
     * Gera uma petição baseada nos dados do processo.
     *
     * @param string $tipoPeticao Tipo de petição.
     * @param array $dadosProcesso Dados estruturados do processo.
     * @return string O texto da petição gerada.
     */
    public function generatePetition(string $tipoPeticao, array $dadosProcesso): string;

    /**
     * Gera um estudo de caso completo.
     *
     * @param array $dadosCaso Dados do caso.
     * @return string O estudo de caso em formato HTML.
     */
    public function generateCaseStudy(array $dadosCaso): string;

    /**
     * Gera um resumo executivo de um texto jurídico.
     *
     * @param string $text Texto original.
     * @return string Resumo gerado.
     */
    public function generateSummary(string $text): string;

    /**
     * Gera uma sugestão de itens de checklist baseada no contexto fornecido.
     *
     * @param string $context Contexto (descrição do caso, processo ou petição).
     * @return array Lista de seções e itens sugeridos.
     */
    public function generateChecklist(string $context): array;

    /**
     * Realiza uma análise preditiva de desfecho e risco para um processo judicial.
     *
     * @param array $processData Dados completos do processo (detalhes, movimentos, etc).
     * @return array [risco_predito, resultado_predito, justificativa_estrategica, score_confianca]
     */
    public function predictProcessOutcome(array $processData): array;

    /**
     * Gera um relatório de encerramento para o cliente.
     *
     * @param array $processData Dados do processo.
     * @return string O relatório gerado em Markdown/HTML.
     */
    public function generateClosingReport(array $processData): string;

    /**
     * Sugere argumentos estratégicos baseados no ramo do direito e histórico do processo.
     *
     * @param array $processData Dados completos do processo.
     * @return array Lista de argumentos e teses sugeridas.
     */
    public function suggestStrategicArguments(array $processData): array;

    /**
     * Interpreta um movimento processual complexo para linguagem simples.
     *
     * @param string $movementText Texto do movimento.
     * @param array $context Contexto adicional (opcional).
     * @return string Interpretação em linguagem clara.
     */
    public function interpretMovement(string $movementText, array $context = []): string;

    /**
     * Analisa o sentimento de um movimento processual (Positivo, Negativo ou Neutro).
     *
     * @param string $text Texto do movimento.
     * @return array [sentimento, justificativa, score_confianca]
     */
    public function analyzeMovementSentiment(string $text): array;

    /**
     * Classifica a urgência de uma mensagem recebida de cliente e identifica o sentimento predominante.
     *
     * @param string $conteudo Conteúdo da mensagem.
     * @param string $assunto Assunto da mensagem.
     * @param array $context Contexto adicional (dados do processo vinculado, quando houver).
     * @return array [urgencia => 'Urgente'|'Normal'|'Baixa', sentimento, justificativa, score_confianca]
     */
    public function classifyMessageUrgency(string $conteudo, string $assunto, array $context = []): array;

    /**
     * Gera uma sugestão de resposta para uma mensagem de cliente, editável pelo advogado antes do envio.
     *
     * @param string $conteudo Conteúdo da mensagem original.
     * @param string $assunto Assunto da mensagem original.
     * @param array $context Contexto adicional (dados do processo vinculado, quando houver).
     * @return string Rascunho de resposta sugerido.
     */
    public function draftMessageReply(string $conteudo, string $assunto, array $context = []): string;

    /**
     * Sugere critérios de atualização de um crédito (índice, juros, termos iniciais, multa e
     * honorários) a partir do texto de uma sentença ou acórdão. É só uma sugestão: o advogado
     * confere e confirma antes de qualquer aplicação.
     *
     * @param string       $texto              Trecho da decisão.
     * @param list<string> $indicesDisponiveis Códigos dos índices cadastrados (a IA não deve citar outros).
     * @return array Chaves: indice_correcao, termo_inicial_correcao, juros_tipo, juros_taxa_mensal,
     *               juros_indice, termo_inicial_juros, multa_percentual, honorarios_percentual,
     *               trechos (list<string>) e observacoes.
     */
    public function suggestLiquidacaoCriteria(string $texto, array $indicesDisponiveis = []): array;
}
