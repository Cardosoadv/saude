<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Catálogo de módulos do sistema.
 *
 * Idempotente:
 * 1. Registros existentes sem slug (instalações antigas) recebem o slug a
 *    partir do nome; `ativo` e `nome` são mantidos.
 * 2. Módulos do catálogo cujo slug ainda não existe (inclusive entre os
 *    excluídos) são inseridos ativos, para não mudar o comportamento de
 *    nenhuma instalação, exceto os de `DESATIVADOS_POR_PADRAO`.
 */
class ModulosSeeder extends Seeder
{
    /**
     * slug => nome exibido.
     */
    public const CATALOGO = [
        'administrativo'  => 'Administrativo',
        'usuarios'        => 'Usuários',
        'clientes'        => 'Clientes',
        'processos'       => 'Processos',
        'objetos'         => 'Objetos',
        'intimacoes'      => 'Intimações',
        'casos'           => 'Casos',
        'documentos'      => 'Documentos',
        'contratos'       => 'Contratos',
        'tarefas'         => 'Tarefas',
        'financeiro'      => 'Financeiro',
        'fornecedores'    => 'Fornecedores',
        'google'          => 'Google',
        'exportacao'      => 'Exportação',
        'gestao'          => 'Gestão',
        'business-canvas' => 'Business Canvas',
        'crm'             => 'CRM',
        'checklist'       => 'Checklist',
        'conhecimento'    => 'Conhecimento',
        'site'            => 'Site',
        'documentacao'    => 'Documentação',
        'contabil'        => 'Contábil',
    ];

    /**
     * Nomes antigos (normalizados) que não viram o slug por normalização direta.
     */
    /**
     * Módulos em descontinuação: inseridos com `ativo = 0` em instalações novas.
     * Registros já existentes não são alterados.
     */
    public const DESATIVADOS_POR_PADRAO = [];

    private const LEGADOS = [
        'processos-objetos-imoveis' => 'objetos',
        'google-service'            => 'google',
    ];

    public function run()
    {
        $this->preencherSlugs();
        $this->inserirFaltantes();
    }

    private function preencherSlugs(): void
    {
        $usados = array_column(
            $this->db->table('modulos')->select('slug')->where('slug IS NOT NULL')->get()->getResultArray(),
            'slug',
        );

        $semSlug = $this->db->table('modulos')->select('id, nome')->where('slug', null)
            ->orderBy('id')->get()->getResultArray();

        foreach ($semSlug as $modulo) {
            $slug = self::slugDoNome((string) $modulo['nome']);
            if ($slug === '' || in_array($slug, $usados, true)) {
                $slug = trim($slug . '-' . $modulo['id'], '-');
            }

            $this->db->table('modulos')->where('id', $modulo['id'])->update(['slug' => $slug]);
            $usados[] = $slug;
        }
    }

    public static function slugDoNome(string $nome): string
    {
        helper(['text', 'url']);

        $normalizado = url_title(convert_accented_characters($nome), '-', true);

        return self::LEGADOS[$normalizado] ?? $normalizado;
    }

    private function inserirFaltantes(): void
    {
        $existentes = array_column(
            $this->db->table('modulos')->select('slug')->where('slug IS NOT NULL')->get()->getResultArray(),
            'slug',
        );

        $agora = date('Y-m-d H:i:s');
        foreach (self::CATALOGO as $slug => $nome) {
            if (in_array($slug, $existentes, true)) {
                continue;
            }

            $this->db->table('modulos')->insert([
                'nome'       => $nome,
                'slug'       => $slug,
                'ativo'      => in_array($slug, self::DESATIVADOS_POR_PADRAO, true) ? 0 : 1,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }
}
