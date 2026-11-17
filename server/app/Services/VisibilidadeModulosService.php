<?php

namespace App\Services;

use Closure;

/**
 * Decide se um bloco de dados de outro módulo pode ir na resposta.
 *
 * Um bloco é liberado quando o módulo está ativo E o usuário tem ao menos
 * uma das permissões indicadas (sem permissões, basta o módulo estar
 * ativo). Blocos bloqueados vão vazios ([]), para o front continuar
 * recebendo o mesmo formato sem os dados.
 */
class VisibilidadeModulosService
{
    private ModulosService $modulos;

    /** @var Closure(string ...): bool */
    private Closure $pode;

    /**
     * @param Closure(string ...): bool|null $pode Verifica permissões do usuário; padrão: usuário logado no Shield.
     */
    public function __construct(?ModulosService $modulos = null, ?Closure $pode = null)
    {
        $this->modulos = $modulos ?? service('modulos');
        $this->pode    = $pode ?? static function (string ...$permissoes): bool {
            $usuario = auth()->user();

            return $usuario !== null && $usuario->can(...$permissoes);
        };
    }

    public function liberado(string $slug, string ...$permissoes): bool
    {
        return $this->modulos->isActive($slug) && ($permissoes === [] || ($this->pode)(...$permissoes));
    }

    /**
     * @param array<string, list<string>> $blocos chave => [slug, permissão, ...]
     */
    public function ocultarBlocos(array $dados, array $blocos): array
    {
        foreach ($blocos as $chave => $regra) {
            $slug = array_shift($regra);
            if (array_key_exists($chave, $dados) && !$this->liberado($slug, ...$regra)) {
                $dados[$chave] = [];
            }
        }

        return $dados;
    }
}
