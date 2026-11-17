<?php namespace App\Traits;

/**
 * Formatação e validação do número único de processo do CNJ (NUP).
 *
 * Formato: NNNNNNN-DD.AAAA.J.TR.OOOO (20 dígitos, 25 caracteres com máscara).
 */
trait FormataNumeroProcessoTrait
{
    /**
     * Retorna somente os dígitos do número de processo informado.
     *
     * @param string|null $numeroProcesso Número de processo, com ou sem máscara.
     * @return string Apenas os dígitos.
     */
    protected function limparNumeroProcesso(?string $numeroProcesso): string
    {
        return preg_replace('/\D/', '', (string) $numeroProcesso) ?? '';
    }

    /**
     * Formata uma string de número de processo bruta para o formato mascarado.
     *
     * @param string $numeroProcessoBruto A string do número de processo bruto (somente dígitos).
     * @return string|null O número de processo formatado ou null se for inválido.
     */
    protected function formatarNumeroProcesso(string $numeroProcessoBruto): ?string
    {
        // Remove todos os caracteres não-dígitos e trunca para 20 caracteres
        $numeroLimpo = $this->limparNumeroProcesso($numeroProcessoBruto);
        $numeroLimpo = substr($numeroLimpo, 0, 20);

        // Regex para o formato esperado: 7 dígitos, 2 dígitos, 4 dígitos, 1 dígito, 2 dígitos, 4 dígitos
        $regex = '/^(\d{7})(\d{2})(\d{4})(\d{1})(\d{2})(\d{4})$/';

        if (preg_match($regex, $numeroLimpo, $partesMascara)) {
            // $partesMascara[0] é a string completa correspondente, os elementos seguintes são os grupos capturados
            $primeiraParte = $partesMascara[1];
            $segundaParte = $partesMascara[2];
            $terceiraParte = $partesMascara[3];
            $quartaParte = $partesMascara[4];
            $quintaParte = $partesMascara[5];
            $sextaParte = $partesMascara[6];

            return "{$primeiraParte}-{$segundaParte}.{$terceiraParte}.{$quartaParte}.{$quintaParte}.{$sextaParte}";
        }

        return null; // Retorna null para números de processo inválidos
    }

    /**
     * Valida se uma dada string é um número de processo formatado corretamente.
     *
     * @param string $numeroProcessoFormatado A string do número de processo formatado.
     * @return bool Verdadeiro se for válido, falso caso contrário.
     */
    protected function ehFormatoNumeroProcessoValido(string $numeroProcessoFormatado): bool
    {
        // Este regex verifica especificamente o formato mascarado: XXXXXXX-XX.XXXX.X.XX.XXXX
        $regex = '/^\d{7}-\d{2}\.\d{4}\.\d{1}\.\d{2}\.\d{4}$/';
        return (bool) preg_match($regex, $numeroProcessoFormatado);
    }

    /**
     * Valida um número de processo CNJ: exatamente 20 dígitos e dígito
     * verificador correto (módulo 97, Resolução CNJ 65/2008).
     *
     * @param string|null $numeroProcesso Número de processo, com ou sem máscara.
     * @return bool Verdadeiro se for um número CNJ válido.
     */
    protected function ehNumeroProcessoCnjValido(?string $numeroProcesso): bool
    {
        $digitos = $this->limparNumeroProcesso($numeroProcesso);

        if (strlen($digitos) !== 20) {
            return false;
        }

        // NNNNNNN AAAA JTR OOOO DD deve resultar em resto 1 na divisão por 97.
        $reordenado = substr($digitos, 0, 7) . substr($digitos, 9) . substr($digitos, 7, 2);
        $resto      = 0;

        foreach (str_split($reordenado) as $digito) {
            $resto = ($resto * 10 + (int) $digito) % 97;
        }

        return $resto === 1;
    }

    /**
     * Normaliza um número de processo CNJ para o formato mascarado, aceitando
     * somente números válidos (inclusive o dígito verificador).
     *
     * @param string|null $numeroProcesso Número de processo, com ou sem máscara.
     * @return string|null O número mascarado ou null se vazio ou inválido.
     */
    protected function normalizarNumeroProcessoCnj(?string $numeroProcesso): ?string
    {
        if (!$this->ehNumeroProcessoCnjValido($numeroProcesso)) {
            return null;
        }

        return $this->formatarNumeroProcesso($numeroProcesso);
    }
}
