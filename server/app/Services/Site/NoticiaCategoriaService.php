<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Models\NewsCategoryModel;
use App\Models\NewsModel;
use App\Repositories\NewsCategoryRepository;
use App\Repositories\NewsRepository;

/**
 * Categorias das notícias do site (API administrativa): listar, criar, editar e excluir.
 *
 * Os campos seguem as colunas de `site_news_categories` (`name`, `slug`, `meta_description`),
 * como a API de notícias. O slug é opcional: vazio, nasce do nome (com sufixo `-2`, `-3`…
 * se já existir). A tabela tem índice único em `slug` que inclui as linhas excluídas
 * (soft delete), por isso a unicidade é conferida contra todas elas.
 */
class NoticiaCategoriaService
{
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private ?NewsCategoryModel $categorias = null,
        private ?NewsModel $noticias = null,
    ) {
        $this->categorias ??= new NewsCategoryModel();
        $this->noticias   ??= new NewsModel();
    }

    /**
     * Categorias por nome, cada uma com `total_noticias` (notícias não excluídas).
     *
     * @return list<array<string, mixed>>
     */
    public function listar(?string $busca = null): array
    {
        $q = $this->categorias->orderBy('name', 'ASC');
        if ($busca !== null && trim($busca) !== '') {
            $q->groupStart()->like('name', trim($busca))->orLike('slug', trim($busca))->groupEnd();
        }
        $categorias = $q->findAll();

        $totais = [];
        foreach ($this->noticias->select('category_id, COUNT(*) AS total')->groupBy('category_id')->findAll() as $linha) {
            $totais[(int) $linha['category_id']] = (int) $linha['total'];
        }

        return array_map(fn (array $c) => $this->apresenta($c, $totais[(int) $c['id']] ?? 0), $categorias);
    }

    /** @return array<string, mixed> */
    public function obter(int $id): array
    {
        $categoria = $this->achar($id);

        return $this->apresenta($categoria, $this->totalNoticias($id));
    }

    /**
     * @param array<string, mixed> $entrada `{name, slug?, meta_description?}`
     * @return array<string, mixed>
     */
    public function criar(array $entrada): array
    {
        $dados = $this->valida($entrada, null);

        $id = $this->categorias->insert($dados);
        if (!$id) {
            throw new ErroDeValidacao($this->categorias->errors() ?: ['categoria' => 'Não foi possível criar a categoria.']);
        }
        $this->limpaCaches();

        return $this->obter((int) $id);
    }

    /**
     * Atualização parcial: só os campos enviados mudam.
     *
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    public function atualizar(int $id, array $entrada): array
    {
        $atual = $this->achar($id);
        $dados = $this->valida($entrada, $atual);

        if ($dados !== []) {
            if (!$this->categorias->update($id, $dados)) {
                throw new ErroDeValidacao($this->categorias->errors() ?: ['categoria' => 'Não foi possível salvar a categoria.']);
            }
            $this->limpaCaches();
        }

        return $this->obter($id);
    }

    /**
     * Exclui a categoria. Recusa (422) se houver notícias nela: mova-as antes.
     * Sem nenhuma referência, a linha sai de vez; se só notícias já excluídas
     * apontam para ela (a chave estrangeira impede apagar), fica excluída com o
     * slug renomeado, para liberá-lo a uma categoria nova.
     */
    public function excluir(int $id): void
    {
        $categoria = $this->achar($id);

        $total = $this->totalNoticias($id);
        if ($total > 0) {
            throw ErroDeValidacao::campo('categoria', $total === 1
                ? 'A categoria tem 1 notícia. Mova-a para outra categoria antes de excluir.'
                : "A categoria tem {$total} notícias. Mova-as para outra categoria antes de excluir.");
        }

        $naLixeira = $this->noticias->onlyDeleted()->where('category_id', $id)->countAllResults();
        if ($naLixeira === 0) {
            $this->categorias->delete($id, true);
        } else {
            $sufixo = '-excluida-' . $id;
            $slug = substr((string) $categoria['slug'], 0, 120 - strlen($sufixo)) . $sufixo;
            $this->categorias->skipValidation(true)->update($id, ['slug' => $slug]);
            $this->categorias->skipValidation(false)->delete($id);
        }

        $this->limpaCaches();
    }

    // ------------------------------------------------------------------

    /**
     * Valida e normaliza a entrada. Na criação (`$atual` null) o nome é obrigatório;
     * na edição só os campos presentes são conferidos.
     *
     * @return array<string, mixed>
     */
    private function valida(array $entrada, ?array $atual): array
    {
        $criando = $atual === null;
        $id = $criando ? null : (int) $atual['id'];
        $erros = [];
        $dados = [];

        if ($criando || array_key_exists('name', $entrada)) {
            $nome = trim((string) ($entrada['name'] ?? ''));
            if ($nome === '') {
                $erros['name'] = 'Informe o nome da categoria.';
            } elseif (mb_strlen($nome) > 100) {
                $erros['name'] = 'O nome tem no máximo 100 caracteres.';
            } elseif ($this->nomeEmUso($nome, $id)) {
                $erros['name'] = 'Já existe uma categoria com este nome.';
            } else {
                $dados['name'] = $nome;
            }
        }

        if (array_key_exists('slug', $entrada) && trim((string) $entrada['slug']) !== '') {
            $slug = trim((string) $entrada['slug']);
            if (strlen($slug) > 120 || !preg_match(self::SLUG, $slug)) {
                $erros['slug'] = 'Use só letras minúsculas sem acento, números e hífens (até 120 caracteres).';
            } elseif ($this->slugEmUso($slug, $id)) {
                $erros['slug'] = 'Este endereço já é usado por outra categoria.';
            } else {
                $dados['slug'] = $slug;
            }
        } elseif ($criando && isset($dados['name'])) {
            $dados['slug'] = $this->slugLivre($this->slugDe($dados['name']), null);
        } elseif (!$criando && array_key_exists('slug', $entrada)) {
            // Slug enviado vazio na edição: gera de novo a partir do nome (novo ou atual).
            $dados['slug'] = $this->slugLivre($this->slugDe($dados['name'] ?? (string) $atual['name']), $id);
        }

        if (array_key_exists('meta_description', $entrada)) {
            $meta = trim((string) ($entrada['meta_description'] ?? ''));
            if (mb_strlen($meta) > 160) {
                $erros['meta_description'] = 'A descrição tem no máximo 160 caracteres.';
            } else {
                $dados['meta_description'] = $meta === '' ? null : $meta;
            }
        }

        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }

        if (!$criando) {
            // Só o que mudou (o model descarta update vazio).
            $dados = array_filter($dados, static fn ($v, $k) => $atual[$k] !== $v, ARRAY_FILTER_USE_BOTH);
        }

        return $dados;
    }

    /** @return array<string, mixed> */
    private function achar(int $id): array
    {
        $categoria = $this->categorias->find($id);
        if (!$categoria) {
            throw new NaoEncontrado('Categoria não encontrada.');
        }

        return $categoria;
    }

    private function totalNoticias(int $id): int
    {
        return $this->noticias->where('category_id', $id)->countAllResults();
    }

    private function nomeEmUso(string $nome, ?int $ignorar): bool
    {
        $q = $this->categorias->where('LOWER(name)', mb_strtolower($nome));
        if ($ignorar !== null) {
            $q->where('id !=', $ignorar);
        }

        return $q->countAllResults() > 0;
    }

    private function slugEmUso(string $slug, ?int $ignorar): bool
    {
        $q = $this->categorias->withDeleted()->where('slug', $slug);
        if ($ignorar !== null) {
            $q->where('id !=', $ignorar);
        }

        return $q->countAllResults() > 0;
    }

    private function slugLivre(string $base, ?int $ignorar): string
    {
        $base = substr($base !== '' ? $base : 'categoria', 0, 110);
        $slug = $base;
        for ($n = 2; $this->slugEmUso($slug, $ignorar); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function slugDe(string $nome): string
    {
        helper('url');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if ($ascii === false || $ascii === '') {
            $ascii = preg_replace('/[^\x00-\x7F]/u', '', $nome);
        }

        return trim(url_title((string) $ascii, '-', true), '-');
    }

    /** @return array<string, mixed> */
    private function apresenta(array $c, int $totalNoticias): array
    {
        return [
            'id'               => (int) $c['id'],
            'name'             => $c['name'],
            'slug'             => $c['slug'],
            'meta_description' => $c['meta_description'],
            'total_noticias'   => $totalNoticias,
            'created_at'       => $c['created_at'],
            'updated_at'       => $c['updated_at'],
        ];
    }

    /** O nome da categoria aparece nas notícias (listas e artigo) e no site renderizado. */
    private function limpaCaches(): void
    {
        (new NewsCategoryRepository($this->categorias))->clearCache();
        (new NewsRepository($this->noticias))->clearCache();
        SiteCache::limpar();
    }
}
