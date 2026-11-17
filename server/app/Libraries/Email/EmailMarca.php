<?php

declare(strict_types=1);

namespace App\Libraries\Email;

use App\Libraries\Site\SiteContexto;
use CodeIgniter\Email\Email;

/**
 * Monta e-mails HTML com a identidade visual do escritório.
 *
 * O logo vai como anexo inline (cid:), porque Gmail e Outlook bloqueiam imagens em data URI.
 */
class EmailMarca
{
    /**
     * Nome e arquivo de logo do escritório (configurações do site: o logo de e-mail, ou o logo
     * do escritório; o logo do .env como reserva).
     *
     * @return array{nome: string, logo: ?string}
     */
    public static function identidade(): array
    {
        $nome = '';
        $logo = null;

        try {
            $contexto = SiteContexto::atual();
            $nome     = (string) $contexto->config('identidade.nome', '');
            $logo     = $contexto->logoEmailArquivo();
        } catch (\Throwable $e) {
            log_message('warning', 'E-mail: configurações do site indisponíveis. ' . $e->getMessage());
        }

        if ($logo === null) {
            $reservas = [FCPATH . 'assets/img/logo.png'];
            if (env('logoEmpresa')) {
                array_unshift($reservas, FCPATH . env('logoEmpresa'), ROOTPATH . env('logoEmpresa'));
            }
            foreach ($reservas as $caminho) {
                if (is_file($caminho)) {
                    $logo = $caminho;
                    break;
                }
            }
        }

        return ['nome' => $nome !== '' ? $nome : 'Ordojuris', 'logo' => $logo];
    }

    /**
     * Renderiza o corpo do e-mail dentro da moldura padrão e o define como mensagem.
     *
     * Limpa o objeto de e-mail (inclusive anexos), então deve ser chamado antes de definir
     * destinatário e assunto.
     *
     * @param array<string, mixed>  $dados   Variáveis da view de conteúdo.
     * @param array<string, mixed>  $moldura titulo, etiqueta, tom, preheader, rodape.
     */
    public static function montar(Email $email, string $viewConteudo, array $dados, array $moldura): void
    {
        $email->clear(true);
        $email->setMailType('html');

        $marca   = self::identidade();
        $logoSrc = null;
        if ($marca['logo'] !== null) {
            $email->attach($marca['logo'], 'inline', 'logo.png');
            $cid = $email->setAttachmentCID($marca['logo']);
            if ($cid) {
                $logoSrc = 'cid:' . $cid;
            }
        }

        helper('email_layout');
        $conteudo = view($viewConteudo, $dados, ['saveData' => false]);

        $email->setMessage(view('emails/layout', $moldura + [
            'conteudo'       => $conteudo,
            'logoSrc'        => $logoSrc,
            'nomeEscritorio' => $marca['nome'],
        ], ['saveData' => false]));
    }
}
