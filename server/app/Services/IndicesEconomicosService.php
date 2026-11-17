<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\Decimal;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\SgsClient;
use App\Repositories\IndicesEconomicosRepository;
use CodeIgniter\Events\Events;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Séries históricas dos índices econômicos usados em cálculos judiciais.
 *
 * Os valores são variações percentuais (0,83 = 0,83%). Índices do SGS (Banco
 * Central) são importados pela API; os demais entram à mão ou por CSV.
 * Depois de qualquer carga com valores novos o evento `indices_atualizados`
 * é disparado com os códigos afetados (o motor de cálculo escuta esse evento).
 */
class IndicesEconomicosService
{
    public const REGRA_CHEIO   = 'cheio';
    public const REGRA_PRO_RATA = 'pro_rata';
    public const REGRA_EXCLUIR = 'excluir';

    private const REGRAS = [self::REGRA_CHEIO, self::REGRA_PRO_RATA, self::REGRA_EXCLUIR];

    /** Início do histórico carregado na importação completa (Plano Real). */
    public const INICIO_HISTORICO = '1994-07-01';

    protected IndicesEconomicosRepository $repository;
    protected SgsClient $sgs;

    public function __construct(?IndicesEconomicosRepository $repository = null, ?SgsClient $sgs = null)
    {
        $this->repository = $repository ?? new IndicesEconomicosRepository();
        $this->sgs        = $sgs ?? new SgsClient();
    }

    // ---- Cadastro e consulta -----------------------------------------

    public function listar(): array
    {
        $indices = $this->repository->listarComResumo();
        foreach ($indices as &$indice) {
            $indice['lacunas'] = $indice['periodicidade'] === 'mensal'
                ? count($this->lacunasInternas($indice))
                : 0;
            $indice['atrasado'] = $indice['periodicidade'] === 'mensal'
                ? $this->estaAtrasado($indice['ultima_competencia'])
                : false;
        }

        return $indices;
    }

    public function obter(string $codigo): array
    {
        $indice = $this->repository->porCodigo($codigo);
        if (!$indice) {
            throw LiquidacaoException::naoEncontrada('Índice');
        }

        return $indice;
    }

    /** Cria (sem $id) ou atualiza um índice. */
    public function salvarIndice(array $dados, ?int $id = null): int
    {
        $dados['codigo'] = isset($dados['codigo']) ? strtoupper(trim((string) $dados['codigo'])) : null;
        $model = $this->repository->getModel();

        if ($id === null) {
            $novoId = (int) $model->insert($dados);
            if ($novoId === 0) {
                throw LiquidacaoException::invalida('Dados do índice inválidos.', $model->errors());
            }
            $this->repository->clearCache();

            return $novoId;
        }

        if (!$model->update($id, $dados)) {
            throw LiquidacaoException::invalida('Dados do índice inválidos.', $model->errors());
        }
        $this->repository->clearCache();

        return $id;
    }

    /** @return array<string, string> competencia => valor */
    public function valores(string $codigo, ?string $de = null, ?string $ate = null): array
    {
        $indice = $this->obter($codigo);

        return $this->repository->valores((int) $indice['id_indice'], $de, $ate);
    }

    /** Valor (percentual) de um mês, ou null se ainda não há. */
    public function valorDoMes(string $codigo, string $anoMes): ?string
    {
        $inicio = $this->primeiroDiaDoMes($anoMes);

        return $this->valores($codigo, $inicio, $inicio)[$inicio] ?? null;
    }

    /** Lança (ou corrige) um valor à mão. */
    public function lancarValor(string $codigo, string $competencia, string $valor): void
    {
        $indice = $this->obter($codigo);

        try {
            $valor       = Decimal::limpar($valor);
            $competencia = $this->normalizarCompetencia($indice, $competencia);
        } catch (InvalidArgumentException $e) {
            throw LiquidacaoException::invalida($e->getMessage());
        }

        $this->gravar($indice, [['competencia' => $competencia, 'valor' => $valor]], 'manual');
    }

    // ---- Importação ---------------------------------------------------

