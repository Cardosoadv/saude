<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\VariantesMidia;
use App\Models\Site\SiteConfiguracaoModel;
use App\Models\Site\SiteMidiaModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use Config\SiteTemplate;

/**
 * Biblioteca de mídias do site (API administrativa, issue #649): logo, favicon,
 * imagens de seção e de compartilhamento.
 *
 * Segurança do envio: o tipo vem do conteúdo (`getimagesize`), nunca do nome nem do
 * tipo informado pelo cliente; o arquivo é salvo com nome aleatório e extensão
 * derivada do tipo real; SVG é recusado (pode conter script); há limite de bytes e de
 * dimensões; e a pasta de envios recebe um `.htaccess` que impede execução de scripts.
 */
class SiteMidiaService
{
    public function __construct(
        private ?SiteMidiaModel $midias = null,
        private ?SiteConfiguracaoModel $configuracoes = null,
        private ?SitePaginaModel $paginas = null,
        private ?SiteSecaoModel $secoes = null,
        private ?SiteTemplateService $templates = null,
        private ?SiteTemplate $config = null,
    ) {
        $this->midias        ??= new SiteMidiaModel();
        $this->configuracoes ??= new SiteConfiguracaoModel();
        $this->paginas       ??= new SitePaginaModel();
        $this->secoes        ??= new SiteSecaoModel();
        $this->templates     ??= new SiteTemplateService();
        $this->config        ??= config(SiteTemplate::class);
    }

    /**
     * @param string $caminhoTemporario Arquivo recebido (ex.: `$_FILES['arquivo']['tmp_name']`)
     * @param string $nomeOriginal Nome informado pelo cliente (só guardado como rótulo)
     * @return array<string, mixed>
     */
    public function enviar(string $caminhoTemporario, string $nomeOriginal, ?string $alt = null): array
    {
        $info = $this->inspeciona($caminhoTemporario);

        $alt = $alt !== null ? trim($alt) : null;
        if ($alt !== null && mb_strlen($alt) > 255) {
            throw ErroDeValidacao::campo('alt', 'O texto alternativo tem no máximo 255 caracteres.');
        }

        $relativo = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $info['extensao'];
        $destino  = $this->caminhoAbsoluto($relativo);
        $this->preparaPasta(dirname($destino));

        if (!@copy($caminhoTemporario, $destino)) {
            throw new \RuntimeException('Não foi possível gravar o arquivo enviado.');
        }
        @chmod($destino, 0644);

        $id = $this->midias->insert([
            'arquivo'       => $relativo,
            'nome_original' => $this->limpaNome($nomeOriginal),
            'mime'          => $info['mime'],
            'tamanho'       => $info['tamanho'],
            'largura'       => $info['largura'],
            'altura'        => $info['altura'],
            'alt'           => $alt === '' ? null : $alt,
        ]);
        if (!$id) {
            @unlink($destino);
            throw new ErroDeValidacao($this->midias->errors());
        }

        $this->geraMiniatura($destino, $relativo, $info);
        VariantesMidia::gerar($destino, $relativo, $info['mime'], $info['largura'], $info['altura']);
        SiteCache::limpar();

        return $this->apresenta($this->midias->find($id));
    }

    /**
     * Gera de novo as variantes WebP/AVIF e a miniatura de uma mídia já enviada (comando `site:midias:reprocessar`).
     *
     * @return int quantidade de arquivos de variante criados
     */
    public function reprocessar(int $id): int
    {
        $m       = $this->achar($id);
        $arquivo = $this->caminhoAbsoluto($m['arquivo']);
        if (! is_file($arquivo)) {
            return 0;
        }

        $info = @getimagesize($arquivo);
        $this->preparaPasta(dirname($arquivo));
        if ($info === false) {
            return 0;
        }
        [$largura, $altura] = $info;
        if ((int) $m['largura'] !== $largura || (int) $m['altura'] !== $altura) {
            $this->midias->update($id, ['largura' => $largura, 'altura' => $altura]);
        }

        VariantesMidia::apagar($this->config->diretorioUploads, $m['arquivo']);

        return count(VariantesMidia::gerar($arquivo, $m['arquivo'], (string) $m['mime'], $largura, $altura));
    }

    /** @return list<int> ids de todas as mídias */
    public function ids(): array
    {
        return array_map('intval', array_column($this->midias->select('id')->orderBy('id')->findAll(), 'id'));
    }

