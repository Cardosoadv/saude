<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

/**
 * Confere se as páginas e mídias citadas em `dados` de uma seção existem
 * (campos `link` do tipo página e campos `imagem`). O validador de formato
 * (SecaoDadosValidator) não consulta o banco; esta classe recebe as buscas.
 */
class ReferenciasDeSecao
{
    /**
     * @param callable(int):bool $paginaExiste
     * @param callable(int):bool $midiaExiste
     */
    public function __construct(private $paginaExiste, private $midiaExiste)
    {
    }

    /**
     * @param array<string, array> $campos
     * @return array<string, string> erros por caminho de campo
     */
    public function erros(array $campos, array $dados, string $prefixo = ''): array
    {
        $erros = [];
        foreach ($campos as $nome => $def) {
            if (!isset($dados[$nome])) {
                continue;
            }
            $caminho = $prefixo . $nome;
            $valor = $dados[$nome];

            switch ($def['tipo'] ?? '') {
                case 'imagem':
                    if (is_int($valor) && !($this->midiaExiste)($valor)) {
                        $erros[$caminho] = 'Mídia não encontrada na biblioteca.';
                    }
                    break;
                case 'link':
                    if (is_array($valor) && ($valor['tipo'] ?? '') === 'pagina' && is_int($valor['pagina_id'] ?? null)
                        && !($this->paginaExiste)($valor['pagina_id'])) {
                        $erros[$caminho] = 'Página não encontrada.';
                    }
                    break;
                case 'lista':
                    foreach ((array) $valor as $i => $item) {
                        if (is_array($item)) {
                            $erros += $this->erros($def['itens'] ?? [], $item, "{$caminho}.{$i}.");
                        }
                    }
                    break;
            }
        }
        return $erros;
    }
}
