<?php

declare(strict_types=1);

namespace App\Services\Contabil;

use CodeIgniter\Database\BaseConnection;
use CognitioTechny\Contabil\Application\Entry\EntryData;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\UseCases;
use Config\Database;
use InvalidArgumentException;
use Throwable;

/**
 * Serviço de Integração do Módulo Contábil com o Financeiro do sistema.
 *
 * Responsável por:
 * 1. Consultar receitas recebidas, despesas pagas e transferências bancárias.
 * 2. Cruzar registros financeiros com lançamentos contábeis já existentes (idempotência).
 * 3. Gerar partidas dobradas (débito e crédito) com contas sugeridas ou personalizadas.
 * 4. Importar transações em lote ou individualmente para o livro diário contábil.
 * 5. Sugerir contas pelas categorias financeiras (dicionário categoria -> conta), dividindo
 *    as operações rateadas em mais de uma categoria.
 *
 * @version 1.0.0
 */
class IntegracaoFinanceiraService
{
    private BaseConnection $db;
    private ?UseCases $useCases;

    public function __construct(?BaseConnection $db = null, ?UseCases $useCases = null)
    {
        $this->db = $db ?? Database::connect();
        $this->useCases = $useCases;
    }

    private function useCases(): UseCases
    {
        if ($this->useCases !== null) {
            return $this->useCases;
        }

        $settings = service('contabilSettings');

        return new UseCases(
            $settings,
            $this->db,
            service('contabilClock'),
            service('contabilCurrentUser'),
            service('contabilEventDispatcher')
        );
    }

