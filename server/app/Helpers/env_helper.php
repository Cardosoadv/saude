<?php

if (!function_exists('updateEnvVariable')) {
    function updateEnvVariable($key, $value)
    {
        $path = ROOTPATH . '.env';
        if (!file_exists($path)) {
            // If .env doesn't exist, try to copy from .env.example or create new
            if (file_exists(ROOTPATH . 'env')) {
                copy(ROOTPATH . 'env', $path);
            } else {
                return false;
            }
        }

        $oldContent = file_get_contents($path);

        // Escape special characters in value if necessary
        // For simplicity, we assume simple string values for tokens

        // Check if key exists
        if (preg_match("/^{$key}=/m", $oldContent)) {
            // Replace existing identifier
            $newContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $oldContent
            );
        } else {
            // Append new identifier
            $newContent = $oldContent . PHP_EOL . "{$key}={$value}" . PHP_EOL;
        }

        if (file_put_contents($path, $newContent) !== false) {
            return true;
        }

        return false;
    }
}
