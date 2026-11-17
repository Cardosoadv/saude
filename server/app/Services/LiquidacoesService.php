<?php

namespace App\Services;

use App\Libraries\Liquidacao\CriterioCalculo;
use App\Libraries\Liquidacao\LiquidacaoDominio;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\ResumoPagamentos;
use App\Repositories\LiquidacoesRepository;
use App\Services\AcessoEntidadeService;

class LiquidacoesService extends BaseService
{
    protected LiquidacoesRepository $liquidacoes;
    protected ?ClienteService $clienteService;
    protected AcessoEntidadeService $acesso;
    protected ?CalculoLiquidacaoService $calculo;
    protected ?LiquidacoesArquivosService $arquivos = null;

    /** Status em que o recálculo automático (novo índice) não mexe: o valor já foi fixado. */
    public const STATUS_CONGELADOS = [
        LiquidacaoDominio::STATUS_HOMOLOGADA,
        LiquidacaoDominio::STATUS_EM_PAGAMENTO,
        LiquidacaoDominio::STATUS_QUITADA,
    ];

    public function __construct(
        ?LiquidacoesRepository $repository = null,
        ?ClienteService $clienteService = null,
        ?AcessoEntidadeService $acesso = null,
        ?CalculoLiquidacaoService $calculo = null
    ) {
        $this->calculo        = $calculo;
        $this->repository     = $repository ?? new LiquidacoesRepository();
        $this->liquidacoes    = $this->repository;
        $this->clienteService = $clienteService;
        $this->acesso         = $acesso ?? new AcessoEntidadeService();
    }

    // --- Acesso ---

