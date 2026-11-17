<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Dados da clínica para documentos gerados (papel timbrado, substabelecimento, metas do painel).
 * Vêm das configurações do site (`identidade.*`, `contato.*`), as mesmas do site público, para que
 * uma instalação de outra clínica não carregue nenhum nome, telefone ou logo fixos no código.
 *
 * Chaves usadas: `identidade.nome`, `identidade.nome_curto`, `identidade.razao_social`, `identidade.cnpj`,
 * `identidade.registro_profissional`, `identidade.logo` (mídia),
 * `contato.telefone`, `contato.telefone2` (opcional), `contato.site` (opcional; senão o host de
 * `baseURL`), `contato.enderecos[0].cidade` e `identidade.cor_documentos` (opcional, `#RRGGBB`).
 */
final class DadosInstituicao
{
    public const COR_PADRAO = '#1b4965';

    public function __construct(private ?SiteContexto $contexto = null)
    {
    }

    public static function atual(): self
    {
        return new self();
    }

    private function config(string $chave, mixed $padrao = null): mixed
    {
        try {
            $this->contexto ??= SiteContexto::atual();

            return $this->contexto->config($chave, $padrao);
        } catch (\Throwable $e) {
            log_message('warning', 'Dados da clínica: configurações do site indisponíveis. ' . $e->getMessage());

            return $padrao;
        }
    }

    public function nome(): string
    {
        return trim((string) $this->config('identidade.nome', ''));
    }

    /** Nome curto; sem ele, o nome completo. */
    public function nomeCurto(): string
    {
        $curto = trim((string) $this->config('identidade.nome_curto', ''));

        return $curto !== '' ? $curto : $this->nome();
    }

    /** Razão social; sem ela, o nome da clínica. */
    public function razaoSocial(): string
    {
        $razao = trim((string) $this->config('identidade.razao_social', ''));

        return $razao !== '' ? $razao : $this->nome();
    }

    /** CNPJ com máscara (`00.000.000/0000-00`); vazio se não informado. */
    public function cnpj(): string
    {
        return Cnpj::formatar((string) $this->config('identidade.cnpj', ''));
    }

    /** Registro da clínica no conselho profissional (ex.: "CRM/MG 1234"); vazio se não informado. */
    public function registroProfissional(): string
    {
        return trim((string) $this->config('identidade.registro_profissional', ''));
    }

    /** @return list<string> */
    public function telefones(): array
    {
        $lista = [];
        foreach (['contato.telefone', 'contato.telefone2'] as $chave) {
            $t = trim((string) $this->config($chave, ''));
            if ($t !== '') {
                $lista[] = $t;
            }
        }

        return $lista;
    }

    /** Endereço do site sem protocolo (ex.: `www.exemplo.adv.br`). */
    public function site(): string
    {
        $site = trim((string) $this->config('contato.site', ''));
        if ($site === '') {
            $site = (string) (parse_url(base_url(), PHP_URL_HOST) ?: '');
        }

        return preg_replace('#^https?://#i', '', rtrim($site, '/')) ?? $site;
    }

    /** Cidade do primeiro endereço (usada na data dos documentos); vazio se não houver. */
    public function cidade(): string
    {
        $enderecos = $this->config('contato.enderecos', []);

        return is_array($enderecos) ? trim((string) ($enderecos[0]['cidade'] ?? '')) : '';
    }

    /** Cor de destaque dos documentos; só hexadecimal válido (vai para CSS). */
    public function cor(): string
    {
        $cor = (string) $this->config('identidade.cor_documentos', '');

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $cor) ? $cor : self::COR_PADRAO;
    }

    /** Logo como `data:` URI para embutir no PDF/HTML; null se a clínica não tem logo. */
    public function logoDataUri(): ?string
    {
        static $cache = [];

        // Só o logo escolhido na biblioteca de mídias (`identidade.logo`). Sem ele o documento sai com o
        // nome da clínica em texto; nunca com o logo de outra clínica que venha com o código.
        $caminho = null;
        try {
            $this->contexto ??= SiteContexto::atual();
            if ($this->contexto->config('identidade.logo') !== null) {
                $caminho = $this->contexto->logoArquivo();
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Dados da clínica: logo indisponível. ' . $e->getMessage());
        }
        if ($caminho === null || !is_file($caminho)) {
            return null;
        }

        $chave = $caminho . '|' . filemtime($caminho);
        if (!isset($cache[$chave])) {
            $mime = match (strtolower(pathinfo($caminho, PATHINFO_EXTENSION))) {
                'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg',
            };
            $cache[$chave] = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($caminho));
        }

        return $cache[$chave];
    }

}
