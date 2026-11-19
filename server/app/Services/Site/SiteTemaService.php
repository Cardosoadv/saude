<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Tema;
use App\Repositories\Site\SiteConfiguracaoRepository;

/**
 * Aparência do site (API administrativa, issue #651): cores e fontes do template ativo
 * que a clínica pode sobrescrever. As escolhas ficam em `tema.cores.<token>` e
 * `tema.fontes.<token>` nas configurações; valor vazio ou `null` volta ao padrão.
 */
class SiteTemaService
{
    public function __construct(
        private ?SiteConfiguracaoService $configuracoes = null,
        private ?SiteConfiguracaoRepository $repositorio = null,
        private ?SiteTemplateService $templates = null,
        private ?Tema $tema = null,
    ) {
        $this->configuracoes ??= new SiteConfiguracaoService();
        $this->repositorio   ??= new SiteConfiguracaoRepository();
        $this->templates     ??= new SiteTemplateService();
        $this->tema          ??= new Tema();
    }

    /**
     * Tokens do template ativo com o padrão e o valor atual de cada um.
     *
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        $manifesto = $this->templates->manifestoAtivo() ?? throw ErroDeValidacao::campo('template', 'Não há template ativo.');
        $mapa = $this->repositorio->mapa();
        $tema = $manifesto['tema'] ?? [];

        return $this->tema->tokens($tema, $mapa) + ['css' => $this->tema->css($tema, $mapa)];
    }

    /**
     * Grava cores e fontes (tudo ou nada). Corpo: `{"cores": {"gold": "#B08E35"}, "fontes": {"serif": "Lora"}}`;
     * `null` ou '' restaura o padrão daquele token.
     *
     * @return array<string, mixed>
     */
    public function salvar(array $entrada): array
    {
        $erros = [];
        foreach (array_diff(array_keys($entrada), ['cores', 'fontes']) as $chave) {
            $erros[(string) $chave] = "Use apenas 'cores' e 'fontes'.";
        }

        $valores = [];
        foreach (['cores', 'fontes'] as $grupo) {
            $bloco = $entrada[$grupo] ?? [];
            if (!is_array($bloco) || ($bloco !== [] && array_is_list($bloco))) {
                $erros[$grupo] = 'Deve ser um objeto {token: valor}.';
                continue;
            }
            foreach ($bloco as $token => $valor) {
                $valores["tema.{$grupo}.{$token}"] = $valor;
            }
        }
        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }

        try {
            $this->configuracoes->atualizar($valores);
        } catch (ErroDeValidacao $e) {
            // Devolve os erros com o caminho do corpo da requisição (`cores.gold`), não da chave interna.
            $porCaminho = [];
            foreach ($e->erros() as $chave => $mensagem) {
                $porCaminho[preg_replace('/^tema\./', '', $chave)] = $mensagem;
            }
            throw new ErroDeValidacao($porCaminho);
        }

        return $this->obter();
    }

    /** Remove todas as personalizações; o site volta ao visual do template. */
    public function restaurar(): array
    {
        foreach ($this->repositorio->linhas() as $linha) {
            if (str_starts_with($linha['chave'], 'tema.')) {
                $this->repositorio->remover($linha['chave']);
            }
        }
        SiteCache::limpar();

        return $this->obter();
    }
}
