<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AIServiceInterface;
use App\Libraries\Liquidacao\CriterioCalculo;
use App\Libraries\Liquidacao\LiquidacaoException;
use DateTimeImmutable;

/**
 * Sugere critérios de cálculo a partir do texto de uma sentença ou acórdão.
 *
 * Nada é gravado aqui: a sugestão volta para o advogado, que confere os trechos citados e só então
 * aplica o critério pelo cadastro normal (que valida de novo). A IA pode errar ou não achar o critério;
 * o resultado nunca substitui a leitura da decisão.
 */
class LiquidacaoCriteriosIaService
{
    public const MAX_TEXTO = 30000;

    protected LiquidacoesService $liquidacoes;
    protected ?IndicesEconomicosService $indices;
    protected ?AIServiceInterface $ia;

    public function __construct(?LiquidacoesService $liquidacoes = null, ?IndicesEconomicosService $indices = null, ?AIServiceInterface $ia = null)
    {
        $this->liquidacoes = $liquidacoes ?? new LiquidacoesService();
        $this->indices     = $indices;
        $this->ia          = $ia;
    }

    /**
     * @return array{sugestao: array, criterio: array, trechos: list<string>, observacoes: string, avisos: list<string>, requer_confirmacao: true}
     */
    public function sugerir(int $liquidacaoId, string $texto): array
    {
        $this->liquidacoes->liquidacaoComAcesso($liquidacaoId); // acesso ao processo

        $texto = trim($texto);
        if ($texto === '') {
            throw LiquidacaoException::invalida('Informe o trecho da sentença ou do acórdão.', ['texto' => 'Obrigatório.']);
        }
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            throw LiquidacaoException::invalida('Texto muito longo; envie só os trechos sobre correção, juros, multa e honorários (até ' . self::MAX_TEXTO . ' caracteres).', ['texto' => 'Texto muito longo.']);
        }

        $codigos = $this->codigosDosIndices();
        $bruto   = ($this->ia ??= new AIManagerService())->suggestLiquidacaoCriteria($texto, $codigos);

        return $this->montar($bruto, $codigos);
    }

    /** Converte a resposta da IA em critério válido, descartando (com aviso) o que não existe ou não confere. */
    public function montar(array $bruto, array $codigos): array
    {
        $avisos   = [];
        $criterio = [];

        $indice = $this->codigo($bruto['indice_correcao'] ?? null, $codigos, 'correção', $avisos);
        if ($indice !== null) {
            $criterio['correcao'] = ['indice' => $indice];
            if ($data = $this->data($bruto['termo_inicial_correcao'] ?? null, 'termo inicial da correção', $avisos)) {
                $criterio['correcao']['data_inicial'] = $data;
            }
        }

        $tipo = strtolower((string) ($bruto['juros_tipo'] ?? ''));
        if (in_array($tipo, ['simples', 'compostos'], true)) {
            $juros   = ['tipo' => $tipo];
            $jIndice = $this->codigo($bruto['juros_indice'] ?? null, $codigos, 'juros', $avisos);
            if ($jIndice !== null) {
                $juros['indice'] = $jIndice;
            } elseif (isset($bruto['juros_taxa_mensal']) && is_numeric($bruto['juros_taxa_mensal'])) {
                $juros['taxa_mensal'] = (string) $bruto['juros_taxa_mensal'];
            } else {
                $avisos[] = 'A decisão indica juros, mas a taxa ou o índice não foi identificado; informe manualmente.';
                $juros = null;
            }
            if ($juros !== null) {
                if ($data = $this->data($bruto['termo_inicial_juros'] ?? null, 'termo inicial dos juros', $avisos)) {
                    $juros['data_inicial'] = $data;
                }
                $criterio['juros'] = $juros;
            }
        }

        try {
            $criterio = CriterioCalculo::normalizar($criterio);
        } catch (LiquidacaoException $e) {
            $avisos[]  = 'A sugestão não passou na validação do critério (' . $e->getMessage() . '); confira manualmente.';
            $criterio = [];
        }

        $sugestao = [];
        foreach (['multa_percentual', 'honorarios_percentual'] as $campo) {
            if (isset($bruto[$campo]) && is_numeric($bruto[$campo]) && (float) $bruto[$campo] > 0 && (float) $bruto[$campo] <= 100) {
                $sugestao[$campo] = (string) $bruto[$campo];
            }
        }

        return [
            'sugestao'           => $sugestao + ['criterio' => $criterio],
            'criterio'           => $criterio,
            'trechos'            => array_values(array_filter(array_map('strval', (array) ($bruto['trechos'] ?? [])))),
            'observacoes'        => (string) ($bruto['observacoes'] ?? ''),
            'avisos'             => $avisos,
            'requer_confirmacao' => true,
        ];
    }

    /** @return list<string> */
    protected function codigosDosIndices(): array
    {
        $indices = ($this->indices ??= new IndicesEconomicosService())->listar();

        return array_values(array_map(
            static fn ($i) => (string) $i['codigo'],
            array_filter($indices, static fn ($i) => (int) ($i['ativo'] ?? 1) === 1)
        ));
    }

    private function codigo(mixed $valor, array $codigos, string $para, array &$avisos): ?string
    {
        if ($valor === null || $valor === '' || strtolower((string) $valor) === 'null') {
            return null;
        }
        foreach ($codigos as $c) {
            if (strcasecmp($c, (string) $valor) === 0) {
                return $c;
            }
        }
        $avisos[] = "Índice \"{$valor}\" sugerido para {$para} não está cadastrado; cadastre-o ou escolha outro.";

        return null;
    }

    private function data(mixed $valor, string $campo, array &$avisos): ?string
    {
        if ($valor === null || $valor === '' || strtolower((string) $valor) === 'null') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valor);
        if ($d === false || $d->format('Y-m-d') !== $valor) {
            $avisos[] = "Data inválida para {$campo} (\"{$valor}\"); ignorada.";

            return null;
        }

        return $valor;
    }
}
