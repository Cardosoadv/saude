<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientesModel;
use App\Models\ContratosArquivosModel;
use App\Repositories\ContratosArquivosRepository;
use App\Repositories\ContratosRepository;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Arquivos de contratos (contrato original, aditivos, anexos) guardados no Google Drive,
 * na subpasta "Contratos" da pasta do cliente.
 *
 * @property ContratosArquivosRepository $repository
 */
class ContratosArquivosService extends BaseService
{
    public const SUBPASTA = 'Contratos';

    /** Tipos aceitos (MIME real do arquivo) e a extensão usada no nome salvo no Drive. */
    public const MIMES_PERMITIDOS = [
        'application/pdf'    => 'pdf',
        'image/jpeg'         => 'jpg',
        'image/png'          => 'png',
        'image/webp'         => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public const MAX_MB_PADRAO       = 20;
    public const MAX_ARQUIVOS_PADRAO = 5;

    protected ?GoogleDriveService $drive;
    protected ContratosRepository $contratosRepository;
    protected ClientesModel $clientesModel;
    protected ?ConfiguracoesService $configuracoesService;

    public function __construct(
        ?ContratosArquivosRepository $repository = null,
        ?GoogleDriveService $drive = null,
        ?ContratosRepository $contratosRepository = null,
        ?ClientesModel $clientesModel = null,
        ?ConfiguracoesService $configuracoesService = null
    ) {
        $this->repository           = $repository ?? new ContratosArquivosRepository();
        $this->drive                = $drive;
        $this->contratosRepository  = $contratosRepository ?? new ContratosRepository();
        $this->clientesModel        = $clientesModel ?? new ClientesModel();
        $this->configuracoesService = $configuracoesService;
    }

    /**
     * Cria o cliente do Drive só quando necessário (o construtor renova o token OAuth).
     */
    protected function drive(): GoogleDriveService
    {
        return $this->drive ??= new GoogleDriveService();
    }

    /**
     * @throws RuntimeException Quando o contrato não existe.
     */
    public function listarPorContrato(int $contratoId): array
    {
        $this->buscarContrato($contratoId);

        return $this->repository->listarPorContrato($contratoId);
    }

    /**
     * Valida e envia arquivos para a subpasta "Contratos" da pasta do cliente.
     * Se o cliente ainda não tem pasta, ela é criada na pasta raiz do Drive.
     *
     * @param array<int,array{path:string,nome:string}> $arquivos
     * @return array{enviados:array,erros:array}
     *
     * @throws RuntimeException         Contrato inexistente.
     * @throws InvalidArgumentException Categoria ou arquivos inválidos.
     * @throws Exception                Contrato sem cliente ou pasta do Drive indisponível.
     */
    public function enviar(int $contratoId, array $arquivos, string $categoria = 'Contrato original', ?int $userId = null): array
    {
        $contrato = $this->buscarContrato($contratoId);

        if (!in_array($categoria, ContratosArquivosModel::CATEGORIAS, true)) {
            throw new InvalidArgumentException('Categoria de arquivo inválida.');
        }

        $validos  = $this->validarArquivos($arquivos);
        $pastaId  = $this->drive()->subpasta($this->pastaDoCliente($contrato), self::SUBPASTA);
        $base     = $this->nomeBase($contrato, $categoria);
        $existentes = count($this->repository->listarPorContrato($contratoId));

        $enviados = [];
        $erros    = [];
        foreach ($validos as $i => $arquivo) {
            $nomeDrive = sprintf('%s_%d.%s', $base, $existentes + $i + 1, $arquivo['ext']);

            try {
                $drive = $this->drive()->uploadFileToFolder($arquivo['path'], $nomeDrive, $arquivo['mime'], $pastaId);

                $registro = [
                    'contrato_id' => $contratoId,
                    'categoria'   => $categoria,
                    'nome'        => $nomeDrive,
                    'mime_type'   => $arquivo['mime'],
                    'tamanho'     => $arquivo['tamanho'],
                    'drive_id'    => $drive['id'],
                    'drive_link'  => $drive['link'],
                    'enviado_por' => $userId,
                ];
                $registro['id_contrato_arquivo'] = $this->repository->create($registro);
                $enviados[] = $registro;
            } catch (Exception $e) {
                log_message('error', '[ContratosArquivosService::enviar] ' . $e->getMessage());
                $erros[] = ['arquivo' => $arquivo['nome'], 'mensagem' => $e->getMessage()];
            }
        }

        return ['enviados' => $enviados, 'erros' => $erros];
    }

    /**
     * Devolve o contrato a que o arquivo pertence (para checar o acesso antes de remover).
     *
     * @throws RuntimeException Arquivo inexistente.
     */
    public function contratoDoArquivo(int $idArquivo): int
    {
        $arquivo = $this->repository->findById($idArquivo);
        if (!$arquivo) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        return (int) $arquivo['contrato_id'];
    }

    /**
     * Move o arquivo para a lixeira do Drive e remove o registro (soft delete).
     * Se o Drive falhar, nada é removido e o erro é devolvido para nova tentativa.
     *
     * @throws RuntimeException Arquivo inexistente.
     * @throws Exception        Falha ao mover o arquivo no Drive.
     */
    public function excluir(int $idArquivo): void
    {
        $arquivo = $this->repository->findById($idArquivo);
        if (!$arquivo) {
            throw new RuntimeException('Arquivo não encontrado.');
        }

        $this->drive()->trashFile($arquivo['drive_id']);
        $this->repository->delete($idArquivo);
    }

    /**
     * Valida quantidade, tamanho e tipo real (MIME) dos arquivos, antes de qualquer envio.
     *
     * @param array<int,array{path:string,nome:string}> $arquivos
     * @return array<int,array{path:string,nome:string,mime:string,ext:string,tamanho:int}>
     * @throws InvalidArgumentException
     */
    public function validarArquivos(array $arquivos): array
    {
        $arquivos = array_values($arquivos);
        if (!$arquivos) {
            throw new InvalidArgumentException('Nenhum arquivo enviado.');
        }

        $maxArquivos = $this->limiteConfigurado('CONTRATOS_MAX_ARQUIVOS', self::MAX_ARQUIVOS_PADRAO);
        if (count($arquivos) > $maxArquivos) {
            throw new InvalidArgumentException("Envie no máximo {$maxArquivos} arquivos por vez.");
        }

        $maxMb    = $this->limiteConfigurado('CONTRATOS_MAX_MB', self::MAX_MB_PADRAO);
        $maxBytes = $maxMb * 1024 * 1024;
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $validos  = [];

        foreach ($arquivos as $arquivo) {
            $path = (string) ($arquivo['path'] ?? '');
            $nome = (string) ($arquivo['nome'] ?? basename($path));

            if ($path === '' || !is_file($path)) {
                throw new InvalidArgumentException("Arquivo \"{$nome}\" inválido ou não recebido.");
            }

            $tamanho = (int) filesize($path);
            if ($tamanho === 0) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" está vazio.");
            }
            if ($tamanho > $maxBytes) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" excede o limite de {$maxMb} MB.");
            }

            $mime = $this->mimeDoArquivo($finfo, $path, $nome);
            if ($mime === null) {
                throw new InvalidArgumentException("O arquivo \"{$nome}\" não é permitido. Envie PDF, Word (DOC/DOCX), JPG, PNG ou WEBP.");
            }

            $validos[] = [
                'path'    => $path,
                'nome'    => $nome,
                'mime'    => $mime,
                'ext'     => self::MIMES_PERMITIDOS[$mime],
                'tamanho' => $tamanho,
            ];
        }

        return $validos;
    }

    /**
     * MIME real do arquivo, ou null se o tipo não é aceito. O .docx é um zip e o .doc é um
     * contêiner OLE: o conteúdo só é aceito quando a extensão informada confirma o formato.
     */
    private function mimeDoArquivo(\finfo $finfo, string $path, string $nome): ?string
    {
        $mime = (string) $finfo->file($path);
        if (isset(self::MIMES_PERMITIDOS[$mime])) {
            return $mime;
        }

        $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if ($extensao === 'docx' && in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
        if ($extensao === 'doc' && in_array($mime, ['application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'], true)) {
            return 'application/msword';
        }

        return null;
    }

    /**
     * ID da pasta do cliente no Drive. Cria a pasta (e grava o link em `clientes.pasta`) quando
     * o cliente ainda não tem uma, no mesmo padrão da sincronização de pastas.
     *
     * @throws Exception Contrato sem cliente, cliente inexistente ou pasta raiz não configurada.
     */
    public function pastaDoCliente(array $contrato): string
    {
        $clienteId = (int) ($contrato['cliente_id'] ?? 0);
        $cliente   = $clienteId > 0 ? $this->clientesModel->find($clienteId) : null;
        if (!$cliente) {
            throw new Exception('O contrato não tem um cliente válido para receber o arquivo.');
        }

        $id = !empty($cliente['pasta']) ? $this->drive()->extractFolderIdFromUrl((string) $cliente['pasta']) : null;
        if ($id !== null) {
            return $id;
        }

        $raiz = trim((string) $this->configuracao()->getValor('GOOGLE_DRIVE_FOLDER_ID', ''));
        $raiz = $raiz === '' ? null : $this->drive()->extractFolderIdFromUrl($raiz);
        if ($raiz === null) {
            throw new Exception('A pasta raiz do Google Drive não está configurada (GOOGLE_DRIVE_FOLDER_ID).');
        }

        $pasta = $this->drive()->getOrCreateFolder($cliente['nome'] . ' - ' . $cliente['id_cliente'], $raiz);
        $this->clientesModel->update($cliente['id_cliente'], ['pasta' => $pasta['link']]);

        return $pasta['id'];
    }

    /**
     * Nome padrão no Drive (sem sequência e extensão): Contrato-<número>_<categoria>_<AAAAMMDD>.
     */
    protected function nomeBase(array $contrato, string $categoria): string
    {
        $numero = $this->slug((string) ($contrato['numero_contrato'] ?? '')) ?: (string) $contrato['id_contrato'];

        return sprintf('Contrato-%s_%s_%s', $numero, $this->slug($categoria), date('Ymd-His'));
    }

    private function slug(string $texto): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $slug  = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii === false ? $texto : $ascii), '-');

        return substr($slug, 0, 30);
    }

    /**
     * @throws RuntimeException Quando o contrato não existe.
     */
    protected function buscarContrato(int $contratoId): array
    {
        $contrato = $this->contratosRepository->findById($contratoId);
        if (!$contrato) {
            throw new RuntimeException('Contrato não encontrado.');
        }

        return $contrato;
    }

    private function configuracao(): ConfiguracoesService
    {
        return $this->configuracoesService ??= new ConfiguracoesService();
    }

    private function limiteConfigurado(string $nome, int $padrao): int
    {
        $valor = (int) $this->configuracao()->getValor($nome, $padrao);

        return $valor > 0 ? $valor : $padrao;
    }
}
