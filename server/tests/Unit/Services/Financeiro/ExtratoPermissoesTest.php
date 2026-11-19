<?php

namespace Tests\Unit\Services\Financeiro;

use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

/**
 * Cada ação de escrita dos extratos exige a sua permissão específica (importar, conciliar, lançar),
 * está no catálogo e chega aos grupos que têm `financeiro.*`.
 */
class ExtratoPermissoesTest extends CIUnitTestCase
{
    private const PERMISSOES = ['financeiro.extratos_importar', 'financeiro.extratos_conciliar', 'financeiro.extratos_lancar'];

    public function testPermissoesEstaoNoCatalogo(): void
    {
        $catalogo = (new AuthGroups())->permissions;
        foreach (self::PERMISSOES as $p) {
            $this->assertArrayHasKey($p, $catalogo);
            $this->assertNotSame('', $catalogo[$p]);
        }
    }

    public function testGruposComFinanceiroCuringaRecebemAsTres(): void
    {
        $matriz = (new AuthGroups())->matrix;
        foreach (['superadmin', 'admin'] as $grupo) {
            $this->assertContains('financeiro.*', $matriz[$grupo], "grupo {$grupo}");
        }
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function rotas(): array
    {
        return [
            'previa'        => ["post('extratos/previa'", 'importar'],
            'importar'      => ["post('extratos/importar'", 'importar'],
            'conciliar'     => ["/conciliar'", 'conciliar'],
            'desconciliar'  => ["/desconciliar'", 'conciliar'],
            'ignorar'       => ["/ignorar'", 'conciliar'],
            'conciliar lote' => ["post('extratos/conciliacao/lote'", 'conciliar'],
            'lancar'        => ["/lancar'", 'lancar'],
            'lancar lote'   => ["lancar-lote'", 'lancar'],
            'criar regra'   => ["post('extratos/regras'", 'lancar'],
            'editar regra'  => ["put('extratos/regras/(:num)'", 'lancar'],
            'excluir regra' => ["delete('extratos/regras/(:num)'", 'lancar'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function testRotaExigeAPermissaoEspecifica(string $trecho, string $acao): void
    {
        $linhas = array_filter(
            explode("\n", (string) file_get_contents(APPPATH . 'Routes/apiV2.php')),
            static fn (string $l): bool => str_contains($l, $trecho) && str_contains($l, 'extratos')
        );

        $this->assertNotEmpty($linhas, "rota {$trecho} não encontrada");
        foreach ($linhas as $linha) {
            $this->assertStringContainsString("'filter' => 'permission:financeiro.extratos_{$acao}'", $linha);
        }
    }

    public function testNenhumaRotaDeEscritaDeExtratoFicouComPermissaoGenerica(): void
    {
        foreach (explode("\n", (string) file_get_contents(APPPATH . 'Routes/apiV2.php')) as $linha) {
            if (preg_match('/->(post|put|delete)\(\'extratos\//', $linha) && !str_contains($linha, 'comprovantes')) {
                $this->assertStringContainsString('permission:financeiro.extratos_', $linha, $linha);
            }
        }
    }
}
