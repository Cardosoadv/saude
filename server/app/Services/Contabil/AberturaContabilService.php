<?php

declare(strict_types=1);

namespace App\Services\Contabil;

use CodeIgniter\Database\BaseConnection;
use CognitioTechny\Contabil\Application\Period\OpeningBalances;
use CognitioTechny\Contabil\Domain\Entry\JournalEntry;
use CognitioTechny\Contabil\Domain\Exception\ConflictException;
use CognitioTechny\Contabil\Domain\Exception\ValidationException;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\UseCases;
use Config\Database;
use InvalidArgumentException;

/**
 * Abertura contábil: lança os saldos iniciais (balancete de encerramento do
 * período anterior) em um único lançamento de partidas dobradas, pelo caso de
 * uso `OpeningBalances` do pacote contábil.
 *
 * Só contas patrimoniais recebem saldo de abertura. O resultado do exercício
 * anterior deve ser levado para Lucros/Prejuízos Acumulados antes da abertura;
 * contas de receita e despesa começam zeradas. A abertura vai no primeiro
 * exercício da entidade, datada do primeiro dia dele; os exercícios seguintes
 * herdam os saldos do razão.
 *
 * @version 2.0.0
 */
class AberturaContabilService
{
    public const SOURCE_TYPE = OpeningBalances::SOURCE;

    private const TIPOS_PATRIMONIAIS = ['ASSET', 'LIABILITY', 'EQUITY'];

    private BaseConnection $db;
    private ?UseCases $useCases;

    public function __construct(?BaseConnection $db = null, ?UseCases $useCases = null)
    {
        $this->db       = $db ?? Database::connect();
        $this->useCases = $useCases;
    }

    private function useCases(): UseCases
    {
        if ($this->useCases !== null) {
            return $this->useCases;
        }

        return new UseCases(
            service('contabilSettings'),
            $this->db,
            service('contabilClock'),
            service('contabilCurrentUser'),
            service('contabilEventDispatcher')
        );
    }

    /**
     * Lança a abertura. Repetir a chamada com os mesmos saldos devolve o
     * lançamento já gravado; com saldos diferentes, exige `$substituir`, que
     * estorna a abertura anterior e lança a nova na mesma transação.
     *
     * @param list<array{code?: string, side?: string, amount?: float|int|string}> $saldos `amount` em reais
     *
     * @return array{entry: array<string, mixed>, created: bool, debitos: int, creditos: int}
     *
     * @throws InvalidArgumentException Dados inválidos ou saldos que não fecham.
     * @throws ConflictException        Abertura já lançada com outros saldos, exercício fechado ou que não é o primeiro.
     */
    public function abrir(array $saldos, string $data, int $entityId = 1, string $descricao = '', bool $substituir = false): array
    {
        $this->validarData($data);

        $balances = $this->montarSaldos($saldos, $entityId);
        [$debitos, $creditos] = $this->totais($balances);

        if ($debitos !== $creditos) {
            throw new InvalidArgumentException(sprintf(
                'Os saldos não fecham: débitos %s e créditos %s (diferença de %s).',
                $this->moeda($debitos),
                $this->moeda($creditos),
                $this->moeda(abs($debitos - $creditos))
            ));
        }

        $exercicio = $this->garantirExercicio($data, $entityId);

        if ($exercicio['start_date'] !== $data) {
            throw new InvalidArgumentException(sprintf(
                'A abertura é datada do primeiro dia do exercício: informe %s.',
                date('d/m/Y', (int) strtotime($exercicio['start_date']))
            ));
        }

        $opening = $this->useCases()->openingBalances();
        $atual   = $opening->current($entityId, $exercicio['id']);

        if ($atual !== null && $this->mesmasPartidas($atual, $balances)) {
            return $this->resultado($atual, false, $debitos, $creditos);
        }

        $descricao = trim($descricao) !== '' ? trim($descricao) : 'Abertura contábil - saldos iniciais em ' . date('d/m/Y', (int) strtotime($data));

        try {
            $entry = $opening->record($entityId, $exercicio['id'], $balances, null, $descricao, $substituir);
        } catch (ValidationException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }

        return $this->resultado($entry, true, $debitos, $creditos);
    }

