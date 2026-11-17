<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Cnpj;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\HtmlSanitizer;
use App\Libraries\Site\RobotsTxt;
use App\Models\Site\SiteMidiaModel;
use App\Repositories\Site\SiteConfiguracaoRepository;

class SiteConfiguracaoService
{
    private const GRUPOS = ['identidade', 'contato', 'redes', 'seo', 'integracoes', 'lgpd', 'tema', 'textos', 'noticias'];
    private const TIPOS  = ['string', 'text', 'json', 'imagem', 'cor', 'bool'];
    /** Chaves que guardam o id de uma mídia da biblioteca. */
    private const CHAVES_DE_IMAGEM = ['identidade.logo', 'identidade.logo_rodape', 'identidade.logo_email', 'identidade.favicon', 'seo.og_image'];
    private const DIAS_DA_SEMANA = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private const CAMPOS_UNIDADE = ['titulo', 'linha1', 'linha2', 'resumo', 'rua', 'cidade', 'uf', 'cep'];

    public function __construct(
        private ?SiteConfiguracaoRepository $repository = null,
        private ?SiteMidiaModel $midias = null,
        private ?SiteTemplateService $templates = null,
    ) {
        $this->repository ??= new SiteConfiguracaoRepository();
        $this->midias     ??= new SiteMidiaModel();
        $this->templates  ??= new SiteTemplateService();
    }

    public function obter(string $chave, mixed $padrao = null): mixed
    {
        return $this->repository->mapa()[$chave] ?? $padrao;
    }

    /** @return array<string, mixed> */
    public function todas(): array
    {
        return $this->repository->mapa();
    }

    /**
     * Grava várias configurações de uma vez; tudo ou nada.
     *
     * @param array<string, mixed> $valores [chave => valor]
     */
    public function definirLote(array $valores): void
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            foreach ($valores as $chave => $valor) {
                $this->repository->definir((string) $chave, $valor);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Configurações agrupadas, para a tela de configurações.
     *
     * @return array<string, list<array{chave:string, valor:mixed, tipo:string, grupo:string}>>
     */
    public function agrupadas(): array
    {
        $grupos = array_fill_keys(self::GRUPOS, []);
        foreach ($this->repository->linhas() as $linha) {
            $grupos[$linha['grupo']][] = $linha;
        }
        return $grupos;
    }

    /**
     * Atualiza várias configurações de uma vez (tudo ou nada). Cada valor pode vir puro ou como
     * `{valor, tipo}` (para criar uma chave nova com tipo explícito; sem isso o tipo é deduzido).
     * Valor vazio em `textos.*` ou `tema.*` volta ao padrão do template.
     *
     * @param array<string, mixed> $entrada [chave => valor]
     * @throws ErroDeValidacao erros por chave
     */
    public function atualizar(array $entrada): array
    {
        $existentes = [];
        foreach ($this->repository->linhas() as $linha) {
            $existentes[$linha['chave']] = $linha['tipo'];
        }
        $manifesto = $this->templates->manifestoAtivo();
        $textosDoTemplate = $manifesto['textos'] ?? null;
        $temaDoTemplate = $manifesto['tema'] ?? null;

        $erros = [];
        $gravar = [];
        foreach ($entrada as $chave => $bruto) {
            $chave = (string) $chave;
            [$valor, $tipoPedido] = $this->desembrulha($bruto);

            if (!preg_match('/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/', $chave) || strlen($chave) > 100) {
                $erros[$chave] = 'Chave inválida (use grupo.nome, em minúsculas).';
                continue;
            }
            $grupo = explode('.', $chave, 2)[0];
            if (!in_array($grupo, self::GRUPOS, true)) {
                $erros[$chave] = 'Grupo desconhecido. Use: ' . implode(', ', self::GRUPOS) . '.';
                continue;
            }
            if ($grupo === 'textos' && $textosDoTemplate !== null && !array_key_exists(substr($chave, 7), $textosDoTemplate)) {
                $erros[$chave] = 'Este texto não existe no template ativo.';
                continue;
            }

            if ($grupo === 'tema') {
                $erroTema = $this->validaTokenDoTema($chave, $temaDoTemplate);
                if ($erroTema !== null) {
                    $erros[$chave] = $erroTema;
                    continue;
                }
            }

            if (in_array($grupo, ['textos', 'tema'], true) && ($valor === null || $valor === '')) {
                $gravar[$chave] = ['remover' => true];
                continue;
            }

            $tipo = $existentes[$chave] ?? $tipoPedido ?? $this->deduzTipo($chave, $grupo, $valor);
            if (!in_array($tipo, self::TIPOS, true)) {
                $erros[$chave] = 'Tipo inválido. Use: ' . implode(', ', self::TIPOS) . '.';
                continue;
            }
            if (isset($existentes[$chave]) && $tipoPedido !== null && $tipoPedido !== $existentes[$chave]) {
                $erros[$chave] = "O tipo desta configuração é '{$existentes[$chave]}' e não pode mudar.";
                continue;
            }

            if ($tipo === 'imagem' && is_string($valor) && ctype_digit($valor)) {
                $valor = (int) $valor; // id vindo da API como texto ("1")
            }

            $erro = $this->validaValor($chave, $tipo, $valor);
            if ($erro !== null) {
                $erros[$chave] = $erro;
                continue;
            }
            if ($chave === 'identidade.cnpj' && is_string($valor)) {
                $valor = Cnpj::digitos($valor); // grava só os dígitos; a máscara é de exibição
            }
            $gravar[$chave] = ['valor' => $valor, 'tipo' => $tipo];
        }

        if ($erros !== []) {
            throw new ErroDeValidacao($erros);
        }

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            foreach ($gravar as $chave => $g) {
                isset($g['remover']) ? $this->repository->remover($chave) : $this->repository->definir($chave, $g['valor'], $g['tipo']);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        SiteCache::limpar();

        return $this->agrupadas();
    }

    /** @return array{0: mixed, 1: ?string} valor e tipo pedido */
    private function desembrulha(mixed $bruto): array
    {
        if (is_array($bruto) && array_key_exists('valor', $bruto) && array_diff(array_keys($bruto), ['valor', 'tipo']) === []) {
            return [$bruto['valor'], $bruto['tipo'] ?? null];
        }
        return [$bruto, null];
    }

    private function deduzTipo(string $chave, string $grupo, mixed $valor): string
    {
        return match (true) {
            in_array($chave, self::CHAVES_DE_IMAGEM, true) => 'imagem',
            str_starts_with($chave, 'tema.cores.')         => 'cor',
            is_bool($valor)  => 'bool',
            is_array($valor) => 'json',
            in_array($chave, ['seo.robots_extra', 'seo.llms_resumo'], true) => 'text',
            $grupo === 'textos' && is_string($valor) && mb_strlen($valor) > 200 => 'text',
            default          => 'string',
        };
    }

    /** @return string|null mensagem de erro, ou null se o valor é aceito */
    private function validaValor(string $chave, string $tipo, mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null; // limpar o campo é permitido
        }

        $erro = match ($tipo) {
            'string' => (!is_string($valor) && !is_int($valor)) ? 'Deve ser texto.'
                : (mb_strlen((string) $valor) > 500 ? 'Máximo de 500 caracteres.'
                : (preg_match('/[\r\n]/', (string) $valor) ? 'Não pode ter quebra de linha.' : null)),
            'text'   => !is_string($valor) ? 'Deve ser texto.' : (mb_strlen($valor) > 5000 ? 'Máximo de 5000 caracteres.' : null),
            'bool'   => is_bool($valor) ? null : 'Deve ser verdadeiro ou falso.',
            'cor'    => (is_string($valor) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $valor)) ? null : 'Cor deve ser hexadecimal (#RGB ou #RRGGBB).',
            'imagem' => (is_int($valor) && $valor > 0) ? ($this->midias->find($valor) ? null : 'Mídia não encontrada na biblioteca.') : 'Informe o id de uma mídia da biblioteca.',
            'json'   => (is_array($valor) && strlen((string) json_encode($valor)) <= 20000) ? null : 'Deve ser uma lista ou objeto (até 20 KB).',
            default  => 'Tipo inválido.',
        };
        return $erro ?? $this->regraDaChave($chave, $valor);
    }

