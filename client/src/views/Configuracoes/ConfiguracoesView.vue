<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Configurações</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Configurações do Sistema</h1>
        <p>Gerencie as preferências gerais, usuários, grupos de acesso e cache do sistema.</p>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="filter-tabs" role="tablist" aria-label="Abas de configuração" @keydown="onTabKeydown">
          <button
            id="tab-sistema"
            class="filter-tab"
            :class="{ active: activeTab === 'sistema' }"
            @click="setTab('sistema')"
            role="tab"
            :aria-selected="activeTab === 'sistema'"
            :tabindex="activeTab === 'sistema' ? 0 : -1"
          >
            <i class="bi bi-gear me-1"></i> Sistema
          </button>
          <button
            id="tab-usuarios"
            class="filter-tab"
            :class="{ active: activeTab === 'usuarios' }"
            @click="setTab('usuarios')"
            role="tab"
            :aria-selected="activeTab === 'usuarios'"
            :tabindex="activeTab === 'usuarios' ? 0 : -1"
          >
            <i class="bi bi-people me-1"></i> Usuários
          </button>
          <button
            id="tab-mcp"
            class="filter-tab"
            :class="{ active: activeTab === 'mcp' }"
            @click="setTab('mcp')"
            role="tab"
            :aria-selected="activeTab === 'mcp'"
            :tabindex="activeTab === 'mcp' ? 0 : -1"
          >
            <i class="bi bi-robot me-1"></i> Integração IA (MCP)
          </button>
          <button
            v-if="podeGerirModulos"
            id="tab-modulos"
            class="filter-tab"
            :class="{ active: activeTab === 'modulos' }"
            @click="setTab('modulos')"
            role="tab"
            :aria-selected="activeTab === 'modulos'"
            :tabindex="activeTab === 'modulos' ? 0 : -1"
          >
            <i class="bi bi-grid-3x3-gap me-1"></i> Módulos
          </button>
          <button
            id="tab-migracoes"
            class="filter-tab"
            :class="{ active: activeTab === 'migracoes' }"
            @click="setTab('migracoes')"
            role="tab"
            :aria-selected="activeTab === 'migracoes'"
            :tabindex="activeTab === 'migracoes' ? 0 : -1"
          >
            <i class="bi bi-database me-1"></i> Banco de Dados
          </button>
          <button
            v-if="podeGerirDrive"
            id="tab-drive"
            class="filter-tab"
            :class="{ active: activeTab === 'drive' }"
            @click="setTab('drive')"
            role="tab"
            :aria-selected="activeTab === 'drive'"
            :tabindex="activeTab === 'drive' ? 0 : -1"
          >
            <i class="bi bi-cloud-arrow-up me-1"></i> Google Drive
          </button>
        </div>
      </div>

      <!-- Action Button based on active tab -->
      <div class="toolbar-right">
        <button v-if="activeTab === 'sistema'" class="btn btn-primary" @click="openConfigModal()">
          <i class="bi bi-plus-circle"></i> Nova Configuração
        </button>
        <button v-if="activeTab === 'usuarios'" class="btn btn-primary" @click="openUserModal()">
          <i class="bi bi-person-plus"></i> Novo Usuário
        </button>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top: 1.5rem;">

      <!-- Left / Main Column -->
      <div class="main-column">

        <!-- ================= SYSTEM TAB ================= -->
        <div v-if="activeTab === 'sistema'">
          <div>
          <!-- Loading state -->
          <div v-if="loadingConfigs" class="process-list">
            <div class="skeleton-card" v-for="i in 3" :key="i">
              <div class="skeleton-line" style="width: 30%; height: 16px;"></div>
              <div class="skeleton-line" style="width: 50%; height: 12px;"></div>
            </div>
          </div>

          <!-- Empty configs state -->
          <div v-else-if="!configs.length" class="empty-state card">
            <i class="bi bi-sliders" style="font-size:3rem;color:var(--accent);"></i>
            <h4>Nenhuma configuração cadastrada</h4>
            <p>Cadastre parâmetros dinâmicos para o funcionamento do sistema.</p>
            <button class="btn btn-primary mt-3" @click="openConfigModal()">
              <i class="bi bi-plus-circle"></i> Criar Configuração
            </button>
          </div>

          <!-- Configs Table -->
          <div v-else class="card animate-fade-in" style="overflow-x: auto;">
            <table class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left; table-layout: fixed; font-size: 0.8rem;">
              <colgroup>
                <col style="width: 22%;" />
                <col style="width: 40%;" />
                <col style="width: 30%;" />
                <col style="width: 8%;" />
              </colgroup>
              <thead>
                <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
                  <th style="padding: 10px 12px; font-size: 0.72rem;">Parâmetro (Chave)</th>
                  <th style="padding: 10px 12px; font-size: 0.72rem;">Valor</th>
                  <th style="padding: 10px 12px; font-size: 0.72rem;">Descrição</th>
                  <th style="padding: 10px 12px; font-size: 0.72rem; text-align: right;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="config in configs" :key="config.id" style="border-bottom: 1px solid var(--border);">
                  <td style="padding: 10px 12px; font-weight: 600; color: var(--ink); font-size: 0.8rem; overflow-wrap: anywhere;">{{ config.nome }}</td>
                  <td style="padding: 10px 12px; font-family: monospace; font-size: 0.74rem; color: var(--accent); word-break: break-all;">{{ config.valor }}</td>
                  <td style="padding: 10px 12px; color: var(--ink-muted); font-size: 0.76rem; overflow-wrap: anywhere;">{{ config.descricao || '-' }}</td>
                  <td style="padding: 10px 12px; text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <button class="btn btn-ghost btn-sm" @click="openConfigModal(config)" title="Editar">
                        <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          </div>
        </div>

        <!-- ================= USERS TAB ================= -->
        <div v-if="activeTab === 'usuarios'">

          <!-- Search users input -->
          <div class="card" style="margin-bottom: 1.5rem; padding: 12px 16px; display: flex; gap: 8px; align-items: center; background: var(--surface);">
            <i class="bi bi-search" style="color: var(--ink-muted);"></i>
            <input
              type="text"
              class="form-control"
              v-model="userSearchQuery"
              placeholder="Buscar usuários por nome ou e-mail..."
              style="border: none; background: transparent; padding: 4px; box-shadow: none;"
              @input="debounceUserSearch"
            />
          </div>

          <!-- Loading state -->
          <div v-if="loadingUsers" class="process-list">
            <div class="skeleton-card" v-for="i in 3" :key="i">
              <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
              <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
            </div>
          </div>

          <!-- Empty users state -->
          <div v-else-if="!users.length" class="empty-state card">
            <i class="bi bi-people" style="font-size:3rem;color:var(--accent);"></i>
            <h4>Nenhum usuário encontrado</h4>
            <p>Não há usuários cadastrados com os critérios fornecidos.</p>
          </div>

          <!-- Users List (Process cards style) -->
          <div v-else class="process-list">
            <div
              v-for="user in users"
              :key="user.id"
              class="process-card animate-fade-in"
              :class="{ 'opacity-60': !user.active }"
            >
              <div class="process-main" style="display: flex; gap: 16px; align-items: center;">

                <!-- Avatar image/placeholder -->
                <div class="user-avatar-container">
                  <img
                    v-if="user.auth_image"
                    :src="getUserAvatarUrl(user)"
                    class="user-profile-avatar"
                    @error="user.auth_image = null"
                    alt="Foto de perfil"
                  />
                  <div v-else class="user-initials-avatar">
                    {{ getUserInitials(user.username) }}
                  </div>
                </div>

                <div style="flex: 1; min-width: 0;">
                  <div class="d-flex align-items-center gap-2">
                    <span class="process-cnj" style="font-size: 1.1rem; font-weight: 600; color: var(--ink);">
                      {{ user.username }}
                    </span>
                    <span v-if="user.is_profissional == 1" class="badge bg-secondary" style="font-size: 0.72rem; padding: 2px 6px;">
                      Profissional <span v-if="user.registro_numero">({{ user.registro_uf }}-{{ user.registro_numero }})</span>
                    </span>
                    <span v-if="!user.active" class="badge bg-danger" style="font-size: 0.72rem; padding: 2px 6px;">
                      Inativo
                    </span>
                  </div>

                  <div class="process-meta" style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 12px;">
                    <span>
                      <i class="bi bi-envelope"></i> <strong>{{ user.email || 'Sem e-mail' }}</strong>
                    </span>
                    <span v-if="user.groups && user.groups.length">
                      <i class="bi bi-shield-lock"></i>
                      Grupos:
                      <span v-for="g in user.groups" :key="g" class="badge rounded-pill bg-light text-dark" style="margin-right: 3px; font-size: 0.72rem;">
                        {{ g }}
                      </span>
                    </span>
                    <span v-if="user.rateio_ativo == 1" class="text-success" style="font-size: 0.82rem;">
                      <i class="bi bi-cash-stack"></i> Participa do Rateio
                    </span>
                  </div>
                </div>

              </div>

              <!-- Actions -->
              <div class="process-actions">
                <div class="action-buttons-wrapper">
                  <button class="btn btn-ghost btn-sm" @click="openUserModal(user)" title="Editar Usuário">
                    <i class="bi bi-pencil-square" style="color: var(--accent);"></i>
                  </button>
                  <button class="btn btn-ghost btn-sm" @click="confirmarExcluirUsuario(user)" title="Excluir Usuário">
                    <i class="bi bi-trash" style="color: var(--danger);"></i>
                  </button>
                </div>
              </div>

            </div>

            <!-- Pagination -->
            <div class="pagination" v-if="!loadingUsers && pagination.total_pages > 1">
              <button class="page-btn" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">
                <i class="bi bi-chevron-left"></i>
              </button>

              <span class="page-info" style="color: var(--ink-muted); margin: 0 10px;">
                Página <strong>{{ pagination.current_page }}</strong> de <strong>{{ pagination.total_pages }}</strong>
              </span>

              <button class="page-btn" :disabled="pagination.current_page === pagination.total_pages" @click="changePage(pagination.current_page + 1)">
                <i class="bi bi-chevron-right"></i>
              </button>
            </div>

          </div>

        </div>

        <!-- ================= MCP / IA TAB ================= -->
        <div v-if="activeTab === 'mcp'">

          <!-- Native Claude.ai connector card -->
          <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px; border: 1px solid var(--accent);">
            <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-plug" style="color: var(--accent);"></i> Conector nativo do Claude.ai
            </h3>
            <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
              Para usar em <strong>claude.ai</strong> (Configurações &rsaquo; Conectores &rsaquo; Adicionar conector
              personalizado), não é preciso gerar nenhum token abaixo: cole apenas a URL do endpoint MCP no
              campo "URL do servidor MCP remoto" e deixe "ID do Cliente OAuth" e "Client Secret" em branco.
              O Claude se registra e faz login sozinho, abrindo uma tela de autorização do sistema — a IA
              passará a agir estritamente como você, respeitando suas permissões.
            </p>
            <div style="display: flex; gap: 8px;">
              <input class="form-control" type="text" :value="mcpUrl" readonly @focus="($event.target as HTMLInputElement).select()" style="font-family: monospace; font-size: 0.85rem;" />
              <button class="btn btn-ghost btn-sm" @click="copyMcpUrl" title="Copiar URL">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </div>

          <!-- Manual OAuth connector card (Gemini) -->
          <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
            <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-shield-lock" style="color: var(--accent);"></i> Gemini e outros conectores OAuth
            </h3>
            <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
              Para conectores que pedem <strong>Client ID</strong> e <strong>Client Secret</strong> (ex.: conector
              personalizado do Gemini). Informe a URL de redirecionamento (callback) que o próprio conector
              exibe na tela de configuração; em seguida o sistema mostra as credenciais e as URLs para colar lá.
            </p>
            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
              <div class="form-group" style="flex: 1; min-width: 160px; margin-bottom: 0;">
                <label class="form-label">Nome do conector</label>
                <input class="form-control" type="text" v-model="novoClienteNome" placeholder='Ex.: "Gemini"' />
              </div>
              <div class="form-group" style="flex: 2; min-width: 240px; margin-bottom: 0;">
                <label class="form-label">URL de redirecionamento (callback)</label>
                <input
                  class="form-control"
                  type="url"
                  v-model="novoClienteRedirect"
                  placeholder="https://..."
                  @keyup.enter="criarClienteOAuth"
                />
              </div>
              <button class="btn btn-primary" @click="criarClienteOAuth" :disabled="criandoCliente">
                <i class="bi bi-plus-lg"></i> {{ criandoCliente ? 'Criando...' : 'Criar credenciais' }}
              </button>
            </div>

            <table v-if="clientesOAuth.length" class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left; margin-top: 16px;">
              <thead>
                <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
                  <th style="padding: 10px 12px;">Conector</th>
                  <th style="padding: 10px 12px;">Client ID</th>
                  <th style="padding: 10px 12px;">Criado em</th>
                  <th style="padding: 10px 12px; text-align: right;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cl in clientesOAuth" :key="cl.id" style="border-bottom: 1px solid var(--border);">
                  <td style="padding: 10px 12px; font-weight: 600; color: var(--ink);">
                    {{ cl.nome }}
                    <div style="font-weight: 400; font-size: 0.78rem; color: var(--ink-muted); word-break: break-all;">{{ cl.redirect_uri }}</div>
                  </td>
                  <td style="padding: 10px 12px; font-family: monospace; font-size: 0.82rem; color: var(--ink-muted);">{{ cl.client_id }}</td>
                  <td style="padding: 10px 12px; color: var(--ink-muted); font-size: 0.88rem;">{{ formatDate(cl.criado_em, true) }}</td>
                  <td style="padding: 10px 12px; text-align: right;">
                    <button class="btn btn-ghost btn-sm" @click="confirmarExcluirClienteOAuth(cl)" title="Remover conector">
                      <i class="bi bi-trash" style="color:var(--danger);"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Generate token card -->
          <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
            <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-robot" style="color: var(--accent);"></i> Gerar novo token de IA
            </h3>
            <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
              Use isto apenas para clientes de IA sem suporte a conectores OAuth (Claude Desktop, ChatGPT
              etc.) — gere um token pessoal e cole na configuração deles. A IA passa a agir estritamente
              como você: respeita suas permissões e nenhuma ação pode ser atribuída a outro usuário.
            </p>
            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
              <div class="form-group" style="flex: 1; min-width: 220px; margin-bottom: 0;">
                <label class="form-label">Nome do token</label>
                <input
                  class="form-control"
                  type="text"
                  v-model="novoTokenNome"
                  placeholder='Ex.: "Claude Desktop"'
                  @keyup.enter="gerarMcpToken"
                />
              </div>
              <button class="btn btn-primary" @click="gerarMcpToken" :disabled="gerandoToken">
                <i class="bi bi-key"></i> {{ gerandoToken ? 'Gerando...' : 'Gerar Token' }}
              </button>
            </div>
          </div>

          <!-- Loading state -->
          <div v-if="loadingMcpTokens" class="process-list">
            <div class="skeleton-card" v-for="i in 2" :key="i">
              <div class="skeleton-line" style="width: 30%; height: 16px;"></div>
              <div class="skeleton-line" style="width: 50%; height: 12px;"></div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else-if="!mcpTokens.length" class="empty-state card">
            <i class="bi bi-key" style="font-size:3rem;color:var(--accent);"></i>
            <h4>Nenhum token de IA gerado</h4>
            <p>Gere um token acima para conectar um chat de IA externo ao sistema.</p>
          </div>

          <!-- Tokens table -->
          <div v-else class="card animate-fade-in" style="overflow-x: auto;">
            <table class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left;">
              <thead>
                <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
                  <th style="padding: 12px 16px;">Nome</th>
                  <th style="padding: 12px 16px;">Criado em</th>
                  <th style="padding: 12px 16px;">Último uso</th>
                  <th style="padding: 12px 16px;">Expira em</th>
                  <th style="padding: 12px 16px; text-align: right;">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tk in mcpTokens" :key="tk.id" style="border-bottom: 1px solid var(--border);">
                  <td style="padding: 12px 16px; font-weight: 600; color: var(--ink);">
                    <i class="bi bi-key-fill" style="color:var(--accent); margin-right:6px;"></i>{{ tk.nome }}
                  </td>
                  <td style="padding: 12px 16px; color: var(--ink-muted); font-size: 0.88rem;">{{ formatDate(tk.criado_em, true) }}</td>
                  <td style="padding: 12px 16px; color: var(--ink-muted); font-size: 0.88rem;">{{ tk.ultimo_uso_em ? formatDate(tk.ultimo_uso_em, true) : 'Nunca usado' }}</td>
                  <td style="padding: 12px 16px; color: var(--ink-muted); font-size: 0.88rem;">{{ tk.expira_em ? formatDate(tk.expira_em, true) : 'Não expira' }}</td>
                  <td style="padding: 12px 16px; text-align: right;">
                    <button class="btn btn-ghost btn-sm" @click="confirmarRevogarMcpToken(tk)" title="Revogar token">
                      <i class="bi bi-trash" style="color:var(--danger);"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- ================= MÓDULOS TAB ================= -->
        <ModulosPanel v-if="activeTab === 'modulos' && podeGerirModulos" @toast="(payload) => emit('toast', payload)" />

        <!-- ================= MIGRATIONS TAB ================= -->
        <MigracoesPanel v-if="activeTab === 'migracoes'" @toast="(payload) => emit('toast', payload)" />

        <!-- ================= GOOGLE DRIVE TAB ================= -->
        <DriveSyncPanel v-if="activeTab === 'drive' && podeGerirDrive" @toast="(payload) => emit('toast', payload)" />

      </div>

      <!-- Right Column – System Tools & Info -->
      <div class="sidebar-widgets">

        <!-- Google Auth Widget -->
        <div class="card animate-fade-in" style="margin-bottom: 16px;">
          <div class="card-header">
            <h3><i class="bi bi-google"></i> Integração Google</h3>
          </div>
          <div style="padding: 16px; display: flex; flex-direction: column; gap: 12px; align-items: flex-start;">
            <p style="font-size: 0.85rem; color: var(--ink-muted); line-height: 1.4; margin: 0 0 12px 0;">
              Autentique-se com o Google para gerar o Refresh Token necessário para integração do sistema com o Google Drive.
            </p>
            <button
              class="btn btn-primary"
              style="width: 100%; justify-content: center;"
              @click="autenticarGoogle"
            >
              <i class="bi bi-google me-1"></i> Autenticar com Google
            </button>
          </div>
        </div>

        <!-- Cache Clearing Widget -->
        <div class="card animate-fade-in" style="margin-bottom: 16px;">
          <div class="card-header">
            <h3><i class="bi bi-cpu"></i> Manutenção de Cache</h3>
          </div>
          <div style="padding: 16px; display: flex; flex-direction: column; gap: 12px; align-items: flex-start;">
            <p style="font-size: 0.85rem; color: var(--ink-muted); line-height: 1.4; margin: 0 0 12px 0;">
              Se o sistema apresentar lentidão ou não estiver refletindo alterações recentes de banco de dados, você pode forçar a limpeza de caches do backend.
            </p>
            <button
              class="btn btn-danger"
              style="background-color: var(--danger); border-color: var(--danger); color: white;"
              @click="confirmarLimparCache"
              :disabled="clearingCache"
            >
              <i class="bi bi-trash2-fill me-1"></i>
              {{ clearingCache ? 'Limpando...' : 'Limpar Cache do Backend' }}
            </button>
          </div>
        </div>

        <!-- Information Info Widget -->
        <div class="card animate-fade-in">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Permissões & Regras</h3>
          </div>
          <div style="padding: 16px;">
            <ul class="widget-list" style="margin: 0; padding: 0; list-style: none;">
              <li style="font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 8px;">
                <i class="bi bi-check2-circle text-success me-1"></i> Apenas administradores podem criar e modificar usuários do sistema.
              </li>
              <li style="font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 8px;">
                <i class="bi bi-check2-circle text-success me-1"></i> Profissionais exigem preenchimento obrigatório da UF e do número do registro profissional (CRM, CRO, CRMV...).
              </li>
              <li style="font-size: 0.85rem; color: var(--ink-muted);">
                <i class="bi bi-check2-circle text-success me-1"></i> O rateio de sócios determina quais usuários aparecem nos repasses de receitas e despesas.
              </li>
            </ul>
          </div>
        </div>

      </div>

    </div>

    <!-- Modals -->
    <ConfigModal
      v-if="showConfigModal"
      :config-data="selectedConfig"
      @close="closeConfigModal"
      @submit="handleConfigSubmit"
      @warn="showToastWarning"
    />

    <UserModal
      v-if="showUserModal"
      :user-data="selectedUser"
      :groups-info="groupsMatrix"
      @close="closeUserModal"
      @submit="handleUserSubmit"
      @warn="showToastWarning"
    />

    <McpTokenModal
      v-if="showMcpTokenModal && novoTokenGerado"
      :token="novoTokenGerado.token"
      :token-name="novoTokenGerado.nome"
      :mcp-url="mcpUrl"
      @close="closeMcpTokenModal"
      @toast="(payload) => emit('toast', payload)"
    />

    <OAuthClienteModal
      v-if="novoClienteCriado"
      :cliente="novoClienteCriado"
      @close="novoClienteCriado = null"
      @toast="(payload) => emit('toast', payload)"
    />

  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.7.0
 */
