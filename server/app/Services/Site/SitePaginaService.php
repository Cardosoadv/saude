<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\PreviewToken;
use App\Libraries\Site\Template\SecaoDadosValidator;
use App\Libraries\Site\UrlPagina;
use App\Models\Site\SiteMidiaModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use Config\SiteTemplate;

/**
 * Páginas do site (API administrativa, issue #648).
 *
 * Fluxo editorial: criada como `rascunho`; só `publicar()` a torna pública.
 * Home e páginas de sistema (notícias, área do cliente) não podem ser
 * excluídas nem despublicadas, e o slug das de sistema é fixo (as rotas
 * dependem dele).
 */
class SitePaginaService
{
    /** Campos que a API aceita ao criar/atualizar. `status` e `tipo` mudam por outros caminhos. */
    private const EDITAVEIS = ['slug', 'titulo', 'layout', 'meta_title', 'meta_description', 'og_image_id', 'exibir_sitemap', 'prioridade_sitemap', 'changefreq', 'ordem'];
    private const TIPOS_CRIAVEIS = ['pagina', 'area'];

    public function __construct(
        private ?SitePaginaModel $paginas = null,
        private ?SiteSecaoModel $secoes = null,
        private ?SiteMidiaModel $midias = null,
        private ?SiteTemplateService $templates = null,
        private ?SecaoDadosValidator $validator = null,
        private ?PreviewToken $preview = null,
        private ?SiteTemplate $config = null,
    ) {
        $this->paginas   ??= new SitePaginaModel();
        $this->secoes    ??= new SiteSecaoModel();
        $this->midias    ??= new SiteMidiaModel();
        $this->templates ??= new SiteTemplateService();
        $this->validator ??= new SecaoDadosValidator();
        $this->preview   ??= new PreviewToken();
        $this->config    ??= config(SiteTemplate::class);
    }

    /**
     * @param array{status?:string, tipo?:string, busca?:string} $filtros
     * @return list<array<string, mixed>> páginas com `url` e `total_secoes`, em ordem de exibição
     */
    public function listar(array $filtros = []): array
    {
        $q = $this->paginas->orderBy('ordem', 'ASC')->orderBy('id', 'ASC');
        if (in_array($filtros['status'] ?? '', ['rascunho', 'publicado'], true)) {
            $q->where('status', $filtros['status']);
        }
        if (!empty($filtros['tipo'])) {
            $q->where('tipo', $filtros['tipo']);
        }
        if (!empty($filtros['busca'])) {
            $q->groupStart()->like('titulo', $filtros['busca'])->orLike('slug', $filtros['busca'])->groupEnd();
        }
        $paginas = $q->findAll();

        $totais = array_column(
            $this->secoes->select('pagina_id, COUNT(*) AS n')->groupBy('pagina_id')->findAll(),
            'n',
            'pagina_id'
        );

        return array_map(fn (array $p) => $this->apresenta($p) + ['total_secoes' => (int) ($totais[$p['id']] ?? 0)], $paginas);
    }

    /** Página com todas as suas seções (inclusive as ocultas), `dados` decodificado. */
    public function obter(int $id): array
    {
        $pagina = $this->achar($id);
        $secoes = $this->secoes->where('pagina_id', $id)->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();

        $tipos = $this->tiposDoTemplate();
        foreach ($secoes as &$secao) {
            $secao['dados'] = $secao['dados'] ? (json_decode($secao['dados'], true) ?? []) : [];
            $secao['compativel'] = $tipos === null || isset($tipos[$secao['tipo']]);
        }

        return $this->apresenta($pagina) + ['secoes' => $secoes];
    }

    /** @return array<string, mixed> */
    public function criar(array $dados): array
    {
        $tipo = $dados['tipo'] ?? 'pagina';
        if (!in_array($tipo, self::TIPOS_CRIAVEIS, true)) {
            throw ErroDeValidacao::campo('tipo', 'Tipo inválido. Use: ' . implode(', ', self::TIPOS_CRIAVEIS) . '.');
        }

        $campos = $this->filtraEditaveis($dados) + ['layout' => $this->layoutPadrao($tipo)];
        $this->validaReferencias($campos);

        $id = $this->paginas->insert($campos + ['tipo' => $tipo, 'status' => 'rascunho']);
        if (!$id) {
            throw new ErroDeValidacao($this->paginas->errors());
        }
        SiteCache::limpar();

        return $this->obter((int) $id);
    }

