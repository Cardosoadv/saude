<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Serviço para consulta de CEP via APIs externas.
 */
class CepService extends BaseService
{
    /**
     * Consulta CEP via API externa (ViaCEP) com fallback para BrasilAPI.
     *
     * @param string $cep O CEP a ser consultado (apenas números).
     * @return array|null Dados do endereço ou null em caso de falha.
     */
    public function consultarCep(string $cep): ?array
    {
        // Sanitize CEP
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        // TENTATIVA 1: VIACEP
        $data = $this->fetchViaCep($cep);

        // SE VIACEP FALHAR, TENTA BRASILAPI
        if (!$data) {
            $data = $this->fetchBrasilApi($cep);
        }

        return $data;
    }

    /**
     * Helper para consultar ViaCEP
     */
    private function fetchViaCep(string $cep): ?array
    {
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $response = $this->executeCurl($url);

        if (!$response || isset($response['curl_error']) || (isset($response['http_code']) && $response['http_code'] !== 200)) {
            return null;
        }

        $data = json_decode($response['body'], true);

        // ViaCEP retorna erro: true
        if (isset($data['erro']) && $data['erro'] === true) {
            return null;
        }

        return $data;
    }

    /**
     * Helper para consultar BrasilAPI
     */
    private function fetchBrasilApi(string $cep): ?array
    {
        $url = "https://brasilapi.com.br/api/cep/v1/{$cep}";
        $response = $this->executeCurl($url);

        if (!$response || isset($response['curl_error']) || (isset($response['http_code']) && $response['http_code'] !== 200)) {
            return null;
        }

        $brasilApiData = json_decode($response['body'], true);

        // Map BrasilAPI structure to ViaCEP structure to maintain frontend compatibility
        return [
            'logradouro' => $brasilApiData['street'] ?? '',
            'bairro'     => $brasilApiData['neighborhood'] ?? '',
            'localidade' => $brasilApiData['city'] ?? '',
            'uf'         => $brasilApiData['state'] ?? '',
            'cep'        => $brasilApiData['cep'] ?? $cep
        ];
    }

    /**
     * Executa cURL genérico
     */
    private function executeCurl(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            log_message('error', "Erro cURL para $url: $curlError");
            return ['curl_error' => $curlError];
        }

        return [
            'http_code' => $httpCode,
            'body'      => $body
        ];
    }
}
