<?php

namespace Tests\Unit\Benchmarks;

use CodeIgniter\Test\CIUnitTestCase;

class CacheBenchmarkTest extends CIUnitTestCase
{
    protected $cache;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }
        $this->cache = service('cache');
        $this->cache->clean();
    }

    public function testCacheDeletionBenchmark()
    {
        $numKeys = 100;
        $userId = 1;
        $agendamentosIds = [];

        // Preencher cache
        for ($i = 1; $i <= $numKeys; $i++) {
            $agendamentosIds[] = ['id_agendamento' => $i];
            $this->cache->save('agendamento_' . $i . '_user_' . $userId, 'data_' . $i);
        }

        // Benchmark O(N) Deletion
        $startOn = microtime(true);
        foreach ($agendamentosIds as $agendamento) {
            $this->cache->delete('agendamento_' . $agendamento['id_agendamento'] . '_user_' . $userId);
        }
        $endOn = microtime(true);
        $timeOn = $endOn - $startOn;

        // Benchmark O(1) Version Increment (Simulado)
        // No sistema real, clearCache incrementa uma versão
        $cacheKey = 'agendamentos';
        $versionKey = "{$cacheKey}_version";
        $this->cache->save($versionKey, 1);

        $startO1 = microtime(true);
        $currentVersion = $this->cache->get($versionKey);
        $this->cache->save($versionKey, $currentVersion + 1);
        $endO1 = microtime(true);
        $timeO1 = $endO1 - $startO1;

        echo "\nCache Deletion Benchmark ($numKeys keys):\n";
        echo "O(N) Loop Deletion: " . number_format($timeOn * 1000, 4) . " ms\n";
        echo "O(1) Version Increment: " . number_format($timeO1 * 1000, 4) . " ms\n";

        if ($timeO1 < $timeOn) {
            $improvement = ($timeOn - $timeO1) / $timeOn * 100;
            echo "Improvement: " . number_format($improvement, 2) . "%\n";
        }

        $this->assertTrue(true);
    }
}
