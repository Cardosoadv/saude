<?php

namespace App\Traits;

trait GeminiTrait
{
    // Configuração da API do Gemini
    protected $apiGeminiModel;
    protected $apiGeminiUrlBase;
    protected string $apiKeyGemini;
    protected string $geminiApiUrl;

    private function bootGeminiTrait(): void
    {
        $this->apiGeminiUrlBase = getenv('API_URL_GEMINI');
        $this->apiGeminiModel   = getenv('API_MODEL_GEMINI');
        $this->apiKeyGemini     = getenv('API_KEY_GEMINI');
    }

    private function ensureGeminiInitialized()
    {
        if (!$this->apiKeyGemini) {
            log_message('error', 'API_KEY_GEMINI não configurada no ambiente.');
            return;
        }

        $this->geminiApiUrl = $this->apiGeminiUrlBase . $this->apiGeminiModel . ':generateContent';
    }

    /**
     * Método auxiliar para chamada à API externa (Gemini).
     *
     * @param array $payload
     * @return object|null
     */
    public function callGeminiApi(array $payload): ?object
    {
        $this->ensureGeminiInitialized();

        if (empty($this->geminiApiUrl)) {
            log_message('error', 'URL da API Gemini não inicializada.');
            return null;
        }

        $ch = curl_init($this->geminiApiUrl);

        if ($ch === false) {
            log_message('error', 'Falha ao inicializar cURL Gemini');
            return null;
        }

        $payloadJson = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                "x-goog-api-key: $this->apiKeyGemini"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            log_message('error', "Erro cURL Gemini: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            log_message('error', "API Gemini retornou HTTP {$httpCode}. Response: " . substr((string)$response, 0, 500));
            return null;
        }

        if ($response) {
            $decoded = json_decode($response);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            } else {
                log_message('error', 'Erro ao decodificar JSON do Gemini: ' . json_last_error_msg());
            }
        }

        return null;
    }
}
