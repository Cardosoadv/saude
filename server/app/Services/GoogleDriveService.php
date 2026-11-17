<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Exception;

class GoogleDriveService extends BaseService
{
    private $client;
    private $service;
    private $folderId;
    /** Indica se o token concedido enxerga arquivos que não foram criados pelo sistema. */
    private bool $leituraCompleta = false;
    private ConfiguracoesService $configuracoesService;
    /** @var array<string,string> Subpastas mensais do financeiro já resolvidas nesta requisição (AAAA-MM => id). */
    private array $pastasMensais = [];
    /** @var array<string,string> Subpastas já resolvidas nesta requisição ("<pai>/<nome>" => id). */
    private array $subpastas = [];

    /**
     * @param ConfiguracoesService|null $configuracoesService
     * @param Client|null $client
     * @param Drive|null $service Serviço do Drive (injetável nos testes).
     */
    public function __construct(?ConfiguracoesService $configuracoesService = null, ?Client $client = null, ?Drive $service = null)
    {
        $this->configuracoesService = $configuracoesService ?? new ConfiguracoesService();
        $this->client = $client ?? new Client();
        $this->client->setApplicationName('Saude');
        // DRIVE_FILE: grava só o que o sistema cria. DRIVE_READONLY: lê também o que foi
        // colocado direto na pasta pelo Drive.
        $this->client->setScopes([Drive::DRIVE_FILE, Drive::DRIVE_READONLY]);
        $this->client->setAccessType('offline');

        $clientId = $this->configuracoesService->getValor('GOOGLE_CLIENT_ID');
        $clientSecret = $this->configuracoesService->getValor('GOOGLE_CLIENT_SECRET');
        $refreshToken = $this->configuracoesService->getValor('GOOGLE_REFRESH_TOKEN');

        if ($clientId && $clientSecret && $refreshToken) {
            $this->client->setClientId($clientId);
            $this->client->setClientSecret($clientSecret);
            $token = $this->client->refreshToken($refreshToken);
            $this->leituraCompleta = $this->escopoPermiteLeitura($token['scope'] ?? '');
        } else {
            // Fallback para comportamento antigo ou erro se não configurado
            // Mas idealmente, se não tiver as vars, vai falhar mais a frente ou avisar
            log_message('error', 'Google Drive OAuth credentials missing in database configurations');
        }

        $this->service = $service ?? new Drive($this->client);

        // Pasta padrão para uploads
        $this->folderId = $this->configuracoesService->getValor('GOOGLE_DRIVE_FOLDER_ID', '');
    }