    /**
     * Lista transações financeiras com seu respectivo status na contabilidade.
     *
     * @param string|null $dataInicio Formato YYYY-MM-DD
     * @param string|null $dataFim Formato YYYY-MM-DD
     * @param string|null $tipo 'receita', 'despesa', 'transferencia' ou null para todos
     * @param string|null $status 'pendente', 'importado' ou null para todos
     * @param int $entityId ID da entidade contábil (padrão 1)
     * @return array{transacoes: array, kpis: array}
     *
     * Uma operação dividida em vários lançamentos aparece como importada, com todos os números em `entry_numbers`.
     */
    public function obterTransacoes(
        ?string $dataInicio = null,
        ?string $dataFim = null,
        ?string $tipo = null,
        ?string $status = null,
        int $entityId = 1
    ): array {
        $anoAtual = (int) date('Y');
        $dataInicio = $dataInicio ?: "{$anoAtual}-01-01";
        $dataFim = $dataFim ?: date('Y-m-d');

        // 1. Buscar lançamentos contábeis já efetuados para cruzamento
        $lancamentosExistentes = $this->db->table('acc_entries')
            ->select('id, source_type, source_id, status, number, accrual_date')
            ->where('entity_id', $entityId)
            ->whereIn('source_type', ['fin_pgto_receita', 'fin_pgto_despesa', 'fin_transferencia'])
            ->get()
            ->getResultArray();

        $mapaImportados = [];
        foreach ($lancamentosExistentes as $entry) {
            $chave = $entry['source_type'] . ':' . $entry['source_id'];
            $mapaImportados[$chave][] = $entry;
        }

        // 2. Mapeamento de contas contábeis padrão sugeridas e dicionário de categorias
        $contasFolhas = $this->obterContasFolhas($entityId);
        $contasPadrao = $this->obterContasPadrao($entityId, $contasFolhas);
        $dicionario   = $this->mapaDicionario($entityId, array_column($contasFolhas, 'rotulo', 'id'));

        $transacoes = [];

        // 3. Buscar Receitas Recebidas
        if ($tipo === null || $tipo === '' || $tipo === 'receita') {
            $receitasQuery = $this->db->table('fin_pgto_receitas pr')
                ->select('pr.id_pgto_receita, pr.receita_id, pr.pagamento_receita_dt, pr.valor, pr.comentario, pr.conta_id, r.receita, c.conta as nome_conta')
                ->join('fin_receitas r', 'r.id_receita = pr.receita_id', 'left')
                ->join('fin_contas c', 'c.id_conta = pr.conta_id', 'left')
                ->where('pr.deleted_at', null)
                ->where('pr.pagamento_receita_dt >=', $dataInicio)
                ->where('pr.pagamento_receita_dt <=', $dataFim)
                ->orderBy('pr.pagamento_receita_dt', 'DESC');

            $receitas = $receitasQuery->get()->getResultArray();
            $categoriasReceitas = $this->categoriasPorLancamento('fin_receitas_categorias', 'receita_id', array_column($receitas, 'receita_id'));
            foreach ($receitas as $rec) {
                $id = (int) $rec['id_pgto_receita'];
                $chave = 'fin_pgto_receita:' . $id;
                $importado = isset($mapaImportados[$chave]);
                $entries = $importado ? $mapaImportados[$chave] : [];
                $entry = $entries[0] ?? null;

                $transacoes[] = $this->aplicarSugestaoCategorias([
                    'uid'             => 'rec_' . $id,
                    'tipo'            => 'receita',
                    'source_type'     => 'fin_pgto_receita',
                    'source_id'       => $id,
                    'data'            => $rec['pagamento_receita_dt'],
                    'descricao'       => 'Recebimento: ' . ($rec['receita'] ?: 'Receita de Atendimentos') . ($rec['comentario'] ? ' (' . $rec['comentario'] . ')' : ''),
                    'valor'           => (float) $rec['valor'],
                    'conta_origem'    => $rec['nome_conta'] ?: 'Conta Bancária Padrão',
                    'status_contabil' => $importado ? 'importado' : 'pendente',
                    'entry_id'        => $entry ? $entry['id'] : null,
                    'entry_number'    => $entry ? $entry['number'] : null,
                    'entry_numbers'   => array_map(static fn ($e) => (int) $e['number'], $entries),
                    'debito_id'       => $contasPadrao['banco']['id'] ?? null,
                    'debito_rotulo'   => $contasPadrao['banco']['rotulo'] ?? '1.1.1.02.01 - Bancos Conta Movimento',
                    'credito_id'      => $contasPadrao['receita']['id'] ?? null,
                    'credito_rotulo'  => $contasPadrao['receita']['rotulo'] ?? '4.1.01.01 - Atendimentos (Serviços Prestados)',
                ], $categoriasReceitas[(int) $rec['receita_id']] ?? [], $dicionario);
            }
        }

        // 4. Buscar Despesas Pagas
        if ($tipo === null || $tipo === '' || $tipo === 'despesa') {
            $despesasQuery = $this->db->table('fin_pgto_despesas pd')
                ->select('pd.id_pgto_despesa, pd.despesa_id, pd.pagamento_despesa_dt, pd.valor, pd.comentario, pd.conta_id, d.despesa, c.conta as nome_conta')
                ->join('fin_despesas d', 'd.id_despesa = pd.despesa_id', 'left')
                ->join('fin_contas c', 'c.id_conta = pd.conta_id', 'left')
                ->where('pd.deleted_at', null)
                ->where('pd.pagamento_despesa_dt >=', $dataInicio)
                ->where('pd.pagamento_despesa_dt <=', $dataFim)
                ->orderBy('pd.pagamento_despesa_dt', 'DESC');

            $despesas = $despesasQuery->get()->getResultArray();
            $categoriasDespesas = $this->categoriasPorLancamento('fin_despesas_categorias', 'despesa_id', array_column($despesas, 'despesa_id'));
            foreach ($despesas as $desp) {
                $id = (int) $desp['id_pgto_despesa'];
                $chave = 'fin_pgto_despesa:' . $id;
                $importado = isset($mapaImportados[$chave]);
                $entries = $importado ? $mapaImportados[$chave] : [];
                $entry = $entries[0] ?? null;

                $transacoes[] = $this->aplicarSugestaoCategorias([
                    'uid'             => 'desp_' . $id,
                    'tipo'            => 'despesa',
                    'source_type'     => 'fin_pgto_despesa',
                    'source_id'       => $id,
                    'data'            => $desp['pagamento_despesa_dt'],
                    'descricao'       => 'Pagamento: ' . ($desp['despesa'] ?: 'Despesa Operacional') . ($desp['comentario'] ? ' (' . $desp['comentario'] . ')' : ''),
                    'valor'           => (float) $desp['valor'],
                    'conta_origem'    => $desp['nome_conta'] ?: 'Conta Bancária Padrão',
                    'status_contabil' => $importado ? 'importado' : 'pendente',
                    'entry_id'        => $entry ? $entry['id'] : null,
                    'entry_number'    => $entry ? $entry['number'] : null,
                    'entry_numbers'   => array_map(static fn ($e) => (int) $e['number'], $entries),
                    'debito_id'       => $contasPadrao['despesa']['id'] ?? null,
                    'debito_rotulo'   => $contasPadrao['despesa']['rotulo'] ?? '5.3.02.20 - Softwares e Assinaturas',
                    'credito_id'      => $contasPadrao['banco']['id'] ?? null,
                    'credito_rotulo'  => $contasPadrao['banco']['rotulo'] ?? '1.1.1.02.01 - Bancos Conta Movimento',
                ], $categoriasDespesas[(int) $desp['despesa_id']] ?? [], $dicionario);
            }
        }

        // 5. Buscar Transferências Bancárias
        if ($tipo === null || $tipo === '' || $tipo === 'transferencia') {
            $transfQuery = $this->db->table('fin_transferencias t')
                ->select('t.id_transferencia, t.data_transferencia, t.valor, t.transferencia, t.comentarios, c1.conta as conta_origem, c2.conta as conta_destino')
                ->join('fin_contas c1', 'c1.id_conta = t.id_conta_origem', 'left')
                ->join('fin_contas c2', 'c2.id_conta = t.id_conta_destino', 'left')
                ->where('t.deleted_at', null)
                ->where('t.data_transferencia >=', $dataInicio)
                ->where('t.data_transferencia <=', $dataFim)
                ->orderBy('t.data_transferencia', 'DESC');

            $transferencias = $transfQuery->get()->getResultArray();
            foreach ($transferencias as $tr) {
                $id = (int) $tr['id_transferencia'];
                $chave = 'fin_transferencia:' . $id;
                $importado = isset($mapaImportados[$chave]);
                $entries = $importado ? $mapaImportados[$chave] : [];
                $entry = $entries[0] ?? null;

                $transacoes[] = [
                    'uid'             => 'transf_' . $id,
                    'tipo'            => 'transferencia',
                    'source_type'     => 'fin_transferencia',
                    'source_id'       => $id,
                    'data'            => $tr['data_transferencia'],
                    'descricao'       => 'Transferência: ' . ($tr['conta_origem'] ?: 'Origem') . ' ➔ ' . ($tr['conta_destino'] ?: 'Destino') . ($tr['comentarios'] ? ' (' . $tr['comentarios'] . ')' : ''),
                    'valor'           => (float) $tr['valor'],
                    'conta_origem'    => ($tr['conta_origem'] ?: 'Origem') . ' ➔ ' . ($tr['conta_destino'] ?: 'Destino'),
                    'status_contabil' => $importado ? 'importado' : 'pendente',
                    'entry_id'        => $entry ? $entry['id'] : null,
                    'entry_number'    => $entry ? $entry['number'] : null,
                    'entry_numbers'   => array_map(static fn ($e) => (int) $e['number'], $entries),
                    'debito_id'       => $contasPadrao['banco']['id'] ?? null,
                    'debito_rotulo'   => $contasPadrao['banco']['rotulo'] ?? '1.1.1.02.01 - Conta Bancária Destino',
                    'credito_id'      => $contasPadrao['banco']['id'] ?? null,
                    'credito_rotulo'  => $contasPadrao['banco']['rotulo'] ?? '1.1.1.02.01 - Conta Bancária Origem',
                ];
            }
        }

        // Ordenar por data decrescente
        usort($transacoes, static fn ($a, $b) => strcmp($b['data'], $a['data']));

        // Filtro por status se solicitado
        if ($status === 'pendente') {
            $transacoes = array_values(array_filter($transacoes, static fn ($t) => $t['status_contabil'] === 'pendente'));
        } elseif ($status === 'importado') {
            $transacoes = array_values(array_filter($transacoes, static fn ($t) => $t['status_contabil'] === 'importado'));
        }

        // Calcular KPIs estatísticos
        $totalPendentes = 0;
        $totalImportados = 0;
        $valorPendente = 0.0;
        $valorImportado = 0.0;

        foreach ($transacoes as $item) {
            if ($item['status_contabil'] === 'pendente') {
                $totalPendentes++;
                $valorPendente += $item['valor'];
            } else {
                $totalImportados++;
                $valorImportado += $item['valor'];
            }
        }

        return [
            'transacoes' => $transacoes,
            'kpis' => [
                'total_geral'      => count($transacoes),
                'total_pendentes'  => $totalPendentes,
                'total_importados' => $totalImportados,
                'valor_pendente'   => $valorPendente,
                'valor_importado'  => $valorImportado,
            ],
        ];
    }

