<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CalendarioForenseRepository;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Contagem de prazos processuais em dias úteis, com memória de cálculo auditável.
 *
 * Regras aplicadas (CPC arts. 219, 220 e 224; Lei 11.419/2006, art. 4º, §§ 3º e 4º):
 *  - a disponibilização no Diário Eletrônico é publicada no primeiro dia útil seguinte;
 *  - exclui-se o dia do começo (a publicação) e conta-se a partir do primeiro dia útil seguinte;
 *  - o dia do vencimento é incluído;
 *  - só contam dias úteis: ficam de fora sábados, domingos, feriados aplicáveis ao
 *    tribunal/UF/comarca, o recesso de 20/12 a 20/01 e as suspensões cadastradas.
 *
 * A IA apenas identifica o tipo de ato e o número de dias; a data final sai daqui.
 * As regras devem ser conferidas pelo advogado, pois cada tribunal pode ter norma própria.
 */
class CalendarioForenseService
{
    public const MAX_DIAS = 365;

    public const MOTIVO_FIM_DE_SEMANA = 'fim_de_semana';
    public const MOTIVO_FERIADO       = 'feriado';
    public const MOTIVO_RECESSO       = 'recesso';
    public const MOTIVO_SUSPENSAO     = 'suspensao';

    private const BASE_LEGAL = [
        'CPC, art. 219 (contagem em dias úteis)',
        'CPC, art. 220 (suspensão de 20/12 a 20/01)',
        'CPC, art. 224 (exclusão do dia do começo e inclusão do vencimento)',
        'Lei 11.419/2006, art. 4º, §§ 3º e 4º (publicação no primeiro dia útil seguinte à disponibilização)',
    ];

    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    protected CalendarioForenseRepository $repository;

    public function __construct(?CalendarioForenseRepository $repository = null)
    {
        $this->repository = $repository ?? new CalendarioForenseRepository();
    }

    /**
     * Data de vencimento (Y-m-d) de um prazo em dias úteis.
     */
    public function calcularVencimento(string $dataDisponibilizacao, int $dias, ?string $tribunal = null, ?string $uf = null, ?string $comarca = null): string
    {
        return $this->memoriaCalculo($dataDisponibilizacao, $dias, $tribunal, $uf, $comarca)['vencimento'];
    }

    /**
     * Calcula o prazo e devolve todos os passos para conferência.
     *
     * @return array{
     *   metodo:string, base_legal:array, entrada:array, data_publicacao:string,
     *   inicio_contagem:string, vencimento:string, dias_uteis:int,
     *   dias_contados:array, descontados:array, avisos:array
     * }
     */
    public function memoriaCalculo(string $dataDisponibilizacao, int $dias, ?string $tribunal = null, ?string $uf = null, ?string $comarca = null): array
    {
        if ($dias < 1 || $dias > self::MAX_DIAS) {
            throw new InvalidArgumentException('O prazo deve ter entre 1 e ' . self::MAX_DIAS . ' dias.');
        }

        $disponibilizacao = $this->parseData($dataDisponibilizacao);
        $tribunal         = $tribunal !== null && trim($tribunal) !== '' ? strtoupper(trim($tribunal)) : null;
        $uf               = $this->resolverUf($uf, $tribunal);
        $comarca          = $comarca !== null && trim($comarca) !== '' ? trim($comarca) : null;

        $limite   = $disponibilizacao->modify('+' . ($dias * 3 + 400) . ' days');
        $contexto = $this->carregarContexto($disponibilizacao, $limite, $tribunal, $uf, $comarca);

        $descontados = [];
        $avisos      = [];

        // 1. Publicação: primeiro dia útil seguinte à disponibilização.
        $cursor = $disponibilizacao;
        $publicacao = $this->proximoDiaUtil($cursor, $contexto, $descontados);

        // 2. Exclui o dia do começo; o dia 1 é o primeiro dia útil seguinte à publicação.
        $contados = [];
        $cursor   = $publicacao;
        while (count($contados) < $dias) {
            $cursor = $this->proximoDiaUtil($cursor, $contexto, $descontados);
            $contados[] = $cursor->format('Y-m-d');
        }

        if ($uf === null) {
            $avisos[] = 'UF não identificada: feriados estaduais não foram considerados.';
        }
        if ($comarca === null) {
            $avisos[] = 'Comarca não informada: feriados municipais não foram considerados.';
        }

        return [
            'metodo'          => 'dias_uteis',
            'base_legal'      => self::BASE_LEGAL,
            'entrada'         => [
                'data_disponibilizacao' => $disponibilizacao->format('Y-m-d'),
                'dias'                  => $dias,
                'tribunal'              => $tribunal,
                'uf'                    => $uf,
                'comarca'               => $comarca,
            ],
            'data_publicacao' => $publicacao->format('Y-m-d'),
            'inicio_contagem' => $contados[0],
            'vencimento'      => end($contados),
            'dias_uteis'      => count($contados),
            'dias_contados'   => $contados,
            'descontados'     => $descontados,
            'avisos'          => $avisos,
        ];
    }

