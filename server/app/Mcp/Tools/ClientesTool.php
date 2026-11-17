<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\AtorMcp;
use App\Mcp\Support\EntradaMcp;
use App\Mcp\Support\McpContext;
use App\Mcp\Support\ModulosMcp;
use App\Mcp\Support\PrivacidadeMcp;
use App\Services\AcessoEntidadeService;
use App\Services\ClienteService;
use App\Traits\ValidacoesTrait;
use Exception;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

final class ClientesTool
{
    use ValidacoesTrait;

    private const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    private ClienteService $clienteService;

    public function __construct()
    {
        $this->clienteService = new ClienteService();
    }

    /**
     * Busca clientes (PF/PJ) por termo livre, com paginação.
     */
    #[McpTool(name: 'buscar_clientes')]
    public function buscarClientes(?string $busca = null, int $porPagina = 25): array
    {
        ModulosMcp::exigir('clientes', 'clientes.access');

        return $this->clienteService->listarClientesComFiltros(
            $busca !== null ? ['search' => $busca] : [],
            $porPagina
        );
    }

    /**
     * Retorna dados cadastrais de um cliente e todos os seus vínculos
     * recentes: processos, casos, anotações, movimentações financeiras e
     * intimações. Use para responder perguntas sobre as conexões de um
     * cliente com o escritório.
     */
    #[McpTool(name: 'obter_cliente')]
    public function obterCliente(int $clienteId): array
    {
        ModulosMcp::exigir('clientes', 'clientes.access');
        PrivacidadeMcp::exigirAcesso('cliente', $clienteId);

        return ModulosMcp::ocultarBlocos([
            'cliente'        => $this->clienteService->encontrarCliente($clienteId),
            'estatisticas'   => $this->clienteService->obterEstatisticasCliente($clienteId),
            'processos'      => $this->clienteService->obterProcessosCliente($clienteId),
            'casos'          => $this->clienteService->obterCasosCliente($clienteId),
            'anotacoes'      => $this->clienteService->obterAnotacoesCliente($clienteId),
            'movimentacoes'  => $this->clienteService->obterMovimentacoesCliente($clienteId),
            'intimacoes'     => $this->clienteService->obterIntimacoesCliente($clienteId),
        ], ClienteService::BLOCOS_DETALHE);
    }

