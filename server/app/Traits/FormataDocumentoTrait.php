<?php

namespace App\Traits;

trait FormataDocumentoTrait
{
    /**
     * Formata um documento (CPF ou CNPJ) para o formato mascarado.
     *
     * @param string $documento O documento a ser formatado.
     * @return string O documento formatado.
     */
    public function formatarCpfCnpj($documento)
    {
        $documento = preg_replace('/\D/', '', $documento);

        if (strlen($documento) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
        } elseif (strlen($documento) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
        }

        return $documento;
    }
}
