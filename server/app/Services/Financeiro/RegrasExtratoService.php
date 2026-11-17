<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Models\Financeiro\FinanceiroExtratosRegrasModel;
use App\Repositories\Financeiro\ExtratosRegrasRepository;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;

/**
 * Regras de classificação de movimentos de extrato: padrão de texto + sinal →
 * fornecedor/cliente, categoria e rateio padrão.
 *
 * As regras só produzem sugestão (`sugerir`); quem confirma e lança é o usuário.
 * O rateio da regra é guardado em percentuais por usuário: [{id, percentual}], somando 100.
 */
class RegrasExtratoService
{
    private ExtratosRegrasRepository $regras;

    public function __construct(?ExtratosRegrasRepository $regras = null)
    {
        $this->regras = $regras ?? new ExtratosRegrasRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listar(?int $contaId = null, bool $apenasAtivas = false): array
    {
        return array_map([$this, 'apresentar'], $this->regras->listar($contaId, $apenasAtivas));
    }

    /**
     * @return array<string,mixed>
     * @throws ExtratoNaoEncontradoException
     */
    public function obter(int $id): array
    {
        $r = $id > 0 ? $this->regras->obter($id) : null;
        if (!$r) {
            throw new ExtratoNaoEncontradoException('Regra não encontrada.');
        }

        return $this->apresentar($r);
    }

    /**
     * @param array<string,mixed> $dados nome, padrao, campo, sinal, conta_id, fornecedor_id, cliente_id, categoria_id, rateio, ativo
     * @return array<string,mixed>
     * @throws ExtratoInvalidoException
     */
    public function criar(array $dados, ?int $userId = null): array
    {
        $linha = $this->normalizar($dados) + ['criado_por' => $userId];
        $linha += ['campo' => 'ambos', 'sinal' => 'ambos', 'ativo' => 1];

        try {
            $id = $this->regras->criar($linha);
        } catch (\InvalidArgumentException $e) {
            throw new ExtratoInvalidoException($e->getMessage());
        }

        log_message('info', sprintf('[ExtratoRegras] regra=%d criada por usuario=%s', $id, $userId ?? '-'));

        return $this->obter($id);
    }

    /**
     * @param array<string,mixed> $dados Só os campos informados são alterados.
     * @return array<string,mixed>
     * @throws ExtratoNaoEncontradoException
     * @throws ExtratoInvalidoException
     */
    public function atualizar(int $id, array $dados, ?int $userId = null): array
    {
        $this->obter($id);
        $linha = $this->normalizar($dados, true);
        if (!$linha) {
            throw new ExtratoInvalidoException('Nenhum campo informado para atualizar.');
        }

        try {
            $this->regras->atualizar($id, $linha);
        } catch (\InvalidArgumentException $e) {
            throw new ExtratoInvalidoException($e->getMessage());
        }

        log_message('info', sprintf('[ExtratoRegras] regra=%d atualizada por usuario=%s', $id, $userId ?? '-'));

        return $this->obter($id);
    }

    /** @throws ExtratoNaoEncontradoException */
    public function excluir(int $id, ?int $userId = null): void
    {
        $this->obter($id);
        $this->regras->excluir($id);

        log_message('info', sprintf('[ExtratoRegras] regra=%d excluída por usuario=%s', $id, $userId ?? '-'));
    }

    /**
     * Primeira regra que casa com o movimento (a de padrão mais longo, depois a mais antiga),
     * ou null. Só sugere: não altera nada.
     *
     * @param array<string,mixed> $movimento conta_id, valor_centavos, memo, nome_contraparte
     * @return array<string,mixed>|null
     */
    public function sugerir(array $movimento): ?array
    {
        $saida   = (int) $movimento['valor_centavos'] < 0;
        $melhor  = null;
        $tamanho = -1;

        foreach ($this->regras->listarAtivasDaConta((int) $movimento['conta_id']) as $r) {
            if (($r['sinal'] === 'entrada' && $saida) || ($r['sinal'] === 'saida' && !$saida)) {
                continue;
            }
            if (!$this->casa($r, $movimento)) {
                continue;
            }
            $t = mb_strlen($this->normalizarTexto((string) $r['padrao']));
            if ($t > $tamanho) {
                $melhor  = $r;
                $tamanho = $t;
            }
        }

        return $melhor ? $this->apresentar($melhor) : null;
    }

    /**
     * Valida o rateio em percentuais: [{id, percentual}], somando 100.
     *
     * @param mixed $rateio
     * @return list<array{id:int,percentual:float}>
     * @throws ExtratoInvalidoException
     */
    public static function validarRateioPercentual($rateio): array
    {
        if ($rateio === null || $rateio === '' || $rateio === []) {
            return [];
        }
        if (!is_array($rateio)) {
            throw new ExtratoInvalidoException('O rateio deve ser uma lista de {id, percentual}.');
        }

        $saida = [];
        $vistos = [];
        $soma  = 0.0;
        foreach ($rateio as $item) {
            $id = (int) ($item['id'] ?? 0);
            $p  = $item['percentual'] ?? null;
            if ($id <= 0 || !is_numeric($p) || (float) $p <= 0) {
                throw new ExtratoInvalidoException('Cada item do rateio precisa de id e de percentual maior que zero.');
            }
            if (isset($vistos[$id])) {
                throw new ExtratoInvalidoException('O mesmo usuário não pode aparecer duas vezes no rateio.');
            }
            $vistos[$id] = true;
            $soma       += (float) $p;
            $saida[]     = ['id' => $id, 'percentual' => round((float) $p, 4)];
        }

        if (abs($soma - 100.0) > 0.01) {
            throw new ExtratoInvalidoException('Os percentuais do rateio precisam somar 100%.');
        }

        return $saida;
    }

    /** Texto em minúsculas, sem acentos, só letras/números separados por espaço. */
    public function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    /**
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $mov
     */
    private function casa(array $regra, array $mov): bool
    {
        $padrao = $this->normalizarTexto((string) $regra['padrao']);
        if ($padrao === '') {
            return false;
        }

        $textos = match ($regra['campo']) {
            'contraparte' => [(string) ($mov['nome_contraparte'] ?? '')],
            'memo'        => [(string) ($mov['memo'] ?? '')],
            default       => [(string) ($mov['nome_contraparte'] ?? ''), (string) ($mov['memo'] ?? '')],
        };

        foreach ($textos as $t) {
            if (str_contains(' ' . $this->normalizarTexto($t) . ' ', ' ' . $padrao . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     * @throws ExtratoInvalidoException
     */
    private function normalizar(array $dados, bool $parcial = false): array
    {
        $linha = [];

        foreach (['nome', 'padrao'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $linha[$campo] = trim((string) $dados[$campo]);
            }
        }
        foreach (['campo' => FinanceiroExtratosRegrasModel::CAMPOS, 'sinal' => FinanceiroExtratosRegrasModel::SINAIS] as $campo => $validos) {
            if (array_key_exists($campo, $dados)) {
                if (!in_array($dados[$campo], $validos, true)) {
                    throw new ExtratoInvalidoException("Valor inválido para {$campo}. Use: " . implode(', ', $validos) . '.');
                }
                $linha[$campo] = $dados[$campo];
            }
        }
        foreach (['conta_id', 'fornecedor_id', 'cliente_id', 'categoria_id'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $linha[$campo] = (int) $dados[$campo] > 0 ? (int) $dados[$campo] : null;
            }
        }
        if (array_key_exists('ativo', $dados)) {
            $linha['ativo'] = filter_var($dados['ativo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if (array_key_exists('rateio', $dados)) {
            $rateio          = self::validarRateioPercentual($dados['rateio']);
            $linha['rateio'] = $rateio ? json_encode($rateio, JSON_UNESCAPED_UNICODE) : null;
        }

        if (!$parcial) {
            if (empty($linha['nome'])) {
                $linha['nome'] = mb_substr($linha['padrao'] ?? '', 0, 100);
            }
            if (!empty($linha['fornecedor_id']) && !empty($linha['cliente_id'])) {
                throw new ExtratoInvalidoException('A regra deve indicar fornecedor ou cliente, não os dois.');
            }
        }

        if (isset($linha['padrao']) && mb_strlen($this->normalizarTexto($linha['padrao'])) < 3) {
            throw new ExtratoInvalidoException('O padrão deve ter ao menos 3 letras ou números.');
        }
        if (isset($linha['nome']) && mb_strlen($linha['nome']) > 100) {
            throw new ExtratoInvalidoException('O nome da regra deve ter no máximo 100 caracteres.');
        }

        return $linha;
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function apresentar(array $r): array
    {
        $rateio = $r['rateio'] ?? null;
        if (is_string($rateio) && $rateio !== '') {
            $rateio = json_decode($rateio, true);
        }

        return [
            'id_regra'      => (int) $r['id_regra'],
            'nome'          => $r['nome'],
            'conta_id'      => $r['conta_id'] !== null ? (int) $r['conta_id'] : null,
            'campo'         => $r['campo'],
            'padrao'        => $r['padrao'],
            'sinal'         => $r['sinal'],
            'fornecedor_id' => $r['fornecedor_id'] !== null ? (int) $r['fornecedor_id'] : null,
            'cliente_id'    => $r['cliente_id'] !== null ? (int) $r['cliente_id'] : null,
            'categoria_id'  => $r['categoria_id'] !== null ? (int) $r['categoria_id'] : null,
            'rateio'        => is_array($rateio) ? $rateio : [],
            'ativo'         => (int) $r['ativo'] === 1,
        ];
    }
}
