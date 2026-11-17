<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use RuntimeException;
use ZipArchive;

/**
 * Gerador mínimo de planilha XLSX (uma aba, números e textos), sem dependências além de ext-zip.
 */
final class Xlsx
{
    /**
     * @param list<list<int|float|string|null>> $linhas
     *
     * @return string conteúdo binário do .xlsx
     */
    public static function gerar(array $linhas, string $aba = 'Liquidação'): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão zip do PHP é necessária para gerar XLSX.');
        }

        $arquivo = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip     = new ZipArchive();
        if ($zip->open($arquivo, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::xml(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $aba), 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', self::planilha($linhas));
        $zip->close();

        $conteudo = (string) file_get_contents($arquivo);
        @unlink($arquivo);

        return $conteudo;
    }

    private static function planilha(array $linhas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_values($linhas) as $i => $linha) {
            $xml .= '<row r="' . ($i + 1) . '">';
            foreach (array_values($linha) as $j => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                $ref = self::coluna($j) . ($i + 1);
                $xml .= is_int($valor) || is_float($valor)
                    ? '<c r="' . $ref . '"><v>' . $valor . '</v></c>'
                    : '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $valor) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private static function coluna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }

        return $letras;
    }

    private static function xml(string $texto): string
    {
        // remove caracteres de controle inválidos em XML 1.0
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto) ?? '';

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
