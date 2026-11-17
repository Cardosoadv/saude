<?php

namespace App\Services;

use CodeIgniter\Email\Email;
use Config\Services;

/**
 * Serviço responsável pelo envio de e-mails.
 *
 * Esta classe encapsula a funcionalidade de envio de e-mails utilizando a biblioteca
 * de e-mail nativa do CodeIgniter, facilitando a configuração e o disparo de mensagens.
 */
class EmailService extends BaseService
{
    /**
     * Instância da classe de e-mail do CodeIgniter.
     *
     * @var Email
     */
    protected $email;

    /**
     * Construtor do EmailService.
     *
     * Inicializa o serviço de e-mail do CodeIgniter.
     */
    /**
     * @param Email|null $email
     */
    public function __construct(?Email $email = null)
    {
        // Carrega a biblioteca de e-mail do CodeIgniter
        $this->email = $email ?? Services::email();
    }

    /**
     * Envia um e-mail simples.
     *
     * Configura e envia um e-mail para um destinatário específico. Em caso de falha,
     * registra o erro nos logs do sistema.
     *
     * @param string $to Endereço de e-mail do destinatário.
     * @param string $subject Assunto da mensagem.
     * @param string $message Corpo da mensagem (pode conter HTML).
     * @return bool Retorna true se o e-mail foi enviado com sucesso, ou false em caso de falha.
     */
    public function sendEmail(string $to, string $subject, string $message): bool
    {
        // Configura o destinatário, assunto e mensagem
        $this->email->setTo($to);
        $this->email->setSubject($subject);
        $this->email->setMessage($message);

        // Tenta enviar o e-mail
        if ($this->email->send()) {
            return true;
        } else {
            log_message('error', 'Erro ao enviar e-mail: ' . $this->email->printDebugger());
            return false;
        }
    }
}
