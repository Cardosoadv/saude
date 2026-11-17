<?php

if (!function_exists('modulo_ativo')) {
    /**
     * Verifica se um módulo está ativo
     *
     * @param string $slug Slug do módulo (ex.: 'contratos')
     * @return bool
     */
    function modulo_ativo(string $slug): bool
    {
        $service = service('modulos');
        return $service->isActive($slug);
    }
}

if (!function_exists('get_modulos_ativos')) {
    /**
     * Retorna os slugs dos módulos ativos
     *
     * @return array
     */
    function get_modulos_ativos()
    {
        $service = service('modulos');
        return $service->getActiveModules();
    }
}