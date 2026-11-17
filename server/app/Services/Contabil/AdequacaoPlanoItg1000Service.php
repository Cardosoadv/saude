<?php

declare(strict_types=1);

namespace App\Services\Contabil;

use CodeIgniter\Database\BaseConnection;
use CognitioTechny\Contabil\Application\Chart\ChartFormat;
use CognitioTechny\Contabil\Domain\Exception\ConflictException;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\UseCases;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Converte o plano de contas anterior da clínica para o plano padrão
 * (ITG 1000, `app/Database/PlanosModelo/itg-1000-clinica.json`) sem perder
 * lançamentos.
 *
 * Cada conta analítica antiga é reaproveitada no mesmo registro (mesmo `id`):
 * ganha o código novo, vai para o novo grupo e, quando indicado, o nome do
 * plano. Assim lançamentos, saldos, regras e o dicionário de categorias
 * continuam apontando para a mesma conta. As contas sintéticas antigas, que
 * nunca recebem lançamentos, são substituídas pelas do plano novo.
 *
 * Só converte quando todas as contas analíticas existentes são as do plano
 * anterior (mesmo código e mesmo nome); qualquer diferença interrompe a
 * conversão antes de gravar, para ser tratada à mão.
 *
 * @version 1.0.0
 */
class AdequacaoPlanoItg1000Service
{
    /**
     * Plano anterior: código antigo => [nome antigo, código novo, manter o nome atual].
     * O nome atual é mantido onde carrega informação da clínica (banco, sócios);
     * nesses casos o nome esperado termina em `*` e basta o início bater.
     */
    public const MAPA = [
        '1.1.01' => ['Caixa Geral', '1.1.1.01.01', false],
        '1.1.02' => ['Banco *', '1.1.1.02.01', true],
        '1.1.03' => ['Aplicação - CDB', '1.1.1.03.01', true],
        '1.1.04' => ['Clientes - Atendimentos a Receber', '1.1.2.01.01', false],
        '1.1.05' => ['Despesas Adiantadas a Reembolsar', '1.1.2.07.01', false],
        '1.2.01' => ['Conta Bancária de Clientes', '1.3.1.01.01', false],
        '1.3.01' => ['Direitos Creditórios', '1.2.1.01.06', false],
        '1.3.02' => ['Móveis e Equipamentos', '1.2.3.01.50', true],
        '1.3.03' => ['(-) Depreciação Acumulada', '1.2.3.02.50', true],
        '2.1.01' => ['Fornecedores a Pagar', '2.1.2.01.01', false],
        '2.1.02' => ['INSS a Recolher', '2.1.1.01.03', false],
        '2.1.03' => ['Pró-Labore a Pagar', '2.1.1.01.12', false],
        '2.1.04' => ['Simples Nacional a Recolher', '2.1.4.02.10', false],
        '2.1.05' => ['Empréstimos de Sócios', '2.1.6.01.03', false],
        '2.1.06' => ['Atendimentos Recebidos Antecipadamente', '2.1.2.02.02', false],
        '2.2.01' => ['Valores de Clientes a Repassar', '2.3.1.01.01', false],
        '2.2.02' => ['Depósitos de Clientes para Despesas', '2.3.1.01.02', false],
        '3.1.01' => ['Capital Social', '3.1.01.01', false],
        '3.2.01' => ['Lucros Acumulados', '3.8.01.01', false],
        '3.2.02' => ['(-) Prejuízos Acumulados', '3.8.02.01', false],
        '3.3.01' => ['Lucro Distribuído - *', '3.9.01.01', true],
        '3.3.02' => ['Lucro Distribuído - *', '3.9.01.02', true],
        '4.1.01' => ['Receita de Serviços - Mercado Interno', '4.1.01.01', true],
        '4.1.02' => ['Convênios e Planos de Saúde', '4.1.01.02', false],
        '4.2.01' => ['(-) Simples Nacional', '4.2.01.05', false],
        '4.3.01' => ['Rendimentos de Renda Fixa', '4.3.01.01', false],
        '4.3.02' => ['Despesas Reembolsadas', '4.4.01.01', false],
        '5.1.01' => ['Pró-Labore', '5.3.01.12', false],
        '5.1.02' => ['INSS', '5.3.01.05', false],
        '5.1.03' => ['Bolsa Estágio', '5.3.01.13', false],
        '5.2.01' => ['Assistência Contábil', '5.3.02.15', false],
        '5.2.02' => ['Serviços Prestados por Terceiros - PJ', '5.3.02.06', false],
        '5.2.03' => ['Certificado Digital', '5.3.02.16', false],
        '5.2.04' => ['Despesas Cartorárias', '5.3.02.17', false],
        '5.2.05' => ['Materiais e Insumos Clínicos', '5.3.02.18', false],
        '5.2.06' => ['Taxas Municipais', '5.3.03.01', false],
        '5.2.07' => ['Depreciação', '5.3.02.04', false],
        '5.3.01' => ['Viagens e Hospedagem', '5.3.02.19', false],
        '5.3.02' => ['Comissões de Vendas', '5.2.01.01', false],
        '5.3.03' => ['Marketing e Publicidade', '5.2.01.02', false],
        '5.4.01' => ['Juros/Multas', '5.5.01.01', false],
        '5.4.02' => ['Tarifas Bancárias', '5.5.01.02', false],
    ];

