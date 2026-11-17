<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SiteConfiguracaoModel;
use App\Repositories\BaseRepository;

class SiteConfiguracaoRepository extends BaseRepository
{
    public function __construct(?SiteConfiguracaoModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SiteConfiguracaoModel();
        $this->cacheKey = 'site_configuracoes';
    }

    private bool $semAuditoria = false;

    /** Grava sem registrar auditoria (carga inicial de template, sem usuário logado). */
    public function semAuditoria(): static
    {
        $this->semAuditoria = true;

        return $this;
    }

    /**
     * Todas as configurações como [chave => valor]. Valores `json` e `bool`
     * são devolvidos já decodificados.
     *
     * @return array<string, mixed>
     */
    public function mapa(): array
    {
        return $this->remember('mapa', function () {
            $mapa = [];
            foreach ($this->model->findAll() as $linha) {
                $mapa[$linha['chave']] = $this->decodifica($linha['valor'], $linha['tipo']);
            }
            return $mapa;
        });
    }

    /**
     * Grava (cria ou atualiza) uma configuração. Valores `json` são
     * serializados; `bool` vira "1"/"0".
     */
    public function definir(string $chave, mixed $valor, ?string $tipo = null, ?string $grupo = null): void
    {
        $existente = $this->model->where('chave', $chave)->first();
        $tipo ??= $existente['tipo'] ?? 'string';

        $dados = ['valor' => $this->codifica($valor, $tipo), 'tipo' => $tipo];
        if ($grupo !== null) {
            $dados['grupo'] = $grupo;
        }

        $this->model->allowCallbacks(!$this->semAuditoria);

        if ($existente) {
            $ok = $this->model->update($existente['id'], $dados);
        } else {
            $ok = (bool) $this->model->insert($dados + ['chave' => $chave, 'grupo' => $grupo ?? $this->grupoDe($chave)]);
        }

        if (!$ok) {
            throw new \InvalidArgumentException(implode(' ', $this->model->errors()) ?: "Não foi possível gravar a configuração {$chave}.");
        }
        $this->clearCache();
    }

    /**
     * Todas as linhas (chave, valor decodificado, tipo, grupo), por grupo e chave.
     *
     * @return list<array{chave:string, valor:mixed, tipo:string, grupo:string}>
     */
    public function linhas(): array
    {
        $linhas = [];
        foreach ($this->model->orderBy('grupo', 'ASC')->orderBy('chave', 'ASC')->findAll() as $l) {
            $linhas[] = [
                'chave' => $l['chave'], 'tipo' => $l['tipo'], 'grupo' => $l['grupo'],
                'valor' => $this->decodifica($l['valor'], $l['tipo']),
            ];
        }
        return $linhas;
    }

    /** Remove a configuração (volta ao padrão do template). */
    public function remover(string $chave): void
    {
        $linha = $this->model->where('chave', $chave)->first();
        if ($linha) {
            $this->model->allowCallbacks(!$this->semAuditoria)->delete($linha['id']);
        }
        $this->clearCache();
    }

    /** O grupo padrão é o prefixo da chave (`seo.titulo_padrao` → `seo`). */
    private function grupoDe(string $chave): string
    {
        return explode('.', $chave, 2)[0];
    }

    private function codifica(mixed $valor, string $tipo): ?string
    {
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'json'  => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'bool'  => $valor ? '1' : '0',
            default => (string) $valor,
        };
    }

    private function decodifica(?string $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'json'  => json_decode($valor, true),
            'bool'  => $valor === '1',
            'imagem' => ctype_digit($valor) ? (int) $valor : null,
            default => $valor,
        };
    }
}