    /** Usuário autenticado, ou null em comandos e filas. */
    protected function usuarioId(): ?int
    {
        try {
            return user_id() ? (int) user_id() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cliente ao qual o usuário está restrito: só quem tem `liquidacoes.exclusive`
     * sem `liquidacoes.access` vê apenas as liquidações dos processos do próprio
     * cliente (mesmo padrão de Processos). Null quando não há restrição.
     */
    public function escopoClienteId(): ?int
    {
        $usuario = auth()->user();
        if ($usuario === null) {
            return null; // contexto de sistema (comandos, filas)
        }

        if ($usuario->can('liquidacoes.access') || !$usuario->can('liquidacoes.exclusive')) {
            return null;
        }

        $this->clienteService ??= new ClienteService();

        return $this->clienteService->getUsuarioCliente() ?? 0;
    }

    /**
     * Garante que o usuário enxerga o processo; a resposta é a mesma para
     * processo inexistente e sem acesso, para não revelar registros restritos.
     */
    public function garantirAcessoProcesso(int $processoId): array
    {
        $processo = $this->liquidacoes->getProcesso($processoId);
        if (!$processo) {
            throw LiquidacaoException::naoEncontrada('Processo');
        }

        $escopo = $this->escopoClienteId();
        if ($escopo !== null && (int) ($processo['cliente_id'] ?? 0) !== $escopo) {
            throw LiquidacaoException::naoEncontrada('Processo');
        }

        if (!$this->acesso->podeAcessar('processo', $processoId)) {
            throw LiquidacaoException::naoEncontrada('Processo');
        }

        return $processo;
    }

    /** Liquidação (sem os filhos) já validada quanto ao acesso ao processo. */
    public function liquidacaoComAcesso(int $liquidacaoId): array
    {
        return $this->garantirAcessoLiquidacao($liquidacaoId);
    }

    /** Liquidação (sem os filhos) já validada quanto ao acesso ao processo. */
    protected function garantirAcessoLiquidacao(int $liquidacaoId): array
    {
        $liquidacao = $this->liquidacoes->findById($liquidacaoId);
        if (!$liquidacao) {
            throw LiquidacaoException::naoEncontrada();
        }

        try {
            $this->garantirAcessoProcesso((int) $liquidacao['processo_id']);
        } catch (LiquidacaoException) {
            throw LiquidacaoException::naoEncontrada();
        }

        return $liquidacao;
    }

    // --- Consulta ---

    public function getAll($params = [])
    {
        $params['_usuario'] = $this->usuarioId(); // chave de cache por usuário
        $escopo = $this->escopoClienteId();
        if ($escopo !== null) {
            $params['escopo_cliente_id'] = $escopo;
        }

        $resultado = $this->liquidacoes->getLiquidacoesComProcesso($params);
        $this->liquidacoes->getModel()->pager = $resultado['pager'];
        return $resultado['liquidacoes'];
    }

    public function getById(int $id): ?array
    {
        $liquidacao = $this->liquidacoes->getDetalhes($id);
        if (!$liquidacao) {
            return null;
        }

        try {
            $this->garantirAcessoProcesso((int) $liquidacao['processo_id']);
        } catch (LiquidacaoException) {
            return null;
        }

        $liquidacao['criterio_padrao']    = $this->decodificar($liquidacao['criterio_padrao'] ?? null);
        $liquidacao['resumo_pagamentos'] = ResumoPagamentos::calcular($liquidacao['pagamentos'] ?? [], (int) $liquidacao['valor_total'], date('Y-m-d'));
        foreach ($liquidacao['itens'] ?? [] as $i => $item) {
            $liquidacao['itens'][$i]['criterio']     = $this->decodificar($item['criterio'] ?? null);
            $liquidacao['itens'][$i]['tem_memoria']  = !empty($item['memoria_calculo']);
            unset($liquidacao['itens'][$i]['memoria_calculo']); // pesada: GET memoria/{item}
        }

        return $liquidacao;
    }

    private function decodificar(?string $json): array
    {
        $dados = $json === null || $json === '' ? null : json_decode($json, true);

        return is_array($dados) ? $dados : [];
    }

    private function codificar(array|string|null $criterio): ?string
    {
        $normalizado = CriterioCalculo::normalizar($criterio);

        return $normalizado === [] ? null : json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function arquivos(): LiquidacoesArquivosService
    {
        return $this->arquivos ??= new LiquidacoesArquivosService($this->liquidacoes);
    }

    protected function calculo(): CalculoLiquidacaoService
    {
        return $this->calculo ??= new CalculoLiquidacaoService();
    }

    /** Executa $acao numa transação, desfazendo tudo se lançar exceção. */
    protected function emTransacao(callable $acao)
    {
        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            $resultado = $acao();
            $db->transCommit();

            return $resultado;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /** Histórico de recálculos da liquidação, do mais recente ao mais antigo. */
    public function getHistorico(int $liquidacaoId): array
    {
        $this->garantirAcessoLiquidacao($liquidacaoId);

        return $this->liquidacoes->getHistoricoPorLiquidacao($liquidacaoId);
    }

    /** Liquidações de um processo (mesmo filtro e escopo da listagem). */
    public function listarPorProcesso(int $processoId): array
    {
        $this->garantirAcessoProcesso($processoId);

        return $this->getAll(['processo_id' => $processoId]);
    }

    // --- CRUD da liquidação ---

    /**
     * Cria a liquidação (e, se vierem em `itens`, os itens) e recalcula o total.
     *
     * @return int liquidacao_id
     */
    public function criar(array $data): int
    {
        $processoId = (int) ($data['processo_id'] ?? 0);
        $this->garantirAcessoProcesso($processoId);

        $itens = $data['itens'] ?? [];
        unset($data['itens'], $data['valor_total']);

        $data += [
            'status'          => LiquidacaoDominio::STATUS_RASCUNHO,
            'tipo_liquidacao' => LiquidacaoDominio::TIPO_POR_CALCULO,
            'data_liquidacao' => date('Y-m-d'),
        ];
        $data['valor_total']   = 0;
        $data['liquidador_id'] ??= $this->usuarioId();

        if (array_key_exists('criterio_padrao', $data)) {
            $data['criterio_padrao'] = $this->codificar($data['criterio_padrao']);
        }

        $id = $this->emTransacao(function () use ($data, $itens) {
            $id = (int) $this->liquidacoes->getModel()->insert($data);
            if ($id === 0) {
                throw LiquidacaoException::invalida('Dados da liquidação inválidos.', $this->liquidacoes->getErrors());
            }

            foreach ($itens as $item) {
                $item['liquidacao_id'] = $id;
                $this->criarItem($item);
            }

            $this->executarRecalculo($this->liquidacoes->findById($id), 'Criação da liquidação');

            return $id;
        });
        $this->liquidacoes->clearCache();

        return $id;
    }

    /** Atualiza os dados gerais; o total é sempre recalculado a partir dos itens. */
    public function atualizar(int $id, array $data): bool
    {
        $liquidacao = $this->garantirAcessoLiquidacao($id);

        // processo, total e data do último cálculo não são editáveis por aqui
        unset($data['liquidacao_id'], $data['processo_id'], $data['valor_total'], $data['calculado_em'], $data['itens']);

        $recalcular = false;
        if (array_key_exists('criterio_padrao', $data)) {
            $data['criterio_padrao'] = $this->codificar($data['criterio_padrao']);
            $recalcular = true;
        }
        if (array_key_exists('data_base', $data)) {
            $data['data_base'] = $data['data_base'] ?: null;
            $recalcular = true;
        }

        $this->emTransacao(function () use ($id, $data, $recalcular, $liquidacao) {
            if ($data !== [] && !$this->liquidacoes->getModel()->update($id, $data)) {
                throw LiquidacaoException::invalida('Dados da liquidação inválidos.', $this->liquidacoes->getErrors());
            }
            $this->executarRecalculo($this->liquidacoes->findById($id) ?? $liquidacao, $recalcular ? 'Alteração do critério ou da data-base' : 'Alteração da liquidação');
        });
        $this->liquidacoes->clearCache();

        return true;
    }

    public function excluir(int $id): bool
    {
        $this->garantirAcessoLiquidacao($id);

        return $this->liquidacoes->delete($id);
    }

    // Os métodos genéricos do BaseService passam pelas versões com checagem de acesso.
    public function create(array $data): int
    {
        return $this->criar($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->atualizar($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->excluir($id);
    }

    // --- Items Management & Recalculation ---

    public function addItem(array $data)
    {
        $liquidacaoId = (int) ($data['liquidacao_id'] ?? 0);
        $this->garantirAcessoLiquidacao($liquidacaoId);

        return $this->emTransacao(function () use ($data, $liquidacaoId) {
            $itemId = $this->criarItem($data, false);
            $this->executarRecalculo($this->liquidacoes->findById($liquidacaoId), 'Criação do item');

            return $itemId;
        });
    }

    /**
     * Insere o item. Item com critério nasce com valor 0 e é calculado no recálculo da
     * liquidação (que grava o histórico); item manual guarda o valor informado.
     */
    protected function criarItem(array $data, bool $registrarHistorico = true)
    {
        $data += ['base_calculo' => '', 'valor_base' => 0];
        $data['criterio']        = $this->codificar($data['criterio'] ?? null);
        unset($data['memoria_calculo']);
        $data['valor_calculado'] = $data['criterio'] !== null ? 0 : $this->calcularItem($data);

        $itemId = $this->liquidacoes->getItensModel()->insert($data);
        if (!$itemId) {
            throw LiquidacaoException::invalida('Dados do item inválidos.', $this->liquidacoes->getItensModel()->errors());
        }

        $this->liquidacoes->clearCache();
        if ($data['criterio'] === null && $registrarHistorico) {
            $this->logHistorico((int) $itemId, null, $data['valor_calculado'], 'Criação do item');
        }

        return $itemId;
    }

    public function updateItem($id, array $data)
    {
        $oldItem = $this->liquidacoes->getItem($id);
        if (!$oldItem) {
            throw LiquidacaoException::naoEncontrada('Item');
        }
        $liquidacao = $this->garantirAcessoLiquidacao((int) $oldItem['liquidacao_id']);

        unset($data['item_id'], $data['liquidacao_id'], $data['memoria_calculo']);

        if (array_key_exists('criterio', $data)) {
            $data['criterio'] = $this->codificar($data['criterio']);
        }
        $calculado = array_key_exists('criterio', $data) ? $data['criterio'] !== null : !empty($oldItem['criterio']);

        // Item manual: sem valor informado, o valor calculado acompanha a nova base.
        if (!$calculado && !isset($data['valor_calculado']) && (isset($data['valor_base']) || isset($data['taxa']))) {
            $data['valor_calculado'] = $this->calcularItem($data + ['valor_calculado' => null] + $oldItem);
        }

        $this->emTransacao(function () use ($id, $data, $oldItem, $calculado, $liquidacao) {
            if ($data !== [] && !$this->liquidacoes->getItensModel()->update($id, $data)) {
                throw LiquidacaoException::invalida('Dados do item inválidos.', $this->liquidacoes->getItensModel()->errors());
            }
            $this->liquidacoes->clearCache();

            if (!$calculado && isset($data['valor_calculado']) && (int) $data['valor_calculado'] !== (int) $oldItem['valor_calculado']) {
                $this->logHistorico((int) $id, (int) $oldItem['valor_calculado'], (int) $data['valor_calculado'], 'Atualização de valores');
            }
            $this->executarRecalculo($liquidacao, 'Atualização do item');
        });

        return true;
    }

    public function deleteItem($id)
    {
        $item = $this->liquidacoes->getItem($id);
        if (!$item) {
            return false;
        }
        $liquidacao = $this->garantirAcessoLiquidacao((int) $item['liquidacao_id']);

        return $this->emTransacao(function () use ($id, $liquidacao) {
            $deleted = $this->liquidacoes->deleteItem($id);
            if ($deleted) {
                $this->executarRecalculo($liquidacao, 'Exclusão de item');
            }

            return $deleted;
        });
    }

    public function getItem($id)
    {
        $item = $this->liquidacoes->getItem($id);
        if ($item) {
            $this->garantirAcessoLiquidacao((int) $item['liquidacao_id']);
        }
        return $item;
    }

    public function getPagamento($id)
    {
        $pagamento = $this->liquidacoes->getPagamento($id);
        if ($pagamento) {
            $this->garantirAcessoLiquidacao((int) $pagamento['liquidacao_id']);
        }
        return $pagamento;
    }

    public function getDocumento($id)
    {
        $documento = $this->liquidacoes->getDocumento($id);
        if ($documento) {
            $this->garantirAcessoLiquidacao((int) $documento['liquidacao_id']);
        }
        return $documento;
    }

    /** Valor do item: o valor manual, se informado, senão o valor base. */
    protected function calcularItem($data)
    {
        if (isset($data['valor_calculado'])) {
            return (int) $data['valor_calculado'];
        }

        return (int) ($data['valor_base'] ?? 0);
    }

    // --- Recálculo, simulação e memória ---

    /**
     * Recalcula todos os itens da liquidação com os critérios atuais.
     *
     * @param string|null $dataBase nova data-base (AAAA-MM-DD); persiste na liquidação. Sem ela vale a
     *                              data-base gravada ou, se não houver, hoje.
     *
     * @return array{valor_total: int, data_base: string, itens: array<int, array{anterior: int, novo: int}>}
     */
    public function recalcular(int $liquidacaoId, string $motivo = 'Recálculo manual', ?string $dataBase = null): array
    {
        $liquidacao = $this->garantirAcessoLiquidacao($liquidacaoId);

        return $this->emTransacao(function () use ($liquidacao, $motivo, $dataBase) {
            $resultado = $this->executarRecalculo($liquidacao, $motivo, $dataBase);
            $this->liquidacoes->clearCache();

            return $resultado;
        });
    }

    /** Atalho: recalcula com a data-base de hoje ("atualizar até hoje"). */
    public function atualizarAteHoje(int $liquidacaoId): array
    {
        return $this->recalcular($liquidacaoId, 'Atualização até ' . date('d/m/Y'), date('Y-m-d'));
    }

    /**
     * Recalcula as liquidações que usam algum dos índices (chamado quando entram valores novos).
     * Liquidações homologadas, em pagamento ou quitadas ficam como estão.
     *
     * @param list<string> $codigos
     *
     * @return list<int> liquidações recalculadas
     */
    public function recalcularPorIndices(array $codigos): array
    {
        $recalculadas = [];
        foreach ($this->liquidacoes->idsPorIndices($codigos, self::STATUS_CONGELADOS) as $id) {
            $liquidacao = $this->liquidacoes->findById($id);
            if (!$liquidacao) {
                continue;
            }
            try {
                $this->emTransacao(fn () => $this->executarRecalculo($liquidacao, 'Novo valor do índice ' . implode(', ', $codigos)));
                $recalculadas[] = $id;
            } catch (\Throwable $e) {
                log_message('error', "[Liquidacoes] Recálculo da liquidação {$id} após novo índice falhou: " . $e->getMessage());
            }
        }
        if ($recalculadas !== []) {
            $this->liquidacoes->clearCache();
        }

        return $recalculadas;
    }

    /**
     * Calcula sem gravar. Aceita `item_id` ou um `item` avulso (e `criterio_padrao`), `data_base` e,
     * para comparar critérios, `cenarios: [{ nome, criterio }]` (cada cenário substitui o critério do item).
     *
     * @return array{data_base: string, cenarios: list<array{nome: string, valor: int, diferenca: int, memoria: array}>}
     */
    public function simular(array $dados): array
    {
        $padrao = [];
        if (!empty($dados['item_id'])) {
            $item = $this->liquidacoes->getItem((int) $dados['item_id']);
            if (!$item) {
                throw LiquidacaoException::naoEncontrada('Item');
            }
            $liquidacao = $this->garantirAcessoLiquidacao((int) $item['liquidacao_id']);
            $padrao     = $this->decodificar($liquidacao['criterio_padrao'] ?? null);
            $dataBase   = $dados['data_base'] ?? $liquidacao['data_base'] ?? date('Y-m-d');
            $item['criterio'] = $this->decodificar($item['criterio'] ?? null);
        } elseif (!empty($dados['item']) && is_array($dados['item'])) {
            $item     = $dados['item'] + ['tipo_item' => LiquidacaoDominio::ITEM_PRINCIPAL, 'valor_base' => 0];
            $padrao   = (array) ($dados['criterio_padrao'] ?? []);
            $dataBase = $dados['data_base'] ?? date('Y-m-d');
        } else {
            throw LiquidacaoException::invalida('Informe `item_id` ou `item`.');
        }

        $cenarios = $dados['cenarios'] ?? [];
        if ($cenarios === []) {
            $cenarios = [['nome' => 'Critério do item', 'criterio' => $item['criterio'] ?? []]];
        }

        $saida   = [];
        $primeiro = null;
        foreach ($cenarios as $i => $cenario) {
            $calculado = $this->calculo()->calcularItem(
                ['criterio' => $cenario['criterio'] ?? []] + $item,
                $dataBase,
                $padrao
            );
            $primeiro ??= $calculado['valor'];
            $saida[] = [
                'nome'      => (string) ($cenario['nome'] ?? 'Cenário ' . ($i + 1)),
                'valor'     => $calculado['valor'],
                'diferenca' => $calculado['valor'] - $primeiro,
                'memoria'   => $calculado['memoria'],
            ];
        }

        return ['data_base' => $dataBase, 'cenarios' => $saida];
    }

    /** Memória de cálculo gravada no item (null se o item é manual ou ainda não foi calculado). */
    public function memoriaDoItem(int $itemId): ?array
    {
        $item = $this->getItem($itemId);
        if (!$item) {
            throw LiquidacaoException::naoEncontrada('Item');
        }

        $memoria = $this->decodificar($item['memoria_calculo'] ?? null);

        return $memoria === [] ? null : $memoria;
    }

    /**
     * Valor atualizado e saldo a receber da liquidação mais recente do processo (null se não houver).
     * Respeita o acesso ao processo.
     */
    public function resumoDoProcesso(int $processoId): ?array
    {
        $this->garantirAcessoProcesso($processoId);

        return $this->liquidacoes->resumoPorProcesso($processoId);
    }

    /**
     * Envia arquivos ao Google Drive (pasta do cliente, subpasta "Liquidações") e registra um documento
     * para cada um.
     *
     * @param array<int,array{path:string,nome:string}> $arquivos
     *
     * @return array{enviados: list<array>, erros: list<array{arquivo: string, mensagem: string}>}
     */
    public function enviarDocumentos(int $liquidacaoId, array $arquivos, string $tipo = 'outros', ?int $pagamentoId = null): array
    {
        $liquidacao = $this->garantirAcessoLiquidacao($liquidacaoId);

        if ($pagamentoId !== null) {
            $pagamento = $this->liquidacoes->getPagamento($pagamentoId);
            if (!$pagamento || (int) $pagamento['liquidacao_id'] !== $liquidacaoId) {
                throw LiquidacaoException::naoEncontrada('Pagamento');
            }
        }

        $envio = $this->arquivos()->enviar($liquidacao, $arquivos, $tipo, $pagamentoId, $this->usuarioId());
        if ($envio['enviados'] === [] && $envio['erros'] !== []) {
            throw new LiquidacaoException('Falha ao enviar ao Google Drive: ' . $envio['erros'][0]['mensagem'], 502);
        }
        $this->liquidacoes->clearCache();

        return $envio;
    }

    /**
     * Recalcula e grava itens, histórico e total. Sem checagem de acesso: o chamador já a fez
     * (ou é um processo de sistema, como o recálculo por novo índice).
     */
    protected function executarRecalculo(array $liquidacao, string $motivo, ?string $dataBase = null): array
    {
        $id       = (int) $liquidacao['liquidacao_id'];
        $efetiva  = $dataBase ?? ($liquidacao['data_base'] ?? null) ?: date('Y-m-d');
        $itens    = $this->liquidacoes->getItensByLiquidacao($id);

        $entrada = [];
        foreach ($itens as $item) {
            $item['criterio']          = $this->decodificar($item['criterio'] ?? null);
            $entrada[$item['item_id']] = $item;
        }

        $calculados = $this->calculo()->calcularLiquidacao($entrada, $efetiva, $this->decodificar($liquidacao['criterio_padrao'] ?? null));

        $total   = 0;
        $detalhe = [];
        foreach ($itens as $item) {
            $itemId   = (int) $item['item_id'];
            $novo     = $calculados[$itemId];
            $anterior = (int) $item['valor_calculado'];
            $total   += $novo['valor'];

            $memoria = ($novo['memoria']['tipo'] ?? 'manual') === 'manual'
                ? null
                : json_encode($novo['memoria'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            if ($novo['valor'] !== $anterior || $memoria !== ($item['memoria_calculo'] ?? null)) {
                $this->liquidacoes->getItensModel()->update($itemId, ['valor_calculado' => $novo['valor'], 'memoria_calculo' => $memoria]);
            }
            if ($novo['valor'] !== $anterior) {
                $this->logHistorico($itemId, $anterior, $novo['valor'], $motivo);
            }
            $detalhe[$itemId] = ['anterior' => $anterior, 'novo' => $novo['valor']];
        }

        $atualizacao = ['valor_total' => $total, 'calculado_em' => date('Y-m-d H:i:s')];
        if ($dataBase !== null) {
            $atualizacao['data_base'] = $dataBase;
        }
        $this->liquidacoes->getModel()->update($id, $atualizacao);
        $this->liquidacoes->clearCache();

        return ['valor_total' => $total, 'data_base' => $efetiva, 'itens' => $detalhe];
    }

    protected function logHistorico($itemId, $valorAnterior, $valorNovo, $motivo)
    {
        $this->liquidacoes->addHistorico([
            'item_id'          => $itemId,
            'data_calculo'     => date('Y-m-d H:i:s'),
            'valor_anterior'   => $valorAnterior ?? 0,
            'valor_novo'       => $valorNovo,
            'usuario_id'       => $this->usuarioId(),
            'motivo_alteracao' => $motivo,
        ]);
    }

    // --- Payments & Documents ---

    public function addPagamento(array $data)
    {
        $this->garantirAcessoLiquidacao((int) ($data['liquidacao_id'] ?? 0));
        $data += ['status_pagamento' => 'pendente'];

        $id = $this->liquidacoes->createPagamento($data);
        if (!$id) {
            throw LiquidacaoException::invalida('Dados do pagamento inválidos.', $this->liquidacoes->getPagamentosModel()->errors());
        }

        return $id;
    }

    public function updatePagamento($id, $data)
    {
        $pagamento = $this->liquidacoes->getPagamento($id);
        if (!$pagamento) {
            throw LiquidacaoException::naoEncontrada('Pagamento');
        }
        $this->garantirAcessoLiquidacao((int) $pagamento['liquidacao_id']);
        unset($data['pagamento_id'], $data['liquidacao_id']);

        if (!$this->liquidacoes->updatePagamento($id, $data)) {
            throw LiquidacaoException::invalida('Dados do pagamento inválidos.', $this->liquidacoes->getPagamentosModel()->errors());
        }

        return true;
    }

    public function deletePagamento($id)
    {
        if (!$this->getPagamento($id)) {
            return false;
        }

        return $this->liquidacoes->deletePagamento($id);
    }

    public function addDocumento(array $data)
    {
        $liquidacao = $this->garantirAcessoLiquidacao((int) ($data['liquidacao_id'] ?? 0));
        $data += [
            'processo_id' => $liquidacao['processo_id'],
            'data_upload' => date('Y-m-d H:i:s'),
            'usuario_id'  => $this->usuarioId(),
        ];

        $id = $this->liquidacoes->createDocumento($data);
        if (!$id) {
            throw LiquidacaoException::invalida('Dados do documento inválidos.', $this->liquidacoes->getDocumentosModel()->errors());
        }

        return $id;
    }

    public function deleteDocumento($id)
    {
        $documento = $this->getDocumento($id);
        if (!$documento) {
            return false;
        }

        // arquivo enviado ao Drive vai para a lixeira; se o Drive falhar, nada é removido
        if (!empty($documento['drive_id'])) {
            $this->arquivos()->excluirDoDrive($documento);
        }

        return $this->liquidacoes->deleteDocumento($id);
    }
}
