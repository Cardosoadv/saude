<?php

declare(strict_types=1);

namespace App\Libraries\Importacao;

use InvalidArgumentException;

/**
 * Lê arquivos de importação CSV ou JSON e devolve os registros já mapeados
 * para os campos conhecidos, com o número da linha (CSV) ou a posição (JSON).
 *
 * Os campos são definidos por um mapa "campo => sinônimos aceitos". Os nomes
 * de coluna/chave são comparados em minúsculas, sem acento, com espaços,
 * hífens e pontos trocados por "_".
 *
 * CSV: separador ";", "," ou tabulação (detectado pela 1ª linha), com ou sem
 * cabeçalho, com ou sem BOM, em UTF-8 ou Windows-1252 (Excel em português).
 * Sem cabeçalho, as colunas seguem a ordem dos campos no mapa.
 *
 * JSON: lista de objetos (ou de strings com o campo principal), solta ou
 * dentro da chave informada em $chaveLista (padrão "processos"). Valores em
 * lista (ex.: várias partes) são unidos por "|".
 */
class LeitorArquivoTabular
{
    /** Separador usado para valores múltiplos numa mesma célula. */
    public const SEPARADOR_MULTIPLO = '|';

    /**
     * @param array<string, string[]> $campos         Campo => sinônimos aceitos (o próprio nome é aceito sempre).
     * @param string                  $campoPrincipal Campo que identifica o registro (ex.: numero_processo).
     * @param string                  $chaveLista     Chave do JSON que pode envolver a lista.
     */
    public function __construct(
        private array $campos,
        private string $campoPrincipal,
        private string $chaveLista = 'processos'
    ) {
        foreach ($this->campos as $campo => $aliases) {
            $this->campos[$campo] = array_values(array_unique(array_map(
                fn (string $a): string => $this->normalizarChave($a),
                array_merge([$campo], $aliases)
            )));
        }
    }

    /**
     * Lê o conteúdo do arquivo.
     *
     * @return array{formato: string, registros: array<int, array{linha: int, dados: array<string, string>}>}
     *
     * @throws InvalidArgumentException Arquivo vazio, JSON malformado ou cabeçalho sem o campo principal.
     */
    public function ler(string $conteudo, string $nomeArquivo): array
    {
        $formato  = $this->detectarFormato($conteudo, $nomeArquivo);
        $conteudo = $this->normalizarCodificacao($conteudo);

        return [
            'formato'   => $formato,
            'registros' => $formato === 'json' ? $this->lerJson($conteudo) : $this->lerCsv($conteudo),
        ];
    }

