<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Repositories\PushRepository;
use App\Services\PushNotificacaoService;
use CodeIgniter\API\ResponseTrait;
use Config\Push as PushConfig;

/**
 * Notificações push (PWA) do usuário logado: inscrição do aparelho, preferências e teste.
 * Opera sempre sobre o próprio usuário, por isso só exige login.
 *
 * @version 1.0.0
 */
class Push extends BaseController
{
    use ResponseTrait;

    private PushNotificacaoService $service;
    private PushRepository $repository;
    private PushConfig $config;

    public function __construct(?PushNotificacaoService $service = null, ?PushRepository $repository = null, ?PushConfig $config = null)
    {
        $this->config     = $config ?? config('Push');
        $this->repository = $repository ?? new PushRepository();
        $this->service    = $service ?? new PushNotificacaoService($this->repository, $this->config);
    }

    /**
     * GET api/v2/push/config[?endpoint=...]
     * Chave pública VAPID e, se o endpoint do aparelho for informado, as preferências dele.
     */
    public function config()
    {
        $endpoint  = trim((string) $this->request->getGet('endpoint'));
        $inscricao = $endpoint !== '' ? $this->repository->inscricaoDoUsuario((int) user_id(), $endpoint) : null;

        return $this->respond([
            'success' => true,
            'data'    => [
                'habilitado'   => $this->service->habilitado(),
                'chavePublica' => $this->service->chavePublica(),
                'inscricao'    => $inscricao ? $this->preferencias($inscricao) : null,
                'horario'      => ['inicio' => $this->config->horaInicio, 'fim' => $this->config->horaFim],
            ],
        ]);
    }

    /**
     * POST api/v2/push/inscricao
     * Corpo: { subscription: {endpoint, keys: {p256dh, auth}}, preferencias?: {intimacoes, prazos} }
     */
    public function inscrever()
    {
        if (!$this->service->habilitado()) {
            return $this->respond(['success' => false, 'message' => 'Notificações push não estão configuradas no servidor.'], 503);
        }

        $dados       = $this->dadosJson();
        $assinatura  = $dados['subscription'] ?? [];
        $endpoint    = trim((string) ($assinatura['endpoint'] ?? ''));
        $p256dh      = trim((string) ($assinatura['keys']['p256dh'] ?? ''));
        $auth        = trim((string) ($assinatura['keys']['auth'] ?? ''));

        if (!self::endpointValido($endpoint) || !self::base64UrlValido($p256dh, 80, 100) || !self::base64UrlValido($auth, 16, 30)) {
            return $this->respond(['success' => false, 'message' => 'Inscrição de notificação inválida.'], 400);
        }

        $preferencias = [];
        foreach (['intimacoes' => 'notificar_intimacoes', 'prazos' => 'notificar_prazos'] as $chave => $campo) {
            if (isset($dados['preferencias'][$chave])) {
                $preferencias[$campo] = filter_var($dados['preferencias'][$chave], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $inscricao = $this->repository->salvarInscricao(
            (int) user_id(),
            ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth],
            $preferencias,
            $this->request->getUserAgent()->getAgentString() ?: null
        );

        return $this->respond(['success' => true, 'data' => $this->preferencias($inscricao)]);
    }

    /** POST api/v2/push/inscricao/remover — Corpo: { endpoint } */
    public function remover()
    {
        $endpoint = trim((string) ($this->dadosJson()['endpoint'] ?? ''));
        if ($endpoint === '') {
            return $this->respond(['success' => false, 'message' => 'Endpoint não informado.'], 400);
        }

        $this->repository->removerInscricao((int) user_id(), $endpoint);

        return $this->respond(['success' => true]);
    }

    /** POST api/v2/push/teste — envia uma notificação de teste a todos os aparelhos do usuário. */
    public function teste()
    {
        if (!$this->service->habilitado()) {
            return $this->respond(['success' => false, 'message' => 'Notificações push não estão configuradas no servidor.'], 503);
        }

        $r = $this->service->enviarTeste((int) user_id());

        if ($r['enviadas'] === 0) {
            return $this->respond([
                'success' => false,
                'message' => 'Nenhum aparelho recebeu o teste. Ative as notificações neste aparelho e tente de novo.',
                'data'    => $r,
            ], 422);
        }

        return $this->respond(['success' => true, 'message' => "Teste enviado para {$r['enviadas']} aparelho(s).", 'data' => $r]);
    }

    /** @return array{intimacoes: bool, prazos: bool} */
    private function preferencias(array $inscricao): array
    {
        return [
            'intimacoes' => (bool) (int) $inscricao['notificar_intimacoes'],
            'prazos'     => (bool) (int) $inscricao['notificar_prazos'],
        ];
    }

    /** @return array<string, mixed> */
    private function dadosJson(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable) {
            $json = null;
        }

        return is_array($json) ? $json : (array) $this->request->getPost();
    }

    /** Só https e sem endereços internos (o servidor faz POST para este endereço). */
    public static function endpointValido(string $endpoint): bool
    {
        if (strlen($endpoint) > 1000 || !str_starts_with($endpoint, 'https://') || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // Endereço IP literal: recusa faixas privadas/reservadas.
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    private static function base64UrlValido(string $valor, int $min, int $max): bool
    {
        return strlen($valor) >= $min && strlen($valor) <= $max && preg_match('/^[A-Za-z0-9_-]+=*$/', $valor) === 1;
    }
}
