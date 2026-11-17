<?php

/**
 * URL de um arquivo estático em `public/`, com ?v=<mtime> para invalidar o cache do navegador.
 */
function shared_asset(string $path, bool $withVersion = true): string
{
    $path = ltrim($path, '/');
    $url  = rtrim(base_url(), '/') . '/' . $path;

    if ($withVersion) {
        $arquivo = FCPATH . $path;
        if (is_file($arquivo)) {
            $url .= '?v=' . filemtime($arquivo);
        }
    }

    return $url;
}
