<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Email\EmailMarca;
use App\Repositories\CrmContactRepository;
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
     * Repositório de clientes.
     */
    protected ClienteRepository $clienteRepository;

    /**
     * Serviço de CRM.
     */
    protected CrmService $crmService;

    /**
     * Construtor com injeção de dependência.
     *
     * @param CrmContactRepository|null $repository
     * @param ClienteRepository|null $clienteRepository
     * @param CrmService|null $crmService
     */
    public function __construct(
        ?CrmContactRepository $repository = null,
        ?ClienteRepository $clienteRepository = null,
        ?CrmService $crmService = null
    ) {
        $this->repository = $repository ?? new CrmContactRepository();
        $this->clienteRepository = $clienteRepository ?? new ClienteRepository();
        $this->crmService = $crmService ?? new CrmService();
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

            $areaTitle = (string) ($data['area_interesse'] ?? '');

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
     * @param string $area
     * @return array
     */
    public function getContactsByArea(string $area): array
    {
        return $this->repository->getContactsByArea($area);
    }

    /**
     * Busca contatos com paginação e filtros.
     *
     * @param string $search
     * @param int $perPage
     * @param int $page
     * @param string|null $status
     * @param int|null $area
     * @return array
     */
    public function buscarContatos(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
        ?string $status = null,
        ?string $area = null
    ): array {
        return $this->repository->buscarContatos($search, $perPage, $page, $status, $area);
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
                    'tipo_cliente' => 'F',
                    'ativo' => 1,
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
