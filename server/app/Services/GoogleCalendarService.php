<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Calendar as GoogleCalendarModel;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use App\Repositories\TarefasRepository;
use App\Services\ConfiguracoesService;
use Exception;

/**
 * Serviço responsável pela sincronização de tarefas com o Google Agendas.
 *
 * Utiliza o mesmo fluxo OAuth centralizado do Google Drive para gerenciar
 * um calendário exclusivo para as tarefas do sistema (Saúde).
 */
class GoogleCalendarService extends BaseService
{
    private ?Client $client = null;
    private ?Calendar $service = null;
    private ConfiguracoesService $configuracoesService;
    private TarefasRepository $tarefasRepository;

    /**
     * @param ConfiguracoesService|null $configuracoesService
     * @param TarefasRepository|null $tarefasRepository
     * @param Client|null $client
     */
    public function __construct(
        ?ConfiguracoesService $configuracoesService = null,
        ?TarefasRepository $tarefasRepository = null,
        ?Client $client = null
    ) {
        $this->configuracoesService = $configuracoesService ?? new ConfiguracoesService();
        $this->tarefasRepository = $tarefasRepository ?? new TarefasRepository();

        $clientId = $this->configuracoesService->getValor('GOOGLE_CLIENT_ID');
        $clientSecret = $this->configuracoesService->getValor('GOOGLE_CLIENT_SECRET');
        $refreshToken = $this->configuracoesService->getValor('GOOGLE_REFRESH_TOKEN');

        if ($clientId && $clientSecret && $refreshToken) {
            $this->client = $client ?? new Client();
            $this->client->setApplicationName('Saude');
            $this->client->setScopes([Calendar::CALENDAR]);
            $this->client->setAccessType('offline');
            $this->client->setClientId($clientId);
            $this->client->setClientSecret($clientSecret);

            try {
                $this->client->refreshToken($refreshToken);
                $this->service = new Calendar($this->client);
            } catch (Exception $e) {
                log_message('error', 'Google Calendar API: Falha ao renovar o token: ' . $e->getMessage());
            }
        } else {
            log_message('error', 'Google Calendar OAuth credentials missing in database configurations');
        }
    }

    /**
     * Verifica se a integração com o Google Calendar está ativa e autenticada.
     */
    public function isReady(): bool
    {
        return $this->client !== null && $this->service !== null;
    }

