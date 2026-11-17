<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Libraries\Importacao\NormalizadorFontesProcesso as Fontes;
use App\Mcp\Support\AtorMcp;
use App\Mcp\Support\EntradaMcp;
use App\Mcp\Support\McpContext;
use App\Mcp\Support\ModulosMcp;
use App\Mcp\Support\PrivacidadeMcp;
use App\Repositories\ProcessosImportacaoRepository;
use App\Services\AcessoEntidadeService;
use App\Services\ContratosService;
use Exception;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Cadastro de contratos de honorários pela IA. Delega a ContratosService.
 */
final class ContratosTool
{
    /** Opções aceitas pelo ContratosModel. */
    private const TIPOS     = ['Honorários', 'Consultoria', 'Assessoria', 'Êxito', 'Misto', 'Pro Bono'];
    private const FORMAS    = ['Mensal', 'Trimestral', 'Semestral', 'Anual', 'Parcela Única', 'Por Hora'];
    private const STATUS    = ['Ativo', 'Pendente', 'Suspenso', 'Encerrado', 'Cancelado', 'Em negociação'];
    private const PRAZO_TIPOS = ['meses', 'anos'];
    private const INDICES   = ['IPCA', 'IGP-M', 'INPC', 'Percentual Fixo'];

    private ContratosService $contratosService;

    public function __construct()
    {
        $this->contratosService = new ContratosService();
    }

