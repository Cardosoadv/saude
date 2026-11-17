<?php

declare(strict_types=1);

namespace App\Libraries\Contabil;

use CognitioTechny\Contabil\Application\Ports\PdfRendererInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renderizador de relatórios contábeis para formato PDF via Dompdf.
 *
 * @version 1.0.0
 */
class SaudeContabilPdfRenderer implements PdfRendererInterface
{
    public function render(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
