<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\Template\Compatibilidade;
use App\Libraries\Site\Template\ExportadorDeConteudo;
use App\Libraries\Site\Template\PacoteTemplate;
use App\Libraries\Site\Template\SeedImporter;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\ZipExtrator;
use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use App\Models\Site\SiteTemplateModel;
use App\Repositories\Site\SiteTemplateRepository;
use Config\SiteTemplate;

/**
 * Ciclo de vida dos templates instaláveis (issue #650): instalar de um `.zip` ou de uma pasta,
 * listar, ativar, importar páginas de exemplo, exportar e remover.
 *
 * Princípios:
 *  - instalar NÃO ativa e ativar NÃO apaga nem sobrescreve conteúdo da clínica;
 *  - pacote enviado pelo painel (`upload`) nunca traz PHP; por linha de comando (`cli`) pode;
 *  - nada é gravado se a validação do pacote falhar; a troca de arquivos é feita por renomeação.
 */
class SiteTemplateGestaoService
{
    public function __construct(
        private ?SiteTemplateModel $templates = null,
        private ?SiteTemplateService $servico = null,
        private ?PacoteTemplate $pacote = null,
        private ?ZipExtrator $zip = null,
        private ?TemplateAssets $assets = null,
        private ?SiteTemplate $config = null,
    ) {
        $this->config    ??= config(SiteTemplate::class);
        $this->templates ??= new SiteTemplateModel();
        $this->servico   ??= new SiteTemplateService(config: $this->config);
        $this->pacote    ??= new PacoteTemplate();
        $this->zip       ??= new ZipExtrator();
        $this->assets    ??= new TemplateAssets($this->config);
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $saida = [];
        foreach ($this->templates->orderBy('nome', 'ASC')->findAll() as $t) {
            $manifesto = $this->servico->manifesto($t);
            $saida[] = [
                'slug' => $t['slug'], 'nome' => $t['nome'], 'versao' => $manifesto['versao'] ?? $t['versao'],
                'autor' => $t['autor'], 'descricao' => $t['descricao'], 'origem' => $t['origem'],
                'ativo' => (bool) $t['ativo'], 'motor' => $this->servico->motor($t, $manifesto),
                'removivel' => $t['origem'] !== 'core' && !$t['ativo'],
                'preview_url' => $this->previewUrl($t),
            ];
        }
        return $saida;
    }

    // ------------------------------------------------------------------ instalar

    /**
     * Instala um `.zip`. Do painel (`upload`) o pacote não pode ter PHP; pela linha de comando (`cli`) pode.
     *
     * @return array{template: array<string,mixed>, atualizado: bool, avisos: list<string>}
     * @throws ErroDeValidacao pacote recusado (erros e avisos do relatório)
     */
    public function instalarZip(string $arquivoZip, string $origem = 'upload'): array
    {
        $temporaria = $this->pastaTemporaria();
        try {
            $erros = $this->zip->extrair($arquivoZip, $temporaria);
            if ($erros !== []) {
                throw new ErroDeValidacao($erros);
            }

            return $this->instalarDaPasta($temporaria, $origem, mover: true);
        } finally {
            $this->removeArvore($temporaria);
        }
    }

    /** Instala uma pasta (linha de comando). Aceita PHP. */
    public function instalarPasta(string $pasta): array
    {
        return $this->instalarDaPasta($pasta, 'cli', mover: false);
    }

