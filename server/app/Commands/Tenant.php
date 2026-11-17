<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Class Tenant
 *
 * Comando CLI para gerenciamento de estrutura Multi-tenancy no CodeIgniter 4.
 * Focado no provisionamento de tenants via subdiretórios com isolamento de ambiente.
 *
 * @package App\Commands
 */
class Tenant extends BaseCommand
{
    /**
     * @var string Grupo do comando
     */
    protected $group       = 'Multi-tenancy';

    /**
     * @var string Nome do comando
     */
    protected $name        = 'tenant:setup';

    /**
     * @var string Descrição do comando
     */
    protected $description = 'Gerencia configurações e provisionamento de novos tenants';

    /**
     * @var string Uso do comando
     */
    protected $usage       = 'tenant:setup [action] [tenant]';

    /**
     * @var array Argumentos do comando
     */
    protected $arguments   = [
        'action' => 'Ação a executar: create, clean-logs, clean-cache, info, update-scripts',
        'tenant' => 'Nome do tenant (obrigatório para create)',
    ];

    /**
     * Executa a lógica principal do comando.
     *
     * @param array $params
     * @return void
     */
    public function run(array $params)
    {
        $action = $params[0] ?? null;
        $tenant = $params[1] ?? null;

        if (!$action) {
            $this->showHelp();
            return;
        }

        try {
            switch ($action) {
                case 'create':
                    $this->createTenant($tenant);
                    break;
                case 'clean-logs':
                    $this->cleanLogs($tenant);
                    break;
                case 'clean-cache':
                    $this->cleanCache($tenant);
                    break;
                case 'update-scripts':
                    $this->updateScripts($tenant);
                    break;
                case 'info':
                    $this->showInfo($tenant);
                    break;
                default:
                    CLI::error("Ação desconhecida: {$action}");
                    $this->showHelp();
            }
        } catch (Throwable $e) {
            CLI::error("Erro na execução: " . $e->getMessage());
        }
    }

    /**
     * Cria a estrutura completa de um novo tenant.
     *
     * @param string|null $tenant Slug do tenant
     * @return void
     */
    protected function createTenant(?string $tenant): void
    {
        if (!$tenant) {
            CLI::error('Você precisa especificar o nome do tenant!');
            CLI::write('Exemplo: php spark tenant:setup create crbio04');
            return;
        }

        CLI::write("Iniciando provisionamento do tenant: {$tenant}", 'yellow');

        $baseDir = ROOTPATH;
        $publicDir = $baseDir . $tenant;

        // 1. Criação de Pastas
        $this->ensureDirectory($publicDir);

        $writableDir = $publicDir . '/writable';
        $dirs = ['cache', 'debugbar', 'logs', 'jsons', 'session', 'uploads'];
        foreach ($dirs as $dir) {
            $this->ensureDirectory($writableDir . '/' . $dir);
        }
        CLI::write("✓ Estrutura 'writable' preparada.", 'green');

        $assetsDir = $publicDir . '/assets';
        $assetDirs = ['logos', 'uploads/documentos', 'uploads/avatars', 'custom'];
        foreach ($assetDirs as $dir) {
            $this->ensureDirectory($assetsDir . '/' . $dir);
        }
        CLI::write("✓ Estrutura de 'assets' preparada.", 'green');

        // 2. Configuração do .env
        $envFile = $publicDir . '/.env';
        if (!file_exists($envFile)) {
            $this->createEnvFile($envFile, $tenant);
            CLI::write("✓ Arquivo .env configurado.", 'green');
        }

        // 3. Front Controller (index.php) customizado
        $indexFile = $publicDir . '/index.php';
        $this->createIndexFile($indexFile);
        CLI::write("✓ Arquivo index.php de boot criado.", 'green');

        // 4. Segurança via .htaccess
        $htaccessFile = $publicDir . '/.htaccess';
        $this->createHtaccessFile($htaccessFile);
        CLI::write("✓ Arquivo .htaccess de segurança criado.", 'green');

        // 5. Spark Script
        $sparkFile = $publicDir . '/spark';
        $this->createSparkFile($sparkFile);
        CLI::write("✓ Arquivo spark criado.", 'green');

        CLI::newLine();
        CLI::write("Tenant '{$tenant}' provisionado com sucesso!", 'black');
        CLI::write("Acesse: https://ordojuris.com.br/{$tenant}/", 'cyan');
    }

    /**
     * Cria o Front Controller index.php configurado para carregar o .env local.
     *
     * @param string $path
     * @return void
     */
    protected function createIndexFile(string $path): void
    {
        $content = <<<'PXHP'
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */
$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;
    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 */

// LOAD OUR PATHS CONFIG FILE
require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

// --------------------------------------------------------------------
// CARREGAMENTO DO .ENV PERSONALIZADO
// --------------------------------------------------------------------
// Forçamos o DotEnv a ler o arquivo no diretório atual (tenant)
// antes de iniciar o boot do WebApp.
// --------------------------------------------------------------------

require_once $paths->systemDirectory . '/Config/DotEnv.php';

$dotenv = new \CodeIgniter\Config\DotEnv(FCPATH);
$dotenv->load();

exit(Boot::bootWeb($paths));
PXHP;

        if (!file_exists($path)) {
            file_put_contents($path, $content);
        }
    }

    /**
     * Cria o arquivo .htaccess para proteger o ambiente.
     *
     * @param string $path
     * @return void
     */
    protected function createHtaccessFile(string $path): void
    {
        $content = <<<EOF
# Impede a listagem de diretórios
Options -Indexes

# Protege arquivos sensíveis
<FilesMatch "^\.env">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

# Bloqueia acesso a arquivos de sistema e logs
<FilesMatch "\.(log|json|txt|cache)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>

# Reescrita para o CodeIgniter (se necessário no subdiretório)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L]
</IfModule>
EOF;

        if (!file_exists($path)) {
            file_put_contents($path, $content);
        }
    }

