<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\RelatorioLiquidacao;
use App\Libraries\Liquidacao\Xlsx;
use Throwable;

/**
 * Memória de cálculo da liquidação em PDF, CSV e XLSX, pronta para juntar aos autos.
 * O acesso à liquidação (e ao processo) é o de LiquidacoesService.
 */
class LiquidacaoRelatorioService
{
    protected LiquidacoesService $liquidacoes;
    protected ?ProcessosService $processos;
    protected ?PdfService $pdf;

    public function __construct(?LiquidacoesService $liquidacoes = null, ?ProcessosService $processos = null, ?PdfService $pdf = null)
    {
        $this->liquidacoes = $liquidacoes ?? new LiquidacoesService();
        $this->processos   = $processos;
        $this->pdf         = $pdf;
    }

    /** Dados do relatório (ver RelatorioLiquidacao::montar). */
    public function relatorio(int $liquidacaoId): array
    {
        $liquidacao = $this->liquidacoes->getById($liquidacaoId);
        if (!$liquidacao) {
            throw LiquidacaoException::naoEncontrada();
        }

        $memorias = [];
        foreach ($liquidacao['itens'] ?? [] as $item) {
            $memorias[(int) $item['item_id']] = $this->liquidacoes->memoriaDoItem((int) $item['item_id']);
        }

        return RelatorioLiquidacao::montar($liquidacao, $memorias, $this->contexto($liquidacao));
    }

    public function pdf(int $liquidacaoId): string
    {
        $relatorio = $this->relatorio($liquidacaoId);
        $html      = view('liquidacoes/memoria_calculo', ['relatorio' => $relatorio, 'escritorio' => $this->escritorio()]);

        return ($this->pdf ??= new PdfService())->render($html, "liquidacao-{$liquidacaoId}.pdf", [], 'A4', 'landscape', false) ?? '';
    }

    public function csv(int $liquidacaoId): string
    {
        return RelatorioLiquidacao::csv($this->relatorio($liquidacaoId));
    }

    public function xlsx(int $liquidacaoId): string
    {
        return Xlsx::gerar(RelatorioLiquidacao::linhasXlsx($this->relatorio($liquidacaoId)));
    }

    /** Processo, cliente e partes. Falha na busca não impede o relatório: sai sem esses dados. */
    protected function contexto(array $liquidacao): array
    {
        $processo = $liquidacao['processo'] ?? [];
        $contexto = [
            'processo' => $processo['numeroprocessocommascara'] ?? $processo['numero_processo'] ?? null,
            'cliente'  => null,
            'poloAtivo'   => [],
            'poloPassivo' => [],
        ];

        try {
            $detalhes = ($this->processos ??= new ProcessosService())->buscarDetalhesProcesso((int) $liquidacao['processo_id']);
            $contexto['cliente']     = $detalhes['processo']['nome_cliente'] ?? null;
            $contexto['poloAtivo']   = array_values(array_filter(array_column($detalhes['poloAtivo'] ?? [], 'nome')));
            $contexto['poloPassivo'] = array_values(array_filter(array_column($detalhes['poloPassivo'] ?? [], 'nome')));
        } catch (Throwable $e) {
            log_message('warning', '[LiquidacaoRelatorioService] Partes indisponíveis: ' . $e->getMessage());
        }

        return $contexto;
    }

    protected function escritorio(): string
    {
        try {
            return \App\Libraries\Site\DadosEscritorio::atual()->nome();
        } catch (Throwable) {
            return '';
        }
    }
}
