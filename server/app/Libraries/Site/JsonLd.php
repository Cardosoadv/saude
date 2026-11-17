<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/**
 * Dados estruturados (schema.org) do escritório, montados só com o que está nas
 * configurações do site. `grafo()` junta tudo num único `@graph` por página
 * (LegalService, WebSite, advogados, WebPage/BreadcrumbList, BlogPosting, FAQPage).
 * Campos ausentes são omitidos; sem nome, nada é gerado.
 */
final class JsonLd
{
    /** Chaves de `redes.*` que viram `sameAs`. */
    private const REDES = ['instagram', 'linkedin', 'facebook', 'youtube'];

    /** Dias aceitos em `contato.horario[].dias` (schema.org/DayOfWeek). */
    private const DIAS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** `@id` da organização (o escritório) no grafo. */
    public static function idOrganizacao(string $url): string
    {
        return rtrim($url, '/') . '/#organizacao';
    }

    public static function idWebSite(string $url): string
    {
        return rtrim($url, '/') . '/#website';
    }

    /**
     * @param array<string, mixed> $config Mapa de configurações do site (`identidade.*`, `contato.*`, `redes.*`)
     * @param string $url URL da raiz do site
     * @param string|null $logo URL absoluta do logo
     * @param list<string> $areas Títulos das áreas de atuação publicadas (`knowsAbout`)
     * @param string|null $imagem URL absoluta da imagem principal (`image`); sem ela, o logo
     */
    public static function legalService(array $config, string $url, ?string $logo = null, array $areas = [], ?string $imagem = null): ?array
    {
        $nome = trim((string) ($config['identidade.nome'] ?? ''));
        if ($nome === '') {
            return null;
        }

        $dados = ['@context' => 'https://schema.org', '@type' => 'LegalService', '@id' => self::idOrganizacao($url), 'name' => $nome, 'url' => $url];

        $razao = trim((string) ($config['identidade.razao_social'] ?? ''));
        if ($razao !== '') {
            $dados['legalName'] = $razao;
        }
        $cnpj = Cnpj::digitos((string) ($config['identidade.cnpj'] ?? ''));
        if (strlen($cnpj) === 14) {
            $dados['taxID'] = Cnpj::formatar($cnpj);
        }
        if ($logo) {
            $dados['logo'] = $logo;
        }
        if ($imagem || $logo) {
            $dados['image'] = $imagem ?: $logo;
        }
        $faixa = trim((string) ($config['identidade.faixa_preco'] ?? ''));
        if ($faixa !== '') {
            $dados['priceRange'] = $faixa;
        }
        if (!empty($config['contato.telefone'])) {
            $dados['telephone'] = (string) $config['contato.telefone'];
        }
        if (!empty($config['contato.email'])) {
            $dados['email'] = (string) $config['contato.email'];
        }

        $enderecos = [];
        foreach ((array) ($config['contato.enderecos'] ?? []) as $unidade) {
            $endereco = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $unidade['rua'] ?? null,
                'addressLocality' => $unidade['cidade'] ?? null,
                'addressRegion'   => $unidade['uf'] ?? null,
                'postalCode'      => $unidade['cep'] ?? null,
                'addressCountry'  => 'BR',
            ]);
            // Só vale com ao menos rua ou cidade (o país sozinho não informa nada).
            if (isset($endereco['streetAddress']) || isset($endereco['addressLocality'])) {
                $enderecos[] = $endereco;
            }
        }
        if ($enderecos !== []) {
            $dados['address'] = count($enderecos) === 1 ? $enderecos[0] : $enderecos;
        }

        $geo = self::geo($config);
        if ($geo !== null) {
            $dados['geo'] = $geo;
        }
        $mapa = (string) ($config['contato.mapa_url'] ?? '');
        if (preg_match('#^https?://\S+$#i', $mapa)) {
            $dados['hasMap'] = $mapa;
        }

        $atendida = self::textos($config['contato.area_atendida'] ?? []);
        if ($atendida !== []) {
            $dados['areaServed'] = $atendida;
        }
        $conhece = self::textos($areas);
        if ($conhece !== []) {
            $dados['knowsAbout'] = $conhece;
        }
        $horarios = self::horarios($config['contato.horario'] ?? []);
        if ($horarios !== []) {
            $dados['openingHoursSpecification'] = $horarios;
        }

        $redes = [];
        foreach (self::REDES as $rede) {
            $link = (string) ($config["redes.{$rede}"] ?? '');
            if (preg_match('#^https?://#i', $link)) {
                $redes[] = $link;
            }
        }
        if ($redes !== []) {
            $dados['sameAs'] = $redes;
        }

        return $dados;
    }

    /**
     * Grafo único da página.
     *
     * `$ctx`: `areas` (títulos das áreas publicadas), `imagem` (URL absoluta), `pagina`
     * (`{titulo, url, tipo, slug}`; `tipo` = home|area|pagina|sistema), `artigo` (`{titulo, url, resumo,
     * publicado, modificado, imagem}`; vira BlogPosting sob "Notícias") e `faq` (lista de
     * `{pergunta, resposta}`).
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $ctx
     */
    public static function grafo(array $config, string $url, ?string $logo = null, array $ctx = []): ?array
    {
        $org = self::legalService($config, $url, $logo, (array) ($ctx['areas'] ?? []), $ctx['imagem'] ?? null);
        if ($org === null) {
            return null;
        }
        unset($org['@context']);
        $idOrg = ['@id' => $org['@id']];
        $nos   = [$org];

        $nos[] = array_filter([
            '@type'      => 'WebSite',
            '@id'        => self::idWebSite($url),
            'url'        => $url,
            'name'       => (string) ($config['identidade.nome_curto'] ?? '') !== '' ? (string) $config['identidade.nome_curto'] : $org['name'],
            'inLanguage' => 'pt-BR',
            'publisher'  => $idOrg,
        ]);

        $pessoas = self::advogados($config['identidade.advogados'] ?? [], $url, $idOrg);
        if ($pessoas !== []) {
            $org['member'] = array_map(static fn (array $p): array => ['@id' => $p['@id']], $pessoas);
            $nos[0]        = $org;
            array_push($nos, ...$pessoas);
        }

        $pagina = (array) ($ctx['pagina'] ?? []);
        $artigo = (array) ($ctx['artigo'] ?? []);
        $urlPagina = (string) ($artigo['url'] ?? $pagina['url'] ?? '');
        $ehHome = ($pagina['tipo'] ?? '') === 'home';

        if ($urlPagina !== '' && !$ehHome) {
            $idPagina = $urlPagina . '#webpage';
            $titulo   = (string) ($artigo['titulo'] ?? $pagina['titulo'] ?? '');

            $migalhas = [['Início', $url]];
            if ($artigo !== []) {
                $migalhas[] = ['Notícias', rtrim($url, '/') . '/noticias'];
            }
            $migalhas[] = [$titulo, $urlPagina];

            $nos[] = array_filter([
                '@type'      => ($pagina['slug'] ?? '') === 'quem-somos' ? 'AboutPage' : 'WebPage',
                '@id'        => $idPagina,
                'url'        => $urlPagina,
                'name'       => $titulo,
                'inLanguage' => 'pt-BR',
                'isPartOf'   => ['@id' => self::idWebSite($url)],
                'about'      => ($pagina['slug'] ?? '') === 'quem-somos' ? $idOrg : '',
                'breadcrumb' => ['@id' => $urlPagina . '#breadcrumb'],
            ], static fn ($v) => $v !== '');
            $nos[] = [
                '@type'           => 'BreadcrumbList',
                '@id'             => $urlPagina . '#breadcrumb',
                'itemListElement' => array_map(static fn (array $m, int $i): array => [
                    '@type' => 'ListItem', 'position' => $i + 1, 'name' => $m[0], 'item' => $m[1],
                ], $migalhas, array_keys($migalhas)),
            ];

            if ($artigo !== [] && $titulo !== '') {
                $post = [
                    '@type'            => 'BlogPosting',
                    '@id'              => $urlPagina . '#artigo',
                    'headline'         => mb_substr($titulo, 0, 110),
                    'description'      => trim((string) ($artigo['resumo'] ?? '')),
                    'datePublished'    => self::data($artigo['publicado'] ?? null),
                    'dateModified'     => self::data($artigo['modificado'] ?? $artigo['publicado'] ?? null),
                    'inLanguage'       => 'pt-BR',
                    'author'           => $idOrg,
                    'publisher'        => $idOrg,
                    'image'            => $artigo['imagem'] ?? $ctx['imagem'] ?? $logo,
                    'mainEntityOfPage' => ['@id' => $idPagina],
                ];
                $nos[] = array_filter($post, static fn ($v) => $v !== null && $v !== '');
            }
        }

        $perguntas = [];
        foreach ((array) ($ctx['faq'] ?? []) as $item) {
            $p = trim((string) ($item['pergunta'] ?? ''));
            $r = trim(strip_tags((string) ($item['resposta'] ?? '')));
            if ($p !== '' && $r !== '') {
                $perguntas[] = ['@type' => 'Question', 'name' => $p, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $r]];
            }
        }
        if ($perguntas !== [] && $urlPagina !== '') {
            $nos[] = ['@type' => 'FAQPage', '@id' => $urlPagina . '#faq', 'mainEntity' => $perguntas];
        }

        return ['@context' => 'https://schema.org', '@graph' => $nos];
    }

    /** @return list<string> textos não vazios */
    private static function textos(mixed $lista): array
    {
        $saida = [];
        foreach (is_array($lista) ? $lista : [] as $t) {
            $t = trim((string) $t);
            if ($t !== '' && !in_array($t, $saida, true)) {
                $saida[] = $t;
            }
        }

        return $saida;
    }

    /** @return array{'@type':string, latitude:float, longitude:float}|null */
    private static function geo(array $config): ?array
    {
        $lat = $config['contato.latitude'] ?? '';
        $lng = $config['contato.longitude'] ?? '';
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        return ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    /**
     * @param mixed $lista `[{dias: [Monday…], abre: "09:00", fecha: "18:00"}]`
     * @return list<array<string, mixed>>
     */
    private static function horarios(mixed $lista): array
    {
        $saida = [];
        foreach (is_array($lista) ? $lista : [] as $h) {
            $dias = array_values(array_intersect(self::DIAS, (array) ($h['dias'] ?? [])));
            $abre = (string) ($h['abre'] ?? '');
            $fecha = (string) ($h['fecha'] ?? '');
            if ($dias !== [] && preg_match('/^\d{2}:\d{2}$/', $abre) && preg_match('/^\d{2}:\d{2}$/', $fecha)) {
                $saida[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $dias, 'opens' => $abre, 'closes' => $fecha];
            }
        }

        return $saida;
    }

    /**
     * @param mixed $lista `[{nome, cargo?, oab?}]`
     * @param array{'@id':string} $idOrg
     * @return list<array<string, mixed>>
     */
    private static function advogados(mixed $lista, string $url, array $idOrg): array
    {
        $pessoas = [];
        foreach (is_array($lista) ? $lista : [] as $i => $a) {
            $nome = trim((string) ($a['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $oab   = trim((string) ($a['oab'] ?? ''));
            $cargo = trim((string) ($a['cargo'] ?? ''));
            $pessoa = [
                '@type'    => 'Person',
                '@id'      => rtrim($url, '/') . '/#advogado-' . ($i + 1),
                'name'     => $nome,
                'jobTitle' => $cargo !== '' ? $cargo : 'Advogado(a)',
                'worksFor' => $idOrg,
            ];
            if ($oab !== '') {
                $pessoa['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'OAB', 'value' => $oab];
            }
            $pessoas[] = $pessoa;
        }

        return $pessoas;
    }

    /** Data ISO 8601 a partir de `Y-m-d H:i:s`; null se vazia ou inválida. */
    private static function data(mixed $valor): ?string
    {
        $t = is_string($valor) && $valor !== '' ? strtotime($valor) : false;

        return $t === false ? null : date('c', $t);
    }

    /** Tag `<script type="application/ld+json">` segura para HTML, ou '' se não há dados. */
    public static function script(?array $dados): string
    {
        if ($dados === null) {
            return '';
        }

        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