    /**
     * Converte os saldos da tela (código e valor em reais) para o formato do
     * pacote, recusando contas que não sejam patrimoniais, analíticas e ativas.
     *
     * @param list<array<string, mixed>> $saldos
     *
     * @return list<array{account_id: int, side: string, amount: int}>
     */
    private function montarSaldos(array $saldos, int $entityId): array
    {
        if ($saldos === [] || ! array_is_list($saldos)) {
            throw new InvalidArgumentException('Informe ao menos um saldo de abertura.');
        }

        $contas = $this->contasPatrimoniais($entityId);

        $balances      = [];
        $desconhecidas = [];
        $repetidas     = [];

        foreach ($saldos as $i => $saldo) {
            $codigo = trim((string) ($saldo['code'] ?? ''));
            $lado   = strtoupper((string) ($saldo['side'] ?? ''));
            $valor  = (int) round((float) ($saldo['amount'] ?? 0) * 100);

            if (! in_array($lado, ['D', 'C'], true)) {
                throw new InvalidArgumentException(sprintf('Saldo %d: o lado deve ser D ou C.', $i + 1));
            }

            if ($valor <= 0) {
                throw new InvalidArgumentException(sprintf('Saldo %d (%s): o valor deve ser maior que zero.', $i + 1, $codigo));
            }

            if (! isset($contas[$codigo])) {
                $desconhecidas[] = $codigo;
                continue;
            }

            if (isset($balances[$codigo])) {
                $repetidas[] = $codigo;
                continue;
            }

            $balances[$codigo] = ['account_id' => $contas[$codigo], 'side' => $lado, 'amount' => $valor];
        }

        if ($desconhecidas !== []) {
            throw new InvalidArgumentException(
                'Conta(s) inexistente(s), inativa(s), sintética(s) ou fora do patrimônio: ' . implode(', ', array_unique($desconhecidas)) . '.'
            );
        }

        if ($repetidas !== []) {
            throw new InvalidArgumentException('Conta(s) informada(s) mais de uma vez; some os saldos em uma linha: ' . implode(', ', array_unique($repetidas)) . '.');
        }

        return array_values($balances);
    }

    /**
     * @return array<string, int> id da conta indexado pelo código
     */
    private function contasPatrimoniais(int $entityId): array
    {
        $rows = $this->db->table('acc_accounts')
            ->select('id, code')
            ->where('entity_id', $entityId)
            ->where('accepts_entries', 1)
            ->where('active', 1)
            ->whereIn('type', self::TIPOS_PATRIMONIAIS)
            ->get()
            ->getResultArray();

        $contas = [];
        foreach ($rows as $row) {
            $contas[(string) $row['code']] = (int) $row['id'];
        }

        return $contas;
    }

    /**
     * @param list<array{side: string, amount: int}> $balances
     *
     * @return array{0: int, 1: int}
     */
    private function totais(array $balances): array
    {
        $debitos = $creditos = 0;

        foreach ($balances as $balance) {
            if ($balance['side'] === 'D') {
                $debitos += $balance['amount'];
            } else {
                $creditos += $balance['amount'];
            }
        }

        return [$debitos, $creditos];
    }

    /**
     * Se o lançamento de abertura em vigor tem exatamente as partidas informadas.
     *
     * @param list<array{account_id: int, side: string, amount: int}> $balances
     */
    private function mesmasPartidas(JournalEntry $entry, array $balances): bool
    {
        $chave = static fn (int $conta, string $lado, int $valor): string => $conta . $lado . $valor;

        $gravadas = array_map(static fn (array $line): string => $chave((int) $line['account_id'], (string) $line['side'], (int) $line['amount']), $entry->toArray()['lines']);
        $novas    = array_map(static fn (array $b): string => $chave($b['account_id'], $b['side'], $b['amount']), $balances);

        sort($gravadas);
        sort($novas);

        return $gravadas === $novas;
    }

    /**
     * @return array{entry: array<string, mixed>, created: bool, debitos: int, creditos: int}
     */
    private function resultado(JournalEntry $entry, bool $created, int $debitos, int $creditos): array
    {
        return [
            'entry'    => $entry->toArray(),
            'created'  => $created,
            'debitos'  => $debitos,
            'creditos' => $creditos,
        ];
    }

    private function validarData(string $data): void
    {
        $partes = explode('-', $data);

        if (count($partes) !== 3 || ! checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]) || strlen($data) !== 10) {
            throw new InvalidArgumentException('Informe a data da abertura no formato AAAA-MM-DD.');
        }
    }

    /**
     * O exercício fiscal que cobre a data, criado (no ano civil da data) quando ainda não existe.
     *
     * @return array{id: int, start_date: string}
     */
    private function garantirExercicio(string $data, int $entityId): array
    {
        $buscar = fn (): ?array => $this->db->table('acc_fiscal_years')
            ->select('id, start_date')
            ->where('entity_id', $entityId)
            ->where('start_date <=', $data)
            ->where('end_date >=', $data)
            ->get()
            ->getRowArray();

        $row = $buscar();

        if ($row === null) {
            $this->useCases()->createFiscalYear()->execute($entityId, substr($data, 0, 4) . '-01-01');
            $row = $buscar();
        }

        return ['id' => (int) $row['id'], 'start_date' => substr((string) $row['start_date'], 0, 10)];
    }

    private function moeda(int $centavos): string
    {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}
