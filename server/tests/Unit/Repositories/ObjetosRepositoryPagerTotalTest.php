<?php

namespace Tests\Unit\Repositories;

use App\Repositories\ObjetosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ObjetosRepositoryPagerTotalTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query("DROP TABLE IF EXISTS db_processos_objeto");

        $this->db->query("CREATE TABLE db_processos_objeto (
            id_objeto INTEGER PRIMARY KEY AUTOINCREMENT,
            comentarios TEXT,
            cidade TEXT,
            bairro TEXT,
            logradouro TEXT,
            numero TEXT,
            complemento TEXT,
            cep TEXT,
            quadra TEXT,
            lote TEXT,
            inscricao TEXT,
            cod_interno TEXT,
            matricula TEXT,
            cartorio TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");

        $this->db->table('processos_objeto')->insert([
            'bairro'      => 'Porto Seguro',
            'cidade'      => 'Ribeirão das Neves',
            'quadra'      => '0004',
            'lote'        => '0049',
            'cod_interno' => 'POR00040049',
        ]);

        ObjetosRepository::clearVersions();
        cache()->clean();
    }

    /**
     * Regressão para o 500: GET /api/v2/objetos chamava $pager->getTotal() sem
     * group (== 'default'), mas o Pager era populado sob um group derivado do
     * termo de busca, deixando o group 'default' sem total (TypeError).
     */
    public function testPagerRetornaTotalNoGroupPadraoSemBuscar()
    {
        $repository = new ObjetosRepository();

        $resultado = $repository->buscarObjetos(null, 1, 25);
        $pager     = $resultado['pager'];

        $this->assertSame(1, $pager->getTotal());
        $this->assertSame(1, $pager->getPageCount());
    }

    public function testPagerRetornaTotalNoGroupPadraoComBuscaEEmCacheHit()
    {
        $repository = new ObjetosRepository();

        // Primeira chamada popula o cache interno de paginate() e o cache externo de remember().
        $repository->buscarObjetos('por', 1, 25);

        // Segunda chamada idêntica deve vir do cache (remember externo) e ainda assim
        // expor um Pager cujo group 'default' tem total definido.
        $resultado = $repository->buscarObjetos('por', 1, 25);
        $pager     = $resultado['pager'];

        $this->assertSame(1, $pager->getTotal());
    }
}
