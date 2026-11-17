<?php

namespace App\Services;

use App\Repositories\FornecedorRepository;
use App\Traits\FormataDocumentoTrait;
use App\Traits\ValidacoesTrait;
use Exception;

/**
 * Serviço responsável pela gestão de fornecedores.
 *
 * Esta classe encapsula a lógica de negócios para operações relacionadas a fornecedores,
 * delegando operações de banco de dados para o FornecedorRepository.
 */
class FornecedorService extends BaseService
{
    use FormataDocumentoTrait;
    use ValidacoesTrait;


    protected ?ClienteService $clienteService = null;

    /**
     * Construtor do FornecedorService.
     */
    public function __construct(?FornecedorRepository $repository = null)
    {
        $this->repository = $repository ?? new FornecedorRepository();
    }

    public function setClienteService(ClienteService $clienteService): void
    {
        $this->clienteService = $clienteService;
    }

    /**
     * Transforma um fornecedor existente em um cliente.
     *
     * Copia os dados cadastrais, valida duplicidade e opcionalmente inativa a origem.
     *
     * @param int $idFornecedor ID do fornecedor
     * @param array $opcoes Opções adicionais (ex: ['inativar_origem' => bool])
     * @return array Contendo cliente_id e mensagem de sucesso
     * @throws Exception
     */
    public function transformarEmCliente(int $idFornecedor, array $opcoes = []): array
    {
        $fornecedor = $this->getById($idFornecedor);
        if (!$fornecedor) {
            throw new Exception('Fornecedor não encontrado.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $docFormatado = !empty($fornecedor['documento']) ? $this->formatarCpfCnpj($fornecedor['documento']) : null;

            $tipoCliente = 'F';
            if (!empty($fornecedor['tipo_pessoa']) && in_array(strtoupper($fornecedor['tipo_pessoa']), ['F', 'J'])) {
                $tipoCliente = strtoupper($fornecedor['tipo_pessoa']);
            }

            $clienteData = [
                'tipo_cliente'    => $tipoCliente,
                'nome'            => $fornecedor['nome'],
                'razao_social'    => $fornecedor['razao_social'] ?? null,
                'documento'       => $docFormatado,
                'email'           => $fornecedor['email'] ?? null,
                'telefone'        => $fornecedor['telefone'] ?? null,
                'endereco'        => $fornecedor['endereco'] ?? null,
                'numero'          => $fornecedor['numero'] ?? null,
                'complemento'     => $fornecedor['complemento'] ?? null,
                'cep'             => $fornecedor['cep'] ?? null,
                'cidade'          => $fornecedor['cidade'] ?? null,
                'uf'              => $fornecedor['uf'] ?? null,
                'ativo'           => isset($fornecedor['ativo']) ? (int)$fornecedor['ativo'] : 1,
                'status_crm'      => 'client',
                'dataAquisicao'   => date('Y-m-d'),
                'visibilidade'    => 'publico',
            ];

            $clienteService = $this->clienteService ?? new ClienteService();
            $clienteId = $clienteService->salvarCliente($clienteData);

            if (!empty($opcoes['inativar_origem'])) {
                $this->update($idFornecedor, ['ativo' => 0]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Erro ao processar a transformação de fornecedor para cliente.');
            }

            return [
                'success'    => true,
                'cliente_id' => (int) $clienteId,
                'message'    => 'Fornecedor transformado em cliente com sucesso!',
            ];
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Lista fornecedores com pesquisa opcional e paginação.
     *
     * @param string|null $searchTerm Termo de pesquisa
     * @param int $perPage Número de itens por página
     * @return array
     */
    public function listarFornecedores(?string $searchTerm = null, int $perPage = 25): array
    {
        $filters = [];
        if ($searchTerm !== null) {
            $filters['search'] = $searchTerm;
        }

        $page = service('request')->getVar('page') ?? 1;

        return $this->repository->buscarFornecedores($filters, $perPage, (int) $page);
    }


    /**
     * Salva um fornecedor (novo ou existente).
     *
     * @param array $data Dados do fornecedor
     * @param int|null $id ID do fornecedor em caso de atualização
     * @return int ID do fornecedor salvo
     * @throws Exception Se houver erro de validação ou de banco
     */
    public function salvarFornecedor(array $data, ?int $id = null): int
    {
        // Validação do CPF ou CNPJ
        $cpf_cnpj = $data['documento'] ?? null;
        if ($cpf_cnpj) {
            if (!$this->validarCpfCnpj($cpf_cnpj)) {
                throw new Exception('CPF ou CNPJ inválido.');
            }

            // Verifica duplicidade (exceto para o próprio fornecedor)
            if ($this->repository->existeDocumento($cpf_cnpj, $id)) {
                throw new Exception('Já existe um fornecedor cadastrado com este documento.');
            }
        }

        // Formata o documento
        $data['documento'] = $this->formatarCpfCnpj($data['documento']);

        if ($id === null) {
            return $this->create($data);
        }

        if (!$this->update($id, $data)) {
            throw new Exception('Erro ao atualizar fornecedor.');
        }

        return (int) $id;
    }


    /**
     * Busca fornecedores para componentes AJAX (como Select2).
     *
     * @param string|null $term Termo de busca
     * @return array
     */
    public function buscarAjax(?string $term): array
    {
        if (empty($term) || strlen($term) < 2) {
            return [];
        }

        $fornecedores = $this->repository->search($term, 10);

        $data = [];
        foreach ($fornecedores as $fornecedor) {
            $texto = $fornecedor['nome'];
            if (!empty($fornecedor['documento'])) {
                $texto .= ' - ' . $fornecedor['documento'];
            }

            $data[] = [
                'id'    => $fornecedor['id_fornecedor'],
                'text'  => $texto,
            ];
        }

        return $data;
    }

    /**
     * Retorna estatísticas de fornecedores.
     */
    public function getEstatisticas(): array
    {
        return $this->repository->getEstatisticas();
    }

    /**
     * Retorna os últimos fornecedores cadastrados.
     */
    public function getRecentes(int $limit = 5): array
    {
        return $this->repository->getRecentes($limit);
    }
}
