<?php

namespace Tests\Unit\Services\Privacidade;

use App\DTO\ProcessoSearchCriteria;
use App\Models\ProcessosModel;
use App\Repositories\ProcessosRepository;
use App\Repositories\ProcessStatisticsRepository;
use App\Services\AcessoEntidadeService;
use App\Services\ConfiguracoesService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Escopo de privacidade nas consultas do ProcessosRepository.
 *
 * Usuários: 1 ana (responsável pelo 1, acesso ao 2), 2 bruno (responsável por 2, 3 e 4),
 * 3 carla (sem acesso), 4 admin (bypass), 5 portal (processos.exclusive, escopo por cliente).
 * Processos: 1 Alfa restrito, 2 Beta restrito, 3 Gama público, 4 Delta restrito e arquivado.
 */
class ProcessosRepositoryPrivacidadeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use PrivacidadeSchemaTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $prefixoOriginal = '';

    protected function setUp(): void
    {
        parent::setUp();

        // O SELECT do baseSearchQuery não é escapado (processos.coluna) e não
        // funciona com o prefixo db_ da conexão de testes.
        $this->prefixoOriginal = $this->db->getPrefix();
        $this->db->setPrefix('');

        $this->criarEsquemaPrivacidade($this->db);

        $forge = Database::forge($this->db);
        $forge->dropTable('processos', true);
        $colunas = ['id_processo' => ['type' => 'INTEGER', 'auto_increment' => true]];
        foreach ([
            'siglaTribunal', 'nomeOrgao', 'numero_processo', 'titulo_processo', 'link', 'tipoDocumento', 'codigoClasse',
            'ativo', 'status', 'numeroprocessocommascara', 'risco', 'dataDistribuicao', 'valorCausa', 'resultado',
            'valorCondenacao', 'provisao_cpc25', 'dataRevisao', 'data_encerramento', 'pasta', 'synchronize_at',
            'arquived_at', 'ramo_direito', 'relatorio_final_publicado', 'created_at', 'updated_at', 'deleted_at',
        ] as $coluna) {
            $colunas[$coluna] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        $colunas += [
            'cliente_id'     => ['type' => 'INTEGER', 'null' => true],
            'responsavel_id' => ['type' => 'INTEGER', 'null' => true],
            'contrato_id'    => ['type' => 'INTEGER', 'null' => true],
            'encerrado'      => ['type' => 'INTEGER', 'default' => 0],
            'visibilidade'   => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'restrito'],
        ];
        $forge->addField($colunas);
        $forge->addPrimaryKey('id_processo');
        $forge->createTable('processos');

        $forge->dropTable('processos_movimentos', true);
        $forge->addField([
            'id'              => ['type' => 'INTEGER', 'auto_increment' => true],
            'numero_processo' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nome'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dataHora'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('processos_movimentos');

        $this->db->table('users')->insertBatch([
            ['id' => 1, 'username' => 'ana', 'active' => 1],
            ['id' => 2, 'username' => 'bruno', 'active' => 1],
            ['id' => 3, 'username' => 'carla', 'active' => 1],
            ['id' => 4, 'username' => 'admin', 'active' => 1],
            ['id' => 5, 'username' => 'portal', 'active' => 1],
        ]);
        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'numero_processo' => '111', 'titulo_processo' => 'Alfa', 'responsavel_id' => 1, 'visibilidade' => 'restrito', 'arquived_at' => null],
            ['id_processo' => 2, 'numero_processo' => '222', 'titulo_processo' => 'Beta', 'responsavel_id' => 2, 'visibilidade' => 'restrito', 'arquived_at' => null],
            ['id_processo' => 3, 'numero_processo' => '333', 'titulo_processo' => 'Gama', 'responsavel_id' => 2, 'visibilidade' => 'publico', 'arquived_at' => null],
            ['id_processo' => 4, 'numero_processo' => '444', 'titulo_processo' => 'Delta', 'responsavel_id' => 2, 'visibilidade' => 'restrito', 'arquived_at' => '2026-01-01 00:00:00'],
        ]);
        $this->db->table('acessos_entidades')->insert(['entidade_tipo' => 'processo', 'entidade_id' => 2, 'user_id' => 1]);
        $this->db->table('processos_movimentos')->insertBatch([
            ['numero_processo' => '111', 'nome' => 'Mov Alfa', 'dataHora' => '2026-09-01 10:00:00'],
            ['numero_processo' => '222', 'nome' => 'Mov Beta', 'dataHora' => '2026-09-02 10:00:00'],
            ['numero_processo' => '333', 'nome' => 'Mov Gama', 'dataHora' => '2026-09-03 10:00:00'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixoOriginal);
        parent::tearDown();
    }

    public function testListagemEContagemRespeitamAcessos(): void
    {
        $this->assertSame([1, 2, 3], $this->ids(1));
        $this->assertSame([2, 3, 4], $this->ids(2));
        $this->assertSame([3], $this->ids(3));
        $this->assertSame([1, 2, 3, 4], $this->ids(4)); // bypass
        $this->assertSame([1, 2, 3, 4], $this->ids(5)); // exclusive: escopo por cliente, não por advogado

        $this->assertSame(1, $this->repo(3)->contarProcessos($this->criteria()));
        $this->assertSame([1, 2, 3, 4], $this->ids(3, false)); // funcionalidade desligada
    }

    public function testContadoresDeStatus(): void
    {
        $this->assertSame(['ativos' => 2, 'encerrados' => 0, 'arquivados' => 1, 'total' => 3], $this->statisticsRepo(2)->getContadoresStatus());
        $this->assertSame(['ativos' => 1, 'encerrados' => 0, 'arquivados' => 0, 'total' => 1], $this->statisticsRepo(3)->getContadoresStatus());
    }

    public function testBuscaRapida(): void
    {
        $this->assertSame(['Alfa'], array_column($this->repo(1)->searchForAutocomplete('Alf'), 'nome'));
        $this->assertSame([], $this->repo(3)->searchForAutocomplete('Alf'));
    }

    public function testNavegacaoNaoPulaParaProcessoRestrito(): void
    {
        $this->assertSame(['prev' => null, 'next' => null], $this->repo(3)->getNavigationIds(3));
        $this->assertSame(['prev' => null, 'next' => 2], $this->repo(1)->getNavigationIds(3));
    }

    public function testWidgetDeUltimosMovimentos(): void
    {
        $this->assertSame(['Mov Gama'], array_column($this->statisticsRepo(3)->getUltimosMovimentosComProcesso(), 'nome'));
        $this->assertSame(['Mov Gama', 'Mov Beta', 'Mov Alfa'], array_column($this->statisticsRepo(1)->getUltimosMovimentosComProcesso(), 'nome'));
    }

    /**
     * @return list<int>
     */
    private function ids(int $userId, bool $ativa = true): array
    {
        $ids = array_map('intval', $this->repo($userId, $ativa)->getProcessosIds($this->criteria()));
        sort($ids);

        return $ids;
    }

    private function criteria(): ProcessoSearchCriteria
    {
        return new ProcessoSearchCriteria(['incluirArquivados' => null, 'paginar' => false]);
    }

    /**
     * Repositório "logado" como $userId (o escopo real usa user_id() da sessão).
     */
    private function repo(int $userId, bool $ativa = true): ProcessosRepository
    {
        $repo = new ProcessosRepository(new ProcessosModel(), $this->acessosFor($userId, $ativa));
        $repo->clearCache(); // a chave por usuário usa user_id(), que não existe no teste

        return $repo;
    }

    /**
     * Statistics repository "logged in" as $userId, sharing the same privacy stub.
     */
    private function statisticsRepo(int $userId, bool $ativa = true): ProcessStatisticsRepository
    {
        $repo = new ProcessStatisticsRepository(new ProcessosModel(), $this->acessosFor($userId, $ativa));
        $repo->clearCache();

        return $repo;
    }

    /**
     * Privacy service stub that treats $userId as the logged-in user.
     */
    private function acessosFor(int $userId, bool $ativa): AcessoEntidadeService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturn($ativa ? '1' : '0');

        $acessos = new class ($this->db, $config, $userId) extends AcessoEntidadeService {
            public function __construct($db, $config, private int $logado)
            {
                parent::__construct($db, $config);
            }

            public function aplicarEscopo($builder, string $tipo, ?string $alias = null, ?int $userId = null)
            {
                return parent::aplicarEscopo($builder, $tipo, $alias, $userId ?? $this->logado);
            }

            public function ignoraEscopo(?int $userId = null, ?string $tipo = null): bool
            {
                return parent::ignoraEscopo($userId ?? $this->logado, $tipo);
            }

            protected function usuarioPode(int $userId, string $permissao): bool
            {
                $permissoes = [
                    'privacidade.bypass'  => [4],
                    'processos.access'    => [1, 2, 3, 4],
                    'processos.exclusive' => [5],
                ];

                return in_array($userId, $permissoes[$permissao] ?? [], true);
            }
        };

        return $acessos;
    }
}
