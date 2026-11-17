<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Importacao\LeitorArquivoTabular;
use App\Repositories\BuscarProcessosRepository;
use App\Traits\FormataNumeroProcessoTrait;
use InvalidArgumentException;

/**
 * Importação em lote de processos para a busca automática (DJEN), a partir
 * de arquivos CSV ou JSON.
 *
 * Cada registro tem o número do processo (CNJ) e a observação que justifica
 * a inclusão. Linhas sem observação usam a observação padrão informada na
 * importação. Números inválidos, repetidos no arquivo ou já cadastrados não
 * são gravados e aparecem no relatório devolvido.
 *
 * CSV: separador ";" ou "," (detectado), com ou sem cabeçalho. Com cabeçalho,
 * as colunas são reconhecidas pelo nome (numero_processo, observacao e
 * sinônimos); sem cabeçalho, a 1ª coluna é o número e a 2ª a observação.
 *
 * JSON: lista de objetos {"numero_processo", "observacao"} (ou de strings com
 * o número), solta ou dentro da chave "processos".
 */
class BuscarProcessosImportacaoService
{
    use FormataNumeroProcessoTrait;

    /** Máximo de registros aceitos por arquivo. */
    public const MAX_REGISTROS = 1000;

    /** Nomes aceitos para a coluna/chave do número do processo. */
    private const ALIASES_NUMERO = ['numero_processo', 'numeroprocesso', 'numero', 'processo', 'cnj', 'numero_cnj'];

    /** Nomes aceitos para a coluna/chave da observação. */
    private const ALIASES_OBSERVACAO = ['observacao', 'observacoes', 'obs', 'justificativa', 'motivo'];

    protected BuscarProcessosRepository $repository;

    public function __construct(?BuscarProcessosRepository $repository = null)
    {
        $this->repository = $repository ?? new BuscarProcessosRepository();
    }

    /**
     * Lê e valida o arquivo sem gravar nada.
     *
     * @param string $conteudo         Conteúdo bruto do arquivo.
     * @param string $nomeArquivo      Nome original (usado para detectar o formato).
     * @param string $observacaoPadrao Observação usada nas linhas que não trazem uma.
     * @return array{formato: string, total: int, validos: array, ignorados: array, erros: array}
     *
     * @throws InvalidArgumentException Arquivo vazio, malformado ou acima do limite.
     */
    public function analisar(string $conteudo, string $nomeArquivo, string $observacaoPadrao = ''): array
    {
        $lido      = $this->leitor()->ler($conteudo, $nomeArquivo);
        $formato   = $lido['formato'];
        $registros = $this->paraRegistros($lido['registros']);

        if ($registros === []) {
            throw new InvalidArgumentException('O arquivo não contém processos para importar.');
        }

        if (count($registros) > self::MAX_REGISTROS) {
            throw new InvalidArgumentException(
                'O arquivo tem ' . count($registros) . ' registros; o limite por importação é ' . self::MAX_REGISTROS . '.'
            );
        }

        return ['formato' => $formato] + $this->validar($registros, trim($observacaoPadrao));
    }

    /**
     * Valida e grava os registros válidos do arquivo.
     *
     * @return array Relatório de {@see analisar()} acrescido de "importados".
     */
    public function importar(string $conteudo, string $nomeArquivo, string $observacaoPadrao = ''): array
    {
        $resultado = $this->analisar($conteudo, $nomeArquivo, $observacaoPadrao);

        $dados = array_map(static fn (array $r): array => [
            'numero_processo' => $r['numero_processo'],
            'observacao'      => $r['observacao'],
        ], $resultado['validos']);

        if ($dados !== []) {
            $this->repository->insertBatch($dados);
        }

        $resultado['importados'] = count($dados);

        return $resultado;
    }

