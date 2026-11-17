<?php

namespace App\Traits;

trait DeepSeekTrait
{
    // Configuração da API do DeepSeek
    protected $apiDeepSeekModel;
    protected string $apiKeyDeepSeek;
    protected string $deepSeekApiUrl;

    private function bootDeepSeekTrait(): void
    {
        $this->deepSeekApiUrl   = getenv('API_URL_DEEPSEEK');
        $this->apiDeepSeekModel = getenv('API_MODEL_DEEPSEEK');
        $this->apiKeyDeepSeek   = getenv('API_KEY_DEEPSEEK');
    }

    private function ensureDeepSeekInitialized()
    {
        if (!$this->apiKeyDeepSeek) {
            log_message('error', 'API_KEY_DEEPSEEK não configurada no ambiente.');
            return;
        }
    }

    /**
     * Método auxiliar para chamada à API externa (DeepSeek / padrão OpenAI).
     *
     * @param array $payload
     * @return object|null
     */
    public function callDeepSeekApi(array $payload): ?object
    {
        $this->ensureDeepSeekInitialized();

        if (empty($this->deepSeekApiUrl)) {
            log_message('error', 'URL da API DeepSeek não inicializada.');
            return null;
        }

        $ch = curl_init($this->deepSeekApiUrl);

        if ($ch === false) {
            log_message('error', 'Falha ao inicializar cURL DeepSeek');
            return null;
        }

        // Garante que o modelo está no payload (padrão OpenAI)
        if (!isset($payload['model'])) {
            $payload['model'] = $this->apiDeepSeekModel;
        }

        $payloadJson = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$this->apiKeyDeepSeek}"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            log_message('error', "Erro cURL DeepSeek: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            log_message('error', "API DeepSeek retornou HTTP {$httpCode}. Response: " . substr((string)$response, 0, 500));
            return null;
        }

        if ($response) {
            $decoded = json_decode($response);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            } else {
                log_message('error', 'Erro ao decodificar JSON do DeepSeek: ' . json_last_error_msg());
            }
        }

        return null;
    }
}