    /**
     * Importa um conjunto de transações financeiras para a contabilidade em lote.
     *
     * @param array<int, array<string, mixed>> $itens
     * @param int $entityId
     * @param bool $postar Se verdadeiro, efetiva o lançamento imediatamente no diário
     * @return array{sucesso: int, ignorados: int, erros: array}
     */
    public function importarLote(array $itens, int $entityId = 1, bool $postar = true): array
    {
        $sucesso = 0;
        $ignorados = 0;
        $erros = [];

        $uc = $this->useCases();
        $contasPadrao = $this->obterContasPadrao($entityId);

        // Lança em ordem cronológica (data de recebimento/pagamento), para que a numeração do
        // diário acompanhe as datas; a tela envia as operações da mais nova para a mais velha.
        // Na mesma data, mantém a ordem de origem (receita, despesa, transferência) e o id.
        $ordemTipo = ['fin_pgto_receita' => 0, 'fin_pgto_despesa' => 1, 'fin_transferencia' => 2];
        uasort($itens, static fn ($a, $b) => [
            (string) ($a['data'] ?? ''),
            $ordemTipo[$a['source_type'] ?? ''] ?? 3,
            (int) ($a['source_id'] ?? 0),
        ] <=> [
            (string) ($b['data'] ?? ''),
            $ordemTipo[$b['source_type'] ?? ''] ?? 3,
            (int) ($b['source_id'] ?? 0),
        ]);

        foreach ($itens as $index => $item) {
            try {
                $sourceType = (string) ($item['source_type'] ?? '');
                $sourceId = (string) ($item['source_id'] ?? '');
                $valor = (float) ($item['valor'] ?? 0);
                $data = (string) ($item['data'] ?? date('Y-m-d'));
                $descricao = trim((string) ($item['descricao'] ?? 'Lançamento importado do financeiro'));

                if (! in_array($sourceType, ['fin_pgto_receita', 'fin_pgto_despesa', 'fin_transferencia'], true) || $sourceId === '' || $valor <= 0) {
                    $erros[] = "Linha {$index}: Dados da transação inválidos.";
                    continue;
                }

                // Valor em centavos (menor unidade monetária)
                $totalMinor = (int) round($valor * 100);

                $partes = $this->resolverPartes($item, $sourceType, $totalMinor, $contasPadrao);

                foreach ($partes as $i => $parte) {
                    if ($parte['debito_id'] === $parte['credito_id'] && $sourceType !== 'fin_transferencia') {
                        throw new InvalidArgumentException('Contas de débito e crédito não podem ser idênticas.');
                    }
                }

                $dividido = count($partes) > 1;

                foreach ($partes as $i => $parte) {
                    $numero = $i + 1;
                    $desc = $parte['descricao'] !== '' ? $parte['descricao'] : $descricao;
                    if ($dividido) {
                        $desc .= " (parte {$numero}/" . count($partes) . ')';
                    }

                    $lines = [
                        [
                            'account_id'  => $parte['debito_id'],
                            'side'        => 'D',
                            'amount'      => $parte['valor'],
                            'description' => 'Débito: ' . $desc,
                        ],
                        [
                            'account_id'  => $parte['credito_id'],
                            'side'        => 'C',
                            'amount'      => $parte['valor'],
                            'description' => 'Crédito: ' . $desc,
                        ],
                    ];

                    $entryData = new EntryData(
                        accrualDate: $data,
                        description: $desc,
                        lines: $lines,
                        cashDate: $data,
                        sourceType: $sourceType,
                        sourceId: $sourceId,
                        // Chave de idempotência única e segura; o sufixo identifica a parte
                        idempotencyKey: sprintf('saude:%s:%s:%d', $sourceType, $sourceId, $numero)
                    );

                    $uc->createEntry()->execute($entityId, $entryData, post: $postar);
                }

                $sucesso += count($partes);
            } catch (Throwable $e) {
                $erros[] = "Item {$sourceType} #{$sourceId}: " . $e->getMessage();
            }
        }

        return [
            'sucesso'   => $sucesso,
            'ignorados' => $ignorados,
            'erros'     => $erros,
        ];
    }

