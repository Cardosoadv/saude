<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use App\DTO\ExtratoArquivo;

/** Contrato dos leitores de extrato (OFX hoje; CSV no futuro). */
interface ParserExtratoInterface
{
    /**
     * Valida metadados do upload (nome, MIME, tamanho) antes de ler o conteúdo.
     *
     * @throws ExtratoInvalidoException
     */
    public function validarArquivo(string $nomeOriginal, ?string $mime, int $tamanhoBytes): void;

    /**
     * Lê o conteúdo bruto do arquivo.
     *
     * @throws ExtratoInvalidoException
     */
    public function parse(string $conteudo): ExtratoArquivo;
}