    private BaseConnection $db;
    private ?UseCases $useCases;

    public function __construct(?BaseConnection $db = null, ?UseCases $useCases = null)
    {
        $this->db       = $db ?? Database::connect();
        $this->useCases = $useCases;
    }

    private function useCases(): UseCases
    {
        return $this->useCases ??= new UseCases(
            service('contabilSettings'),
            $this->db,
            service('contabilClock'),
            service('contabilCurrentUser'),
            service('contabilEventDispatcher')
        );
    }

    /**
     * @return array{status: string, relocadas: int, criadas: int, removidas: int, avisos: list<string>}
     *                                                                                                  `status` é `convertido`, `simulado` ou `ja_adequado`.
     *
     * @throws InvalidArgumentException O plano atual não é o plano anterior esperado.
     */
    public function adequar(int $entityId = 1, bool $simular = false): array
    {
        $plano = $this->lerPlano();

        $contas = $this->db->table('acc_accounts')->where('entity_id', $entityId)->get()->getResultArray();

        if ($contas === []) {
            throw new InvalidArgumentException('A entidade ainda não tem plano de contas; importe o modelo padrão em vez de converter.');
        }

        if ($this->jaAdequado($contas)) {
            return ['status' => 'ja_adequado', 'relocadas' => 0, 'criadas' => 0, 'removidas' => 0, 'avisos' => []];
        }

        $analiticas = array_values(array_filter($contas, static fn (array $c): bool => (int) $c['accepts_entries'] === 1));
        $this->validar($analiticas, $plano);

        $this->db->transBegin();

        try {
            $resultado = $this->converter($entityId, $contas, $analiticas, $plano);
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        if ($simular) {
            $this->db->transRollback();
        } else {
            $this->db->transCommit();
        }

        $resultado['status'] = $simular ? 'simulado' : 'convertido';

        return $resultado;
    }

    /**
     * @param list<array<string, mixed>> $contas
     */
    private function jaAdequado(array $contas): bool
    {
        foreach ($contas as $conta) {
            if ($conta['code'] === '1.1.1.02.01' && (string) ($conta['reference_code'] ?? '') === '1.1.1.02.01') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>> linhas do plano novo por código
     */
    private function lerPlano(): array
    {
        $arquivo = APPPATH . 'Database/PlanosModelo/' . PlanoContasModeloService::MODELO_PADRAO . '.json';
        $linhas  = ChartFormat::parse((string) file_get_contents($arquivo), ChartFormat::JSON);

        $plano = [];
        foreach ($linhas as $linha) {
            $plano[(string) $linha['code']] = $linha;
        }

        return $plano;
    }

    /**
     * @param list<array<string, mixed>>         $analiticas
     * @param array<string, array<string, mixed>> $plano
     */
    private function validar(array $analiticas, array $plano): void
    {
        $problemas = [];
        $tipos     = $this->tiposDoPlano($plano);

        foreach ($analiticas as $conta) {
            $codigo = (string) $conta['code'];

            if (! isset(self::MAPA[$codigo])) {
                $problemas[] = sprintf('%s - %s: conta fora do plano anterior; não há para onde levá-la.', $codigo, $conta['name']);
                continue;
            }

            [$nomeAntigo, $novo] = self::MAPA[$codigo];

            if (! $this->nomeConfere((string) $conta['name'], $nomeAntigo)) {
                $problemas[] = sprintf('%s: o nome é "%s", esperava "%s".', $codigo, $conta['name'], $nomeAntigo);
            }

            if (($tipos[$novo] ?? null) !== $conta['type']) {
                $problemas[] = sprintf('%s: o tipo %s não combina com o destino %s (%s).', $codigo, $conta['type'], $novo, $tipos[$novo] ?? 'inexistente');
            }
        }

        if ($problemas !== []) {
            throw new InvalidArgumentException("O plano atual não é o plano anterior esperado:\n- " . implode("\n- ", $problemas));
        }
    }

    private function nomeConfere(string $atual, string $esperado): bool
    {
        $atual    = mb_strtolower(trim($atual));
        $esperado = mb_strtolower($esperado);

        if (str_ends_with($esperado, '*')) {
            return str_starts_with($atual, rtrim($esperado, '* '));
        }

        return $atual === $esperado;
    }

    /**
     * @param array<string, array<string, mixed>> $plano
     *
     * @return array<string, string> tipo (herdado da raiz) por código
     */
    private function tiposDoPlano(array $plano): array
    {
        $tipos = [];

        foreach ($plano as $codigo => $linha) {
            $raiz          = explode('.', (string) $codigo)[0];
            $tipos[$codigo] = (string) $plano[$raiz]['type'];
        }

        return $tipos;
    }

    /**
     * @param list<array<string, mixed>>          $contas
     * @param list<array<string, mixed>>          $analiticas
     * @param array<string, array<string, mixed>> $plano
     *
     * @return array{relocadas: int, criadas: int, removidas: int, avisos: list<string>}
     */
    private function converter(int $entityId, array $contas, array $analiticas, array $plano): array
    {
        $avisos = [];

        // Libera os códigos: o plano novo reaproveita vários (1, 1.1, 2.1...).
        foreach ($contas as $conta) {
            $this->db->table('acc_accounts')->where('id', $conta['id'])->update(['code' => 'ANTIGA-' . $conta['id']]);
        }

        $destinos = [];
        foreach ($analiticas as $conta) {
            $destinos[self::MAPA[$conta['code']][1]] = $conta;
        }

        $linhas = array_values(array_filter($plano, static fn (array $linha): bool => ! isset($destinos[(string) $linha['code']])));
        $criar  = $this->useCases()->importChart()->execute($entityId, $linhas, false);

        $porCodigo = [];
        foreach ($this->db->table('acc_accounts')->select('id, code')->where('entity_id', $entityId)->get()->getResultArray() as $linha) {
            $porCodigo[$linha['code']] = (int) $linha['id'];
        }

        foreach ($destinos as $codigoNovo => $conta) {
            $linha    = $plano[$codigoNovo];
            $paiCodigo = (string) $linha['parent_code'];

            $this->useCases()->moveAccount()->execute($entityId, (int) $conta['id'], $porCodigo[$paiCodigo], $codigoNovo);

            $mudancas = ['reference_code' => $linha['reference_code'] ?? null];
            $antigo   = self::MAPA[$conta['code']];

            if (! $antigo[2]) {
                $mudancas['name'] = $linha['name'];
            }

            $this->useCases()->updateAccount()->execute($entityId, (int) $conta['id'], $mudancas);

            $contra = (bool) ($linha['contra'] ?? false);
            if ($contra !== $this->ehContra($conta)) {
                try {
                    $this->useCases()->updateAccount()->execute($entityId, (int) $conta['id'], ['contra' => $contra]);
                } catch (ConflictException) {
                    $avisos[] = sprintf('%s (%s): já tem movimento, então a natureza %s não pôde ser ajustada.', $codigoNovo, $linha['name'], $contra ? 'redutora' : 'normal');
                }
            }
        }

        $removidas = $this->removerGruposAntigos($entityId);

        return ['relocadas' => count($destinos), 'criadas' => $criar->created, 'removidas' => $removidas, 'avisos' => $avisos];
    }

    /**
     * @param array<string, mixed> $conta
     */
    private function ehContra(array $conta): bool
    {
        $normal = in_array($conta['type'], ['ASSET', 'EXPENSE'], true) ? 'DEBIT' : 'CREDIT';

        return strtoupper((string) $conta['nature']) !== $normal;
    }

    private function removerGruposAntigos(int $entityId): int
    {
        $removidas = 0;

        do {
            $antigas = $this->db->table('acc_accounts')
                ->select('id')
                ->where('entity_id', $entityId)
                ->like('code', 'ANTIGA-', 'after')
                ->orderBy('level', 'DESC')
                ->get()
                ->getResultArray();

            $removeu = false;

            foreach ($antigas as $antiga) {
                if ($this->db->table('acc_accounts')->where('parent_id', $antiga['id'])->countAllResults() > 0) {
                    continue;
                }

                $this->useCases()->deleteAccount()->execute($entityId, (int) $antiga['id']);
                $removidas++;
                $removeu = true;
            }
        } while ($removeu && $antigas !== []);

        $restantes = $this->db->table('acc_accounts')->where('entity_id', $entityId)->like('code', 'ANTIGA-', 'after')->countAllResults();

        if ($restantes > 0) {
            throw new RuntimeException(sprintf('%d conta(s) do plano anterior não puderam ser removidas.', $restantes));
        }

        return $removidas;
    }
}
