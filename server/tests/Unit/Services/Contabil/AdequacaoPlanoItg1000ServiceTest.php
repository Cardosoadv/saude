<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contabil;

use App\Services\Contabil\AberturaContabilService;
use App\Services\Contabil\AdequacaoPlanoItg1000Service;
use App\Services\Contabil\PlanoContasModeloService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CognitioTechny\Contabil\Application\Chart\ChartFormat;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateCoreTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateEntryTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateSupportTables;
use Config\Database;
use InvalidArgumentException;

/**
 * Plano padrão ITG 1000 e a conversão do plano anterior, sem perder lançamentos.
 *
 * @version 1.0.0
 */
class AdequacaoPlanoItg1000ServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private AdequacaoPlanoItg1000Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge($this->db);

        if (! $this->db->tableExists('acc_entities')) {
            $pasta = dirname(__DIR__, 4) . '/vendor/cognitiotechny/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/';

            require_once $pasta . '2026-10-20-000001_CreateCoreTables.php';
            require_once $pasta . '2026-10-20-000002_CreateEntryTables.php';
            require_once $pasta . '2026-10-20-000003_CreateSupportTables.php';

            (new CreateCoreTables($forge))->up();
            (new CreateEntryTables($forge))->up();
            (new CreateSupportTables($forge))->up();
        }

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

        $this->db->disableForeignKeyChecks();
        foreach (['acc_entry_lines', 'acc_entries', 'acc_balances', 'acc_sequences', 'acc_fiscal_years', 'acc_accounts', 'acc_entities'] as $tabela) {
            if ($this->db->tableExists($tabela)) {
                $this->db->table($tabela)->emptyTable();
            }
        }
        $this->db->enableForeignKeyChecks();

        $this->service = new AdequacaoPlanoItg1000Service($this->db);
    }

    /**
     * Monta o plano anterior (contas analíticas do mapa e grupos genéricos).
     */
    private function criarPlanoAnterior(): void
    {
        $agora = date('Y-m-d H:i:s');
        $this->db->table('acc_entities')->insert(['id' => 1, 'name' => 'Escritório de teste', 'currency' => 'BRL', 'default_basis' => 'ACCRUAL', 'created_at' => $agora, 'updated_at' => $agora]);

        $tipos  = ['1' => 'ASSET', '2' => 'LIABILITY', '3' => 'EQUITY', '4' => 'REVENUE', '5' => 'EXPENSE'];
        $linhas = [];

        foreach (AdequacaoPlanoItg1000Service::MAPA as $codigo => [$nome]) {
            $partes = explode('.', (string) $codigo);

            for ($n = 1; $n < count($partes); $n++) {
                $grupo = implode('.', array_slice($partes, 0, $n));
                $linhas[$grupo] ??= [
                    'code'        => $grupo,
                    'name'        => 'GRUPO ' . $grupo,
                    'type'        => $n === 1 ? $tipos[$partes[0]] : null,
                    'parent_code' => $n === 1 ? null : implode('.', array_slice($partes, 0, $n - 1)),
                ];
            }

            $linhas[(string) $codigo] = [
                'code'        => (string) $codigo,
                'name'        => str_replace('*', 'Teste', $nome),
                'type'        => null,
                'parent_code' => implode('.', array_slice($partes, 0, -1)),
            ];
        }

        service('contabil')->importChart()->execute(1, array_values($linhas), true);
    }

    private function idPorCodigo(string $codigo): int
    {
        $row = $this->db->table('acc_accounts')->where('entity_id', 1)->where('code', $codigo)->get()->getRowArray();
        $this->assertNotNull($row, "Conta {$codigo} não encontrada.");

        return (int) $row['id'];
    }

    public function testPlanoPadraoEhConsistenteEImportaSemErro(): void
    {
        $linhas = ChartFormat::parse((string) file_get_contents(APPPATH . 'Database/PlanosModelo/itg-1000-advocacia.json'), ChartFormat::JSON);
        $codigos = array_column($linhas, 'code');

        $this->assertSame(count($codigos), count(array_unique($codigos)), 'Códigos repetidos no plano.');

        $resultado = (new PlanoContasModeloService())->importar(PlanoContasModeloService::MODELO_PADRAO, 1);

        $this->assertSame(count($linhas), $resultado['created']);

        $contasFolha = $this->db->table('acc_accounts')->where('accepts_entries', 1)->countAllResults();
        $this->assertGreaterThan(100, $contasFolha);

        // Contas específicas da advocacia ficam fora da árvore das contas próprias.
        $clientes = $this->db->table('acc_accounts')->where('code', '1.3.1.01.01')->get()->getRowArray();
        $this->assertSame('ASSET', $clientes['type']);
        $this->assertSame(1, (int) $clientes['is_cash_account']);
        $this->assertSame('LIABILITY', $this->db->table('acc_accounts')->where('code', '2.3.1.01.01')->get()->getRowArray()['type']);

        // O código de origem na ITG 1000 fica em reference_code.
        $this->assertSame('1.1.1.02.01', $this->db->table('acc_accounts')->where('code', '1.1.1.02.01')->get()->getRowArray()['reference_code']);
        $this->assertSame('2.3.1.01.01', $this->db->table('acc_accounts')->where('code', '3.1.01.01')->get()->getRowArray()['reference_code']);
    }

    public function testModeloPadraoVemPrimeiroNaListaDeModelos(): void
    {
        $modelos = (new PlanoContasModeloService())->listarModelos();

        $this->assertSame(PlanoContasModeloService::MODELO_PADRAO, $modelos[0]['id']);
    }

    public function testConverteMantendoLancamentosNasMesmasContas(): void
    {
        $this->criarPlanoAnterior();

        $banco   = $this->idPorCodigo('1.1.02');
        $capital = $this->idPorCodigo('3.1.01');

        (new AberturaContabilService($this->db))->abrir([
            ['code' => '1.1.02', 'side' => 'D', 'amount' => 1000],
            ['code' => '3.1.01', 'side' => 'C', 'amount' => 1000],
        ], '2026-01-01', 1);

        $linhasAntes = $this->db->table('acc_entry_lines')->countAllResults();

        $resultado = $this->service->adequar(1);

        $this->assertSame('convertido', $resultado['status']);
        $this->assertSame(count(AdequacaoPlanoItg1000Service::MAPA), $resultado['relocadas']);

        // Mesmas contas (mesmo id), código e grupo novos; lançamentos intactos.
        $contaBanco = $this->db->table('acc_accounts')->where('id', $banco)->get()->getRowArray();
        $this->assertSame('1.1.1.02.01', $contaBanco['code']);
        $this->assertSame('Banco Teste', $contaBanco['name'], 'Nome com informação do escritório é mantido.');
        $this->assertSame('1.1.1.02.01', $contaBanco['reference_code']);
        $this->assertSame($this->idPorCodigo('1.1.1.02'), (int) $contaBanco['parent_id']);
        $this->assertSame(5, (int) $contaBanco['level']);

        $contaCapital = $this->db->table('acc_accounts')->where('id', $capital)->get()->getRowArray();
        $this->assertSame('3.1.01.01', $contaCapital['code']);
        $this->assertSame('Capital Social Subscrito', $contaCapital['name'], 'Nome do plano padrão é adotado.');

        $this->assertSame($linhasAntes, $this->db->table('acc_entry_lines')->countAllResults());
        $this->assertSame(2, $this->db->table('acc_entry_lines')->whereIn('account_id', [$banco, $capital])->countAllResults());

        // Nada do plano anterior sobra e o plano fica igual ao modelo.
        $this->assertSame(0, $this->db->table('acc_accounts')->like('code', 'ANTIGA-', 'after')->countAllResults());
        $modelo = ChartFormat::parse((string) file_get_contents(APPPATH . 'Database/PlanosModelo/itg-1000-advocacia.json'), ChartFormat::JSON);
        $this->assertSame(count($modelo), $this->db->table('acc_accounts')->where('entity_id', 1)->countAllResults());
        $this->assertSame(0, $this->db->table('acc_accounts')->where('code', '1.1.02')->countAllResults());
    }

    public function testRepetirAConversaoNaoFazNada(): void
    {
        $this->criarPlanoAnterior();
        $this->service->adequar(1);

        $this->assertSame('ja_adequado', $this->service->adequar(1)['status']);
    }

    public function testSimulacaoNaoGravaNada(): void
    {
        $this->criarPlanoAnterior();
        $antes = $this->db->table('acc_accounts')->orderBy('id')->get()->getResultArray();

        $resultado = $this->service->adequar(1, true);

        $this->assertSame('simulado', $resultado['status']);
        $this->assertSame($antes, $this->db->table('acc_accounts')->orderBy('id')->get()->getResultArray());
    }

    public function testContaForaDoPlanoAnteriorInterrompeSemGravar(): void
    {
        $this->criarPlanoAnterior();
        service('contabil')->createAccount()->create(1, '5.2.99', 'Despesa criada à mão', null, $this->idPorCodigo('5.2'));
        $antes = $this->db->table('acc_accounts')->orderBy('id')->get()->getResultArray();

        try {
            $this->service->adequar(1);
            $this->fail('Conta desconhecida deveria interromper a conversão.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('5.2.99', $e->getMessage());
        }

        $this->assertSame($antes, $this->db->table('acc_accounts')->orderBy('id')->get()->getResultArray());
    }
}
