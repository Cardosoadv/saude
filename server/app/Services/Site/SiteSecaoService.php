<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Libraries\Site\Template\ReferenciasDeSecao;
use App\Libraries\Site\Template\SecaoDadosNormalizador;
use App\Libraries\Site\Template\SecaoDadosValidator;
use App\Models\Site\SiteMidiaModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;

/**
 * Seções de uma página (API administrativa, issue #648).
 *
 * `dados` é sempre validado contra o schema do tipo no template ativo e
 * gravado normalizado (só campos declarados; `rich_text` sanitizado). Erros
 * saem por caminho de campo com o prefixo `dados.` (`dados.botoes.1.rotulo`).
 */
class SiteSecaoService
{
    public function __construct(
        private ?SiteSecaoModel $secoes = null,
        private ?SitePaginaModel $paginas = null,
        private ?SiteMidiaModel $midias = null,
        private ?SiteTemplateService $templates = null,
        private ?SecaoDadosValidator $validator = null,
        private ?SecaoDadosNormalizador $normalizador = null,
    ) {
        $this->secoes       ??= new SiteSecaoModel();
        $this->paginas      ??= new SitePaginaModel();
        $this->midias       ??= new SiteMidiaModel();
        $this->templates    ??= new SiteTemplateService();
        $this->validator    ??= new SecaoDadosValidator();
        $this->normalizador ??= new SecaoDadosNormalizador();
    }

    /**
     * @param array{tipo?:string, dados?:array, ancora?:?string, visivel?:bool} $entrada
     * @return array{secao: array<string, mixed>, avisos: list<string>}
     */
    public function criar(int $paginaId, array $entrada): array
    {
        $this->paginas->find($paginaId) ?? throw new NaoEncontrado('Página não encontrada.');

        $tipo = (string) ($entrada['tipo'] ?? '');
        $campos = $this->camposDoTipo($tipo);
        [$dados, $avisos] = $this->prepara($campos, (array) ($entrada['dados'] ?? []));
        $ancora = $this->ancora($entrada['ancora'] ?? null, $paginaId);

        $ordem = (int) ($this->secoes->selectMax('ordem')->where('pagina_id', $paginaId)->first()['ordem'] ?? 0) + 1;
        $id = $this->secoes->insert([
            'pagina_id' => $paginaId,
            'tipo'      => $tipo,
            'ancora'    => $ancora,
            'ordem'     => $ordem,
            'visivel'   => array_key_exists('visivel', $entrada) ? (int) (bool) $entrada['visivel'] : 1,
            'dados'     => $this->json($dados),
        ]);
        if (!$id) {
            throw new ErroDeValidacao($this->secoes->errors());
        }
        SiteCache::limpar();

        return ['secao' => $this->apresenta($this->secoes->find($id)), 'avisos' => $avisos];
    }

    /**
     * PUT/PATCH: `dados`, se enviado, substitui o conteúdo inteiro da seção (o formulário da SPA
     * manda todos os campos). O `tipo` não muda. Seção de tipo que o template ativo não tem só
     * aceita `visivel` e `ancora`.
     *
     * @return array{secao: array<string, mixed>, avisos: list<string>}
     */
    public function atualizar(int $id, array $entrada): array
    {
        $secao = $this->achar($id);
        $mudancas = [];
        $avisos = [];

        if (array_key_exists('tipo', $entrada) && $entrada['tipo'] !== $secao['tipo']) {
            throw ErroDeValidacao::campo('tipo', 'O tipo de uma seção não pode ser alterado. Exclua e crie outra.');
        }
        if (array_key_exists('dados', $entrada)) {
            [$dados, $avisos] = $this->prepara($this->camposDoTipo($secao['tipo']), (array) $entrada['dados']);
            $mudancas['dados'] = $this->json($dados);
        }
        if (array_key_exists('ancora', $entrada)) {
            $mudancas['ancora'] = $this->ancora($entrada['ancora'], (int) $secao['pagina_id'], $id);
        }
        if (array_key_exists('visivel', $entrada)) {
            $mudancas['visivel'] = (int) (bool) $entrada['visivel'];
        }

        if ($mudancas !== [] && !$this->secoes->update($id, $mudancas)) {
            throw new ErroDeValidacao($this->secoes->errors());
        }
        SiteCache::limpar();

        return ['secao' => $this->apresenta($this->achar($id)), 'avisos' => $avisos];
    }

    public function excluir(int $id): void
    {
        $this->achar($id);
        $this->secoes->delete($id, true);
        SiteCache::limpar();
    }

