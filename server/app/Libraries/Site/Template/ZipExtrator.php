<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

/**
 * Extrai um `.zip` de template para uma pasta, com defesas contra pacote malicioso:
 * limites de arquivos e de tamanho (contados byte a byte na extração, não pelo que o zip
 * declara), caminhos absolutos ou com `..` (zip slip), nomes estranhos, links simbólicos
 * e arquivos criptografados são recusados. A pasta única de nível superior que muitos
 * zips trazem é removida.
 */
class ZipExtrator
{
    public function __construct(
        private int $maxArquivos = 500,
        private int $maxBytesTotal = 30 * 1048576,
        private int $maxBytesArquivo = 5 * 1048576,
    ) {
    }

    /**
     * @param string $zip Caminho do .zip
     * @param string $destino Pasta (será criada) que recebe o conteúdo
     * @return array<string, string> erros por entrada (vazio = extraído)
     */
    public function extrair(string $zip, string $destino): array
    {
        $arquivo = new \ZipArchive();
        $r = $arquivo->open($zip, \ZipArchive::RDONLY);
        if ($r !== true) {
            return ['zip' => 'Arquivo .zip inválido ou corrompido.'];
        }

        try {
            $entradas = $this->lista($arquivo, $erros);
            if ($erros !== []) {
                return $erros;
            }

            $prefixo = $this->pastaUnica($entradas);
            if (!is_dir($destino) && !mkdir($destino, 0775, true)) {
                return ['zip' => 'Não foi possível criar a pasta de extração.'];
            }

            $total = 0;
            foreach ($entradas as $indice => $nome) {
                $relativo = $prefixo !== '' ? substr($nome, strlen($prefixo)) : $nome;
                $alvo = $destino . '/' . rtrim($relativo, '/');

                if (str_ends_with($nome, '/')) {
                    if ($relativo !== '' && !is_dir($alvo)) {
                        mkdir($alvo, 0775, true);
                    }
                    continue;
                }
                if (!is_dir(dirname($alvo))) {
                    mkdir(dirname($alvo), 0775, true);
                }

                $erro = $this->grava($arquivo, $indice, $alvo, $total);
                if ($erro !== null) {
                    return [$nome => $erro];
                }
            }
        } finally {
            $arquivo->close();
        }

        return [];
    }

    /**
     * @param array<string, string> $erros
     * @return array<int, string> índice => nome normalizado
     */
    private function lista(\ZipArchive $zip, ?array &$erros): array
    {
        $erros = [];
        if ($zip->numFiles === 0) {
            $erros['zip'] = 'O arquivo .zip está vazio.';
            return [];
        }
        if ($zip->numFiles > $this->maxArquivos) {
            $erros['zip'] = "O pacote tem mais de {$this->maxArquivos} arquivos.";
            return [];
        }

        $entradas = [];
        $declarado = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $nome = (string) $stat['name'];

            if ($nome === '' || str_contains($nome, "\0") || str_contains($nome, '\\') || str_starts_with($nome, '/')
                || preg_match('#(^|/)\.\.(/|$)#', $nome) || preg_match('/^[A-Za-z]:/', $nome) || preg_match('/[\x00-\x1F\x7F]/', $nome)) {
                $erros[$nome] = 'Caminho inválido no pacote.';
                continue;
            }
            if ($this->ehLinkSimbolico($zip, $i)) {
                $erros[$nome] = 'Links simbólicos não são permitidos.';
                continue;
            }
            if ((int) ($stat['encryption_method'] ?? 0) !== 0) {
                $erros[$nome] = 'Arquivos criptografados não são permitidos.';
                continue;
            }
            if ($stat['size'] > $this->maxBytesArquivo) {
                $erros[$nome] = 'Arquivo grande demais (máximo de ' . round($this->maxBytesArquivo / 1048576) . ' MB).';
                continue;
            }
            $declarado += $stat['size'];
            $entradas[$i] = $nome;
        }
        if ($declarado > $this->maxBytesTotal) {
            $erros['zip'] = 'O pacote descompactado passa de ' . round($this->maxBytesTotal / 1048576) . ' MB.';
        }

        return $entradas;
    }

    private function ehLinkSimbolico(\ZipArchive $zip, int $indice): bool
    {
        $sistema = 0;
        $atributos = 0;
        if (!$zip->getExternalAttributesIndex($indice, $sistema, $atributos)) {
            return false;
        }

        return $sistema === \ZipArchive::OPSYS_UNIX && (($atributos >> 16) & 0170000) === 0120000;
    }

    /** Se todos os caminhos começam pela mesma pasta, devolve `pasta/`; senão ''. */
    private function pastaUnica(array $entradas): string
    {
        $primeiros = [];
        foreach ($entradas as $nome) {
            if (!str_contains($nome, '/')) {
                return ''; // arquivo solto na raiz
            }
            $primeiros[explode('/', $nome, 2)[0]] = true;
        }

        return count($primeiros) === 1 ? array_key_first($primeiros) . '/' : '';
    }

    /** Copia a entrada contando os bytes lidos; passar do limite aborta (zip bomb). */
    private function grava(\ZipArchive $zip, int $indice, string $alvo, int &$total): ?string
    {
        $origem = $zip->getStream($zip->getNameIndex($indice));
        $destino = fopen($alvo, 'wb');
        if ($origem === false || $destino === false) {
            return 'Não foi possível extrair.';
        }

        $lidos = 0;
        while (!feof($origem)) {
            $bloco = fread($origem, 65536);
            if ($bloco === false) {
                break;
            }
            $lidos += strlen($bloco);
            $total += strlen($bloco);
            if ($lidos > $this->maxBytesArquivo || $total > $this->maxBytesTotal) {
                fclose($origem);
                fclose($destino);
                @unlink($alvo);
                return 'O conteúdo real passa do limite de tamanho.';
            }
            fwrite($destino, $bloco);
        }
        fclose($origem);
        fclose($destino);
        @chmod($alvo, 0644);

        return null;
    }
}
