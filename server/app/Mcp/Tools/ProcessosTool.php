<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Libraries\Importacao\LeitorArquivoTabular;
use App\Mcp\Support\AtorMcp;
use App\Mcp\Support\McpContext;
use App\Mcp\Support\ModulosMcp;
use App\Mcp\Support\PrivacidadeMcp;
use App\Services\AcessoEntidadeService;
use App\Services\ClienteService;
use App\Services\ProcessosImportacaoService;
use App\Services\ProcessosService;
use InvalidArgumentException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Expõe consulta e cadastro de processos e criação de anotações para a IA.
 * Delega a ProcessosService e ProcessosImportacaoService — nenhuma regra de
 * negócio aqui.
 */
final class ProcessosTool
{
    private ProcessosService $processosService;

    public function __construct()
    {
        $this->processosService = new ProcessosService();
    }

    /**
     * Busca processos por termo livre, cliente e/ou status de encerramento.
     */
    #[McpTool(name: 'buscar_processos')]
    public function buscarProcessos(
        ?string $busca = null,
        ?int $clienteId = null,
        ?int $encerrado = null,
        int $porPagina = 25
    ): array {
        ModulosMcp::exigir('processos', 'processos.access', 'processos.exclusive');

        return $this->processosService->listarProcessos(
            search: $busca,
            sortField: 'created_at',
            sortOrder: 'DESC',
            encerrado: $encerrado,
            perPage: $porPagina,
            clienteId: $clienteId
        );
    }

    /**
     * Retorna os detalhes completos de um processo: dados cadastrais,
     * movimentos, partes, objetos e anotações.
     */
    #[McpTool(name: 'obter_processo')]
    public function obterProcesso(int $processoId): array
    {
        ModulosMcp::exigir('processos', 'processos.access', 'processos.exclusive');
        PrivacidadeMcp::exigirAcesso('processo', $processoId);

        return ModulosMcp::ocultarBlocos(
            $this->processosService->buscarDetalhesProcesso($processoId),
            ProcessosService::BLOCOS_DETALHE,
        );
    }

    /**
     * Registra uma anotação em um processo, em nome de um usuário real do
     * escritório.
     *
     * Em chamadas de um chat de IA externo (token pessoal), `usuarioId` é
     * ignorado e sempre substituído pelo usuário autenticado. Só é
     * obrigatório em chamadas internas (GeminiService/Davinci).
     */
    #[McpTool(name: 'criar_anotacao_processo')]
    public function criarAnotacaoProcesso(
        int $processoId,
        string $anotacao,
        ?int $usuarioId = null,
        int $privacidade = 1
    ): array {
        $usuarioId = McpContext::resolverUsuarioId($usuarioId);
        AtorMcp::assumir($usuarioId);
        ModulosMcp::exigir('processos', 'processos.access', 'processos.exclusive');
        PrivacidadeMcp::exigirAcesso('processo', $processoId);

        $this->processosService->salvarAnotacao([
            'processo_id' => $processoId,
            'anotacao'    => $anotacao,
            'user_id'     => $usuarioId,
            'privacidade' => $privacidade,
        ]);

        return ['success' => true];
    }

    /**
     * Adiciona um processo à carteira pelo número CNJ, com a mesma pesquisa da
     * importação em lote: consulta o DJEN e o DataJud, preenche tribunal,
     * órgão, classe, data de distribuição, link e partes, sugere o cliente
     * pelas partes e gera o título "Cliente - Classe" quando não informado.
     *
     * Se o processo já estiver cadastrado, só completa os campos vazios (ou
     * aplica os valores informados), acrescenta partes e etiquetas novas e
     * nunca apaga nada; responsável e visibilidade não mudam. Não cria cliente.
     *
     * Use `simular: true` para ver a conferência e o que seria gravado antes
     * de confirmar com o usuário.
     *
     * Em chamadas de um chat de IA externo (token pessoal), `usuarioId` é
     * ignorado e sempre substituído pelo usuário autenticado.
     *
     * @param string        $numeroProcesso   Número CNJ, com ou sem máscara.
     * @param int|null      $clienteId        Cliente já cadastrado (veja buscar_clientes). Sem ele, usa a sugestão de confiança alta, se houver.
     * @param string|null   $clienteDocumento CPF/CNPJ de um cliente cadastrado, alternativa ao clienteId.
     * @param string|null   $tituloProcesso   Título; padrão "Cliente - Classe da ação".
     * @param string|null   $pasta            Pasta física ou identificador interno.
     * @param string|null   $risco            Provável, Possível ou Remoto (padrão Possível em processo novo).
     * @param string|null   $valorCausa       Valor da causa (ex.: "10.000,00"). Sem ele, usa o do DataJud.
     * @param string|null   $comentario       Comentário livre.
     * @param string|null   $ramoDireito      Nome do ramo do direito cadastrado.
     * @param int|null      $responsavelId    Usuário responsável (advogado ativo). Só em processo novo.
     * @param string|null   $visibilidade     publico ou restrito. Só em processo novo.
     * @param string[]|null $etiquetas        Nomes das etiquetas (criadas se não existirem).
     * @param string[]|null $poloAtivo        Partes do polo ativo além das encontradas no DJEN.
     * @param string[]|null $poloPassivo      Partes do polo passivo além das encontradas no DJEN.
     * @param bool          $simular          true: só consulta e mostra o que seria gravado.
     */
    #[McpTool(name: 'adicionar_processo')]
    public function adicionarProcesso(
        string $numeroProcesso,
        ?int $usuarioId = null,
        ?int $clienteId = null,
        ?string $clienteDocumento = null,
        ?string $tituloProcesso = null,
        ?string $pasta = null,
        ?string $risco = null,
        ?string $valorCausa = null,
        ?string $comentario = null,
        ?string $ramoDireito = null,
        ?int $responsavelId = null,
        ?string $visibilidade = null,
        ?array $etiquetas = null,
        ?array $poloAtivo = null,
        ?array $poloPassivo = null,
        bool $simular = false
    ): array {
        $usuarioId = McpContext::resolverUsuarioId($usuarioId);
        AtorMcp::assumir($usuarioId);
        ModulosMcp::exigir('processos', 'processos.create');
        if ($clienteId !== null) {
            PrivacidadeMcp::exigirAcesso('cliente', $clienteId);
        }

        // DJEN + DataJud (com novas tentativas) podem passar dos 30 s padrão.
        @set_time_limit(180);

        $importacao = new ProcessosImportacaoService();
        $separador  = LeitorArquivoTabular::SEPARADOR_MULTIPLO;
        $lista      = static fn (?array $itens): string => implode($separador, array_map(
            static fn ($v) => str_replace($separador, ' ', trim((string) $v)),
            $itens ?? []
        ));

        [$informado, $avisosEntrada] = $importacao->normalizarLinha([
            'titulo_processo'   => $tituloProcesso,
            'cliente_documento' => $clienteDocumento,
            'pasta'             => $pasta,
            'risco'             => $risco,
            'valorCausa'        => $valorCausa,
            'comentario'        => $comentario,
            'ramo_direito'      => $ramoDireito,
            'responsavel'       => $responsavelId !== null ? (string) $responsavelId : null,
            'visibilidade'      => $visibilidade,
            'etiquetas'         => $lista($etiquetas),
            'poloAtivo'         => $lista($poloAtivo),
            'poloPassivo'       => $lista($poloPassivo),
        ]);

        try {
            $conferencia = $importacao->conferir($numeroProcesso, $informado);
        } catch (InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage());
        }

