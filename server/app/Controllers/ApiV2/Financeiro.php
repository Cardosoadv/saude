<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Financeiro\ExtratoService;
use App\Services\Financeiro\FinanceiroService;
use App\Services\Financeiro\DREService;
use App\Services\ClienteService;
use App\Services\PdfService;
use App\Repositories\Financeiro\CategoriasRepository;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller de API para o Módulo Financeiro Geral
 *
 * Centraliza os dados consoles para Balanço, Extrato e DRE.
 *
 * @package App\Controllers\Api
 */
class Financeiro extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ExtratoService $extratoService;
    protected FinanceiroService $financeiroService;
    protected DREService $dreService;
    protected ClienteService $clienteService;
    protected PdfService $pdfService;
    protected string $permissaoNecessaria = 'financeiro';

    public function __construct(
        ?ExtratoService $extratoService = null,
        ?FinanceiroService $financeiroService = null,
        ?DREService $dreService = null,
        ?ClienteService $clienteService = null,
        ?PdfService $pdfService = null,
        ?CategoriasRepository $categoriasRepository = null
    ) {
        $this->extratoService    = $extratoService    ?? new ExtratoService();
        $this->financeiroService = $financeiroService ?? new FinanceiroService();
        $this->dreService        = $dreService        ?? new DREService();
        $this->clienteService    = $clienteService    ?? new ClienteService();
        $this->pdfService        = $pdfService        ?? new PdfService();
    }

    /**
     * Retorna os dados do Balanço Financeiro
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function balanco()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar o balanço.');
        }

        $dataReferencia = $this->request->getGet('dataReferencia') ?? null;
        $resultado = $this->extratoService->getBalancoFinanceiro($dataReferencia);

        return $this->respond($resultado);
    }

    /**
     * Retorna os dados do Extrato por Período
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function extrato()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar o extrato.');
        }

        $conta_id = $this->request->getGet('conta_id') ?? 1;
        $dataInicial = $this->request->getGet('dataInicial') ?? date('Y-m-01');
        $dataFinal = $this->request->getGet('dataFinal') ?? date('Y-m-t');

        $extrato = $this->extratoService->getExtratoPorPeriodo($conta_id, $dataInicial, $dataFinal);

        $export = $this->request->getGet('export');
        if ($export === 'pdf') {
            return $this->_gerarPdfExtrato($extrato, $dataInicial, $dataFinal, false);
        } elseif ($export === 'pdf_rateio') {
            return $this->_gerarPdfExtrato($extrato, $dataInicial, $dataFinal, true);
        } elseif ($export === 'csv') {
            return $this->_gerarCsvExtrato($extrato, $dataInicial, $dataFinal);
        } elseif ($this->request->getGet('print') === 'true') {
            return $this->_gerarPdfExtrato($extrato, $dataInicial, $dataFinal, true);
        }

        return $this->respond($extrato);
    }

    /**
     * Retorna o relatório de categorias do período
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function relatorioCategorias()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar o relatório de categorias.');
        }

        $conta_id    = $this->request->getGet('conta_id') ?? 1;
        $dataInicial = $this->request->getGet('dataInicial') ?? date('Y-m-01');
        $dataFinal   = $this->request->getGet('dataFinal') ?? date('Y-m-t');

        $resultado = $this->extratoService->getRelatorioCategorias((int)$conta_id, (string)$dataInicial, (string)$dataFinal);

        return $this->respond($resultado);
    }

    /**
     * Retorna os dados do DRE
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function dre()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar o DRE.');
        }

        try {
            $dataInicio = $this->request->getGet('data_inicio') ?? date('Y-m-01');
            $dataFim = $this->request->getGet('data_fim') ?? date('Y-m-t');

            $dreData = $this->dreService->gerarDRE($dataInicio, $dataFim);

            return $this->respond($dreData);
        } catch (\Exception $e) {
            log_message('error', '[Api\Financeiro::dre] ' . $e->getMessage());
            return $this->failServerError('Erro ao gerar DRE.');
        }
    }

    /**
     * Retorna as movimentações de um cliente específico
     *
     * @param int $clienteId
     * @return \CodeIgniter\HTTP\Response
     */
    public function movimentacoes($clienteId = null)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar movimentações.');
        }

        if (!$clienteId) {
            return $this->fail('Cliente não especificado.');
        }

        $cliente = $this->clienteService->encontrarCliente($clienteId);
        if (!$cliente) {
            return $this->failNotFound('Cliente não encontrado.');
        }

        // Parâmetros de filtro consolidados para o banco de dados
        $filtros = [
            'categoria'   => $this->request->getGet('categoria'),
            'data_inicio' => $this->request->getGet('data_inicio'),
            'data_fim'    => $this->request->getGet('data_fim'),
            'search'      => $this->request->getGet('s'),
        ];

        // Otimizado: Delega a filtragem para o banco de dados via Repository
        $movimentacoes = $this->financeiroService->getMovimentacoesCliente($clienteId, $filtros);
        $resumo = $this->financeiroService->getResumoFinanceiro($clienteId);
        $dadosGraficos = $this->financeiroService->getDadosGraficos($clienteId);

        return $this->respond([
            'cliente' => $cliente,
            'movimentacoes' => $movimentacoes,
            'resumo' => $resumo,
            'graficos' => $dadosGraficos,
            'categorias' => array_values(array_unique(array_column($movimentacoes, 'categoria'))),
        ]);
    }

    private function _gerarPdfExtrato($extratoDados, $dataInicial, $dataFinal, $comRateio = true)
    {
        $transacoes = $extratoDados['transacoes'] ?? $extratoDados['data'] ?? [];

        $totalReceitas = 0.0;
        $totalDespesas = 0.0;
        foreach ($transacoes as $t) {
            $valor = (float) $t['valor'];
            if ($valor >= 0) {
                $totalReceitas += $valor;
            } else {
                $totalDespesas += $valor;
            }
        }

        $saldoAnterior = (float) ($extratoDados['saldo_anterior_total'] ?? 0);
        $saldoFinal    = (float) ($extratoDados['saldo_final'] ?? ($saldoAnterior + $totalReceitas + $totalDespesas));

        $moeda = static function (float $valor): string {
            return 'R$ ' . number_format($valor, 2, ',', '.');
        };
        $corValor = static function (float $valor): string {
            return $valor < 0 ? '#dc2626' : '#16a34a';
        };
        $esc = static fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page { margin: 90px 32px 60px 32px; }
            * { box-sizing: border-box; }
            body { font-family: "dejavu sans", sans-serif; color: #0f1923; font-size: 11px; margin: 0; }

            #cabecalho { position: fixed; top: -70px; left: 0; right: 0; height: 70px; }
            #cabecalho .titulo { font-size: 20px; font-weight: bold; color: #0f1923; }
            #cabecalho .barra { height: 4px; background: #c8922a; margin: 6px 0 8px 0; border-radius: 2px; }
            #cabecalho .periodo { font-size: 11px; color: #6b7280; }
            #cabecalho .emitido { font-size: 9px; color: #6b7280; float: right; }

            #rodape { position: fixed; bottom: -45px; left: 0; right: 0; height: 30px; border-top: 1px solid #e5e2db; padding-top: 6px; font-size: 9px; color: #6b7280; }
            #rodape .pagina { float: right; }
            #rodape .pagina:after { content: "Página " counter(page); }

            .resumo { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 14px; }
            .resumo td { width: 25%; background: #f8f7f4; border: 1px solid #e5e2db; border-left: 3px solid #c8922a; border-radius: 4px; padding: 8px 10px; vertical-align: top; }
            .resumo .rotulo { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 3px; }
            .resumo .valor { display: block; font-size: 13px; font-weight: bold; }

            table.extrato { width: 100%; border-collapse: collapse; font-size: 10.5px; }
            table.extrato thead th { background: #0f1923; color: #fff; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
            table.extrato thead th.num { text-align: right; }
            table.extrato tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e2db; vertical-align: top; }
            table.extrato tbody tr.par td { background: #f8f7f4; }
            table.extrato tbody td.num { text-align: right; white-space: nowrap; font-weight: bold; }
            table.extrato tbody td.data { white-space: nowrap; color: #374151; }
            table.extrato tbody td.vazio { color: #9ca3af; font-style: italic; }
            table.extrato tbody tr.rateio td { background: #fbfbfb; padding: 3px 8px 6px 24px; border-bottom: 1px solid #e5e2db; font-size: 9.5px; color: #374151; }
            table.extrato tbody tr.rateio strong { color: #0f1923; }

            h3.rateio-titulo { margin: 18px 0 8px 0; font-size: 13px; color: #0f1923; border-bottom: 2px solid #c8922a; padding-bottom: 4px; }
            table.rateio-acumulado { width: 100%; border-collapse: collapse; font-size: 10.5px; }
            table.rateio-acumulado td { padding: 5px 8px; border-bottom: 1px solid #e5e2db; }
            table.rateio-acumulado td.num { text-align: right; font-weight: bold; white-space: nowrap; }

            .vazio-lista { text-align: center; color: #6b7280; padding: 20px; font-style: italic; }
        </style></head><body>';

        $html .= '<div id="cabecalho">
            <span class="emitido">Emitido em ' . $esc(date('d/m/Y H:i')) . '</span>
            <div class="titulo">Extrato Financeiro</div>
            <div class="barra"></div>
            <div class="periodo">Período: ' . $esc(date('d/m/Y', strtotime($dataInicial))) . ' a ' . $esc(date('d/m/Y', strtotime($dataFinal))) . '</div>
        </div>';

        $html .= '<div id="rodape"><span class="pagina"></span>Ordo Juris &middot; Extrato Financeiro</div>';

        $html .= '<table class="resumo"><tr>
            <td><span class="rotulo">Saldo Anterior</span><span class="valor" style="color:' . $corValor($saldoAnterior) . '">' . $moeda($saldoAnterior) . '</span></td>
            <td><span class="rotulo">Receitas no Período</span><span class="valor" style="color:#16a34a">' . $moeda($totalReceitas) . '</span></td>
            <td><span class="rotulo">Despesas no Período</span><span class="valor" style="color:#dc2626">' . $moeda($totalDespesas) . '</span></td>
            <td><span class="rotulo">Saldo Final</span><span class="valor" style="color:' . $corValor($saldoFinal) . '">' . $moeda($saldoFinal) . '</span></td>
        </tr></table>';

        if (empty($transacoes)) {
            $html .= '<div class="vazio-lista">Nenhum movimento encontrado para o período selecionado.</div>';
        } else {
            $html .= '<table class="extrato"><thead><tr>
                <th>Data</th><th>Descrição</th><th>Cliente/Fornecedor</th><th class="num">Valor</th><th class="num">Saldo</th>
            </tr></thead><tbody>';

            foreach ($transacoes as $i => $t) {
                $valor = (float) $t['valor'];
                $clienteFornecedor = trim((string) ($t['cliente_fornecedor'] ?? ''));
                $classeLinha = $i % 2 === 0 ? 'par' : 'impar';

                $html .= '<tr class="' . $classeLinha . '">';
                $html .= '<td class="data">' . $esc(date('d/m/Y', strtotime($t['data']))) . '</td>';
                $html .= '<td>' . $esc($t['descricao'] ?? '') . '</td>';
                $html .= $clienteFornecedor !== ''
                    ? '<td>' . $esc($clienteFornecedor) . '</td>'
                    : '<td class="vazio">Transferência interna</td>';
                $html .= '<td class="num" style="color:' . $corValor($valor) . ';">' . $moeda($valor) . '</td>';
                $html .= '<td class="num">' . $moeda((float) $t['saldo']) . '</td>';
                $html .= '</tr>';

                if ($comRateio && !empty($t['rateio']) && is_array($t['rateio'])) {
                    $rateioStrings = [];
                    foreach ($t['rateio'] as $rt) {
                        $rateioStrings[] = $esc($rt['nome']) . ': <span style="color:' . $corValor((float) $rt['valor']) . '">' . $moeda((float) $rt['valor']) . '</span>';
                    }
                    $html .= '<tr class="rateio"><td colspan="5"><strong>Rateio:</strong> ' . implode(' &nbsp;|&nbsp; ', $rateioStrings) . '</td></tr>';
                }
            }

            $html .= '</tbody></table>';
        }

        if ($comRateio && !empty($extratoDados['rateio_acumulado'])) {
            $html .= '<h3 class="rateio-titulo">Rateio Acumulado</h3>';
            $html .= '<table class="rateio-acumulado">';
            foreach ($extratoDados['rateio_acumulado'] as $rateio) {
                $valorRateio = (float) $rateio['rateio_acumulado'];
                $html .= '<tr><td>' . $esc($rateio['nome']) . '</td><td class="num" style="color:' . $corValor($valorRateio) . '">' . $moeda($valorRateio) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';

        $pdfBinary = $this->pdfService->render($html, 'extrato.pdf', [], 'A4', 'portrait', false);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="extrato_' . date('Ymd', strtotime($dataInicial)) . '_' . date('Ymd', strtotime($dataFinal)) . '.pdf"')
            ->setBody($pdfBinary);
    }

    private function _gerarCsvExtrato($extratoDados, $dataInicial, $dataFinal)
    {
        $fileName = 'extrato_' . date('Ymd', strtotime($dataInicial)) . '_' . date('Ymd', strtotime($dataFinal)) . '.csv';

        $output = fopen('php://temp', 'w');
        // UTF-8 BOM para o Excel abrir corretamente com acentos
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

        fputcsv($output, ['Data', 'Descrição', 'Cliente/Fornecedor', 'Valor', 'Saldo'], ';');

        $transacoes = $extratoDados['transacoes'] ?? $extratoDados['data'] ?? [];

        foreach ($transacoes as $t) {
            fputcsv($output, [
                date('d/m/Y', strtotime($t['data'])),
                $t['descricao'] ?? '',
                $t['cliente_fornecedor'] ?? '',
                number_format((float) $t['valor'], 2, ',', ''),
                number_format((float) $t['saldo'], 2, ',', ''),
            ], ';');
        }

        rewind($output);
        $csvData = stream_get_contents($output);
        fclose($output);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->setBody($csvData);
    }
}
