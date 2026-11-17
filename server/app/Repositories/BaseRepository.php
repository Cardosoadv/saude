<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Model;
use CodeIgniter\Pager\Pager;

/**
 * Classe base para repositórios com sistema de cache versionado.
 *
 * @property Model $model
 */
class BaseRepository
{
    /**
     * Chave usada para marcar um Pager reduzido a dados escalares dentro do cache.
     */
    private const PAGER_MARKER = '__pager';

    /**
     * @var Model A instância do model principal.
     */
    protected Model $model;

    /**
     * @var string|null Chave base para o cache. Se nulo, o cache é desativado.
     */
    protected $cacheKey = null;

    /**
     * @var int Tempo de vida do cache em segundos.
     */
    protected int $cacheTTL = 3600;

    /**
     * @var \CodeIgniter\Cache\CacheInterface
     */
    protected $cache;

    /**
     * @var \CodeIgniter\Database\BaseConnection
     */
    protected $db;

    /**
     * @var array Cache de versões para evitar múltiplas chamadas ao cache no mesmo request.
     */
    protected static array $versions = [];

    public function __construct()
    {
        $this->cache = service('cache');
        $this->db    = \Config\Database::connect();
    }

    /**
     * Gera uma chave de cache versionada.
     *
     * @param string $suffix Sufixo para identificar o tipo de dado (ex: 'all', '1', 'page_1')
     * @return string
     */
    protected function getCacheKey(string $suffix): string
    {
        $version = $this->getCacheVersion();
        $class = str_replace(['App\\Repositories\\', '\\'], ['', '_'], get_class($this));
        return "{$class}_{$this->cacheKey}_v{$version}_{$suffix}";
    }

    /**
     * Retorna os fragmentos SQL para formatação de mês baseado no driver do banco.
     * Centraliza a lógica para MySQL e SQLite (testes).
     *
     * @param string $column Nome da coluna de data.
     * @return array [field, label]
     */
    protected function getMonthFormat(string $column): array
    {
        $driver = $this->db->getPlatform();

        if ($driver === 'MySQLi') {
            return [
                'field' => "DATE_FORMAT($column, '%Y-%m')",
                'label' => "DATE_FORMAT($column, '%m/%Y')",
            ];
        }

        // Fallback para SQLite usado em testes
        return [
            'field' => "strftime('%Y-%m', $column)",
            'label' => "strftime('%m/%Y', $column)",
        ];
    }

    protected function getCacheVersion(): int
    {
        $versionKey = "{$this->cacheKey}_version";

        if (isset(self::$versions[$versionKey])) {
            return self::$versions[$versionKey];
        }

        $version = $this->cache->get($versionKey);

        if ($version === null) {
            $version = time();
            $this->cache->save($versionKey, $version, 86400 * 30); // 30 dias
        }

        self::$versions[$versionKey] = (int) $version;
        return self::$versions[$versionKey];
    }

    /**
     * Helper para buscar do cache ou executar um callback e salvar.
     *
     * @param string $suffix
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function remember(string $suffix, callable $callback, ?int $ttl = null)
    {
        if (!$this->cacheKey) {
            return $callback();
        }

        $key = $this->getCacheKey($suffix);
        $data = $this->cache->get($key);

        if ($data !== null) {
            return $this->thawPager($data);
        }

        $data = $callback();

        if ($data !== null) {
            $frozen = $this->freezePager($data);

            if ($frozen !== null) {
                $this->cache->save($key, $frozen, $ttl ?? $this->cacheTTL);
            }
        }

        return $data;
    }

    /**
     * Substitui o objeto Pager (que carrega o View renderer com Closures e por isso
     * não é serializável pelo cache) por seus dados escalares de paginação.
     *
     * @param mixed $data Resultado do callback.
     * @return mixed|null Dados seguros para serializar, ou null se o pager não puder ser resumido.
     */
    protected function freezePager(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        try {
            return $this->freezePagerRecursive($data);
        } catch (\Throwable $e) {
            // Grupo de paginação não populado: melhor não cachear do que gravar dado inválido.
            return null;
        }
    }

