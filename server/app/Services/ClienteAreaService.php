<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Email\EmailMarca;
use App\Libraries\TimelineSimplificador;
use App\Models\ClientesModel;
use App\Repositories\ClientPortalProcessesRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\ClienteTokenRepository;
use App\Repositories\MensagensRepository;
use App\Repositories\ProcessosRepository;
use App\Repositories\UsuariosRepository;
use CodeIgniter\Shield\Entities\User;
use Exception;

/**
 * Service responsável por toda a lógica de negócio da Área do Cliente.
 *
 * Fluxo de autenticação:
 *   1. Cliente solicita acesso → token gerado e enviado por email (magic link)
 *   2. Cliente clica no link → token validado → sessão Shield criada (grupo 'cliente')
 *   3. Requisições subsequentes autenticadas pelo ClienteAuthFilter via Shield
 *
 * Escopo de dados:
 *   - Processos: todos com `processos.cliente_id = id_cliente` do cliente logado
 *   - Mensagens: sempre enviadas para `processos.responsavel_id`
 *   - Dados financeiros: valorCausa, valorCondenacao, resultado, risco expostos
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteAreaService extends BaseService
{
    protected ClienteTokenRepository $tokenRepository;
    protected ProcessosRepository    $processosRepository;
    protected MensagensRepository    $mensagensRepository;
    protected UsuariosRepository     $usuariosRepository;
    protected ClientPortalProcessesRepository $clientPortalRepository;

    /**
     * Construtor com injeção de dependência.
     */
    public function __construct(
        ?ClienteTokenRepository $tokenRepository    = null,
        ?ProcessosRepository    $processosRepository = null,
        ?MensagensRepository    $mensagensRepository = null,
        ?ClienteRepository      $clienteRepository   = null,
        ?UsuariosRepository     $usuariosRepository  = null,
        ?ClientPortalProcessesRepository $clientPortalRepository = null,
    ) {
        $this->tokenRepository     = $tokenRepository    ?? new ClienteTokenRepository();
        $this->processosRepository = $processosRepository ?? new ProcessosRepository();
        $this->mensagensRepository = $mensagensRepository ?? new MensagensRepository();
        $this->repository          = $clienteRepository   ?? new ClienteRepository();
        $this->usuariosRepository  = $usuariosRepository  ?? new UsuariosRepository();
        $this->clientPortalRepository = $clientPortalRepository ?? new ClientPortalProcessesRepository();
    }

    // =========================================================================
    // AUTENTICAÇÃO
    // =========================================================================

    /**
     * Solicita acesso à Área do Cliente via magic link.
     *
     * Busca o cliente pelo email, gera token de 24h e envia o link por email.
     * Sempre retorna true para não revelar se o email existe (evita user enumeration).
     *
     * @param string $email Email do cliente.
     * @return bool Sempre true (resposta genérica de segurança).
     */
    public function solicitarAcesso(string $email): bool
    {
        $cliente = $this->repository->findByEmail($email);

        if (!$cliente) {
            log_message('info', "[ClienteAreaService::solicitarAcesso] Email não encontrado ou inativo: {$email}");
            return true; // Resposta genérica — não revela existência
        }

        try {
            $tokenBruto = $this->tokenRepository->gerarToken((int) $cliente['id_cliente']);
            $this->enviarEmailMagicLink($cliente, $tokenBruto);
        } catch (Exception $e) {
            log_message('error', "[ClienteAreaService::solicitarAcesso] Erro ao gerar/enviar token: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Autentica o cliente a partir do token bruto recebido via URL.
     *
     * Valida o token, busca (ou cria) o usuário Shield e inicia a sessão.
     *
     * @param string $tokenBruto Token bruto da URL.
     * @return array|false Dados do cliente ou false se inválido.
     */
    public function autenticarToken(string $tokenBruto): array|false
    {
        $registro = $this->tokenRepository->validarToken($tokenBruto);

        if (!$registro) {
            log_message('info', '[ClienteAreaService::autenticarToken] Token inválido ou expirado.');
            return false;
        }

        $cliente = $this->repository->findById((int) $registro['cliente_id']);

        if (!$cliente) {
            log_message('error', '[ClienteAreaService::autenticarToken] Cliente não encontrado para o token válido.');
            return false;
        }

        try {
            $this->criarSessaoShield($cliente);
        } catch (Exception $e) {
            log_message('error', '[ClienteAreaService::autenticarToken] Erro ao criar sessão Shield: ' . $e->getMessage());
            return false;
        }

        return $cliente;
    }

    /**
     * Cria uma sessão Shield para o cliente.
     *
     * Busca ou cria um usuário Shield vinculado ao email do cliente,
     * garante que pertence ao grupo 'cliente' e inicia a sessão.
     *
     * @param array $cliente Dados do cliente da tabela `clientes`.
     * @throws Exception Se não conseguir criar ou logar o usuário.
     */
    public function criarSessaoShield(array $cliente): void
    {
        $email      = $cliente['email'];
        $clienteId  = (int) $cliente['id_cliente'];
        $nome       = $cliente['nome'] ?? ($cliente['razao_social'] ?? 'Cliente');

        $users = auth()->getProvider();

        /** @var User|null $user */
        $user = $users->findByCredentials(['email' => $email]);

        if (!$user) {
            // Cria usuário Shield vinculado ao cliente
            $user = new User([
                'username' => 'cliente_' . $clienteId,
                'email'    => $email,
                'password' => bin2hex(random_bytes(16)), // Senha aleatória — acesso só por magic link
                'active'   => true,
            ]);

            $users->save($user);
            $user = $users->findByCredentials(['email' => $email]);

            if (!$user) {
                throw new Exception("Não foi possível criar o usuário Shield para o cliente ID {$clienteId}.");
            }
        }

        // Garante que o usuário está no grupo 'cliente'
        if (!$user->inGroup('cliente')) {
            $user->addGroup('cliente');
        }

        // Armazena o cliente_id na sessão para autorização de escopo
        session()->set('cliente_id', $clienteId);

        // Inicia a sessão Shield
        auth()->loginById($user->id);

        log_message('info', "[ClienteAreaService::criarSessaoShield] Sessão Shield criada para cliente ID: {$clienteId}");
    }

    // =========================================================================
    // PROCESSOS
    // =========================================================================

    /**
     * Lista todos os processos do cliente com movimentos recentes e dados financeiros.
     *
     * Escopo: todos os processos onde `processos.cliente_id = $clienteId`.
     *
     * @param int $clienteId ID do cliente logado.
     * @return array Lista de processos formatados para o cliente.
     */
    public function listarProcessos(int $clienteId): array
    {
        return $this->clientPortalRepository->listarProcessosClienteFull($clienteId);
    }

    /**
     * Retorna os detalhes completos de um processo, validando que pertence ao cliente.
     *
     * @param int $clienteId  ID do cliente logado.
     * @param int $processoId ID do processo solicitado.
     * @return array|null Detalhes do processo ou null se não encontrado/sem permissão.
     */
    public function detalharProcesso(int $clienteId, int $processoId): ?array
    {
        $detalhes = $this->clientPortalRepository->detalharProcessoParaCliente($clienteId, $processoId);

        if (!$detalhes) {
            return null;
        }

        $detalhes['mensagens'] = $this->listarMensagens($clienteId, $processoId);

        return $detalhes;
    }

    // =========================================================================
    // TIMELINE (Portal do Cliente)
    // =========================================================================

    /**
     * Monta a linha do tempo simplificada de um processo para o cliente.
     *
     * Traduz os movimentos oficiais e os marcos do cadastro (início,
     * arquivamento, encerramento) para uma versão sem "juridiquês", pensada
     * para quem não é advogado.
     *
     * @param int $clienteId  ID do cliente logado.
     * @param int $processoId ID do processo solicitado.
     * @return array|null Dados do processo + eventos da timeline, ou null se não encontrado/sem permissão.
     */
    public function timelineProcesso(int $clienteId, int $processoId): ?array
    {
        $dados = $this->clientPortalRepository->timelineCliente($clienteId, $processoId);

        if (!$dados) {
            return null;
        }

        return [
            'processo' => [
                'id_processo'     => (int) $dados['processo']['id_processo'],
                'numero_processo' => $dados['processo']['numero_processo'] ?? null,
                'titulo_processo' => $dados['processo']['titulo_processo'] ?? null,
            ],
            'eventos' => TimelineSimplificador::montar($dados['processo'], $dados['movimentos']),
        ];
    }

    // =========================================================================
    // MENSAGENS
    // =========================================================================

    /**
     * Envia uma mensagem do cliente para o advogado responsável do processo.
     *
     * O destinatário é sempre o `processos.responsavel_id`.
     *
     * @param int    $clienteId  ID do cliente logado.
     * @param int    $processoId ID do processo.
     * @param string $assunto    Assunto da mensagem.
     * @param string $mensagem   Conteúdo da mensagem.
     * @return bool True se enviada com sucesso.
     * @throws Exception Se o processo não for encontrado, não pertencer ao cliente ou não tiver responsável.
     */
    public function enviarMensagem(int $clienteId, int $processoId, string $assunto, string $mensagem): bool
    {
        $processo = $this->processosRepository->findById($processoId);

        if (!$processo || (int) $processo['cliente_id'] !== $clienteId) {
            throw new Exception('Processo não encontrado ou sem permissão de acesso.');
        }

        if (empty($processo['responsavel_id'])) {
            throw new Exception('Este processo não possui advogado responsável definido.');
        }

        $remetenteId = auth()->id();

        $resultado = $this->mensagensRepository->create([
            'remetente_id'    => $remetenteId,
            'destinatario_id' => (int) $processo['responsavel_id'],
            'processo_id'     => $processoId,
            'assunto'         => mb_substr(trim($assunto), 0, 255),
            'conteudo'        => trim($mensagem),
            'data_envio'      => date('Y-m-d H:i:s'),
        ]);

        if ($resultado) {
            log_message('info', "[ClienteAreaService::enviarMensagem] Cliente ID {$clienteId} enviou mensagem para responsável ID {$processo['responsavel_id']} no processo ID {$processoId}.");
        }

        return (bool) $resultado;
    }

    /**
     * Lista mensagens trocadas com o advogado no contexto de um processo.
     *
     * Filtra mensagens onde o remetente ou destinatário é o usuário Shield do cliente
     * e o assunto referencia o processo.
     *
     * @param int $clienteId  ID do cliente logado.
     * @param int $processoId ID do processo.
     * @return array Lista de mensagens da thread.
     */
    public function listarMensagens(int $clienteId, int $processoId): array
    {
        // Busca o user_id Shield do cliente logado
        $userId = auth()->id();

        if (!$userId) {
            return [];
        }

        // Busca o responsavel_id do processo
        $processo = $this->processosRepository->findById($processoId);

        if (!$processo || (int) $processo['cliente_id'] !== $clienteId) {
            return [];
        }

        // Mensagens recebidas pelo cliente neste processo são marcadas como lidas
        $this->mensagensRepository->markProcessMessagesAsRead($userId, $processoId);

        $mensagens = $this->mensagensRepository->getThreadProcessoCliente($userId, $processoId);

        foreach ($mensagens as &$msg) {
            $msg['is_cliente'] = ((int) $msg['remetente_id'] === $userId);
        }

        return $mensagens;
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================


    /**
     * Envia o email de magic link para o cliente.
     *
     * @param array  $cliente    Dados do cliente.
     * @param string $tokenBruto Token bruto gerado.
     */
    private function enviarEmailMagicLink(array $cliente, string $tokenBruto): void
    {
        $link      = site_url('cliente?token=' . $tokenBruto);
        $nomeCliente = $cliente['nome'] ?? ($cliente['razao_social'] ?? 'Cliente');

                // Em ambiente de desenvolvimento local, não envia o e-mail, apenas registra no log.
        if (ENVIRONMENT === 'development') {
            log_message('info', "\n\n===========================================\nMAGIC LINK GERADO PARA {$cliente['email']}:\n{$link}\n===========================================\n");
            return;
        }
        
        $email = \Config\Services::email();
        EmailMarca::montar($email, 'emails/cliente_magic_link', [
            'nomeCliente' => $nomeCliente,
            'linkAcesso'  => $link,
            'expiracaoH'  => 24,
        ], [
            'titulo'    => 'Área do Cliente',
            'etiqueta'  => 'Acesso seguro',
            'tom'       => 'primary',
            'preheader' => 'Seu link de acesso à Área do Cliente.',
        ]);
        $email->setTo($cliente['email'], $nomeCliente);
        $email->setSubject('Acesso à Área do Cliente');

        $enviado = $email->send(false);

        if (!$enviado) {
            log_message('error', "[ClienteAreaService] Falha ao enviar magic link para {$cliente['email']}: " . $email->printDebugger(['headers']));
            throw new Exception('Falha ao enviar o email de acesso.');
        }

        log_message('info', "[ClienteAreaService] Magic link enviado para {$cliente['email']}");
    }
}