    /**
     * Cadastra um cliente (pessoa física ou jurídica), com as mesmas regras da
     * tela de cadastro: nome e CPF/CNPJ válido são obrigatórios e não pode
     * haver outro cliente com o mesmo documento. Se o documento já estiver
     * cadastrado, nada é gravado e a resposta traz o ID do cliente existente
     * (quando o usuário tem acesso a ele).
     *
     * O tipo (física/jurídica) é deduzido do documento. A pasta no Google
     * Drive é criada depois, pela sincronização.
     *
     * Em chamadas de um chat de IA externo (token pessoal), `usuarioId` é
     * ignorado e sempre substituído pelo usuário autenticado.
     *
     * @param string      $nome           Nome completo (PF) ou nome fantasia/nome usual (PJ).
     * @param string      $documento      CPF ou CNPJ, com ou sem máscara.
     * @param string|null $razaoSocial    Razão social (PJ).
     * @param string|null $email          E-mail.
     * @param string|null $telefone       Telefone.
     * @param string|null $dataNascimento Data de nascimento (dd/mm/aaaa).
     * @param string|null $nacionalidade  Nacionalidade (PF).
     * @param string|null $estadoCivil    Estado civil (PF).
     * @param string|null $profissao      Profissão (PF).
     * @param string|null $cep            CEP.
     * @param string|null $endereco       Logradouro.
     * @param string|null $numero         Número.
     * @param string|null $complemento    Complemento.
     * @param string|null $cidade         Cidade.
     * @param string|null $uf             UF (sigla com 2 letras).
     * @param int|null    $responsavelId  Advogado responsável (padrão: quem cadastra).
     * @param string|null $visibilidade   publico ou restrito.
     */
    #[McpTool(name: 'adicionar_cliente')]
    public function adicionarCliente(
        string $nome,
        string $documento,
        ?int $usuarioId = null,
        ?string $razaoSocial = null,
        ?string $email = null,
        ?string $telefone = null,
        ?string $dataNascimento = null,
        ?string $nacionalidade = null,
        ?string $estadoCivil = null,
        ?string $profissao = null,
        ?string $cep = null,
        ?string $endereco = null,
        ?string $numero = null,
        ?string $complemento = null,
        ?string $cidade = null,
        ?string $uf = null,
        ?int $responsavelId = null,
        ?string $visibilidade = null
    ): array {
        $usuarioId = McpContext::resolverUsuarioId($usuarioId);
        AtorMcp::assumir($usuarioId);
        ModulosMcp::exigir('clientes', 'clientes.create');

        $nome = trim($nome);
        if (mb_strlen($nome) < 3) {
            throw new ToolCallException('Nome: informe pelo menos 3 caracteres.');
        }

        $digitos = preg_replace('/\D/', '', $documento);
        if (!$this->validarCpfCnpj($digitos)) {
            throw new ToolCallException('CPF ou CNPJ inválido.');
        }
        $documentoFormatado = (string) $this->clienteService->formatarCpfCnpj($digitos);

        $existente = $this->clienteService->getClientePeloDocumento($documentoFormatado);
        if ($existente !== null) {
            $visivel = (new AcessoEntidadeService())->podeAcessar('cliente', $existente);

            return [
                'success'    => false,
                'status'     => 'ja_cadastrado',
                'id_cliente' => $visivel ? $existente : null,
                'mensagem'   => 'Já existe um cliente cadastrado com este documento.',
            ];
        }

        if (($email = EntradaMcp::texto($email)) !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ToolCallException("E-mail inválido \"{$email}\".");
        }

        $dados = array_filter([
            'tipo_cliente'    => strlen($digitos) === 14 ? 'J' : 'F',
            'nome'            => mb_substr($nome, 0, 255),
            'documento'       => $documentoFormatado,
            'razao_social'    => EntradaMcp::texto($razaoSocial, 150),
            'email'           => $email,
            'telefone'        => EntradaMcp::texto($telefone, 50),
            'data_nascimento' => EntradaMcp::data($dataNascimento, 'Data de nascimento'),
            'nacionalidade'   => EntradaMcp::texto($nacionalidade),
            'estado_civil'    => EntradaMcp::texto($estadoCivil),
            'profissao'       => EntradaMcp::texto($profissao),
            'cep'             => EntradaMcp::texto($cep, 50),
            'endereco'        => EntradaMcp::texto($endereco, 150),
            'numero'          => EntradaMcp::texto($numero, 20),
            'complemento'     => EntradaMcp::texto($complemento, 150),
            'cidade'          => EntradaMcp::texto($cidade, 150),
            'uf'              => ($u = EntradaMcp::texto($uf)) !== null ? EntradaMcp::opcao(mb_strtoupper($u), self::UFS, 'UF') : null,
            'responsavel_id'  => EntradaMcp::responsavel($responsavelId),
            'visibilidade'    => EntradaMcp::opcao($visibilidade, AcessoEntidadeService::VISIBILIDADES, 'Visibilidade'),
            'status_crm'      => 'client',
            'ativo'           => 1,
        ], static fn ($v) => $v !== null);

        try {
            $id = $this->clienteService->salvarCliente($dados);
        } catch (Exception $e) {
            log_message('error', '[MCP adicionar_cliente] ' . $e->getMessage());
            throw new ToolCallException('Não foi possível cadastrar o cliente: ' . $e->getMessage());
        }

        return [
            'success'    => true,
            'status'     => 'criado',
            'id_cliente' => $id,
            'mensagem'   => 'Cliente cadastrado.',
        ];
    }
}
