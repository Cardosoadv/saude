<?php

namespace App\Services;

use App\Controllers\Processos;
use App\Models\ProcessosMovimentosModel;
use App\Repositories\MovimentosRepository;
use Exception;
use Symfony\Component\Process\Process;

/**
 * Serviço responsável pela gestão de movimentos processuais.
 *
 * Esta classe gerencia a adição, sincronização, listagem e processamento de movimentos
 * processuais, integrando-se com APIs externas de tribunais (DataJud) e repositórios locais.
 */
class MovimentosService extends BaseService
{
    /**
     * Gera um nome de arquivo único para salvar o JSON.
     *
     * O arquivo é organizado em diretórios por data (Ano/Mês/Dia).
     *
     * @return string Caminho completo do arquivo gerado.
     */
    private function generateFilename(): string
    {
        // Define o caminho base para a pasta de armazenamento dos arquivos JSON
        $basePath = WRITEPATH . 'jsons/Movimentos';

        // Obtém a data atual no formato 'ano/mês/dia'
        $datePath = date('Y/m/d');

        // Cria o caminho completo para a pasta, incluindo ano, mês e dia
        $storagePath = "{$basePath}/{$datePath}";

        // Verifica se o diretório já existe. Se não, ele é criado.
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0o755, true);
        }

        // Gera o nome do arquivo com a data e hora completas para garantir que seja único
        $timestamp = date('YmdHis');
        return "{$storagePath}/Movimento_{$timestamp}.json";
    }

    /**
     * Salva o conteúdo JSON em um arquivo.
     *
     * @param string $filename Caminho completo do arquivo.
     * @param string $jsonContent Conteúdo JSON a ser salvo.
     * @throws Exception Se ocorrer erro ao escrever o arquivo.
     */
    private function saveJsonToFile(string $filename, string $jsonContent): void
    {
        if (file_put_contents($filename, $jsonContent) === false) {
            throw new Exception("Unable to save JSON file: {$filename}");
        }
    }

    /**
     * Construtor do MovimentosService.
     *
     * Inicializa o repositório.
     */
    public function __construct(?MovimentosRepository $repository = null)
    {
        $this->repository = $repository ?? new MovimentosRepository();
    }

    /**
     * Busca um movimento pelo ID.
     *
     * @param int $idMovimento ID do movimento.
     * @return array|null
     */
    public function buscarMovimento(int $idMovimento): ?array
    {
        return $this->repository->findById($idMovimento);
    }

    /**
     * Analisa o sentimento de um movimento processual e salva o resultado.
     *
     * @param int $idMovimento ID do movimento.
     * @return array [sentimento, justificativa, score_confianca]
     * @throws Exception
     */
    public function analisarSentimento(int $idMovimento): array
    {
        $movimento = $this->repository->findById($idMovimento);
        if (!$movimento) {
            throw new Exception("Movimento não encontrado.");
        }

        $aiService = service('AIService');
        $analise = $aiService->analyzeMovementSentiment($movimento['nome'] . ' ' . ($movimento['nome_complemento'] ?? ''));

        $updateData = [
            'sentiment'           => $analise['sentimento'] ?? 'Neutro',
            'sentiment_score'     => $analise['score_confianca'] ?? 0.5,
            'sentiment_rationale' => $analise['justificativa'] ?? null,
        ];

        $this->repository->update($idMovimento, $updateData);

        return $analise;
    }

    /**
     * Busca e processa movimentos de um processo em um tribunal específico via DataJud.
     *
     * @param string $tribunal Código do tribunal (ex: '813' para TJMG).
     * @param string $numeroProcesso Número do processo no formato CNJ.
     * @return array|null Retorna um array com estatísticas do processamento ou null se o tribunal não for suportado.
     */
    public function receberMovimentos(string $tribunal, string $numeroProcesso): ?array
    {
        $url = (new DatajudService())->urlPorCodigo($tribunal);

        if (!$url) {
            log_message('error', "Tribunal não encontrado: tribunal={$tribunal}");
            return null;
        }

        $apiKey = getenv('API_KEY');
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json',
                'x-li-format: json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'query' => [
                    'match' => [
                        'numeroProcesso' => $numeroProcesso,
                    ],
                ],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return ['erro' => 'Não foi possível conectar à API DataJud.'];
        }

        // Generate a unique filename and save JSON for audit
        try {
            $filename = $this->generateFilename();
            $this->saveJsonToFile($filename, $response);
        } catch (Exception $e) {
            log_message('error', 'Erro ao salvar JSON do DataJud: ' . $e->getMessage());
        }

        return $this->processarMovimento($response);
    }

    /**
     * Adiciona um novo movimento ao sistema.
     *
     * Verifica se o movimento já existe antes de inserir para evitar duplicatas.
     *
     * @param array $movimento Dados do movimento a ser adicionado.
     * @return bool True se adicionado com sucesso, false se já existir ou falhar.
     */
    public function adicionarMovimento(array $movimento): bool
    {
        try {
            // Verifica se o movimento já existe
            if ($this->repository->movimentoJaExiste($movimento['numero_processo'], $movimento['dataHora'])) {
                log_message('info', 'Movimento já existe: ' . json_encode($movimento));
                return false; // Movimento já existe, não adiciona novamente
            }

            // Salva o novo movimento
            $this->repository->salvarMovimento($movimento);
            log_message('info', 'Movimento adicionado com sucesso: ' . json_encode($movimento));
            return true;
        } catch (Exception $e) {
            log_message('error', 'Erro ao adicionar movimento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincroniza os movimentos não sincronizados com o sistema externo.
     *
     * Busca movimentos pendentes de sincronização, envia para a API remota e atualiza o status local.
     *
     * @return array Retorna um array com o resultado da sincronização:
     *               - 'success': Mensagem de sucesso ou erro.
     *               - 'synchronized_count': Total de movimentos sincronizados.
     *               - 'unsynchronized_count': Total restante não sincronizado.
     */
    public function synchronizeMovimentos(): array
    {
        $movimentosNaoSincronizados = $this->repository->buscarMovimentosNaoSincronizados(1000);
        $totalNaoSincronizados = $this->repository->contarMovimentosNaoSincronizados();
        $synchronizedCount = 0;
        $failedCount = 0;

        if (empty($movimentosNaoSincronizados)) {
            return [
                'success' => 'Nenhum movimento para sincronizar.',
                'synchronized_count' => 0,
                'unsynchronized_count' => $totalNaoSincronizados,
            ];
        }

        foreach ($movimentosNaoSincronizados as $movimento) {
            try {
                $this->addRemoteMovimento($movimento);
                // Após a sincronização, registrar a data de sincronização
                $this->repository->registrarSincronizacao($movimento['id_movimento']);
                $synchronizedCount++;
            } catch (Exception $e) {
                log_message('error', 'Erro ao adicionar movimento remoto: ' . $e->getMessage());
                $failedCount++;
                continue; // Pular para o próximo movimento em caso de erro
            }
        }

        $newTotalNaoSincronizados = $this->repository->contarMovimentosNaoSincronizados();

        return [
            'success'               => "{$synchronizedCount} movimentos sincronizados com sucesso.",
            'synchronized_count'    => $synchronizedCount,
            'unsynchronized_count'  => $newTotalNaoSincronizados,
        ];
    }

    /**
     * Envia um movimento para o sistema remoto via API.
     *
     * @param array $movimento Dados do movimento.
     * @return bool True se enviado com sucesso (HTTP 2xx), false caso contrário.
     */
    public function addRemoteMovimento(array $movimento): bool
    {
        unset($movimento['id_movimento']); // Remover ID local para evitar conflito
        $urlAdicionar = getenv('URL_SYNC') . 'movimentos/adicionar';
        log_message('info', 'URL de destino para adicionar movimento: ' . $urlAdicionar);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlAdicionar);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('API_KEY_SYNC'),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($movimento));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            log_message('error', 'Erro cURL ao adicionar movimento remoto: ' . $error_msg);
            curl_close($ch);
            return false;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            log_message('info', 'Movimento remoto adicionado com sucesso.');
            return true;
        } else {
            log_message('error', 'Falha ao adicionar movimento remoto. HTTP Code: ' . $http_code . ', Resposta: ' . $response);
            return false;
        }
    }

    /**
     * Lista movimentos que ainda não foram sincronizados.
     *
     * @param int|null $limit Limite opcional de registros.
     * @return array Lista de movimentos não sincronizados.
     */
    public function listarMovimentosNaoSincronizados($limit = null): array
    {
        return $this->repository->buscarMovimentosNaoSincronizados($limit);
    }

    /**
     * Conta quantos movimentos ainda não foram sincronizados.
     *
     * @return int O número de movimentos pendentes de sincronização.
     */
    public function contarMovimentosNaoSincronizados(): int
    {
        return $this->repository->contarMovimentosNaoSincronizados();
    }

    /**
     * Monta a URL da API do tribunal com base no número do processo.
     *
     * Identifica o tribunal pelo código CNJ presente no número do processo e retorna a URL correspondente.
     *
     * @param string $numeroProcesso Número do processo (formato CNJ).
     * @return string URL da API do tribunal.
     * @throws Exception Se o tribunal não for encontrado na lista de suportados.
     */
    public function montarUrl(string $numeroProcesso): string
    {
        return (new DatajudService())->urlPorNumero($numeroProcesso) ?? '';
    }

    /**
     * Processa a resposta JSON do DataJud e extrai os movimentos.
     *
     * Analisa o JSON retornado pela API do DataJud, itera sobre os movimentos e complementos,
     * e salva os dados relevantes no banco de dados se ainda não existirem.
     *
     * @param string $datajudMovimento JSON retornado pela API do DataJud.
     * @return array Estatísticas do processamento (total, salvos, ignorados, erros).
     */
    public function processarMovimento(string $datajudMovimento): array
    {
        $resultados = json_decode($datajudMovimento);
        if (empty($resultados->hits->hits[0])) {
            $data = [
                'erro' => "Erro! Não foi possível encontrar dados do processo. Isso pode acontecer porque ele é sigiloso ou está tramitando em outro tribunal.",
                'numero_movimentos' => 0,
                'movimentos_salvos' => 0,
                'movimentos_ignorados' => 0,
                'success' => null,
            ];
            return $data;
            die;
        }

        // Acessando os dados
        $numeroProcesso = $resultados->hits->hits[0]->_source->numeroProcesso;

        // Acessando os movimentos
        $movimentos = $resultados->hits->hits[0]->_source->movimentos;
        $numeroMovimentos = count($movimentos);
        $movimentosSalvos = 0;
        $movimentosIgnorados = 0;

        foreach ($movimentos as $movimento) {

            $nome = $movimento->nome;
            $dataHoraMovimento = $movimento->dataHora;

            if (! empty($movimento->complementosTabelados)) {

                foreach ($movimento->complementosTabelados as $complementos) {

                    $complementoNome = $complementos->nome;
                    $compleentoDescricao = $complementos->descricao;
                    $complementoCodigo = $complementos->codigo;
                    $complementoValor = $complementos->valor;

                    $data = [
                        'numero_processo'           => $numeroProcesso,
                        'nome'                      => $nome,
                        'descricao_complemento'     => $compleentoDescricao,
                        'nome_complemento'          => $complementoNome,
                        'codigo'                    => $complementoCodigo,
                        'valor'                     => $complementoValor,
                        'dataHora'                  => $dataHoraMovimento,
                    ];

                    //Checa se o movimento já consta no db
                    if (!$this->repository->movimentoJaExiste($numeroProcesso, $dataHoraMovimento)) {
                        $this->repository->salvarMovimento($data);
                        $movimentosSalvos++;
                    } else {
                        $movimentosIgnorados++;
                    }
                }
            }
        }
        return $data = [
            'numero_movimentos'     => $numeroMovimentos,
            'movimentos_salvos'     => $movimentosSalvos,
            'movimentos_ignorados'  => $movimentosIgnorados,
            'erro'                  => null,
            'success'               => 'Movimentos processados com sucesso.',
        ];
    }

}
