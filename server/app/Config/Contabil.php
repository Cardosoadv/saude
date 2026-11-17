<?php

declare(strict_types=1);

namespace Config;

use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Config\Contabil as BaseContabil;

/**
 * Host configuration for the Contabil accounting package in OrdoJuris.
 *
 * @version 1.0.0
 */
class Contabil extends BaseContabil
{
    public string $tablePrefix = 'acc_';
    public string $currency = 'BRL';
    public int|string|null $defaultEntityId = 1;
    public string $apiRoute = 'api/v2/contabil';
    public bool $routesEnabled = true;
    public bool $commandsEnabled = true;
    public string $defaultBasis = 'ACCRUAL';
    public ?string $dbGroup = null;
    public ?string $accountCodeMask = null;
    public ?string $resultAccountCode = null;
}
