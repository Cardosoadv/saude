<?php

namespace App\Services\Financeiro;

use App\Services\BaseService;
use App\Repositories\Financeiro\ExtratoRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\CategoriasRepository;
use App\Repositories\UsuariosRepository;

/**
 * Serviço responsável pela geração de extratos financeiros.
 *
 * Esta classe processa dados de despesas, receitas e transferências para gerar
 * extratos detalhados por conta, incluindo cálculos de saldo acumulado e rateio.
 *
 * @version 1.2.0
 * @since 2026-05-04
 */
class ExtratoService extends BaseService
{
    /**
     * Repositório de extratos.
     *
     * @var ExtratoRepository
     */
    protected $extratoRepository;

    /**
     * Repositório de contas.
     *
     * @var ContasRepository
     */
    protected ContasRepository $contasRepository;

    /**
     * Repositório de usuários.
     *
     * @var UsuariosRepository
     */
    protected UsuariosRepository $usuariosRepository;

    /**
     * Repositório de categorias.
     *
     * @var CategoriasRepository
     */
    protected CategoriasRepository $categoriasRepository;

    /**
     * Construtor do ExtratoService.
     *
     * Inicializa os repositórios necessários.
     */
    public function __construct(
        ?ExtratoRepository $extratoRepository = null,
        ?ContasRepository $contasRepository = null,
        ?UsuariosRepository $usuariosRepository = null,
        ?CategoriasRepository $categoriasRepository = null
    ) {
        $this->extratoRepository    = $extratoRepository ?? new ExtratoRepository();
        $this->contasRepository     = $contasRepository ?? new ContasRepository();
        $this->usuariosRepository   = $usuariosRepository ?? new UsuariosRepository();
        $this->categoriasRepository = $categoriasRepository ?? new CategoriasRepository();
    }

    /**
     * Obtém o extrato completo de uma conta (sem filtro de data).
     *
     * Recupera todas as transações (despesas, receitas e transferências) associadas
     * a uma conta, ordena cronologicamente e calcula o saldo acumulado e o rateio.
     *
     * @param int $conta_id O ID da conta financeira.
     * @return array Retorna um array contendo todas as transações ordenadas, com os saldos calculados.
     */
    public function getExtratoPorConta($conta_id)
    {
        // Libera o lock da sessão para evitar travamento de outras abas durante o processamento
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $despesas               = $this->extratoRepository->getDespesasPorConta($conta_id);
        $receitas               = $this->extratoRepository->getReceitasPorConta($conta_id);
        $transferenciasDe       = $this->extratoRepository->getTransferenciasDePorConta($conta_id);
        $transferenciasPara     = $this->extratoRepository->getTransferenciasParaPorConta($conta_id);

        $extrato = array_merge($despesas, $receitas, $transferenciasDe, $transferenciasPara);

        // Ordenar extrato por data (ordem crescente)
        usort($extrato, function ($a, $b) {
            return strtotime($a['data']) - strtotime($b['data']);
        });

        // Calcular saldo acumulado e rateio acumulado por ID
        $saldoAcumulado = 0;
        $rateioAcumuladoPorId = [];

        foreach ($extrato as &$registro) {
            // Calcular saldo acumulado
            $saldoAcumulado += $registro['valor'];
            $registro['saldo'] = $saldoAcumulado;

            // Verificar se o rateio existe e é um array (já processado pelo repositório)
            if (!isset($registro['rateio']) || !is_array($registro['rateio'])) {
                continue;
            }

            // Calcular rateio acumulado por ID
            foreach ($registro['rateio'] as &$rateio) {
                $id = $rateio['id'];

                // Inicializar o acumulado para este ID se não existir
                if (!isset($rateioAcumuladoPorId[$id])) {
                    $rateioAcumuladoPorId[$id] = [
                        'rateio_acumulado' => 0,
                        'nome' => $rateio['nome'], // Adiciona o nome ao acumulado também
                    ];
                }

                $rateioAcumulado = $rateioAcumuladoPorId[$id]['rateio_acumulado'];
                $rateioAcumulado += $rateio['valor'];
                $rateioAcumuladoPorId[$id]['rateio_acumulado'] = $rateioAcumulado;

                // Adiciona o valor acumulado ao item do rateio na transação
                $rateio['rateio_acumulado'] = $rateioAcumulado;
            }
        }

        return $extrato;
    }

