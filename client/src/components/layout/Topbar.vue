<template>
  <header class="topbar">
    <button class="topbar-icon-btn menu-toggle-btn" @click="$emit('toggle-sidebar')" aria-label="Toggle Sidebar" title="Alternar Barra Lateral">
      <i class="bi bi-list"></i>
    </button>
 
    <div class="topbar-title">
      {{
        currentView === 'dashboard' ? 'Dashboard' :
        currentView === 'crm' ? 'Pipeline CRM' :
        currentView === 'crm-leads' ? 'Leads CRM' :
        currentView === 'clientes' ? 'Clientes' :
        currentView === 'cliente-detail' ? 'Detalhes do Cliente' :
        currentView === 'contratos' ? 'Contratos' :
        currentView === 'contrato-detail' ? 'Detalhes do Contrato' :
        currentView === 'fornecedores' ? 'Fornecedores' :
        currentView === 'fornecedor-detail' ? 'Detalhes do Fornecedor' :
        currentView === 'despesas' ? 'Despesas' :
        currentView === 'extrato' ? 'Extrato' :
        currentView === 'contas' ? 'Contas Bancárias' :
        currentView === 'extrato-importacao' ? 'Importar Extrato' :
        currentView === 'extrato-movimentos' ? 'Movimentos do Extrato' :
        currentView === 'balanco' ? 'Balanço' :
        currentView === 'dre' ? 'DRE' :
        currentView === 'fechamento-mes' ? 'Fechamento do Mês' :
        currentView === 'contabil' ? 'Contabilidade' :
        currentView === 'contabil-importar' ? 'Contabilidade · Importar do Financeiro' :
        currentView === 'contabil-lancamentos' ? 'Contabilidade · Lançamentos' :
        currentView === 'contabil-plano-contas' ? 'Contabilidade · Plano de Contas' :
        currentView === 'contabil-relatorios' ? 'Contabilidade · Livros & Relatórios' :
        currentView === 'contabil-periodos' ? 'Contabilidade · Exercícios Fiscais' :
        currentView === 'despesa-detail' ? 'Detalhes da Despesa' :
        currentView === 'despesas-pagas' ? 'Despesas Pagas' :
        currentView === 'despesa-paga-detail' ? 'Detalhes da Despesa Paga' :
        currentView === 'receitas' ? 'Receitas' :
        currentView === 'receitas-recebidas' ? 'Receitas Recebidas' :
        currentView === 'receita-detail' ? 'Detalhes da Receita' :
        currentView === 'receita-recebida-detail' ? 'Detalhes da Receita Recebida' :
        currentView === 'tarefas' ? 'Tarefas' :
        currentView === 'business-canvas' ? 'Business Canvas' :
        currentView === 'perfil' ? 'Meu Perfil' :
        currentView === 'configuracoes' ? 'Configurações' :
        currentView === 'transferencias' ? 'Lista de Transferências' :
        currentView === 'transferencia-detail' ? 'Editar Transferência' :
        currentView === 'previsao-fluxo-caixa' ? 'Previsão de Fluxo de Caixa' :
        currentView === 'relatorio-categorias' ? 'Relatório por Categorias' :
        currentView === 'noticias' ? 'Notícias' :
        currentView === 'noticias-form' ? 'Editor de Notícia' :
        currentView === 'noticias-categorias' ? 'Categorias das Notícias' :
        currentView === 'site-paginas' ? 'Páginas do Site' :
        currentView === 'site-pagina-editor' ? 'Editor de Página' :
        currentView === 'site-menus' ? 'Menus do Site' :
        currentView === 'site-midias' ? 'Mídias do Site' :
        currentView === 'site-configuracoes' ? 'Configurações do Site' :
        currentView === 'site-aparencia' ? 'Aparência do Site' :
        currentView === 'site-templates' ? 'Templates do Site' :
        currentView === 'documentacao' ? 'Documentação' :
        currentView === 'documentacao-admin' ? 'Edição da Documentação' :
        currentView === 'documentacao-editor' ? 'Editor da Documentação' :
        'Saúde'
      }}
    </div> 
 
    <div class="topbar-search" v-if="currentView !== 'dashboard' && currentView !== 'crm' && ['clientes', 'contratos', 'fornecedores', 'crm-leads', 'tarefas', 'noticias'].includes(currentView)">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input
        type="text"
        :placeholder="currentView === 'contratos' ? 'Buscar contrato, cliente...' : currentView === 'fornecedores' ? 'Buscar fornecedor...' : currentView === 'crm-leads' ? 'Buscar lead por nome, e-mail, telefone...' : currentView === 'tarefas' ? 'Buscar tarefa...' : currentView === 'noticias' ? 'Buscar notícia...' : 'Buscar cliente...'"
        :value="modelValue"
        @input="onInput"
        @keyup.enter="$emit('do-search')"
        aria-label="Campo de busca"
      >
      <button v-if="modelValue" @click="$emit('clear-search')" aria-label="Limpar busca" title="Limpar busca" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:.8rem;">
        <i class="bi bi-x"></i>
      </button>
    </div>
 
    <div class="topbar-actions">
      <AjudaContextual :current-view="currentView" @abrir-documentacao="(d) => $emit('abrir-documentacao', d)" />
      <div class="notifications-menu-wrapper">
        <button
          class="topbar-icon-btn"
          title="Notificações"
          aria-label="Ver Notificações"
          @click="toggleNotificationsMenu"
          :aria-expanded="notificationsMenuOpen"
          aria-haspopup="true"
        >
          <i class="bi bi-bell"></i>
          <span :class="['topbar-badge', { 'badge-pulse': pulseNotifications }]" v-if="globalStats.notificacoes > 0">{{ globalStats.notificacoes }}</span>
        </button>
        <div v-if="notificationsMenuOpen" class="notifications-dropdown card" role="menu">
          <div class="notifications-dropdown-header" role="none">
            <strong role="none">Notificações</strong>
          </div>
          <div class="notifications-dropdown-body" role="none">
            <template v-if="notificationItems.length > 0">
              <a
                href="#"
                class="dropdown-item notification-item"
                v-for="item in notificationItems"
                :key="item.key"
                @click.prevent="goToNotification(item)"
                role="menuitem"
                tabindex="0"
                @keydown.enter="goToNotification(item)"
                @keydown.space.prevent="goToNotification(item)"
              >
                <i :class="['bi', item.icon]"></i>
                <span class="notification-label">{{ item.label }}</span>
                <span class="notification-count">{{ item.count }}</span>
              </a>
            </template>
            <div v-else class="dropdown-item text-muted" style="text-align: center; padding: 12px;">
              Nenhuma notificação nova.
            </div>
          </div>
        </div>
      </div>
      <!-- User Menu -->
      <div class="user-menu-wrapper" v-if="currentUser">
        <button 
          class="topbar-avatar-btn" 
          @click="toggleUserMenu" 
          :title="currentUser.username"
          :aria-label="'Menu do usuário: ' + currentUser.username"
          :aria-expanded="userMenuOpen"
          aria-haspopup="true"
        >
          <img v-if="currentUser.id && currentUser.avatar !== null && !avatarLoadError" :src="currentUser.avatar || getUserPhotoUrl(currentUser.id)" :alt="currentUser.username" class="avatar-img" @error="onAvatarError">
          <div v-else class="avatar-initials">{{ userInitials }}</div>
        </button>

        <div v-if="userMenuOpen" class="user-dropdown card" role="menu">
          <div class="user-dropdown-header" role="none">
            <div class="user-info" role="none">
              <span class="user-name" role="none">{{ currentUser.username || 'Usuário' }}</span>
              <span class="user-email" role="none">{{ currentUser.email || '' }}</span>
            </div>
          </div>
          <div class="user-dropdown-body" role="none">
            <a href="#/perfil" class="dropdown-item" @click.prevent="irPara('#/perfil')" role="menuitem" tabindex="0" @keydown.enter.prevent="irPara('#/perfil')" @keydown.space.prevent="irPara('#/perfil')">
              <i class="bi bi-person-gear"></i> Perfil
            </a>
            <a v-if="podeConfigurar" href="#/configuracoes" class="dropdown-item" @click.prevent="irPara('#/configuracoes')" role="menuitem" tabindex="0" @keydown.enter.prevent="irPara('#/configuracoes')" @keydown.space.prevent="irPara('#/configuracoes')">
              <i class="bi bi-gear"></i> Configurações
            </a>
            <a v-if="podeInstalar" href="#" class="dropdown-item" @click.prevent="instalar" role="menuitem" tabindex="0" @keydown.enter.prevent="instalar" @keydown.space.prevent="instalar">
              <i class="bi bi-download"></i> Instalar app
            </a>
            <div v-else-if="mostrarDicaIos" class="dropdown-item pwa-dica-ios" role="note">
              <i class="bi bi-phone"></i>
              <span>Para instalar: toque em <i class="bi bi-box-arrow-up" aria-label="Compartilhar"></i> e depois em <strong>Adicionar à Tela de Início</strong>.</span>
            </div>
            <div class="dropdown-divider" role="separator"></div>
            <a href="#" class="dropdown-item logout-item" @click.prevent="handleLogout" role="menuitem" tabindex="0" @keydown.enter="handleLogout" @keydown.space.prevent="handleLogout">
              <i class="bi bi-box-arrow-right"></i> Sair
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
 
