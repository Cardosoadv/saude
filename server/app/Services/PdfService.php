<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Serviço para geração de arquivos PDF utilizando Dompdf.
 */
class PdfService extends BaseService
{
    /**
     * Renderiza e envia um PDF para o navegador ou retorna o conteúdo do PDF.
     *
     * @param string $html Conteúdo HTML a ser renderizado.
     * @param string $filename Nome do arquivo PDF (opcional).
     * @param array $options Opções de configuração para o Dompdf.
     * @param string $paper Tamanho do papel (ex: 'A4').
     * @param string $orientation Orientação do papel ('portrait' ou 'landscape').
     * @param bool $stream Se true, faz o stream do PDF para o navegador. Se false, retorna o conteúdo binário.
     * @return string|null Conteúdo binário do PDF se $stream for false, null caso contrário.
     */
    public function render(
        string $html,
        string $filename = 'documento.pdf',
        array $options = [],
        string $paper = 'A4',
        string $orientation = 'portrait',
        bool $stream = true
    ): ?string {
        $dompdfOptions = new Options();
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdfOptions->set('isHtml5ParserEnabled', true);
        $dompdfOptions->set('defaultFont', 'dejavu sans');

        // Merge custom options
        foreach ($options as $key => $value) {
            $dompdfOptions->set($key, $value);
        }

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        if ($stream) {
            $dompdf->stream($filename, ['Attachment' => false]);
            return null;
        }

        return $dompdf->output();
    }
}
