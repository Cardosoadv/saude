<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\Financeiro\CategoriasService;
use App\Repositories\Financeiro\CategoriasRepository;

class CategoriasServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    protected $basePath = 'app/Database';
    protected $namespace = 'App';

    private $service;
    private $repo;

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

        $this->service = new CategoriasService();
        $this->repo = new CategoriasRepository();
    }

    public function testCriarComHerancaElemento()
    {
        // Precisamos da tabela fin_elementos populada.
        // Como o seed não roda automático no trait (gera só estrutura), precisamos rodar seeder ou inserir manual.
        $db = \Config\Database::connect();
        $db->table('fin_elementos')->insert(['nome' => 'Receita']);
        $elementoId = $db->insertID();

        // Criar Pai
        $paiId = $this->repo->create(['categoria' => 'Pai', 'elemento_id' => $elementoId]);

        // Criar Filho SEM passar elemento (deve herdar)
        $filhoData = ['categoria' => 'Filho', 'categoria_pai_id' => $paiId];
        $filhoId = $this->service->criar($filhoData);

        $filho = $this->repo->findById($filhoId);
        $this->assertEquals($elementoId, $filho['elemento_id'], 'Subcategoria deve herdar elemento do pai');
    }

    public function testValidacaoAutoReferencia()
    {
        $id = $this->repo->create(['categoria' => 'Teste Ciclo']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Uma categoria não pode ser pai de si mesma');

        // Tentar ser pai de si mesmo
        $this->service->atualizar($id, ['categoria_pai_id' => $id]);
    }
}
