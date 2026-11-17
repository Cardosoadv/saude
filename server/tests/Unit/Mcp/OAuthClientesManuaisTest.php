<?php

namespace Tests\Unit\Mcp;

use App\Controllers\Mcp\OAuthController;
use App\Database\Migrations\AddOAuthClientesManuais;
use App\Database\Migrations\CreateMcpOAuthClients;
use App\Database\Migrations\CreateMcpOAuthCodes;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Settings\Database\Migrations\AddContextColumn;
use CodeIgniter\Settings\Database\Migrations\CreateSettingsTable;
use CodeIgniter\Shield\Database\Migrations\CreateAuthTables;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Config\Database;

/**
 * Conector do Gemini: cliente OAuth confidencial cadastrado à mão (Client
 * ID/Secret), que manda as credenciais por client_secret_basic e pode não
 * usar PKCE. Clientes públicos (Claude.ai via /oauth/register) continuam
 * obrigados a usar PKCE.
 */
class OAuthClientesManuaisTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const REDIRECT = 'https://gemini.example.com/oauth-redirect';
    private const SEGREDO  = 'segredo-do-gemini';

    protected function setUp(): void
    {
        parent::setUp();
        // auth()->loggedIn() consulta a tabela de settings do Shield.
        require_once VENDORPATH . 'codeigniter4/settings/src/Database/Migrations/2021-07-04-041948_CreateSettingsTable.php';
        require_once VENDORPATH . 'codeigniter4/settings/src/Database/Migrations/2021-11-14-143905_AddContextColumn.php';
        require_once VENDORPATH . 'codeigniter4/shield/src/Database/Migrations/2020-12-28-223112_create_auth_tables.php';
        require_once APPPATH . 'Database/Migrations/Mcp/2026-08-27-090000_CreateMcpOAuthClients.php';
        require_once APPPATH . 'Database/Migrations/Mcp/2026-08-27-090001_CreateMcpOAuthCodes.php';
        require_once APPPATH . 'Database/Migrations/Mcp/2026-10-24-120000_AddOAuthClientesManuais.php';

        // O banco em memória é compartilhado com outros testes: recria do zero.
        $forge = Database::forge($this->db);
        $this->derrubarTabelas();
        (new CreateSettingsTable($forge))->up();
        (new AddContextColumn($forge))->up();
        (new CreateAuthTables($forge))->up();
        (new CreateMcpOAuthClients($forge))->up();
        (new CreateMcpOAuthCodes($forge))->up();
        (new AddOAuthClientesManuais($forge))->up();

        $this->db->table('users')->insert(['username' => 'ana', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);

        $this->db->table('mcp_oauth_clients')->insertBatch([
            [
                'client_id'                  => 'gemini',
                'client_secret_hash'         => hash('sha256', self::SEGREDO),
                'client_name'                => 'Gemini',
                'redirect_uris'              => json_encode([self::REDIRECT]),
                'token_endpoint_auth_method' => 'client_secret_basic',
                'usuario_id'                 => 1,
                'created_at'                 => date('Y-m-d H:i:s'),
            ],
            [
                'client_id'                  => 'publico',
                'client_secret_hash'         => null,
                'client_name'                => 'Claude',
                'redirect_uris'              => json_encode([self::REDIRECT]),
                'token_endpoint_auth_method' => 'none',
                'usuario_id'                 => null,
                'created_at'                 => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->derrubarTabelas();
        parent::tearDown();
    }

    private function derrubarTabelas(): void
    {
        $forge = Database::forge($this->db);
        (new CreateMcpOAuthCodes($forge))->down();
        (new CreateMcpOAuthClients($forge))->down();
        (new CreateAuthTables($forge))->down();
        $forge->dropTable('settings', true);
    }

    private function emitirCodigo(string $clientId, ?string $challenge): string
    {
        $codigo = bin2hex(random_bytes(8));
        $this->db->table('mcp_oauth_codes')->insert([
            'code_hash'             => hash('sha256', $codigo),
            'client_id'             => $clientId,
            'usuario_id'            => 1,
            'redirect_uri'          => self::REDIRECT,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'scope'                 => 'mcp',
            'expires_at'            => date('Y-m-d H:i:s', time() + 300),
            'created_at'            => date('Y-m-d H:i:s'),
        ]);

        return $codigo;
    }

    private function token(array $post, ?string $basic = null)
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config, 'oauth/token'), http_build_query($post), new UserAgent());
        $request->setMethod('POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setGlobal('post', $post);
        if ($basic !== null) {
            $request->setHeader('Authorization', 'Basic ' . base64_encode($basic));
        }

        return $this->withRequest($request)->controller(OAuthController::class)->execute('token');
    }

    private function authorize(array $get)
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config, 'oauth/authorize'), null, new UserAgent());
        $request->setMethod('GET');
        $request->setGlobal('get', $get);

        return $this->withRequest($request)->controller(OAuthController::class)->execute('authorize');
    }

    public function testClienteConfidencialTrocaCodigoSemPkceUsandoBasicAuth(): void
    {
        $codigo = $this->emitirCodigo('gemini', null);

        // Gemini repete o client_id no corpo e manda o segredo só no header.
        $r = $this->token(
            ['grant_type' => 'authorization_code', 'code' => $codigo, 'redirect_uri' => self::REDIRECT, 'client_id' => 'gemini'],
            'gemini:' . self::SEGREDO
        );

        $this->assertSame(200, $r->response()->getStatusCode(), $r->response()->getBody());
        $corpo = json_decode($r->response()->getBody(), true);
        $this->assertNotEmpty($corpo['access_token']);
        $this->assertSame('Bearer', $corpo['token_type']);
        $this->assertSame(
            'Gemini (OAuth)',
            $this->db->table('auth_identities')->where('type', 'access_token')->get()->getRowArray()['name']
        );
    }

    public function testClienteConfidencialComSegredoErradoERecusado(): void
    {
        $codigo = $this->emitirCodigo('gemini', null);

        $r = $this->token(
            ['grant_type' => 'authorization_code', 'code' => $codigo, 'redirect_uri' => self::REDIRECT, 'client_id' => 'gemini'],
            'gemini:errado'
        );

        $this->assertSame(401, $r->response()->getStatusCode());
        $this->assertSame('invalid_client', json_decode($r->response()->getBody(), true)['error']);
    }

    public function testCodigoComPkceContinuaExigindoCodeVerifier(): void
    {
        $verifier  = str_repeat('v', 50);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $base      = ['grant_type' => 'authorization_code', 'redirect_uri' => self::REDIRECT, 'client_id' => 'publico'];

        $r = $this->token($base + ['code' => $this->emitirCodigo('publico', $challenge)]);
        $this->assertSame(400, $r->response()->getStatusCode());
        $this->assertSame('invalid_grant', json_decode($r->response()->getBody(), true)['error']);

        $r = $this->token($base + ['code' => $this->emitirCodigo('publico', $challenge), 'code_verifier' => $verifier]);
        $this->assertSame(200, $r->response()->getStatusCode(), $r->response()->getBody());
    }

    public function testClientePublicoSemPkceERecusadoNoAuthorize(): void
    {
        $r = $this->authorize(['client_id' => 'publico', 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'abc']);

        $location = $r->response()->getHeaderLine('Location');
        $this->assertStringStartsWith(self::REDIRECT . '?', $location);
        $this->assertStringContainsString('error=invalid_request', $location);
        $this->assertStringContainsString('state=abc', $location);
    }

    public function testClienteConfidencialSemPkceSegueParaLogin(): void
    {
        // Outros testes da suíte resetam o serviço de rotas, levando a rota nomeada do Shield.
        service('routes')->get('login', 'Login::index', ['as' => 'login']);

        $r = $this->authorize(['client_id' => 'gemini', 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'abc']);

        $this->assertStringNotContainsString('error=', $r->response()->getHeaderLine('Location'));
        $this->assertStringContainsString('login', $r->response()->getHeaderLine('Location'));
    }
}
