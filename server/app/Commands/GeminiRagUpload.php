<?php

/**
 * @version 1.0.0
 */

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Envia um arquivo para um File Search Store do Gemini (RAG gerenciado).
 *
 * Uso:
 *   php spark gemini:rag-upload writable/uploads/Tabela_Honorarios_Advocaticios_2023.pdf
 *   php spark gemini:rag-upload <arquivo> --store fileSearchStores/xxxx
 *   php spark gemini:rag-upload <arquivo> --name "Tabela OAB 2023"
 */
class GeminiRagUpload extends BaseCommand
{
    protected $group       = 'Gemini';
    protected $name        = 'gemini:rag-upload';
    protected $description = 'Envia um documento para um File Search Store do Gemini (RAG).';
    protected $usage       = 'gemini:rag-upload <arquivo> [--store fileSearchStores/ID] [--name "Nome"]';
    protected $arguments   = ['arquivo' => 'Caminho do arquivo (relativo ao ROOTPATH ou absoluto).'];
    protected $options     = [
        '--store' => 'Store existente. Se omitido, usa GEMINI_FILE_SEARCH_STORE do .env ou cria um novo.',
        '--name'  => 'Nome de exibição do documento.',
    ];

    private const BASE = 'https://generativelanguage.googleapis.com';

    private string $apiKey = '';

    public function run(array $params)
    {
        $this->apiKey = (string) env('API_KEY_GEMINI', '');
        if ($this->apiKey === '') {
            CLI::error('API_KEY_GEMINI não definida no .env.');
            return EXIT_ERROR;
        }

        $path = $params[0] ?? null;
        if (! $path) {
            CLI::error('Informe o caminho do arquivo.');
            return EXIT_USER_INPUT;
        }
        if (! is_file($path)) {
            $path = ROOTPATH . ltrim($path, '/\\');
        }
        if (! is_file($path)) {
            CLI::error("Arquivo não encontrado: {$path}");
            return EXIT_USER_INPUT;
        }

        $store = CLI::getOption('store') ?: (string) env('GEMINI_FILE_SEARCH_STORE', '');
        if ($store === '') {
            CLI::write('Criando File Search Store...', 'yellow');
            $res = $this->request('POST', self::BASE . '/v1beta/fileSearchStores', json_encode(['displayName' => 'ordojuris-rag']), ['Content-Type: application/json']);
            $store = $res['name'] ?? '';
            if ($store === '') {
                CLI::error('Falha ao criar store: ' . json_encode($res));
                return EXIT_ERROR;
            }
            CLI::write("Store criado: {$store}", 'green');
            CLI::write("Adicione ao .env: GEMINI_FILE_SEARCH_STORE = '{$store}'", 'light_cyan');
        }

        $displayName = CLI::getOption('name') ?: basename($path);
        $mime        = mime_content_type($path) ?: 'application/pdf';

        // Upload multipart (metadados JSON + binário).
        $boundary = 'ordojuris' . bin2hex(random_bytes(8));
        $body     = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode(['displayName' => $displayName]) . "\r\n"
            . "--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n"
            . file_get_contents($path) . "\r\n--{$boundary}--";

        CLI::write("Enviando {$displayName}...", 'yellow');
        $op = $this->request(
            'POST',
            self::BASE . "/upload/v1beta/{$store}:uploadToFileSearchStore?uploadType=multipart",
            $body,
            ['Content-Type: multipart/related; boundary=' . $boundary]
        );

        if (empty($op['name'])) {
            CLI::error('Falha no upload: ' . json_encode($op));
            return EXIT_ERROR;
        }

        // Aguarda a indexação (chunking + embeddings).
        for ($i = 0; $i < 60 && empty($op['done']); $i++) {
            sleep(3);
            CLI::print('.');
            $op = $this->request('GET', self::BASE . '/v1beta/' . $op['name']);
        }
        CLI::newLine();

        if (! empty($op['error'])) {
            CLI::error('Erro na indexação: ' . json_encode($op['error']));
            return EXIT_ERROR;
        }

        CLI::write(empty($op['done']) ? 'Upload enviado; indexação ainda em andamento.' : 'Documento indexado com sucesso!', 'green');
        CLI::write('Documento: ' . ($op['response']['documentName'] ?? 'n/d'));
        CLI::write("Store: {$store}");

        return EXIT_SUCCESS;
    }

    private function request(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => array_merge(['x-goog-api-key: ' . $this->apiKey], $headers),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => $err];
        }
        curl_close($ch);

        return json_decode($raw, true) ?? ['raw' => $raw];
    }
}
