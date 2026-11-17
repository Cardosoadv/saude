<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Importacao\NormalizadorFontesProcesso;
use Config\Services;
use Throwable;

/**
 * Consulta à API pública do DataJud (CNJ).
 *
 * O índice de cada tribunal é deduzido dos dígitos J.TR do número CNJ
 * (NNNNNNN-DD.AAAA.J.TR.OOOO). A consulta é só de leitura: não grava nada.
 *
 * A chave da API vem da variável de ambiente API_KEY (mesma usada pelo
 * MovimentosService).
 */
class DatajudService
{
    public const BASE_URL = 'https://api-publica.datajud.cnj.jus.br/';

    /** Novas tentativas quando o DataJud está sobrecarregado (HTTP 429/502/503). */
    private const RETENTATIVAS = 2;

    /** UFs na ordem dos códigos TR da Justiça Estadual e Eleitoral (01 a 27). */
    private const UFS = [
        '01' => 'ac', '02' => 'al', '03' => 'ap', '04' => 'am', '05' => 'ba', '06' => 'ce', '07' => 'dft',
        '08' => 'es', '09' => 'go', '10' => 'ma', '11' => 'mt', '12' => 'ms', '13' => 'mg', '14' => 'pa',
        '15' => 'pb', '16' => 'pr', '17' => 'pe', '18' => 'pi', '19' => 'rj', '20' => 'rn', '21' => 'rs',
        '22' => 'ro', '23' => 'rr', '24' => 'sc', '25' => 'se', '26' => 'sp', '27' => 'to',
    ];

    /** Tribunais de Justiça Militar estaduais (J = 9). */
    private const TJM = ['13' => 'tjmmg', '21' => 'tjmrs', '26' => 'tjmsp'];

    protected $client;

    public function __construct($client = null)
    {
        $this->client = $client;
    }

    /**
     * Alias do índice do DataJud (ex.: "tjmg", "trt3", "tre-sp") para o
     * número CNJ, ou null quando o tribunal não tem índice público (STF, CNJ).
     */
    public function aliasPorNumero(string $numeroProcesso): ?string
    {
        $digitos = preg_replace('/\D/', '', $numeroProcesso) ?? '';
        if (strlen($digitos) !== 20) {
            return null;
        }

        return $this->aliasPorCodigo(substr($digitos, 13, 3));
    }

    /**
     * Alias do índice a partir do código J + TR (3 dígitos, ex.: "813").
     */
    public function aliasPorCodigo(string $codigo): ?string
    {
        if (!preg_match('/^\d{3}$/', $codigo)) {
            return null;
        }

        $j  = $codigo[0];
        $tr = substr($codigo, 1, 2);
        $n  = (int) $tr;

        return match ($j) {
            '3'     => $tr === '00' ? 'stj' : null,
            '4'     => $n >= 1 && $n <= 6 ? 'trf' . $n : null,
            '5'     => $tr === '00' ? 'tst' : ($n >= 1 && $n <= 24 ? 'trt' . $n : null),
            '6'     => $tr === '00' ? 'tse' : (isset(self::UFS[$tr]) ? 'tre-' . self::UFS[$tr] : null),
            '7'     => $tr === '00' ? 'stm' : null,
            '8'     => isset(self::UFS[$tr]) ? 'tj' . self::UFS[$tr] : null,
            '9'     => self::TJM[$tr] ?? null,
            default => null,
        };
    }

    /** URL de busca (_search) do índice do tribunal, ou null se não houver. */
    public function urlPorNumero(string $numeroProcesso): ?string
    {
        $alias = $this->aliasPorNumero($numeroProcesso);

        return $alias ? $this->urlPorAlias($alias) : null;
    }

    /** URL de busca a partir do código J + TR (ex.: "813"). */
    public function urlPorCodigo(string $codigo): ?string
    {
        $alias = $this->aliasPorCodigo($codigo);

        return $alias ? $this->urlPorAlias($alias) : null;
    }

    public function urlPorAlias(string $alias): string
    {
        return self::BASE_URL . 'api_publica_' . $alias . '/_search';
    }

    /**
     * Consulta o processo no DataJud sem gravar nada.
     *
     * @return array{status: string, mensagem: ?string, dados: array}
     *         status: "encontrado", "nao_encontrado", "indisponivel" (tribunal
     *         sem índice público) ou "erro".
     */
    public function consultarProcesso(string $numeroProcesso): array
    {
        $digitos = preg_replace('/\D/', '', $numeroProcesso) ?? '';
        $url     = $this->urlPorNumero($digitos);

        if ($url === null) {
            return [
                'status'   => 'indisponivel',
                'mensagem' => 'O tribunal deste processo não tem consulta pública no DataJud.',
                'dados'    => NormalizadorFontesProcesso::deDatajud([]),
            ];
        }

        try {
            $resposta = $this->buscar($url, $digitos);
        } catch (Throwable $e) {
            log_message('error', "DataJud: falha ao consultar {$digitos}: " . $e->getMessage());

            return [
                'status'   => 'erro',
                'mensagem' => $e->getCode() === 429
                    ? 'O DataJud está sobrecarregado no momento. Consulte de novo em alguns minutos.'
                    : 'Não foi possível consultar o DataJud agora. Tente novamente.',
                'dados'    => NormalizadorFontesProcesso::deDatajud([]),
            ];
        }

        $hits = $resposta['hits']['hits'] ?? [];

        return [
            'status'   => $hits === [] ? 'nao_encontrado' : 'encontrado',
            'mensagem' => $hits === [] ? 'Processo não encontrado no DataJud.' : null,
            'dados'    => NormalizadorFontesProcesso::deDatajud($hits),
        ];
    }

    /**
     * Faz a requisição ao DataJud.
     *
     * @throws \RuntimeException Resposta HTTP diferente de 200 ou corpo inválido.
     */
    protected function buscar(string $url, string $numeroProcesso): array
    {
        $client = $this->client ?? Services::curlrequest(['timeout' => 20]);

        // O DataJud responde 429 quando o cluster está sobrecarregado; vale
        // tentar de novo depois de uma pausa curta.
        for ($tentativa = 0; ; $tentativa++) {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => (string) getenv('API_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'body'        => json_encode(['query' => ['match' => ['numeroProcesso' => $numeroProcesso]]]),
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            if (!in_array($status, [429, 502, 503], true) || $tentativa >= self::RETENTATIVAS) {
                break;
            }
            $this->aguardar($tentativa + 1);
        }

        if ($status !== 200) {
            throw new \RuntimeException("HTTP {$status}", $status);
        }

        $dados = json_decode((string) $response->getBody(), true);
        if (!is_array($dados)) {
            throw new \RuntimeException('Resposta inválida.');
        }

        return $dados;
    }

    /** Pausa entre tentativas (segundos). Separada para os testes. */
    protected function aguardar(int $segundos): void
    {
        sleep($segundos);
    }
}