    /**
     * Obtém o extrato de uma conta por período, incluindo saldo anterior.
     *
     * Calcula o saldo anterior com base em transações passadas e, em seguida,
     * processa as transações dentro do intervalo especificado, atualizando saldos e rateios.
     *
     * @param int $conta_id O ID da conta financeira.
     * @param string $dataInicial A data de início do período (Y-m-d).
     * @param string $dataFinal A data de término do período (Y-m-d).
     * @return array Retorna um array com:
     *               - 'saldo_anterior': Saldo acumulado antes da data inicial (simplificado).
     *               - 'saldo_anterior_detalhado': Saldo anterior detalhado por rateio.
     *               - 'saldo_anterior_total': Valor total do saldo anterior.
     *               - 'transacoes': Lista de transações no período.
     *               - 'saldo_final': Saldo final após processar o período.
     *               - 'rateio_acumulado': Acumulado de rateio até o final do período.
     */
    public function getExtratoPorPeriodo($conta_id, $dataInicial, $dataFinal)
    {
        // Libera o lock da sessão para evitar travamento de outras abas durante o processamento
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Converter as datas para o formato correto
        $dataInicial = date('Y-m-d', strtotime($dataInicial));
        $dataFinal = date('Y-m-d', strtotime($dataFinal));

        // 1. Obter saldos anteriores de forma otimizada via SQL (O(1) queries em vez de O(N) rows)
        $saldosAnteriores = $this->extratoRepository->getSaldosAnterioresAgrupados($conta_id, $dataInicial);
        $saldoAnteriorRateio = $saldosAnteriores['rateio'];
        $saldoTotalAnterior = $saldosAnteriores['total'];

        // 3. Obter todas as transações do período
        $despesas = $this->extratoRepository->getDespesasPorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $receitas = $this->extratoRepository->getReceitasPorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $transferenciasDe = $this->extratoRepository->getTransferenciasDePorContaPeriodo($conta_id, $dataInicial, $dataFinal);
        $transferenciasParaAtual = $this->extratoRepository->getTransferenciasParaPorContaPeriodo($conta_id, $dataInicial, $dataFinal);

        // 4. Juntar todas as transações e ordenar por data
        $extrato = array_merge($despesas, $receitas, $transferenciasDe, $transferenciasParaAtual);

        usort($extrato, function ($a, $b) {
            $dateA = strtotime($a['data']);
            $dateB = strtotime($b['data']);

            if ($dateA == $dateB) {
                return 0;
            }

            return ($dateA < $dateB) ? -1 : 1;
        });

        // 5. Calcular saldo corrente e rateio acumulado
        $saldoCorrente = $saldoTotalAnterior;
        $rateioAcumuladoPorId = [];

        // Inicializar o rateio acumulado com o saldo anterior
        foreach ($saldoAnteriorRateio as $id => $dados) {
            $rateioAcumuladoPorId[$id] = [
                'rateio_acumulado' => $dados['valor'],
                'nome' => $dados['nome'],
            ];
        }

        foreach ($extrato as &$transacao) {
            // Calcular saldo acumulado geral
            $saldoCorrente += $transacao['valor'];
            $transacao['saldo'] = $saldoCorrente;

            // Verificar se o rateio existe e é um array
            if (!isset($transacao['rateio']) || !is_array($transacao['rateio'])) {
                continue;
            }

            // Calcular rateio acumulado por ID
            foreach ($transacao['rateio'] as &$rateio) {
                $id = $rateio['id'];

                // Inicializar o acumulado para este ID se não existir
                if (!isset($rateioAcumuladoPorId[$id])) {
                    $rateioAcumuladoPorId[$id] = [
                        'rateio_acumulado' => 0,
                        'nome' => $rateio['nome'],
                    ];
                }

                $rateioAcumulado = $rateioAcumuladoPorId[$id]['rateio_acumulado'];
                $rateioAcumulado += $rateio['valor'];
                $rateioAcumuladoPorId[$id]['rateio_acumulado'] = $rateioAcumulado;

                $rateio['rateio_acumulado'] = $rateioAcumulado; // Adiciona o valor acumulado ao item do rateio
            }
        }

        // Simplificar estrutura do saldo anterior para retorno (apenas valores se o front esperar assim, ou manter estrutura)
        // O código original retornava array(id => valor), vamos manter compatibilidade mas o novo array tem estrutura ['valor', 'nome']
        // Se o front esperar apenas valor, precisamos ajustar. O código original fazia: $saldoAnteriorRateio[$id] = valor;
        // Vou ajustar para retornar compatível: id => valor.

        $saldoAnteriorSimples = [];
        foreach ($saldoAnteriorRateio as $id => $dados) {
            $saldoAnteriorSimples[$id] = $dados['valor'];
        }

        $extrato = (new ComprovantesService())->anexarTotaisExtrato($extrato);

        return [
            'saldo_anterior' => $saldoAnteriorSimples,
            'saldo_anterior_detalhado' => $saldoAnteriorRateio, // Adicionando detalhado caso o front queira usar nomes no futuro
            'saldo_anterior_total' => $saldoTotalAnterior,
            'transacoes' => $extrato,
            'saldo_final' => $saldoCorrente,
            'rateio_acumulado' => $rateioAcumuladoPorId,
        ];
    }

