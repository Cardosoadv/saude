<?php

namespace Tests\Unit\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use App\Controllers\ApiV2\BusinessCanvas;
use App\Services\BusinessCanvasService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;

class BusinessCanvasControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use AuthenticationTesting;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $db = \Config\Database::connect();
        $prefix = $db->getPrefix();

        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT, key TEXT, value TEXT, type TEXT, context TEXT, created_at DATETIME, updated_at DATETIME
        )");
        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}auth_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            selector TEXT, hashed_validator TEXT, user_id INTEGER, expires DATETIME, created_at DATETIME, updated_at DATETIME
        )");

        $this->service = $this->createMock(BusinessCanvasService::class);
    }

    protected function createMockUser(int $id, array $permissions = [])
    {
        $user = $this->getMockBuilder(User::class)
            ->setConstructorArgs([['id' => $id, 'username' => 'testuser' . $id, 'email' => "test$id@example.com"]])
            ->onlyMethods(['can'])
            ->getMock();

        $user->method('can')->willReturnCallback(function($perm) use ($permissions) {
            return in_array($perm, $permissions);
        });

        return $user;
    }

    public function testIndexForbidden()
    {
        $user = $this->createMockUser(1, []);
        $this->actingAs($user);

        $result = $this->withConfig(config('App'))
                       ->controller(BusinessCanvas::class)
                       ->execute('index');

        $this->assertEquals(403, $result->response()->getStatusCode());
    }

    public function testIndexAllowedAccess()
    {
        $user = $this->createMockUser(1, ['business_canvas.access']);
        $this->actingAs($user);

        $result = $this->withConfig(config('App'))
                       ->controller(BusinessCanvas::class)
                       ->execute('index');

        $this->assertEquals(200, $result->response()->getStatusCode());
    }
}