    /**
     * Faz o upload de um arquivo para o Google Drive.
     *
     * @param string $filePath Caminho do arquivo temporário no servidor.
     * @param string $fileName Nome do arquivo a ser salvo.
     * @param string $mimeType Tipo MIME do arquivo.
     * @return array Retorna um array com ID e Link do arquivo.
     * @throws Exception
     */
    public function uploadFile(string $filePath, string $fileName, string $mimeType): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado para upload.");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$this->folderId],
        ]);

        log_message('info', 'Enviando arquivo para o Google Drive:' . json_encode($fileMetadata));

        log_message('info', 'Enviando arquivo para o Google Drive: ' . $this->folderId);

        $content = file_get_contents($filePath);

        try {
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink, webContentLink',
            ]);

            // Define permissão para "anyone with the link" (opcional, ajustável conforme regra de negócio)
            // $this->setPublicPermission($file->id);

            return [
                'id' => $file->id,
                'link' => $file->webViewLink,
                'download_link' => $file->webContentLink,
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao fazer upload para o Google Drive: ' . $e->getMessage());
            throw new Exception("Falha ao enviar arquivo para o Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Faz o upload de um arquivo para o Google Drive.
     *
     * @param string $filePath Caminho do arquivo temporário no servidor.
     * @param string $fileName Nome do arquivo a ser salvo.
     * @param string $mimeType Tipo MIME do arquivo.
     * @return array Retorna um array com ID e Link do arquivo.
     * @throws Exception
     */
    public function uploadFileToFolder(string $filePath, string $fileName, string $mimeType, string $newFolderId): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado para upload.");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$newFolderId],
        ]);

        log_message('info', 'Enviando arquivo para o Google Drive:' . json_encode($fileMetadata));

        log_message('info', 'Enviando arquivo para o Google Drive: ' . $newFolderId);

        $content = file_get_contents($filePath);

        try {
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink, webContentLink',
            ]);

            // Define permissão para "anyone with the link" (opcional, ajustável conforme regra de negócio)
            // $this->setPublicPermission($file->id);

            return [
                'id' => $file->id,
                'link' => $file->webViewLink,
                'download_link' => $file->webContentLink,
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao fazer upload para o Google Drive: ' . $e->getMessage());
            throw new Exception("Falha ao enviar arquivo para o Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Busca uma pasta pelo nome.
     */
    public function searchFolder(string $folderName, ?string $parentFolderId = null): ?array
    {
        $folderName = str_replace("'", "\'", $folderName);
        $query = "mimeType='application/vnd.google-apps.folder' and name='{$folderName}' and trashed=false";
        
        if ($parentFolderId) {
            $query .= " and '{$parentFolderId}' in parents";
        }

        try {
            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name, webViewLink)',
            ]);

            if (count($results->getFiles()) > 0) {
                $file = $results->getFiles()[0];
                return [
                    'id' => $file->getId(),
                    'link' => $file->getWebViewLink(),
                ];
            }
        } catch (Exception $e) {
            log_message('error', "Erro ao buscar pasta no Drive: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Cria uma nova pasta.
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): array
    {
        $fileMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        if ($parentFolderId) {
            $fileMetadata->setParents([$parentFolderId]);
        }

        try {
            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id, webViewLink'
            ]);

            return [
                'id' => $folder->id,
                'link' => $folder->webViewLink,
            ];
        } catch (Exception $e) {
            log_message('error', 'Erro ao criar pasta no Google Drive: ' . $e->getMessage());
            throw new Exception("Falha ao criar pasta no Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Busca uma pasta, se não existir cria.
     */
    public function getOrCreateFolder(string $folderName, ?string $parentFolderId = null): array
    {
        $folder = $this->searchFolder($folderName, $parentFolderId);
        if ($folder) {
            return $folder;
        }

        return $this->createFolder($folderName, $parentFolderId);
    }

    /**
     * Define permissão pública de leitura para o arquivo (opcional).
     *
     * @param string $fileId
     */

    /**
     * Extrai o ID da pasta a partir da URL do Google Drive.
     *
     * @param string $url URL da pasta do Google Drive.
     * @return string|null Retorna o ID da pasta ou null se não encontrar.
     */
    public function extractFolderIdFromUrl(string $url): ?string
    {
        // Padrão para URLs do tipo /folders/ID
        if (preg_match('/folders\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Padrão para URLs do tipo ?id=ID
        if (preg_match('/id=([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Se a string já for o ID
        if (preg_match('/^[a-zA-Z0-9-_]{20,}$/', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Retorna o ID da pasta raiz do financeiro (GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID).
     * Aceita o ID puro ou a URL da pasta.
     *
     * @throws Exception Quando a configuração está ausente ou inválida.
     */
    public function getPastaFinanceiraId(): string
    {
        $valor = trim((string) $this->configuracoesService->getValor('GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID', ''));
        if ($valor === '') {
            throw new Exception('A pasta do financeiro no Google Drive não está configurada (GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID).');
        }

        $id = $this->extractFolderIdFromUrl($valor);
        if ($id === null) {
            throw new Exception('GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID não contém um ID de pasta válido.');
        }

        return $id;
    }

    /**
     * Retorna o ID da subpasta mensal (AAAA-MM) dentro da pasta do financeiro,
     * criando-a se ainda não existir.
     *
     * @param string $anoMes Mês de referência no formato AAAA-MM.
     * @throws Exception
     */
    public function pastaMensal(string $anoMes): string
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw new Exception("Mês inválido para a pasta do financeiro: {$anoMes}.");
        }

        if (!isset($this->pastasMensais[$anoMes])) {
            $raiz = $this->getPastaFinanceiraId();
            $this->pastasMensais[$anoMes] = $this->getOrCreateFolder($anoMes, $raiz)['id'];
        }

        return $this->pastasMensais[$anoMes];
    }

    /**
     * Retorna o ID da subpasta `$nome` dentro da pasta `$paiId`, criando-a se ainda não existir.
     *
     * @throws Exception
     */
    public function subpasta(string $paiId, string $nome): string
    {
        $chave = $paiId . '/' . $nome;
        if (!isset($this->subpastas[$chave])) {
            $this->subpastas[$chave] = $this->getOrCreateFolder($nome, $paiId)['id'];
        }

        return $this->subpastas[$chave];
    }

    /**
     * Confirma que o token atual consegue gravar na pasta informada, criando e
     * apagando um arquivo de teste.
     *
     * @throws Exception Quando a gravação falha (pasta inexistente ou sem permissão).
     */
    public function testarGravacao(string $folderId): void
    {
        try {
            $arquivo = $this->service->files->create(
                new DriveFile(['name' => '.teste-gravacao-saude', 'parents' => [$folderId]]),
                ['data' => 'ok', 'mimeType' => 'text/plain', 'uploadType' => 'multipart', 'fields' => 'id']
            );
            $this->service->files->delete($arquivo->id);
        } catch (Exception $e) {
            log_message('error', 'Teste de gravação no Google Drive falhou: ' . $e->getMessage());
            throw new Exception('Não foi possível gravar na pasta do Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Move um arquivo para a lixeira do Drive (recuperável por 30 dias; nunca exclui definitivamente).
     *
     * @throws Exception
     */
    public function trashFile(string $fileId): void
    {
        try {
            $this->service->files->update($fileId, new DriveFile(['trashed' => true]), ['fields' => 'id']);
        } catch (Exception $e) {
            log_message('error', "Erro ao mover arquivo {$fileId} para a lixeira do Drive: " . $e->getMessage());
            throw new Exception('Falha ao mover o arquivo para a lixeira do Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Informa se o token atual consegue listar arquivos que não foram criados pelo sistema.
     * Falso quando a conta foi autorizada só com o escopo antigo (drive.file) e precisa reconectar.
     */
    public function temLeituraCompleta(): bool
    {
        return $this->leituraCompleta;
    }

    private function escopoPermiteLeitura(string $escopos): bool
    {
        foreach (preg_split('/\s+/', trim($escopos)) ?: [] as $escopo) {
            if (in_array($escopo, [Drive::DRIVE_READONLY, Drive::DRIVE], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lista os arquivos contidos em uma pasta específica do Google Drive
     * (inclusive os adicionados direto no Drive, se o token tiver leitura completa).
     *
     * @param string $folderId O ID da pasta no Google Drive.
     * @return array Lista de arquivos contendo id, nome, mimeType, datas, tamanho e links.
     * @throws Exception
     */
    public function listFiles(string $folderId): array
    {
        $query = "'{$folderId}' in parents and trashed=false";

        try {
            $filesArray = [];
            $pageToken  = null;

            do {
                $params = [
                    'q' => $query,
                    'spaces' => 'drive',
                    'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, webViewLink, webContentLink, iconLink, thumbnailLink)',
                    'orderBy' => 'folder, name',
                    'pageSize' => 100,
                    'supportsAllDrives' => true,
                    'includeItemsFromAllDrives' => true,
                ];
                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $results = $this->service->files->listFiles($params);

                foreach ($results->getFiles() as $file) {
                    $filesArray[] = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'mimeType' => $file->getMimeType(),
                        'isFolder' => $file->getMimeType() === 'application/vnd.google-apps.folder',
                        'size' => $file->getSize() !== null ? (int) $file->getSize() : null,
                        'modifiedTime' => $file->getModifiedTime(),
                        'webViewLink' => $file->getWebViewLink(),
                        'webContentLink' => $file->getWebContentLink(),
                        'iconLink' => $file->getIconLink(),
                        'thumbnailLink' => $file->getThumbnailLink(),
                    ];
                }

                $pageToken = $results->getNextPageToken();
            } while ($pageToken);

            return $filesArray;
        } catch (Exception $e) {
            log_message('error', 'Erro ao listar arquivos da pasta no Google Drive: ' . $e->getMessage());
            throw new Exception("Falha ao listar arquivos no Google Drive: " . $e->getMessage());
        }
    }
}
