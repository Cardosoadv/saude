<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use Config\Services;
use DateTimeInterface;
use RuntimeException;
use Throwable;

/**
 * Cliente da API pública de séries temporais (SGS) do Banco Central.
 *
 * A requisição HTTP é injetável (`fn(string $url): array{status:int, body:string}`)
 * para que os testes não dependam de rede.
 */
class SgsClient
{
    public const URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados?formato=json&dataInicial=%s&dataFinal=%s';

    /** @var callable(string): array{status:int, body:string} */
    private $http;

    /**
     * @param callable|null $http          requisição HTTP; padrão: CURLRequest do CodeIgniter
     * @param int           $tentativas    total de tentativas por consulta
     * @param int           $esperaMs      espera base entre tentativas (dobra a cada nova tentativa)
     */
    public function __construct(?callable $http = null, private int $tentativas = 3, private int $esperaMs = 500)
    {
        $this->http = $http ?? static function (string $url): array {
            $resposta = Services::curlrequest(['timeout' => 30, 'http_errors' => false])->get($url);

            return ['status' => $resposta->getStatusCode(), 'body' => (string) $resposta->getBody()];
        };
    }

    /**
     * @return list<array{competencia: string, valor: string}> competencia em AAAA-MM-DD
     *
     * @throws RuntimeException após esgotar as tentativas ou se a resposta for inválida
     */
    public function serie(int $codigoSerie, DateTimeInterface $de, DateTimeInterface $ate): array
    {
        $url = sprintf(self::URL, $codigoSerie, $de->format('d/m/Y'), $ate->format('d/m/Y'));

        $ultimoErro = 'sem resposta';
        for ($tentativa = 1; $tentativa <= $this->tentativas; $tentativa++) {
            try {
                $resposta = ($this->http)($url);
                $status   = (int) $resposta['status'];

                if ($status === 200) {
                    return $this->interpretar((string) $resposta['body'], $codigoSerie);
                }

                $ultimoErro = "HTTP {$status}";
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    break; // erro do pedido (série inexistente etc.): repetir não adianta
                }
            } catch (Throwable $e) {
                $ultimoErro = $e->getMessage();
            }

            if ($tentativa < $this->tentativas && $this->esperaMs > 0) {
                usleep($this->esperaMs * 1000 * (2 ** ($tentativa - 1)));
            }
        }

        log_message('error', "[SgsClient] Falha ao consultar a série {$codigoSerie}: {$ultimoErro}");
        throw new RuntimeException("Falha ao consultar a série {$codigoSerie} no SGS: {$ultimoErro}");
    }

    /** @return list<array{competencia: string, valor: string}> */
    private function interpretar(string $corpo, int $codigoSerie): array
    {
        $dados = json_decode($corpo, true);
        if (!is_array($dados)) {
            throw new RuntimeException("Resposta inválida do SGS para a série {$codigoSerie}.");
        }

        $linhas = [];
        foreach ($dados as $ponto) {
            if (!is_array($ponto) || !isset($ponto['data'], $ponto['valor'])) {
                continue;
            }
            $data = \DateTimeImmutable::createFromFormat('!d/m/Y', (string) $ponto['data']);
            if ($data === false || !is_numeric($ponto['valor'])) {
                continue; // ponto sem divulgação ("valor": "")
            }
            $linhas[] = ['competencia' => $data->format('Y-m-d'), 'valor' => (string) $ponto['valor']];
        }

        return $linhas;
    }
}
