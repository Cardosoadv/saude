<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Controle total do sistema.',
        ],
        'admin' => [
            'title'       => 'Administrador',
            'description' => 'Administração do dia a dia: usuários, cadastros, financeiro e configurações.',
        ],
        'developer' => [
            'title'       => 'Desenvolvedor',
            'description' => 'Acesso técnico: documentação e contabilidade.',
        ],
        'profissional' => [
            'title'       => 'Profissional de saúde',
            'description' => 'Médicos, dentistas, veterinários e demais profissionais: atendimento a clientes, tarefas e consultas.',
        ],
        'recepcao' => [
            'title'       => 'Recepção',
            'description' => 'Atendimento e cadastro de clientes, tarefas e financeiro básico.',
        ],
        'user' => [
            'title'       => 'Usuário',
            'description' => 'Acesso mínimo (apenas documentação).',
        ],
        'cliente' => [
            'title'       => 'Cliente',
            'description' => 'Acesso restrito à Área do Cliente.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.access'                  => 'Can access the sites admin area',
        'admin.settings'                => 'Can access the main site settings',
        'users.manage-admins'           => 'Can manage other admins',
        'users.create'                  => 'Can create new non-admin users',
        'users.edit'                    => 'Can edit existing non-admin users',
        'users.delete'                  => 'Can delete existing non-admin users',
        // Minhas Permissões

        //Site público (conteúdo gerenciável)
        'site.conteudo'                 => 'Pode editar páginas, seções, menus, mídias e notícias do site',
        'site.configuracoes'            => 'Pode editar dados da clínica, SEO, integrações e tema do site',
        'site.publicar'                 => 'Pode publicar e despublicar páginas do site',
        'site.templates'                => 'Pode instalar, ativar, exportar e remover templates do site',

        //Documentação interna (manual do sistema)
        'documentacao.access'           => 'Pode ler a documentação do sistema',
        'documentacao.editar'           => 'Pode criar e editar páginas da documentação',

        
        //Novo clientes
        'clientes.access'                => 'Pode acessar clientes',
        'clientes.create'                => 'Pode criar clientes',
        'clientes.edit'                  => 'Pode editar clientes',
        'clientes.delete'                => 'Pode deletar clientes',

        //Novo Tarefas
        'tarefas.access'                => 'Pode acessar tarefas',
        'tarefas.create'                => 'Pode criar tarefas',
        'tarefas.edit'                  => 'Pode editar tarefas',
        'tarefas.delete'                => 'Pode deletar tarefas',


        //Novo Financeiro
        'financeiro.access'                => 'Pode acessar financeiro',
        'financeiro.create'                => 'Pode criar financeiro',
        'financeiro.edit'                  => 'Pode editar financeiro',
        'financeiro.delete'                => 'Pode deletar financeiro',
        'financeiro.extratos_importar'     => 'Pode importar extratos bancários (OFX)',
        'financeiro.extratos_conciliar'    => 'Pode conciliar, desconciliar e ignorar movimentos de extrato',
        'financeiro.extratos_lancar'       => 'Pode lançar pagamentos a partir do extrato e gerir regras de classificação',

        //Novo Contratos
        'contratos.access'                => 'Pode acessar contratos',
        'contratos.create'                => 'Pode criar contratos',
        'contratos.edit'                  => 'Pode editar contratos',
        'contratos.delete'                => 'Pode deletar contratos',


        //Novo Fornecedores
        'fornecedores.access'             => 'Pode acessar fornecedores',
        'fornecedores.create'             => 'Pode criar fornecedores',
        'fornecedores.edit'               => 'Pode editar fornecedores',
        'fornecedores.delete'             => 'Pode deletar fornecedores',

        //Anotações

        //Etiquetas

        //Mensagens


        //Módulos do sistema
        'module.access'                   => 'Pode ver os módulos do sistema e se estão ativos',
        'module.edit'                     => 'Pode ativar e desativar módulos do sistema',

        //Módulo Extra

        //Conhecimento

        //Business Canvas
        'business_canvas.access'          => 'Pode acessar o Business Model Canvas',
        'business_canvas.create'          => 'Pode criar Business Model Canvas',
        'business_canvas.edit'            => 'Pode editar Business Model Canvas',
        'business_canvas.delete'          => 'Pode deletar Business Model Canvas',
        'business_canvas.exclusive'       => 'Pode gerenciar apenas seus próprios Business Model Canvas',


        //Privacidade granular (clientes e contratos)
        'privacidade.bypass'              => 'Vê e edita todos os clientes e contratos, inclusive os restritos',
        'privacidade.manage'              => 'Pode gerenciar visibilidade e profissionais com acesso de qualquer registro',

        //Contábil
        'contabil.access'                 => 'Pode acessar o módulo contábil',
        'contabil.create'                 => 'Pode criar lançamentos e contas contábeis',
        'contabil.edit'                   => 'Pode editar e estornar lançamentos contábeis',
        'contabil.delete'                 => 'Pode reabrir períodos ou excluir contas contábeis',
        'contabil.relatorios'             => 'Pode visualizar e emitir relatórios e demonstrações contábeis',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'site.*',
            'documentacao.*',
            'contabil.*',
            'privacidade.*',
            'users.*',
            'clientes.*',
            'tarefas.*',
            'financeiro.*',
            'contratos.*',
            'fornecedores.*',
            'module.*',
            'business_canvas.*',
        ],
        'admin' => [
            'users.*',
            'documentacao.*',
            'contabil.*',
            'site.conteudo',
            'site.configuracoes',
            'site.publicar',
            'privacidade.*',
            'clientes.*',
            'tarefas.*',
            'financeiro.*',
            'contratos.*',
            'fornecedores.*',
            'module.*',
            'business_canvas.*',
        ],
        'developer' => [
            'documentacao.*',
            'contabil.*',
        ],
        'profissional' => [
            'documentacao.access',
            'clientes.access',
            'clientes.create',
            'clientes.edit',
            'tarefas.access',
            'tarefas.create',
            'tarefas.edit',
            'contratos.access',
            'contratos.create',
            'contratos.edit',
            'business_canvas.exclusive',
        ],
        'recepcao' => [
            'documentacao.access',
            'clientes.access',
            'clientes.create',
            'clientes.edit',
            'tarefas.access',
            'tarefas.create',
            'tarefas.edit',
            'financeiro.access',
            'financeiro.create',
            'contratos.access',
        ],
        'user' => [
            'documentacao.access',
            'business_canvas.exclusive',
        ],
        'cliente' => [],
    ];
}