    /**
     * Importa as séries do SGS. Sem $codigo, todas as ativas com fonte `sgs`.
     * Por padrão busca só o que falta (do mês seguinte ao último valor até hoje);
     * `$historicoCompleto` recarrega desde INICIO_HISTORICO.
     *
     * @return array<string, array{status: string, novos: int, atualizados: int, mensagem: ?string}>
     */
    public function atualizarDoSgs(?string $codigo = null, bool $historicoCompleto = false): array
    {
        $indices = $codigo !== null
            ? [$this->obter($codigo)]
            : $this->repository->getModel()->where('ativo', 1)->where('fonte', 'sgs')->findAll();

        $resultado = [];
        $afetados  = [];
        foreach ($indices as $indice) {
            $id = (int) $indice['id_indice'];
            if ($indice['fonte'] !== 'sgs' || empty($indice['codigo_serie'])) {
                $resultado[$indice['codigo']] = ['status' => 'falha', 'novos' => 0, 'atualizados' => 0, 'mensagem' => 'Índice sem série SGS configurada.'];
                continue;
            }

            try {
                $ultima = $historicoCompleto ? null : $this->repository->ultimaCompetencia($id);
                $de     = new DateTimeImmutable($ultima !== null
                    ? (new DateTimeImmutable($ultima))->modify('first day of this month')->format('Y-m-d')
                    : self::INICIO_HISTORICO);
                // Rebusca o último mês gravado para pegar revisões.
                $linhas = $this->sgs->serie((int) $indice['codigo_serie'], $de, new DateTimeImmutable('today'));
                $gravados = $this->gravar($indice, $linhas, 'sgs', false);

                $this->repository->registrarImportacao($id, 'ok', $gravados['novos']);
                $resultado[$indice['codigo']] = ['status' => 'ok', 'mensagem' => null] + $gravados;
                if ($gravados['novos'] > 0 || $gravados['atualizados'] > 0) {
                    $afetados[] = $indice['codigo'];
                }
            } catch (Throwable $e) {
                $this->repository->registrarImportacao($id, 'falha', 0, $e->getMessage());
                $resultado[$indice['codigo']] = ['status' => 'falha', 'novos' => 0, 'atualizados' => 0, 'mensagem' => $e->getMessage()];
            }
        }

        $this->notificar($afetados);

        return $resultado;
    }

    /**
     * Importa valores de um CSV (`competencia;valor`, cabeçalho opcional).
     * Competência: AAAA-MM, AAAA-MM-DD, MM/AAAA ou DD/MM/AAAA. Valor com vírgula
     * ou ponto decimal (com vírgula decimal use `;` como separador). Tudo ou nada.
     *
     * @return array{novos: int, atualizados: int}
     */
    public function importarCsv(string $codigo, string $conteudo): array
    {
        $indice = $this->obter($codigo);
        $linhas = [];
        $erros  = [];

        $registros = preg_split('/\R/', trim($conteudo)) ?: [];
        $separador = substr_count($registros[0] ?? '', ';') > 0 ? ';' : ',';

        foreach ($registros as $n => $registro) {
            if (trim($registro) === '') {
                continue;
            }
            $campos = str_getcsv($registro, $separador, '"', '\\');
            if (count($campos) < 2) {
                $erros[] = 'Linha ' . ($n + 1) . ': esperado "competência;valor".';
                continue;
            }

            try {
                $competencia = $this->normalizarCompetencia($indice, trim($campos[0]));
                $valor       = Decimal::limpar($campos[1]);
            } catch (InvalidArgumentException $e) {
                if ($n === 0) {
                    continue; // cabeçalho
                }
                $erros[] = 'Linha ' . ($n + 1) . ': ' . $e->getMessage();
                continue;
            }
            $linhas[] = ['competencia' => $competencia, 'valor' => $valor];
        }

        if ($erros !== []) {
            throw LiquidacaoException::invalida('O CSV tem linhas inválidas; nada foi importado.', $erros);
        }
        if ($linhas === []) {
            throw LiquidacaoException::invalida('O CSV não tem valores.');
        }

        return $this->gravar($indice, $linhas, 'csv');
    }

    // ---- Cálculo ------------------------------------------------------

    /**
     * Fator acumulado entre duas datas (string decimal, ex.: "1.045312345678").
     *
     * @param array{mes_inicial?: string, mes_final?: string, tolerar_lacuna_final?: bool} $regra
     *
     * @throws LiquidacaoException se faltar índice em algum mês do período
     */
    public function fatorAcumulado(string $codigo, string $dataInicial, string $dataFinal, array $regra = []): string
    {
        return $this->fatorAcumuladoDetalhado($codigo, $dataInicial, $dataFinal, $regra)['fator'];
    }

