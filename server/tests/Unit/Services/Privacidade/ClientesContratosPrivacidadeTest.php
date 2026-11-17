<?php

namespace Tests\Unit\Services\Privacidade;

use App\Models\ClientesModel;
use App\Models\ContratosModel;
use App\Repositories\ClienteRepository;
use App\Repositories\ContratosRepository;
use App\Services\AcessoEntidadeService;
use App\Services\ConfiguracoesService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Escopo de privacidade em ClienteRepository e ContratosRepository.
 *
 * Usuários: 1 ana, 2 bruno, 3 carla (sem nada), 4 admin (bypass).
 * Clientes: 10 Acme (resp. ana, restrito), 11 Beta (resp. bruno, restrito, acesso da ana), 12 Gama (público).
 * Contratos: 20 (cliente 10, resp. ana), 21 (cliente 11, resp. bruno), 22 (cliente 12, público).
 * Processos do cliente 10: 1 (resp. ana), 2 (resp. bruno, restrito) — ana vê o cliente, mas não o processo 2.
 */
class ClientesContratosPrivacidadeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use PrivacidadeSchemaTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $prefixoOriginal = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Consultas manuais dos repositórios não usam prefixo de tabela.
        $this->prefixoOriginal = $this->db->getPrefix();
        $this->db->setPrefix('');

        $this->criarEsquemaPrivacidade($this->db);
        $forge = Database::forge($this->db);

        $tabelas = [
            'clientes' => [
                'id_cliente' => 'INTEGER', 'nome' => 'TEXT', 'razao_social' => 'TEXT', 'documento' => 'TEXT', 'email' => 'TEXT',
                'tipo_cliente' => 'TEXT', 'ativo' => 'INTEGER', 'data_nascimento' => 'TEXT', 'status_crm' => 'TEXT',
                'usuario_id' => 'INTEGER', 'responsavel_id' => 'INTEGER', 'visibilidade' => 'TEXT',
                'created_at' => 'TEXT', 'updated_at' => 'TEXT', 'deleted_at' => 'TEXT',
            ],
            'contratos' => [
                'id_contrato' => 'INTEGER', 'numero_contrato' => 'TEXT', 'cliente_id' => 'INTEGER', 'objeto' => 'TEXT',
                'area_atuacao' => 'TEXT', 'status' => 'TEXT', 'data_inicio' => 'TEXT', 'data_fim' => 'TEXT', 'valor' => 'REAL',
                'responsavel_id' => 'INTEGER', 'visibilidade' => 'TEXT',
                'created_at' => 'TEXT', 'updated_at' => 'TEXT', 'deleted_at' => 'TEXT',
            ],
            'processos' => [
                'id_processo' => 'INTEGER', 'titulo_processo' => 'TEXT', 'numeroprocessocommascara' => 'TEXT', 'ativo' => 'INTEGER',
                'cliente_id' => 'INTEGER', 'contrato_id' => 'INTEGER', 'responsavel_id' => 'INTEGER', 'visibilidade' => 'TEXT',
                'arquived_at' => 'TEXT', 'deleted_at' => 'TEXT',
            ],
            'juris_ramos_direito' => ['id' => 'INTEGER', 'nome' => 'TEXT', 'pai_id' => 'INTEGER'],
            'fin_receitas'        => ['id_receita' => 'INTEGER', 'receita' => 'TEXT', 'vencimento_dt' => 'TEXT', 'contrato_id' => 'INTEGER', 'valor' => 'REAL', 'deleted_at' => 'TEXT'],
            'fin_despesas'        => ['id_despesa' => 'INTEGER', 'despesa' => 'TEXT', 'vencimento_dt' => 'TEXT', 'contrato_id' => 'INTEGER', 'valor' => 'REAL', 'deleted_at' => 'TEXT'],
            'fin_pgto_receitas'   => ['id_pgto_receita' => 'INTEGER', 'receita_id' => 'INTEGER', 'deleted_at' => 'TEXT'],
            'fin_pgto_despesas'   => ['id_pgto_despesa' => 'INTEGER', 'despesa_id' => 'INTEGER', 'deleted_at' => 'TEXT'],
            'casos'               => ['id_caso' => 'INTEGER', 'titulo' => 'TEXT', 'contrato_id' => 'INTEGER', 'deleted_at' => 'TEXT'],
        ];
        foreach ($tabelas as $tabela => $colunas) {
            $forge->dropTable($tabela, true);
            $campos = [];
            foreach ($colunas as $nome => $tipo) {
                $campos[$nome] = ['type' => $tipo, 'null' => true];
            }
            $forge->addField($campos);
            $forge->addPrimaryKey(array_key_first($colunas));
            $forge->createTable($tabela);
        }

        $this->db->table('users')->insertBatch([
            ['id' => 1, 'username' => 'ana', 'active' => 1],
            ['id' => 2, 'username' => 'bruno', 'active' => 1],
            ['id' => 3, 'username' => 'carla', 'active' => 1],
            ['id' => 4, 'username' => 'admin', 'active' => 1],
        ]);
        $this->db->table('clientes')->insertBatch([
            ['id_cliente' => 10, 'nome' => 'Acme', 'ativo' => 1, 'responsavel_id' => 1, 'visibilidade' => 'restrito'],
            ['id_cliente' => 11, 'nome' => 'Beta', 'ativo' => 1, 'responsavel_id' => 2, 'visibilidade' => 'restrito'],
            ['id_cliente' => 12, 'nome' => 'Gama', 'ativo' => 1, 'responsavel_id' => 2, 'visibilidade' => 'publico'],
        ]);
        $this->db->table('contratos')->insertBatch([
            ['id_contrato' => 20, 'numero_contrato' => 'C-20', 'cliente_id' => 10, 'status' => 'Ativo', 'data_fim' => '2030-01-01', 'valor' => 100, 'responsavel_id' => 1, 'visibilidade' => 'restrito'],
            ['id_contrato' => 21, 'numero_contrato' => 'C-21', 'cliente_id' => 11, 'status' => 'Ativo', 'data_fim' => '2030-01-01', 'valor' => 200, 'responsavel_id' => 2, 'visibilidade' => 'restrito'],
            ['id_contrato' => 22, 'numero_contrato' => 'C-22', 'cliente_id' => 12, 'status' => 'Ativo', 'data_fim' => '2030-01-01', 'valor' => 300, 'responsavel_id' => 2, 'visibilidade' => 'publico'],
        ]);
        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'titulo_processo' => 'P1', 'cliente_id' => 10, 'contrato_id' => 20, 'responsavel_id' => 1, 'visibilidade' => 'restrito'],
            ['id_processo' => 2, 'titulo_processo' => 'P2', 'cliente_id' => 10, 'contrato_id' => 20, 'responsavel_id' => 2, 'visibilidade' => 'restrito'],
        ]);
        $this->db->table('acessos_entidades')->insert(['entidade_tipo' => 'cliente', 'entidade_id' => 11, 'user_id' => 1]);
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixoOriginal);
        parent::tearDown();
    }

    public function testListagemEBuscaDeClientes(): void
    {
        $this->assertSame(['Acme', 'Beta', 'Gama'], $this->nomes($this->clientes(1)->buscarClientes()['clientes']));
        $this->assertSame(['Gama'], $this->nomes($this->clientes(3)->buscarClientes()['clientes']));
        $this->assertSame(['Acme', 'Beta', 'Gama'], $this->nomes($this->clientes(4)->buscarClientes()['clientes']));

        $this->assertSame(['Beta'], $this->nomes($this->clientes(1)->search('Bet')));
        $this->assertSame([], $this->nomes($this->clientes(3)->search('Bet')));

        $this->assertSame(1, $this->clientes(3)->buscarEstatisticas()['total']);
    }

    public function testFichaDoClienteRespeitaPrivacidadeDosProcessos(): void
    {
        // ana vê o cliente 10, mas o processo 2 é do bruno e está restrito.
        $this->assertSame(['P1'], array_column($this->clientes(1)->buscarProcessosCliente(10), 'titulo_processo'));
    }

    public function testContratos(): void
    {
        $this->assertSame(['C-20', 'C-21', 'C-22'], $this->numeros($this->contratos(2, true)->buscarTodos(['ordenar' => 'antigo'])));
        $this->assertSame(['C-21', 'C-22'], $this->numeros($this->contratos(2)->buscarTodos(['ordenar' => 'antigo'])));
        $this->assertSame(['C-22'], $this->numeros($this->contratos(3)->buscarTodos()));

        $this->assertSame(2, $this->contratos(2)->getEstatisticas()['total']);
        $this->assertSame(['C-22'], $this->numeros($this->contratos(3)->buscarPorTermo('C-')));
        $this->assertSame([22], array_map('intval', array_column($this->contratos(3)->getContratosProximosVencimento(), 'id_contrato')));
        $this->assertSame([22], array_map('intval', array_column($this->contratos(3)->getContratosRentabilidade(), 'id_contrato')));
    }

    public function testDetalheDoContratoTrazResponsavelEFiltraProcessos(): void
    {
        $contrato = $this->contratos(1)->findById(20);

        $this->assertSame('ana', $contrato['responsavel_nome']);
        $this->assertSame(['P1'], array_column($contrato['processos'], 'titulo_processo'));
    }

    private function nomes(array $linhas): array
    {
        $nomes = array_column($linhas, 'nome');
        sort($nomes);

        return $nomes;
    }

    private function numeros(array $linhas): array
    {
        $numeros = array_column($linhas, 'numero_contrato');
        sort($numeros);

        return $numeros;
    }

    private function clientes(int $userId): ClienteRepository
    {
        $repo = new ClienteRepository(new ClientesModel(), $this->acessos($userId));
        $repo->clearCache(); // a chave por usuário usa user_id(), que não existe no teste

        return $repo;
    }

    private function contratos(int $userId, bool $bypass = false): ContratosRepository
    {
        return new ContratosRepository(new ContratosModel(), $this->acessos($bypass ? 4 : $userId));
    }

    private function acessos(int $userId): AcessoEntidadeService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturn('1');

        return new class ($this->db, $config, $userId) extends AcessoEntidadeService {
            public function __construct($db, $config, private int $logado)
            {
                parent::__construct($db, $config);
            }

            public function aplicarEscopo($builder, string $tipo, ?string $alias = null, ?int $userId = null)
            {
                return parent::aplicarEscopo($builder, $tipo, $alias, $userId ?? $this->logado);
            }

            public function condicaoSql(string $tipo, string $alias, ?int $userId = null): ?string
            {
                return parent::condicaoSql($tipo, $alias, $userId ?? $this->logado);
            }

            public function ignoraEscopo(?int $userId = null, ?string $tipo = null): bool
            {
                return parent::ignoraEscopo($userId ?? $this->logado, $tipo);
            }

            protected function usuarioPode(int $userId, string $permissao): bool
            {
                return $permissao === 'privacidade.bypass' ? $userId === 4 : str_ends_with($permissao, '.access');
            }
        };
    }
}
