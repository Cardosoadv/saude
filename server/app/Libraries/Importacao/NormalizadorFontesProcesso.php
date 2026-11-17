<?php

declare(strict_types=1);

namespace App\Libraries\Importacao;

/**
 * Converte as respostas do DJEN e do DataJud para um mesmo formato de dados
 * de processo, usado na conferência da importação em lote.
 *
 * Formato devolvido (campos ausentes ficam null ou lista vazia):
 *  - numeroprocessocommascara, siglaTribunal, nomeOrgao, tipoDocumento (classe),
 *    codigoClasse, dataDistribuicao (Y-m-d), link
 *  - poloAtivo / poloPassivo: nomes das partes (apenas DJEN)
 *  - sigilo: bool|null (apenas DataJud, nivelSigilo > 0)
 *  - assuntos, graus, sistema (DataJud); total_publicacoes, ultima_publicacao (DJEN)
 *
 * A API pública do DataJud não traz as partes (anonimizadas pela LGPD); elas
 * vêm só do DJEN. Em processos com sigilo o DJEN costuma trazer apenas as
 * iniciais dos nomes.
 */
class NormalizadorFontesProcesso
{
    /**
     * Junta as comunicações do DJEN de um processo.
     *
     * Tribunal, órgão, classe e link vêm da comunicação mais recente que os
     * tenha; as partes são a união, sem repetição, de todas as comunicações.
     *
     * @param array $itens Itens ("items") devolvidos pela API do DJEN.
     */
    public static function deDjen(array $itens): array
    {
        $dados = self::vazio();
        if ($itens === []) {
            return $dados;
        }

        usort($itens, static fn (array $a, array $b): int => strcmp(
            (string) ($b['data_disponibilizacao'] ?? ''),
            (string) ($a['data_disponibilizacao'] ?? '')
        ));

        $mapa = [
            'numeroprocessocommascara' => 'numeroprocessocommascara',
            'siglaTribunal'            => 'siglaTribunal',
            'nomeOrgao'                => 'nomeOrgao',
            'tipoDocumento'            => 'nomeClasse',
            'codigoClasse'             => 'codigoClasse',
            'link'                     => 'link',
        ];

        foreach ($mapa as $campo => $chave) {
            foreach ($itens as $item) {
                $valor = trim((string) ($item[$chave] ?? ''));
                if ($valor !== '') {
                    $dados[$campo] = $valor;
                    break;
                }
            }
        }

        // O DJEN às vezes devolve a classe com caixa misturada ("AçãO TRABALHISTA").
        if ($dados['tipoDocumento'] !== null) {
            $dados['tipoDocumento'] = mb_strtoupper($dados['tipoDocumento']);
        }
        if ($dados['nomeOrgao'] !== null) {
            $dados['nomeOrgao'] = mb_strtoupper($dados['nomeOrgao']);
        }

        $vistos = [];
        foreach ($itens as $item) {
            foreach ($item['destinatarios'] ?? [] as $destinatario) {
                $nome = trim((string) ($destinatario['nome'] ?? ''));
                $polo = (string) ($destinatario['polo'] ?? '');
                if ($nome === '' || !in_array($polo, ['A', 'P'], true)) {
                    continue;
                }

                $chave = $polo . '|' . self::comparavel($nome);
                if (isset($vistos[$chave])) {
                    continue;
                }
                $vistos[$chave] = true;
                $dados[$polo === 'A' ? 'poloAtivo' : 'poloPassivo'][] = $nome;
            }
        }

        $dados['total_publicacoes'] = count($itens);
        $dados['ultima_publicacao'] = $itens[0]['data_disponibilizacao'] ?? null;

        return $dados;
    }

