<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Services\IndicesEconomicosService;
use CodeIgniter\API\ResponseTrait;
use Throwable;

/**
 * Índices econômicos usados nos cálculos de liquidação.
 *
 * Consulta: `liquidacoes.access`/`liquidacoes.exclusive`. Cadastro, lançamento,
 * importação e CSV: `admin.settings` (ver rotas).
 */
class Indices extends BaseController
{
    use ResponseTrait;

    protected IndicesEconomicosService $service;

    public function __construct(?IndicesEconomicosService $service = null)
    {
        $this->service = $service ?? new IndicesEconomicosService();
    }

    /** GET api/v2/indices */
    public function index()
    {
        return $this->executar(fn () => $this->respond(['success' => true, 'data' => $this->service->listar()]), 'listar');
    }

    /** GET api/v2/indices/{codigo}/valores?de=AAAA-MM-DD&ate=AAAA-MM-DD */
    public function valores($codigo)
    {
        return $this->executar(function () use ($codigo) {
            $valores = $this->service->valores(
                strtoupper((string) $codigo),
                $this->request->getGet('de') ?: null,
                $this->request->getGet('ate') ?: null
            );

            return $this->respond(['success' => true, 'data' => $valores]);
        }, 'consultar valores');
    }

    /** GET api/v2/indices/{codigo}/lacunas */
    public function lacunas($codigo)
    {
        return $this->executar(fn () => $this->respond([
            'success' => true,
            'data'    => $this->service->verificarLacunas(strtoupper((string) $codigo)),
        ]), 'verificar lacunas');
    }

    /** GET api/v2/indices/fator?indice=&inicio=&fim=&mes_inicial=&mes_final= */
    public function fator()
    {
        return $this->executar(function () {
            $regra = array_filter([
                'mes_inicial'          => $this->request->getGet('mes_inicial'),
                'mes_final'            => $this->request->getGet('mes_final'),
                'tolerar_lacuna_final' => (bool) $this->request->getGet('tolerar_lacuna_final'),
            ]);

            return $this->respond(['success' => true, 'data' => $this->service->fatorAcumuladoDetalhado(
                strtoupper((string) $this->request->getGet('indice')),
                (string) $this->request->getGet('inicio'),
                (string) $this->request->getGet('fim'),
                $regra
            )]);
        }, 'calcular o fator');
    }

    /** POST api/v2/indices (cadastra) | PUT api/v2/indices/{id} (altera) */
    public function salvar($id = null)
    {
        return $this->executar(function () use ($id) {
            $novoId = $this->service->salvarIndice($this->corpo(), $id !== null ? (int) $id : null);
            $payload = ['success' => true, 'message' => 'Índice salvo.', 'id' => $novoId];

            return $id === null ? $this->respondCreated($payload) : $this->respond($payload);
        }, 'salvar');
    }

    /** POST api/v2/indices/{codigo}/valores  { competencia, valor } */
    public function lancarValor($codigo)
    {
        return $this->executar(function () use ($codigo) {
            $dados = $this->corpo();
            $this->service->lancarValor(strtoupper((string) $codigo), (string) ($dados['competencia'] ?? ''), (string) ($dados['valor'] ?? ''));

            return $this->respondCreated(['success' => true, 'message' => 'Valor lançado.']);
        }, 'lançar valor');
    }

    /** POST api/v2/indices/{codigo}/csv  (multipart `arquivo` ou JSON { csv }) */
    public function importarCsv($codigo)
    {
        return $this->executar(function () use ($codigo) {
            $arquivo = $this->request->getFile('arquivo');
            $csv = $arquivo && $arquivo->isValid()
                ? (string) file_get_contents($arquivo->getTempName())
                : (string) ($this->corpo()['csv'] ?? '');

            return $this->respond(['success' => true, 'data' => $this->service->importarCsv(strtoupper((string) $codigo), $csv)]);
        }, 'importar CSV');
    }

    /** POST api/v2/indices/atualizar  { codigo?, historico? }: importa do SGS agora */
    public function atualizar()
    {
        return $this->executar(function () {
            $dados = $this->corpo();

            return $this->respond(['success' => true, 'data' => $this->service->atualizarDoSgs(
                !empty($dados['codigo']) ? strtoupper((string) $dados['codigo']) : null,
                !empty($dados['historico'])
            )]);
        }, 'atualizar índices');
    }

    protected function corpo(): array
    {
        return $this->request->getJSON(true) ?: ($this->request->getRawInput() ?: $this->request->getPost());
    }

    protected function executar(callable $acao, string $rotulo)
    {
        try {
            return $acao();
        } catch (LiquidacaoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->erros()], $e->status());
        } catch (Throwable $e) {
            log_message('error', "[ApiV2\\Indices] Erro ao {$rotulo}: " . $e->getMessage());

            return $this->respond(['success' => false, 'message' => "Erro ao {$rotulo}."], 500);
        }
    }
}
