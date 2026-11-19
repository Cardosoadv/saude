<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Database\Migrations\CreatePushNotificacoes;
use App\Libraries\WebPush\ChavesEc;
use App\Libraries\WebPush\Enviador;
use App\Libraries\WebPush\Vapid;
use App\Repositories\PushRepository;
use App\Services\PushNotificacaoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Push;
use Tests\Support\WebPush\NavegadorFalso;

/**
 * Disparo das notificações push: quem recebe, o que recebe, horário e "não repetir".
 */
final class PushNotificacaoServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    /** 2026-11-03 10:00 (terça) no fuso da aplicação. */
    private int $agora;

    private PushRepository $repo;
    private Push $config;

    /** @var array{publica: string, privada: string} */
    private array $parVapid;

    /** @var array<int, array{status: int}> respostas do serviço de push por endpoint */
    private array $respostas = [];

    /** @var list<array{url: string, cabecalhos: array, corpo: string}> */
    private array $enviados = [];

    /** @var array<int, list<string>> módulos que cada usuário pode acessar */
    private array $acessos = [];

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/Users/2026-11-02-120000_CreatePushNotificacoes.php';

        $forge = Database::forge($this->db);
        foreach (['push_envios', 'push_inscricoes', 'tarefas'] as $tabela) {
            $forge->dropTable($tabela, true);
        }

        (new CreatePushNotificacoes(Database::forge($this->db)))->up();

        $forge->addField([
            'id_tarefa'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tarefa'      => ['type' => 'TEXT', 'null' => true],
            'prazo'       => ['type' => 'DATE', 'null' => true],
            'responsavel' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_tarefa');
        $forge->createTable('tarefas');

        $this->agora = (int) strtotime('2026-11-03 10:00:00');
        $this->repo  = new PushRepository($this->db);

        $par          = ChavesEc::gerar();
        $this->config = new Push();
        $this->config->vapidPublicKey  = 'B' . str_repeat('A', 86);
        $this->config->vapidPrivateKey = 'chave';
        $this->config->horaInicio      = 7;
        $this->config->horaFim         = 22;
        $this->parVapid                = $par;

        $this->acessos   = [1 => ['tarefas'], 2 => ['tarefas'], 3 => ['tarefas']];
        $this->respostas = [];
        $this->enviados  = [];
    }

    private function servico(?int $agora = null): PushNotificacaoService
    {
        $par      = $this->parVapid;
        $enviador = new Enviador(
            new Vapid($par['publica'], $par['privada'], 'mailto:ti@exemplo.com.br'),
            function (string $url, array $cabecalhos, string $corpo): int {
                $this->enviados[] = compact('url', 'cabecalhos', 'corpo');

                return $this->respostas[$url] ?? 201;
            }
        );

        return new PushNotificacaoService(
            $this->repo,
            $this->config,
            $enviador,
            fn (int $userId, string $modulo): bool => in_array($modulo, $this->acessos[$userId] ?? [], true),
            fn (): int => $agora ?? $this->agora
        );
    }

    /** Inscreve um aparelho do usuário "antes" dos avisos de teste chegarem. */
    private function inscrever(int $userId, array $preferencias = [], string $quando = '2026-11-01 08:00:00'): NavegadorFalso
    {
        $navegador = new NavegadorFalso('https://push.exemplo.com/envio/' . bin2hex(random_bytes(6)));
        $keys      = $navegador->inscricao()['keys'];
        $linha     = $this->repo->salvarInscricao($userId, ['endpoint' => $navegador->endpoint] + $keys, $preferencias, 'Teste');
        $this->db->table('push_inscricoes')->where('id', $linha['id'])->update(['created_at' => $quando]);

        return $navegador;
    }

    /** @return list<array<string, mixed>> mensagens decifradas recebidas pelo aparelho */
    private function recebidas(NavegadorFalso $navegador): array
    {
        $mensagens = [];
        foreach ($this->enviados as $envio) {
            if ($envio['url'] === $navegador->endpoint) {
                $mensagens[] = json_decode($navegador->decifrar($envio['corpo']), true);
            }
        }

        return $mensagens;
    }


    public function testSemChavesNaoFazNada(): void
    {
        $this->config->vapidPublicKey = '';
        $r = $this->servico()->processar();

        $this->assertFalse($r['executado']);
        $this->assertStringContainsString('VAPID', $r['motivo']);
    }

    public function testForaDoHorarioNaoEnviaEDepoisEnviaOPendente(): void
    {
        $celular = $this->inscrever(1);
        $this->db->table('tarefas')->insert(['id_tarefa' => 1, 'tarefa' => 'Retorno', 'prazo' => '2026-11-03', 'responsavel' => 1, 'status' => 1]);

        $r = $this->servico((int) strtotime('2026-11-02 23:45:00'))->processar();
        $this->assertFalse($r['executado']);
        $this->assertSame([], $this->enviados);

        $r = $this->servico((int) strtotime('2026-11-03 07:05:00'))->processar();
        $this->assertTrue($r['executado']);
        $this->assertCount(1, $this->recebidas($celular));
    }





    public function testPrazosDeHojeEAmanhaParaOResponsavel(): void
    {
        $celular = $this->inscrever(1);
        $outro   = $this->inscrever(2);
        $this->db->table('tarefas')->insertBatch([
            ['id_tarefa' => 1, 'tarefa' => 'Retorno do paciente', 'prazo' => '2026-11-03', 'responsavel' => 1, 'status' => 1],
            ['id_tarefa' => 2, 'tarefa' => 'Exame', 'prazo' => '2026-11-04', 'responsavel' => 1, 'status' => 2],
            ['id_tarefa' => 3, 'tarefa' => 'Concluída', 'prazo' => '2026-11-03', 'responsavel' => 1, 'status' => 4],
            ['id_tarefa' => 4, 'tarefa' => 'Semana que vem', 'prazo' => '2026-11-10', 'responsavel' => 1, 'status' => 1],
            ['id_tarefa' => 5, 'tarefa' => 'Já venceu', 'prazo' => '2026-11-02', 'responsavel' => 1, 'status' => 1],
        ]);

        $r = $this->servico()->processar();

        $this->assertSame(2, $r['prazos']);
        [$msg] = $this->recebidas($celular);
        $this->assertSame('2 prazos próximos (1 hoje)', $msg['title']);
        $this->assertSame('Retorno do paciente; Exame', $msg['body']);
        $this->assertSame('#/tarefas', $msg['url']);
        $this->assertSame('high', $this->enviados[0]['cabecalhos']['Urgency']);
        $this->assertSame([], $this->recebidas($outro), 'só o responsável recebe');

        // Não repete; se o prazo mudar, avisa de novo.
        $this->servico()->processar();
        $this->assertCount(1, $this->recebidas($celular));
        $this->db->table('tarefas')->where('id_tarefa', 2)->update(['prazo' => '2026-11-03']);
        $this->servico()->processar();
        $mensagens = $this->recebidas($celular);
        $this->assertCount(2, $mensagens);
        $this->assertSame('Prazo hoje', $mensagens[1]['title']);
        $this->assertSame('Exame', $mensagens[1]['body']);
    }


    public function testInscricaoExpiradaEApagadaEFalhaTemporariaRepete(): void
    {
        $morto   = $this->inscrever(1);
        $instavel = $this->inscrever(1);
        $this->respostas[$morto->endpoint]    = 410;
        $this->respostas[$instavel->endpoint] = 503;
        $this->db->table('tarefas')->insert(['id_tarefa' => 1, 'tarefa' => 'Retorno', 'prazo' => '2026-11-03', 'responsavel' => 1, 'status' => 1]);

        $r = $this->servico()->processar();

        $this->assertSame(1, $r['removidas']);
        $this->assertSame(1, $r['falhas']);
        $this->assertSame(0, $r['prazos'], 'nenhum aparelho recebeu: fica pendente');
        $this->assertCount(1, $this->repo->inscricoesDoUsuario(1));

        // Serviço volta: o aviso pendente é entregue.
        unset($this->respostas[$instavel->endpoint]);
        $r = $this->servico()->processar();
        $this->assertSame(1, $r['prazos']);
    }

    public function testInscricaoComFalhasSeguidasEDescartada(): void
    {
        $this->config->maximoFalhas = 2;
        $instavel = $this->inscrever(1);
        $this->respostas[$instavel->endpoint] = 500;

        $this->servico()->enviarTeste(1);
        $this->assertCount(1, $this->repo->inscricoesDoUsuario(1));
        $r = $this->servico()->enviarTeste(1);
        $this->assertSame(1, $r['removidas']);
        $this->assertSame([], $this->repo->inscricoesDoUsuario(1));
    }

    public function testTesteVaiParaTodosOsAparelhosDoUsuario(): void
    {
        $celular  = $this->inscrever(1, ['notificar_prazos' => false]);
        $notebook = $this->inscrever(1);
        $alheio   = $this->inscrever(2);

        $r = $this->servico()->enviarTeste(1);

        $this->assertSame(2, $r['enviadas']);
        $this->assertSame('Notificações ativadas', $this->recebidas($celular)[0]['title']);
        $this->assertCount(1, $this->recebidas($notebook));
        $this->assertSame([], $this->recebidas($alheio));
    }

    public function testMesmoNavegadorComOutroUsuarioTrocaODono(): void
    {
        $navegador = new NavegadorFalso();
        $chaves    = ['endpoint' => $navegador->endpoint] + $navegador->inscricao()['keys'];

        $this->repo->salvarInscricao(1, $chaves, ['notificar_prazos' => false], null);
        $linha = $this->repo->salvarInscricao(2, $chaves, [], null);

        $this->assertSame(2, (int) $linha['user_id']);
        $this->assertSame([], $this->repo->inscricoesDoUsuario(1));
        $this->assertSame('0', (string) $linha['notificar_prazos'], 'preferência não informada é mantida');
        $this->assertFalse($this->repo->removerInscricao(1, $navegador->endpoint), 'usuário 1 não remove inscrição alheia');
        $this->assertTrue($this->repo->removerInscricao(2, $navegador->endpoint));
    }
}