        $resposta = [
            'numero_processo' => $conferencia['numeroprocessocommascara'],
            'situacao'        => $conferencia['situacao'],
            'fontes'          => $conferencia['fontes'],
            'avisos'          => array_values(array_merge($avisosEntrada, $conferencia['avisos'] ?? [])),
        ];

        // Processo cadastrado que o usuário não pode alterar: não revela os dados.
        if ($conferencia['situacao'] === 'cadastrado' && !$this->podeEditarProcesso((int) $conferencia['id_processo'])) {
            return $resposta + [
                'success'     => false,
                'status'      => 'sem_acesso',
                'id_processo' => null,
                'mensagem'    => 'Este processo já está cadastrado e você não tem permissão para alterá-lo.',
            ];
        }

        if ($conferencia['situacao'] === 'excluido') {
            return $resposta + [
                'success'     => false,
                'status'      => 'excluido',
                'id_processo' => null,
                'mensagem'    => 'Este processo foi excluído da carteira. Restaure-o antes de importar.',
            ];
        }

        $item = $importacao->itemParaConfirmar($conferencia, $clienteId);

        $resposta += [
            'id_processo' => $conferencia['id_processo'],
            'gravar'      => [
                'campos'    => $item['campos'],
                'partes'    => $item['partes'],
                'etiquetas' => $item['etiquetas'],
            ],
            'clientes_sugeridos' => empty($item['campos']['cliente_id']) ? $conferencia['clientes_sugeridos'] : [],
            'sigilo'             => $conferencia['sigilo'],
            'informacoes'        => $conferencia['informacoes'],
        ];

        if ($simular) {
            return $resposta + [
                'success'  => true,
                'status'   => 'simulado',
                'mensagem' => 'Nada foi gravado. Chame de novo sem "simular" para confirmar.',
            ];
        }

        $acesso    = new AcessoEntidadeService();
        $resultado = $importacao->confirmar(
            [$item],
            fn (int $id): bool => $this->podeEditarProcesso($id),
            static fn (int $userId): bool => $acesso->ehAdvogadoAtivo($userId)
        )['resultados'][0];

        $resposta['id_processo'] = $resultado['id_processo'] ?? $resposta['id_processo'];

        return $resposta + [
            'success'  => $resultado['status'] !== 'erro',
            'status'   => $resultado['status'],
            'mensagem' => $resultado['mensagem'] ?? match ($resultado['status']) {
                'criado'     => 'Processo cadastrado.',
                'atualizado' => 'O processo já existia e foi completado.',
                default      => null,
            },
        ];
    }

    /**
     * Mesmas regras do controller da importação: processos.edit e acesso ao
     * processo (privacidade granular e escopo de processos.exclusive).
     */
    private function podeEditarProcesso(int $processoId): bool
    {
        $usuario = auth()->user();
        if ($usuario === null || !$usuario->can('processos.edit')) {
            return false;
        }

        if (!$usuario->can('processos.access') && $usuario->can('processos.exclusive')) {
            $processo = db_connect()->table('processos')->select('cliente_id')
                ->where('id_processo', $processoId)->get()->getRowArray();
            $clienteUsuario = (new ClienteService())->getUsuarioCliente() ?? 0;
            if ($processo === null || (int) $processo['cliente_id'] !== (int) $clienteUsuario) {
                return false;
            }
        }

        return (new AcessoEntidadeService())->podeAcessar('processo', $processoId);
    }
}