    /**
     * Memória de cálculo para prazos sem número de dias identificado: a data vem da
     * estimativa da IA (ou do padrão do sistema) e o advogado precisa conferi-la.
     */
    public function memoriaPrazoAConferir(?string $dataEstimada, string $origem): array
    {
        return [
            'metodo'     => 'estimativa',
            'a_conferir' => true,
            'origem'     => $origem,
            'vencimento' => $dataEstimada,
            'avisos'     => ['Prazo não calculado: o número de dias não foi identificado na intimação. Conferir manualmente.'],
        ];
    }

    /**
     * Soma uma margem de segurança em dias úteis a partir de uma data (a própria data não conta).
     */
    public function adicionarDiasUteis(string $dataInicio, int $dias, ?string $tribunal = null, ?string $uf = null, ?string $comarca = null): string
    {
        if ($dias <= 0) {
            return $dataInicio;
        }

        $inicio   = $this->parseData($dataInicio);
        $tribunal = $tribunal !== null && trim($tribunal) !== '' ? strtoupper(trim($tribunal)) : null;
        $uf       = $this->resolverUf($uf, $tribunal);
        $contexto = $this->carregarContexto($inicio, $inicio->modify('+' . ($dias * 3 + 400) . ' days'), $tribunal, $uf, $comarca);

        $descartado = [];
        $cursor = $inicio;
        for ($i = 0; $i < $dias; $i++) {
            $cursor = $this->proximoDiaUtil($cursor, $contexto, $descartado);
        }

        return $cursor->format('Y-m-d');
    }

