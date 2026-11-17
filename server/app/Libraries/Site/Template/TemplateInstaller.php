<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteTemplateModel;
use App\Repositories\Site\SiteTemplateRepository;
use App\Services\Site\SiteTemplateService;

/**
 * Instala um template que acompanha a aplicação (origem `core`):
 * valida o manifesto, registra/atualiza em `site_templates`, publica os
 * assets e, se o site ainda não tem nenhuma página, importa o conteúdo
 * inicial. O template só é ativado se não houver outro ativo.
 *
 * Idempotente. Reinstalar atualiza versão/manifesto/assets e nunca mexe no
 * conteúdo existente. (Pacotes enviados pelo painel: #650.)
 */
class TemplateInstaller
{
    public function __construct(
        private ?SiteTemplateModel $templates = null,
        private ?SitePaginaModel $paginas = null,
        private ?SeedImporter $seed = null,
        private ?TemplateAssets $assets = null,
        private ?ManifestValidator $validator = null,
        private ?SiteTemplateService $service = null,
    ) {
        $this->templates ??= new SiteTemplateModel();
        $this->paginas   ??= new SitePaginaModel();
        $this->seed      ??= new SeedImporter();
        $this->assets    ??= new TemplateAssets();
        $this->validator ??= new ManifestValidator();
        $this->service   ??= new SiteTemplateService();
    }

    /**
     * @return array{template:string, versao:string, ativado:bool, assets:bool, seed:?array}
     * @throws \InvalidArgumentException manifesto inválido
     */
    public function instalarCore(string $slug): array
    {
        $diretorio = $this->service->diretorio(['origem' => 'core', 'slug' => $slug]);
        $arquivo   = $diretorio . DIRECTORY_SEPARATOR . 'template.json';
        $manifesto = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;
        if (!is_array($manifesto) || ($manifesto['slug'] ?? null) !== $slug) {
            throw new \InvalidArgumentException("Template '{$slug}' não encontrado em {$diretorio}.");
        }

        $resultado = $this->validator->validar($manifesto);
        if (!$resultado->valido()) {
            throw new \InvalidArgumentException("Manifesto de '{$slug}' inválido: " . json_encode($resultado->erros(), JSON_UNESCAPED_UNICODE));
        }

        $dados = [
            'nome'      => $manifesto['nome'],
            'versao'    => $manifesto['versao'],
            'autor'     => $manifesto['autor'] ?? null,
            'descricao' => $manifesto['descricao'] ?? null,
            'manifest'  => json_encode($manifesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $existente = $this->templates->where('slug', $slug)->first();
        if ($existente) {
            $this->templates->allowCallbacks(false)->update($existente['id'], $dados);
            $id = (int) $existente['id'];
        } else {
            $id = (int) $this->templates->allowCallbacks(false)->insert($dados + ['slug' => $slug, 'origem' => 'core', 'ativo' => 0]);
            if ($id === 0) {
                throw new \RuntimeException('Não foi possível registrar o template: ' . implode(' ', $this->templates->errors()));
            }
        }

        $repo = new SiteTemplateRepository($this->templates);
        $ativado = false;
        if ($this->templates->where('ativo', 1)->countAllResults() === 0) {
            $ativado = $repo->ativar($id);
        }
        $repo->clearCache();

        $this->assets->publicar($slug, $manifesto['versao'], $diretorio);

        $seedImportado = null;
        $seedArquivo = $diretorio . DIRECTORY_SEPARATOR . 'seed' . DIRECTORY_SEPARATOR . 'content.json';
        if (is_file($seedArquivo) && $this->paginas->withDeleted()->countAllResults() === 0) {
            $seedImportado = $this->seed->importar(json_decode((string) file_get_contents($seedArquivo), true) ?: [], $manifesto);
        }

        return ['template' => $slug, 'versao' => $manifesto['versao'], 'ativado' => $ativado, 'assets' => true, 'seed' => $seedImportado];
    }
}