<script setup lang="ts">
import AjudaContextual from '../documentacao/AjudaContextual.vue'
/**
 * @version 1.8.4
 */
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { logout, getUserPhotoUrl } from '../../services/auth'
import { telaLiberada } from '../../utils/acessoTelas'
import { usePwa } from '../../composables/usePwa'
import { encerrarPushAoSair } from '../../composables/usePush'

const props = defineProps({
  currentView: String,
  modelValue: String,
  currentUser: Object,
  globalStats: {
    type: Object,
    default: () => ({
      notificacoes: 0,
      qteTarefas: 0,
      qteDespesasNaoPagas: 0
    })
  }
})
 
const emit = defineEmits(['toggle-sidebar', 'update:modelValue', 'do-search', 'clear-search', 'logout', 'abrir-documentacao'])

 
function onInput(e) {
  emit('update:modelValue', e.target.value)
}

const userMenuOpen = ref(false)

// PWA: oferta de instalação (Chrome/Edge/Android) ou instrução manual (iPhone/iPad)
const { podeInstalar, instalado, ehIosSafari, instalarApp } = usePwa()
const mostrarDicaIos = computed(() => !instalado.value && ehIosSafari())
async function instalar() {
  userMenuOpen.value = false
  await instalarApp()
}
const notificationsMenuOpen = ref(false)
const avatarLoadError = ref(false)
const podeConfigurar = computed(() => telaLiberada('configuracoes'))

