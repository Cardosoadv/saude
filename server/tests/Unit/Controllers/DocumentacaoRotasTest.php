<?php

namespace Tests\Unit\Controllers;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Rotas de leitura da documentação interna (issue #703): existem e estão atrás
 * de `permission:documentacao.access` e `modulo:documentacao`.
 */
class DocumentacaoRotasTest extends CIUnitTestCase
{
    private static function rotas(): RouteCollection
    {
        $routes = new RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/documentacao.php';

        return $routes;
    }

    public function testArquivoDeRotasEstaRegistrado(): void
    {
        $this->assertContains(APPPATH . 'Routes/documentacao.php', config('Routing')->routeFiles);
    }

    public function testRotasExigemPermissaoEModulo(): void
    {
        $esperadas = [
            'api/v2/documentacao'                    => '\App\Controllers\ApiV2\Documentacao::index',
            'api/v2/documentacao/busca'              => '\App\Controllers\ApiV2\Documentacao::busca',
            'api/v2/documentacao/paginas/(.*)'       => '\App\Controllers\ApiV2\Documentacao::pagina/$1',
            'api/v2/documentacao/tela/([^/]+)'       => '\App\Controllers\ApiV2\Documentacao::tela/$1',
            'api/v2/documentacao/imagens/(.*)'       => '\App\Controllers\ApiV2\Documentacao::imagem/$1',
        ];
        $rotas = self::rotas();
        $get = $rotas->getRoutes('GET');

        foreach ($esperadas as $caminho => $destino) {
            $this->assertArrayHasKey($caminho, $get, "GET {$caminho} não está registrada");
            $this->assertSame($destino, $get[$caminho]);

            $filtros = (array) ($rotas->getRoutesOptions($caminho, 'GET')['filter'] ?? []);
            $this->assertContains('permission:documentacao.access', $filtros, $caminho);
            $this->assertContains('modulo:documentacao', $filtros, $caminho);
        }
    }

    public function testRotasDeEdicaoExigemDocumentacaoEditar(): void
    {
        $esperadas = [
            ['GET', 'api/v2/documentacao/admin/paginas', 'index'],
            ['POST', 'api/v2/documentacao/admin/paginas', 'create'],
            ['GET', 'api/v2/documentacao/admin/paginas/([0-9]+)', 'show/$1'],
            ['PUT', 'api/v2/documentacao/admin/paginas/([0-9]+)', 'update/$1'],
            ['DELETE', 'api/v2/documentacao/admin/paginas/([0-9]+)', 'delete/$1'],
            ['POST', 'api/v2/documentacao/admin/paginas/([0-9]+)/publicar', 'publicar/$1'],
            ['POST', 'api/v2/documentacao/admin/paginas/([0-9]+)/despublicar', 'despublicar/$1'],
            ['POST', 'api/v2/documentacao/admin/paginas/de-arquivo/(.*)', 'deArquivo/$1'],
            ['POST', 'api/v2/documentacao/admin/preview', 'preview'],
            ['POST', 'api/v2/documentacao/admin/imagens', 'imagem'],
        ];
        $rotas = self::rotas();

        foreach ($esperadas as [$verbo, $caminho, $metodo]) {
            $this->assertSame('\\App\\Controllers\\ApiV2\\DocumentacaoAdmin::' . $metodo, $rotas->getRoutes($verbo)[$caminho] ?? null, "{$verbo} {$caminho}");
            $filtros = (array) ($rotas->getRoutesOptions($caminho, $verbo)['filter'] ?? []);
            $this->assertContains('permission:documentacao.editar', $filtros, $caminho);
            $this->assertContains('modulo:documentacao', $filtros, $caminho);
        }
    }
}
