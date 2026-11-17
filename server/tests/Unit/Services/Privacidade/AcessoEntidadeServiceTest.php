<?php

namespace Tests\Unit\Services\Privacidade;

use App\Services\AcessoEntidadeService;
use App\Services\ConfiguracoesService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Usuários do cenário:
 *  1 ana    – responsável pelo processo 1; acesso explícito ao processo 2
 *  2 bruno  – responsável pelos processos 2 e 3
 *  3 carla  – nenhum acesso
 *  4 admin  – privacidade.bypass
 *  5 portal – grupo cliente (não é advogado)
 *  6 inativo
 *
 * Processos: 1 restrito (ana), 2 restrito (bruno), 3 público (bruno), 4 restrito sem responsável.
 */
class AcessoEntidadeServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use PrivacidadeSchemaTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarEsquemaPrivacidade($this->db);

        $this->db->table('users')->insertBatch([
            ['id' => 1, 'username' => 'ana', 'active' => 1],
            ['id' => 2, 'username' => 'bruno', 'active' => 1],
            ['id' => 3, 'username' => 'carla', 'active' => 1],
            ['id' => 4, 'username' => 'admin', 'active' => 1],
            ['id' => 5, 'username' => 'portal', 'active' => 1],
            ['id' => 6, 'username' => 'inativo', 'active' => 0],
        ]);
        $this->db->table('auth_groups_users')->insert(['user_id' => 5, 'group' => 'cliente']);

        $this->db->table('clientes')->insertBatch([
            ['id_cliente' => 10, 'nome' => 'Cliente X', 'responsavel_id' => 2, 'visibilidade' => 'restrito'],
        ]);
        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'cliente_id' => 10, 'responsavel_id' => 1, 'visibilidade' => 'restrito'],
            ['id_processo' => 2, 'cliente_id' => 10, 'responsavel_id' => 2, 'visibilidade' => 'restrito'],
            ['id_processo' => 3, 'cliente_id' => null, 'responsavel_id' => 2, 'visibilidade' => 'publico'],
            ['id_processo' => 4, 'cliente_id' => null, 'responsavel_id' => null, 'visibilidade' => 'restrito'],
        ]);
        $this->db->table('contratos')->insert(['id_contrato' => 20, 'cliente_id' => 10, 'visibilidade' => 'restrito']);
        $this->db->table('acessos_entidades')->insert([
            'entidade_tipo' => 'processo', 'entidade_id' => 2, 'user_id' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testDesligadaNaoFiltra(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->processosVisiveis($this->servico(false), 3));
    }

    public function testResponsavelEAcessoExplicito(): void
    {
        $this->assertSame([1, 2, 3], $this->processosVisiveis($this->servico(true), 1));
        $this->assertSame([2, 3], $this->processosVisiveis($this->servico(true), 2));
    }

    public function testSemAcessoVeApenasPublicos(): void
    {
        $this->assertSame([3], $this->processosVisiveis($this->servico(true), 3));
    }

    public function testBypassVeTudo(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->processosVisiveis($this->servico(true), 4));
    }

    public function testEscopoComAliasEJoin(): void
    {
        $builder = $this->db->table('processos p')
            ->select('p.id_processo')
            ->join('clientes c', 'c.id_cliente = p.cliente_id', 'left')
            ->orderBy('p.id_processo');

        $this->servico(true)->aplicarEscopo($builder, 'processo', 'p', 3);

        $this->assertSame([3], array_map('intval', array_column($builder->get()->getResultArray(), 'id_processo')));
    }

    public function testPodeAcessar(): void
    {
        $servico = $this->servico(true);

        $this->assertTrue($servico->podeAcessar('processo', 2, 1));
        $this->assertFalse($servico->podeAcessar('processo', 1, 3));
        $this->assertFalse($servico->podeAcessar('processo', 999, 1));
        $this->assertTrue($servico->podeAcessar('cliente', 10, 2));
        $this->assertFalse($servico->podeAcessar('contrato', 20, 1));
    }

    public function testTipoInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->servico(true)->podeAcessar('caso', 1, 1);
    }

    public function testPodeGerenciar(): void
    {
        $servico = $this->servico(true, ['privacidade.manage' => [3]]);

        $this->assertTrue($servico->podeGerenciar('processo', 1, 1));  // responsável
        $this->assertFalse($servico->podeGerenciar('processo', 2, 1)); // só tem acesso
        $this->assertTrue($servico->podeGerenciar('processo', 2, 3));  // privacidade.manage
        $this->assertTrue($servico->podeGerenciar('processo', 2, 4));  // bypass
    }

    public function testConcederEhIdempotenteERevogar(): void
    {
        $servico = $this->servico(true);

        $this->assertTrue($servico->conceder('processo', 1, 3, 1));
        $this->assertFalse($servico->conceder('processo', 1, 3, 1));
        $this->assertTrue($servico->podeAcessar('processo', 1, 3));
        $this->seeNumRecords(1, 'acessos_entidades', ['entidade_tipo' => 'processo', 'entidade_id' => 1, 'user_id' => 3]);

        $this->assertTrue($servico->revogar('processo', 1, 3));
        $this->assertFalse($servico->revogar('processo', 1, 3));
        $this->assertFalse($servico->podeAcessar('processo', 1, 3));
    }

    public function testDefinirVisibilidade(): void
    {
        $servico = $this->servico(true);

        $servico->definirVisibilidade('processo', 4, 'publico');
        $this->assertTrue($servico->podeAcessar('processo', 4, 3));

        $this->expectException(\InvalidArgumentException::class);
        $servico->definirVisibilidade('processo', 4, 'secreto');
    }

    public function testListarAcessos(): void
    {
        $acessos = $this->servico(true)->listarAcessos('processo', 2);

        $this->assertSame('restrito', $acessos['visibilidade']);
        $this->assertSame(['user_id' => 2, 'username' => 'bruno'], $acessos['responsavel']);
        $this->assertSame(['ana'], array_column($acessos['usuarios'], 'username'));
        $this->assertNull($this->servico(true)->listarAcessos('processo', 999));
    }

    public function testPropagarDoCliente(): void
    {
        $servico = $this->servico(true);
        $servico->conceder('cliente', 10, 3);
        $servico->definirVisibilidade('cliente', 10, 'publico');

        $afetados = $servico->propagarDoCliente(10, 4, true);

        $this->assertSame(['processos' => 2, 'contratos' => 1], $afetados);
        // carla (acesso ao cliente) e bruno (responsável do cliente) passam a ter acesso aos processos/contratos do cliente
        foreach ([['processo', 1], ['processo', 2], ['contrato', 20]] as [$tipo, $id]) {
            $this->seeInDatabase('acessos_entidades', ['entidade_tipo' => $tipo, 'entidade_id' => $id, 'user_id' => 3]);
        }
        $this->seeInDatabase('acessos_entidades', ['entidade_tipo' => 'contrato', 'entidade_id' => 20, 'user_id' => 2]);
        $this->seeInDatabase('processos', ['id_processo' => 1, 'visibilidade' => 'publico']);
        // processos de outros clientes não são afetados
        $this->seeInDatabase('processos', ['id_processo' => 4, 'visibilidade' => 'restrito']);
    }

    public function testAdvogados(): void
    {
        $servico = $this->servico(true);

        $this->assertTrue($servico->ehAdvogadoAtivo(1));
        $this->assertFalse($servico->ehAdvogadoAtivo(5)); // grupo cliente
        $this->assertFalse($servico->ehAdvogadoAtivo(6)); // inativo
        $this->assertSame(['admin', 'ana', 'bruno', 'carla'], array_column($servico->buscarAdvogados(), 'username'));
        $this->assertSame(['bruno'], array_column($servico->buscarAdvogados('bru'), 'username'));
    }

    public function testCondicaoSqlParaConsultasManuais(): void
    {
        $this->assertNull($this->servico(false)->condicaoSql('processo', 'p', 3));
        $this->assertNull($this->servico(true)->condicaoSql('processo', 'p', 4)); // bypass

        $condicao = $this->servico(true)->condicaoSql('processo', 'p', 1);
        $tabela   = $this->db->prefixTable('processos');
        $ids      = array_map('intval', array_column(
            $this->db->query("SELECT p.id_processo FROM {$tabela} p WHERE {$condicao} ORDER BY p.id_processo")->getResultArray(),
            'id_processo'
        ));

        $this->assertSame([1, 2, 3], $ids);
    }

    public function testProcessosOcultosIncluiProcessoSemResponsavel(): void
    {
        // Processo 4 tem responsavel_id NULL: a condição vale NULL e precisa contar como oculto.
        $this->assertSame([1, 2, 4], array_keys($this->servico(true)->processosOcultos(3)['ids']));
        $this->assertSame([4], array_keys($this->servico(true)->processosOcultos(1)['ids']));
        $this->assertNull($this->servico(true)->processosOcultos(4));
        $this->assertNull($this->servico(false)->processosOcultos(3));
    }

    public function testUsuarioExclusiveNaoPassaPeloEscopoDoModulo(): void
    {
        // processos.exclusive sem processos.access: já é restrito ao próprio cliente
        $servico = $this->servico(true, ['processos.exclusive' => [3]]);

        $this->assertTrue($servico->ignoraEscopo(3, 'processo'));
        $this->assertFalse($servico->ignoraEscopo(3, 'cliente'));
        $this->assertSame([1, 2, 3, 4], $this->processosVisiveis($servico, 3));
    }

    public function testResponsavelPadraoDoSistema(): void
    {
        foreach (['1' => 1, '5' => null, '6' => null, '' => null, 'abc' => null] as $valor => $esperado) {
            $config = $this->createMock(ConfiguracoesService::class);
            $config->method('getValor')->willReturnCallback(
                static fn (string $nome) => $nome === AcessoEntidadeService::CONFIG_RESPONSAVEL_PADRAO ? (string) $valor : '1'
            );

            $this->assertSame($esperado, (new AcessoEntidadeService($this->db, $config))->responsavelPadraoDoSistema(), "valor '{$valor}'");
        }
    }

    /**
     * @param array<string, list<int>> $permissoes permissão => usuários que a possuem (além do bypass do admin)
     */
    private function servico(bool $ativa, array $permissoes = []): AcessoEntidadeService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturn($ativa ? '1' : '0');

        $permissoes['privacidade.bypass'] = array_merge($permissoes['privacidade.bypass'] ?? [], [4]);

        return new class ($this->db, $config, $permissoes) extends AcessoEntidadeService {
            public function __construct($db, $config, private array $permissoes)
            {
                parent::__construct($db, $config);
            }

            protected function usuarioPode(int $userId, string $permissao): bool
            {
                return in_array($userId, $this->permissoes[$permissao] ?? [], true);
            }
        };
    }

    /**
     * @return list<int>
     */
    private function processosVisiveis(AcessoEntidadeService $servico, int $userId): array
    {
        $builder = $this->db->table('processos')->select('id_processo')->orderBy('id_processo');
        $servico->aplicarEscopo($builder, 'processo', null, $userId);

        return array_map('intval', array_column($builder->get()->getResultArray(), 'id_processo'));
    }
}