import { ref, computed, onMounted, nextTick } from 'vue'
import { apiFetch, BASE_URL, SITE_BASE_URL, formatDate } from '../../services/api'
import ConfigModal from '../../components/modals/Configuracoes/ConfigModal.vue'
import UserModal from '../../components/modals/Configuracoes/UserModal.vue'
import McpTokenModal from '../../components/modals/Configuracoes/McpTokenModal.vue'
import OAuthClienteModal from '../../components/modals/Configuracoes/OAuthClienteModal.vue'
import MigracoesPanel from '../../components/Configuracoes/MigracoesPanel.vue'
import ModulosPanel from '../../components/Configuracoes/ModulosPanel.vue'
import DriveSyncPanel from '../../components/Configuracoes/DriveSyncPanel.vue'
import { pode } from '../../composables/useAcesso'

const emit = defineEmits(['toast', 'set-view'])

// State
const activeTab = ref('sistema')
// Ativar/desativar módulos exige module.edit (mesma permissão de modulos/toggleStatus).
const podeGerirModulos = computed(() => pode('module.edit'))
// A API de sincronização do Drive é restrita a administradores (admin.access).
const podeGerirDrive = computed(() => pode('admin.access'))

// System settings states
const configs = ref([])
const loadingConfigs = ref(false)
const showConfigModal = ref(false)
const selectedConfig = ref(null)