    /**
     * Cadastra um contrato para um cliente já cadastrado (veja buscar_clientes
     * ou adicionar_cliente). O número do contrato é gerado automaticamente.
     * Opcionalmente vincula o contrato a um processo.
     *
     * Em chamadas de um chat de IA externo (token pessoal), `usuarioId` é
     * ignorado e sempre substituído pelo usuário autenticado.
     *
     * @param int         $clienteId           Cliente do contrato.
     * @param string      $tipoContrato        Honorários, Consultoria, Assessoria, Êxito, Misto ou Pro Bono.
     * @param string      $objeto              Objeto do contrato (até 500 caracteres).
     * @param string      $formaPagamento      Mensal, Trimestral, Semestral, Anual, Parcela Única ou Por Hora.
     * @param string|null $dataInicio          Início (dd/mm/aaaa). Padrão: hoje.
     * @param string|null $dataFim             Término (dd/mm/aaaa).
     * @param string|null $valor               Valor dos honorários (ex.: "1.500,00").
     * @param string|null $honorariosExito     Honorários de êxito (valor, ex.: "10.000,00").
     * @param int|null    $numeroParcelas      Número de parcelas.
     * @param int|null    $diaVencimento       Dia do vencimento (1 a 31).
     * @param int|null    $prazoVigencia       Prazo de vigência (número, na unidade de prazoTipo).
     * @param string|null $prazoTipo           meses ou anos (padrão meses).
     * @param bool        $renovacaoAutomatica Renova automaticamente ao fim da vigência.
     * @param int|null    $prazoAviso          Dias de aviso antes do vencimento.
     * @param string|null $indiceReajuste      Índice de reajuste IPCA, IGP-M, INPC ou Percentual Fixo.
     * @param string|null $ramoDireito         Nome do ramo do direito cadastrado.
     * @param string|null $status              Ativo, Pendente, Suspenso, Encerrado, Cancelado ou Em negociação (padrão Ativo).
     * @param string|null $comentarios         Comentários.
     * @param string|null $clausulasEspeciais  Cláusulas especiais.
     * @param string|null $pasta               Pasta física ou identificador interno.
     * @param int|null    $responsavelId       Advogado responsável (padrão: quem cadastra).
     * @param string|null $visibilidade        publico ou restrito.
     * @param int|null    $processoId          Processo a vincular ao contrato.
     */
    #[McpTool(name: 'adicionar_contrato')]
    public function adicionarContrato(
        int $clienteId,
        string $tipoContrato,
        string $objeto,
        string $formaPagamento,
        ?int $usuarioId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        ?string $valor = null,
        ?string $honorariosExito = null,
        ?int $numeroParcelas = null,
        ?int $diaVencimento = null,
        ?int $prazoVigencia = null,
        ?string $prazoTipo = null,
        bool $renovacaoAutomatica = false,
        ?int $prazoAviso = null,
        ?string $indiceReajuste = null,
        ?string $ramoDireito = null,
        ?string $status = null,
        ?string $comentarios = null,
        ?string $clausulasEspeciais = null,
        ?string $pasta = null,
        ?int $responsavelId = null,
        ?string $visibilidade = null,
        ?int $processoId = null
    ): array {
        $usuarioId = McpContext::resolverUsuarioId($usuarioId);
        AtorMcp::assumir($usuarioId);
        ModulosMcp::exigir('contratos', 'contratos.create');
        PrivacidadeMcp::exigirAcesso('cliente', $clienteId);
        if ($processoId !== null) {
            ModulosMcp::exigir('processos', 'processos.access', 'processos.exclusive');
            PrivacidadeMcp::exigirAcesso('processo', $processoId);
        }

        $objeto = trim($objeto);
        if ($objeto === '' || mb_strlen($objeto) > 500) {
            throw new ToolCallException('Objeto: informe de 1 a 500 caracteres.');
        }
        if ($diaVencimento !== null && ($diaVencimento < 1 || $diaVencimento > 31)) {
            throw new ToolCallException('Dia de vencimento: use de 1 a 31.');
        }
        foreach (['Número de parcelas' => $numeroParcelas, 'Prazo de vigência' => $prazoVigencia, 'Prazo de aviso' => $prazoAviso] as $rotulo => $n) {
            if ($n !== null && $n < 0) {
                throw new ToolCallException("{$rotulo}: não pode ser negativo.");
            }
        }

        $inicio = EntradaMcp::data($dataInicio, 'Data de início') ?? date('Y-m-d');
        $fim    = EntradaMcp::data($dataFim, 'Data de término');
        if ($fim !== null && $fim < $inicio) {
            throw new ToolCallException('A data de término é anterior à data de início.');
        }

        $dados = array_filter([
            'cliente_id'           => $clienteId,
            'tipo_contrato'        => EntradaMcp::opcao($tipoContrato, self::TIPOS, 'Tipo de contrato'),
            'objeto'               => $objeto,
            'forma_pagamento'      => EntradaMcp::opcao($formaPagamento, self::FORMAS, 'Forma de pagamento'),
            'data_inicio'          => $inicio,
            'data_fim'             => $fim,
            'valor'                => $this->valor($valor, 'Valor'),
            'honorarios_exito'     => $this->valor($honorariosExito, 'Honorários de êxito'),
            'numero_parcelas'      => $numeroParcelas,
            'dia_vencimento'       => $diaVencimento,
            'prazo_vigencia'       => $prazoVigencia,
            'prazo_tipo'           => EntradaMcp::opcao($prazoTipo, self::PRAZO_TIPOS, 'Tipo de prazo'),
            'renovacao_automatica' => $renovacaoAutomatica ? 'Sim' : 'Não',
            'prazo_aviso'          => $prazoAviso,
            'indice_reajuste'      => EntradaMcp::opcao($indiceReajuste, self::INDICES, 'Índice de reajuste'),
            'area_atuacao'         => $this->ramo($ramoDireito),
            'status'               => EntradaMcp::opcao($status, self::STATUS, 'Status') ?? 'Ativo',
            'comentarios'          => EntradaMcp::texto($comentarios, 5000),
            'clausulas_especiais'  => EntradaMcp::texto($clausulasEspeciais, 5000),
            'pasta'                => EntradaMcp::texto($pasta),
            'responsavel_id'       => EntradaMcp::responsavel($responsavelId),
            'visibilidade'         => EntradaMcp::opcao($visibilidade, AcessoEntidadeService::VISIBILIDADES, 'Visibilidade'),
        ], static fn ($v) => $v !== null);

        try {
            $id = $this->contratosService->criar($dados);
        } catch (Exception $e) {
            log_message('error', '[MCP adicionar_contrato] ' . $e->getMessage());
            throw new ToolCallException('Não foi possível cadastrar o contrato: ' . $e->getMessage());
        }

        $avisos = [];
        if ($processoId !== null) {
            try {
                if (!$this->contratosService->vincularProcesso($id, $processoId)) {
                    $avisos[] = 'O contrato foi criado, mas não foi possível vinculá-lo ao processo.';
                }
            } catch (Exception $e) {
                log_message('error', "[MCP adicionar_contrato] vincular processo {$processoId} ao contrato {$id}: " . $e->getMessage());
                $avisos[] = 'O contrato foi criado, mas não foi possível vinculá-lo ao processo.';
            }
        }

        $contrato = $this->contratosService->buscarPorId($id);

        return [
            'success'         => true,
            'status'          => 'criado',
            'id_contrato'     => $id,
            'numero_contrato' => $contrato['numero_contrato'] ?? null,
            'mensagem'        => 'Contrato cadastrado.',
            'avisos'          => $avisos,
        ];
    }

    /** "R$ 1.500,00", "1500,00" ou "1500.00" → 1500.0; vazio → null. */
    private function valor(?string $valor, string $rotulo): ?float
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $limpo = str_ireplace(['R$', ' ', "\u{00A0}"], '', $valor);
        if (str_contains($limpo, ',')) {
            $limpo = str_replace(['.', ','], ['', '.'], $limpo);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $limpo)) {
            $limpo = str_replace('.', '', $limpo);
        }

        if (!is_numeric($limpo) || (float) $limpo < 0) {
            throw new ToolCallException("{$rotulo}: valor inválido \"{$valor}\".");
        }

        return round((float) $limpo, 2);
    }

    /** ID do ramo do direito pelo nome (sem acento e caixa); vazio → null. */
    private function ramo(?string $nome): ?int
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return null;
        }

        $alvo = Fontes::comparavel($nome);
        foreach ((new ProcessosImportacaoRepository())->ramos() as $ramo) {
            if (Fontes::comparavel($ramo['nome']) === $alvo) {
                return $ramo['id'];
            }
        }

        throw new ToolCallException("Ramo do direito \"{$nome}\" não encontrado.");
    }
}