    /**
     * @return array<int, array{linha: int, dados: array<string, string>}>
     */
    public function lerCsv(string $conteudo): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];

        $primeira = '';
        foreach ($linhas as $l) {
            if (trim($l) !== '') {
                $primeira = $l;
                break;
            }
        }
        if ($primeira === '') {
            return [];
        }

        $separador = $this->detectarSeparador($primeira);
        $mapa      = array_flip(array_keys($this->campos)); // posicional: campo => índice
        $cabecalho = false;
        $registros = [];

        foreach ($linhas as $i => $texto) {
            if (trim($texto) === '') {
                continue;
            }

            $celulas = str_getcsv($texto, $separador, '"', '');

            if (!$cabecalho && $registros === [] && $this->ehCabecalho($celulas)) {
                $cabecalho = true;
                $mapa      = $this->mapearColunas($celulas);
                continue;
            }

            $dados = [];
            foreach ($mapa as $campo => $indice) {
                $dados[$campo] = trim((string) ($celulas[$indice] ?? ''));
            }

            $registros[] = ['linha' => $i + 1, 'dados' => $dados];
        }

        return $registros;
    }

    /**
     * @return array<int, array{linha: int, dados: array<string, string>}>
     *
     * @throws InvalidArgumentException JSON malformado ou fora do formato esperado.
     */
    public function lerJson(string $conteudo): array
    {
        $dados = json_decode($conteudo, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg() . '.');
        }

        if (is_array($dados) && isset($dados[$this->chaveLista]) && is_array($dados[$this->chaveLista])) {
            $dados = $dados[$this->chaveLista];
        }

        if (!is_array($dados) || !array_is_list($dados)) {
            throw new InvalidArgumentException('O JSON deve ser uma lista de processos ou um objeto com a chave "' . $this->chaveLista . '".');
        }

        $registros = [];
        foreach ($dados as $i => $item) {
            if (is_string($item) || is_int($item)) {
                $registros[] = ['linha' => $i + 1, 'dados' => [$this->campoPrincipal => trim((string) $item)]];
                continue;
            }

            if (!is_array($item)) {
                $registros[] = ['linha' => $i + 1, 'dados' => [$this->campoPrincipal => '']];
                continue;
            }

            $normalizado = [];
            foreach ($item as $chave => $valor) {
                $normalizado[$this->normalizarChave((string) $chave)] = $valor;
            }

            $registro = [];
            foreach ($this->campos as $campo => $aliases) {
                $registro[$campo] = $this->valorPorAlias($normalizado, $aliases);
            }

            $registros[] = ['linha' => $i + 1, 'dados' => $registro];
        }

        return $registros;
    }

    /** Minúsculas, sem acentos, espaços/hífens/pontos viram "_". */
    public function normalizarChave(string $chave): string
    {
        $chave = mb_strtolower(trim($chave, " \t\"'"));
        $chave = strtr($chave, [
            'ç' => 'c', 'ã' => 'a', 'á' => 'a', 'â' => 'a', 'à' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);

        return (string) preg_replace('/[\s\-.]+/', '_', $chave);
    }

    /**
     * Define o formato pela extensão do arquivo ou, na falta dela, pelo conteúdo.
     */
    private function detectarFormato(string $conteudo, string $nomeArquivo): string
    {
        $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        if (in_array($extensao, ['csv', 'txt'], true)) {
            return 'csv';
        }
        if ($extensao === 'json') {
            return 'json';
        }

        $inicio = ltrim($this->removerBom($conteudo));
        if ($inicio === '') {
            throw new InvalidArgumentException('O arquivo enviado está vazio.');
        }

        return in_array($inicio[0], ['[', '{'], true) ? 'json' : 'csv';
    }

    private function normalizarCodificacao(string $conteudo): string
    {
        $conteudo = $this->removerBom($conteudo);

        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        return $conteudo;
    }

    private function removerBom(string $conteudo): string
    {
        return str_starts_with($conteudo, "\xEF\xBB\xBF") ? substr($conteudo, 3) : $conteudo;
    }

    private function detectarSeparador(string $linha): string
    {
        $contagens = [';' => substr_count($linha, ';'), ',' => substr_count($linha, ','), "\t" => substr_count($linha, "\t")];
        arsort($contagens);
        $separador = (string) array_key_first($contagens);

        return $contagens[$separador] > 0 ? $separador : ';';
    }

    /** A linha é cabeçalho quando nenhuma célula traz um número de processo e alguma é um nome de campo. */
    private function ehCabecalho(array $celulas): bool
    {
        foreach ($celulas as $celula) {
            if (strlen((string) preg_replace('/\D/', '', (string) $celula)) >= 15) {
                return false;
            }
        }

        $chaves  = array_map(fn ($c) => $this->normalizarChave((string) $c), $celulas);
        $aliases = array_merge(...array_values($this->campos));

        return array_intersect($chaves, $aliases) !== [];
    }

    /**
     * @return array<string, int> Campo => índice da coluna.
     */
    private function mapearColunas(array $cabecalho): array
    {
        $chaves = array_map(fn ($c) => $this->normalizarChave((string) $c), $cabecalho);
        $mapa   = [];

        foreach ($chaves as $i => $chave) {
            foreach ($this->campos as $campo => $aliases) {
                if (!isset($mapa[$campo]) && in_array($chave, $aliases, true)) {
                    $mapa[$campo] = $i;
                    break;
                }
            }
        }

        if (!isset($mapa[$this->campoPrincipal])) {
            throw new InvalidArgumentException('Cabeçalho do CSV sem a coluna "' . $this->campoPrincipal . '".');
        }

        return $mapa;
    }

    private function valorPorAlias(array $item, array $aliases): string
    {
        foreach ($aliases as $alias) {
            if (!array_key_exists($alias, $item)) {
                continue;
            }

            $valor = $item[$alias];
            if (is_scalar($valor)) {
                return trim((string) $valor);
            }
            if (is_array($valor) && array_is_list($valor)) {
                $partes = array_map(
                    static fn ($v) => is_array($v) ? (string) ($v['nome'] ?? '') : (is_scalar($v) ? (string) $v : ''),
                    $valor
                );

                return implode(self::SEPARADOR_MULTIPLO, array_filter(array_map('trim', $partes), 'strlen'));
            }
        }

        return '';
    }
}