// Users states
const users = ref([])
const loadingUsers = ref(false)
const showUserModal = ref(false)
const selectedUser = ref(null)
const userSearchQuery = ref('')
const groupsMatrix = ref({ groups: {}, permissions: {}, matrix: {} })
const pagination = ref({ current_page: 1, total_pages: 1 })

// Cache action state
const clearingCache = ref(false)

// MCP / IA integration states
const mcpTokens = ref([])
const loadingMcpTokens = ref(false)
const novoTokenNome = ref('Claude Desktop')
const gerandoToken = ref(false)
const showMcpTokenModal = ref(false)
const novoTokenGerado = ref(null)
const mcpUrl = SITE_BASE_URL.replace(/\/+$/, '') + '/mcp'
const clientesOAuth = ref([])
const novoClienteNome = ref('Gemini')
const novoClienteRedirect = ref('')
const criandoCliente = ref(false)
const novoClienteCriado = ref(null)

// Tab handling
function setTab(tab) {
  activeTab.value = tab
  if (tab === 'sistema') {
    fetchConfigs()
  } else if (tab === 'usuarios') {
    fetchUsers()
  } else if (tab === 'mcp') {
    fetchMcpTokens()
    fetchClientesOAuth()
  }

  nextTick(() => {
    const activeEl = document.getElementById('tab-' + tab)
    if (activeEl) activeEl.focus()
  })
}

