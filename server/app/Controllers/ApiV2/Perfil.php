<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\UsuariosService;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Perfil do usuário logado: dados pessoais/profissionais, foto e troca de senha.
 * Opera sempre sobre o próprio usuário (nunca recebe um id), por isso só exige login.
 *
 * @version 1.0.0
 */
class Perfil extends BaseController
{
    use ResponseTrait;

    private UsuariosService $service;

    public function __construct(?UsuariosService $service = null)
    {
        $this->service = $service ?? new UsuariosService();
    }

    public function detalhes()
    {
        $perfil = $this->service->getPerfil((int) user_id());

        return $perfil
            ? $this->respond(['success' => true, 'data' => $perfil])
            : $this->respond(['success' => false, 'message' => 'Usuário não encontrado.'], 404);
    }

    public function atualizar()
    {
        $id = (int) user_id();

        $rules = [
            'username' => "required|alpha_numeric_space|min_length[3]|max_length[30]|is_unique[users.username,id,{$id}]",
            'email'    => "required|valid_email|is_unique[auth_identities.secret,user_id,{$id}]",
            'lattes_url'   => 'permit_empty|valid_url_strict[http,https]|max_length[255]',
            'linkedin_url' => 'permit_empty|valid_url_strict[http,https]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'success' => false,
                'message' => 'Dados de validação inválidos.',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        try {
            $data = $this->request->getPost();
            $data['photo'] = $this->request->getFile('foto-perfil');
            $this->service->atualizarPerfil($id, $data);

            return $this->respond(['success' => true, 'message' => 'Perfil atualizado com sucesso.']);
        } catch (Exception $e) {
            log_message('error', 'Erro ao atualizar perfil: ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao atualizar perfil: ' . $e->getMessage()], 500);
        }
    }

    public function alterarSenha()
    {
        $rules = [
            'senha_atual'  => 'required',
            'nova_senha'   => 'required|min_length[8]|max_length[255]',
            'pass_confirm' => 'required|matches[nova_senha]',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'success' => false,
                'message' => 'Dados de validação inválidos.',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        $throttler = service('throttler');
        if ($throttler->check('senha-user-' . (int) user_id(), 5, MINUTE) === false) {
            return $this->respond(['success' => false, 'message' => 'Muitas tentativas. Aguarde um instante.'], 429);
        }

        $dados = $this->request->getJSON(true) ?? $this->request->getPost();

        try {
            $this->service->alterarSenha((int) user_id(), (string) $dados['senha_atual'], (string) $dados['nova_senha']);

            return $this->respond(['success' => true, 'message' => 'Senha alterada com sucesso.']);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