    /**
     * Define a ordem das seções da página. `$ids` precisa conter só seções desta página;
     * as que ficarem de fora vão para o fim, na ordem atual.
     *
     * @param list<int|string> $ids
     * @return list<array<string, mixed>> seções na nova ordem
     */
    public function reordenar(int $paginaId, array $ids): array
    {
        $this->paginas->find($paginaId) ?? throw new NaoEncontrado('Página não encontrada.');

        $atuais = $this->secoes->where('pagina_id', $paginaId)->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findColumn('id') ?? [];
        $ids = array_map('intval', $ids);

        if (count($ids) !== count(array_unique($ids))) {
            throw ErroDeValidacao::campo('ordem', 'A lista tem ids repetidos.');
        }
        $estranhas = array_diff($ids, array_map('intval', $atuais));
        if ($estranhas !== []) {
            throw ErroDeValidacao::campo('ordem', 'Seções que não pertencem a esta página: ' . implode(', ', $estranhas) . '.');
        }

        $db = \Config\Database::connect();
        $db->transStart();
        foreach (array_merge($ids, array_diff(array_map('intval', $atuais), $ids)) as $posicao => $secaoId) {
            $this->secoes->builder()->where('id', $secaoId)->where('pagina_id', $paginaId)->update(['ordem' => $posicao + 1]);
        }
        $db->transComplete();
        SiteCache::limpar();

        return array_map(
            fn (array $s) => $this->apresenta($s),
            $this->secoes->where('pagina_id', $paginaId)->orderBy('ordem', 'ASC')->findAll()
        );
    }

    // ------------------------------------------------------------------ interno

    private function achar(int $id): array
    {
        return $this->secoes->find($id) ?? throw new NaoEncontrado('Seção não encontrada.');
    }

    private function apresenta(array $secao): array
    {
        $secao['dados'] = $secao['dados'] ? (json_decode($secao['dados'], true) ?? []) : [];

        return $secao;
    }

    /** @return array<string, array> campos do tipo no template ativo */
    private function camposDoTipo(string $tipo): array
    {
        $manifesto = $this->templates->manifestoAtivo()
            ?? throw ErroDeValidacao::campo('tipo', 'Não há template ativo.');
        $def = $manifesto['secoes'][$tipo] ?? null;
        if ($def === null) {
            throw ErroDeValidacao::campo('tipo', $tipo === '' ? 'Informe o tipo da seção.' : "O tipo '{$tipo}' não existe no template ativo.");
        }

        return $def['campos'] ?? [];
    }

    /**
     * Normaliza, aplica padrões e valida (formato + referências).
     *
     * @return array{0: array, 1: list<string>} dados prontos e avisos
     */
    private function prepara(array $campos, array $bruto): array
    {
        $avisos = [];
        foreach ($this->normalizador->descartadas($campos, $bruto) as $chave) {
            $avisos[] = "O campo '{$chave}' não existe neste tipo de seção e foi descartado.";
        }

        $dados = $this->validator->aplicarPadroes($campos, $this->normalizador->normalizar($campos, $bruto));
        $res = $this->validator->validar($campos, $dados);
        $erros = $res->erros();

        $referencias = new ReferenciasDeSecao(
            fn (int $id) => $this->paginas->find($id) !== null,
            fn (int $id) => $this->midias->find($id) !== null,
        );
        $erros += $referencias->erros($campos, $dados);

        if ($erros !== []) {
            $comPrefixo = [];
            foreach ($erros as $caminho => $mensagem) {
                $comPrefixo["dados.{$caminho}"] = $mensagem;
            }
            throw new ErroDeValidacao($comPrefixo);
        }

        return [$dados, $avisos];
    }

    private function ancora(mixed $ancora, int $paginaId, ?int $ignorarId = null): ?string
    {
        if ($ancora === null || $ancora === '') {
            return null;
        }
        if (!is_string($ancora) || !preg_match('/^[a-z0-9_-]{1,60}$/', $ancora)) {
            throw ErroDeValidacao::campo('ancora', 'Use letras minúsculas, números, hífen e sublinhado (até 60).');
        }

        $q = $this->secoes->where('pagina_id', $paginaId)->where('ancora', $ancora);
        if ($ignorarId !== null) {
            $q->where('id !=', $ignorarId);
        }
        if ($q->countAllResults() > 0) {
            throw ErroDeValidacao::campo('ancora', 'Já existe uma seção com esta âncora na página.');
        }

        return $ancora;
    }

    private function json(array $dados): string
    {
        return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