function onTabKeydown(e) {
  const tabs = ['sistema', 'usuarios', 'mcp', 'migracoes']
  if (podeGerirDrive.value) tabs.push('drive')
  const currentIdx = tabs.indexOf(activeTab.value)
  let nextIdx

  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown':
      e.preventDefault()
      nextIdx = (currentIdx + 1) % tabs.length
      setTab(tabs[nextIdx])
      break
    case 'ArrowLeft':
    case 'ArrowUp':
      e.preventDefault()
      nextIdx = (currentIdx - 1 + tabs.length) % tabs.length
      setTab(tabs[nextIdx])
      break
    case 'Home':
      e.preventDefault()
      setTab(tabs[0])
      break
    case 'End':
      e.preventDefault()
      setTab(tabs[tabs.length - 1])
      break
  }
}

// ── Configurations ──────────────────────────────────────────
async function fetchConfigs() {
  loadingConfigs.value = true
  try {
    const res = await apiFetch('configuracoes')
    if (res && res.success) {
      configs.value = res.data || []
    } else {
      emit('toast', { msg: res?.message || 'Erro ao carregar configurações', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar configurações do backend.', type: 'error' })
  } finally {
    loadingConfigs.value = false
  }
}

function openConfigModal(config = null) {
  selectedConfig.value = config
  showConfigModal.value = true
}

function closeConfigModal() {
  showConfigModal.value = false
  selectedConfig.value = null
}

async function handleConfigSubmit(formData) {
  try {
    const params = new URLSearchParams()
    if (formData.id) params.append('id', String(formData.id))
    params.append('nome', formData.nome)
    params.append('valor', formData.valor)
    params.append('descricao', formData.descricao || '')

    const res = await apiFetch('configuracoes/salvar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: params.toString()
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Configuração salva!', type: 'success' })
      closeConfigModal()
      fetchConfigs()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar configuração', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao salvar configuração.', type: 'error' })
  }
}

// ── Users ────────────────────────────────────────────────────
async function fetchUsers() {
  loadingUsers.value = true
  try {
    const query = new URLSearchParams()
    if (userSearchQuery.value) query.append('s', userSearchQuery.value)
    query.append('page', String(pagination.value.current_page))
    query.append('perPage', '10')

    const res = await apiFetch(`usuarios?${query.toString()}`)
    if (res && res.success) {
      users.value = res.data || []
      if (res.pagination) {
        pagination.value = {
          current_page: res.pagination.current_page || 1,
          total_pages: res.pagination.total_pages || 1
        }
      }
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao buscar listagem de usuários.', type: 'error' })
  } finally {
    loadingUsers.value = false
  }
}

async function fetchGroupsMatrix() {
  try {
    const res = await apiFetch('usuarios/grupos')
    if (res && res.success) {
      groupsMatrix.value = {
        groups: res.groups || {},
        permissions: res.permissions || {},
        matrix: res.matrix || {}
      }
    }
  } catch (err) {
    console.error('Erro ao obter matriz de permissões/grupos do backend:', err)
  }
}

let searchDebounceTimeout = null
function debounceUserSearch() {
  clearTimeout(searchDebounceTimeout)
  searchDebounceTimeout = setTimeout(() => {
    pagination.value.current_page = 1
    fetchUsers()
  }, 400)
}

function changePage(p) {
  if (p < 1 || p > pagination.value.total_pages) return
  pagination.value.current_page = p
  fetchUsers()
}

async function openUserModal(user = null) {
  if (user) {
    try {
      const res = await apiFetch(`usuarios/detalhes/${user.id}`)
      if (res && res.success) {
        selectedUser.value = res.data
      } else {
        selectedUser.value = user
      }
    } catch (err) {
      selectedUser.value = user
    }
  } else {
    selectedUser.value = null
  }
  showUserModal.value = true
}

function closeUserModal() {
  showUserModal.value = false
  selectedUser.value = null
}

async function handleUserSubmit(formData) {
  try {
    const isEdit = !!formData.id
    const url = isEdit ? `usuarios/atualizar/${formData.id}` : 'usuarios/salvar'

    // We must use standard FormData for multipart uploads (images)
    const bodyFormData = new FormData()
    if (isEdit) bodyFormData.append('id', String(formData.id))
    bodyFormData.append('username', formData.username)
    bodyFormData.append('email', formData.email)

    if (formData.password) {
      bodyFormData.append('password', formData.password)
      bodyFormData.append('pass_confirm', formData.pass_confirm)
    }

    bodyFormData.append('is_profissional', formData.is_profissional ? '1' : '0')
    bodyFormData.append('rateio_ativo', formData.rateio_ativo ? '1' : '0')
    bodyFormData.append('active', formData.active ? '1' : '0')
    bodyFormData.append('responsavel_tarefas', formData.responsavel_tarefas ? '1' : '0')
    bodyFormData.append('exibir_site', formData.exibir_site ? '1' : '0')
    bodyFormData.append('site_ordem', String(formData.site_ordem || 0))
    for (const campo of ['cargo', 'formacao', 'especializacoes', 'areas_atuacao', 'curriculo', 'lattes_url', 'linkedin_url']) {
      bodyFormData.append(campo, formData[campo] || '')
    }

    if (formData.registro_numero) bodyFormData.append('registro_numero', formData.registro_numero)
    if (formData.registro_uf) bodyFormData.append('registro_uf', formData.registro_uf)

    if (formData.groups && formData.groups.length) {
      bodyFormData.append('groups', JSON.stringify(formData.groups))
    }
    if (formData.permissions && formData.permissions.length) {
      bodyFormData.append('permissions', JSON.stringify(formData.permissions))
    }

    if (formData.photoFile) {
      bodyFormData.append('foto-perfil', formData.photoFile)
    }

    const res = await apiFetch(url, {
      method: 'POST',
      body: bodyFormData
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Usuário salvo com sucesso!', type: 'success' })
      closeUserModal()
      fetchUsers()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar usuário', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao salvar usuário.', type: 'error' })
  }
}

async function confirmarExcluirUsuario(user) {
  if (!confirm(`Tem certeza que deseja excluir o usuário "${user.username}"? Esta ação não pode ser desfeita.`)) return

  try {
    const res = await apiFetch(`usuarios/excluir/${user.id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: 'Usuário excluído com sucesso!', type: 'success' })
      fetchUsers()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao excluir usuário.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao se conectar ao servidor.', type: 'error' })
  }
}

// ── MCP / Integração com IA ─────────────────────────────────
async function fetchMcpTokens() {
  loadingMcpTokens.value = true
  try {
    const res = await apiFetch('integracao-ia/tokens')
    if (res && res.success) {
      mcpTokens.value = res.data || []
    } else {
      emit('toast', { msg: res?.message || 'Erro ao carregar tokens de IA.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar tokens de IA do backend.', type: 'error' })
  } finally {
    loadingMcpTokens.value = false
  }
}

async function gerarMcpToken() {
  const nome = novoTokenNome.value.trim()
  if (!nome) {
    emit('toast', { msg: 'Informe um nome para identificar o token (ex.: "Claude Desktop").', type: 'warning' })
    return
  }

  gerandoToken.value = true
  try {
    const res = await apiFetch('integracao-ia/tokens', {
      method: 'POST',
      body: { nome }
    })

    if (res && res.success) {
      novoTokenGerado.value = res.data
      showMcpTokenModal.value = true
      novoTokenNome.value = 'Claude Desktop'
      fetchMcpTokens()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao gerar token.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao gerar token.', type: 'error' })
  } finally {
    gerandoToken.value = false
  }
}

function closeMcpTokenModal() {
  showMcpTokenModal.value = false
  novoTokenGerado.value = null
}

async function confirmarRevogarMcpToken(tk) {
  if (!confirm(`Revogar o token "${tk.nome}"? O chat de IA conectado com ele perderá acesso imediatamente.`)) return

  try {
    const res = await apiFetch(`integracao-ia/tokens/${tk.id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Token revogado.', type: 'success' })
      fetchMcpTokens()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao revogar token.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao se conectar ao servidor.', type: 'error' })
  }
}

async function fetchClientesOAuth() {
  try {
    const res = await apiFetch('integracao-ia/clientes-oauth')
    if (res && res.success) {
      clientesOAuth.value = res.data || []
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar conectores OAuth.', type: 'error' })
  }
}

async function criarClienteOAuth() {
  const nome = novoClienteNome.value.trim()
  const redirect_uri = novoClienteRedirect.value.trim()
  if (!nome || !redirect_uri) {
    emit('toast', { msg: 'Informe o nome e a URL de redirecionamento exibida pelo conector.', type: 'warning' })
    return
  }

  criandoCliente.value = true
  try {
    const res = await apiFetch('integracao-ia/clientes-oauth', {
      method: 'POST',
      body: { nome, redirect_uri }
    })

    if (res && res.success) {
      novoClienteCriado.value = res.data
      novoClienteNome.value = 'Gemini'
      novoClienteRedirect.value = ''
      fetchClientesOAuth()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao criar conector.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao criar conector.', type: 'error' })
  } finally {
    criandoCliente.value = false
  }
}

async function confirmarExcluirClienteOAuth(cl) {
  if (!confirm(`Remover o conector "${cl.nome}"? Ele não poderá mais pedir novas autorizações. Tokens já emitidos para ele continuam na lista abaixo e podem ser revogados lá.`)) return

  try {
    const res = await apiFetch(`integracao-ia/clientes-oauth/${cl.id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Conector removido.', type: 'success' })
      fetchClientesOAuth()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao remover conector.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao se conectar ao servidor.', type: 'error' })
  }
}

async function copyMcpUrl() {
  try {
    await navigator.clipboard.writeText(mcpUrl)
    emit('toast', { msg: 'URL do endpoint MCP copiada!', type: 'success' })
  } catch (err) {
    emit('toast', { msg: 'Não foi possível copiar automaticamente. Selecione e copie manualmente.', type: 'error' })
  }
}

// Helpers
function getUserAvatarUrl(user) {
  const cacheBuster = user.auth_image ? user.auth_image : Date.now()
  return `${BASE_URL}usuarios/exibirFoto/${user.id}?t=${cacheBuster}`
}

function getUserInitials(name) {
  if (!name) return 'U'
  return name.trim().split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

// ── Integrações ──────────────────────────────────────────────
function autenticarGoogle() {
  window.location.href = BASE_URL + 'google-auth/login';
}

// ── Cache Maintenance ────────────────────────────────────────
async function confirmarLimparCache() {
  if (!confirm('Deseja limpar todo o cache de consultas e arquivos temporários do servidor?')) return

  clearingCache.value = true
  try {
    const res = await apiFetch('configuracoes/limpar-cache', { method: 'POST' })
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Cache do servidor limpo com sucesso!', type: 'success' })
    } else {
      emit('toast', { msg: res?.message || 'Erro ao limpar cache.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Falha na comunicação com o servidor ao limpar cache.', type: 'error' })
  } finally {
    clearingCache.value = false
  }
}

function showToastWarning(msg) {
  emit('toast', { msg, type: 'warning' })
}

// Init
onMounted(() => {
  fetchConfigs()
  fetchGroupsMatrix()

  // Verificar parâmetros de redirecionamento do Google Auth
  const url = window.location.href
  if (url.includes('google_auth=success')) {
    emit('toast', { msg: 'Integração com o Google Drive autenticada com sucesso!', type: 'success' })
    history.replaceState(null, '', '#/configuracoes')
  } else if (url.includes('google_auth=error')) {
    const match = url.match(/error_desc=([^&]+)/)
    const errorMsg = match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : 'Falha na autenticação com o Google.'
    emit('toast', { msg: errorMsg, type: 'error' })
    history.replaceState(null, '', '#/configuracoes')
  }
})
</script>

<style scoped>
.user-avatar-container {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--primary);
  color: white;
  font-weight: bold;
  font-size: 1.1rem;
  border: 2px solid var(--border);
  flex-shrink: 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.user-profile-avatar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-initials-avatar {
  text-align: center;
  line-height: 48px;
}

.table-premium th {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: var(--surface);
}

.table-premium td {
  font-size: 0.95rem;
}

.opacity-60 {
  opacity: 0.6;
}

.w-100 {
  width: 100%;
}
</style>
