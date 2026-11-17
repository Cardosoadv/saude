<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\PermissoesEfetivasService;
use App\Services\UsuariosService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller de Autenticação da API V2
 * @version 1.0.2
 */
class Auth extends BaseController
{
    use ResponseTrait;

    public function login()
    {
        $throttler = service('throttler');
        $ipAddress = $this->request->getIPAddress();

        if ($throttler->check('login-ip-' . md5($ipAddress), 10, MINUTE) === false) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Muitas tentativas de login. Tente novamente em ' . $throttler->getCooldown() . ' segundos.',
            ], 429);
        }

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $credentials = [
            'email'    => $this->request->getVar('email'),
            'password' => $this->request->getVar('password'),
        ];

        if ($throttler->check('login-email-' . md5(strtolower((string) $credentials['email'])), 5, MINUTE) === false) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Muitas tentativas de login. Tente novamente em ' . $throttler->getCooldown() . ' segundos.',
            ], 429);
        }

        $remember = (bool) $this->request->getVar('remember');

        // Autenticar com o Shield
        $result = auth()->attempt($credentials, $remember);

        if (! $result->isOK()) {
            return $this->failUnauthorized($result->reason());
        }

        $user = auth()->user();

        return $this->respond([
            'status' => 'success',
            'message' => 'Login realizado com sucesso',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * Esqueci minha senha: envia o link de redefinição. A resposta é sempre a mesma,
     * exista ou não o e-mail, para não revelar quem tem conta.
     */
    public function esqueciSenha()
    {
        $throttler = service('throttler');
        if ($throttler->check('esqueci-ip-' . md5($this->request->getIPAddress()), 5, MINUTE) === false) {
            return $this->respond(['status' => 'error', 'message' => 'Muitas tentativas. Tente novamente em instantes.'], 429);
        }

        if (!$this->validate(['email' => 'required|valid_email'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $email = strtolower(trim((string) $this->request->getVar('email')));
        if ($throttler->check('esqueci-email-' . md5($email), 3, HOUR) === false) {
            return $this->respond(['status' => 'error', 'message' => 'Muitas solicitações para este e-mail. Tente mais tarde.'], 429);
        }

        try {
            (new UsuariosService())->solicitarRedefinicaoSenha($email);
        } catch (\Throwable $e) {
            log_message('error', 'Erro ao solicitar redefinição de senha: ' . $e->getMessage());
        }

        return $this->respond([
            'status'  => 'success',
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha.',
        ]);
    }

    /** Define a nova senha a partir do token enviado por e-mail. */
    public function redefinirSenha()
    {
        $throttler = service('throttler');
        if ($throttler->check('redefinir-ip-' . md5($this->request->getIPAddress()), 10, MINUTE) === false) {
            return $this->respond(['status' => 'error', 'message' => 'Muitas tentativas. Tente novamente em instantes.'], 429);
        }

        $rules = [
            'email'        => 'required|valid_email',
            'token'        => 'required|exact_length[64]|hex',
            'password'     => 'required|min_length[8]|max_length[255]',
            'pass_confirm' => 'required|matches[password]',
        ];
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $ok = (new UsuariosService())->redefinirSenha(
                strtolower(trim((string) $this->request->getVar('email'))),
                (string) $this->request->getVar('token'),
                (string) $this->request->getVar('password')
            );
        } catch (\Throwable $e) {
            log_message('error', 'Erro ao redefinir senha: ' . $e->getMessage());
            $ok = false;
        }

        if (!$ok) {
            return $this->respond(['status' => 'error', 'message' => 'Link inválido ou expirado. Solicite uma nova redefinição.'], 400);
        }

        return $this->respond(['status' => 'success', 'message' => 'Senha redefinida. Entre com a nova senha.']);
    }

    public function userInfo()
    {
        if (! auth()->loggedIn()) {
            return $this->failUnauthorized('Usuário não autenticado.');
        }

        $user = auth()->user();

        return $this->respond([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'avatar' => base_url('api/v2/usuarios/exibirFoto/' . $user->id),
                'groups' => $user->getGroups(),
                'permissoes' => (new PermissoesEfetivasService())->paraUsuario($user),
                // null = módulos não configurados: o front libera tudo.
                'modulos_ativos' => service('modulos')->configurado()
                    ? service('modulos')->getActiveModules()
                    : null,
            ],
        ]);
    }
}