    /**
     * Atualiza os campos editáveis. Mudar o slug de uma página publicada devolve um aviso:
     * o endereço antigo deixa de funcionar (não há redirecionamento).
     *
     * @return array{pagina: array<string, mixed>, avisos: list<string>}
     */
    public function atualizar(int $id, array $dados): array
    {
        $pagina = $this->achar($id);
        $campos = $this->filtraEditaveis($dados);
        $avisos = [];

        if (isset($campos['slug']) && $campos['slug'] !== $pagina['slug']) {
            if ($pagina['tipo'] === 'sistema') {
                throw ErroDeValidacao::campo('slug', 'O endereço de uma página de sistema é fixo.');
            }
            if ($pagina['status'] === 'publicado') {
                $avisos[] = "O endereço antigo (/pagina/{$pagina['slug']}) deixará de funcionar; não há redirecionamento.";
            }
        }
        $this->validaReferencias($campos);

        // `id` junto dos dados: é dele que a regra `is_unique[..., id, {id}]` do slug tira a própria página.
        if ($campos !== [] && !$this->paginas->update($id, $campos + ['id' => $id])) {
            throw new ErroDeValidacao($this->paginas->errors());
        }
        SiteCache::limpar();

        return ['pagina' => $this->obter($id), 'avisos' => $avisos];
    }

    public function excluir(int $id): void
    {
        $pagina = $this->achar($id);
        $this->exigeNaoEspecial($pagina, 'excluída');

        $this->paginas->delete($id, true); // definitiva: libera o slug; as seções caem por cascata
        SiteCache::limpar();
    }

    /**
     * Publica a página. Seções visíveis precisam estar válidas para o template ativo;
     * seções de tipo que o template não tem são ignoradas na renderização e geram aviso.
     *
     * @return array{pagina: array<string, mixed>, avisos: list<string>}
     */
    public function publicar(int $id): array
    {
        $pagina = $this->achar($id);
        if ($pagina['tipo'] === 'sistema') {
            throw ErroDeValidacao::campo('pagina', 'Páginas de sistema não têm rascunho.');
        }

        $tipos  = $this->tiposDoTemplate();
        $erros  = [];
        $avisos = [];
        foreach ($this->secoes->where('pagina_id', $id)->where('visivel', 1)->orderBy('ordem', 'ASC')->findAll() as $secao) {
            if ($tipos !== null && !isset($tipos[$secao['tipo']])) {
                $avisos[] = "A seção #{$secao['id']} ({$secao['tipo']}) não existe no template ativo e não será exibida.";
                continue;
            }
            if ($tipos === null) {
                continue;
            }
            $res = $this->validator->validar($tipos[$secao['tipo']]['campos'] ?? [], json_decode((string) $secao['dados'], true) ?? []);
            foreach ($res->erros() as $caminho => $mensagem) {
                $erros["secoes.{$secao['id']}.{$caminho}"] = $mensagem;
            }
        }
        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }

        $this->paginas->update($id, ['status' => 'publicado', 'publicado_em' => $pagina['publicado_em'] ?? date('Y-m-d H:i:s')]);
        SiteCache::limpar();

