<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Site\ApiController;
use App\Database\Migrations\CreateSiteConteudoTables;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Config\Database;
use Config\Services;

/**
 * Formulário de contato do site: ciência da Política de Privacidade (LGPD, issue #692).
 */
class SiteContatoPrivacidadeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        (new CreateSiteConteudoTables($forge))->up();

        $forge->dropTable('site_crm_contacts', true);
        $forge->addField([
            'id'                    => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'                  => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'message'               => ['type' => 'TEXT'],
            'area_interesse'        => ['type' => 'INTEGER'],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'new'],
            'privacidade_aceite_em' => ['type' => 'DATETIME', 'null' => true],
            'privacidade_versao'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('site_crm_contacts');

        // O construtor do controller monta serviços que leem as configurações gerais do sistema.
        $forge->dropTable('configuracoes', true);
        $forge->addField([
            'id'        => ['type' => 'INTEGER', 'auto_increment' => true],
            'nome'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'valor'     => ['type' => 'TEXT', 'null' => true],
            'descricao' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('configuracoes');

        // A notificação por e-mail consulta o ramo do direito da área escolhida; sem a tabela, o
        // SQLite3Exception falha no CI (xdebug transforma a exceção capturada em Error).
        $forge->dropTable('juris_ramos_direito', true);
        $forge->addField([
            'id'     => ['type' => 'INTEGER', 'auto_increment' => true],
            'nome'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'pai_id' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('juris_ramos_direito');

        $this->db->table('site_configuracoes')->insert(['chave' => 'lgpd.politica_versao', 'valor' => '2026-10-13', 'tipo' => 'string', 'grupo' => 'lgpd']);
    }

    protected function tearDown(): void
    {
        Database::forge($this->db)->dropTable('site_crm_contacts', true);
        Database::forge($this->db)->dropTable('configuracoes', true);
        Database::forge($this->db)->dropTable('juris_ramos_direito', true);
        (new CreateSiteConteudoTables(Database::forge($this->db)))->down();
        parent::tearDown();
    }

    private function enviar(array $dados)
    {
        // Cada envio é uma requisição nova: não reaproveita o validador compartilhado da anterior.
        Services::resetSingle('validation');
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config, 'api/v2/site/contact'), json_encode($dados), new UserAgent());
        $request->setMethod('POST');
        $request->setHeader('Content-Type', 'application/json');
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');

        return $this->withRequest($request)->withBody(json_encode($dados))->controller(ApiController::class)->execute('submitContact');
    }

    private const CONTATO = ['nome' => 'Maria Titular', 'email' => 'maria@example.com', 'telefone' => '', 'area' => '1', 'mensagem' => 'Preciso de ajuda.'];

    public function testSemCienciaDaPoliticaORegistroERecusado(): void
    {
        $r = $this->enviar(self::CONTATO);

        $this->assertSame(400, $r->response()->getStatusCode());
        $this->assertStringContainsString('Pol&iacute;tica de Privacidade', $r->getBody());
        $this->assertSame(0, $this->db->table('site_crm_contacts')->countAllResults());

        $r = $this->enviar(self::CONTATO + ['aceite_privacidade' => '0']);
        $this->assertSame(400, $r->response()->getStatusCode(), 'checkbox desmarcado não vale como ciência');
    }

    public function testComCienciaGravaDataEVersaoDaPolitica(): void
    {
        $antes = date('Y-m-d H:i:s');
        $r = $this->enviar(self::CONTATO + ['aceite_privacidade' => '1']);

        $this->assertSame(201, $r->response()->getStatusCode(), $r->getBody());
        $contato = $this->db->table('site_crm_contacts')->get()->getRowArray();
        $this->assertSame('maria@example.com', $contato['email']);
        $this->assertSame('2026-10-13', $contato['privacidade_versao']);
        $this->assertGreaterThanOrEqual($antes, $contato['privacidade_aceite_em']);
    }

    public function testCheckboxDoFormularioOuBooleanoDoJsonValemComoCiencia(): void
    {
        $this->assertSame(201, $this->enviar(self::CONTATO + ['aceite_privacidade' => 'on'])->response()->getStatusCode());
        $this->assertSame(201, $this->enviar(self::CONTATO + ['aceite_privacidade' => true])->response()->getStatusCode());
        $this->assertSame(2, $this->db->table('site_crm_contacts')->countAllResults());
    }
}
