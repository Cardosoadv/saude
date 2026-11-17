<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Cnpj;
use App\Libraries\Site\HtmlParaMarkdown;
use App\Libraries\Site\SecaoParaMarkdown;
use App\Libraries\Site\UrlPagina;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Services\NewsService;

/**
 * Monta o /llms-full.txt: o texto integral das páginas publicadas e dos artigos mais recentes em
 * markdown limpo, para modelos de linguagem. Reaproveita o cabeçalho e as regras de exclusão do
 * LlmsTxtService (rascunhos, páginas noindex, área/painel do cliente) e nunca lê dados de clientes
 * ou de processos: só configurações públicas do site, páginas e notícias publicadas.
 */
class LlmsFullService extends LlmsTxtService
{
    /** Quantos artigos entram (o arquivo não pode crescer sem controle). */
    public const MAX_ARTIGOS_COMPLETOS = 50;

    private SiteSecaoRepository $secoes;

    public function __construct(
        ?SiteConfiguracaoService $configuracoes = null,
        ?SitePaginaRepository $paginas = null,
        ?NewsService $noticias = null,
        ?SiteSecaoRepository $secoes = null,
    ) {
        parent::__construct($configuracoes, $paginas, $noticias);
        $this->secoes = $secoes ?? new SiteSecaoRepository();
    }

    public function gerar(): string
    {
        helper('site');

        $linhas = ['# ' . $this->nome(), ''];
        $resumo = $this->resumo();
        if ($resumo !== '') {
            array_push($linhas, '> ' . $resumo, '');
        }

        $paginas = array_values(array_filter($this->paginas->publicadas(), fn (array $p) => in_array($p['tipo'], ['home', 'area', 'pagina'], true) && $this->indexavel($p)));
        $perguntas = [];
        $blocos = [$this->dadosInstitucionais($paginas)];

        foreach ($paginas as $pagina) {
            $blocos[] = $this->blocoDaPagina($pagina, $perguntas);
        }
        if ($perguntas !== []) {
            $blocos[] = "## Perguntas frequentes\n\n" . implode("\n\n", $perguntas);
        }
        $artigos = $this->blocosDosArtigos();
        if ($artigos !== []) {
            $blocos[] = "## Artigos\n\n" . implode("\n\n", $artigos);
        }

        return rtrim(implode("\n", $linhas) . "\n" . implode("\n\n", array_filter($blocos))) . "\n";
    }

    /** @param list<array<string, mixed>> $paginas */
    private function dadosInstitucionais(array $paginas): string
    {
        $itens = [];
        $campo = function (string $rotulo, string $valor) use (&$itens): void {
            $valor = $this->linha($valor);
            if ($valor !== '') {
                $itens[] = "- {$rotulo}: {$valor}";
            }
        };

        $campo('Razão social', (string) ($this->configuracoes->obter('identidade.razao_social', '') ?: $this->configuracoes->obter('identidade.nome', '')));
        $campo('CNPJ', Cnpj::formatar((string) $this->configuracoes->obter('identidade.cnpj', '')));
        $campo('Registro na OAB', (string) ($this->configuracoes->obter('identidade.registro_oab', '') ?: $this->configuracoes->obter('identidade.oab', '')));
        $enderecos = $this->configuracoes->obter('contato.enderecos', []);
        foreach (is_array($enderecos) ? $enderecos : [] as $endereco) {
            $campo('Endereço', (string) ($endereco['resumo'] ?? ''));
        }
        $campo('Telefone', (string) $this->configuracoes->obter('contato.telefone', ''));
        $campo('Telefone', (string) $this->configuracoes->obter('contato.telefone2', ''));
        $campo('E-mail', (string) $this->configuracoes->obter('contato.email', ''));
        $campo('Horário de atendimento', (string) $this->configuracoes->obter('contato.horario', ''));
        $campo('Site', site_url_publica());
        $areas = array_column(array_filter($paginas, static fn (array $p) => $p['tipo'] === 'area'), 'titulo');
        $campo('Áreas atendidas', implode(', ', $areas));

        return "## Dados institucionais\n\n" . implode("\n", $itens);
    }

    /**
     * @param array<string, mixed> $pagina
     * @param list<string> $perguntas recebe as seções de perguntas frequentes, que vão para um bloco só
     */
    private function blocoDaPagina(array $pagina, array &$perguntas): string
    {
        $blocos = ['## ' . $this->linha((string) $pagina['titulo'], 'Página'), 'URL: ' . UrlPagina::de($pagina)];

        foreach ($this->secoes->porPagina((int) $pagina['id'], true) as $secao) {
            $texto = SecaoParaMarkdown::converter((string) $secao['tipo'], (array) $secao['dados']);
            if ($texto === '') {
                continue;
            }
            if (SecaoParaMarkdown::ehPerguntasFrequentes((string) $secao['tipo'])) {
                $perguntas[] = $texto;
            } else {
                $blocos[] = $texto;
            }
        }

        return implode("\n\n", $blocos);
    }

    /** @return list<string> */
    private function blocosDosArtigos(): array
    {
        $artigos = $this->noticias->getAll(
            ['title', 'slug', 'summary', 'content', 'published_at'],
            ['status' => 'published'],
            'published_at',
            'desc',
            [],
            self::MAX_ARTIGOS_COMPLETOS,
        );

        $blocos = [];
        foreach ($artigos as $artigo) {
            $data = substr((string) ($artigo['published_at'] ?? ''), 0, 10);
            $cabecalho = '### ' . $this->linha((string) $artigo['title'], 'Artigo') . ($data !== '' ? " — {$data}" : '');
            $corpo = HtmlParaMarkdown::converter((string) ($artigo['content'] ?? ''), 4)
                ?: $this->linha((string) ($artigo['summary'] ?? ''));

            $blocos[] = implode("\n\n", array_filter([$cabecalho, 'URL: ' . site_url_publica('noticias/' . $artigo['slug']), $corpo]));
        }

        return $blocos;
    }
}
