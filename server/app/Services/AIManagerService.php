<?php

namespace App\Services;

use App\Interfaces\AIServiceInterface;

/**
 * Proxy/Manager para rotear chamadas de IA para o provedor configurado no ambiente.
 */
class AIManagerService extends BaseService implements AIServiceInterface
{
    private function resolveProvider(string $featureEnvKey): AIServiceInterface
    {
        $provider = getenv($featureEnvKey);

        if (!$provider) {
            $provider = getenv('AI_PROVIDER_DEFAULT') ?: 'gemini';
        }

        if (strtolower($provider) === 'deepseek') {
            return new DeepSeekService();
        }

        return new GeminiService();
    }

    public function analyzeNotification(string $text, array $context = []): array
    {
        return $this->resolveProvider('AI_PROVIDER_NOTIFICATIONS')->analyzeNotification($text, $context);
    }

    public function checkRelevance(string $text, array $context): array
    {
        return $this->resolveProvider('AI_PROVIDER_NOTIFICATIONS')->checkRelevance($text, $context);
    }

    public function generatePetition(string $tipoPeticao, array $processo): string
    {
        return $this->resolveProvider('AI_PROVIDER_PETITIONS')->generatePetition($tipoPeticao, $processo);
    }

    public function generateCaseStudy(array $caso): string
    {
        return $this->resolveProvider('AI_PROVIDER_REPORTS')->generateCaseStudy($caso);
    }

    public function generateSummary(string $text): string
    {
        return $this->resolveProvider('AI_PROVIDER_REPORTS')->generateSummary($text);
    }

    public function generateChecklist(string $context): array
    {
        return $this->resolveProvider('AI_PROVIDER_CHECKLIST')->generateChecklist($context);
    }

    public function predictProcessOutcome(array $processData): array
    {
        return $this->resolveProvider('AI_PROVIDER_STRATEGY')->predictProcessOutcome($processData);
    }

    public function generateClosingReport(array $processData): string
    {
        return $this->resolveProvider('AI_PROVIDER_REPORTS')->generateClosingReport($processData);
    }

    public function suggestStrategicArguments(array $processData): array
    {
        return $this->resolveProvider('AI_PROVIDER_STRATEGY')->suggestStrategicArguments($processData);
    }

    public function interpretMovement(string $movementText, array $context = []): string
    {
        return $this->resolveProvider('AI_PROVIDER_MOVEMENT')->interpretMovement($movementText, $context);
    }

    public function analyzeMovementSentiment(string $text): array
    {
        return $this->resolveProvider('AI_PROVIDER_MOVEMENT')->analyzeMovementSentiment($text);
    }

    public function classifyMessageUrgency(string $conteudo, string $assunto, array $context = []): array
    {
        return $this->resolveProvider('AI_PROVIDER_MESSAGES')->classifyMessageUrgency($conteudo, $assunto, $context);
    }

    public function draftMessageReply(string $conteudo, string $assunto, array $context = []): string
    {
        return $this->resolveProvider('AI_PROVIDER_MESSAGES')->draftMessageReply($conteudo, $assunto, $context);
    }

    public function suggestLiquidacaoCriteria(string $texto, array $indicesDisponiveis = []): array
    {
        return $this->resolveProvider('AI_PROVIDER_STRATEGY')->suggestLiquidacaoCriteria($texto, $indicesDisponiveis);
    }
}