    /**
     * Busca ou cria o calendário dedicado "Saúde" para tarefas.
     * Retorna o ID do calendário no Google.
     */
    public function getOrCreateCalendar(): ?string
    {
        if (!$this->isReady()) {
            return null;
        }

        // 1. Tenta recuperar do banco de dados
        $calendarId = $this->configuracoesService->getValor('GOOGLE_CALENDAR_ID');

        if (!empty($calendarId)) {
            try {
                // Valida se o calendário ainda existe na conta do Google
                $calendar = $this->service->calendars->get($calendarId);
                if ($calendar && $calendar->getId()) {
                    return $calendarId;
                }
            } catch (Exception $e) {
                log_message('info', 'Google Calendar ID registrado é inválido ou foi removido. Criando ou localizando novamente...');
            }
        }

        // 2. Busca na lista de calendários do usuário pelo nome "Saúde"
        try {
            $calendarList = $this->service->calendarList->listCalendarList();
            foreach ($calendarList->getItems() as $item) {
                if ($item->getSummary() === 'Saúde') {
                    $this->salvarCalendarIdConfig($item->getId());
                    return $item->getId();
                }
            }
        } catch (Exception $e) {
            log_message('error', 'Google Calendar: Erro ao listar calendários: ' . $e->getMessage());
        }

        // 3. Se não encontrar, cria um novo calendário dedicado
        try {
            $newCalendar = new GoogleCalendarModel();
            $newCalendar->setSummary('Saúde');
            $newCalendar->setTimeZone('America/Sao_Paulo');
            $newCalendar->setDescription('Calendário exclusivo de tarefas e prazos do sistema Saúde.');

            $createdCalendar = $this->service->calendars->insert($newCalendar);
            if ($createdCalendar && $createdCalendar->getId()) {
                $this->salvarCalendarIdConfig($createdCalendar->getId());
                return $createdCalendar->getId();
            }
        } catch (Exception $e) {
            log_message('error', 'Google Calendar: Erro ao criar calendário "Saúde": ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Salva o ID do calendário nas configurações do sistema.
     */
    private function salvarCalendarIdConfig(string $id): void
    {
        try {
            $configModel = model('ConfiguracoesModel');
            $config = $configModel->where('nome', 'GOOGLE_CALENDAR_ID')->first();
            if ($config) {
                $configModel->update($config['id'], ['valor' => $id]);
            } else {
                $configModel->insert([
                    'nome'      => 'GOOGLE_CALENDAR_ID',
                    'valor'     => $id,
                    'descricao' => 'ID do calendário do Google utilizado para sincronização de tarefas (Saúde).',
                ]);
            }
            // Limpa o cache das configurações para carregar o valor atualizado
            $configRepo = new \App\Repositories\ConfiguracoesRepository($configModel);
            $configRepo->clearCache();
        } catch (Exception $e) {
            log_message('error', 'Google Calendar: Falha ao salvar ID do calendário no banco: ' . $e->getMessage());
        }
    }

    /**
     * Sincroniza uma tarefa específica com o Google Agendas.
     *
     * @param int $idTarefa ID da tarefa
     * @param string $action Ação a ser realizada ('insert', 'update', 'delete')
     * @param array $dados Opcional. Dados atualizados passados diretamente
     */
    public function sincronizarTarefa(int $idTarefa, string $action, array $dados = []): void
    {
        if (!$this->isReady()) {
            return;
        }

        $calendarId = $this->getOrCreateCalendar();
        if (!$calendarId) {
            log_message('error', 'Google Calendar: Não foi possível obter ou criar o calendário dedicado.');
            return;
        }

        // Busca os dados da tarefa atualizados no banco de dados
        $tarefaRaw = $this->tarefasRepository->buscarPorId($idTarefa);
        if (!$tarefaRaw) {
            log_message('info', "Google Calendar: Tarefa {$idTarefa} não encontrada para sincronização.");
            return;
        }

        // Mescla com dados passados na requisição (caso não persistidos ainda)
        $tarefaMerged = array_merge($tarefaRaw, $dados);

        // Popula as informações de vínculos para enviar dados completos ao Google
        $tarefasPopuladas = $this->tarefasRepository->popularVinculos([$tarefaMerged]);
        $tarefa = $tarefasPopuladas[0];

        $googleEventId = $tarefa['google_event_id'] ?? null;

        // Se ação for delete ou o status for concluído/cancelado e decidirmos remover ou apenas marcar
        // Como o usuário respondeu "Todas" para filtros de sincronização, e "Unidirecional",
        // se a tarefa for deletada do sistema, deletamos do Google.
        if ($action === 'delete') {
            if ($googleEventId) {
                try {
                    $this->service->events->delete($calendarId, $googleEventId);
                } catch (Exception $e) {
                    log_message('error', "Google Calendar: Erro ao deletar evento {$googleEventId}: " . $e->getMessage());
                }
            }
            return;
        }

        // Se não tiver prazo cadastrado, não temos como criar um evento no calendário
        if (empty($tarefa['prazo'])) {
            // Se já existia um evento associado, removemos porque agora não tem prazo
            if ($googleEventId) {
                try {
                    $this->service->events->delete($calendarId, $googleEventId);
                    $this->tarefasRepository->atualizar($idTarefa, ['google_event_id' => null]);
                } catch (Exception $e) {
                    log_message('error', "Google Calendar: Erro ao remover evento órfão sem prazo: " . $e->getMessage());
                }
            }
            return;
        }

        // Prepara as propriedades do evento do calendário
        $summary = $this->gerarTituloEvento($tarefa);
        $description = $this->gerarDescricaoEvento($tarefa);

        // Define datas do evento (Dia Inteiro)
        $startDateStr = date('Y-m-d', strtotime($tarefa['prazo']));
        $endDateStr = date('Y-m-d', strtotime($tarefa['prazo'] . ' +1 day'));

        $start = new EventDateTime();
        $start->setDate($startDateStr);

        $end = new EventDateTime();
        $end->setDate($endDateStr);

        $event = new Event();
        $event->setSummary($summary);
        $event->setDescription($description);
        $event->setStart($start);
        $event->setEnd($end);

        if ($action === 'update' && $googleEventId) {
            try {
                // Tenta buscar o evento existente para atualizar
                $existingEvent = $this->service->events->get($calendarId, $googleEventId);
                if ($existingEvent && $existingEvent->getId()) {
                    $existingEvent->setSummary($summary);
                    $existingEvent->setDescription($description);
                    $existingEvent->setStart($start);
                    $existingEvent->setEnd($end);

                    $this->service->events->update($calendarId, $googleEventId, $existingEvent);
                    return;
                }
            } catch (Exception $e) {
                log_message('info', "Google Calendar: Evento {$googleEventId} não encontrado no Google. Criando um novo...");
            }
        }

        // Se for insert ou update sem evento correspondente no Google, cria um novo
        try {
            $createdEvent = $this->service->events->insert($calendarId, $event);
            if ($createdEvent && $createdEvent->getId()) {
                // Atualiza a tarefa localmente com o ID do evento do Google Calendar
                // Ignoramos temporariamente a verificação de auditoria para salvar o ID do Google
                $this->tarefasRepository->atualizar($idTarefa, ['google_event_id' => $createdEvent->getId()]);
            }
        } catch (Exception $e) {
            log_message('error', "Google Calendar: Falha ao inserir evento para tarefa {$idTarefa}: " . $e->getMessage());
        }
    }

    /**
     * Gera o título amigável para o evento no Google Calendar.
     */
    private function gerarTituloEvento(array $tarefa): string
    {
        $titulo = "[Tarefa] " . ($tarefa['tarefa'] ?? 'Sem título');

        if (!empty($tarefa['vinculo_tipo']) && !empty($tarefa['vinculo_info'])) {
            $tipoLabel = '';
            switch ($tarefa['vinculo_tipo']) {
                case 'cliente':
                    $tipoLabel = 'Cliente';
                    break;
            }
            $titulo .= " ({$tipoLabel}: " . $tarefa['vinculo_info'] . ")";
        }

        return $titulo;
    }

    /**
     * Gera a descrição rica para o evento no Google Calendar.
     */
    private function gerarDescricaoEvento(array $tarefa): string
    {
        $prioridades = [
            1 => 'Muito Baixa',
            2 => 'Baixa',
            3 => 'Média',
            4 => 'Alta',
            5 => 'Muito Alta',
        ];
        $prioridadeLabel = $prioridades[$tarefa['prioridade'] ?? 5] ?? 'Não definida';

        $statusMap = [
            1 => 'A Fazer',
            2 => 'Pendente',
            3 => 'Fazendo',
            4 => 'Concluída',
            5 => 'Cancelada',
        ];
        $statusLabel = $statusMap[$tarefa['status'] ?? 1] ?? 'A Fazer';

        $description = "📋 DETALHES DA TAREFA\n";
        $description .= "--------------------------------------------------\n";
        $description .= "Tarefa: " . ($tarefa['tarefa'] ?? '') . "\n";
        $description .= "Prioridade: " . $prioridadeLabel . "\n";
        $description .= "Status atual: " . $statusLabel . "\n";
        $description .= "Responsável: " . ($tarefa['responsavel_nome'] ?? 'Não atribuído') . "\n";

        if (!empty($tarefa['vinculo_tipo']) && !empty($tarefa['vinculo_info'])) {
            $tipoLabel = ucfirst($tarefa['vinculo_tipo']);
            $description .= "Vínculo: " . $tipoLabel . " - " . $tarefa['vinculo_info'] . "\n";
        }

        if (!empty($tarefa['detalhes'])) {
            $description .= "\n📝 Detalhes/Observações:\n" . $tarefa['detalhes'] . "\n";
        }

        $description .= "--------------------------------------------------\n";
        $description .= "Sincronizado automaticamente por Saúde.\n";

        return $description;
    }
}
