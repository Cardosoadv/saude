<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use App\DTO\ExtratoArquivo;
use App\DTO\MovimentoExtrato;
use App\Services\Financeiro\ChaveMovimentoExtrato;
use InvalidArgumentException;

/**
 * Leitor tolerante de OFX 1.x (SGML, tags sem fechamento) e 2.x (XML).
 *
 * Não usa libxml: o conteúdo é tokenizado por expressão regular, então não há
 * DTD nem entidades externas (XXE). Os dois formatos viram a mesma árvore.
 *
 * Primeira versão: uma conta bancária (BANKACCTFROM) por arquivo; cartão de
 * crédito e múltiplas contas são rejeitados com mensagem explícita.
 */
final class OfxParser implements ParserExtratoInterface
{
    public const TAMANHO_MAX_PADRAO = 5 * 1024 * 1024;

    private const MIMES_ACEITOS = [
        'text/plain', 'application/x-ofx', 'application/ofx', 'application/octet-stream', 'text/xml', 'application/xml',
    ];

    /** Tags que contêm outras tags (o resto é folha). */
    private const AGREGADOS = [
        'OFX', 'SIGNONMSGSRSV1', 'SONRS', 'STATUS', 'FI', 'BANKMSGSRSV1', 'STMTTRNRS', 'STMTRS',
        'BANKACCTFROM', 'BANKTRANLIST', 'STMTTRN', 'LEDGERBAL', 'AVAILBAL', 'CCACCTFROM',
        'CREDITCARDMSGSRSV1', 'CCSTMTTRNRS', 'CCSTMTRS', 'BANKACCTTO', 'PAYEE', 'INVSTMTMSGSRSV1',
        'SECLISTMSGSRSV1', 'INVSTMTTRNRS', 'INVSTMTRS', 'MKTGINFO', 'BALLIST', 'BAL', 'INVACCTFROM',
        'LOANMSGSRSV1', 'TRNRS', 'SIGNUPMSGSRSV1', 'PROFMSGSRSV1', 'EMAILMSGSRSV1', 'TAXMSGSRSV1',
    ];

    public function __construct(private readonly int $tamanhoMaxBytes = self::TAMANHO_MAX_PADRAO)
    {
    }

