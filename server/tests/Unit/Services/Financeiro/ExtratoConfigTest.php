<?php

namespace Tests\Unit\Services\Financeiro;

use App\Services\ConfiguracoesService;
use App\Services\Financeiro\ExtratoConfig;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Limites editáveis dos extratos: valor válido vale; ausente, inválido ou fora da faixa volta ao padrão.
 */
class ExtratoConfigTest extends CIUnitTestCase
{
    private function config(array|\Throwable $valores): ExtratoConfig
    {
        $servico = new class ($valores) extends ConfiguracoesService {
            public function __construct(private array|\Throwable $valores)
            {
            }

            public function getValor(string $nome, $default = null)
            {
                if ($this->valores instanceof \Throwable) {
                    throw $this->valores;
                }

                return $this->valores[$nome] ?? $default;
            }
        };

        return new ExtratoConfig($servico);
    }

    public function testPadroesQuandoNaoHaConfiguracao(): void
    {
        $c = $this->config([]);

        $this->assertSame(5 * 1048576, $c->tamanhoMaxBytes());
        $this->assertSame(3, $c->janelaDias());
        $this->assertSame(50, $c->limiteLote());
        $this->assertSame(500, $c->limiteLoteConciliacao());
    }

    public function testValoresConfiguradosValem(): void
    {
        $c = $this->config([
            ExtratoConfig::MAX_MB => '10', ExtratoConfig::JANELA_DIAS => '7',
            ExtratoConfig::LIMITE_LOTE => '20', ExtratoConfig::LIMITE_LOTE_CONCILIACAO => '100',
        ]);

        $this->assertSame(10 * 1048576, $c->tamanhoMaxBytes());
        $this->assertSame(7, $c->janelaDias());
        $this->assertSame(20, $c->limiteLote());
        $this->assertSame(100, $c->limiteLoteConciliacao());
    }

    public function testZeroDeJanelaEPermitido(): void
    {
        $this->assertSame(0, $this->config([ExtratoConfig::JANELA_DIAS => '0'])->janelaDias());
    }

    /** @return array<string,array{0:mixed}> */
    public static function invalidos(): array
    {
        return ['texto' => ['abc'], 'vazio' => [''], 'negativo' => ['-4'], 'acima do máximo' => ['9999'], 'zero em campo que exige 1+' => ['0']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidos')]
    public function testValorInvalidoVoltaAoPadrao(mixed $valor): void
    {
        $c = $this->config([ExtratoConfig::LIMITE_LOTE => $valor, ExtratoConfig::MAX_MB => $valor]);

        $this->assertSame(50, $c->limiteLote());
        $this->assertSame(5 * 1048576, $c->tamanhoMaxBytes());
    }

    public function testFalhaAoLerConfiguracaoUsaPadrao(): void
    {
        $this->assertSame(50, $this->config(new \RuntimeException('sem tabela'))->limiteLote());
    }

    public function testDefinicoesDaMigrationCobremTodasAsConfiguracoes(): void
    {
        $this->assertSame(
            [ExtratoConfig::MAX_MB, ExtratoConfig::JANELA_DIAS, ExtratoConfig::LIMITE_LOTE, ExtratoConfig::LIMITE_LOTE_CONCILIACAO],
            array_keys(ExtratoConfig::DEFINICOES)
        );
        foreach (ExtratoConfig::DEFINICOES as [$padrao, $min, $max, $descricao]) {
            $this->assertGreaterThanOrEqual($min, $padrao);
            $this->assertLessThanOrEqual($max, $padrao);
            $this->assertNotSame('', $descricao);
        }
    }
}
