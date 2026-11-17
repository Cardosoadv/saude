<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

/**
 * Decide o que acontece com as seções existentes ao ativar um template
 * (docs/site-templates.md, "Trocar de template"). Nunca apaga conteúdo:
 *
 *  - tipo existe no novo template  → `manter` (com `padroes` aplicados e
 *    `avisos`/`erros` da revalidação dos dados);
 *  - tipo não existe               → `ocultar` (visivel=0, dados intactos).
 *
 * Seção que já estava oculta por incompatibilidade volta a poder ser exibida
 * se o tipo existir de novo: a ação `manter` não altera `visivel`.
 */
class Compatibilidade
{
    public function __construct(private ?SecaoDadosValidator $validator = null)
    {
        $this->validator ??= new SecaoDadosValidator();
    }

    /**
     * @param list<array{id:int|string, tipo:string, dados:array}> $secoes Seções do site
     * @param array<string, array> $secoesDoTemplate `secoes` do manifesto novo
     * @return list<array{id:int|string, tipo:string, acao:string, dados:array, erros:array<string,string>, avisos:array<string,string>}>
     */
    public function analisar(array $secoes, array $secoesDoTemplate): array
    {
        $plano = [];
        foreach ($secoes as $secao) {
            $def = $secoesDoTemplate[$secao['tipo']] ?? null;

            if ($def === null) {
                $plano[] = [
                    'id' => $secao['id'], 'tipo' => $secao['tipo'], 'acao' => 'ocultar',
                    'dados' => $secao['dados'], 'erros' => [], 'avisos' => [],
                ];
                continue;
            }

            $campos = $def['campos'] ?? [];
            $dados  = $this->validator->aplicarPadroes($campos, $secao['dados']);
            $res    = $this->validator->validar($campos, $dados);

            $plano[] = [
                'id' => $secao['id'], 'tipo' => $secao['tipo'], 'acao' => 'manter',
                'dados' => $dados, 'erros' => $res->erros(), 'avisos' => $res->avisos(),
            ];
        }
        return $plano;
    }
}
