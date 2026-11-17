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
            'description' => 'Complete control of the site.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Day to day administrators of the site.',
        ],
        'developer' => [
            'title'       => 'Developer',
            'description' => 'Site programmers.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
        'beta' => [
            'title'       => 'Beta User',
            'description' => 'Has access to beta-level features.',
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
        'beta.access'                   => 'Can access beta-level features',
        // Minhas Permissões

        //Site público (conteúdo gerenciável)
        'site.conteudo'                 => 'Pode editar páginas, seções, menus, mídias e notícias do site',
        'site.configuracoes'            => 'Pode editar dados do escritório, SEO, integrações e tema do site',
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
        'anotacoes.access'                => 'Pode acessar anotações',
        'anotacoes.create'                => 'Pode criar anotações',
        'anotacoes.edit'                  => 'Pode editar anotações',
        'anotacoes.delete'                => 'Pode deletar anotações',

        //Etiquetas
        'etiquetas.access'                => 'Pode acessar etiquetas',
        'etiquetas.create'                => 'Pode criar etiquetas',
        'etiquetas.edit'                  => 'Pode editar etiquetas',
        'etiquetas.delete'                => 'Pode deletar etiquetas',

        //Mensagens
        'mensagens.access'                => 'Pode acessar mensagens',
        'mensagens.create'                => 'Pode criar mensagens',
        'mensagens.edit'                  => 'Pode editar mensagens',
        'mensagens.delete'                => 'Pode deletar mensagens',

        'monitorar.delete'               => 'Pode deletar monitorar',
        'monitorar.exclusive'            => 'Pode acessar monitorar exclusivo',

        //Módulos do sistema
        'module.access'                   => 'Pode ver os módulos do sistema e se estão ativos',
        'module.edit'                     => 'Pode ativar e desativar módulos do sistema',

        //Módulo Extra
        'module.processos'                => 'Pode acessar o módulo de Estratégia Legal (Legal Ops BI) e relatórios gerenciais',

        //Conhecimento
        'conhecimento.access'             => 'Pode acessar a Base de Conhecimento',
        'conhecimento.create'             => 'Pode criar itens na Base de Conhecimento',
        'conhecimento.edit'               => 'Pode editar itens na Base de Conhecimento',
        'conhecimento.delete'             => 'Pode deletar itens na Base de Conhecimento',
        'conhecimento.modelos.gerar'      => 'Pode gerar documentos a partir de modelos',

        //Business Canvas
        'business_canvas.access'          => 'Pode acessar o Business Model Canvas',
        'business_canvas.create'          => 'Pode criar Business Model Canvas',
        'business_canvas.edit'            => 'Pode editar Business Model Canvas',
        'business_canvas.delete'          => 'Pode deletar Business Model Canvas',
        'business_canvas.exclusive'       => 'Pode gerenciar apenas seus próprios Business Model Canvas',

        //Consultas (CPF / Endereço - API Brasil)
        'consultas.access'                => 'Pode realizar consultas de CPF/Endereço (API Brasil)',

        //Privacidade granular (processos, clientes e contratos)
        'privacidade.bypass'              => 'Vê e edita todos os processos, clientes e contratos, inclusive os restritos',
        'privacidade.manage'              => 'Pode gerenciar visibilidade e advogados com acesso de qualquer registro',

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
            'beta.*',
            'casos.*',
            'clientes.*',
            'tarefas.*',
            'processos.*',
            'financeiro.*',
            'contratos.*',
            'fornecedores.*',
            'objetos.*',
            'exportacao.*',
            'module.*',
            'checklist.*',
            'liquidacoes.*',
            'anotacoes.*',
            'etiquetas.*',
            'mensagens.*',
            'monitorar.*',
            'conhecimento.*',
            'business_canvas.*',
            'consultas.*',
        ],
        'admin' => [
            'users.*',
            'documentacao.*',
            'contabil.*',
            'site.conteudo',
            'site.configuracoes',
            'site.publicar',
            'privacidade.*',
            'beta.*',
            'casos.*',
            'clientes.*',
            'tarefas.*',
            'processos.*',
            'financeiro.*',
            'contratos.*',
            'fornecedores.*',
            'objetos.*',
            'exportacao.*',
            'module.*',
            'liquidacoes.*',
            'anotacoes.*',
            'etiquetas.*',
            'mensagens.*',
            'monitorar.*',
            'conhecimento.*',
            'business_canvas.*',
            'consultas.*',
        ],
        'developer' => [
            'documentacao.*',
            'contabil.*',
        ],
        'estagiario' => [
            'documentacao.access',
            'casos.access',
            'casos.create',
            'casos.edit',
            'clientes.access',
            'clientes.create',
            'clientes.edit',
            'tarefas.access',
            'tarefas.create',
            'tarefas.edit',
            'processos.access',
            'processos.create',
            'processos.edit',
            'financeiro.access',
            'financeiro.create',
            'financeiro.edit',
            'intimacoes.access',
            'intimacoes.create',
            'intimacoes.edit',
            'contratos.access',
            'contratos.create',
            'contratos.edit',
            'fornecedores.access',
            'fornecedores.create',
            'fornecedores.edit',
            'objetos.access',
            'objetos.create',
            'objetos.edit',
            'exportacao.access',
            'exportacao.create',
            'exportacao.edit',
            'intimacoes.access',
            'intimacoes.create',
            'intimacoes.edit',
            'module.access',
            'module.create',
            'module.edit',
            'anotacoes.*',
            'etiquetas.*',
            'mensagens.*',
            'monitorar.*',
            'movimentos.*',
            'conhecimento.access',
            'conhecimento.modelos.gerar',
            'consultas.access',
        ],
        'user' => [
            'documentacao.access',
            'processos.exclusive',
            'liquidacoes.exclusive',
            'casos.exclusive',
            'checklist.exclusive',
            'business_canvas.exclusive',
        ],
        'beta' => [
            'beta.access',
        ],
        'cliente' => [],
    ];
}