    /**
     * Fator acumulado com o passo a passo (alimenta a memória de cálculo).
     *
     * Índice mensal: o mês da data inicial e o da final seguem a regra
     * (`cheio` usa a variação inteira do mês; `pro_rata` usa a fração linear
     * dos dias do mês dentro do período; `excluir` ignora o mês). Padrão: `cheio`.
     * Índice diário: soma os dias de $dataInicial a $dataFinal, ambos inclusive.
     *
     * @return array{indice: string, fator: string, passos: list<array>, ate_competencia: ?string, ignoradas: list<string>}
     */
    public function fatorAcumuladoDetalhado(string $codigo, string $dataInicial, string $dataFinal, array $regra = []): array
    {
        $indice = $this->obter($codigo);
        $inicio = $this->data($dataInicial);
        $fim    = $this->data($dataFinal);
        if ($inicio > $fim) {
            throw LiquidacaoException::invalida('A data inicial é posterior à data final.');
        }

        $mesInicial = $regra['mes_inicial'] ?? self::REGRA_CHEIO;
        $mesFinal   = $regra['mes_final'] ?? self::REGRA_CHEIO;
        foreach ([$mesInicial, $mesFinal] as $r) {
            if (!in_array($r, self::REGRAS, true)) {
                throw LiquidacaoException::invalida("Regra de mês inválida: {$r}.");
            }
        }

        $id = (int) $indice['id_indice'];
        $chave = 'fator_' . md5(json_encode([$id, $dataInicial, $dataFinal, $mesInicial, $mesFinal, $regra['tolerar_lacuna_final'] ?? false]));

        return $this->repository->remember($chave, function () use ($indice, $id, $inicio, $fim, $mesInicial, $mesFinal, $regra) {
            return $indice['periodicidade'] === 'diaria'
                ? $this->fatorDiario($indice, $id, $inicio, $fim)
                : $this->fatorMensal($indice, $id, $inicio, $fim, $mesInicial, $mesFinal, (bool) ($regra['tolerar_lacuna_final'] ?? false));
        });
    }

    /**
     * Meses sem valor entre o primeiro e o último valor da série mensal.
     *
     * @return list<string> AAAA-MM
     */
    public function verificarLacunas(string $codigo): array
    {
        return $this->lacunasInternas($this->obter($codigo) + ['ultima_competencia' => null]);
    }

    // ---- Internos -----------------------------------------------------

    private function fatorMensal(array $indice, int $id, DateTimeImmutable $inicio, DateTimeImmutable $fim, string $mesInicial, string $mesFinal, bool $toleraFinal): array
    {
        $mesIni = $inicio->modify('first day of this month');
        $mesFim = $fim->modify('first day of this month');
        $valores = $this->repository->valores($id, $mesIni->format('Y-m-d'), $mesFim->format('Y-m-d'));
        $mesmoMes = $mesIni == $mesFim;

        // meses que entram no cálculo, com a fração de cada um
        $meses = [];
        for ($m = $mesIni; $m <= $mesFim; $m = $m->modify('+1 month')) {
            $ehInicial = $m == $mesIni;
            $ehFinal   = $m == $mesFim;
            $dias      = (int) $m->format('t');
            $fracao    = '1';

            if ($mesmoMes) {
                if ($mesInicial === self::REGRA_EXCLUIR || $mesFinal === self::REGRA_EXCLUIR) {
                    continue;
                }
                if ($mesInicial === self::REGRA_PRO_RATA || $mesFinal === self::REGRA_PRO_RATA) {
                    $fracao = Decimal::div((string) ((int) $fim->format('j') - (int) $inicio->format('j')), (string) $dias);
                }
            } elseif ($ehInicial) {
                if ($mesInicial === self::REGRA_EXCLUIR) {
                    continue;
                }
                if ($mesInicial === self::REGRA_PRO_RATA) {
                    $fracao = Decimal::div((string) ($dias - (int) $inicio->format('j') + 1), (string) $dias);
                }
            } elseif ($ehFinal) {
                if ($mesFinal === self::REGRA_EXCLUIR) {
                    continue;
                }
                if ($mesFinal === self::REGRA_PRO_RATA) {
                    $fracao = Decimal::div($fim->format('j'), (string) $dias);
                }
            }
            $meses[] = [$m, $fracao];
        }

        // meses finais ainda não divulgados podem ser ignorados
        $ignoradas = [];
        if ($toleraFinal) {
            while ($meses !== [] && !isset($valores[end($meses)[0]->format('Y-m-d')])) {
                $ignoradas[] = array_pop($meses)[0]->format('Y-m');
            }
            $ignoradas = array_reverse($ignoradas);
        }

        $faltantes = [];
        foreach ($meses as [$m]) {
            if (!isset($valores[$m->format('Y-m-d')])) {
                $faltantes[] = $m->format('Y-m');
            }
        }
        if ($faltantes !== []) {
            throw LiquidacaoException::invalida(
                "Faltam valores do índice {$indice['codigo']} para: " . implode(', ', $faltantes) . '.',
                ['lacunas' => implode(', ', $faltantes)]
            );
        }

        $fator  = '1';
        $passos = [];
        foreach ($meses as [$m, $fracao]) {
            $valor     = $valores[$m->format('Y-m-d')];
            $fatorMes  = Decimal::soma('1', Decimal::mul(Decimal::div($valor, '100', 14), $fracao, 14));
            $fator     = Decimal::mul($fator, $fatorMes);
            $passos[]  = ['competencia' => $m->format('Y-m'), 'valor' => $valor, 'fracao' => $fracao, 'fator' => $fatorMes];
        }

        return [
            'indice'          => $indice['codigo'],
            'fator'           => $fator,
            'passos'          => $passos,
            'ate_competencia' => $passos === [] ? null : end($passos)['competencia'],
            'ignoradas'       => $ignoradas,
        ];
    }

