<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\GoogleCalendarService;
use App\Services\ConfiguracoesService;
use App\Repositories\TarefasRepository;
use App\Models\TarefasModel;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Resource\Calendars as CalendarsResource;
use Google\Service\Calendar\Resource\CalendarList as CalendarListResource;
use Google\Service\Calendar\Resource\Events as EventsResource;
use Google\Service\Calendar\Calendar as GoogleCalendarModel;
use Google\Service\Calendar\CalendarListEntry;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

/**
 * Testes unitários para o GoogleCalendarService.
 */
class GoogleCalendarServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
        }

        // Limpa o cache estático do repositório e o cache do CodeIgniter
        \App\Repositories\BaseRepository::clearVersions();
        service('cache')->clean();

        $db = \Config\Database::connect();
        $prefix = $db->getPrefix();

        // Cria tabela de auditoria
        $db->query("DROP TABLE IF EXISTS {$prefix}auditoria");
        $db->query("CREATE TABLE {$prefix}auditoria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            table_name TEXT,
            action_type TEXT,
            dados_antigos TEXT,
            dados_novos TEXT,
            ip_address TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");

        // Cria tabela de settings
        $db->query("DROP TABLE IF EXISTS {$prefix}settings");
        $db->query("CREATE TABLE {$prefix}settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT,
            key TEXT,
            value TEXT,
            type TEXT,
            context TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");

        // Cria tabela de configurações
        $db->query("DROP TABLE IF EXISTS {$prefix}configuracoes");
        $db->query("CREATE TABLE {$prefix}configuracoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT UNIQUE,
            valor TEXT,
            descricao TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");

        // Cria tabela de tarefas
        $db->query("DROP TABLE IF EXISTS {$prefix}tarefas");
        $db->query("CREATE TABLE {$prefix}tarefas (
            id_tarefa INTEGER PRIMARY KEY AUTOINCREMENT,
            tarefa TEXT,
            prazo DATE,
            prioridade INTEGER,
            detalhes TEXT,
            responsavel INTEGER,
            status INTEGER,
            cliente_id INTEGER,
            caso_id INTEGER,
            processo_id INTEGER,
            notificar INTEGER,
            google_event_id TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $prefix = $this->db->getPrefix();
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS {$prefix}auditoria");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}settings");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}configuracoes");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}tarefas");
        }
        parent::tearDown();
    }

    /**
     * Testa se o serviço não está pronto quando as credenciais não estão configuradas.
     */
    public function testIsReadyReturnsFalseWithoutCredentials()
    {
        // Garante que o banco não tem as configurações
        $db = \Config\Database::connect();
        $db->table('configuracoes')->truncate();

        $service = new GoogleCalendarService();
        $this->assertFalse($service->isReady());
    }

    /**
     * Testa se o serviço busca ou cria o calendário corretamente.
     */
    public function testGetOrCreateCalendarFetchesExisting()
    {
        $db = \Config\Database::connect();
        $db->table('configuracoes')->insertBatch([
            ['nome' => 'GOOGLE_CLIENT_ID', 'valor' => 'client_id_teste'],
            ['nome' => 'GOOGLE_CLIENT_SECRET', 'valor' => 'client_secret_teste'],
            ['nome' => 'GOOGLE_REFRESH_TOKEN', 'valor' => 'refresh_token_teste'],
            ['nome' => 'GOOGLE_CALENDAR_ID', 'valor' => 'calendar_id_salvo'],
        ]);

        $clientMock = $this->createMock(Client::class);
        $calendarServiceMock = $this->createMock(Calendar::class);
        $calendarsResourceMock = $this->createMock(CalendarsResource::class);

        // Define as propriedades mágicas do mock do Calendar
        $calendarServiceMock->calendars = $calendarsResourceMock;

        // Se o calendário com o ID salvo for buscado, retorna um objeto de calendário válido
        $googleCalendar = new GoogleCalendarModel();
        $googleCalendar->setId('calendar_id_salvo');
        $googleCalendar->setSummary('Saúde');

        $calendarsResourceMock->expects($this->once())
            ->method('get')
            ->with('calendar_id_salvo')
            ->willReturn($googleCalendar);

        $service = new GoogleCalendarService(null, null, $clientMock);

        // Injeta o mock do serviço do Google via Reflection porque ele é instanciado no construtor
        $reflection = new \ReflectionClass(GoogleCalendarService::class);
        $propService = $reflection->getProperty('service');
        $propService->setAccessible(true);
        $propService->setValue($service, $calendarServiceMock);

        $propClient = $reflection->getProperty('client');
        $propClient->setAccessible(true);
        $propClient->setValue($service, $clientMock);

        $calendarId = $service->getOrCreateCalendar();
        $this->assertEquals('calendar_id_salvo', $calendarId);
    }

    /**
     * Testa se o serviço cria o calendário quando ele não existe no banco nem no Google.
     */
    public function testGetOrCreateCalendarCreatesNewOne()
    {
        $db = \Config\Database::connect();
        $db->table('configuracoes')->insertBatch([
            ['nome' => 'GOOGLE_CLIENT_ID', 'valor' => 'client_id_teste'],
            ['nome' => 'GOOGLE_CLIENT_SECRET', 'valor' => 'client_secret_teste'],
            ['nome' => 'GOOGLE_REFRESH_TOKEN', 'valor' => 'refresh_token_teste'],
            ['nome' => 'GOOGLE_CALENDAR_ID', 'valor' => ''],
        ]);

        $clientMock = $this->createMock(Client::class);
        $calendarServiceMock = $this->createMock(Calendar::class);
        $calendarsResourceMock = $this->createMock(CalendarsResource::class);
        $calendarListResourceMock = $this->createMock(CalendarListResource::class);

        $calendarServiceMock->calendars = $calendarsResourceMock;
        $calendarServiceMock->calendarList = $calendarListResourceMock;

        // Ao listar os calendários, retorna uma lista vazia
        $listMock = new \Google\Service\Calendar\CalendarList();
        $listMock->setItems([]);

        $calendarListResourceMock->expects($this->once())
            ->method('listCalendarList')
            ->willReturn($listMock);

        // Deve chamar a criação do calendário
        $createdCalendar = new GoogleCalendarModel();
        $createdCalendar->setId('novo_calendar_id_google');
        $createdCalendar->setSummary('Saúde');

        $calendarsResourceMock->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($arg) {
                return $arg instanceof GoogleCalendarModel && $arg->getSummary() === 'Saúde';
            }))
            ->willReturn($createdCalendar);

        $service = new GoogleCalendarService(null, null, $clientMock);

        $reflection = new \ReflectionClass(GoogleCalendarService::class);
        $propService = $reflection->getProperty('service');
        $propService->setAccessible(true);
        $propService->setValue($service, $calendarServiceMock);

        $propClient = $reflection->getProperty('client');
        $propClient->setAccessible(true);
        $propClient->setValue($service, $clientMock);

        $calendarId = $service->getOrCreateCalendar();
        $this->assertEquals('novo_calendar_id_google', $calendarId);

        // Verifica se persistiu no banco de dados
        $configService = new ConfiguracoesService();
        $this->assertEquals('novo_calendar_id_google', $configService->getValor('GOOGLE_CALENDAR_ID'));
    }

    /**
     * Testa sincronização de inserção de tarefa.
     */
    public function testSincronizarTarefaInsert()
    {
        $db = \Config\Database::connect();
        $db->table('configuracoes')->insertBatch([
            ['nome' => 'GOOGLE_CLIENT_ID', 'valor' => 'client_id_teste'],
            ['nome' => 'GOOGLE_CLIENT_SECRET', 'valor' => 'client_secret_teste'],
            ['nome' => 'GOOGLE_REFRESH_TOKEN', 'valor' => 'refresh_token_teste'],
            ['nome' => 'GOOGLE_CALENDAR_ID', 'valor' => 'calendar_existente'],
        ]);

        $db->table('tarefas')->insert([
            'tarefa' => 'Fazer petição inicial',
            'prazo' => '2026-10-15',
            'prioridade' => 4,
            'detalhes' => 'Tarefa importante de teste.',
            'status' => 1,
        ]);
        $idTarefa = $db->insertID();

        $clientMock = $this->createMock(Client::class);
        $calendarServiceMock = $this->createMock(Calendar::class);
        $calendarsResourceMock = $this->createMock(CalendarsResource::class);
        $eventsResourceMock = $this->createMock(EventsResource::class);

        $calendarServiceMock->calendars = $calendarsResourceMock;
        $calendarServiceMock->events = $eventsResourceMock;

        // Mock para validar a existência do calendário
        $googleCalendar = new GoogleCalendarModel();
        $googleCalendar->setId('calendar_existente');
        $calendarsResourceMock->expects($this->once())
            ->method('get')
            ->with('calendar_existente')
            ->willReturn($googleCalendar);

        // Deve chamar a inserção do evento
        $createdEvent = new Event();
        $createdEvent->setId('event_id_google_123');

        $eventsResourceMock->expects($this->once())
            ->method('insert')
            ->with('calendar_existente', $this->callback(function ($event) {
                return $event instanceof Event
                    && $event->getSummary() === '[Tarefa] Fazer petição inicial'
                    && str_contains($event->getDescription(), 'Tarefa importante de teste.')
                    && $event->getStart()->getDate() === '2026-10-15'
                    && $event->getEnd()->getDate() === '2026-10-16'; // Dia seguinte (fim exclusivo)
            }))
            ->willReturn($createdEvent);

        $service = new GoogleCalendarService(null, null, $clientMock);

        $reflection = new \ReflectionClass(GoogleCalendarService::class);
        $propService = $reflection->getProperty('service');
        $propService->setAccessible(true);
        $propService->setValue($service, $calendarServiceMock);

        $propClient = $reflection->getProperty('client');
        $propClient->setAccessible(true);
        $propClient->setValue($service, $clientMock);

        $service->sincronizarTarefa($idTarefa, 'insert');

        // Verifica se a tarefa local foi atualizada com o google_event_id
        $tarefasModel = new TarefasModel();
        $tarefaLoc = $tarefasModel->find($idTarefa);
        $this->assertEquals('event_id_google_123', $tarefaLoc['google_event_id']);
    }

    /**
     * Testa exclusão de evento sincronizado.
     */
    public function testSincronizarTarefaDelete()
    {
        $db = \Config\Database::connect();
        $db->table('configuracoes')->insertBatch([
            ['nome' => 'GOOGLE_CLIENT_ID', 'valor' => 'client_id_teste'],
            ['nome' => 'GOOGLE_CLIENT_SECRET', 'valor' => 'client_secret_teste'],
            ['nome' => 'GOOGLE_REFRESH_TOKEN', 'valor' => 'refresh_token_teste'],
            ['nome' => 'GOOGLE_CALENDAR_ID', 'valor' => 'calendar_existente'],
        ]);

        $db->table('tarefas')->insert([
            'tarefa' => 'Tarefa deletada',
            'prazo' => '2026-10-15',
            'google_event_id' => 'event_id_google_delete',
        ]);
        $idTarefa = $db->insertID();

        $clientMock = $this->createMock(Client::class);
        $calendarServiceMock = $this->createMock(Calendar::class);
        $calendarsResourceMock = $this->createMock(CalendarsResource::class);
        $eventsResourceMock = $this->createMock(EventsResource::class);

        $calendarServiceMock->calendars = $calendarsResourceMock;
        $calendarServiceMock->events = $eventsResourceMock;

        // Mock para validar a existência do calendário
        $googleCalendar = new GoogleCalendarModel();
        $googleCalendar->setId('calendar_existente');
        $calendarsResourceMock->expects($this->once())
            ->method('get')
            ->with('calendar_existente')
            ->willReturn($googleCalendar);

        // Deve chamar a exclusão do evento
        $eventsResourceMock->expects($this->once())
            ->method('delete')
            ->with('calendar_existente', 'event_id_google_delete');

        $service = new GoogleCalendarService(null, null, $clientMock);

        $reflection = new \ReflectionClass(GoogleCalendarService::class);
        $propService = $reflection->getProperty('service');
        $propService->setAccessible(true);
        $propService->setValue($service, $calendarServiceMock);

        $propClient = $reflection->getProperty('client');
        $propClient->setAccessible(true);
        $propClient->setValue($service, $clientMock);

        $service->sincronizarTarefa($idTarefa, 'delete');
    }
}
