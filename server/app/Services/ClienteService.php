<?php

namespace App\Services;

use App\Repositories\ClienteRepository;
use App\Traits\FormataDocumentoTrait;
use App\Traits\FormataValorTrait;
use Exception;

/**
 * Serviço responsável pela gestão de clientes.
 *
 * Esta classe encapsula a lógica de negócios para operações relacionadas a clientes,
 * delegando operações de banco de dados para o ClienteRepository.
 */
class ClienteService extends BaseService
{
    /**
     * Blocos do detalhe do cliente que pertencem a outros módulos:
     * chave => [slug do módulo, permissões aceitas]. Ver VisibilidadeModulosService.
     */
    public const BLOCOS_DETALHE = [
        'movimentacoes' => ['financeiro', 'financeiro.access'],
    ];

    use FormataDocumentoTrait;
    use FormataValorTrait;

    protected ?FornecedorService $fornecedorService = null;

    /**
     * Construtor do ClienteService.
     */
    public function __construct(?ClienteRepository $repository = null)
    {
        $this->repository = $repository ?? new ClienteRepository();
    }

    public function setFornecedorService(FornecedorService $fornecedorService): void
    {
        $this->fornecedorService = $fornecedorService;
    }

    public function getUsuarioCliente(): ?int
    {
        return $this->repository->getUsuarioCliente();
    }

    /**
     * Lista clientes com pesquisa opcional e paginação.
     *
     * @param string|null $searchTerm Termo de pesquisa (compatibilidade com código legado)
     * @param int $perPage Número de itens por página
     * @return array
     */
    public function listarClientes(?string $searchTerm = null, int $perPage = 25): array
    {
        // Compatibilidade com código legado que usa apenas searchTerm
        $filters = [];
        if ($searchTerm !== null) {
            $filters['search'] = $searchTerm;
        }

        $page = service('request')->getVar('page') ?? 1;

        return $this->repository->buscarClientes($filters, $perPage, (int) $page);
    }

    /**
     * Lista clientes com filtros avançados.
     *
     * @param array $filters Filtros de busca
     * @param int $perPage Número de itens por página
     * @return array
     */
    public function listarClientesComFiltros(array $filters = [], int $perPage = 25): array
    {
        $page = service('request')->getVar('page') ?? 1;

        return $this->repository->buscarClientes($filters, $perPage, (int) $page);
    }

