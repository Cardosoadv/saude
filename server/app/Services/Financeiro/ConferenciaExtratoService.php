<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use Closure;

/**
 * Conferência informativa entre o saldo do banco (LEDGERBAL do último arquivo OFX
 * do mês) e o saldo do sistema na mesma data, e resumo da conciliação do mês.
 *
 * Nada aqui bloqueia o fechamento do mês (mesmo critério das pendências de comprovante).
 * Valores em centavos. Os logs não trazem memo nem nome de contraparte.
 */
class ConferenciaExtratoService
{
    public const SITUACAO_SEM_SALDO  = 'sem_saldo_banco';
    public const SITUACAO_CONFERIDO  = 'conferido';
    public const SITUACAO_EXPLICADA  = 'explicada_por_pendentes';
    public const SITUACAO_DIVERGENTE = 'divergente';

    private ExtratosImportacoesRepository $importacoes;
    private ExtratosMovimentosRepository $movimentos;
    /** @var Closure(int,string):float Saldo do sistema (em reais) da conta ao fim da data. */
    private Closure $saldoDoSistema;

    public function __construct(
        ?ExtratosImportacoesRepository $importacoes = null,
        ?ExtratosMovimentosRepository $movimentos = null,
        ?Closure $saldoDoSistema = null
    ) {
        $this->importacoes = $importacoes ?? new ExtratosImportacoesRepository();
        $this->movimentos  = $movimentos ?? new ExtratosMovimentosRepository();
        $this->saldoDoSistema = $saldoDoSistema ?? static function (int $contaId, string $data): float {
            $extrato = (new ExtratoService())->getExtratoPorPeriodo($contaId, $data, $data);

            return (float) ($extrato['saldo_final'] ?? 0);
        };
    }

    /**
     * Compara o saldo do banco com o do sistema.
     *
     * `diferenca` = saldo_banco - saldo_sistema. Os movimentos pendentes (no banco e ainda
     * não lançados) até a data do saldo explicam a diferença quando a soma deles a fecha;
     * o que sobrar vem em `diferenca_nao_explicada`.
     *
     * @return array<string,mixed>
     * @throws ExtratoInvalidoException Conta ou mês inválidos.
     */
    public function conferir(int $contaId, int $ano, int $mes): array
    {
        [$inicio, $fim] = $this->periodo($ano, $mes);
        if ($contaId <= 0) {
            throw new ExtratoInvalidoException('Informe a conta (conta_id).');
        }

        $conciliacao = $this->conciliacaoDoMes($inicio, $fim, $contaId);
        $base = ['conta_id' => $contaId, 'ano' => $ano, 'mes' => $mes, 'conciliacao' => $conciliacao];

        $importacao = $this->importacoes->ultimoSaldoNoPeriodo($contaId, $inicio, $fim);
        if ($importacao === null) {
            return $base + [
                'situacao'                => self::SITUACAO_SEM_SALDO,
                'mensagem'                => 'Nenhum extrato importado traz o saldo do banco neste mês.',
                'data_saldo'              => null,
                'saldo_banco'             => null,
                'saldo_sistema'           => null,
                'diferenca'               => null,
                'total_pendentes'         => 0,
                'soma_pendentes'          => 0,
                'diferenca_nao_explicada' => null,
                'pendentes'               => [],
            ];
        }

        $dataSaldo   = (string) $importacao['saldo_final_data'];
        $saldoBanco  = (int) $importacao['saldo_final_arquivo'];
        $saldoSistema = (int) round(($this->saldoDoSistema)($contaId, $dataSaldo) * 100);
        $diferenca   = $saldoBanco - $saldoSistema;

        $pendentes = [];
        $soma      = 0;
        foreach ($this->movimentos->listarPendentesAte($contaId, $dataSaldo) as $mov) {
            $valor = (int) $mov['valor_centavos'];
            $soma += $valor;
            $pendentes[] = [
                'id_movimento'     => (int) $mov['id_movimento'],
                'data'             => (string) $mov['data'],
                'valor_centavos'   => $valor,
                'memo'             => $mov['memo'] ?? null,
                'nome_contraparte' => $mov['nome_contraparte'] ?? null,
            ];
        }

        $naoExplicada = $diferenca - $soma;
        if ($diferenca === 0) {
            $situacao = self::SITUACAO_CONFERIDO;
            $mensagem = 'O saldo do sistema confere com o do banco.';
        } elseif ($naoExplicada === 0) {
            $situacao = self::SITUACAO_EXPLICADA;
            $mensagem = 'A diferença é explicada pelos movimentos do extrato ainda pendentes.';
        } else {
            $situacao = self::SITUACAO_DIVERGENTE;
            $mensagem = 'Há diferença entre o saldo do banco e o do sistema que os movimentos pendentes não explicam.';
        }

        return $base + [
            'situacao'                => $situacao,
            'mensagem'                => $mensagem,
            'importacao_id'           => (int) $importacao['id_importacao'],
            'data_saldo'              => $dataSaldo,
            'saldo_banco'             => $saldoBanco,
            'saldo_sistema'           => $saldoSistema,
            'diferenca'               => $diferenca,
            'total_pendentes'         => count($pendentes),
            'soma_pendentes'          => $soma,
            'diferenca_nao_explicada' => $naoExplicada,
            'pendentes'               => $pendentes,
        ];
    }

    /**
     * Status de conciliação do mês, por conta e no total (para o fechamento).
     *
     * @return array{ano:int,mes:int,pendentes:int,ignorados:int,conciliados:int,lancados:int,contas:array}
     * @throws ExtratoInvalidoException Mês inválido.
     */
    public function resumoDoMes(int $ano, int $mes): array
    {
        [$inicio, $fim] = $this->periodo($ano, $mes);

        $porConta = $this->movimentos->contarPorStatus($inicio, $fim);
        $resumo = ['ano' => $ano, 'mes' => $mes] + $this->totais($porConta) + ['contas' => []];

        foreach ($porConta as $contaId => $c) {
            $resumo['contas'][] = ['conta_id' => $contaId] + $this->totais([$contaId => $c]);
        }

        return $resumo;
    }

    /** @return array{pendentes:int,ignorados:int,conciliados:int,lancados:int} */
    private function conciliacaoDoMes(string $inicio, string $fim, int $contaId): array
    {
        return $this->totais($this->movimentos->contarPorStatus($inicio, $fim, $contaId));
    }

    /**
     * @param array<int,array{pendente:int,conciliado:int,lancado:int,ignorado:int}> $porConta
     * @return array{pendentes:int,ignorados:int,conciliados:int,lancados:int}
     */
    private function totais(array $porConta): array
    {
        $t = ['pendentes' => 0, 'ignorados' => 0, 'conciliados' => 0, 'lancados' => 0];
        foreach ($porConta as $c) {
            $t['pendentes']   += $c['pendente'];
            $t['ignorados']   += $c['ignorado'];
            $t['conciliados'] += $c['conciliado'];
            $t['lancados']    += $c['lancado'];
        }

        return $t;
    }

    /** @return array{0:string,1:string} */
    private function periodo(int $ano, int $mes): array
    {
        if ($ano < 2000 || $ano > 2100 || $mes < 1 || $mes > 12) {
            throw new ExtratoInvalidoException('Informe ano e mês válidos.');
        }
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);

        return [$inicio, date('Y-m-t', strtotime($inicio))];
    }
}