    /**
     * @return array{template: array<string,mixed>, atualizado: bool, avisos: list<string>}
     */
    private function instalarDaPasta(string $pasta, string $origem, bool $mover): array
    {
        ['resultado' => $res, 'manifesto' => $manifesto] = $this->pacote->validar($pasta, permitePhp: $origem === 'cli');
        if (!$res->valido()) {
            throw new ErroDeValidacao($res->erros(), $this->avisosComoLista($res));
        }

        $slug = $manifesto['slug'];
        $existente = $this->templates->where('slug', $slug)->first();
        $avisos = $this->avisosComoLista($res);

        if ($existente && $existente['origem'] !== $origem) {
            $dono = $existente['origem'] === 'core' ? 'do sistema' : 'instalado de outra forma';
            throw new ErroDeValidacao(['slug' => "Já existe um template '{$slug}' {$dono}; escolha outro slug."], $avisos);
        }
        if ($existente && version_compare($manifesto['versao'], (string) $existente['versao'], '<')) {
            $avisos[] = "A versão enviada ({$manifesto['versao']}) é mais antiga que a instalada ({$existente['versao']}).";
        }

        $destino = $this->servico->diretorio(['origem' => $origem, 'slug' => $slug]);
        $this->trocaArquivos($pasta, $destino, $mover);

        $dados = [
            'nome' => $manifesto['nome'], 'versao' => $manifesto['versao'], 'autor' => $manifesto['autor'] ?? null,
            'descricao' => $manifesto['descricao'] ?? null,
            'manifest' => json_encode($manifesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if ($existente) {
            $this->templates->update($existente['id'], $dados);
        } elseif (!$this->templates->insert($dados + ['slug' => $slug, 'origem' => $origem, 'ativo' => 0])) {
            throw new ErroDeValidacao($this->templates->errors(), $avisos);
        }

        $this->assets->publicar($slug, $manifesto['versao'], $destino);
        $this->limpaCaches();

        $linha = $this->templates->where('slug', $slug)->first();
        $this->auditar($existente ? 'ATUALIZAR_TPL' : 'INSTALAR_TPL', [
            'slug' => $slug, 'versao' => $manifesto['versao'], 'origem' => $origem,
            'versao_anterior' => $existente['versao'] ?? null, 'avisos' => $avisos,
        ]);

        return ['template' => $this->resumo($linha), 'atualizado' => $existente !== null, 'avisos' => $avisos];
    }

    // ------------------------------------------------------------------ ativar

    /**
     * Ativa o template sem alterar o conteúdo da clínica.
     *
     * - Seções de tipo que o novo template não tem continuam no banco (e voltam a aparecer se o
     *   tipo voltar a existir); o relatório as lista como `incompativel`. O renderizador as ignora.
     * - Seções compatíveis recebem os valores padrão de campos novos (só adiciona) e são revalidadas;
     *   o relatório lista as que têm erros.
     * - Só se o site não tem nenhuma página o conteúdo inicial do template é importado.
     *
     * @return array{template:string, ativado:bool, secoes: list<array<string,mixed>>, avisos: list<string>, seed: ?array}
     */
    public function ativar(string $slug): array
    {
        $linha = $this->templates->where('slug', $slug)->first() ?? throw new NaoEncontrado('Template não encontrado.');
        $manifesto = $this->servico->manifesto($linha);
        $tipos = $manifesto['secoes'] ?? [];

        $secoes = new SiteSecaoModel();
        $paginas = array_column((new SitePaginaModel())->findAll(), null, 'id');
        $lista = array_map(
            static fn (array $s) => ['id' => (int) $s['id'], 'tipo' => $s['tipo'], 'dados' => json_decode((string) $s['dados'], true) ?? []],
            $secoes->findAll()
        );

        $relatorio = [];
        $antes = array_column($lista, 'dados', 'id');
        $pagina_de = array_column($secoes->findAll(), 'pagina_id', 'id');
        foreach ((new Compatibilidade())->analisar($lista, $tipos) as $item) {
            $pagina = $paginas[$pagina_de[$item['id']] ?? 0] ?? null;

            if ($item['acao'] === 'manter' && $item['dados'] !== $antes[$item['id']]) {
                $secoes->update($item['id'], ['dados' => json_encode($item['dados'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            if ($item['acao'] === 'ocultar' || $item['erros'] !== []) {
                $relatorio[] = [
                    'id' => $item['id'], 'tipo' => $item['tipo'], 'pagina' => $pagina['titulo'] ?? '', 'pagina_id' => (int) ($pagina['id'] ?? 0),
                    'acao' => $item['acao'] === 'ocultar' ? 'incompativel' : 'revisar', 'erros' => $item['erros'],
                ];
            }
        }

        $avisos = $this->avisosDeAtivacao($manifesto, $paginas);

        $this->servico->ativar($slug);

        $seed = null;
        if ((new SitePaginaModel())->withDeleted()->countAllResults() === 0) {
            $seed = $this->importaSeed($linha, $manifesto, null, completo: true);
        }
        $this->limpaCaches();
        $this->auditar('ATIVAR_TPL', ['slug' => $slug, 'secoes' => $relatorio, 'avisos' => $avisos, 'seed_importado' => $seed !== null]);

        return ['template' => $slug, 'ativado' => true, 'secoes' => $relatorio, 'avisos' => $avisos, 'seed' => $seed];
    }

    /**
     * Cria como rascunho as páginas do conteúdo inicial do template cujo slug ainda não existe.
     * Não altera páginas existentes, configurações nem menus.
     */
    public function paginasExemplo(string $slug): array
    {
        $linha = $this->templates->where('slug', $slug)->first() ?? throw new NaoEncontrado('Template não encontrado.');

        $relatorio = $this->importaSeed($linha, $this->servico->manifesto($linha), 'rascunho', completo: false);
        if ($relatorio === null) {
            throw ErroDeValidacao::campo('template', 'Este template não traz conteúdo inicial.');
        }
        $this->limpaCaches();

        return $relatorio;
    }

    /**
     * O que "páginas de exemplo" criaria, sem criar nada: as páginas do conteúdo inicial cujo slug ainda
     * não existe (a lista que o painel mostra antes de confirmar) e as que seriam ignoradas.
     *
     * @return array{criar: list<array{slug:string, titulo:string, secoes:int}>, ignoradas: list<string>}
     */
    public function previaPaginasExemplo(string $slug): array
    {
        $linha = $this->templates->where('slug', $slug)->first() ?? throw new NaoEncontrado('Template não encontrado.');
        $arquivo = $this->servico->diretorio($linha) . '/seed/content.json';
        $seed = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;
        if (!is_array($seed)) {
            throw ErroDeValidacao::campo('template', 'Este template não traz conteúdo inicial.');
        }

        $paginas = new SitePaginaModel();
        $criar = [];
        $ignoradas = [];
        foreach ($seed['paginas'] ?? [] as $pagina) {
            $slugPagina = (string) ($pagina['slug'] ?? '');
            if ($paginas->where('slug', $slugPagina)->withDeleted()->first()) {
                $ignoradas[] = $slugPagina;
                continue;
            }
            $criar[] = ['slug' => $slugPagina, 'titulo' => (string) ($pagina['titulo'] ?? $slugPagina), 'secoes' => count($pagina['secoes'] ?? [])];
        }

        return ['criar' => $criar, 'ignoradas' => $ignoradas];
    }

    // ------------------------------------------------------------------ remover / exportar

    public function remover(string $slug): void
    {
        $linha = $this->templates->where('slug', $slug)->first() ?? throw new NaoEncontrado('Template não encontrado.');
        if ($linha['origem'] === 'core') {
            throw ErroDeValidacao::campo('template', 'Templates do sistema não podem ser removidos.');
        }
        if ($linha['ativo']) {
            throw ErroDeValidacao::campo('template', 'O template ativo não pode ser removido. Ative outro antes.');
        }

        $this->removeArvore($this->servico->diretorio($linha));
        $this->removeArvore($this->assets->destino($slug));
        $this->templates->delete($linha['id'], true);
        $this->limpaCaches();
        $this->auditar('REMOVER_TPL', ['slug' => $slug, 'versao' => $linha['versao'], 'origem' => $linha['origem']]);
    }

    /**
     * Registra a operação (com o relatório) na trilha de auditoria, além do que o model de templates
     * já grava (a coluna `action_type` é um ENUM CREATE/UPDATE/DELETE; a ação exata vai em `dados_novos.acao`). Falha de auditoria nunca desfaz a operação.
     */
    private function auditar(string $acao, array $dados): void
    {
        try {
            (new \App\Models\AuditoriaModel())->insert([
                'user_id'     => user_id(),
                'table_name'  => 'site_templates',
                'action_type' => ['INSTALAR_TPL' => 'CREATE', 'REMOVER_TPL' => 'DELETE'][$acao] ?? 'UPDATE',
                'dados_novos' => json_encode(['acao' => $acao] + $dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address'  => is_cli() ? null : service('request')->getIPAddress(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Site: falha ao auditar ' . $acao . '. ' . $e->getMessage());
        }
    }

    /**
     * Gera um `.zip` do template (e, com `$comConteudo`, o conteúdo atual do site como seed).
     *
     * @return string conteúdo do zip
     */
    public function exportar(string $slug, bool $comConteudo = false): string
    {
        $linha = $this->templates->where('slug', $slug)->first() ?? throw new NaoEncontrado('Template não encontrado.');
        $pasta = $this->servico->diretorio($linha);
        if (!is_dir($pasta)) {
            throw new NaoEncontrado('Os arquivos do template não foram encontrados no servidor.');
        }

        $arquivoZip = tempnam(sys_get_temp_dir(), 'tpl');
        $zip = new \ZipArchive();
        $zip->open($arquivoZip, \ZipArchive::OVERWRITE);

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arquivo) {
            $rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($pasta) + 1));
            if ($arquivo->isLink() || ($comConteudo && $rel === 'seed/content.json')) {
                continue;
            }
            $zip->addFile($arquivo->getPathname(), "{$slug}/{$rel}");
        }
        if ($comConteudo) {
            $seed = (new ExportadorDeConteudo())->seed($this->servico->manifesto($linha));
            $zip->addFromString("{$slug}/seed/content.json", json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
        $zip->close();

        $bytes = (string) file_get_contents($arquivoZip);
        @unlink($arquivoZip);

        return $bytes;
    }

    // ------------------------------------------------------------------ interno

    private function importaSeed(array $linha, array $manifesto, ?string $statusForcado, bool $completo): ?array
    {
        $arquivo = $this->servico->diretorio($linha) . '/seed/content.json';
        $seed = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;
        if (!is_array($seed)) {
            return null;
        }
        if (!$completo) {
            $seed = ['paginas' => $seed['paginas'] ?? []]; // exemplo = só páginas; configurações e menus ficam como estão
        }

        return (new SeedImporter())->importar($seed, $manifesto, $statusForcado);
    }

    /** Pontos de atenção da troca de template que não bloqueiam. */
    private function avisosDeAtivacao(array $manifesto, array $paginas): array
    {
        $avisos = [];
        $layouts = $manifesto['layouts'] ?? [];
        foreach ($paginas as $p) {
            if ($layouts !== [] && !in_array($p['layout'], $layouts, true)) {
                $avisos[] = "A página \"{$p['titulo']}\" usa o layout '{$p['layout']}', que o template não tem; será exibida com '{$layouts[0]}'.";
            }
        }

        $menus = new SiteMenuModel();
        $itens = new SiteMenuItemModel();
        foreach ($manifesto['menus'] ?? [] as $local) {
            $menu = $menus->where('local', $local)->first();
            if (!$menu || $itens->where('menu_id', $menu['id'])->countAllResults() === 0) {
                $avisos[] = "O menu '{$local}' do template está vazio.";
            }
        }
        return $avisos;
    }

    private function resumo(array $t): array
    {
        return ['slug' => $t['slug'], 'nome' => $t['nome'], 'versao' => $t['versao'], 'origem' => $t['origem'], 'ativo' => (bool) $t['ativo']];
    }

    private function previewUrl(array $t): ?string
    {
        $arquivo = $this->assets->garantirPreview($t['slug'], $this->servico->diretorio($t));
        if ($arquivo === null) {
            return null;
        }
        helper('site');
        return site_url_publica(trim($this->config->prefixoAssets, '/') . "/{$t['slug']}/{$arquivo}");
    }

    /** @return list<string> avisos do relatório como lista de frases */
    private function avisosComoLista(\App\Libraries\Site\Template\Resultado $r): array
    {
        $lista = [];
        foreach ($r->avisos() as $caminho => $mensagem) {
            $lista[] = "{$caminho}: {$mensagem}";
        }
        return $lista;
    }

    private function pastaTemporaria(): string
    {
        $base = rtrim($this->config->diretorios['upload'], '/\\');
        $pasta = $base . '/.tmp-' . bin2hex(random_bytes(6));
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new \RuntimeException('Não foi possível criar a pasta de templates.');
        }

        return $pasta;
    }

    /** Troca a pasta do template por renomeação (com cópia quando vem de um diretório que não é nosso). */
    private function trocaArquivos(string $origem, string $destino, bool $mover): void
    {
        $base = dirname($destino);
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new \RuntimeException('Não foi possível criar a pasta de templates.');
        }

        $novo = $base . '/.novo-' . bin2hex(random_bytes(6));
        if ($mover) {
            if (!rename($origem, $novo)) {
                throw new \RuntimeException('Não foi possível instalar os arquivos do template.');
            }
        } else {
            $this->copiaArvore($origem, $novo);
        }

        $antigo = null;
        if (is_dir($destino)) {
            $antigo = $base . '/.antigo-' . bin2hex(random_bytes(6));
            rename($destino, $antigo);
        }
        if (!rename($novo, $destino)) {
            if ($antigo !== null) {
                rename($antigo, $destino);
            }
            $this->removeArvore($novo);
            throw new \RuntimeException('Não foi possível instalar os arquivos do template.');
        }
        if ($antigo !== null) {
            $this->removeArvore($antigo);
        }
    }

    private function copiaArvore(string $origem, string $destino): void
    {
        mkdir($destino, 0775, true);
        foreach (new \DirectoryIterator($origem) as $item) {
            if ($item->isDot() || $item->isLink()) {
                continue;
            }
            $alvo = $destino . '/' . $item->getFilename();
            $item->isDir() ? $this->copiaArvore($item->getPathname(), $alvo) : copy($item->getPathname(), $alvo);
        }
    }

    private function removeArvore(string $pasta): void
    {
        if (!is_dir($pasta) || is_link($pasta)) {
            return;
        }
        foreach (new \DirectoryIterator($pasta) as $item) {
            if ($item->isDot()) {
                continue;
            }
            ($item->isDir() && !$item->isLink()) ? $this->removeArvore($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($pasta);
    }

    private function limpaCaches(): void
    {
        (new SiteTemplateRepository())->clearCache();
        SiteCache::limpar();
    }
}