    /**
     * Resolve as partes (lançamentos) em que a operação financeira será contabilizada.
     *
     * Sem `divisoes` (ou com apenas uma), gera um único lançamento com o valor total.
     * Com duas ou mais, cada parte vira um lançamento próprio e a soma deve fechar o total.
     *
     * @param array<string, mixed> $item
     * @param array{banco: array, receita: array, despesa: array} $contasPadrao
     * @return list<array{valor: int, debito_id: int, credito_id: int, descricao: string}>
     */
    private function resolverPartes(array $item, string $sourceType, int $totalMinor, array $contasPadrao): array
    {
        [$debitoPadrao, $creditoPadrao] = match ($sourceType) {
            'fin_pgto_receita' => [$contasPadrao['banco']['id'], $contasPadrao['receita']['id']],
            'fin_pgto_despesa' => [$contasPadrao['despesa']['id'], $contasPadrao['banco']['id']],
            default            => [$contasPadrao['banco']['id'], $contasPadrao['banco']['id']],
        };

        $debitoItem  = ! empty($item['debito_id']) ? (int) $item['debito_id'] : null;
        $creditoItem = ! empty($item['credito_id']) ? (int) $item['credito_id'] : null;

        $divisoes = $item['divisoes'] ?? [];
        if (! is_array($divisoes) || count($divisoes) < 2) {
            return [[
                'valor'      => $totalMinor,
                'debito_id'  => (int) ($debitoItem ?? $debitoPadrao),
                'credito_id' => (int) ($creditoItem ?? $creditoPadrao),
                'descricao'  => '',
            ]];
        }

        $partes = [];
        $soma   = 0;
        foreach (array_values($divisoes) as $i => $div) {
            $valorParte = (int) round((float) ($div['valor'] ?? 0) * 100);
            if ($valorParte <= 0) {
                throw new InvalidArgumentException('Todas as partes da divisão precisam ter valor maior que zero.');
            }

            $soma    += $valorParte;
            $partes[] = [
                'valor'      => $valorParte,
                'debito_id'  => (int) (! empty($div['debito_id']) ? $div['debito_id'] : ($debitoItem ?? $debitoPadrao)),
                'credito_id' => (int) (! empty($div['credito_id']) ? $div['credito_id'] : ($creditoItem ?? $creditoPadrao)),
                'descricao'  => trim((string) ($div['descricao'] ?? '')),
            ];
        }

        if ($soma !== $totalMinor) {
            throw new InvalidArgumentException(sprintf(
                'A soma das partes (%s) difere do valor da operação (%s).',
                number_format($soma / 100, 2, ',', '.'),
                number_format($totalMinor / 100, 2, ',', '.')
            ));
        }

        return $partes;
    }

