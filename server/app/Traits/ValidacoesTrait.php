<?php

namespace App\Traits;


/**
 * Trait ValidacoesTrait
 * Fornece métodos para validar CPF e CNPJ.
 * * @method validarCpfCnpj(string $cpf_cnpj) Valida um CPF ou CNPJ, retornando true se for válido e false caso contrário.
 * * @method validarCpf(string $cpf) Valida um CPF, retornando true se for válido e false caso contrário.
 * * @method validarCnpj(string $cnpj) Valida um CNPJ, retornando true se for válido e false caso contrário.
 */
trait ValidacoesTrait
{
    /**
     * Função para validar CPF ou CNPJ
     */
    public function validarCpfCnpj(string $cpf_cnpj): bool
    {
        log_message('info', 'Validando CPF ou CNPJ: ' . $cpf_cnpj);
        // Remove caracteres não alfanuméricos e converte para maiúsculo
        $cpf_cnpj = strtoupper(preg_replace('/[^0-9a-zA-Z]/', '', $cpf_cnpj));
        log_message('info', 'CPF ou CNPJ sem caracteres formatadores: ' . $cpf_cnpj);

        // Verifica se é CPF (apenas números)
        if (strlen($cpf_cnpj) == 11 && preg_match('/^[0-9]{11}$/', $cpf_cnpj)) {
            log_message('info', 'Validando CPF');
            $resultado = $this->validarCpf($cpf_cnpj);
            log_message('info', 'Resultado da validação do CPF: ' . ($resultado ? 'verdadeiro' : 'falso'));
            return $resultado;
        }

        // Verifica se é CNPJ
        if (strlen($cpf_cnpj) == 14) {
            log_message('info', 'Validando CNPJ');
            $resultado = $this->validarCnpj($cpf_cnpj);
            log_message('info', 'Resultado da validação do CNPJ: ' . ($resultado ? 'verdadeiro' : 'falso'));
            return $resultado;
        }

        return false;
    }

    /**
     * Função para validar CPF
     */
    private function validarCpf(string $cpf): bool
    {
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validação do CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    /**
     * Função para validar CNPJ
     */
    private function validarCnpj(string $cnpj): bool
    {
        log_message('info', 'Início da validação de CNPJ: ' . $cnpj);

        // Verifica comprimento
        if (strlen($cnpj) != 14) {
            log_message('info', 'Comprimento inválido: ' . strlen($cnpj));
            return false;
        }

        // Verifica se todos os caracteres são iguais
        if (preg_match('/(.)\1{13}/', $cnpj)) {
            log_message('info', 'CNPJ com caracteres iguais');
            return false;
        }

        // Validação do CNPJ
        $calculatedDigits = [];

        // Cálculo do primeiro dígito verificador
        $soma1 = 0;
        $peso1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 12; $i++) {
            $valor = ord($cnpj[$i]) - 48;
            $soma1 += $valor * $peso1[$i];
            log_message('debug', "Posição $i: $valor * {$peso1[$i]} = " . ($valor * $peso1[$i]));
        }
        $resto1 = $soma1 % 11;
        $digito1 = $resto1 < 2 ? 0 : 11 - $resto1;

        log_message('info', "Primeiro dígito - Soma: $soma1, Resto: $resto1, Dígito calculado: $digito1, Dígito original: {$cnpj[12]}");

        // Cálculo do segundo dígito verificador
        $soma2 = 0;
        $peso2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 13; $i++) {
            $valor = ord($cnpj[$i]) - 48;
            $soma2 += $valor * $peso2[$i];
            log_message('debug', "Posição $i: $valor * {$peso2[$i]} = " . ($valor * $peso2[$i]));
        }
        $resto2 = $soma2 % 11;
        $digito2 = $resto2 < 2 ? 0 : 11 - $resto2;

        log_message('info', "Segundo dígito - Soma: $soma2, Resto: $resto2, Dígito calculado: $digito2, Dígito original: {$cnpj[13]}");

        // Verifica os dígitos calculados
        if ($digito1 == $cnpj[12] && $digito2 == $cnpj[13]) {
            log_message('info', 'CNPJ válido');
            return true;
        } else {
            log_message('info', 'CNPJ inválido');
            return false;
        }
    }
}
