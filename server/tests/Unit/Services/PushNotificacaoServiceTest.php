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
        foreach (['push_envios', 'push_inscricoes', 'intimacoes', 'intimacoes_tratamento', 'tarefas', 'processos'] as $tabela) {
            $forge->dropTable($tabela, true);
        }

        (new CreatePushNotificacoes(Database::forge($this->db)))->up();

        $forge->addField([
            'id_intimacao'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'numero_processo' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'tipoComunicacao' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'texto'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id_intimacao');
        $forge->createTable('intimacoes');

        $forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'intimacao_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tratamento_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('intimacoes_tratamento');

        $forge->addField([
            'id_processo'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'numero_processo'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'numeroprocessocommascara' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
        ]);
        $forge->addPrimaryKey('id_processo');
        $forge->createTable('processos');

        $forge->addField([
            'id_tarefa'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tarefa'      => ['type' => 'TEXT', 'null' => true],
            'prazo'       => ['type' => 'DATE', 'null' => true],
            'responsavel' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'processo_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
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

        $this->acessos   = [1 => ['intimacoes', 'tarefas'], 2 => ['intimacoes', 'tarefas'], 3 => ['tarefas']];
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

    /** Inscreve um aparelho do usuário "antes" das intimações de teste chegarem. */
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

    private function intimacao(int $id, string $quando, string $numero = '0001234-56.2026.8.26.0100', string $tipo = 'Intimação'): void
    {
        $this->db->table('intimacoes')->insert([
            'id_intimacao' => $id, 'numero_processo' => $numero, 'tipoComunicacao' => $tipo,
            'texto' => 'TEXTO SIGILOSO DA INTIMAÇÃO', 'created_at' => $quando,
        ]);
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
        $this->intimacao(10, '2026-11-02 23:30:00');

        $r = $this->servico((int) strtotime('2026-11-02 23:45:00'))->processar();
        $this->assertFalse($r['executado']);
        $this->assertSame([], $this->enviados);

        $r = $this->servico((int) strtotime('2026-11-03 07:05:00'))->processar();
        $this->assertTrue($r['executado']);
        $this->assertCount(1, $this->recebidas($celular));
    }

    public function testUmaIntimacaoNovaGeraAvisoComLinkESemOTexto(): void
    {
        $celular = $this->inscrever(1);
        $this->intimacao(10, '2026-11-03 09:00:00');

        $r = $this->servico()->processar();

        $this->assertSame(1, $r['intimacoes']);
        [$msg] = $this->recebidas($celular);
        $this->assertSame('Nova intimação', $msg['title']);
        $this->assertSame('Processo 0001234-56.2026.8.26.0100 — Intimação', $msg['body']);
        $this->assertSame('#/intimacoes/detalhes/10', $msg['url']);
        $this->assertStringNotContainsString('SIGILOSO', json_encode($msg));
    }

    public function testVariasIntimacoesViramUmAvisoSoENaoRepetem(): void
    {
        $celular = $this->inscrever(1);
        $this->intimacao(10, '2026-11-03 09:00:00', '111');
        $this->intimacao(11, '2026-11-03 09:01:00', '222');
        $this->intimacao(12, '2026-11-03 09:02:00', '333');

        $this->servico()->processar();
        $this->servico()->processar(); // segunda execução do cron: nada novo

        $mensagens = $this->recebidas($celular);
        $this->assertCount(1, $mensagens);
        $this->assertSame('3 novas intimações', $mensagens[0]['title']);
        $this->assertSame('Processos 111, 222 e outros', $mensagens[0]['body']);
        $this->assertSame('#/intimacoes', $mensagens[0]['url']);

        // Chega mais uma: só ela é avisada.
        $this->intimacao(13, '2026-11-03 09:30:00', '444');
        $this->servico()->processar();
        $mensagens = $this->recebidas($celular);
        $this->assertCount(2, $mensagens);
        $this->assertSame('#/intimacoes/detalhes/13', $mensagens[1]['url']);
    }

    public function testIgnoraTratadasExcluidasAntigasEAnterioresAInscricao(): void
    {
        $celular = $this->inscrever(1, [], '2026-11-03 08:00:00');
        $this->intimacao(10, '2026-11-03 07:59:00');                 // antes de ativar as notificações
        $this->intimacao(11, '2026-11-03 09:00:00');                 // tratada
        $this->db->table('intimacoes_tratamento')->insert(['intimacao_id' => 11, 'tratamento_id' => 1]);
        $this->intimacao(12, '2026-11-03 09:00:00');                 // excluída
        $this->db->table('intimacoes')->where('id_intimacao', 12)->update(['deleted_at' => '2026-11-03 09:10:00']);

        $r = $this->servico()->processar();

        $this->assertSame(0, $r['intimacoes']);
        $this->assertSame([], $this->recebidas($celular));
    }

    public function testIntimacoesSoParaQuemTemAcessoEPreferenciaLigada(): void
    {
        $semAcesso    = $this->inscrever(3);
        $desligado    = $this->inscrever(2, ['notificar_intimacoes' => false]);
        $comAcesso    = $this->inscrever(1);
        $this->intimacao(10, '2026-11-03 09:00:00');

        $this->servico()->processar();

        $this->assertCount(1, $this->recebidas($comAcesso));
        $this->assertSame([], $this->recebidas($semAcesso));
        $this->assertSame([], $this->recebidas($desligado));
    }

    public function testPrazosDeHojeEAmanhaParaOResponsavel(): void
    {
        $celular = $this->inscrever(1);
        $outro   = $this->inscrever(2);
        $this->db->table('processos')->insert(['id_processo' => 5, 'numeroprocessocommascara' => '0009999-00.2026.5.02.0001']);
        $this->db->table('tarefas')->insertBatch([
            ['id_tarefa' => 1, 'tarefa' => 'Contestação', 'prazo' => '2026-11-03', 'responsavel' => 1, 'processo_id' => 5, 'status' => 1],
            ['id_tarefa' => 2, 'tarefa' => 'Recurso', 'prazo' => '2026-11-04', 'responsavel' => 1, 'processo_id' => null, 'status' => 2],
            ['id_tarefa' => 3, 'tarefa' => 'Concluída', 'prazo' => '2026-11-03', 'responsavel' => 1, 'processo_id' => null, 'status' => 4],
            ['id_tarefa' => 4, 'tarefa' => 'Semana que vem', 'prazo' => '2026-11-10', 'responsavel' => 1, 'processo_id' => null, 'status' => 1],
            ['id_tarefa' => 5, 'tarefa' => 'Já venceu', 'prazo' => '2026-11-02', 'responsavel' => 1, 'processo_id' => null, 'status' => 1],
        ]);

        $r = $this->servico()->processar();

        $this->assertSame(2, $r['prazos']);
        [$msg] = $this->recebidas($celular);
        $this->assertSame('2 prazos próximos (1 hoje)', $msg['title']);
        $this->assertSame('Contestação; Recurso', $msg['body']);
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
        $this->assertSame('Recurso', $mensagens[1]['body']);
    }

    public function testPrazoUnicoMostraOProcesso(): void
    {
        $celular = $this->inscrever(1);
        $this->db->table('processos')->insert(['id_processo' => 5, 'numero_processo' => '00099990020265020001']);
        $this->db->table('tarefas')->insert(['id_tarefa' => 1, 'tarefa' => 'Juntar <b>procuração</b>', 'prazo' => '2026-11-04', 'responsavel' => 1, 'processo_id' => 5, 'status' => 1]);

        $this->servico()->processar();

        [$msg] = $this->recebidas($celular);
        $this->assertSame('Prazo amanhã', $msg['title']);
        $this->assertSame('Juntar procuração — Processo 00099990020265020001', $msg['body']);
        $this->assertSame('normal', $this->enviados[0]['cabecalhos']['Urgency']);
    }

    public function testInscricaoExpiradaEApagadaEFalhaTemporariaRepete(): void
    {
        $morto   = $this->inscrever(1);
        $instavel = $this->inscrever(1);
        $this->respostas[$morto->endpoint]    = 410;
        $this->respostas[$instavel->endpoint] = 503;
        $this->intimacao(10, '2026-11-03 09:00:00');

        $r = $this->servico()->processar();

        $this->assertSame(1, $r['removidas']);
        $this->assertSame(1, $r['falhas']);
        $this->assertSame(0, $r['intimacoes'], 'nenhum aparelho recebeu: fica pendente');
        $this->assertCount(1, $this->repo->inscricoesDoUsuario(1));

        // Serviço volta: o aviso pendente é entregue.
        unset($this->respostas[$instavel->endpoint]);
        $r = $this->servico()->processar();
        $this->assertSame(1, $r['intimacoes']);
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
        $celular  = $this->inscrever(1, ['notificar_intimacoes' => false, 'notificar_prazos' => false]);
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
