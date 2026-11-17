<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Arquiva a tabela `auditoria`: exporta os registros para um arquivo JSON Lines
 * compactado (gzip), confere o arquivo e só então apaga os registros exportados
 * e otimiza a tabela.
 *
 * Garantias:
 *  - nada é apagado se a exportação ou a conferência falhar;
 *  - só são apagados os registros que entraram no arquivo (id até o maior id
 *    exportado), então registros gravados durante a execução são preservados;
 *  - o arquivo só ganha o nome final depois de conferido.
 */
class AuditoriaArquivamentoService
{
    public const TABELA = 'auditoria';

    /** Registros lidos/apagados por vez, para não estourar a memória. */
    public const LOTE = 1000;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * @param int      $manterDias 0 = arquiva tudo; N = mantém na tabela os registros dos últimos N dias
     * @param string   $diretorio  pasta de destino do backup (criada se não existir)
     * @param bool     $otimizar   roda OPTIMIZE TABLE ao final (só MySQL/MariaDB)
     * @param bool     $simular    só informa o que seria feito; não grava nem apaga nada
     * @param callable $log        função(string $mensagem) para acompanhar o andamento
     *
     * @return array{total:int, arquivo:?string, sha256:?string, apagados:int, otimizada:bool}
     */
    public function arquivar(int $manterDias, string $diretorio, bool $otimizar = true, bool $simular = false, ?callable $log = null): array
    {
        $log ??= static function (string $m): void {};
        $limite = $manterDias > 0 ? date('Y-m-d H:i:s', strtotime("-{$manterDias} days")) : null;

        $total = $this->elegiveis($limite)->countAllResults();
        $maxId = (int) ($this->elegiveis($limite)->selectMax('id')->get()->getRowArray()['id'] ?? 0);

        if ($total === 0) {
            $log('Nenhum registro de auditoria para arquivar.');

            return ['total' => 0, 'arquivo' => null, 'sha256' => null, 'apagados' => 0, 'otimizada' => false];
        }

        if ($simular) {
            $log("Simulação: {$total} registro(s) seriam arquivados (id até {$maxId}).");

            return ['total' => $total, 'arquivo' => null, 'sha256' => null, 'apagados' => 0, 'otimizada' => false];
        }

        [$arquivo, $sha256] = $this->exportar($limite, $maxId, $total, $diretorio, $log);

        $apagados = $this->apagar($limite, $maxId, $total, $log);

        $otimizada = false;
        if ($otimizar) {
            $otimizada = $this->otimizar($log);
        }

        return ['total' => $total, 'arquivo' => $arquivo, 'sha256' => $sha256, 'apagados' => $apagados, 'otimizada' => $otimizada];
    }

    /** Consulta base: registros elegíveis (antigos o bastante) para arquivar. */
    private function elegiveis(?string $limite)
    {
        $b = $this->db->table(self::TABELA);
        if ($limite !== null) {
            $b->groupStart()->where('created_at IS NULL', null, false)->orWhere('created_at <', $limite)->groupEnd();
        }

        return $b;
    }

    /**
     * Grava, confere e nomeia o arquivo definitivo.
     *
     * @return array{0:string,1:string} caminho final e sha256
     */
    private function exportar(?string $limite, int $maxId, int $esperado, string $diretorio, callable $log): array
    {
        if (!is_dir($diretorio) && !@mkdir($diretorio, 0o750, true) && !is_dir($diretorio)) {
            throw new RuntimeException("Não foi possível criar a pasta de backup: {$diretorio}");
        }

        $base     = rtrim($diretorio, '/\\') . DIRECTORY_SEPARATOR . 'auditoria_' . date('Ymd_His');
        $final    = $base . '.jsonl.gz';
        $temporario = $base . '.jsonl.gz.parcial';

        $gz = gzopen($temporario, 'wb9');
        if ($gz === false) {
            throw new RuntimeException("Não foi possível criar o arquivo de backup: {$temporario}");
        }

        $gravados = 0;
        $ultimoId = 0;
        try {
            while (true) {
                $linhas = $this->elegiveis($limite)
                    ->where('id >', $ultimoId)
                    ->where('id <=', $maxId)
                    ->orderBy('id', 'ASC')
                    ->limit(self::LOTE)
                    ->get()
                    ->getResultArray();

                if ($linhas === []) {
                    break;
                }

                foreach ($linhas as $linha) {
                    $json = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    if ($json === false || gzwrite($gz, $json . "\n") === false) {
                        throw new RuntimeException('Falha ao gravar o backup da auditoria.');
                    }
                    $gravados++;
                    $ultimoId = (int) $linha['id'];
                }
                $log("Exportados {$gravados} de {$esperado}...");
            }
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($temporario);
            throw $e;
        }
        gzclose($gz);

        // Conferência: relê o arquivo compactado e compara contagem e último id.
        try {
            $this->conferir($temporario, $gravados, $ultimoId);
            if ($gravados !== $esperado) {
                throw new RuntimeException("Exportados {$gravados} registros, mas eram esperados {$esperado}; nada foi apagado.");
            }
        } catch (\Throwable $e) {
            @unlink($temporario);
            throw $e;
        }

        if (!rename($temporario, $final)) {
            @unlink($temporario);
            throw new RuntimeException("Não foi possível finalizar o arquivo de backup: {$final}");
        }

        $sha256 = hash_file('sha256', $final);
        file_put_contents($final . '.sha256', $sha256 . '  ' . basename($final) . "\n");
        $log("Backup conferido: {$final} ({$gravados} registros, sha256 {$sha256}).");

        return [$final, $sha256];
    }

    private function conferir(string $arquivo, int $esperado, int $ultimoId): void
    {
        $gz = gzopen($arquivo, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Backup ilegível na conferência; nada foi apagado.');
        }

        $linhas = 0;
        $ultimo = 0;
        while (($linha = gzgets($gz)) !== false) {
            $dado = json_decode($linha, true);
            if (!is_array($dado) || !isset($dado['id'])) {
                gzclose($gz);
                throw new RuntimeException('Backup com linha inválida na conferência; nada foi apagado.');
            }
            $linhas++;
            $ultimo = (int) $dado['id'];
        }
        $fimNormal = gzeof($gz);
        gzclose($gz);

        if (!$fimNormal || $linhas !== $esperado || $ultimo !== $ultimoId) {
            throw new RuntimeException("Conferência do backup falhou ({$linhas} de {$esperado} linhas); nada foi apagado.");
        }
    }

    private function apagar(?string $limite, int $maxId, int $esperado, callable $log): int
    {
        $apagados = 0;

        while (true) {
            $ids = array_column(
                $this->elegiveis($limite)->select('id')->where('id <=', $maxId)->orderBy('id', 'ASC')->limit(self::LOTE)->get()->getResultArray(),
                'id'
            );
            if ($ids === []) {
                break;
            }

            $this->db->table(self::TABELA)->whereIn('id', $ids)->delete();
            $apagados += count($ids);
            $log("Apagados {$apagados} de {$esperado}...");
        }

        if ($apagados !== $esperado) {
            $log("Atenção: {$apagados} apagados, {$esperado} exportados (houve alteração concorrente).");
        }

        return $apagados;
    }

    private function otimizar(callable $log): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            $log('OPTIMIZE TABLE ignorado: o banco não é MySQL/MariaDB.');

            return false;
        }

        $tabela = $this->db->protectIdentifiers(self::TABELA, true);
        $this->db->query("OPTIMIZE TABLE {$tabela}");
        $log('Tabela auditoria otimizada.');

        return true;
    }
}