const notificationItems = computed(() => {
  const items = []
  if (props.globalStats.qteTarefas > 0) {
    items.push({ key: 'tarefas', icon: 'bi-check2-square', label: 'Tarefas pendentes', count: props.globalStats.qteTarefas, hash: '#/tarefas' })
  }
  if (props.globalStats.qteDespesasNaoPagas > 0) {
    items.push({ key: 'despesas', icon: 'bi-arrow-up-circle', label: 'Despesas a pagar', count: props.globalStats.qteDespesasNaoPagas, hash: '#/despesas' })
  }
  if (props.globalStats.qteReceitasNaoRecebidas > 0) {
    items.push({ key: 'receitas', icon: 'bi-cash-coin', label: 'Receitas a receber', count: props.globalStats.qteReceitasNaoRecebidas, hash: '#/receitas' })
  }
  // A chave é a tela de destino: some o atalho de módulo sem acesso.
  return items.filter(item => telaLiberada(item.key))
})

const userInitials = computed(() => {
  if (!props.currentUser || !props.currentUser.username) return '?'
  return props.currentUser.username.substring(0, 2).toUpperCase()
})

function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value
  notificationsMenuOpen.value = false
}

function toggleNotificationsMenu() {
  notificationsMenuOpen.value = !notificationsMenuOpen.value
  userMenuOpen.value = false
}

function goToNotification(item) {
  notificationsMenuOpen.value = false
  window.location.hash = item.hash
}

watch(() => props.currentUser?.avatar, () => { avatarLoadError.value = false })

function onAvatarError() {
  avatarLoadError.value = true
}

function irPara(hash) {
  userMenuOpen.value = false
  window.location.hash = hash
}

