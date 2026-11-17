<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\EtiquetasModel;

// Ensure user_id mock exists
if (!function_exists('user_id')) {
    function user_id()
    {
        return 1;
    }
}

class EtiquetasModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $basePath = 'app/Database';
    protected $namespace = 'App';

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
    }

    public function testCreateEtiqueta()
    {
        $model = new EtiquetasModel();

        $data = [
            'nome' => 'Teste Unitário',
            'cor'  => 'FF0000'
        ];

        try {
            $model->insert($data);
            $id = $model->getInsertID();
            $this->seeInDatabase('etiquetas', ['id_etiqueta' => $id, 'nome' => 'Teste Unitário']);
        } catch (\Exception $e) {
            $this->fail('Insertion failed: ' . $e->getMessage());
        }
    }

    public function testUpdateEtiqueta()
    {
        $model = new EtiquetasModel();

        try {
            $model->insert(['nome' => 'Para Editar', 'cor' => '00FF00']);
            $id = $model->getInsertID();

            $model->update($id, ['nome' => 'Editado']);

            $this->seeInDatabase('etiquetas', ['id_etiqueta' => $id, 'nome' => 'Editado']);
        } catch (\Exception $e) {
            $this->fail('Update failed: ' . $e->getMessage());
        }
    }

    public function testDeleteEtiqueta()
    {
        $model = new EtiquetasModel();

        try {
            $model->insert(['nome' => 'Para Deletar', 'cor' => '0000FF']);
            $id = $model->getInsertID();

            $model->delete($id);

            $this->dontSeeInDatabase('etiquetas', ['id_etiqueta' => $id]);
        } catch (\Exception $e) {
            if (isset($id)) {
                $model->delete($id, true); // force delete if needed
            }
            $this->fail('Delete failed: ' . $e->getMessage());
        }
    }
}
