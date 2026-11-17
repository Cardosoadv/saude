<?php

namespace Tests\Unit\Services;

use App\Services\AuditoriaArquivamentoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Só dados fictícios; tabela `auditoria` simplificada (sem FK) no SQLite de teste.
 */
class AuditoriaArquivamentoServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge($this->db);
        $forge->dropTable('auditoria', true);
        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'       => ['type' => 'INTEGER', 'null' => true],
            'table_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'action_type'   => ['type' => 'VARCHAR', 'constraint' => 10],
            'dados_antigos' => ['type' => 'TEXT', 'null' => true],
            'dados_novos'   => ['type' => 'TEXT', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('auditoria');

        $this->dir = sys_get_temp_dir() . '/audit_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Database::forge($this->db)->dropTable('auditoria', true);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function popular(int $n, string $data = '2026-01-10 10:00:00'): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $this->db->table('auditoria')->insert([
                'user_id'       => 1,
                'table_name'    => 'clientes',
                'action_type'   => 'UPDATE',
                'dados_antigos' => json_encode(['nome' => 'João ' . $i]),
                'dados_novos'   => json_encode(['nome' => 'José ' . $i]),
                'ip_address'    => '127.0.0.1',
                'created_at'    => $data,
            ]);
        }
    }

    private function servico(): AuditoriaArquivamentoService
    {
        return new AuditoriaArquivamentoService($this->db);
    }

    public function testArquivaTudoGeraBackupConferidoEApagaATabela(): void
    {
        $this->popular(2500); // mais de um lote

        $r = $this->servico()->arquivar(0, $this->dir);

        $this->assertSame(2500, $r['total']);
        $this->assertSame(2500, $r['apagados']);
        $this->assertSame(0, $this->db->table('auditoria')->countAllResults());
        $this->assertFileExists($r['arquivo']);
        $this->assertSame($r['sha256'], hash_file('sha256', $r['arquivo']));
        $this->assertFileExists($r['arquivo'] . '.sha256');
        $this->assertFalse($r['otimizada']); // SQLite: OPTIMIZE ignorado

        $linhas = file('compress.zlib://' . $r['arquivo'], FILE_IGNORE_NEW_LINES);
        $this->assertCount(2500, $linhas);
        $this->assertSame('João 1', json_decode(json_decode($linhas[0], true)['dados_antigos'], true)['nome']);
    }

    public function testManterDiasPreservaRegistrosRecentes(): void
    {
        $this->popular(3, '2020-01-01 00:00:00');
        $this->popular(2, date('Y-m-d H:i:s'));

        $r = $this->servico()->arquivar(30, $this->dir);

        $this->assertSame(3, $r['apagados']);
        $this->assertSame(2, $this->db->table('auditoria')->countAllResults());
    }

    public function testSimularNaoGravaNemApaga(): void
    {
        $this->popular(4);

        $r = $this->servico()->arquivar(0, $this->dir, true, true);

        $this->assertSame(4, $r['total']);
        $this->assertNull($r['arquivo']);
        $this->assertSame(4, $this->db->table('auditoria')->countAllResults());
        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function testTabelaVaziaNaoCriaArquivo(): void
    {
        $r = $this->servico()->arquivar(0, $this->dir);

        $this->assertSame(0, $r['total']);
        $this->assertNull($r['arquivo']);
    }

    public function testFalhaNaGravacaoNaoApagaNada(): void
    {
        $this->popular(3);
        // O "diretório" é um arquivo existente: a criação da pasta falha.
        $arquivo = tempnam(sys_get_temp_dir(), 'aud');

        try {
            $this->servico()->arquivar(0, $arquivo . '/sub');
            $this->fail('Deveria lançar exceção.');
        } catch (\RuntimeException) {
            $this->assertSame(3, $this->db->table('auditoria')->countAllResults());
        } finally {
            unlink($arquivo);
        }
    }
}
