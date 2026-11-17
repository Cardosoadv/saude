<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Email\EmailMarca;
use App\Repositories\CrmContactRepository;
use App\Repositories\RamosDireitoRepository;
use App\Repositories\ClienteRepository;
use App\Models\ClientesModel;

/**
 * Serviço para gerenciamento de contatos do CRM.
 *
 * @version 1.1.0
 */
class CrmContactService extends BaseService
{
    /**
     * Repositório de ramos do direito.
     */
    protected RamosDireitoRepository $ramosDireitoRepository;

    /**
     * Repositório de clientes.
     */
    protected ClienteRepository $clienteRepository;

    /**
     * Serviço de CRM.
     */
    protected CrmService $crmService;

    /**
     * Serviço de Processos.
     */
    protected ProcessosService $processosService;

    /**
     * Construtor com injeção de dependência.
     *
     * @param CrmContactRepository|null $repository
     * @param RamosDireitoRepository|null $ramosDireitoRepository
     * @param ClienteRepository|null $clienteRepository
     * @param CrmService|null $crmService
     * @param ProcessosService|null $processosService
     */
    public function __construct(
        ?CrmContactRepository $repository = null,
        ?RamosDireitoRepository $ramosDireitoRepository = null,
        ?ClienteRepository $clienteRepository = null,
        ?CrmService $crmService = null,
        ?ProcessosService $processosService = null
    ) {
        $this->repository = $repository ?? new CrmContactRepository();
        $this->ramosDireitoRepository = $ramosDireitoRepository ?? new RamosDireitoRepository();
        $this->clienteRepository = $clienteRepository ?? new ClienteRepository();
        $this->crmService = $crmService ?? new CrmService();
        $this->processosService = $processosService ?? new ProcessosService();
    }

    #################################################################
    #                           Contatos                            #
    #################################################################

    /**
     * Cria um novo contato e envia notificação por e-mail.
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $id = parent::create($data);

        // Se o contato foi salvo com sucesso, envia o e-mail
        if ($id) {
            $this->sendNotificationEmail($data);
        }

        return (int) $id;
    }

    /**
     * Envia notificação por e-mail sobre novo contato.
     *
     * @param array $data
     * @return void
     */
    private function sendNotificationEmail(array $data): void
    {
        try {
            $emailService = \Config\Services::email();

            // Pega configurações do email para FROM
            $config = new \Config\Email();
            $from = $config->fromEmail;

            // Define o destinatário. Tenta pegar do .env, se não houver, usa o e-mail padrão do $from
            $to = env('CONTACT_MAIL_TO', $from);

            // Pega o nome do ramo do direito se houver area_interesse
            $areaTitle = '';
            if (!empty($data['area_interesse'])) {
                $ramo = $this->getRamoDireitoById((int) $data['area_interesse']);
                if ($ramo) {
                    $areaTitle = is_object($ramo) ? ($ramo->nome ?? '') : ($ramo['nome'] ?? '');
                }
            }

            EmailMarca::montar($emailService, 'emails/contato_site', [
                'contact'   => $data,
                'areaTitle' => $areaTitle,
            ], [
                'titulo'    => 'Novo contato pelo site',
                'etiqueta'  => 'Contato',
                'tom'       => 'accent',
                'preheader' => ($data['name'] ?? '') . ' entrou em contato pelo site.',
            ]);

            $emailService->setFrom($from, 'Site Admin');
            $emailService->setTo($to);
            $emailService->setSubject('Novo Contato do Site: ' . ($data['name'] ?? ''));

            $emailService->send();

        } catch (\Exception $e) {
            log_message('error', 'Erro ao enviar notificação de contato: ' . $e->getMessage());
        }
    }

    /**
     * Retorna contatos filtrados por área de interesse.
     *
     * @param int $areaId
     * @return array
     */
    public function getContactsByArea(int $areaId): array
    {
        return $this->repository->getContactsByArea($areaId);
    }

    #################################################################
    #                      Ramos do Direito                         #
    #################################################################

    /**
     * Retorna todos os ramos do direito em estrutura hierárquica.
     *
     * @return array
     */
    public function getRamosDireito(): array
    {
        return $this->ramosDireitoRepository->listarTodosHierarquico();
    }

    /**
     * Retorna um ramo do direito pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getRamoDireitoById(int $id): ?array
    {
        return $this->ramosDireitoRepository->findById($id);
    }

    /**
     * Busca contatos com paginação e filtros.
     *
     * @param string $search
     * @param int $perPage
     * @param int $page
     * @param string|null $status
     * @param int|null $areaId
     * @return array
     */
    public function buscarContatos(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
        ?string $status = null,
        ?int $areaId = null
    ): array {
        return $this->repository->buscarContatos($search, $perPage, $page, $status, $areaId);
    }

    /**
     * Converte um lead do site em um Cliente e Oportunidade no CRM.
     *
     * @param int $id
     * @param string $titulo
     * @param int $etapaId
     * @return int
     * @throws \Exception
     */
    public function converterEmOportunidade(int $id, string $titulo, int $etapaId): int
    {
        $contato = $this->repository->findById($id);

        if (!$contato) {
            throw new \Exception('Contato não encontrado.');
        }

        if ($contato['status'] === 'converted') {
            throw new \Exception('Este contato já foi convertido.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // 0. Verificar conflito de interesse antes de prosseguir com a conversão
            $conflito = $this->processosService->checkConflictOfInterest($contato['name']);
            if ($conflito['has_conflict']) {
                throw new \Exception('Conflito de Interesse detectado: ' . $conflito['message'] . ' Verifique os detalhes: ' . json_encode($conflito['conflicts']));
            }

            // 1. Verificar se o cliente já existe por email ou telefone
            $clienteBase = $this->clienteRepository->getModel()
                ->where('email', $contato['email'])
                ->orWhere('telefone', $contato['phone'])
                ->first();

            $clienteId = $clienteBase ? $clienteBase['id_cliente'] : null;

            // 2. Se não existe, criar novo cliente
            if (!$clienteId) {
                $novoCliente = [
                    'nome' => $contato['name'],
                    'email' => $contato['email'],
                    'telefone' => $contato['phone'],
                    'tipo' => 'PF',
                    'status' => 'ativo',
                    'observacoes' => 'Lead oriundo do site. Mensagem original: ' . $contato['message'],
                ];

                $clienteId = $this->clienteRepository->create($novoCliente);
            }

            // 3. Criar a oportunidade no CRM
            $oportunidadeId = $this->crmService->salvarOportunidade([
                'cliente_id' => $clienteId,
                'titulo' => $titulo,
                'etapa_id' => $etapaId,
                'valor_estimado' => 0.00,
                'status' => 'aberto',
                'descricao' => "Interesse na área: " . ($contato['area_interesse'] ?? 'Não especificada') . "\n\nMensagem do Lead:\n" . $contato['message'],
            ]);

            // 4. Atualizar o status do contato para 'converted'
            $this->update($id, ['status' => 'converted']);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Erro ao salvar no banco de dados.');
            }

            return (int) $oportunidadeId;

        } catch (\Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
