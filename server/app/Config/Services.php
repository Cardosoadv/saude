<?php

namespace Config;

use App\Services\ModulosService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
    public static function modulos($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('modulos');
        }
        
        return new ModulosService();
    }

    /**
     * Retorna o serviço de Inteligência Artificial.
     *
     * @param bool $getShared
     * @return \App\Interfaces\AIServiceInterface
     */
    /**
     * Leitura da documentação interna (épico #699). Registrado como serviço para
     * os testes trocarem as dependências (módulos, permissões do usuário).
     */
    public static function documentacao($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('documentacao');
        }

        return new \App\Services\Documentacao\DocumentacaoService();
    }

    public static function AIService($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('AIService');
        }

        return new \App\Services\AIManagerService();
    }

    public static function contabilAuthorizer(bool $getShared = true): \CognitioTechny\Contabil\Application\Ports\AuthorizerInterface
    {
        if ($getShared) {
            return static::getSharedInstance('contabilAuthorizer');
        }

        return new \App\Libraries\Contabil\OrdoJurisContabilAuthorizer();
    }

    public static function contabilCurrentUser(bool $getShared = true): \CognitioTechny\Contabil\Application\Ports\CurrentUserInterface
    {
        if ($getShared) {
            return static::getSharedInstance('contabilCurrentUser');
        }

        return new \App\Libraries\Contabil\OrdoJurisContabilCurrentUser();
    }

    public static function contabilCurrentEntity(bool $getShared = true): \CognitioTechny\Contabil\Application\Ports\CurrentEntityInterface
    {
        if ($getShared) {
            return static::getSharedInstance('contabilCurrentEntity');
        }

        return new \App\Libraries\Contabil\OrdoJurisContabilCurrentEntity();
    }

    public static function contabilPdfRenderer(bool $getShared = true): \CognitioTechny\Contabil\Application\Ports\PdfRendererInterface
    {
        if ($getShared) {
            return static::getSharedInstance('contabilPdfRenderer');
        }

        return new \App\Libraries\Contabil\OrdoJurisContabilPdfRenderer();
    }
}

