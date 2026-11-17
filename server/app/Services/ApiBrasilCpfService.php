<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Serviço para consulta de CPF (dados cadastrais, endereço e informações
 * de crédito) via API Brasil (gateway.apibrasil.io).
 */
class ApiBrasilCpfService extends BaseService
{

    /**
     * Consulta um CPF na API Brasil.
     *
     * @param string $cpf CPF a ser consultado (com ou sem máscara).
     * @return array Estrutura ['success' => bool, 'data' => array|null, 'message' => string, 'statusCode' => int]
     */
    public function consultarCpf(string $cpf): array
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return [
                'success'    => false,
                'data'       => null,
                'message'    => 'CPF inválido. Informe os 11 dígitos do documento.',
                'statusCode' => 422,
            ];
        }

        $token       = env('APIBRASIL_TOKEN') ?: getenv('APIBRASIL_TOKEN');

        if (empty($token)) {
            log_message('error', 'ApiBrasilCpfService: credencial APIBRASIL_TOKEN não configurada.');
            return [
                'success'    => false,
                'data'       => null,
                'message'    => 'Integração com a API Brasil não está configurada.',
                'statusCode' => 500,
            ];
        }

        $endpoint = env('APIBRASIL_CPF_ENDPOINT') ?: getenv('APIBRASIL_CPF_ENDPOINT');

        $payload = ['cpf' => $cpf];
        $tipo    = env('APIBRASIL_CPF_TIPO') ?: getenv('APIBRASIL_CPF_TIPO');
        if (!empty($tipo)) {
            $payload['tipo'] = $tipo;
        }

        $homolog = env('APIBRASIL_HOMOLOG') ?: getenv('APIBRASIL_HOMOLOG');
        if ($homolog !== null && $homolog !== '') {
            $payload['homolog'] = filter_var($homolog, FILTER_VALIDATE_BOOLEAN);
        }

        $response = $this->executeCurl($endpoint, $payload, (string) $token);

        if (isset($response['curl_error'])) {
            log_message('error', 'ApiBrasilCpfService: erro de conexão - ' . $response['curl_error']);
            return [
                'success'    => false,
                'data'       => null,
                'message'    => 'Falha ao conectar ao serviço de consulta. Tente novamente em instantes.',
                'statusCode' => 502,
            ];
        }

        $body = json_decode($response['body'], true);

        if (!is_array($body)) {
            return [
                'success'    => false,
                'data'       => null,
                'message'    => 'Resposta inválida do serviço de consulta.',
                'statusCode' => 502,
            ];
        }

        if ($response['http_code'] !== 200 || ($body['error'] ?? false) === true) {
            return [
                'success'    => false,
                'data'       => $body,
                'message'    => $body['message'] ?? 'Não foi possível concluir a consulta.',
                'statusCode' => $response['http_code'] !== 200 ? $response['http_code'] : 422,
            ];
        }

        return [
            'success'    => true,
            'data'       => $body,
            'message'    => $body['message'] ?? 'Consulta realizada com sucesso.',
            'statusCode' => 200,
        ];
    }

    /**
     * Executa a chamada cURL para a API Brasil.
     */
    private function executeCurl(string $url, array $payload, string $token): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
        log_message('info','url:'.$url);
        log_message('info','payload:'.json_encode($payload));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['curl_error' => $curlError];
        }

        return [
            'http_code' => $httpCode,
            'body'      => $body,
        ];
    }
}
