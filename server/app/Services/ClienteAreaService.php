<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Email\EmailMarca;
use App\Models\ClientesModel;
use App\Repositories\ClienteRepository;
use App\Repositories\ClienteTokenRepository;
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
 * Escopo de dados: apenas o cadastro do próprio cliente logado.
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteAreaService extends BaseService
{
    protected ClienteTokenRepository $tokenRepository;
    protected UsuariosRepository     $usuariosRepository;

    /**
     * Construtor com injeção de dependência.
     */
    public function __construct(
        ?ClienteTokenRepository $tokenRepository    = null,
        ?ClienteRepository      $clienteRepository   = null,
        ?UsuariosRepository     $usuariosRepository  = null,
    ) {
        $this->tokenRepository     = $tokenRepository    ?? new ClienteTokenRepository();
        $this->repository          = $clienteRepository   ?? new ClienteRepository();
        $this->usuariosRepository  = $usuariosRepository  ?? new UsuariosRepository();
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