    /**
     * Lista todos os clientes.
     *
     * @return array
     */
    public function listarTodos(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Busca estatísticas gerais de clientes.
     *
     * @return array
     */
    public function obterEstatisticas(): array
    {
        return $this->repository->buscarEstatisticas();
    }

    /**
     * Busca clientes recentes.
     *
     * @param int $limit
     * @return array
     */
    public function obterClientesRecentes(int $limit = 5): array
    {
        $clientes = $this->repository->buscarClientesRecentes($limit);

        // Formata as datas para exibição
        return array_map(function ($cliente) {
            $cliente['data_formatada'] = !empty($cliente['created_at'])
                ? date('d/m/Y', strtotime($cliente['created_at']))
                : 'N/A';
            return $cliente;
        }, $clientes);
    }

    /**
     * Busca aniversariantes do mês.
     *
     * @return array
     */
    public function obterAniversariantes(): array
    {
        $aniversariantes = $this->repository->buscarAniversariantes();

        // Formata as datas de aniversário
        return array_map(function ($cliente) {
            if (!empty($cliente['data_nascimento'])) {
                $data = date_create($cliente['data_nascimento']);
                $cliente['data_aniversario'] = date_format($data, 'd/m');
            } else {
                $cliente['data_aniversario'] = 'N/A';
            }
            return $cliente;
        }, $aniversariantes);
    }

    /**
     * Busca estatísticas de um cliente específico.
     *
     * @param int $clienteId
     * @return array
     */
    public function obterEstatisticasCliente(int $clienteId): array
    {
        return $this->repository->buscarEstatisticasCliente($clienteId);
    }

    /**
     * Busca movimentações financeiras de um cliente.
     *
     * @param int $clienteId
     * @param int $limit
     * @return array
     */
    public function obterMovimentacoesCliente(int $clienteId, int $limit = 5): array
    {
        $movimentacoes = $this->repository->buscarMovimentacoesCliente($clienteId, $limit);

        // Formata as datas e valores
        return array_map(function ($mov) {
            $mov['data_formatada'] = !empty($mov['data'])
                ? date('d/m/Y', strtotime($mov['data']))
                : 'N/A';

            $mov['valor'] = floatval($mov['valor']);

            return $mov;
        }, $movimentacoes);
    }

    public function obterOrigens(): array
    {
        $origensModel = new \App\Models\CrmOrigensModel();
        return $origensModel->orderBy('nome', 'ASC')->findAll();
    }

    /**
     * Salva um cliente (novo ou existente).
     *
     * @param array $data
     * @param int|null $id
     * @return int
     * @throws Exception
     */
    public function salvarCliente(array $data, ?int $id = null): int
    {
        // Converte strings vazias para null em campos que são chaves estrangeiras, datas ou enums nulos
        $camposNulos = ['origem_id', 'data_nascimento', 'status_crm', 'temperatura'];
        foreach ($camposNulos as $campo) {
            if (isset($data[$campo]) && $data[$campo] === '') {
                $data[$campo] = null;
            }
        }

        // Valida se o documento já existe (exceto para o próprio cliente em caso de atualização)
        if (!empty($data['documento'])) {
            if ($this->repository->existeDocumento($data['documento'], $id)) {
                throw new Exception('Já existe um cliente cadastrado com este documento.');
            }
            // Formata o documento
            $data['documento'] = $this->formatarCpfCnpj($data['documento']);
        } else {
            // Leads e oportunidades podem não ter CPF/CNPJ. Grava como NULL (e não
            // string vazia) para não colidir com o índice único da coluna `documento`
            // quando múltiplos registros ficam sem documento cadastrado.
            $data['documento'] = null;
        }

        if ($id === null) {
            $novoId = $this->create($data);
            if (!$novoId) {
                $errors = $this->getErrors();
                if (!empty($errors)) {
                    $detalhes = implode(' | ', $errors);
                } else {
                    $dbError = \Config\Database::connect()->error();
                    $detalhes = !empty($dbError['message']) ? $dbError['message'] : 'Erro desconhecido no banco';
                }
                throw new Exception('Erro ao criar cliente. Detalhes: ' . $detalhes);
            }
            $this->agendarSincronizacaoDrive((int)$novoId);
            return $novoId;
        }

        if (!$this->update($id, $data)) {
            $errors = $this->getErrors();
            if (!empty($errors)) {
                $detalhes = implode(' | ', $errors);
            } else {
                $dbError = \Config\Database::connect()->error();
                $detalhes = !empty($dbError['message']) ? $dbError['message'] : 'Erro desconhecido no banco ou validação';
            }
            throw new Exception('Erro ao atualizar cliente. Detalhes: ' . $detalhes);
        }

        $cliente = $this->encontrarCliente($id);
        if (empty($cliente['pasta'])) {
            $this->agendarSincronizacaoDrive($id);
        }

        return $id;
    }

    /**
     * Agenda a criação da pasta do Google Drive para o cliente.
     */
    private function agendarSincronizacaoDrive(int $clienteId): void
    {
        $jobModel = new \App\Models\DriveSyncJobModel();

        $existing = $jobModel->where('entity_type', 'cliente')
                             ->where('entity_id', $clienteId)
                             ->where('status', 'pending')
                             ->first();

        if (!$existing) {
            $jobModel->insert([
                'entity_type' => 'cliente',
                'entity_id'   => $clienteId,
                'status'      => 'pending'
            ]);
        }
    }

    /**
     * Encontra um cliente pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function encontrarCliente(int $id): ?array
    {
        return $this->getById($id);
    }

    /**
     * Exclui um cliente pelo ID.
     *
     * @param int $id
     * @return void
     * @throws Exception
     */
    public function deletarCliente(int $id): void
    {
        if (!$this->delete($id)) {
            throw new Exception('Erro ao excluir cliente.');
        }
    }

    /**
     * Obtém o ID do cliente associado a um ID de usuário.
     *
     * @param int $userId
     * @return int|null
     */
    public function getClienteIdByUserId(int $userId): ?int
    {
        return $this->repository->getClienteIdByUserId($userId);
    }

    /**
     * Retorna o ID do cliente pelo nome.
     *
     * @param string|null $nome
     * @return int|null
     */
    public function getClientePeloNome(?string $nome): ?int
    {
        if (empty($nome)) {
            log_message('warning', 'Tentativa de buscar cliente com nome vazio ou nulo no ClienteService.');
            return null;
        }

        $id = $this->repository->getClientePeloNome($nome);

        if ($id) {
            return $id;
        }

        log_message('info', 'Cliente não encontrado para o nome: ' . $nome);
        return null;
    }

    /**
     * Retorna o ID do cliente pelo documento.
     *
     * @param string|null $documento
     * @return int|null
     */
    public function getClientePeloDocumento(?string $documento): ?int
    {
        if (empty($documento)) {
            log_message('warning', 'Tentativa de buscar cliente com documento vazio ou nulo no ClienteService.');
            return null;
        }

        $id = $this->repository->getClientePeloDocumento($documento);

        if ($id) {
            return $id;
        }

        log_message('info', 'Cliente não encontrado para o documento: ' . $documento);
        return null;
    }

    /**
     * Realiza uma pesquisa leve de clientes para componentes de autocomplete.
     *
     * @param string $term Termo de busca
     * @param int $limit Limite de resultados
     * @return array
     */
    public function pesquisarClientes(string $term, int $limit = 15): array
    {
        return $this->repository->search($term, $limit);
    }

    /**
     * Processa a importação de um arquivo TXT de clientes.
     *
     * @param string $filepath Caminho completo do arquivo no servidor.
     * @return string Resumo da importação.
     */
    public function importarClientesDeArquivo(string $filepath): string
    {
        try {
            $file = new \SplFileObject($filepath, 'r');
        } catch (\Exception $e) {
            return 'Não foi possível abrir o arquivo: ' . $e->getMessage();
        }

        $linhaProcessada = 0;
        $linhasSucesso = 0;
        $linhasErro = 0;
        $linhaRepetida = 0;

        foreach ($file as $linha) {
            $linhaProcessada++;
            $linha = trim((string)$linha);
            if (empty($linha)) {
                continue;
            }

            // Extrair valores usando expressões regulares
            preg_match("/'nome'\s*=>\s*'([^']+)'/", $linha, $matchesNome);
            preg_match("/'tipo_cliente'\s*=>\s*'([^']+)'/", $linha, $matchesTipo);
            preg_match("/'documento'\s*=>\s*'([^']+)'/", $linha, $matchesDocumento);
            preg_match("/'dataAquisicao'\s*=>\s*'([^']+)'/", $linha, $matchesData);
            preg_match("/'ativo'\s*=>\s*'([^']+)'/", $linha, $matchesAtivo);

            if (
                !isset($matchesNome[1]) ||
                !isset($matchesTipo[1]) ||
                !isset($matchesDocumento[1]) ||
                !isset($matchesData[1]) ||
                !isset($matchesAtivo[1])
            ) {
                $linhasErro++;
                continue;
            }

            $dados = [
                'nome' => $matchesNome[1],
                'tipo_cliente' => $matchesTipo[1],
                'documento' => $matchesDocumento[1],
                'dataAquisicao' => $matchesData[1],
                'ativo' => $matchesAtivo[1],
            ];

            // Tratamento de dados
            $dados['ativo'] = (strtolower($dados['ativo']) === 'sim' || strtolower($dados['ativo']) === 'true' || $dados['ativo'] === '1') ? 1 : 0;
            $dados['dataAquisicao'] = $this->excelSerialDateToDate($dados['dataAquisicao']);
            $dados['documento'] = preg_replace('/[^0-9]/', '', $dados['documento']);

            if (empty($dados['nome']) || empty($dados['documento'])) {
                $linhasErro++;
                continue;
            }

            $dados['documento'] = $this->formataValorCpf_cnpj($dados['documento']);

            // Verifica se o cliente já existe
            if ($this->getClientePeloDocumento($dados['documento'])) {
                $linhaRepetida++;
                continue;
            }

            try {
                $this->create($dados);
                $linhasSucesso++;
            } catch (\Exception $e) {
                log_message('error', 'Erro ao importar linha ' . $linhaProcessada . ': ' . $e->getMessage());
                $linhasErro++;
            }
        }

        $file = null; // Fecha o SplFileObject
        return "Importação concluída: $linhasSucesso cliente(s) importado(s) com sucesso, $linhasErro erro(s), $linhaRepetida linha(s) repetida(s).";
    }

    /**
     * Converte data serial do Excel para formato Y-m-d.
     *
     * @param mixed $excelSerialDate O número serial da data do Excel.
     * @return string A data formatada.
     */
     private function excelSerialDateToDate($excelSerialDate): string
     {
         if (!is_numeric($excelSerialDate)) {
             return date('Y-m-d');
         }
         $unixTimestamp = ($excelSerialDate - 25569) * 86400;
         return date('Y-m-d', $unixTimestamp);
     }

    /**
     * Transforma um cliente existente em um fornecedor.
     *
     * Copia os dados cadastrais, valida duplicidade e opcionalmente inativa a origem.
     *
     * @param int $idCliente ID do cliente
     * @param array $opcoes Opções adicionais (ex: inativar_origem, banco, agencia, conta, tipo_conta, pix)
     * @return array Contendo fornecedor_id e mensagem de sucesso
     * @throws Exception
     */
    public function transformarEmFornecedor(int $idCliente, array $opcoes = []): array
    {
        $cliente = $this->getById($idCliente);
        if (!$cliente) {
            throw new Exception('Cliente não encontrado.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $docFormatado = !empty($cliente['documento']) ? $this->formatarCpfCnpj($cliente['documento']) : null;

            $tipoPessoa = 'F';
            if (!empty($cliente['tipo_cliente']) && in_array(strtoupper($cliente['tipo_cliente']), ['F', 'J'])) {
                $tipoPessoa = strtoupper($cliente['tipo_cliente']);
            }

            $fornecedorData = [
                'tipo_pessoa'     => $tipoPessoa,
                'nome'            => $cliente['nome'],
                'razao_social'    => $cliente['razao_social'] ?? null,
                'documento'       => $docFormatado,
                'email'           => $cliente['email'] ?? null,
                'telefone'        => $cliente['telefone'] ?? null,
                'endereco'        => $cliente['endereco'] ?? null,
                'numero'          => $cliente['numero'] ?? null,
                'complemento'     => $cliente['complemento'] ?? null,
                'cep'             => $cliente['cep'] ?? null,
                'cidade'          => $cliente['cidade'] ?? null,
                'uf'              => $cliente['uf'] ?? null,
                'ativo'           => isset($cliente['ativo']) ? (int)$cliente['ativo'] : 1,
                'banco'           => $opcoes['banco'] ?? null,
                'agencia'         => $opcoes['agencia'] ?? null,
                'conta'           => $opcoes['conta'] ?? null,
                'tipo_conta'      => $opcoes['tipo_conta'] ?? null,
                'pix'             => $opcoes['pix'] ?? null,
            ];

            $fornecedorService = $this->fornecedorService ?? new FornecedorService();
            $fornecedorId = $fornecedorService->salvarFornecedor($fornecedorData);

            if (!empty($opcoes['inativar_origem'])) {
                $this->update($idCliente, ['ativo' => 0]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Erro ao processar a transformação de cliente para fornecedor.');
            }

            return [
                'success'       => true,
                'fornecedor_id' => (int) $fornecedorId,
                'message'       => 'Cliente transformado em fornecedor com sucesso!',
            ];
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
