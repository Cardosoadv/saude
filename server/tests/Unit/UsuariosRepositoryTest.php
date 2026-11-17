<?php

namespace Tests\Unit;

use App\Repositories\UsuariosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class UsuariosRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = false;
    protected $migrateOnce = false;
    protected $refresh     = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        // Drop tables if exist
        $this->db->query("DROP TABLE IF EXISTS db_users");
        $this->db->query("DROP TABLE IF EXISTS db_auth_identities");
        $this->db->query("DROP TABLE IF EXISTS db_auth_groups_users");
        $this->db->query("DROP TABLE IF EXISTS db_auth_permissions_users");

        // Minimal tables for Shield/UsuariosRepository
        $this->db->query("CREATE TABLE db_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255),
            status VARCHAR(255),
            status_message VARCHAR(255),
            active TINYINT(1) DEFAULT 1,
            last_active DATETIME,
            is_lawyer TINYINT(1) DEFAULT 0,
            rateio_ativo TINYINT(1) DEFAULT 0,
            oab_numero VARCHAR(50),
            oab_uf VARCHAR(2),
            avatar VARCHAR(255),
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_auth_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            type VARCHAR(255),
            name VARCHAR(255),
            secret VARCHAR(255),
            secret2 VARCHAR(255),
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_auth_groups_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            `group` VARCHAR(255),
            created_at DATETIME
        )");

        $this->db->query("CREATE TABLE db_auth_permissions_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            permission VARCHAR(255),
            created_at DATETIME
        )");

        // Disable callbacks for AuditoriaTrait
        model('UsuariosModel')->allowCallbacks(false);
    }

    public function testListarPaginado()
    {
        // Seed data
        $this->db->table('users')->insert([
            'username' => 'testuser',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $userId = $this->db->insertID();

        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => 'test@example.com'
        ]);

        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => 'admin'
        ]);

        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $userId,
            'permission' => 'users.create'
        ]);

        $repository = new UsuariosRepository();
        $result = $repository->listarPaginado();

        $this->assertNotEmpty($result['users']);
        $this->assertEquals('testuser', $result['users'][0]['username']);
        $this->assertEquals('test@example.com', $result['users'][0]['email']);
        $this->assertContains('admin', $result['users'][0]['groups']);
        $this->assertEquals(1, $result['users'][0]['permissions_count']);
    }

    public function testFindById()
    {
        $this->db->table('users')->insert(['username' => 'findme']);
        $id = $this->db->insertID();
        
        $this->db->table('auth_identities')->insert([
            'user_id' => $id,
            'type' => 'email_password',
            'secret' => 'findme@example.com'
        ]);

        $repository = new UsuariosRepository();
        $user = $repository->findById($id);

        $this->assertNotNull($user);
        $this->assertEquals('findme', $user['username']);
        $this->assertEquals('findme@example.com', $user['email']);
    }

    public function testGetParticipaRateio()
    {
        $this->db->table('users')->insertBatch([
            ['username' => 'active', 'rateio_ativo' => 1],
            ['username' => 'inactive', 'rateio_ativo' => 0]
        ]);

        $repository = new UsuariosRepository();
        $users = $repository->getParticipaRateio();

        $this->assertCount(1, $users);
        $this->assertEquals('active', $users[0]['username'] ?? $users[0]->username);
    }

    public function testGetAdvogados()
    {
        $this->db->table('users')->insertBatch([
            ['username' => 'lawyer', 'is_lawyer' => 1],
            ['username' => 'not_lawyer', 'is_lawyer' => 0]
        ]);

        $repository = new UsuariosRepository();
        $users = $repository->getAdvogados();

        $this->assertCount(1, $users);
        $this->assertEquals('lawyer', $users[0]['username'] ?? $users[0]->username);
    }
}