        return ['pagina' => $this->obter($id), 'avisos' => $avisos];
    }

    /** @return array<string, mixed> */
    public function despublicar(int $id): array
    {
        $pagina = $this->achar($id);
        $this->exigeNaoEspecial($pagina, 'despublicada');

        $this->paginas->update($id, ['status' => 'rascunho']);
        SiteCache::limpar();

        return $this->obter($id);
    }

    /** Cópia em rascunho, com as seções. Duplicar a home gera uma página comum. */
    public function duplicar(int $id): array
    {
        $original = $this->achar($id);
        if ($original['tipo'] === 'sistema') {
            throw ErroDeValidacao::campo('pagina', 'Páginas de sistema não podem ser duplicadas.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $copia = array_intersect_key($original, array_flip(['titulo', 'layout', 'meta_title', 'meta_description', 'og_image_id', 'exibir_sitemap', 'prioridade_sitemap', 'changefreq', 'ordem']));
        $copia['titulo'] = $original['titulo'] . ' (cópia)';
        $copia['slug']   = $this->slugLivre($original['slug'] . '-copia');
        $copia['tipo']   = $original['tipo'] === 'home' ? 'pagina' : $original['tipo'];
        $copia['status'] = 'rascunho';

        $novoId = $this->paginas->insert($copia);
        if (!$novoId) {
            $db->transRollback();
            throw new ErroDeValidacao($this->paginas->errors());
        }
        foreach ($this->secoes->where('pagina_id', $id)->orderBy('ordem', 'ASC')->findAll() as $secao) {
            $this->secoes->insert(['pagina_id' => $novoId] + array_intersect_key($secao, array_flip(['tipo', 'ancora', 'ordem', 'visivel', 'dados'])));
        }
        $db->transComplete();
        SiteCache::limpar();

        return $this->obter((int) $novoId);
    }

    /**
     * Link assinado para pré-visualizar a página (inclusive rascunho).
     *
     * @return array{token:string, url:string, expira_em:string}
     */
    public function previewToken(int $id): array
    {
        $pagina = $this->achar($id);
        $validade = $this->config->validadePreview;
        $token = $this->preview->gerar($id, $validade);

        return [
            'token'     => $token,
            'url'       => UrlPagina::de($pagina) . '?preview=' . $token,
            'expira_em' => date('Y-m-d H:i:s', time() + $validade),
        ];
    }

    // ------------------------------------------------------------------ interno

    /** @return array<string, mixed> */
    private function achar(int $id): array
    {
        return $this->paginas->find($id) ?? throw new NaoEncontrado('Página não encontrada.');
    }

    private function apresenta(array $pagina): array
    {
        return $pagina + ['url' => UrlPagina::de($pagina)];
    }

    private function filtraEditaveis(array $dados): array
    {
        $campos = array_intersect_key($dados, array_flip(self::EDITAVEIS));

        foreach (['meta_title', 'meta_description'] as $texto) {
            if (array_key_exists($texto, $campos) && trim((string) $campos[$texto]) === '') {
                $campos[$texto] = null;
            }
        }
        if (array_key_exists('og_image_id', $campos) && ($campos['og_image_id'] === '' || $campos['og_image_id'] === 0)) {
            $campos['og_image_id'] = null;
        }
        if (isset($campos['exibir_sitemap'])) {
            $campos['exibir_sitemap'] = filter_var($campos['exibir_sitemap'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $campos;
    }

    private function validaReferencias(array $campos): void
    {
        $erros = [];
        if (isset($campos['og_image_id']) && !$this->midias->find((int) $campos['og_image_id'])) {
            $erros['og_image_id'] = 'Mídia não encontrada na biblioteca.';
        }
        if (isset($campos['layout'])) {
            $layouts = $this->templates->manifestoAtivo()['layouts'] ?? null;
            if ($layouts !== null && !in_array($campos['layout'], $layouts, true)) {
                $erros['layout'] = 'Layout inexistente no template ativo. Use: ' . implode(', ', $layouts) . '.';
            }
        }
        if (isset($campos['prioridade_sitemap']) && (!is_numeric($campos['prioridade_sitemap']) || $campos['prioridade_sitemap'] < 0 || $campos['prioridade_sitemap'] > 1)) {
            $erros['prioridade_sitemap'] = 'Deve estar entre 0 e 1.';
        }
        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }
    }

    private function layoutPadrao(string $tipo): string
    {
        $layouts = $this->templates->manifestoAtivo()['layouts'] ?? ['main'];

        return ($tipo === 'area' && in_array('area', $layouts, true)) ? 'area' : ($layouts[0] ?? 'main');
    }

    private function exigeNaoEspecial(array $pagina, string $verbo): void
    {
        if ($pagina['tipo'] === 'home') {
            throw ErroDeValidacao::campo('pagina', "A página inicial não pode ser {$verbo}.");
        }
        if ($pagina['tipo'] === 'sistema') {
            throw ErroDeValidacao::campo('pagina', "Páginas de sistema não podem ser {$verbo}.");
        }
    }

    private function slugLivre(string $base): string
    {
        $slug = $base;
        for ($i = 2; $this->paginas->where('slug', $slug)->withDeleted()->countAllResults() > 0; $i++) {
            $slug = "{$base}-{$i}";
        }
        return $slug;
    }

    /** @return array<string, array>|null `secoes` do manifesto ativo; null se não há template ativo */
    private function tiposDoTemplate(): ?array
    {
        return $this->templates->manifestoAtivo()['secoes'] ?? null;
    }
}