    /**
     * Deriva a UF da sigla de tribunais estaduais (TJSP => SP). Outros tribunais devolvem null.
     */
    public function ufDoTribunal(?string $tribunal): ?string
    {
        if ($tribunal === null) {
            return null;
        }
        $tribunal = strtoupper(trim($tribunal));
        if (strlen($tribunal) === 4 && str_starts_with($tribunal, 'TJ') && in_array(substr($tribunal, 2), self::UFS, true)) {
            return substr($tribunal, 2);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // CRUD do calendário (repassa ao repositório)
    // ------------------------------------------------------------------

    public function listarFeriados(?int $ano = null): array
    {
        return $this->repository->listarFeriados($ano);
    }

    public function listarSuspensoes(): array
    {
        return $this->repository->listarSuspensoes();
    }

    public function salvarFeriado(array $dados, ?int $id = null)
    {
        return $this->repository->salvarFeriado($this->normalizarFeriado($dados), $id);
    }

    public function salvarSuspensao(array $dados, ?int $id = null)
    {
        return $this->repository->salvarSuspensao($this->normalizarSuspensao($dados), $id);
    }

    public function excluirFeriado(int $id): bool
    {
        return $this->repository->excluirFeriado($id);
    }

    public function excluirSuspensao(int $id): bool
    {
        return $this->repository->excluirSuspensao($id);
    }

    public function errosValidacao(): array
    {
        return $this->repository->errosValidacao();
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private function normalizarFeriado(array $dados): array
    {
        $abrangencia = $dados['abrangencia'] ?? 'nacional';

        return [
            'data'        => $dados['data'] ?? null,
            'nome'        => trim((string) ($dados['nome'] ?? '')),
            'abrangencia' => $abrangencia,
            'uf'          => $abrangencia === 'nacional' || empty($dados['uf']) ? null : strtoupper((string) $dados['uf']),
            'municipio'   => $abrangencia === 'municipal' && !empty($dados['municipio']) ? trim((string) $dados['municipio']) : null,
            'tribunal'    => !empty($dados['tribunal']) ? strtoupper(trim((string) $dados['tribunal'])) : null,
            'recorrente'  => !empty($dados['recorrente']) ? 1 : 0,
        ];
    }

    private function normalizarSuspensao(array $dados): array
    {
        return [
            'tribunal'    => !empty($dados['tribunal']) ? strtoupper(trim((string) $dados['tribunal'])) : null,
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim'    => $dados['data_fim'] ?? null,
            'motivo'      => trim((string) ($dados['motivo'] ?? '')),
        ];
    }

    private function parseData(string $data): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr($data, 0, 10));
        if (!$dt || $dt->format('Y-m-d') !== substr($data, 0, 10)) {
            throw new InvalidArgumentException("Data inválida: {$data}");
        }

        return $dt;
    }

    private function resolverUf(?string $uf, ?string $tribunal): ?string
    {
        $uf = $uf !== null ? strtoupper(trim($uf)) : null;
        if ($uf !== null && in_array($uf, self::UFS, true)) {
            return $uf;
        }

        return $this->ufDoTribunal($tribunal);
    }

    private function normalizarTexto(?string $texto): string
    {
        $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return preg_replace('/[^a-z0-9]+/', ' ', $semAcento !== false ? $semAcento : $texto) ?? $texto;
    }

    /**
     * Reúne, para o período, os feriados e suspensões que se aplicam ao contexto.
     *
     * @return array{feriados: array<string,string>, suspensoes: array}
     */
    private function carregarContexto(DateTimeImmutable $inicio, DateTimeImmutable $fim, ?string $tribunal, ?string $uf, ?string $comarca): array
    {
        $feriados = [];

        // Feriados nacionais calculados, sempre presentes mesmo sem seed para o ano.
        for ($ano = (int) $inicio->format('Y'); $ano <= (int) $fim->format('Y'); $ano++) {
            foreach (FeriadosNacionais::doAno($ano) as $data => $nome) {
                $feriados[$data] = $nome;
            }
        }

        $comarcaNorm = $comarca !== null ? $this->normalizarTexto($comarca) : null;

        foreach ($this->repository->feriadosNoPeriodo($inicio->format('Y-m-d'), $fim->format('Y-m-d')) as $f) {
            if (!empty($f['tribunal']) && strtoupper((string) $f['tribunal']) !== $tribunal) {
                continue;
            }
            $abrangencia = $f['abrangencia'] ?? 'nacional';
            if ($abrangencia === 'estadual' && ($uf === null || strtoupper((string) $f['uf']) !== $uf)) {
                continue;
            }
            if ($abrangencia === 'municipal') {
                if ($comarcaNorm === null || $this->normalizarTexto($f['municipio'] ?? null) !== $comarcaNorm) {
                    continue;
                }
                if (!empty($f['uf']) && $uf !== null && strtoupper((string) $f['uf']) !== $uf) {
                    continue;
                }
            }

            $datas = [];
            if (!empty($f['recorrente'])) {
                $mesDia = substr((string) $f['data'], 5, 5);
                for ($ano = (int) $inicio->format('Y'); $ano <= (int) $fim->format('Y'); $ano++) {
                    $datas[] = "{$ano}-{$mesDia}";
                }
            } else {
                $datas[] = substr((string) $f['data'], 0, 10);
            }

            foreach ($datas as $data) {
                $feriados[$data] = $f['nome'];
            }
        }

        $suspensoes = array_values(array_filter(
            $this->repository->suspensoesNoPeriodo($inicio->format('Y-m-d'), $fim->format('Y-m-d')),
            static fn (array $s) => empty($s['tribunal']) || strtoupper((string) $s['tribunal']) === $tribunal
        ));

        return ['feriados' => $feriados, 'suspensoes' => $suspensoes];
    }

    /**
     * Motivo pelo qual o dia não conta, ou null se for dia útil.
     *
     * @return array{motivo:string, descricao:string}|null
     */
    private function motivoDiaNaoUtil(DateTimeImmutable $dia, array $contexto): ?array
    {
        $data = $dia->format('Y-m-d');
        $dow  = (int) $dia->format('N');

        if ($dow >= 6) {
            return ['motivo' => self::MOTIVO_FIM_DE_SEMANA, 'descricao' => $dow === 6 ? 'Sábado' : 'Domingo'];
        }
        if (isset($contexto['feriados'][$data])) {
            return ['motivo' => self::MOTIVO_FERIADO, 'descricao' => $contexto['feriados'][$data]];
        }
        $mesDia = $dia->format('m-d');
        if ($mesDia >= '12-20' || $mesDia <= '01-20') {
            return ['motivo' => self::MOTIVO_RECESSO, 'descricao' => 'Suspensão dos prazos de 20/12 a 20/01 (CPC, art. 220)'];
        }
        foreach ($contexto['suspensoes'] as $s) {
            if ($data >= substr((string) $s['data_inicio'], 0, 10) && $data <= substr((string) $s['data_fim'], 0, 10)) {
                return ['motivo' => self::MOTIVO_SUSPENSAO, 'descricao' => (string) $s['motivo']];
            }
        }

        return null;
    }

    /**
     * Primeiro dia útil estritamente depois de $dia, registrando os dias descontados.
     */
    private function proximoDiaUtil(DateTimeImmutable $dia, array $contexto, array &$descontados): DateTimeImmutable
    {
        do {
            $dia    = $dia->modify('+1 day');
            $motivo = $this->motivoDiaNaoUtil($dia, $contexto);
            if ($motivo !== null) {
                $descontados[] = ['data' => $dia->format('Y-m-d')] + $motivo;
            }
        } while ($motivo !== null);

        return $dia;
    }
}
