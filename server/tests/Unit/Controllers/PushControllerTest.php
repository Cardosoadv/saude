<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Push as PushController;
use App\Database\Migrations\CreatePushNotificacoes;
use App\Libraries\WebPush\Base64Url;
use App\Libraries\WebPush\ChavesEc;
use App\Libraries\WebPush\Enviador;
use App\Libraries\WebPush\Vapid;
use App\Repositories\PushRepository;
use App\Services\PushNotificacaoService;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Push;
use Tests\Support\WebPush\NavegadorFalso;

/**
 * API de notificações push do usuário logado (api/v2/push).
 */
final class PushControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use AuthenticationTesting;
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private PushRepository $repo;
    private Push $config;
    private int $statusServicoPush = 201;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = $this->db->getPrefix();
        $this->db->query("CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT, key TEXT, value TEXT, type TEXT, context TEXT, created_at DATETIME, updated_at DATETIME
        )");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$prefix}auth_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            selector TEXT, hashed_validator TEXT, user_id INTEGER, expires DATETIME, created_at DATETIME, updated_at DATETIME
        )");

        require_once APPPATH . 'Database/Migrations/Users/2026-11-02-120000_CreatePushNotificacoes.php';
        $migration = new CreatePushNotificacoes(Database::forge($this->db));
        $migration->down();
        $migration->up();

        $par = ChavesEc::gerar();
        $this->config = new Push();
        $this->config->vapidPublicKey  = Base64Url::codificar($par['publica']);
        $this->config->vapidPrivateKey = Base64Url::codificar($par['privada']);
        $this->repo = new PushRepository($this->db);

        $this->actingAs(new User(['id' => 7, 'username' => 'advogada', 'email' => 'a@exemplo.com']));
    }

    private function executar(string $metodo, ?array $json = null, array $get = []): TestResponse
    {
        $enviador = new Enviador(
            Vapid::deBase64Url($this->config->vapidPublicKey, $this->config->vapidPrivateKey, $this->config->contato),
            fn (): int => $this->statusServicoPush
        );
        $servico = new PushNotificacaoService($this->repo, $this->config, $enviador, static fn (): bool => true);

        $this->withConfig(config('App'))->controller(PushController::class);
        $this->controller = new PushController($servico, $this->repo, $this->config);
        $this->controller->initController($this->request, $this->response, $this->logger);

        if ($json !== null) {
            $this->request->setHeader('Content-Type', 'application/json');
            $this->withBody(json_encode($json));
            $this->request->setBody(json_encode($json));
        }
        if ($get !== []) {
            $this->request->setGlobal('get', $get);
        }

        return $this->execute($metodo);
    }

    private static function json(TestResponse $r): array
    {
        return json_decode($r->response()->getBody(), true);
    }

    public function testConfigInformaAChavePublica(): void
    {
        $r = $this->executar('config');

        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame(
            ['habilitado' => true, 'chavePublica' => $this->config->vapidPublicKey, 'inscricao' => null, 'horario' => ['inicio' => 7, 'fim' => 22]],
            self::json($r)['data']
        );
    }

    public function testConfigSemChavesDesabilitado(): void
    {
        $this->config->vapidPrivateKey = '';
        $dados = self::json($this->executar('config'))['data'];

        $this->assertFalse($dados['habilitado']);
        $this->assertNull($dados['chavePublica']);
    }

    public function testInscreverSalvaParaOUsuarioLogadoEConfigDevolveAsPreferencias(): void
    {
        $navegador = new NavegadorFalso('https://fcm.googleapis.com/fcm/send/abc');
        $r = $this->executar('inscrever', [
            'subscription' => $navegador->inscricao(),
            'preferencias' => ['intimacoes' => false, 'prazos' => true],
        ]);

        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame(['intimacoes' => false, 'prazos' => true], self::json($r)['data']);

        [$linha] = $this->repo->inscricoesDoUsuario(7);
        $this->assertSame($navegador->endpoint, $linha['endpoint']);

        $dados = self::json($this->executar('config', null, ['endpoint' => $navegador->endpoint]))['data'];
        $this->assertSame(['intimacoes' => false, 'prazos' => true], $dados['inscricao']);
    }

    public function testRecusaInscricaoComEndpointInterno(): void
    {
        $navegador = new NavegadorFalso('https://192.168.1.10/push');
        $r = $this->executar('inscrever', ['subscription' => $navegador->inscricao()]);

        $this->assertSame(400, $r->response()->getStatusCode());
        $this->assertSame([], $this->repo->inscricoesDoUsuario(7));
    }

    public function testRecusaInscricaoSemChaves(): void
    {
        $r = $this->executar('inscrever', ['subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']]);

        $this->assertSame(400, $r->response()->getStatusCode());
    }

    public function testInscreverSemChavesNoServidor(): void
    {
        $this->config->vapidPublicKey = '';
        $r = $this->executar('inscrever', ['subscription' => (new NavegadorFalso())->inscricao()]);

        $this->assertSame(503, $r->response()->getStatusCode());
    }

    public function testRemoverEOTeste(): void
    {
        $navegador = new NavegadorFalso('https://fcm.googleapis.com/fcm/send/abc');
        $this->executar('inscrever', ['subscription' => $navegador->inscricao()]);

        $r = $this->executar('teste');
        $this->assertSame(200, $r->response()->getStatusCode());
        $this->assertSame(1, self::json($r)['data']['enviadas']);

        $this->executar('remover', ['endpoint' => $navegador->endpoint]);
        $this->assertSame([], $this->repo->inscricoesDoUsuario(7));

        $r = $this->executar('teste');
        $this->assertSame(422, $r->response()->getStatusCode(), 'sem aparelhos: ninguém recebeu');
    }

    public function testRotasExigemLogin(): void
    {
        $routes = new RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/apiV2.php';

        $get  = $routes->getRoutes('GET');
        $post = $routes->getRoutes('POST');
        $this->assertSame('\App\Controllers\ApiV2\Push::config', $get['api/v2/push/config']);
        $this->assertSame('\App\Controllers\ApiV2\Push::inscrever', $post['api/v2/push/inscricao']);
        $this->assertSame('\App\Controllers\ApiV2\Push::remover', $post['api/v2/push/inscricao/remover']);
        $this->assertSame('\App\Controllers\ApiV2\Push::teste', $post['api/v2/push/teste']);

        foreach (['api/v2/push/config' => 'GET', 'api/v2/push/inscricao' => 'POST', 'api/v2/push/teste' => 'POST'] as $rota => $verbo) {
            $this->assertContains('auth', (array) $routes->getFiltersForRoute($rota, $verbo), "{$verbo} {$rota} sem filtro auth");
        }
    }
}