    /**
     * @return array{data: list<array<string, mixed>>, pager: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function listar(int $pagina = 1, int $porPagina = 24, ?string $busca = null): array
    {
        $porPagina = max(1, min(100, $porPagina));
        $pagina    = max(1, $pagina);

        $q = $this->midias->orderBy('id', 'DESC');
        if ($busca !== null && trim($busca) !== '') {
            $q->groupStart()->like('nome_original', trim($busca))->orLike('alt', trim($busca))->groupEnd();
        }

        $total = $q->countAllResults(false); // mantém filtros para a busca da página
        $itens = $q->findAll($porPagina, ($pagina - 1) * $porPagina);

        return [
            'data'  => array_map(fn (array $m) => $this->apresenta($m), $itens),
            'pager' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $porPagina))],
        ];
    }

    /** Uma mídia pela id (o seletor de imagem do painel mostra a miniatura da imagem já escolhida). */
    public function obter(int $id): array
    {
        return $this->apresenta($this->achar($id));
    }

    public function atualizarAlt(int $id, ?string $alt): array
    {
        $this->achar($id);
        $alt = $alt === null ? null : trim($alt);
        if ($alt !== null && mb_strlen($alt) > 255) {
            throw ErroDeValidacao::campo('alt', 'O texto alternativo tem no máximo 255 caracteres.');
        }

        $this->midias->update($id, ['alt' => $alt === '' ? null : $alt]);
        SiteCache::limpar();

        return $this->apresenta($this->achar($id));
    }

    /**
     * Onde a mídia está em uso (configurações, imagem de página e seções).
     *
     * @return list<array<string, mixed>>
     */
    public function usos(int $id): array
    {
        $this->achar($id);
        $usos = [];

        foreach ($this->configuracoes->where('tipo', 'imagem')->where('valor', (string) $id)->findAll() as $c) {
            $usos[] = ['tipo' => 'configuracao', 'chave' => $c['chave']];
        }
        foreach ($this->paginas->where('og_image_id', $id)->findAll() as $p) {
            $usos[] = ['tipo' => 'pagina', 'pagina_id' => (int) $p['id'], 'titulo' => $p['titulo']];
        }

        $tipos = $this->templates->manifestoAtivo()['secoes'] ?? [];
        foreach ($this->secoes->like('dados', (string) $id)->findAll() as $s) {
            $dados = json_decode((string) $s['dados'], true) ?? [];
            $campos = $tipos[$s['tipo']]['campos'] ?? null;
            // Tipo que o template ativo não conhece: na dúvida, qualquer número igual ao id conta.
            $usa = $campos !== null ? $this->usaImagem($campos, $dados, $id) : $this->contemNumero($dados, $id);
            if ($usa) {
                $pagina = $this->paginas->find($s['pagina_id']);
                $usos[] = ['tipo' => 'secao', 'secao_id' => (int) $s['id'], 'secao_tipo' => $s['tipo'], 'pagina_id' => (int) $s['pagina_id'], 'titulo' => $pagina['titulo'] ?? ''];
            }
        }

        return $usos;
    }

    /** Exclui a mídia e os arquivos, se ninguém a usa. */
    public function excluir(int $id): void
    {
        $midia = $this->achar($id);

        $usos = $this->usos($id);
        if ($usos !== []) {
            $quem = array_map(fn (array $u) => match ($u['tipo']) {
                'configuracao' => "configuração {$u['chave']}",
                'pagina'       => "imagem de compartilhamento da página \"{$u['titulo']}\"",
                default        => "seção #{$u['secao_id']} ({$u['secao_tipo']}) da página \"{$u['titulo']}\"",
            }, $usos);

            throw ErroDeValidacao::campo('midia', 'A mídia está em uso e não pode ser excluída: ' . implode('; ', $quem) . '.');
        }

        $this->midias->delete($id, true);
        VariantesMidia::apagar($this->config->diretorioUploads, $midia['arquivo']);
        foreach ([$this->caminhoAbsoluto($midia['arquivo']), $this->caminhoAbsoluto($this->miniatura($midia['arquivo']))] as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
        SiteCache::limpar();
    }

    // ------------------------------------------------------------------ interno

    /** @return array<string, mixed> */
    private function achar(int $id): array
    {
        return $this->midias->find($id) ?? throw new NaoEncontrado('Mídia não encontrada.');
    }

    /**
     * Confere tamanho, tipo real e dimensões.
     *
     * @return array{mime:string, extensao:string, largura:int, altura:int, tamanho:int}
     */
    private function inspeciona(string $caminho): array
    {
        if (!is_file($caminho) || !is_readable($caminho)) {
            throw ErroDeValidacao::campo('arquivo', 'Nenhum arquivo recebido.');
        }

        $tamanho = (int) filesize($caminho);
        if ($tamanho === 0) {
            throw ErroDeValidacao::campo('arquivo', 'O arquivo está vazio.');
        }
        if ($tamanho > $this->config->maxUploadBytes) {
            throw ErroDeValidacao::campo('arquivo', 'O arquivo passa do limite de ' . round($this->config->maxUploadBytes / 1048576, 1) . ' MB.');
        }

        $imagem = @getimagesize($caminho);
        $mime = $imagem['mime'] ?? '';
        $extensao = $this->config->tiposMidia[$mime] ?? null;
        if ($imagem === false || $extensao === null) {
            throw ErroDeValidacao::campo('arquivo', 'Formato não aceito. Envie JPG, PNG, GIF, WebP ou ICO (SVG não é aceito).');
        }

        [$largura, $altura] = $imagem;
        if ($largura < 1 || $altura < 1 || $largura > $this->config->maxLadoPx || $altura > $this->config->maxLadoPx) {
            throw ErroDeValidacao::campo('arquivo', "A imagem deve ter no máximo {$this->config->maxLadoPx} px por lado.");
        }

        return ['mime' => $mime, 'extensao' => $extensao, 'largura' => $largura, 'altura' => $altura, 'tamanho' => $tamanho];
    }

    /** Somente nome de arquivo, sem caminho nem caracteres de controle. */
    private function limpaNome(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        $nome = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $nome) ?? '');

