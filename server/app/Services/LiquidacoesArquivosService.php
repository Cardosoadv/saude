<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Models\LiquidacoesDocumentosModel;
use App\Repositories\LiquidacoesRepository;
use Throwable;

/**
 * Documentos e comprovantes da liquidação guardados no Google Drive, na subpasta
 * "Liquidações" da pasta do cliente (mesma regra de pasta e de validação de arquivo
 * dos arquivos de contrato: tipo real do arquivo, tamanho e quantidade máximos).
 */
class LiquidacoesArquivosService
{
    public const SUBPASTA = 'Liquidações';

    protected LiquidacoesRepository $repository;
    protected ?GoogleDriveService $drive;
    protected ?ContratosArquivosService $contratosArquivos;

    public function __construct(
        ?LiquidacoesRepository $repository = null,
        ?GoogleDriveService $drive = null,
        ?ContratosArquivosService $contratosArquivos = null
    ) {
        $this->repository        = $repository ?? new LiquidacoesRepository();
        $this->drive             = $drive;
        $this->contratosArquivos = $contratosArquivos;
    }

    /**
     * Valida e envia os arquivos, registrando um documento para cada um.
     *
     * @param array                                   $liquidacao linha de `liquidacoes` (acesso já verificado)
     * @param array<int,array{path:string,nome:string}> $arquivos
     * @param string|null                             $tipo       sentenca, calculos, comprovante ou outros
     *
     * @return array{enviados: list<array>, erros: list<array{arquivo: string, mensagem: string}>}
     */
    public function enviar(array $liquidacao, array $arquivos, string $tipo = 'outros', ?int $pagamentoId = null, ?int $usuarioId = null): array
    {
        if (!array_key_exists($tipo, (new LiquidacoesDocumentosModel())->getTiposDocumento())) {
            throw LiquidacaoException::invalida('Tipo de documento inválido.', ['tipo_documento' => 'Use sentenca, calculos, comprovante ou outros.']);
        }

        try {
            $validos = $this->validador()->validarArquivos($arquivos);
        } catch (\InvalidArgumentException $e) {
            throw LiquidacaoException::invalida($e->getMessage());
        }

        $processo = $this->repository->getProcesso((int) $liquidacao['processo_id']);
        try {
            $pastaCliente = $this->validador()->pastaDoCliente(['cliente_id' => $processo['cliente_id'] ?? 0]);
            $pastaId      = $this->drive()->subpasta($pastaCliente, self::SUBPASTA);
        } catch (Throwable $e) {
            log_message('error', '[LiquidacoesArquivosService::enviar] ' . $e->getMessage());
            throw new LiquidacaoException($e->getMessage(), 502);
        }

        $liquidacaoId = (int) $liquidacao['liquidacao_id'];
        $base         = sprintf('Liquidacao-%d_%s_%s', $liquidacaoId, $tipo, date('Ymd-His'));
        $enviados     = [];
        $erros        = [];

        foreach ($validos as $i => $arquivo) {
            $nome = sprintf('%s_%d.%s', $base, $i + 1, $arquivo['ext']);
            try {
                $drive = $this->drive()->uploadFileToFolder($arquivo['path'], $nome, $arquivo['mime'], $pastaId);

                $registro = [
                    'processo_id'    => $liquidacao['processo_id'],
                    'liquidacao_id'  => $liquidacaoId,
                    'pagamento_id'   => $pagamentoId,
                    'tipo_documento' => $tipo,
                    'nome_arquivo'   => $nome,
                    'url_arquivo'    => $drive['link'],
                    'drive_id'       => $drive['id'],
                    'mime_type'      => $arquivo['mime'],
                    'tamanho'        => $arquivo['tamanho'],
                    'data_upload'    => date('Y-m-d H:i:s'),
                    'usuario_id'     => $usuarioId,
                ];
                $registro['documento_id'] = (int) $this->repository->createDocumento($registro);
                $enviados[] = $registro;
            } catch (Throwable $e) {
                log_message('error', '[LiquidacoesArquivosService::enviar] ' . $e->getMessage());
                $erros[] = ['arquivo' => $arquivo['nome'], 'mensagem' => $e->getMessage()];
            }
        }

        return ['enviados' => $enviados, 'erros' => $erros];
    }

    /** Move o arquivo para a lixeira do Drive. Lança exceção se o Drive falhar (nada deve ser removido nesse caso). */
    public function excluirDoDrive(array $documento): void
    {
        try {
            $this->drive()->trashFile((string) $documento['drive_id']);
        } catch (Throwable $e) {
            log_message('error', '[LiquidacoesArquivosService::excluirDoDrive] ' . $e->getMessage());
            throw new LiquidacaoException('Não foi possível remover o arquivo do Google Drive: ' . $e->getMessage(), 502);
        }
    }

    /**
     * Desfaz envios (lixeira do Drive + registro) quando a operação que os pediu não termina.
     * Melhor esforço: falhas só entram no log.
     *
     * @param list<array> $documentos registros devolvidos por enviar()
     */
    public function descartar(array $documentos): void
    {
        foreach ($documentos as $doc) {
            try {
                $this->drive()->trashFile((string) $doc['drive_id']);
                $this->repository->deleteDocumento((int) $doc['documento_id']);
            } catch (Throwable $e) {
                log_message('error', '[LiquidacoesArquivosService::descartar] ' . $e->getMessage());
            }
        }
    }

    protected function drive(): GoogleDriveService
    {
        return $this->drive ??= new GoogleDriveService();
    }

    protected function validador(): ContratosArquivosService
    {
        return $this->contratosArquivos ??= new ContratosArquivosService(null, $this->drive);
    }
}
