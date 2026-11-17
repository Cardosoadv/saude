<?php

namespace Tests\Unit\Repositories;

use App\Repositories\BaseRepository;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Cache as CacheConfig;

/**
 * Regressão: o Pager do CI4 carrega o View renderer (com Closures) e não pode
 * ser serializado pelo FileHandler ("Serialization of 'Closure' is not allowed").
 */
class BaseRepositoryPagerCacheTest extends CIUnitTestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = WRITEPATH . 'cache_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->cachePath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cachePath . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cachePath);
        parent::tearDown();
    }

    private function makeRepository(): PagerCacheTestRepository
    {
        $config = new CacheConfig();
        $config->file['storePath'] = $this->cachePath;

        $repo = new PagerCacheTestRepository();
        $repo->setCache(new FileHandler($config));

        return $repo;
    }

    public function testRememberCachesResultWithPagerWithoutSerializingClosures(): void
    {
        $repo   = $this->makeRepository();
        $calls  = 0;
        $result = fn () => [
            'processos' => [['id_processo' => 1]],
            'pager'     => service('pager')->store('default', 2, 10, 35),
        ];

        $first = $repo->remember('lista', function () use (&$calls, $result) {
            $calls++;
            return $result();
        });
        $second = $repo->remember('lista', function () use (&$calls, $result) {
            $calls++;
            return $result();
        });

        $this->assertSame(1, $calls, 'A segunda leitura deve vir do cache.');
        $this->assertInstanceOf(Pager::class, $first['pager']);
        $this->assertInstanceOf(Pager::class, $second['pager']);
        $this->assertSame([['id_processo' => 1]], $second['processos']);
        $this->assertSame(2, $second['pager']->getCurrentPage());
        $this->assertSame(10, $second['pager']->getPerPage());
        $this->assertSame(35, $second['pager']->getTotal());
        $this->assertSame(4, $second['pager']->getPageCount());
    }

    /**
     * Regressão: ProcessosRepository::buscarDetalhesProcesso() cacheia um array com
     * um Pager aninhado (ex.: `['objetos' => ['objetos' => [...], 'pager' => Pager]]`),
     * como devolvido por ProcessoObjetoModel::selecionarObjetoPorProcessoId(). O Pager
     * não fica na chave raiz 'pager', então precisa ser encontrado e congelado em
     * qualquer profundidade, ou a serialização falha com
     * "Serialization of 'Closure' is not allowed".
     */
    public function testRememberCachesResultWithNestedPagerWithoutSerializingClosures(): void
    {
        $repo  = $this->makeRepository();
        $calls = 0;
        $result = fn () => [
            'processo' => ['id_processo' => 1],
            'objetos'  => [
                'objetos' => [['id_objeto' => 1]],
                'pager'   => service('pager')->store('default', 1, 25, 3),
            ],
        ];

        $first = $repo->remember('detalhes', function () use (&$calls, $result) {
            $calls++;
            return $result();
        });
        $second = $repo->remember('detalhes', function () use (&$calls, $result) {
            $calls++;
            return $result();
        });

        $this->assertSame(1, $calls, 'A segunda leitura deve vir do cache.');
        $this->assertInstanceOf(Pager::class, $first['objetos']['pager']);
        $this->assertInstanceOf(Pager::class, $second['objetos']['pager']);
        $this->assertSame([['id_objeto' => 1]], $second['objetos']['objetos']);
        $this->assertSame(1, $second['objetos']['pager']->getCurrentPage());
        $this->assertSame(25, $second['objetos']['pager']->getPerPage());
        $this->assertSame(3, $second['objetos']['pager']->getTotal());
    }
}

class PagerCacheTestRepository extends BaseRepository
{
    public function __construct()
    {
        $this->cacheKey = 'pager_test';
    }

    public function setCache($cache): void
    {
        $this->cache = $cache;
    }
}