    /**
     * Ajusta a sugestão de contas de uma transação pelas categorias do lançamento financeiro.
     *
     * A perna "da categoria" é o crédito nas receitas e o débito nas despesas. Com uma categoria,
     * a conta vem do dicionário (se houver). Com duas ou mais, sugere `divisoes`: uma parte por
     * categoria, com o valor pago repartido na proporção do rateio do financeiro.
     *
     * @param array<string, mixed> $transacao
     * @param list<array{categoria_id: int|null, categoria: string, valor: float}> $categorias
     * @param array<string, array{id: int, rotulo: string}> $dicionario Chave "tipo:categoria_id"
     * @return array<string, mixed>
     */
    private function aplicarSugestaoCategorias(array $transacao, array $categorias, array $dicionario): array
    {
        $tipo  = $transacao['tipo'];
        $lado  = $tipo === 'receita' ? 'credito' : 'debito';
        $total = (int) round($transacao['valor'] * 100);

        $pesos     = array_map(static fn ($c) => (int) round($c['valor'] * 100), $categorias);
        $somaPesos = array_sum($pesos);

        $transacao['categoria_id']    = null;
        $transacao['categorias']      = [];
        $transacao['sugestao_origem'] = 'padrao';

        if ($categorias === [] || $somaPesos <= 0) {
            return $transacao;
        }

        // Repartição proporcional em centavos (pagamentos parciais seguem o rateio da receita/despesa);
        // a sobra do arredondamento vai para a última parte.
        $partes  = [];
        $alocado = 0;
        $ultimo  = count($categorias) - 1;
        foreach ($categorias as $i => $cat) {
            $valorParte = $i === $ultimo ? $total - $alocado : intdiv($total * $pesos[$i], $somaPesos);
            $alocado   += $valorParte;
            if ($valorParte <= 0) {
                continue;
            }

            $sugestao = $cat['categoria_id'] !== null ? ($dicionario[$tipo . ':' . $cat['categoria_id']] ?? null) : null;
            $partes[] = [
                'categoria_id'    => $cat['categoria_id'],
                'categoria'       => $cat['categoria'],
                'valor'           => $valorParte / 100.0,
                'conta_id'        => $sugestao['id'] ?? $transacao[$lado . '_id'],
                'conta_rotulo'    => $sugestao['rotulo'] ?? $transacao[$lado . '_rotulo'],
                'sugestao_origem' => $sugestao !== null ? 'dicionario' : 'padrao',
            ];
        }

        $transacao['categorias'] = array_map(static fn ($p) => [
            'categoria_id' => $p['categoria_id'],
            'categoria'    => $p['categoria'],
            'valor'        => $p['valor'],
        ], $partes);

        if (count($partes) === 1) {
            $transacao['categoria_id']    = $partes[0]['categoria_id'];
            $transacao[$lado . '_id']     = $partes[0]['conta_id'];
            $transacao[$lado . '_rotulo'] = $partes[0]['conta_rotulo'];
            $transacao['sugestao_origem'] = $partes[0]['sugestao_origem'];

            return $transacao;
        }

        $transacao['divisao_sugerida'] = true;
        $transacao['divisoes'] = array_map(static fn ($p) => [
            'valor'           => $p['valor'],
            'debito_id'       => $lado === 'debito' ? $p['conta_id'] : $transacao['debito_id'],
            'credito_id'      => $lado === 'credito' ? $p['conta_id'] : $transacao['credito_id'],
            'descricao'       => $transacao['descricao'] . ' — ' . $p['categoria'],
            'categoria_id'    => $p['categoria_id'],
            'categoria'       => $p['categoria'],
            'sugestao_origem' => $p['sugestao_origem'],
        ], $partes);

        return $transacao;
    }

