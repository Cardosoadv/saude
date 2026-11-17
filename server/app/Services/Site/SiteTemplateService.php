<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\Site\SiteTemplateRepository;

class SiteTemplateService
{
    public function __construct(
        private ?SiteTemplateRepository $repository = null,
        private ?\Config\SiteTemplate $config = null,
    ) {
        $this->repository ??= new SiteTemplateRepository();
        $this->config     ??= config(\Config\SiteTemplate::class);
    }

    public function ativo(): ?array
    {
        return $this->repository->getAtivo();
    }

    /** Manifesto do template ativo, ou null se não há template ativo. */
    public function manifestoAtivo(): ?array
    {
        $ativo = $this->ativo();

        return $ativo ? $this->manifesto($ativo) : null;
    }

    /**
     * O que a SPA precisa para montar os formulários de edição: layouts, locais de
     * menu, tipos de seção com seus campos, textos de interface e tokens de tema.
     *
     * @return array<string, mixed>|null
     */
    public function schema(): ?array
    {
        $ativo = $this->ativo();
        if ($ativo === null) {
            return null;
        }
        $m = $this->manifesto($ativo);

        return [
            'template' => ['slug' => $ativo['slug'], 'nome' => $ativo['nome'], 'versao' => $m['versao'] ?? $ativo['versao']],
            'layouts'  => $m['layouts'] ?? [],
            'menus'    => $m['menus'] ?? [],
            'secoes'   => $m['secoes'] ?? [],
            'textos'   => $m['textos'] ?? [],
            'tema'     => $m['tema'] ?? [],
        ];
    }

    /** Diretório do pacote, conforme a origem (core, cli, upload). */
    public function diretorio(array $template): string
    {
        $base = $this->config->diretorios[$template['origem']] ?? null;
        if ($base === null || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $template['slug'])) {
            throw new \RuntimeException("Template '{$template['slug']}' com origem ou slug inválido.");
        }

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $template['slug'];
    }

    /**
     * Manifesto do template. Para templates do pacote da aplicação (core/cli) vale o
     * `template.json` do disco, que muda a cada deploy; para os enviados pelo painel, o
     * gravado no banco na instalação (com o `template.json` do pacote como reserva).
     */
    public function manifesto(array $template): array
    {
        if ($template['origem'] !== 'upload') {
            $arquivo = $this->diretorio($template) . DIRECTORY_SEPARATOR . 'template.json';
            $doDisco = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;
            if (is_array($doDisco)) {
                return $doDisco;
            }
        }

        $gravado = $template['manifest'] ? json_decode($template['manifest'], true) : null;
        if (is_array($gravado)) {
            return $gravado;
        }

        $arquivo = $this->diretorio($template) . DIRECTORY_SEPARATOR . 'template.json';

        return is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];
    }

    /** Motor de view do template: templates enviados pelo painel NUNCA usam PHP. */
    public function motor(array $template, array $manifesto): string
    {
        if ($template['origem'] === 'upload') {
            return 'twig';
        }

        return ($manifesto['motor'] ?? 'php') === 'twig' ? 'twig' : 'php';
    }

    public function listar(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Ativa o template pelo slug. Só um template fica ativo por vez; o
     * conteúdo do site nunca é alterado por esta operação.
     *
     * @throws \InvalidArgumentException se o slug não estiver instalado
     */
    public function ativar(string $slug): array
    {
        $template = $this->repository->getBySlug($slug);
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$slug}' não está instalado.");
        }
        if (!$this->repository->ativar((int) $template['id'])) {
            throw new \RuntimeException("Falha ao ativar o template '{$slug}'.");
        }
        return $this->repository->getBySlug($slug);
    }
}
