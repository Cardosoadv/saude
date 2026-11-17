<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use Config\AuthGroups;

/**
 * Lista as permissões que um usuário realmente tem, para o front decidir o
 * que exibir sem replicar a lógica do Shield.
 *
 * Segue as mesmas regras de User::can(): permissões diretas do usuário mais
 * as da matriz dos grupos dele, com curingas (`clientes.*`) expandidos para
 * as permissões declaradas daquele escopo.
 */
class PermissoesEfetivasService
{
    public function __construct(private ?AuthGroups $config = null)
    {
        $this->config ??= config(AuthGroups::class);
    }

    /**
     * @return list<string>
     */
    public function paraUsuario(User $user): array
    {
        return self::calcular(
            $user->getGroups(),
            $user->getPermissions(),
            $this->config->matrix,
            array_keys($this->config->permissions),
        );
    }

    /**
     * @param list<string>                $grupos
     * @param list<string>                $diretas     Permissões atribuídas ao usuário.
     * @param array<string, list<string>> $matriz      Grupo => permissões (aceita curingas).
     * @param list<string>                $declaradas  Permissões declaradas no AuthGroups.
     *
     * @return list<string> Ordenadas e sem repetição.
     */
    public static function calcular(array $grupos, array $diretas, array $matriz, array $declaradas): array
    {
        $concedidas = $diretas;
        foreach ($grupos as $grupo) {
            $concedidas = array_merge($concedidas, $matriz[$grupo] ?? []);
        }

        $efetivas = [];
        foreach ($concedidas as $permissao) {
            $permissao = strtolower($permissao);

            if (!str_ends_with($permissao, '.*')) {
                $efetivas[] = $permissao;
                continue;
            }

            $escopo = substr($permissao, 0, -1);
            foreach ($declaradas as $declarada) {
                if (str_starts_with(strtolower($declarada), $escopo)) {
                    $efetivas[] = strtolower($declarada);
                }
            }
        }

        $efetivas = array_values(array_unique($efetivas));
        sort($efetivas);

        return $efetivas;
    }
}