    /**
     * Obtém apenas as transações de um período, sem calcular saldos acumulados ou saldo anterior.
     * Útil para relatórios que processam dados brutos (ex: Relatório por Categorias).
     *
     * @param int $conta_id
     * @param string $dataInicial
     * @param string $dataFinal
     * @return array
     */
    public function getTransacoesNoPeriodo(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $dataInicial = date('Y-m-d', strtotime($dataInicial));
        $dataFinal = date('Y-m-d', strtotime($dataFinal));

        return $this->extratoRepository->getTransacoesNoPeriodo($conta_id, $dataInicial, $dataFinal);
    }

    /**
     * Calcula o balanço financeiro até uma data de referência.
     * Otimizado usando fechamentos mensais.
     *
     * @param string|null $dataReferencia Data de referência (Y-m-d). Se nulo, usa a data atual.
     * @param SaldoMensalService|null $saldoMensalService
     * @return array
     */
    public function getBalancoFinanceiro(?string $dataReferencia = null, ?SaldoMensalService $saldoMensalService = null): array
    {
        // Libera o lock da sessão para evitar travamento de outras abas durante o processamento longo
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($dataReferencia === null) {
            $dataReferencia = date('Y-m-d');
        }

        if ($saldoMensalService === null) {
            $saldoMensalService = new SaldoMensalService();
        }

        $contas = $this->contasRepository->findAll(200);

        $saldosPorConta = [];
        $rateiosPorProfissional = [];

        foreach ($contas as $conta) {
            $conta_id = $conta['id_conta'];

            // Usar o método otimizado (busca o último fechamento)
            $extratoResultado = $this->getExtratoPorPeriodoOtimizado(
                $conta_id,
                '2000-01-01',  // Data antiga para pegar tudo
                $dataReferencia,
                $saldoMensalService
            );

            $saldosPorConta[$conta_id] = [
                'conta_nome' => $conta['conta'],
                'saldo' => $extratoResultado['saldo_final'],
                'ultimo_fechamento' => $extratoResultado['ultimo_fechamento_utilizado'],
            ];

            if (isset($extratoResultado['rateio_acumulado'])) {
                foreach ($extratoResultado['rateio_acumulado'] as $profissional_id => $dados) {
                    if ($profissional_id !== 'geral') {
                        if (!isset($rateiosPorProfissional[$profissional_id])) {
                            $rateiosPorProfissional[$profissional_id] = 0;
                        }
                        $rateiosPorProfissional[$profissional_id] += $dados['rateio_acumulado'];
                    }
                }
            }
        }

        // Buscar nomes dos profissionais
        $users = $this->usuariosRepository->getUsers();
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user['id']] = $user['username'];
        }

        foreach ($rateiosPorProfissional as $profissional_id => &$valor) {
            $nome = $userMap[$profissional_id] ?? 'Profissional #' . $profissional_id;
            $valor = [
                'nome' => $nome,
                'valor' => $valor,
            ];
        }

        $saldoTotal = array_sum(array_column($saldosPorConta, 'saldo'));
        $rateioTotal = array_sum(array_column($rateiosPorProfissional, 'valor'));

        return [
            'data_referencia' => $dataReferencia,
            'saldos_contas' => $saldosPorConta,
            'rateios_profissionais' => $rateiosPorProfissional,
            'saldo_total' => $saldoTotal,
            'rateio_total' => $rateioTotal,
            // O rateio é uma atribuição opcional por profissional e pode não cobrir 100%
            // do valor das transações (ex.: item de rateio com valor incorreto/
            // incompleto); esta diferença é o sinal de que algo precisa de revisão.
            'diferenca' => round($saldoTotal - $rateioTotal, 2),
        ];
    }

    /**
     * Obtém o extrato de uma conta por período, utilizando o último mês fechado como base.
     * Otimizado para performance e consistência.
     *
     * @param int $conta_id
     * @param string $dataInicial
     * @param string $dataFinal
     * @param SaldoMensalService|null $saldoMensalService
     * @return array
     */
    public function getExtratoPorPeriodoOtimizado(
        int $conta_id,
        string $dataInicial,
        string $dataFinal,
        ?SaldoMensalService $saldoMensalService = null
    ): array {
        // Libera o lock da sessão se for uma chamada direta (não vinda de getBalancoFinanceiro que já liberou)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Se não foi injetado, cria uma instância
        if ($saldoMensalService === null) {
            $saldoMensalService = new SaldoMensalService();
        }

        $dataInicial = date('Y-m-d', strtotime($dataInicial));
        $dataFinal = date('Y-m-d', strtotime($dataFinal));

        // 1. Buscar o último fechamento antes da data inicial
        $ultimoFechamento = $saldoMensalService->getRepository()->getUltimoSaldoFechado($conta_id, $dataInicial);

        $saldoAnteriorRateio = [];
        $saldoTotalAnterior = 0;
        $dataPartida = $dataInicial; // Data de início padrão

        if ($ultimoFechamento) {
            // Temos um fechamento! Usar o saldo acumulado dele
            $detalheFechamento = $saldoMensalService->getRepository()->getSaldoMensal(
                'CONTA',
                $conta_id,
                (int) $ultimoFechamento['ano'],
                (int) $ultimoFechamento['mes']
            );

            if ($detalheFechamento) {
                // Carregar rateios do fechamento
                foreach ($detalheFechamento['rateios'] as $rateio) {
                    $saldoAnteriorRateio[$rateio['user_id']] = [
                        'valor' => (float) $rateio['rateio_acumulado'],
                        'nome' => $this->getNomeUsuario($rateio['user_id']),
                    ];
                }

                $saldoTotalAnterior = (float) $detalheFechamento['saldo_acumulado'];

                // Data de partida = primeiro dia do mês seguinte ao fechamento
                $dataPartida = date('Y-m-d', strtotime(
                    "{$ultimoFechamento['ano']}-{$ultimoFechamento['mes']}-01 +1 month"
                ));

                // Se a data de partida for maior que a data inicial, ajustar
                if ($dataPartida > $dataInicial) {
                    $dataPartida = $dataInicial;
                }
            }
        } else {
            // Sem fechamento anterior, calcular do zero (apenas para compatibilidade)
            // Isso é um fallback para quando não há nenhum fechamento
            $extratoCompleto = $this->getExtratoPorPeriodo($conta_id, $dataInicial, $dataFinal);
            $extratoCompleto['ultimo_fechamento_utilizado'] = null;
            return $extratoCompleto;
        }

        // 2. Buscar APENAS as transações a partir da data de partida
        $transacoes = $this->extratoRepository->getTransacoesApartirDe($conta_id, $dataPartida);

        // 3. Filtrar apenas as transações dentro do período solicitado
        $transacoesNoPeriodo = array_filter($transacoes, function ($transacao) use ($dataInicial, $dataFinal) {
            $dataTransacao = $transacao['data'];
            return $dataTransacao >= $dataInicial && $dataTransacao <= $dataFinal;
        });

        // 4. Reordenar as transações
        $transacoesNoPeriodo = array_values($transacoesNoPeriodo);
        usort($transacoesNoPeriodo, function ($a, $b) {
            return strtotime($a['data']) - strtotime($b['data']);
        });

        // 5. Calcular saldo corrente e rateio acumulado a partir do último fechamento
        $saldoCorrente = $saldoTotalAnterior;
        $rateioAcumuladoPorId = [];

        // Inicializar com os valores do último fechamento
        foreach ($saldoAnteriorRateio as $id => $dados) {
            $rateioAcumuladoPorId[$id] = [
                'rateio_acumulado' => $dados['valor'],
                'nome' => $dados['nome'],
            ];
        }

        // Processar transações (incluindo as que estão antes da data inicial, se houver)
        // Isso garante que o rateio acumulado esteja correto para o período
        foreach ($transacoes as &$transacao) {
            // Atualizar saldo corrente
            $saldoCorrente += $transacao['valor'];

            // Se a transação está dentro do período, adicionar o saldo no registro
            if ($transacao['data'] >= $dataInicial && $transacao['data'] <= $dataFinal) {
                $transacao['saldo'] = $saldoCorrente;
            }

            // Atualizar rateios
            if (isset($transacao['rateio']) && is_array($transacao['rateio'])) {
                foreach ($transacao['rateio'] as &$rateio) {
                    $id = $rateio['id'];

                    if (!isset($rateioAcumuladoPorId[$id])) {
                        $rateioAcumuladoPorId[$id] = [
                            'rateio_acumulado' => 0,
                            'nome' => $rateio['nome'],
                        ];
                    }

                    $rateioAcumuladoPorId[$id]['rateio_acumulado'] += $rateio['valor'];

                    // Se a transação está dentro do período, adicionar o acumulado
                    if ($transacao['data'] >= $dataInicial && $transacao['data'] <= $dataFinal) {
                        $rateio['rateio_acumulado'] = $rateioAcumuladoPorId[$id]['rateio_acumulado'];
                    }
                }
            }
        }

        // 6. Preparar o saldo anterior simplificado (apenas para o período solicitado)
        $saldoAnteriorSimples = [];
        foreach ($saldoAnteriorRateio as $id => $dados) {
            $saldoAnteriorSimples[$id] = $dados['valor'];
        }

        return [
            'saldo_anterior' => $saldoAnteriorSimples,
            'saldo_anterior_detalhado' => $saldoAnteriorRateio,
            'saldo_anterior_total' => $saldoTotalAnterior,
            'ultimo_fechamento_utilizado' => $ultimoFechamento ? [
                'ano' => $ultimoFechamento['ano'],
                'mes' => $ultimoFechamento['mes'],
                'data_referencia' => "{$ultimoFechamento['ano']}-{$ultimoFechamento['mes']}-01",
            ] : null,
            'transacoes' => $transacoesNoPeriodo,
            'saldo_final' => $saldoCorrente,
            'rateio_acumulado' => $rateioAcumuladoPorId,
        ];
    }

    /**
     * Retorna o relatório de categorias do período agrupado por pai/filho.
     *
     * @param int $conta_id
     * @param string $dataInicial
     * @param string $dataFinal
     * @return array
     */
    public function getRelatorioCategorias(int $conta_id, string $dataInicial, string $dataFinal): array
    {
        $extrato = $this->getExtratoPorPeriodo($conta_id, $dataInicial, $dataFinal);
        $transacoes = $extrato['transacoes'] ?? [];

        // Agrupar transações
        $agrupados = [];
        $totaisPai = [];
        $totalReceitas = 0;
        $totalDespesas = 0;

        foreach ($transacoes as $t) {
            // Uma transação pode estar classificada em mais de uma categoria (rateio de
            // categorias); cada item do rateio já traz o valor proporcional a ela.
            $categorias = $t['categorias'] ?? [];

            if (empty($categorias)) {
                continue;
            }

            $isReceita = (isset($t['tipo']) && strpos($t['tipo'], 'receita') !== false) || $t['valor'] > 0;

            foreach ($categorias as $item) {
                $idCategoria = $item['id_categoria'] ?? null;
                $paiId = $item['categoria_pai_id'] ?? null;

                if (!$idCategoria) {
                    continue;
                }

                $valor = abs((float) $item['valor']);

                if ($isReceita) {
                    $totalReceitas += $valor;
                } else {
                    $totalDespesas += $valor;
                }

                if ($paiId) {
                    // Tem pai, agrupa no pai
                    if (!isset($agrupados[$paiId])) {
                        $agrupados[$paiId] = [];
                    }
                    if (!isset($agrupados[$paiId][$idCategoria])) {
                        $agrupados[$paiId][$idCategoria] = [
                            'id' => $idCategoria,
                            'name' => $item['categoria'],
                            'description' => '',
                            'value' => 0,
                        ];
                    }
                    $agrupados[$paiId][$idCategoria]['value'] += $valor;

                    if (!isset($totaisPai[$paiId])) {
                        $totaisPai[$paiId] = 0;
                    }
                    $totaisPai[$paiId] += $valor;
                } else {
                    // É pai ou sem pai
                    if (!isset($totaisPai[$idCategoria])) {
                        $totaisPai[$idCategoria] = 0;
                    }
                    $totaisPai[$idCategoria] += $valor;
                }
            }
        }

        // Buscar nomes dos pais via repositório
        $todasCategorias = $this->categoriasRepository->findAll();
        $catMap = [];
        foreach ($todasCategorias as $c) {
            $catMap[$c['id_categoria']] = $c;
        }

        $resultado = [];

        foreach ($totaisPai as $paiId => $total) {
            $catInfo = $catMap[$paiId] ?? null;
            $nomePai = $catInfo ? $catInfo['categoria'] : 'Categoria ' . $paiId;
            $descricaoPai = $catInfo ? ($catInfo['comentarios'] ?? '') : '';

            // Determinar tipo do pai baseando no elemento_id
            $tipoPai = 'despesa';
            if ($catInfo && isset($catInfo['elemento_id'])) {
                $tipoPai = (int) $catInfo['elemento_id'] === 1 ? 'receita' : 'despesa';
            }

            $percent = 0;
            if ($tipoPai === 'receita' && $totalReceitas > 0) {
                $percent = round(($total / $totalReceitas) * 100, 2);
            } elseif ($tipoPai === 'despesa' && $totalDespesas > 0) {
                $percent = round(($total / $totalDespesas) * 100, 2);
            }

            $children = [];
            if (isset($agrupados[$paiId])) {
                $children = array_values($agrupados[$paiId]);
            }

            $resultado[] = [
                'id' => $paiId,
                'name' => $nomePai,
                'description' => $descricaoPai,
                'type' => $tipoPai,
                'expanded' => false,
                'percentage' => $percent,
                'value' => $total,
                'children' => $children,
            ];
        }

        // Ordenar receitas primeiro, depois despesas, e depois maior valor
        usort($resultado, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'receita' ? -1 : 1;
            }
            return $b['value'] <=> $a['value'];
        });

        return $resultado;
    }

    /**
     * Helper para obter nome do usuário (evitar dependência circular)
     */
    private function getNomeUsuario(int $userId): string
    {
        // Tenta buscar do mapa interno do repositório primeiro (mais rápido)
        $nome = $this->extratoRepository->getNomeUsuario($userId);
        if ($nome !== "Usuário #$userId") {
            return $nome;
        }

        static $userCache = [];

        if (!isset($userCache[$userId])) {

            // Verifica se o repositório tem o método findById (padrão BaseRepository)
            $user = method_exists($this->usuariosRepository, 'findById') ? $this->usuariosRepository->findById($userId) : $this->usuariosRepository->find($userId);
            $userCache[$userId] = $user['username'] ?? 'Usuário #' . $userId;
        }

        return $userCache[$userId];
    }
}