    /**
     * Classifica os registros em válidos, ignorados (repetidos/já cadastrados)
     * e erros (número inválido, observação ausente ou longa demais).
     *
     * @param array<int, array{linha: int, numero: string, observacao: string}> $registros
     */
    public function validar(array $registros, string $observacaoPadrao = ''): array
    {
        $cadastrados = array_flip($this->repository->numerosCadastrados());
        $vistos      = [];
        $validos     = [];
        $ignorados   = [];
        $erros       = [];

        foreach ($registros as $r) {
            $linha      = $r['linha'];
            $original   = trim($r['numero']);
            $numero     = $this->limparNumeroProcesso($original);
            $observacao = trim($r['observacao']) !== '' ? trim($r['observacao']) : $observacaoPadrao;

            if ($numero === '') {
                $erros[] = ['linha' => $linha, 'numero_processo' => $original, 'motivo' => 'Número do processo não informado.'];
                continue;
            }

            if (!$this->ehNumeroProcessoCnjValido($numero)) {
                $erros[] = [
                    'linha'           => $linha,
                    'numero_processo' => $original,
                    'motivo'          => strlen($numero) !== 20
                        ? 'Número CNJ deve ter 20 dígitos (tem ' . strlen($numero) . ').'
                        : 'Dígito verificador do número CNJ não confere.',
                ];
                continue;
            }

            if ($observacao === '') {
                $erros[] = ['linha' => $linha, 'numero_processo' => $original, 'motivo' => 'Observação não informada (e sem observação padrão).'];
                continue;
            }

            if (mb_strlen($observacao) > BuscarProcessosService::OBSERVACAO_MAX) {
                $erros[] = [
                    'linha'           => $linha,
                    'numero_processo' => $original,
                    'motivo'          => 'Observação com mais de ' . BuscarProcessosService::OBSERVACAO_MAX . ' caracteres.',
                ];
                continue;
            }

            if (isset($cadastrados[$numero])) {
                $ignorados[] = ['linha' => $linha, 'numero_processo' => $original, 'motivo' => 'Já cadastrado na busca automática.'];
                continue;
            }

            if (isset($vistos[$numero])) {
                $ignorados[] = ['linha' => $linha, 'numero_processo' => $original, 'motivo' => 'Repetido no arquivo (linha ' . $vistos[$numero] . ').'];
                continue;
            }

            $vistos[$numero] = $linha;
            $validos[]       = ['linha' => $linha, 'numero_processo' => $numero, 'observacao' => $observacao];
        }

        return [
            'total'     => count($registros),
            'validos'   => $validos,
            'ignorados' => $ignorados,
            'erros'     => $erros,
        ];
    }

    /**
     * Lê um CSV e devolve os registros com o número da linha no arquivo.
     *
     * @return array<int, array{linha: int, numero: string, observacao: string}>
     */
    public function lerCsv(string $conteudo): array
    {
        return $this->paraRegistros($this->leitor()->lerCsv($conteudo));
    }

    /**
     * Lê um JSON e devolve os registros com a posição (1-based) na lista.
     *
     * @return array<int, array{linha: int, numero: string, observacao: string}>
     *
     * @throws InvalidArgumentException JSON malformado ou fora do formato esperado.
     */
    public function lerJson(string $conteudo): array
    {
        return $this->paraRegistros($this->leitor()->lerJson($conteudo));
    }

    private function leitor(): LeitorArquivoTabular
    {
        return new LeitorArquivoTabular(
            ['numero_processo' => self::ALIASES_NUMERO, 'observacao' => self::ALIASES_OBSERVACAO],
            'numero_processo'
        );
    }

    /**
     * @param array<int, array{linha: int, dados: array<string, string>}> $lidos
     * @return array<int, array{linha: int, numero: string, observacao: string}>
     */
    private function paraRegistros(array $lidos): array
    {
        return array_map(static fn (array $r): array => [
            'linha'      => $r['linha'],
            'numero'     => (string) ($r['dados']['numero_processo'] ?? ''),
            'observacao' => (string) ($r['dados']['observacao'] ?? ''),
        ], $lidos);
    }
}