    /** `tema.cores.<token>` e `tema.fontes.<token>`, com o token declarado pelo template ativo. */
    private function validaTokenDoTema(string $chave, ?array $tema): ?string
    {
        if (!preg_match('/^tema\.(cores|fontes)\.([a-z][a-z0-9-]*)$/', $chave, $m)) {
            return 'Use tema.cores.<token> ou tema.fontes.<token>.';
        }
        if ($tema !== null && !array_key_exists($m[2], (array) ($tema[$m[1]] ?? []))) {
            return "O template ativo não tem o token '{$m[2]}' em {$m[1]}.";
        }

        return null;
    }

    /** Regras específicas de algumas chaves conhecidas. */
    private function regraDaChave(string $chave, mixed $valor): ?string
    {
        switch ($chave) {
            case 'integracoes.ga_id':
                return preg_match('/^G-[A-Z0-9]{4,20}$/', (string) $valor) ? null : "ID do Google Analytics inválido (formato G-XXXXXXX).";
            case 'integracoes.ads_id':
                return $valor === '' || preg_match('/^AW-\d{6,15}$/', (string) $valor) ? null : 'ID do Google Ads inválido (formato AW-123456789).';
            case 'integracoes.ads_conversao_formulario':
            case 'integracoes.ads_conversao_whatsapp':
                return $valor === '' || preg_match('/^[A-Za-z0-9_-]{10,40}$/', (string) $valor) ? null : 'Rótulo de conversão inválido (parte após a barra em AW-…/RÓTULO).';
            case 'contato.email':
            case 'lgpd.encarregado_email':
                return filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'E-mail inválido.';
            case 'identidade.cnpj':
                return Cnpj::valido((string) $valor) ? null : 'CNPJ inválido (confira os dígitos verificadores).';
            case 'identidade.razao_social':
            case 'identidade.registro_oab':
            case 'identidade.faixa_preco':
                return mb_strlen(trim((string) $valor)) <= 200 ? null : 'Máximo de 200 caracteres.';
            case 'contato.latitude':
            case 'contato.longitude':
                $limite = $chave === 'contato.latitude' ? 90 : 180;
                return (is_numeric($valor) && abs((float) $valor) <= $limite) ? null : "Informe um número entre -{$limite} e {$limite}.";
            case 'contato.mapa_url':
                return (is_string($valor) && preg_match('#^https?://\S+$#i', $valor) && HtmlSanitizer::hrefSeguro($valor)) ? null : 'Informe a URL completa (http ou https).';
            case 'contato.area_atendida':
                if (!is_array($valor) || !array_is_list($valor) || count($valor) > 30) {
                    return 'Informe uma lista de até 30 locais.';
                }
                foreach ($valor as $local) {
                    if (!is_string($local) || trim($local) === '' || mb_strlen($local) > 100) {
                        return 'Cada local deve ter de 1 a 100 caracteres.';
                    }
                }
                return null;
            case 'contato.horario':
                if (!is_array($valor) || !array_is_list($valor) || count($valor) > 7) {
                    return 'Informe uma lista de até 7 faixas de horário.';
                }
                foreach ($valor as $faixa) {
                    $dias = is_array($faixa) ? ($faixa['dias'] ?? null) : null;
                    if (!is_array($faixa) || array_diff(array_keys($faixa), ['dias', 'abre', 'fecha']) !== []
                        || !is_array($dias) || $dias === [] || array_diff($dias, self::DIAS_DA_SEMANA) !== []
                        || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($faixa['abre'] ?? ''))
                        || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($faixa['fecha'] ?? ''))) {
                        return 'Cada faixa: {dias: [Monday…Sunday], abre: "HH:MM", fecha: "HH:MM"}.';
                    }
                }
                return null;
            case 'identidade.advogados':
                if (!is_array($valor) || !array_is_list($valor) || count($valor) > 30) {
                    return 'Informe uma lista de até 30 advogados.';
                }
                foreach ($valor as $adv) {
                    if (!is_array($adv) || array_diff(array_keys($adv), ['nome', 'cargo', 'oab']) !== []
                        || trim((string) ($adv['nome'] ?? '')) === '') {
                        return 'Cada advogado aceita só: nome (obrigatório), cargo, oab.';
                    }
                    foreach ($adv as $campo) {
                        if (!is_string($campo) || mb_strlen($campo) > 200) {
                            return 'Os campos do advogado são textos de até 200 caracteres.';
                        }
                    }
                }
                return null;
            case 'seo.descricao_padrao':
                return mb_strlen((string) $valor) <= 160 ? null : 'Máximo de 160 caracteres.';
            case 'seo.robots_extra':
                return RobotsTxt::erroNoExtra((string) $valor);
            case 'seo.llms_resumo':
                return mb_strlen((string) $valor) <= 600 ? null : 'Máximo de 600 caracteres.';
            case 'seo.sufixo_titulo':
                return mb_strlen((string) $valor) <= 100 ? null : 'Máximo de 100 caracteres.';
            case 'noticias.filtros':
                if (!is_array($valor) || !array_is_list($valor) || count($valor) > 10) {
                    return 'Informe uma lista de até 10 termos.';
                }
                foreach ($valor as $termo) {
                    if (!is_string($termo) || trim($termo) === '' || mb_strlen($termo) > 60) {
                        return 'Cada termo deve ter de 1 a 60 caracteres.';
                    }
                }
                return null;
            case 'contato.enderecos':
                if (!is_array($valor) || !array_is_list($valor) || count($valor) > 10) {
                    return 'Informe uma lista de até 10 unidades.';
                }
                foreach ($valor as $unidade) {
                    if (!is_array($unidade) || array_diff(array_keys($unidade), self::CAMPOS_UNIDADE) !== []) {
                        return 'Cada unidade aceita só: ' . implode(', ', self::CAMPOS_UNIDADE) . '.';
                    }
                    foreach ($unidade as $campo) {
                        if (!is_string($campo) || mb_strlen($campo) > 200) {
                            return 'Os campos da unidade são textos de até 200 caracteres.';
                        }
                    }
                }
                return null;
        }

        if (str_starts_with($chave, 'tema.fontes.')) {
            $permitidas = array_keys(config(\Config\SiteTemplate::class)->fontesPermitidas);

            return in_array($valor, $permitidas, true) ? null : 'Fonte fora da lista permitida: ' . implode(', ', $permitidas) . '.';
        }

        if (str_starts_with($chave, 'redes.')) {
            return (is_string($valor) && preg_match('#^https?://\S+$#i', $valor) && HtmlSanitizer::hrefSeguro($valor)) ? null : 'Informe a URL completa (http ou https).';
        }
        return null;
    }
}
