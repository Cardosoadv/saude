<?php

namespace Tests\Unit\Services\Privacidade;

use App\Services\PrivacidadeBackfillService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class PrivacidadeBackfillServiceTest extends CIUnitTestCase
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
            ['id' => 1, 'username' => 'ana'],
            ['id' => 2, 'username' => 'bruno'],
            ['id' => 3, 'username' => 'carla'],
        ]);
        $this->db->table('auth_identities')->insert(['user_id' => 3, 'type' => 'email_password', 'secret' => 'carla@escritorio.adv.br']);

        $this->db->table('clientes')->insertBatch([
            ['id_cliente' => 10, 'nome' => 'Mais frequente', 'status_crm' => 'client', 'responsavel_id' => null],
            ['id_cliente' => 11, 'nome' => 'Empate', 'status_crm' => 'client', 'responsavel_id' => null],
            ['id_cliente' => 12, 'nome' => 'Só encerrados', 'status_crm' => 'client', 'responsavel_id' => null],
            ['id_cliente' => 13, 'nome' => 'Lead sem vínculos', 'status_crm' => 'lead', 'responsavel_id' => null],
            ['id_cliente' => 14, 'nome' => 'Já tem responsável', 'status_crm' => 'client', 'responsavel_id' => 3],
            ['id_cliente' => 15, 'nome' => 'Só contrato', 'status_crm' => 'client', 'responsavel_id' => null],
        ]);

        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'cliente_id' => 10, 'responsavel_id' => 2, 'encerrado' => 0],
            ['id_processo' => 2, 'cliente_id' => 10, 'responsavel_id' => 2, 'encerrado' => 0],
            ['id_processo' => 3, 'cliente_id' => 10, 'responsavel_id' => 1, 'encerrado' => 0],
            ['id_processo' => 4, 'cliente_id' => 11, 'responsavel_id' => 1, 'encerrado' => 0],
            ['id_processo' => 5, 'cliente_id' => 11, 'responsavel_id' => 2, 'encerrado' => 0],
            ['id_processo' => 6, 'cliente_id' => 12, 'responsavel_id' => 1, 'encerrado' => 1],
            ['id_processo' => 7, 'cliente_id' => 14, 'responsavel_id' => 1, 'encerrado' => 0],
            ['id_processo' => 8, 'cliente_id' => null, 'responsavel_id' => null, 'encerrado' => 0],
        ]);

        $this->db->table('contratos')->insertBatch([
            ['id_contrato' => 20, 'numero_contrato' => 'C-20', 'cliente_id' => 10, 'responsavel_interno' => ' ANA '],
            ['id_contrato' => 21, 'numero_contrato' => 'C-21', 'cliente_id' => 11, 'responsavel_interno' => 'carla@escritorio.adv.br'],
            ['id_contrato' => 22, 'numero_contrato' => 'C-22', 'cliente_id' => 12, 'responsavel_interno' => 'Dr. Fulano'],
            ['id_contrato' => 23, 'numero_contrato' => 'C-23', 'cliente_id' => 12, 'responsavel_interno' => null],
            ['id_contrato' => 24, 'numero_contrato' => 'C-24', 'cliente_id' => 15, 'responsavel_interno' => 'bruno'],
        ]);
    }

    public function testSimulacaoNaoGrava(): void
    {
        $resultado = (new PrivacidadeBackfillService($this->db))->executar(true);

        $this->assertSame(3, $resultado['contratos_preenchidos']);
        $this->assertSame(4, $resultado['clientes_preenchidos']);
        $this->assertSame(4, $resultado['acessos_concedidos']);
        $this->dontSeeInDatabase('contratos', ['id_contrato' => 20, 'responsavel_id' => 1]);
        $this->dontSeeInDatabase('clientes', ['id_cliente' => 10, 'responsavel_id' => 2]);
        $this->seeNumRecords(0, 'acessos_entidades', []);
    }

    public function testExecucao(): void
    {
        $resultado = (new PrivacidadeBackfillService($this->db))->executar();

        // Contratos: username (sem diferenciar maiúsculas/espaços) e e-mail
        $this->seeInDatabase('contratos', ['id_contrato' => 20, 'responsavel_id' => 1]);
        $this->seeInDatabase('contratos', ['id_contrato' => 21, 'responsavel_id' => 3]);
        $this->seeInDatabase('contratos', ['id_contrato' => 24, 'responsavel_id' => 2]);
        $this->seeInDatabase('contratos', ['id_contrato' => 22, 'responsavel_id' => null]);

        // Clientes
        $this->seeInDatabase('clientes', ['id_cliente' => 10, 'responsavel_id' => 2]); // mais frequente
        $this->seeInDatabase('clientes', ['id_cliente' => 11, 'responsavel_id' => 2]); // empate → processo mais recente
        $this->seeInDatabase('clientes', ['id_cliente' => 12, 'responsavel_id' => 1]); // sem ativos/contrato → qualquer processo
        $this->seeInDatabase('clientes', ['id_cliente' => 15, 'responsavel_id' => 2]); // contrato recém-preenchido
        $this->seeInDatabase('clientes', ['id_cliente' => 14, 'responsavel_id' => 3]); // preservado
        $this->seeInDatabase('clientes', ['id_cliente' => 13, 'responsavel_id' => null]);

        // Demais responsáveis recebem acesso ao cliente
        $acessos = $this->db->table('acessos_entidades')->select('entidade_id, user_id')
            ->where('entidade_tipo', 'cliente')->orderBy('entidade_id')->orderBy('user_id')->get()->getResultArray();
        $this->assertSame(
            [[10, 1], [11, 1], [11, 3], [14, 1]],
            array_map(static fn ($a) => [(int) $a['entidade_id'], (int) $a['user_id']], $acessos)
        );
        $this->assertSame(4, $resultado['acessos_concedidos']);

        // Pendências para revisão manual
        $pendencias = array_map(static fn ($p) => $p['tipo'] . ':' . $p['id'], $resultado['pendencias']);
        sort($pendencias);
        $this->assertSame(['cliente:13', 'contrato:22', 'contrato:23', 'processo:8'], $pendencias);
    }

    public function testResponsavelPadraoAssumePendencias(): void
    {
        $resultado = (new PrivacidadeBackfillService($this->db))->executar(false, 3);

        $this->assertSame(4, $resultado['atribuidos_padrao']);
        $this->seeInDatabase('processos', ['id_processo' => 8, 'responsavel_id' => 3]);
        $this->seeInDatabase('clientes', ['id_cliente' => 13, 'responsavel_id' => 3]);
        $this->seeInDatabase('contratos', ['id_contrato' => 22, 'responsavel_id' => 3]); // texto não reconhecido
        $this->seeInDatabase('contratos', ['id_contrato' => 23, 'responsavel_id' => 3]); // sem responsável

        // Heurísticas continuam valendo e têm prioridade sobre o padrão
        $this->seeInDatabase('clientes', ['id_cliente' => 10, 'responsavel_id' => 2]);
        $this->seeInDatabase('contratos', ['id_contrato' => 20, 'responsavel_id' => 1]);

        // Relatório lista o que foi atribuído ao padrão, para redistribuição
        $this->assertCount(4, $resultado['pendencias']);
        foreach ($resultado['pendencias'] as $p) {
            $this->assertStringStartsWith('atribuído ao responsável padrão #3', $p['motivo']);
        }

        // Nada mais a atribuir numa segunda execução
        $this->assertSame(0, (new PrivacidadeBackfillService($this->db))->executar(false, 3)['atribuidos_padrao']);
    }

    public function testResponsavelPadraoEmSimulacaoNaoGrava(): void
    {
        $resultado = (new PrivacidadeBackfillService($this->db))->executar(true, 3);

        $this->assertSame(4, $resultado['atribuidos_padrao']);
        $this->seeInDatabase('processos', ['id_processo' => 8, 'responsavel_id' => null]);
    }

    public function testResponsavelPadraoPrecisaSerAdvogadoAtivo(): void
    {
        $this->db->table('users')->insert(['id' => 9, 'username' => 'portal']);
        $this->db->table('auth_groups_users')->insert(['user_id' => 9, 'group' => 'cliente']);

        $this->expectException(\InvalidArgumentException::class);
        (new PrivacidadeBackfillService($this->db))->executar(false, 9);
    }

    public function testIdempotente(): void
    {
        $service = new PrivacidadeBackfillService($this->db);
        $service->executar();
        $segunda = $service->executar();

        $this->assertSame(0, $segunda['contratos_preenchidos']);
        $this->assertSame(0, $segunda['clientes_preenchidos']);
        $this->assertSame(0, $segunda['acessos_concedidos']);
        $this->seeNumRecords(4, 'acessos_entidades', []);
    }
}
