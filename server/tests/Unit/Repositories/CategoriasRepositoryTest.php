<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\Financeiro\FinanceiroCategoriasModel;
use App\Repositories\Financeiro\CategoriasRepository;

class CategoriasRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = 'App';

    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Force logout to prevent foreign key errors from leftover auth sessions in other tests
        if (function_exists('auth')) {
            auth()->logout();
        }

        // Ensure user 1 exists for AuditoriaTrait foreign key checks
        $this->db->table('users')->ignore(true)->insert([
            'id' => 1,
            'username' => 'system',
            'active' => 1
        ]);

        $this->repository = new CategoriasRepository();
    }

    public function testCreateAndFind()
    {
        $data = [
            'categoria' => 'Teste Unitário',
            'cor' => '#000000',
            'comentarios' => 'Teste'
        ];

        $id = $this->repository->create($data);
        $this->assertIsInt($id);

        $cat = $this->repository->findById($id);
        $this->assertEquals('Teste Unitário', $cat['categoria']);
    }

    public function testFindAllTree()
    {
        // Criar estrutura Pai -> Filho
        $paiId = $this->repository->create(['categoria' => 'Pai']);
        $filhoId = $this->repository->create(['categoria' => 'Filho', 'categoria_pai_id' => $paiId]);

        $tree = $this->repository->findAllTree();

        // Verifica se ambos estão lá
        $this->assertGreaterThanOrEqual(2, count($tree));

        // Verificar ordem ou estrutura (Tree retorna flat)
        // Se a implementação ordena Pai antes de filho (por nome ou ID se nome igual), verificar.
        // O findAllTree atual ordena recursivamente.

        // Simplesmente verificar que existem
        $ids = array_column($tree, 'id_categoria');
        $this->assertContains($paiId, $ids);
        $this->assertContains($filhoId, $ids);
    }
}