    /**
     * Percorre o array (em qualquer profundidade) substituindo objetos Pager por seus
     * dados escalares. Necessário porque um Pager pode aparecer aninhado (ex.: dentro de
     * um sub-array retornado por um Model, como em `['objetos' => [..., 'pager' => Pager]]`),
     * não apenas na chave raiz `pager`.
     *
     * @throws \Throwable Se algum Pager encontrado não estiver com o grupo de paginação populado.
     */
    private function freezePagerRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Pager) {
                $data[$key] = [
                    self::PAGER_MARKER => [
                        'currentPage' => $value->getCurrentPage(),
                        'perPage'     => $value->getPerPage(),
                        'total'       => $value->getTotal(),
                    ],
                ];
            } elseif (is_array($value)) {
                $data[$key] = $this->freezePagerRecursive($value);
            }
        }

        return $data;
    }

    /**
     * Reconstrói os Pagers a partir dos dados escalares gravados por freezePager(),
     * em qualquer profundidade do array.
     *
     * @param mixed $data Dados lidos do cache.
     */
    protected function thawPager(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        return $this->thawPagerRecursive($data);
    }

    private function thawPagerRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $frozen = $value[self::PAGER_MARKER] ?? null;

            if (is_array($frozen)) {
                // O Pager do serviço é a mesma instância compartilhada com $this->model->pager.
                $data[$key] = service('pager')->store(
                    'default',
                    (int) $frozen['currentPage'],
                    (int) $frozen['perPage'],
                    (int) $frozen['total'],
                );
            } else {
                $data[$key] = $this->thawPagerRecursive($value);
            }
        }

        return $data;
    }

    /**
     * Incrementa a versão do cache, invalidando efetivamente todos os itens antigos.
     */
    public function clearCache(): void
    {
        if (!$this->cacheKey) {
            return;
        }

        $versionKey = "{$this->cacheKey}_version";
        $currentVersion = $this->cache->get($versionKey);
        $newVersion = $currentVersion ? (int)$currentVersion + 1 : time();
        $this->cache->save($versionKey, $newVersion, 86400 * 30);

        // Limpa o cache estático de versões para evitar leitura de dados obsoletos no mesmo request
        unset(self::$versions[$versionKey]);

        // Invalida cache global do dashboard quando qualquer repositório é alterado
        // Usamos um sistema de versão para invalidar todos os caches (global e clientes) atomitamente
        $dashboardVersion = $this->cache->get('dashboard_version') ?? 1;
        $this->cache->save('dashboard_version', (int)$dashboardVersion + 1, 86400 * 30);
    }

    /**
     * Busca todos os registros com cache.
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        if ($this->cacheKey) {
            $key = $this->getCacheKey("all_{$limit}_{$offset}");
            $data = $this->cache->get($key);
            if ($data !== null) {
                return $data;
            }
        }

        $data = $this->model->findAll($limit, $offset);

        if ($this->cacheKey && is_array($data)) {
            $this->cache->save($key, $data, $this->cacheTTL);
        }

        return $data;
    }

    /**
     * Busca um registro por ID com cache.
     */
    public function findById(int $id): ?array
    {
        if ($this->cacheKey) {
            $key = $this->getCacheKey((string) $id);
            $data = $this->cache->get($key);
            if ($data !== null) {
                return $data;
            }
        }

        $data = $this->model->find($id);

        if ($this->cacheKey && $data) {
            $this->cache->save($key, $data, $this->cacheTTL);
        }

        return $data;
    }

    /**
     * Busca múltiplos registros por ID com cache.
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) return [];

        $filtrosKey = md5(implode(',', $ids));

        return $this->remember("ids_{$filtrosKey}", function () use ($ids) {
            return $this->model->whereIn($this->model->primaryKey, $ids)->findAll();
        });
    }

    /**
     * Pagina os resultados com cache.
     * Importante: Para o CI4, o Pager deve ser restaurado se viermos do cache.
     *
     * $group isola apenas o cache interno desta paginação (ex.: por termo de busca);
     * o Pager do CodeIgniter em si é sempre populado sob o group 'default', pois os
     * controllers leem getTotal()/getPageCount()/links() sem informar um group
     * customizado. Se $group fosse repassado ao Pager, o group 'default' nunca seria
     * populado e essas chamadas lançariam TypeError (getTotal(): null returned).
     */
    public function paginate(int $perPage = 20, string $group = 'default', ?int $page = null): array
    {
        $pageForCache = $page ?? (int)(service('request')->getGet('page') ?? 1);
        $cacheKey = "page_{$pageForCache}_{$perPage}_{$group}";

        // Se houver cache, tentamos restaurar o resultado e o pager
        if ($this->cacheKey) {
            $key = $this->getCacheKey($cacheKey);
            $cached = $this->cache->get($key);

            if ($cached !== null && isset($cached['data'], $cached['pager'])) {
                // Restaura o pager no modelo a partir dos dados escalares cacheados
                $cached = $this->thawPager($cached);
                $this->model->pager = $cached['pager'];
                return $cached['data'];
            }
        }

        $data = $this->model->paginate($perPage, 'default', $page);

        if ($this->cacheKey && is_array($data)) {
            $frozen = $this->freezePager([
                'data'  => $data,
                'pager' => $this->model->pager,
            ]);

            if ($frozen !== null) {
                $this->cache->save($this->getCacheKey($cacheKey), $frozen, $this->cacheTTL);
            }
        }

        return $data;
    }

    /**
     * Cria um registro e limpa o cache.
     */
    public function create(array $data): int
    {
        $id = (int) $this->model->insert($data);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    /**
     * Atualiza um registro e limpa o cache.
     */
    public function update(int $id, array $data): bool
    {
        $result = $this->model->update($id, $data);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Deleta um registro e limpa o cache.
     */
    public function delete(int $id): bool
    {
        $result = $this->model->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Deleta registros com base em uma condição e limpa o cache.
     */
    public function deleteWhere(array $where): bool
    {
        $result = $this->model->where($where)->delete();
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Insere múltiplos registros e limpa o cache.
     */
    public function insertBatch(array $set, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        $result = $this->model->insertBatch($set, $escape, $batchSize, $testing);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Atualiza múltiplos registros e limpa o cache.
     */
    public function updateBatch(array $set, string $index, ?int $batchSize = 100, bool $testing = false)
    {
        $result = $this->model->updateBatch($set, $index, $batchSize, $testing);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Retorna o pager.
     */
    public function getPager()
    {
        return $this->model->pager;
    }

    /**
     * Retorna os erros do modelo.
     */
    public function getErrors(): array
    {
        return $this->model->errors() ?: [];
    }

    /**
     * Retorna a instância do modelo.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Proxy para o Query Builder do modelo.
     *
     * @param mixed $field
     * @param mixed $value
     * @return mixed
     */
    public function where($field, $value = null)
    {
        return $this->model->where($field, $value);
    }

    /**
     * Limpa todas as versões estáticas do cache (útil para testes unitários).
     */
    public static function clearVersions(): void
    {
        self::$versions = [];
    }
}