    private function fatorDiario(array $indice, int $id, DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $fator  = '1';
        $passos = [];
        foreach ($this->repository->valores($id, $inicio->format('Y-m-d'), $fim->format('Y-m-d')) as $dia => $valor) {
            $fatorDia = Decimal::fatorDoPercentual($valor);
            $fator    = Decimal::mul($fator, $fatorDia);
            $passos[] = ['competencia' => $dia, 'valor' => $valor, 'fracao' => '1', 'fator' => $fatorDia];
        }

        return [
            'indice'          => $indice['codigo'],
            'fator'           => $fator,
            'passos'          => $passos,
            'ate_competencia' => $passos === [] ? null : end($passos)['competencia'],
            'ignoradas'       => [],
        ];
    }

    /** @return list<string> AAAA-MM */
    private function lacunasInternas(array $indice): array
    {
        $valores = $this->repository->valores((int) $indice['id_indice']);
        if (count($valores) < 2) {
            return [];
        }

        $datas   = array_keys($valores);
        $lacunas = [];
        $fim     = new DateTimeImmutable(end($datas));
        for ($m = new DateTimeImmutable($datas[0]); $m <= $fim; $m = $m->modify('+1 month')) {
            if (!isset($valores[$m->format('Y-m-d')])) {
                $lacunas[] = $m->format('Y-m');
            }
        }

        return $lacunas;
    }

    /**
     * Aproximação: o índice do mês M costuma sair por volta do dia 10 de M+1.
     * A data de divulgação de cada índice deve ser conferida na fonte.
     */
    private function estaAtrasado(?string $ultimaCompetencia, ?DateTimeImmutable $hoje = null): bool
    {
        if ($ultimaCompetencia === null) {
            return true;
        }
        $hoje ??= new DateTimeImmutable('today');
        $esperada = $hoje->modify('first day of this month')->modify((int) $hoje->format('j') >= 15 ? '-1 month' : '-2 months');

        return new DateTimeImmutable($ultimaCompetencia) < $esperada;
    }

    private function gravar(array $indice, array $linhas, string $origem, bool $notificar = true): array
    {
        $resultado = $this->repository->gravarValores((int) $indice['id_indice'], $linhas, $origem);
        if ($notificar && ($resultado['novos'] > 0 || $resultado['atualizados'] > 0)) {
            $this->notificar([$indice['codigo']]);
        }

        return $resultado;
    }

    /** @param list<string> $codigos */
    private function notificar(array $codigos): void
    {
        if ($codigos !== []) {
            Events::trigger('indices_atualizados', array_values(array_unique($codigos)));
        }
    }

    private function data(string $data): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($d === false || $d->format('Y-m-d') !== $data) {
            throw LiquidacaoException::invalida("Data inválida: {$data}.");
        }

        return $d;
    }

    private function primeiroDiaDoMes(string $anoMes): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})/', $anoMes, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw LiquidacaoException::invalida("Competência inválida: {$anoMes}.");
        }

        return "{$m[1]}-{$m[2]}-01";
    }

    /** Competência em AAAA-MM-DD; para índice mensal, sempre o dia 1. */
    private function normalizarCompetencia(array $indice, string $texto): string
    {
        $texto = trim($texto);
        $ano = $mes = $dia = null;

        if (preg_match('/^(\d{4})-(\d{2})$/', $texto, $m)) {
            [, $ano, $mes, $dia] = [0, $m[1], $m[2], '01'];
        } elseif (preg_match('/^(\d{2})\/(\d{4})$/', $texto, $m)) {
            [, $ano, $mes, $dia] = [0, $m[2], $m[1], '01'];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m)) {
            [, $ano, $mes, $dia] = [0, $m[1], $m[2], $m[3]];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $texto, $m)) {
            [, $ano, $mes, $dia] = [0, $m[3], $m[2], $m[1]];
        }

        if ($ano === null || !checkdate((int) $mes, (int) $dia, (int) $ano)) {
            throw new InvalidArgumentException("Competência inválida: '{$texto}'.");
        }

        return $indice['periodicidade'] === 'mensal' ? "{$ano}-{$mes}-01" : "{$ano}-{$mes}-{$dia}";
    }
}
