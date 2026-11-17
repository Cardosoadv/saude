<?php

declare(strict_types=1);

namespace App\Libraries\Contabil;

use CognitioTechny\Contabil\Application\Ports\AuthorizerInterface;

/**
 * Adaptador de autorização do módulo Contábil integrado ao Shield do OrdoJuris.
 *
 * @version 1.0.0
 */
class OrdoJurisContabilAuthorizer implements AuthorizerInterface
{
    public function can(string $permission, int|string|null $entityId = null): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return is_cli();
        }

        if ($user->inGroup('superadmin', 'admin', 'developer')) {
            return true;
        }

        if ($user->can('contabil.access')) {
            if ($permission === 'contabil.access') {
                return true;
            }

            if (str_starts_with($permission, 'contabil.reports.') && $user->can('contabil.relatorios')) {
                return true;
            }

            if ((str_starts_with($permission, 'contabil.entry.') || str_starts_with($permission, 'contabil.chart.')) && ($user->can('contabil.edit') || $user->can('contabil.create'))) {
                return true;
            }

            return true;
        }

        return false;
    }
}