async function handleLogout() {
  try {
    // Desvincula as notificações push deste aparelho antes de encerrar a sessão.
    await encerrarPushAoSair()
    await logout()
    emit('logout')
  } catch (error) {
    console.error('Logout failed:', error)
    // Even if server call fails, we should clear local state
    emit('logout')
  }
}

// Close menu when clicking outside
function handleClickOutside(event) {
  if (userMenuOpen.value && !event.target.closest('.user-menu-wrapper')) {
    userMenuOpen.value = false
  }
  if (notificationsMenuOpen.value && !event.target.closest('.notifications-menu-wrapper')) {
    notificationsMenuOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
})

// Badge Pulse Triggers
const pulseNotifications = ref(false)

watch(() => props.globalStats.notificacoes, (newVal, oldVal) => {
  if (oldVal !== undefined && newVal !== oldVal) {
    pulseNotifications.value = true
    setTimeout(() => { pulseNotifications.value = false }, 1800)
  }
})

</script>
 
<style scoped>
.pwa-dica-ios {
  align-items: flex-start;
  gap: 8px;
  font-size: .78rem;
  line-height: 1.35;
  color: var(--ink-muted);
  cursor: default;
  white-space: normal;
}
.pwa-dica-ios:hover { background: none; color: var(--ink-muted); }
.user-menu-wrapper {
  position: relative;
  margin-left: 8px;
}
.topbar-avatar-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid var(--border);
  padding: 0;
  overflow: hidden;
  cursor: pointer;
  background: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.topbar-avatar-btn:hover {
  border-color: var(--accent);
  transform: scale(1.05);
}
.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.avatar-initials {
  color: #fff;
  font-weight: 600;
  font-size: 0.85rem;
}
.user-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 220px;
  z-index: 1000;
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  animation: slideDown 0.2s ease;
}
.user-dropdown-header {
  padding: 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.user-info {
  display: flex;
  flex-direction: column;
}
.user-name {
  font-weight: 600;
  color: var(--ink);
  font-size: 0.9rem;
}
.user-email {
  font-size: 0.75rem;
  color: var(--ink-muted);
}
.user-dropdown-body {
  padding: 8px 0;
}
.dropdown-divider {
  height: 1px;
  background: var(--border);
  margin: 8px 0;
}
.logout-item {
  color: var(--danger) !important;
}
.logout-item:hover {
  background: #fef2f2 !important;
}

/* Messages Dropdown */
.messages-menu-wrapper {
  position: relative;
  margin-left: 8px;
}
.messages-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: -50px;
  width: 300px;
  z-index: 1000;
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  animation: slideDown 0.2s ease;
  background: var(--surface);
}
.messages-dropdown-header {
  padding: 12px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  font-size: 0.9rem;
}
.messages-dropdown-body {
  max-height: 300px;
  overflow-y: auto;
  padding: 0;
}
.message-item {
  display: flex;
  flex-direction: column;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
}
.message-item:last-child {
  border-bottom: none;
}
.message-sender {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--ink);
}
.message-subject {
  font-size: 0.8rem;
  color: var(--ink-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.messages-dropdown-footer {
  padding: 12px 16px;
  background: var(--surface);
  border-top: 1px solid var(--border);
}

/* Notifications Dropdown */
.notifications-menu-wrapper {
  position: relative;
}
.notifications-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: -50px;
  width: 300px;
  z-index: 1000;
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  animation: slideDown 0.2s ease;
  background: var(--surface);
}
.notifications-dropdown-header {
  padding: 12px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  font-size: 0.9rem;
}
.notifications-dropdown-body {
  max-height: 300px;
  overflow-y: auto;
  padding: 0;
}
.notification-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
}
.notification-item:last-child {
  border-bottom: none;
}
.notification-item i {
  color: var(--ink-muted);
}
.notification-label {
  flex: 1;
  font-size: 0.85rem;
  color: var(--ink);
}
.notification-count {
  background: var(--danger);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
  line-height: 1.5;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.menu-toggle-btn {
  display: flex;
}
.topbar-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: var(--danger);
  color: #fff;
  font-size: .62rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 10px;
  line-height: 1;
  border: 1.5px solid var(--card);
  min-width: 16px;
  text-align: center;
  z-index: 10;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
  transition: transform 0.2s ease;
}
@media (max-width: 768px) {
  .menu-toggle-btn {
    display: flex !important;
  }
}
</style>