        return mb_substr($nome !== '' ? $nome : 'imagem', 0, 255);
    }

    private function caminhoAbsoluto(string $relativo): string
    {
        return rtrim($this->config->diretorioUploads, '/\\') . '/' . ltrim($relativo, '/');
    }

    private function miniatura(string $relativo): string
    {
        return preg_replace('/\.([a-z0-9]+)$/', '-thumb.$1', $relativo) ?? $relativo;
    }

    private function preparaPasta(string $pasta): void
    {
        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true) && !is_dir($pasta)) {
            throw new \RuntimeException('Não foi possível criar a pasta de envios.');
        }

        // Nada que seja script roda a partir daqui, mesmo que alguém consiga gravar um.
        $raiz = rtrim($this->config->diretorioUploads, '/\\');
        $htaccess = $raiz . '/.htaccess';
        $conteudo = "# Gerado pelo sistema: envios do site não executam scripts.\n"
            . "Options -Indexes -ExecCGI\n"
            . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
            . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar\n"
            . "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)\$\">\n  Require all denied\n</FilesMatch>\n"
            // Os nomes são hashes aleatórios: um arquivo nunca muda de conteúdo, então o cache pode ser eterno.
            . "<IfModule mod_headers.c>\n  <FilesMatch \"\\.(jpe?g|png|gif|webp|avif|ico)\$\">\n    Header set Cache-Control \"public, max-age=31536000, immutable\"\n  </FilesMatch>\n</IfModule>\n";
        if (! is_file($htaccess) || ! str_contains((string) @file_get_contents($htaccess), 'immutable')) {
            @file_put_contents($htaccess, $conteudo);
        }
    }

    /** Miniatura para a listagem (só JPG, PNG e WebP, e só se a biblioteca de imagens estiver disponível). */
    private function geraMiniatura(string $arquivo, string $relativo, array $info): void
    {
        if (!in_array($info['extensao'], ['jpg', 'png', 'webp'], true) || !extension_loaded('gd')) {
            return;
        }
        $largura = $this->config->larguraMiniatura;
        if ($info['largura'] <= $largura) {
            return;
        }

        try {
            \Config\Services::image('gd')->withFile($arquivo)
                ->resize($largura, (int) round($info['altura'] * $largura / $info['largura']), true)
                ->save($this->caminhoAbsoluto($this->miniatura($relativo)));
        } catch (\Throwable $e) {
            log_message('warning', 'Site: não foi possível gerar a miniatura. ' . $e->getMessage());
        }
    }

    private function apresenta(array $m): array
    {
        $prefixo = trim($this->config->prefixoUploads, '/');
        $thumb   = $this->miniatura($m['arquivo']);

        return $m + [
            'url'           => base_url("{$prefixo}/{$m['arquivo']}"),
            'miniatura_url' => is_file($this->caminhoAbsoluto($thumb)) ? base_url("{$prefixo}/{$thumb}") : base_url("{$prefixo}/{$m['arquivo']}"),
        ];
    }

    /** @param array<string, array> $campos */
    private function usaImagem(array $campos, array $dados, int $id): bool
    {
        foreach ($campos as $nome => $def) {
            $valor = $dados[$nome] ?? null;
            if (($def['tipo'] ?? '') === 'imagem' && $valor === $id) {
                return true;
            }
            if (($def['tipo'] ?? '') === 'lista' && is_array($valor)) {
                foreach ($valor as $item) {
                    if (is_array($item) && $this->usaImagem($def['itens'] ?? [], $item, $id)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function contemNumero(mixed $valor, int $id): bool
    {
        if (is_int($valor)) {
            return $valor === $id;
        }
        if (is_array($valor)) {
            foreach ($valor as $v) {
                if ($this->contemNumero($v, $id)) {
                    return true;
                }
            }
        }
        return false;
    }
}