    public function validarArquivo(string $nomeOriginal, ?string $mime, int $tamanhoBytes): void
    {
        if (strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION)) !== 'ofx') {
            throw self::erro('a extensão do arquivo deve ser .ofx.');
        }
        if ($mime !== null && $mime !== '' && !in_array(strtolower($mime), self::MIMES_ACEITOS, true)) {
            throw self::erro('tipo de arquivo não aceito (' . $mime . ').');
        }
        if ($tamanhoBytes <= 0) {
            throw self::erro('arquivo vazio.');
        }
        if ($tamanhoBytes > $this->tamanhoMaxBytes) {
            $mb = round($this->tamanhoMaxBytes / 1048576, 1);
            throw self::erro("arquivo maior que o limite de {$mb} MB.");
        }
    }

    public function parse(string $conteudo): ExtratoArquivo
    {
        if (strlen($conteudo) > $this->tamanhoMaxBytes) {
            throw self::erro('arquivo maior que o limite permitido.');
        }
        if (trim($conteudo) === '') {
            throw self::erro('arquivo vazio.');
        }

        $texto = $this->paraUtf8($conteudo);
        $corpo = $this->extrairCorpo($texto);
        $raiz  = $this->montarArvore($corpo);

        if ($this->descendentes($raiz, 'CCSTMTRS') !== [] || $this->descendentes($raiz, 'CCACCTFROM') !== []) {
            throw self::erro('extratos de cartão de crédito ainda não são suportados.');
        }
        $contas = $this->descendentes($raiz, 'STMTRS');
        if (count($contas) > 1 || count($this->descendentes($raiz, 'STMTTRNRS')) > 1) {
            throw self::erro('o arquivo contém mais de uma conta; envie um arquivo por conta.');
        }
        if ($contas === []) {
            throw self::erro('não foi encontrado nenhum extrato bancário (STMTRS).');
        }

        $stmt = $contas[0];
        $conta = $this->primeiro($stmt, 'BANKACCTFROM');
        if ($conta === null) {
            throw self::erro('conta bancária (BANKACCTFROM) ausente.');
        }
        $acctId = trim($this->campo($conta, 'ACCTID'));
        if ($acctId === '') {
            throw self::erro('número da conta (ACCTID) ausente.');
        }

        $lista = $this->primeiro($stmt, 'BANKTRANLIST');
        $nos   = $lista === null ? [] : array_values(array_filter(
            $lista['filhos'],
            static fn (array $n): bool => $n['tag'] === 'STMTTRN'
        ));

        $movimentos = $this->montarMovimentos($nos);

        $saldo = $this->primeiro($stmt, 'LEDGERBAL');
        $saldoCentavos = null;
        $saldoData = null;
        if ($saldo !== null && trim($this->campo($saldo, 'BALAMT')) !== '') {
            $saldoCentavos = $this->centavos($this->campo($saldo, 'BALAMT'), 'saldo (BALAMT)');
            $saldoData = $this->data($this->campo($saldo, 'DTASOF'), 'DTASOF', true);
        }

        return new ExtratoArquivo(
            banco: trim($this->campo($conta, 'BANKID')),
            agencia: trim($this->campo($conta, 'BRANCHID')),
            conta: $acctId,
            tipoConta: trim($this->campo($conta, 'ACCTTYPE')),
            moeda: trim($this->campo($stmt, 'CURDEF')),
            periodoInicio: $lista === null ? null : $this->data($this->campo($lista, 'DTSTART'), 'DTSTART', true),
            periodoFim: $lista === null ? null : $this->data($this->campo($lista, 'DTEND'), 'DTEND', true),
            saldoFinalCentavos: $saldoCentavos,
            saldoData: $saldoData,
            movimentos: $movimentos,
        );
    }

    /**
     * @param list<array<string,mixed>> $nos
     * @return list<MovimentoExtrato>
     */
    private function montarMovimentos(array $nos): array
    {
        $brutos = [];
        foreach ($nos as $i => $no) {
            $n = $i + 1;
            $brutos[] = [
                'fitid'  => trim($this->campo($no, 'FITID')),
                'data'   => $this->data($this->campo($no, 'DTPOSTED'), "DTPOSTED da transação {$n}", false),
                'valor'  => $this->centavos($this->campo($no, 'TRNAMT'), "valor (TRNAMT) da transação {$n}"),
                'tipo'   => trim($this->campo($no, 'TRNTYPE')),
                'memo'   => trim($this->campo($no, 'MEMO')),
                'nome'   => trim($this->campo($no, 'NAME')),
                'check'  => trim($this->campo($no, 'CHECKNUM')),
                'ref'    => trim($this->campo($no, 'REFNUM')),
            ];
        }

        // FITID repetido no arquivo: nenhuma das ocorrências é confiável.
        $contagem = [];
        foreach ($brutos as $b) {
            if ($this->fitidValido($b['fitid'])) {
                $contagem[$b['fitid']] = ($contagem[$b['fitid']] ?? 0) + 1;
            }
        }

        // CHECKNUM/REFNUM constantes no arquivo todo (ex.: Banco Inter) são ignorados.
        $variaCheck = count(array_unique(array_column($brutos, 'check'))) > 1;
        $variaRef   = count(array_unique(array_column($brutos, 'ref'))) > 1;

        $ocorrencias = [];
        $movimentos  = [];
        foreach ($brutos as $b) {
            $confiavel = $this->fitidValido($b['fitid']) && $contagem[$b['fitid']] === 1;

            $ordem = 1;
            if (!$confiavel) {
                $trio = implode('|', [$b['data'], $b['valor'], ChaveMovimentoExtrato::normalizarMemo($b['memo'])]);
                $ocorrencias[$trio] = ($ocorrencias[$trio] ?? 0) + 1;
                $ordem = $ocorrencias[$trio];
            }

            $documento = null;
            if ($variaRef && $b['ref'] !== '') {
                $documento = $b['ref'];
            } elseif ($variaCheck && $b['check'] !== '') {
                $documento = $b['check'];
            }

            $movimentos[] = new MovimentoExtrato(
                fitid: $confiavel ? $b['fitid'] : null,
                fitidConfiavel: $confiavel,
                data: $b['data'],
                valorCentavos: $b['valor'],
                tipoBanco: $b['tipo'],
                memo: $b['memo'],
                nome: $b['nome'],
                documento: $documento,
                ordemNoDia: $ordem,
            );
        }

        return $movimentos;
    }

    private function fitidValido(string $fitid): bool
    {
        return $fitid !== '' && strlen($fitid) <= 255 && !preg_match('/[\x00-\x1F\x7F]/', $fitid);
    }

    /** Converte DTPOSTED e similares para Y-m-d, mantendo o dia escrito no arquivo (sem aplicar fuso). */
    private function data(string $bruto, string $rotulo, bool $opcional): ?string
    {
        $bruto = trim($bruto);
        if ($bruto === '') {
            if ($opcional) {
                return null;
            }
            throw self::erro("{$rotulo} ausente.");
        }
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})(?:\d{2}(?:\d{2}(?:\d{2})?)?)?(?:\.\d+)?(?:\[[^\]]*\])?$/', $bruto, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw self::erro("data inválida em {$rotulo}.");
        }

        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }

    /** Valor decimal (ponto ou vírgula) para centavos inteiros, sem float. */
    private function centavos(string $bruto, string $rotulo): int
    {
        $v = trim($bruto);
        if ($v === '') {
            throw self::erro("{$rotulo} ausente.");
        }
        // Zeros à direita além de 2 casas (ex.: "10.500") não mudam o valor.
        $v = preg_replace('/([.,]\d{2})0+$/', '$1', $v) ?? $v;

        try {
            return ChaveMovimentoExtrato::paraCentavos($v);
        } catch (InvalidArgumentException) {
            throw self::erro("{$rotulo} inválido.");
        }
    }

    private function paraUtf8(string $conteudo): string
    {
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
        $cabeca   = substr($conteudo, 0, 2048);

        // Já é UTF-8 válido (ou ASCII): não reconverter, para não corromper acentos.
        if (mb_check_encoding($conteudo, 'UTF-8')) {
            return $conteudo;
        }

        $charset = null;
        if (preg_match('/^\s*CHARSET\s*:\s*(\S+)/mi', $cabeca, $m)) {
            $charset = strtoupper($m[1]);
        } elseif (preg_match('/encoding\s*=\s*["\']([^"\']+)["\']/i', $cabeca, $m)) {
            $charset = strtoupper($m[1]);
        }

        // Sem UTF-8 válido, qualquer charset de 8 bits ocidental (ou ausente) é lido como CP1252.
        if ($charset !== null && !in_array($charset, ['1252', 'WINDOWS-1252', 'CP1252', 'ISO-8859-1', 'LATIN1', '8859-1', 'NONE', 'USASCII', 'ASCII'], true)) {
            throw self::erro("codificação não suportada ({$charset}).");
        }

        $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');

        return is_string($convertido) ? $convertido : throw self::erro('não foi possível converter a codificação.');
    }

    /** Remove cabeçalho SGML/XML e devolve a partir de <OFX>. */
    private function extrairCorpo(string $texto): string
    {
        $pos = stripos($texto, '<OFX>');
        if ($pos === false) {
            throw self::erro('marcador <OFX> não encontrado; o arquivo não parece ser OFX.');
        }
        $corpo = substr($texto, $pos);
        // Sem DTD, instruções de processamento nem CDATA/comentários: nada disso é interpretado.
        $corpo = preg_replace('/<!--.*?-->|<!\[CDATA\[(.*?)\]\]>|<![^>]*>|<\?.*?\?>/s', '$1', $corpo) ?? $corpo;

        return $corpo;
    }

    /**
     * @return array<string,mixed> nó raiz: ['tag','campos'=>[tag=>[valores]],'filhos'=>[nós]]
     */
    private function montarArvore(string $corpo): array
    {
        preg_match_all('/<(\/?)([A-Za-z0-9_.]+)\s*>([^<]*)/', $corpo, $tokens, PREG_SET_ORDER);

        $raiz  = ['tag' => '#raiz', 'campos' => [], 'filhos' => []];
        $pilha = [&$raiz];
        $fechouOfx = false;

        foreach ($tokens as [, $barra, $tag, $texto]) {
            $tag = strtoupper($tag);
            $atual = &$pilha[count($pilha) - 1];

            if ($barra === '') {
                if (in_array($tag, self::AGREGADOS, true)) {
                    $atual['filhos'][] = ['tag' => $tag, 'campos' => [], 'filhos' => []];
                    $pilha[] = &$atual['filhos'][count($atual['filhos']) - 1];
                } else {
                    $atual['campos'][$tag][] = $this->decodificar($texto);
                }
            } elseif (in_array($tag, self::AGREGADOS, true)) {
                if (count($pilha) < 2 || $atual['tag'] !== $tag) {
                    throw self::erro("estrutura inesperada: fechamento </{$tag}> sem abertura correspondente.");
                }
                array_pop($pilha);
                $fechouOfx = $fechouOfx || $tag === 'OFX';
            }
            // Fechamento de folha (XML) é ignorado.
            unset($atual);
        }

        if (count($pilha) !== 1 || !$fechouOfx) {
            throw self::erro('arquivo truncado ou incompleto (estrutura não foi fechada).');
        }

        return $raiz;
    }

    private function decodificar(string $texto): string
    {
        return html_entity_decode(trim($texto), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param array<string,mixed> $no */
    private function campo(array $no, string $tag): string
    {
        return (string) ($no['campos'][$tag][0] ?? '');
    }

    /**
     * @param array<string,mixed> $no
     * @return list<array<string,mixed>>
     */
    private function descendentes(array $no, string $tag): array
    {
        $achados = [];
        foreach ($no['filhos'] as $filho) {
            if ($filho['tag'] === $tag) {
                $achados[] = $filho;
            }
            array_push($achados, ...$this->descendentes($filho, $tag));
        }

        return $achados;
    }

    /**
     * @param array<string,mixed> $no
     * @return array<string,mixed>|null
     */
    private function primeiro(array $no, string $tag): ?array
    {
        return $this->descendentes($no, $tag)[0] ?? null;
    }

    private static function erro(string $detalhe): ExtratoInvalidoException
    {
        return new ExtratoInvalidoException('arquivo OFX inválido: ' . $detalhe);
    }
}
