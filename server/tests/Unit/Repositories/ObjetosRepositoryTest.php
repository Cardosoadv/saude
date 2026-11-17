<?php

namespace Tests\Unit\Repositories;

use App\Repositories\ObjetosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ObjetosRepositoryTest extends CIUnitTestCase
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
        $this->db->table('processos_objeto')->insert([
            'bairro'      => 'Copacabana',
            'cidade'      => 'Rio de Janeiro',
            'quadra'      => '0001',
            'lote'        => '0001',
            'cod_interno' => 'COP00010001',
        ]);

        ObjetosRepository::clearVersions();
        cache()->clean();
    }

    /**
     * Regressão: o cache interno de paginate() não pode ser reaproveitado
     * entre buscas com termos diferentes na mesma página/perPage, senão a
     * segunda busca retorna os resultados (ou a ausência deles) da primeira.
     */
    public function testBuscasComTermosDiferentesNaoReaproveitamCachePaginado()
    {
        $repository = new ObjetosRepository();

        $semResultado = $repository->buscarObjetos('inexistente-123', 1, 10);
        $this->assertCount(0, $semResultado['objetos']);

        $comResultado = $repository->buscarObjetos('por', 1, 10);
        $this->assertCount(1, $comResultado['objetos']);
        $this->assertSame('POR00040049', $comResultado['objetos'][0]['cod_interno']);
    }

    public function testBuscaFiltraPorCodigoInterno()
    {
        $repository = new ObjetosRepository();

        $resultado = $repository->buscarObjetos('cop', 1, 10);

        $this->assertCount(1, $resultado['objetos']);
        $this->assertSame('Copacabana', $resultado['objetos'][0]['bairro']);
    }
}