    /**
     * Garante existência de diretório.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new \RuntimeException("Falha ao criar diretório: {$path}");
            }
        }
    }

    /**
     * Gera .env inicial.
     */
    protected function createEnvFile(string $path, string $tenant): void
    {
        $content = <<<EOF
# Configurações do Tenant: {$tenant}
CI_ENVIRONMENT = production

Tenant = {$tenant}
TENANT_NAME = "Empresa {$tenant}"

app.baseURL = 'https://ordojuris.com.br/{$tenant}/'

database.default.hostname = localhost
database.default.database = db_{$tenant}
database.default.username = user_{$tenant}
database.default.password = "SENHA"
database.default.DBDriver = MySQLi

session.cookieName = ci_session_{$tenant}
EOF;
        file_put_contents($path, $content);
    }

    /**
     * Limpeza de arquivos e logs.
     */
    protected function processCleanup(string $tenant, string $type, int $time, string $unit): void
    {
        $path = ROOTPATH . "{$tenant}/writable/{$type}/";
        if (!is_dir($path)) return;

        $seconds = ($unit === 'days') ? $time * 86400 : $time * 3600;
        $cutoff = time() - $seconds;
        $count = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff) {
                if (@unlink($file->getPathname())) $count++;
            }
        }

        CLI::write("✓ [{$tenant}] {$count} arquivos de {$type} removidos.", 'green');
    }

    protected function cleanLogs(?string $tenant): void
    {
        $days = CLI::prompt('Remover logs com mais de quantos dias?', 30);
        $tenants = $tenant ? [$tenant] : $this->getValidTenants();
        foreach ($tenants as $t) $this->processCleanup($t, 'logs', (int)$days, 'days');
    }

    protected function cleanCache(?string $tenant): void
    {
        $hours = CLI::prompt('Remover cache com mais de quantas horas?', 24);
        $tenants = $tenant ? [$tenant] : $this->getValidTenants();
        foreach ($tenants as $t) $this->processCleanup($t, 'cache', (int)$hours, 'hours');
    }

    protected function showInfo(?string $tenant): void
    {
        $tenants = $tenant ? [$tenant] : $this->getValidTenants();
        foreach ($tenants as $t) {
            $writablePath = ROOTPATH . "{$t}/writable";
            if (!is_dir($writablePath)) continue;
            CLI::write("Tenant: " . strtoupper($t) . " | Tamanho: " . $this->getDirectorySize($writablePath), 'yellow');
        }
    }

    protected function getDirectorySize(string $path): string
    {
        if (!is_dir($path)) return '0 B';
        $bytes = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) $bytes += $file->getSize();
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Cria o arquivo spark para o tenant.
     *
     * @param string $path
     * @return void
     */
    protected function createSparkFile(string $path): void
    {
        $content = <<<'PXHP'
#!/usr/bin/env php
<?php

/*
 * --------------------------------------------------------------------
 * CODEIGNITER COMMAND-LINE TOOLS FOR TENANTS
 * --------------------------------------------------------------------
 */

use CodeIgniter\Boot;
use Config\Paths;
use CodeIgniter\Config\DotEnv;

// Refuse to run when called from php-cgi
if (str_starts_with(PHP_SAPI, 'cgi')) {
    exit("The cli tool is not supported when running php-cgi. It needs php-cli to function!\n\n");
}

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */
$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    exit($message);
}

// We want errors to be shown when using it from the CLI.
error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 */

// LOAD OUR PATHS CONFIG FILE
// Tenant paths are one level deeper than root, so we go back one level to find app
require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

// LOAD TENANT ENV
require_once $paths->systemDirectory . '/Config/DotEnv.php';
$dotenv = new DotEnv(FCPATH);
$dotenv->load();



// BOOT SPARK
// We execute Boot::bootSpark but we need to ensure it uses our paths and env
exit(Boot::bootSpark($paths));
PXHP;

        if (!file_exists($path)) {
            file_put_contents($path, $content);
        }
    }

    /**
     * Atualiza os scripts (index.php, soap.php, spark) em todos os tenants.
     *
     * @param string|null $tenant
     * @return void
     */
    protected function updateScripts(?string $tenant): void
    {
        $tenants = $tenant ? [$tenant] : $this->getValidTenants();

        foreach ($tenants as $t) {
            $dir = ROOTPATH . $t;
            if (!is_dir($dir)) continue;

            CLI::write("Atualizando scripts para: {$t}...", 'yellow');

            // Atualiza index.php se necessário (opcional, aqui focado no spark)
            // $this->createIndexFile($dir . '/index.php'); // Descomentar se quiser forçar atualização do index

            // Cria/Atualiza spark
            $sparkFile = $dir . '/spark';
            if (file_exists($sparkFile)) {
                unlink($sparkFile); // Remove para recriar
            }
            $this->createSparkFile($sparkFile);

            CLI::write("✓ Scripts atualizados.", 'green');
        }
    }

    protected function getValidTenants(): array
    {
        return array_map('trim', explode(',', env('VALID_TENANTS', 'crbio04,crbio05')));
    }

    public function showHelp(): void
    {
        CLI::write('Uso:', 'yellow');
        CLI::write('  php spark tenant:setup create <tenant>   - Provisiona estrutura');
        CLI::write('  php spark tenant:setup update-scripts    - Atualiza scripts (spark) nos tenants');
        CLI::write('  php spark tenant:setup clean-logs        - Limpa logs');
        CLI::write('  php spark tenant:setup info              - Status');
    }
}
