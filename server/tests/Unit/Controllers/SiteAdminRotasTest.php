<?php

namespace Tests\Unit\Controllers;

use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Toda rota do conteúdo gerenciável do site (issues #648 e #654) existe e está atrás dos filtros `auth`,
 * `modulo:site` e da permissão do seu grupo (site.conteudo, site.publicar, site.configuracoes, site.templates).
 */
class SiteAdminRotasTest extends CIUnitTestCase
{
    public static function rotas(): array
    {
        $caminhos = [
            ['get', 'configuracoes'], ['put', 'configuracoes'], ['patch', 'configuracoes'], ['get', 'template/schema'], ['get', 'template/tema'], ['put', 'template/tema'], ['patch', 'template/tema'], ['delete', 'template/tema'],
            ['get', 'paginas'], ['post', 'paginas'], ['get', 'paginas/(:num)'], ['put', 'paginas/(:num)'], ['patch', 'paginas/(:num)'], ['delete', 'paginas/(:num)'],
            ['post', 'paginas/(:num)/publicar'], ['post', 'paginas/(:num)/despublicar'], ['post', 'paginas/(:num)/duplicar'], ['get', 'paginas/(:num)/preview-token'],
            ['post', 'paginas/(:num)/secoes'], ['put', 'paginas/(:num)/secoes/ordem'], ['put', 'secoes/(:num)'], ['patch', 'secoes/(:num)'], ['delete', 'secoes/(:num)'],
            ['get', 'templates'], ['post', 'templates'], ['post', 'templates/(:segment)/ativar'], ['get', 'templates/(:segment)/paginas-exemplo'], ['post', 'templates/(:segment)/paginas-exemplo'], ['get', 'templates/(:segment)/exportar'], ['delete', 'templates/(:segment)'],
            ['get', 'menus'], ['put', 'menus/(:segment)'],
            ['get', 'noticias/categorias'], ['post', 'noticias/categorias'], ['get', 'noticias/categorias/(:num)'], ['put', 'noticias/categorias/(:num)'], ['patch', 'noticias/categorias/(:num)'], ['delete', 'noticias/categorias/(:num)'],
            ['get', 'midias'], ['get', 'midias/(:num)'], ['post', 'midias'], ['put', 'midias/(:num)'], ['patch', 'midias/(:num)'], ['get', 'midias/(:num)/usos'], ['delete', 'midias/(:num)'],
        ];

        return array_combine(array_map(fn ($c) => strtoupper($c[0]) . ' ' . $c[1], $caminhos), $caminhos);
    }

    /** Carrega só `app/Routes/site.php` numa coleção nova (no ambiente de testes a coleção global vem vazia). */
    private static function rotasDoSite(): RouteCollection
    {
        $routes = new RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/site.php';

        return $routes;
    }

    /** Permissão exigida por caminho; o que não está aqui exige `site.conteudo`. */
    private static function permissaoEsperada(string $caminho): string
    {
        return match (true) {
            str_contains($caminho, '/publicar'), str_contains($caminho, '/despublicar') => 'permission:site.publicar',
            $caminho === 'configuracoes', $caminho === 'template/tema' => 'permission:site.configuracoes',
            $caminho === 'template/schema' => 'permission:site.conteudo,site.configuracoes',
            str_starts_with($caminho, 'templates') => 'permission:site.templates',
            default => 'permission:site.conteudo',
        };
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotas')]
    public function testRotaExisteEExigeAutenticacaoModuloEPermissao(string $verbo, string $caminho): void
    {
        $rotas = self::rotasDoSite();
        $completo = "api/v2/site/admin/{$caminho}";
        // O CI guarda `(:num)` e `(:segment)` já convertidos em expressões regulares.
        $chave = strtr($completo, ['(:num)' => '([0-9]+)', '(:segment)' => '([^/]+)']);

        $this->assertArrayHasKey($chave, $rotas->getRoutes(strtoupper($verbo)), "{$verbo} {$caminho} não está registrada");
        $filtros = (array) ($rotas->getRoutesOptions($chave, strtoupper($verbo))['filter'] ?? []);

        $this->assertContains('auth', $filtros, "{$verbo} {$caminho} sem filtro auth");
        $this->assertContains('modulo:site', $filtros, "{$verbo} {$caminho} sem filtro do módulo Site");
        $this->assertContains(self::permissaoEsperada($caminho), $filtros, "{$verbo} {$caminho} sem a permissão esperada (filtros: " . implode(' | ', $filtros) . ')');
        $this->assertCount(1, array_filter($filtros, static fn ($f) => str_starts_with($f, 'permission:')), "{$verbo} {$caminho} com mais de uma permissão");
    }

    public function testRotasDeNoticiasTambemExigemPermissao(): void
    {
        $rotas = self::rotasDoSite();
        foreach (['noticias', 'noticias/categorias', 'noticias/([0-9]+)'] as $caminho) {
            $filtros = (array) ($rotas->getRoutesOptions("api/v2/site/admin/{$caminho}", 'GET')['filter'] ?? []);
            $this->assertContains('permission:site.conteudo', $filtros, $caminho);
            $this->assertContains('auth', $filtros, $caminho);
        }
    }

    public function testMatrizDePermissoes(): void
    {
        $auth = new \Config\AuthGroups();
        foreach (['site.conteudo', 'site.configuracoes', 'site.publicar', 'site.templates'] as $permissao) {
            $this->assertArrayHasKey($permissao, $auth->permissions);
        }
        $this->assertContains('site.*', $auth->matrix['superadmin']);
        $this->assertNotContains('site.templates', $auth->matrix['admin'], 'admin não instala templates');
        $this->assertNotContains('site.*', $auth->matrix['admin']);
        foreach (['site.conteudo', 'site.configuracoes', 'site.publicar'] as $permissao) {
            $this->assertContains($permissao, $auth->matrix['admin']);
        }
        foreach (['advogado', 'estagiario'] as $grupo) {
            $this->assertSame([], preg_grep('/^site\./', $auth->matrix[$grupo] ?? []), "{$grupo} não edita o site por padrão");
        }
    }

    public function testModuloSiteNoCatalogo(): void
    {
        $this->assertSame('Site', \App\Database\Seeds\ModulosSeeder::CATALOGO['site']);
    }
}
