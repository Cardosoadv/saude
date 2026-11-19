<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Notificações push (Web Push / PWA).
 *
 * Preencha no .env (gere as chaves com `php spark push:vapid`):
 *
 *   push.vapidPublicKey  = "B..."            (base64url, 65 bytes)
 *   push.vapidPrivateKey = "..."             (base64url, 32 bytes) — NUNCA versionar
 *   push.contato         = "mailto:ti@exemplo.com.br"
 *
 * Sem as chaves, a API responde `habilitado: false` e o painel esconde a opção.
 *
 * @version 1.0.0
 */
class Push extends BaseConfig
{
    public string $vapidPublicKey = '';
    public string $vapidPrivateKey = '';

    /** Contato do responsável técnico, exigido pelo VAPID ("mailto:" ou "https://"). */
    public string $contato = 'mailto:noreply@exemplo.com.br';

    /**
     * Horário em que o envio é permitido (hora local, 0–23). Fora dele o comando não envia;
     * o que ficou pendente sai na primeira execução dentro da janela.
     */
    public int $horaInicio = 7;
    public int $horaFim = 22;

    /** Quantos dias à frente o prazo de uma tarefa gera aviso (0 = só no dia, 1 = hoje e amanhã). */
    public int $diasAntecedenciaPrazo = 1;

    /** Tempo que o serviço de push guarda a mensagem se o aparelho estiver desligado (segundos). */
    public int $ttl = 86400;

    /** Falhas seguidas (sem ser 404/410) até a inscrição ser descartada. */
    public int $maximoFalhas = 5;

    public function habilitado(): bool
    {
        return $this->vapidPublicKey !== '' && $this->vapidPrivateKey !== '';
    }
}