    /**
     * Junta os registros ("hits") do DataJud de um processo, um por grau.
     *
     * Classe e órgão vêm do registro atualizado mais recentemente; a data de
     * distribuição é o ajuizamento mais antigo.
     *
     * @param array $hits Lista em hits.hits da resposta do DataJud.
     */
    public static function deDatajud(array $hits): array
    {
        $dados = self::vazio();
        $fontes = array_values(array_filter(array_map(
            static fn ($h) => is_array($h) ? ($h['_source'] ?? $h) : null,
            $hits
        )));

        if ($fontes === []) {
            return $dados;
        }

        usort($fontes, static fn (array $a, array $b): int => strcmp(
            (string) ($b['dataHoraUltimaAtualizacao'] ?? ''),
            (string) ($a['dataHoraUltimaAtualizacao'] ?? '')
        ));
        $recente = $fontes[0];

        $numero = preg_replace('/\D/', '', (string) ($recente['numeroProcesso'] ?? '')) ?? '';
        if (strlen($numero) === 20) {
            $dados['numeroprocessocommascara'] = sprintf(
                '%s-%s.%s.%s.%s.%s',
                substr($numero, 0, 7),
                substr($numero, 7, 2),
                substr($numero, 9, 4),
                substr($numero, 13, 1),
                substr($numero, 14, 2),
                substr($numero, 16, 4)
            );
        }

        $dados['siglaTribunal'] = self::texto($recente['tribunal'] ?? null);
        $dados['nomeOrgao']     = self::texto($recente['orgaoJulgador']['nome'] ?? null);
        $dados['tipoDocumento'] = self::texto($recente['classe']['nome'] ?? null);
        $dados['codigoClasse']  = isset($recente['classe']['codigo']) ? (string) $recente['classe']['codigo'] : null;
        $dados['sistema']       = self::texto($recente['sistema']['nome'] ?? null);

        $datas = array_filter(array_map(
            static fn (array $f): ?string => self::dataAjuizamento($f['dataAjuizamento'] ?? null),
            $fontes
        ));
        sort($datas);
        $dados['dataDistribuicao'] = $datas[0] ?? null;

        $dados['graus'] = array_values(array_unique(array_filter(array_map(
            static fn (array $f): string => (string) ($f['grau'] ?? ''),
            $fontes
        ))));

        $assuntos = [];
        foreach ($fontes as $f) {
            foreach ($f['assuntos'] ?? [] as $assunto) {
                // O DataJud às vezes aninha a lista de assuntos.
                foreach (isset($assunto['nome']) ? [$assunto] : (array) $assunto as $a) {
                    if (is_array($a) && !empty($a['nome'])) {
                        $assuntos[$a['nome']] = true;
                    }
                }
            }
        }
        $dados['assuntos'] = array_keys($assuntos);

        $sigilo = false;
        foreach ($fontes as $f) {
            if ((int) ($f['nivelSigilo'] ?? 0) > 0) {
                $sigilo = true;
            }
        }
        $dados['sigilo'] = $sigilo;

        return $dados;
    }

    /**
     * Texto em forma comparável: minúsculas, sem acento e com espaços simples.
     * Usado para saber se dois valores de fontes diferentes são o mesmo.
     */
    public static function comparavel(?string $texto): string
    {
        $texto = mb_strtolower(trim((string) $texto));
        $semAcento = class_exists(\Transliterator::class)
            ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($texto)
            : null;
        $texto = $semAcento ?? strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Converte a data de ajuizamento do DataJud, que vem como "20030515135200"
     * ou em ISO 8601, para Y-m-d.
     */
    public static function dataAjuizamento(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    private static function texto(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private static function vazio(): array
    {
        return [
            'numeroprocessocommascara' => null,
            'siglaTribunal'            => null,
            'nomeOrgao'                => null,
            'tipoDocumento'            => null,
            'codigoClasse'             => null,
            'dataDistribuicao'         => null,
            'link'                     => null,
            'poloAtivo'                => [],
            'poloPassivo'              => [],
            'sigilo'                   => null,
        ];
    }
}
