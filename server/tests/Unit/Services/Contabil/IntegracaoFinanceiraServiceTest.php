<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contabil;

use App\Database\Migrations\CreateContabilCategoriasContas;
use App\Database\Seeds\ContabilSeeder;
use App\Services\Contabil\IntegracaoFinanceiraService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateCoreTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateEntryTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateSupportTables;
use Config\Database;

/**
 * Testes unitários do serviço de integração contábil e importação financeira.
 *
 * @version 1.0.0
 */
class IntegracaoFinanceiraServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private IntegracaoFinanceiraService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge($this->db);

        // 1. Cria tabelas contábeis na conexão de teste se ainda não existirem
        if (! $this->db->tableExists('acc_entities')) {
            require_once dirname(__DIR__, 5) . '/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000001_CreateCoreTables.php';
            require_once dirname(__DIR__, 5) . '/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000002_CreateEntryTables.php';
            require_once dirname(__DIR__, 5) . '/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000003_CreateSupportTables.php';

            (new CreateCoreTables($forge))->up();
            (new CreateEntryTables($forge))->up();
            (new CreateSupportTables($forge))->up();
        }

        // 2. Cria tabelas financeiras simuladas
        $forge->dropTable('fin_contas', true);
        $forge->addField([
            'id_conta' => ['type' => 'INTEGER', 'auto_increment' => true],
            'conta'    => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addPrimaryKey('id_conta');
        $forge->createTable('fin_contas');

        $forge->dropTable('fin_receitas', true);
        $forge->addField([
            'id_receita' => ['type' => 'INTEGER', 'auto_increment' => true],
            'receita'    => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addPrimaryKey('id_receita');
        $forge->createTable('fin_receitas');

        $forge->dropTable('fin_pgto_receitas', true);
        $forge->addField([
            'id_pgto_receita'      => ['type' => 'INTEGER', 'auto_increment' => true],
            'receita_id'           => ['type' => 'INTEGER'],
            'conta_id'             => ['type' => 'INTEGER', 'null' => true],
            'pagamento_receita_dt' => ['type' => 'DATE'],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'comentario'           => ['type' => 'TEXT', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_pgto_receita');
        $forge->createTable('fin_pgto_receitas');

        $forge->dropTable('fin_despesas', true);
        $forge->addField([
            'id_despesa' => ['type' => 'INTEGER', 'auto_increment' => true],
            'despesa'    => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addPrimaryKey('id_despesa');
        $forge->createTable('fin_despesas');

        $forge->dropTable('fin_pgto_despesas', true);
        $forge->addField([
            'id_pgto_despesa'      => ['type' => 'INTEGER', 'auto_increment' => true],
            'despesa_id'           => ['type' => 'INTEGER'],
            'conta_id'             => ['type' => 'INTEGER', 'null' => true],
            'pagamento_despesa_dt' => ['type' => 'DATE'],
            'valor'                => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'comentario'           => ['type' => 'TEXT', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_pgto_despesa');
        $forge->createTable('fin_pgto_despesas');

        $forge->dropTable('fin_transferencias', true);
        $forge->addField([
            'id_transferencia'   => ['type' => 'INTEGER', 'auto_increment' => true],
            'transferencia'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'data_transferencia' => ['type' => 'DATE'],
            'id_conta_origem'    => ['type' => 'INTEGER'],
            'id_conta_destino'   => ['type' => 'INTEGER'],
            'valor'              => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'comentarios'        => ['type' => 'TEXT', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_transferencia');
        $forge->createTable('fin_transferencias');

        $forge->dropTable('fin_categorias', true);
        $forge->addField([
            'id_categoria'     => ['type' => 'INTEGER', 'auto_increment' => true],
            'categoria'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'cor'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'comentarios'      => ['type' => 'TEXT', 'null' => true],
            'categoria_pai_id' => ['type' => 'INTEGER', 'null' => true],
            'elemento_id'      => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_categoria');
        $forge->createTable('fin_categorias');

        foreach (['fin_receitas_categorias' => 'receita_id', 'fin_despesas_categorias' => 'despesa_id'] as $tabela => $chave) {
            $forge->dropTable($tabela, true);
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true],
                $chave         => ['type' => 'INTEGER'],
                'categoria_id' => ['type' => 'INTEGER', 'null' => true],
                'valor'        => ['type' => 'DECIMAL', 'constraint' => '15,2'],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addPrimaryKey('id');
            $forge->createTable($tabela);
        }

        require_once APPPATH . 'Database/Migrations/Contabil/2026-10-24-100000_CreateContabilCategoriasContas.php';
        $migracaoDicionario = new CreateContabilCategoriasContas($forge);
        $migracaoDicionario->down();
        $migracaoDicionario->up();

        // Cria tabela settings exigida pelo Shield
        $forge->dropTable('settings', true);
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 31],
            'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('settings');

        // 3. Executa o Seeder Contábil
        $seeder = new ContabilSeeder(config(Database::class), $this->db);
        $seeder->run();

        $this->service = new IntegracaoFinanceiraService($this->db);
    }

    public function testObterContasFolhasRetornaApenasContasQueAceitamLancamento(): void
    {
        $contas = $this->service->obterContasFolhas(1);

        $this->assertIsArray($contas);
        $this->assertNotEmpty($contas);

        foreach ($contas as $conta) {
            $this->assertArrayHasKey('id', $conta);
            $this->assertArrayHasKey('code', $conta);
            $this->assertArrayHasKey('name', $conta);
            $this->assertArrayHasKey('type', $conta);
            $this->assertArrayHasKey('rotulo', $conta);
            $this->assertNotEmpty($conta['code']);
        }
    }

    public function testObterTransacoesRetornaEstruturaComTransacoesEKpis(): void
    {
        // Insere dados simulados
        $this->db->table('fin_contas')->insert(['id_conta' => 1, 'conta' => 'Banco Principal']);
        $this->db->table('fin_receitas')->insert(['id_receita' => 1, 'receita' => 'Honorários Advocatícios']);
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita'      => 10,
            'receita_id'           => 1,
            'conta_id'             => 1,
            'pagamento_receita_dt' => '2026-05-10',
            'valor'                => 2500.00,
            'comentario'           => 'Parcela 1/3',
        ]);

        $resultado = $this->service->obterTransacoes(
            dataInicio: '2026-01-01',
            dataFim: '2026-12-31',
            entityId: 1
        );

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('transacoes', $resultado);
        $this->assertArrayHasKey('kpis', $resultado);

        $this->assertCount(1, $resultado['transacoes']);
        $item = $resultado['transacoes'][0];
        $this->assertSame('fin_pgto_receita', $item['source_type']);
        $this->assertSame(10, $item['source_id']);
        $this->assertSame('pendente', $item['status_contabil']);
        $this->assertSame(2500.0, $item['valor']);

        $kpis = $resultado['kpis'];
        $this->assertSame(1, $kpis['total_geral']);
        $this->assertSame(1, $kpis['total_pendentes']);
        $this->assertSame(0, $kpis['total_importados']);
        $this->assertSame(2500.0, $kpis['valor_pendente']);
    }

    public function testImportarLoteValidaItensEImportaComIdempotencia(): void
    {
        $itensInvalidos = [
            [
                'source_type' => 'invalido',
                'source_id'   => 999,
                'valor'       => 0,
            ],
        ];

        $resultadoInvalido = $this->service->importarLote($itensInvalidos, 1);
        $this->assertSame(0, $resultadoInvalido['sucesso']);
        $this->assertNotEmpty($resultadoInvalido['erros']);

        $contas = $this->service->obterContasFolhas(1);
        $debito = $contas[0]['id'];
        $credito = $contas[1]['id'];

        $itemValido = [
            'source_type' => 'fin_pgto_receita',
            'source_id'   => 8888,
            'valor'       => 1500.50,
            'data'        => '2026-03-10',
            'descricao'   => 'Receita Teste Unitário Honorários',
            'debito_id'   => $debito,
            'credito_id'  => $credito,
        ];

        $resultadoValido = $this->service->importarLote([$itemValido], 1, postar: true);
        $this->assertSame(1, $resultadoValido['sucesso']);
        $this->assertEmpty($resultadoValido['erros']);

        // Teste de idempotência
        $reimportacao = $this->service->importarLote([$itemValido], 1, postar: true);
        $this->assertSame(1, $reimportacao['sucesso']);
    }

    public function testImportarLoteDivideOperacaoEmDoisLancamentos(): void
    {
        $contas = $this->service->obterContasFolhas(1);
        $banco = $contas[0]['id'];
        $receitaA = $contas[1]['id'];
        $receitaB = $contas[2]['id'];

        $this->db->table('fin_receitas')->insert(['id_receita' => 1, 'receita' => 'Honorários']);
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita'      => 77,
            'receita_id'           => 1,
            'pagamento_receita_dt' => '2026-04-02',
            'valor'                => 1000.00,
        ]);

        $item = [
            'source_type' => 'fin_pgto_receita',
            'source_id'   => 77,
            'valor'       => 1000.00,
            'data'        => '2026-04-02',
            'descricao'   => 'Honorários divididos',
            'divisoes'    => [
                ['valor' => 600.00, 'debito_id' => $banco, 'credito_id' => $receitaA],
                ['valor' => 400.00, 'debito_id' => $banco, 'credito_id' => $receitaB],
            ],
        ];

        $resultado = $this->service->importarLote([$item], 1);
        $this->assertSame(2, $resultado['sucesso']);
        $this->assertEmpty($resultado['erros']);

        $transacao = $this->service->obterTransacoes('2026-01-01', '2026-12-31', null, null, 1)['transacoes'][0];
        $this->assertSame('importado', $transacao['status_contabil']);
        $this->assertCount(2, $transacao['entry_numbers']);
    }

    public function testImportarLoteRejeitaDivisaoQueNaoFechaOTotal(): void
    {
        $contas = $this->service->obterContasFolhas(1);

        $resultado = $this->service->importarLote([[
            'source_type' => 'fin_pgto_receita',
            'source_id'   => 78,
            'valor'       => 1000.00,
            'data'        => '2026-04-02',
            'divisoes'    => [
                ['valor' => 600.00, 'debito_id' => $contas[0]['id'], 'credito_id' => $contas[1]['id']],
                ['valor' => 300.00, 'debito_id' => $contas[0]['id'], 'credito_id' => $contas[2]['id']],
            ],
        ]], 1);

        $this->assertSame(0, $resultado['sucesso']);
        $this->assertStringContainsString('soma das partes', $resultado['erros'][0]);
    }

    public function testCategoriaUnicaUsaContaDoDicionario(): void
    {
        $contas = $this->contasPorTipo();
        $contaDespesa = $contas['EXPENSE'][1];

        $this->db->table('fin_categorias')->insert(['id_categoria' => 5, 'categoria' => 'Aluguel']);
        $this->db->table('fin_despesas')->insert(['id_despesa' => 1, 'despesa' => 'Aluguel do escritório']);
        $this->db->table('fin_despesas_categorias')->insert(['despesa_id' => 1, 'categoria_id' => 5, 'valor' => 3000.00]);
        $this->db->table('fin_pgto_despesas')->insert([
            'id_pgto_despesa'      => 20,
            'despesa_id'           => 1,
            'pagamento_despesa_dt' => '2026-06-05',
            'valor'                => 3000.00,
        ]);

        $antes = $this->service->obterTransacoes('2026-01-01', '2026-12-31', 'despesa', null, 1)['transacoes'][0];
        $this->assertSame(5, $antes['categoria_id']);
        $this->assertSame('padrao', $antes['sugestao_origem']);
        $this->assertArrayNotHasKey('divisoes', $antes);

        $this->service->salvarDicionarioCategorias([['categoria_id' => 5, 'tipo' => 'despesa', 'account_id' => $contaDespesa['id']]], 1);

        $depois = $this->service->obterTransacoes('2026-01-01', '2026-12-31', 'despesa', null, 1)['transacoes'][0];
        $this->assertSame($contaDespesa['id'], $depois['debito_id']);
        $this->assertSame($contaDespesa['rotulo'], $depois['debito_rotulo']);
        $this->assertSame('dicionario', $depois['sugestao_origem']);
    }

    public function testVariasCategoriasSugeremDivisaoProporcionalAoPagamento(): void
    {
        $contas = $this->contasPorTipo();
        $contaReceita = $contas['REVENUE'][1];

        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Honorários contratuais'],
            ['id_categoria' => 2, 'categoria' => 'Êxito'],
        ]);
        $this->db->table('fin_receitas')->insert(['id_receita' => 1, 'receita' => 'Contrato X']);
        $this->db->table('fin_receitas_categorias')->insertBatch([
            ['receita_id' => 1, 'categoria_id' => 1, 'valor' => 700.00],
            ['receita_id' => 1, 'categoria_id' => 2, 'valor' => 300.00],
        ]);
        // Pagamento parcial: 100,01 de uma receita de 1.000,00
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita'      => 30,
            'receita_id'           => 1,
            'pagamento_receita_dt' => '2026-07-01',
            'valor'                => 100.01,
        ]);

        $this->service->salvarDicionarioCategorias([['categoria_id' => 2, 'tipo' => 'receita', 'account_id' => $contaReceita['id']]], 1);

        $t = $this->service->obterTransacoes('2026-01-01', '2026-12-31', 'receita', null, 1)['transacoes'][0];

        $this->assertTrue($t['divisao_sugerida']);
        $this->assertNull($t['categoria_id']);
        $this->assertCount(2, $t['divisoes']);
        $this->assertSame(70.0, $t['divisoes'][0]['valor']);
        $this->assertSame(30.01, $t['divisoes'][1]['valor']);
        $this->assertSame('padrao', $t['divisoes'][0]['sugestao_origem']);
        $this->assertSame($t['credito_id'], $t['divisoes'][0]['credito_id']);
        $this->assertSame('dicionario', $t['divisoes'][1]['sugestao_origem']);
        $this->assertSame($contaReceita['id'], $t['divisoes'][1]['credito_id']);
        $this->assertSame($t['debito_id'], $t['divisoes'][1]['debito_id']);
        $this->assertStringContainsString('Êxito', $t['divisoes'][1]['descricao']);

        // A divisão sugerida é aceita como está pela importação
        $resultado = $this->service->importarLote([$t], 1);
        $this->assertSame(2, $resultado['sucesso'], implode('; ', $resultado['erros']));
    }

    public function testDicionarioGravaSubstituiERemoveAssociacoes(): void
    {
        $contas = $this->contasPorTipo();
        $this->db->table('fin_categorias')->insert(['id_categoria' => 9, 'categoria' => 'Custas']);

        $this->service->salvarDicionarioCategorias([['categoria_id' => 9, 'tipo' => 'despesa', 'account_id' => $contas['EXPENSE'][0]['id']]], 1);
        $this->service->salvarDicionarioCategorias([['categoria_id' => 9, 'tipo' => 'despesa', 'account_id' => $contas['EXPENSE'][1]['id']]], 1);

        $dicionario = $this->service->obterDicionarioCategorias(1);
        $this->assertCount(1, $dicionario);
        $this->assertSame('Custas', $dicionario[0]['categoria']);
        $this->assertSame($contas['EXPENSE'][1]['id'], $dicionario[0]['account_id']);

        $this->service->salvarDicionarioCategorias([['categoria_id' => 9, 'tipo' => 'despesa', 'account_id' => null]], 1);
        $this->assertSame([], $this->service->obterDicionarioCategorias(1));
    }

    public function testDicionarioRejeitaContaQueNaoAceitaLancamento(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->salvarDicionarioCategorias([['categoria_id' => 1, 'tipo' => 'receita', 'account_id' => 999999]], 1);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function contasPorTipo(): array
    {
        $porTipo = [];
        foreach ($this->service->obterContasFolhas(1) as $conta) {
            $porTipo[$conta['type']][] = $conta;
        }

        return $porTipo;
    }
}