    /**
     * Categorias (rateio) de cada receita ou despesa, indexadas pelo id do lançamento.
     *
     * @param list<int|string|null> $ids
     * @return array<int, list<array{categoria_id: int|null, categoria: string, valor: float}>>
     */
    private function categoriasPorLancamento(string $tabela, string $chave, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === [] || ! $this->db->tableExists($tabela)) {
            return [];
        }

        $rows = $this->db->table($tabela . ' rc')
            ->select("rc.{$chave} AS lancamento_id, rc.categoria_id, rc.valor, c.categoria")
            ->join('fin_categorias c', 'c.id_categoria = rc.categoria_id', 'left')
            ->whereIn("rc.{$chave}", $ids)
            ->orderBy('rc.id', 'ASC')
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[(int) $row['lancamento_id']][] = [
                'categoria_id' => $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null,
                'categoria'    => $row['categoria'] ?: 'Sem categoria',
                'valor'        => (float) $row['valor'],
            ];
        }

        return $mapa;
    }

    /**
     * Dicionário indexado por "tipo:categoria_id", só com contas que ainda aceitam lançamentos.
     *
     * @param array<int, string> $rotulos Rótulos das contas folha ativas, por id
     * @return array<string, array{id: int, rotulo: string}>
     */
    private function mapaDicionario(int $entityId, array $rotulos): array
    {
        $mapa = [];
        foreach ($this->linhasDicionario($entityId) as $row) {
            $accountId = (int) $row['account_id'];
            if (isset($rotulos[$accountId])) {
                $mapa[$row['tipo'] . ':' . $row['categoria_id']] = ['id' => $accountId, 'rotulo' => $rotulos[$accountId]];
            }
        }

        return $mapa;
    }

    /**
     * @return list<array{categoria_id: int|string, tipo: string, account_id: int|string}>
     */
    private function linhasDicionario(int $entityId): array
    {
        if (! $this->db->tableExists('contabil_categorias_contas')) {
            return [];
        }

        return $this->db->table('contabil_categorias_contas')
            ->select('categoria_id, tipo, account_id')
            ->where('entity_id', $entityId)
            ->get()
            ->getResultArray();
    }

    /**
     * Lista o dicionário categoria -> conta contábil da entidade.
     *
     * @return list<array{categoria_id: int, categoria: string, tipo: string, account_id: int, rotulo: string|null}>
     */
    public function obterDicionarioCategorias(int $entityId = 1): array
    {
        $rotulos = array_column($this->obterContasFolhas($entityId), 'rotulo', 'id');
        $linhas  = $this->linhasDicionario($entityId);

        $nomes = [];
        $ids   = array_values(array_unique(array_map(static fn ($r) => (int) $r['categoria_id'], $linhas)));
        if ($ids !== []) {
            $nomes = array_column(
                $this->db->table('fin_categorias')->select('id_categoria, categoria')->whereIn('id_categoria', $ids)->get()->getResultArray(),
                'categoria',
                'id_categoria'
            );
        }

        $dicionario = [];
        foreach ($linhas as $row) {
            $dicionario[] = [
                'categoria_id' => (int) $row['categoria_id'],
                'categoria'    => (string) ($nomes[(int) $row['categoria_id']] ?? 'Categoria removida'),
                'tipo'         => (string) $row['tipo'],
                'account_id'   => (int) $row['account_id'],
                'rotulo'       => $rotulos[(int) $row['account_id']] ?? null,
            ];
        }

        usort($dicionario, static fn ($a, $b) => [$a['tipo'], $a['categoria']] <=> [$b['tipo'], $b['categoria']]);

        return $dicionario;
    }

    /**
     * Grava (cria ou substitui) associações categoria -> conta. `account_id` vazio remove a associação.
     *
     * @param list<array{categoria_id: int, tipo: string, account_id?: int|null}> $itens
     * @return int Quantidade de associações gravadas ou removidas
     */
    public function salvarDicionarioCategorias(array $itens, int $entityId = 1): int
    {
        $contasValidas = array_column($this->obterContasFolhas($entityId), 'id', 'id');

        foreach ($itens as $item) {
            $categoriaId = (int) ($item['categoria_id'] ?? 0);
            $tipo        = (string) ($item['tipo'] ?? '');
            $accountId   = (int) ($item['account_id'] ?? 0);

            if ($categoriaId <= 0 || ! in_array($tipo, ['receita', 'despesa'], true)) {
                throw new InvalidArgumentException('Cada associação precisa de uma categoria e do tipo (receita ou despesa).');
            }
            if ($accountId > 0 && ! isset($contasValidas[$accountId])) {
                throw new InvalidArgumentException("A conta #{$accountId} não existe ou não aceita lançamentos.");
            }
        }

        $agora = date('Y-m-d H:i:s');
        $total = 0;

        $this->db->transStart();
        foreach ($itens as $item) {
            $chave = [
                'entity_id'    => $entityId,
                'categoria_id' => (int) $item['categoria_id'],
                'tipo'         => (string) $item['tipo'],
            ];
            $accountId = (int) ($item['account_id'] ?? 0);
            $tabela    = $this->db->table('contabil_categorias_contas');

            if ($accountId <= 0) {
                $tabela->where($chave)->delete();
            } elseif ($tabela->where($chave)->countAllResults() > 0) {
                $this->db->table('contabil_categorias_contas')->where($chave)->update(['account_id' => $accountId, 'updated_at' => $agora]);
            } else {
                $this->db->table('contabil_categorias_contas')->insert($chave + ['account_id' => $accountId, 'created_at' => $agora, 'updated_at' => $agora]);
            }
            $total++;
        }
        $this->db->transComplete();

        return $total;
    }

    /**
     * Retorna todas as contas contábeis folha (que aceitam lançamentos) para seleção.
     *
     * @param int $entityId
     * @return list<array{id: int, code: string, name: string, type: string, rotulo: string}>
     */
    public function obterContasFolhas(int $entityId = 1): array
    {
        $rows = $this->db->table('acc_accounts')
            ->select('id, code, name, type, accepts_entries, is_cash_account')
            ->where('entity_id', $entityId)
            ->where('accepts_entries', 1)
            ->where('active', 1)
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();

        $contas = [];
        foreach ($rows as $row) {
            $contas[] = [
                'id'              => (int) $row['id'],
                'code'            => $row['code'],
                'name'            => $row['name'],
                'type'            => $row['type'],
                'rotulo'          => "{$row['code']} - {$row['name']}",
                'is_cash_account' => (bool) $row['is_cash_account'],
            ];
        }

        return $contas;
    }

    /**
     * Recupera ou deduz as contas contábeis padrão sugeridas para o plano ativo.
     *
     * @param int $entityId
     * @param list<array<string, mixed>>|null $contas Contas folha já carregadas, para evitar nova consulta
     * @return array{banco: array, receita: array, despesa: array}
     */
    private function obterContasPadrao(int $entityId = 1, ?array $contas = null): array
    {
        $contas ??= $this->obterContasFolhas($entityId);

        $banco = null;
        $receita = null;
        $despesa = null;

        foreach ($contas as $c) {
            if ($banco === null && (in_array($c['code'], ['1.1.1.02.01', '1.1.02'], true) || str_contains(strtolower($c['name']), 'bank') || str_contains(strtolower($c['name']), 'banco'))) {
                $banco = $c;
            }
            if ($receita === null && (in_array($c['code'], ['4.1.01.01', '4.1.02', '4.1.01'], true) || $c['type'] === 'REVENUE')) {
                $receita = $c;
            }
            if ($despesa === null && (in_array($c['code'], ['5.3.02.20', '5.2.03', '5.2.01'], true) || $c['type'] === 'EXPENSE')) {
                $despesa = $c;
            }
        }

        // Fallbacks seguros se os códigos específicos não existirem
        if (! $banco) {
            foreach ($contas as $c) {
                if ($c['type'] === 'ASSET') {
                    $banco = $c;
                    break;
                }
            }
        }
        if (! $receita) {
            foreach ($contas as $c) {
                if ($c['type'] === 'REVENUE') {
                    $receita = $c;
                    break;
                }
            }
        }
        if (! $despesa) {
            foreach ($contas as $c) {
                if ($c['type'] === 'EXPENSE') {
                    $despesa = $c;
                    break;
                }
            }
        }

        return [
            'banco'   => $banco ?: ['id' => 4, 'rotulo' => '1.1.02 - Operating bank accounts'],
            'receita' => $receita ?: ['id' => 24, 'rotulo' => '4.1.02 - Fixed and retainer fees'],
            'despesa' => $despesa ?: ['id' => 37, 'rotulo' => '5.2.03 - Software and subscriptions'],
        ];
    }
}
