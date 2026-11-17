<?php

namespace App\Services;

use App\Database\Seeds\ModulosSeeder;
use App\Repositories\ModulosRepository;

class ModulosService extends BaseService
{
    /**
     * Cache em nível de requisição para os módulos ativos.
     * @var array|null
     */
    private static ?array $activeModules = null;

    /**
     * Cache em nível de requisição: há módulos cadastrados?
     */
    private static ?bool $configurado = null;

    /**
     * Módulos que não podem ser desativados: sem eles ninguém reativa os demais.
     */
    public const ESSENCIAIS = ['administrativo'];

    public function __construct(?ModulosRepository $repository = null)
    {
        $this->repository = $repository ?? new ModulosRepository();
    }

    /**
     * Verifica se um módulo está ativo.
     * Otimizado: Realiza o carregamento de todos os módulos ativos uma única vez por requisição.
     *
     * @param string $slug Slug do módulo (ex.: 'objetos').
     * @return bool
     */
    public function isActive(string $slug): bool
    {
        // Tabela vazia (catálogo nunca semeado) não pode bloquear o sistema inteiro.
        if (!$this->configurado()) {
            return true;
        }

        if (self::$activeModules === null) {
            $this->getActiveModules();
        }

        return in_array(strtolower($slug), self::$activeModules, true);
    }

    /**
     * Há módulos cadastrados? Sem nenhum, a ativação de módulos não está em uso
     * e tudo fica liberado (isActive() sempre verdadeiro).
     */
    public function configurado(): bool
    {
        if (self::$configurado === null) {
            self::$configurado = $this->repository->contarModulos() > 0;
        }

        return self::$configurado;
    }

    /**
     * Retorna os slugs dos módulos ativos, utilizando o cache de requisição.
     * Slugs são retornados em minúsculo para permitir comparação case-insensitive.
     *
     * Linha sem slug (migration CorrigeTabelaModulos ainda não rodou, ou cache
     * gravado antes dela) usa o slug derivado do nome, como faz o seeder.
     *
     * @return list<string>
     */
    public function getActiveModules(): array
    {
        if (self::$activeModules === null) {
            $slugs = [];
            foreach ($this->repository->getActiveModules() as $modulo) {
                $slug = (string) ($modulo['slug'] ?? '');
                if ($slug === '') {
                    $slug = ModulosSeeder::slugDoNome((string) ($modulo['nome'] ?? ''));
                }
                if ($slug !== '') {
                    $slugs[] = strtolower($slug);
                }
            }
            self::$activeModules = array_values(array_unique($slugs));
        }

        return self::$activeModules;
    }

    /**
     * Lista os módulos para a API, ordenados pelo nome.
     *
     * @return list<array{id: int, slug: string|null, nome: string, ativo: bool}>
     */
    public function listar(): array
    {
        return array_map([self::class, 'formatar'], $this->repository->listarOrdenados());
    }

    /**
     * @return array{id: int, slug: string|null, nome: string, ativo: bool}
     */
    public static function formatar(array $modulo): array
    {
        return [
            'id'    => (int) $modulo['id'],
            'slug'  => $modulo['slug'] ?? null,
            'nome'  => (string) $modulo['nome'],
            'ativo' => (bool) $modulo['ativo'],
        ];
    }

    /**
     * Ativa ou desativa um módulo pelo slug.
     *
     * @return array{id: int, slug: string|null, nome: string, ativo: bool}|null Null se o slug não existir.
     * @throws \DomainException Ao tentar desativar um módulo essencial.
     * @throws \RuntimeException Se a gravação falhar.
     */
    public function alterarStatus(string $slug, bool $ativo): ?array
    {
        $modulo = $this->repository->getBySlug($slug);
        if ($modulo === null) {
            return null;
        }

        if (!$ativo && in_array($slug, self::ESSENCIAIS, true)) {
            throw new \DomainException("O módulo {$modulo['nome']} não pode ser desativado.");
        }

        if ((bool) $modulo['ativo'] !== $ativo && !$this->update((int) $modulo['id'], ['ativo' => $ativo ? 1 : 0])) {
            throw new \RuntimeException('Não foi possível alterar o status do módulo.');
        }

        $modulo['ativo'] = $ativo;

        return self::formatar($modulo);
    }

    /**
     * Salva (cria ou atualiza) um módulo.
     *
     * @param array $data
     * @return array
     */
    public function save(array $data): array
    {
        $id = $data['id'] ?? null;

        if (!empty($id)) {
            $updated = $this->update((int) $id, $data);
            return [
                'success' => $updated,
                'message' => $updated ? 'Módulo atualizado com sucesso.' : 'Erro ao atualizar módulo.',
            ];
        }

        $newId = $this->create($data);
        return [
            'success' => $newId > 0,
            'message' => $newId > 0 ? 'Módulo criado com sucesso.' : 'Erro ao criar módulo.',
        ];
    }

    /**
     * Sobrescreve o create para limpar o cache de requisição.
     */
    public function create(array $data): int
    {
        $id = parent::create($data);
        if ($id > 0) {
            self::$activeModules = null;
            self::$configurado = null;
        }
        return $id;
    }

    /**
     * Sobrescreve o update para limpar o cache de requisição.
     */
    public function update(int $id, array $data): bool
    {
        $updated = parent::update($id, $data);
        if ($updated) {
            self::$activeModules = null;
            self::$configurado = null;
        }
        return $updated;
    }

    /**
     * Sobrescreve o delete para limpar o cache de requisição.
     */
    public function delete(int $id): bool
    {
        $deleted = parent::delete($id);
        if ($deleted) {
            self::$activeModules = null;
            self::$configurado = null;
        }
        return $deleted;
    }
}
