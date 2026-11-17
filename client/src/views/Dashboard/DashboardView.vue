<template>
  <div class="page-content workspace-split">
    <div class="main-column" style="flex: 2; min-width: 0;">
      <Transition name="fade" mode="out-in">
        <!-- Loading State -->
        <div v-if="loading" class="empty-state" key="loading">
          <div class="spinner"></div>
          <p>Carregando dashboard...</p>
        </div>

        <div v-else key="content">
          <!-- Sec 1: KPIs -->
        <div class="dashboard-kpis mt-4">
          <div class="kpi-card" v-for="kpi in kpis" :key="kpi.title" :class="kpi.colorClass">
            <div class="kpi-icon">
              <i class="bi" :class="kpi.icon"></i>
            </div>
            <div class="kpi-details">
              <span class="kpi-title" :title="kpi.title">{{ kpi.title }}</span>
              <span class="kpi-value" :title="kpi.value">{{ kpi.value }}</span>
            </div>
          </div>
        </div>

        <!-- CRM Summary (Option A) -->
        <div class="section-card mt-4" v-if="verCrm && crmData">
          <div class="section-header">
            <div class="d-flex align-items-center">
              <i class="bi bi-bullseye text-primary me-2"></i>
              <h3 class="m-0 font-weight-bold">Resumo CRM</h3>
            </div>
            <button
              class="btn btn-sm btn-outline-primary"
              @click="navigateHash('#/crm')"
              title="Ver Pipeline Completo"
              aria-label="Ver Pipeline Completo"
            >
              Pipeline Completo
            </button>
          </div>
          <div class="section-body">
            <div class="crm-summary-grid">
              <!-- CRM KPIs -->
              <div class="crm-kpis">
                <div class="crm-kpi">
                  <span class="label">Oportunidades</span>
                  <span class="value">{{ crmData.totalOpportunities || 0 }}</span>
                </div>
                <div class="crm-kpi">
                  <span class="label">Taxa de Conversão</span>
                  <span class="value">{{ crmData.conversionRate || 0 }}%</span>
                </div>
                <div class="crm-kpi">
                  <span class="label">Ticket Médio</span>
                  <span class="value">{{ formatCurrency(crmData.averageTicket || 0) }}</span>
                </div>
                <div class="crm-kpi">
                  <span class="label">Valor em Pipeline</span>
                  <span class="value text-success">{{ formatCurrency(crmData.totalPipelineValue || 0) }}</span>
                </div>
              </div>

              <!-- Leads Chart -->
              <div class="crm-chart">
                <canvas ref="leadsChartCanvas"></canvas>
              </div>
            </div>
          </div>
        </div>

          <!-- Sec 2 & 3: Charts -->
          <div class="charts-grid mt-4">
            <!-- Financeiro -->
            <div class="section-card" v-if="verFinanceiro">
              <div class="section-header">
                <h3 class="m-0 font-weight-bold"><i class="bi bi-graph-up me-2"></i>Evolução Financeira</h3>
              </div>
              <div class="section-body p-3">
                <div class="chart-container" style="position: relative; height:250px;">
                  <canvas ref="financeChartCanvas"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Resultado por área -->
          <div class="section-card mt-4" v-if="verFinanceiro && dashboardData?.charts?.resultadoPorArea?.length">
            <div class="section-header">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-pie-chart-fill me-2"></i>Resultado por área</h3>
            </div>
            <div class="section-body p-3">
              <div class="chart-container" style="position: relative; height:320px;">
                <canvas ref="areaChartCanvas"></canvas>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Sidebar Widgets -->
    <div class="sidebar-widgets" style="flex: 1; width: auto; min-width: 0;">
      <!-- Ações Rápidas -->
      <div class="widget-card section-card">
        <div class="section-header">
          <h4 class="m-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Ações Rápidas</h4>
        </div>
        <div class="section-body p-2">
          <div class="quick-actions-grid">
            <button v-if="verClientes" class="quick-action-btn" @click="navigateHash('#/clientes/novo')" title="Cadastrar Novo Cliente" aria-label="Novo Cliente">
              <i class="bi bi-person-plus text-success"></i>
              <span>Novo Cliente</span>
            </button>
            <button v-if="verFinanceiro" class="quick-action-btn" @click="navigateHash('#/receitas/nova')" title="Cadastrar Nova Receita" aria-label="Nova Receita">
              <i class="bi bi-cash text-info"></i>
              <span>Nova Receita</span>
            </button>
            <button v-if="verFinanceiro" class="quick-action-btn" @click="navigateHash('#/despesas/nova')" title="Cadastrar Nova Despesa" aria-label="Nova Despesa">
              <i class="bi bi-receipt text-danger"></i>
              <span>Nova Despesa</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Resumo Financeiro -->
      <div class="widget-card section-card mt-3" v-if="verFinanceiro && dashboardData?.metrics">
        <div class="section-header">
          <h4 class="m-0"><i class="bi bi-wallet-fill text-primary me-2"></i>Resumo do Mês</h4>
        </div>
        <div class="section-body p-3">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Receitas</span>
            <span class="text-success fw-bold">{{ formatCurrency(dashboardData.metrics.receitasMes || 0) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Despesas</span>
            <span class="text-danger fw-bold">{{ formatCurrency(dashboardData.metrics.despesasMes || 0) }}</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between align-items-center">
            <span>Saldo Líquido</span>
            <span class="fw-bold fs-5" :class="saldoMes >= 0 ? 'text-success' : 'text-danger'">
              {{ formatCurrency(saldoMes) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Tarefas Pendentes (Movido para Sidebar) -->
      <div class="widget-card section-card mt-3" v-if="verTarefas">
        <div class="section-header">
          <div class="d-flex align-items-center">
            <i class="bi bi-check2-square text-warning me-2"></i>
            <h4 class="m-0 font-weight-bold">Tarefas Pendentes</h4>
            <span class="badge badge-warning ms-2" v-if="dashboardData?.tasks?.length">{{ dashboardData.tasks.length
              }}</span>
          </div>
        </div>
        <div class="section-body p-0">
          <div v-if="!dashboardData?.tasks?.length" class="empty-state p-4">
            <p class="m-0 text-muted">🎉 Nenhuma tarefa.</p>
          </div>
          <TransitionGroup
            v-else
            name="fade"
            tag="ul"
            class="widget-list"
            style="display: block; list-style: none; padding: 0; margin: 0;"
          >
            <li v-for="task in dashboardData.tasks" :key="task.id_tarefa" class="p-3 border-bottom"
              role="listitem"
              style="display: flex; flex-direction: row; align-items: flex-start; gap: 10px; list-style: none;">
              <div style="flex-shrink: 0;">
                <button
                  class="btn btn-sm btn-success-soft px-2 py-1"
                  @click="concluirTarefa(task.id_tarefa)"
                  title="Marcar como Concluída"
                  aria-label="Concluir tarefa"
                >
                  <i class="bi bi-check-lg"></i>
                </button>
              </div>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-bold mb-1" style="font-size: 0.85rem; word-break: break-word;">
                  <a href="#" @click.prevent="openTarefa(task)" class="text-decoration-none text-primary" title="Visualizar detalhes da tarefa">
                    {{ task.tarefa }}
                  </a>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                  <div class="small text-truncate" style="max-width: 65%;">
                    <a v-if="task.vinculo_tipo === 'cliente' || task.cliente_id" :href="'#/clientes/editar/' + task.cliente_id" class="text-muted text-decoration-none link-hover-primary" title="Acessar cliente">
                      <i class="bi bi-person me-1"></i>{{ task.vinculo_info }}
                    </a>
                  </div>
                  <span class="badge badge-tag" :class="getTaskStatusClass(task.statusPrazo)">{{ task.statusPrazo }}</span>
                </div>
              </div>
            </li>
          </TransitionGroup>
        </div>
      </div>
    </div>

    <!-- Tarefa Modal Overlay -->
    <TarefaModal 
      v-if="showTarefaModal"
      :task-data="selectedTarefa"
      @close="closeTarefaModal"
      @saved="loadData"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.5.37
 */
import { ref, computed, onMounted, nextTick } from 'vue'
import { apiFetch, formatCurrency } from '../../services/api'
import { liberado } from '../../composables/useAcesso'
import Chart from 'chart.js/auto'
import TarefaModal from '../../components/modals/Tarefas/TarefaModal.vue'

const emit = defineEmits(['set-view', 'toast'])

const loading = ref(true)
const dashboardData = ref(null)
const showTarefaModal = ref(false)
const selectedTarefa = ref(null)
const crmData = ref(null)

const financeChartCanvas = ref(null)
const leadsChartCanvas = ref(null)
const areaChartCanvas = ref(null)

let financeChart = null
let leadsChart = null
let areaChart = null

// Blocos do dashboard por módulo (mesmas regras das rotas da API).
const verFinanceiro = computed(() => liberado('financeiro', 'financeiro.access'))
const verClientes = computed(() => liberado('clientes', 'clientes.access'))
const verCrm = computed(() => liberado('crm', 'clientes.access'))
const verTarefas = computed(() => liberado('tarefas', 'tarefas.access'))

const kpis = computed(() => {
  if (!dashboardData.value?.metrics) return []
  const m = dashboardData.value.metrics
  return [
    ...(verClientes.value ? [
      { title: 'Clientes Ativos', value: m.clientesAtivos || 0, icon: 'bi-people-fill', colorClass: 'kpi-primary' },
      { title: 'Clientes Cadastrados', value: m.clientesTotal || 0, icon: 'bi-person-lines-fill', colorClass: 'kpi-success' },
    ] : []),
    ...(verFinanceiro.value ? [
      { title: 'Receitas (Mês)', value: formatCurrency(m.receitasMes || 0), icon: 'bi-cash-coin', colorClass: 'kpi-info' },
      { title: 'Despesas (Mês)', value: formatCurrency(m.despesasMes || 0), icon: 'bi-receipt-cutoff', colorClass: 'kpi-danger' },
    ] : []),
  ]
})

const saldoMes = computed(() => {
  if (!dashboardData.value?.metrics) return 0
  const m = dashboardData.value.metrics
  return (m.receitasMes || 0) - (m.despesasMes || 0)
})

function formatDateStr(dateStr) {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}/${m}/${y}`
}

function getTaskStatusClass(status) {
  switch (status?.toLowerCase()) {
    case 'urgente':
    case 'atrasada': return 'badge-danger'
    case 'hoje': return 'badge-warning'
    default: return 'badge-muted'
  }
}

function navigateHash(hash) {
  window.location.hash = hash
}

async function loadData() {
  loading.value = true
  try {
    const [dashRes, crmRes] = await Promise.all([
      apiFetch('dashboard').catch(() => null),
      // crm/dashboard responde 403 sem acesso ao CRM: nem chama.
      verCrm.value ? apiFetch('crm/dashboard').catch(() => null) : Promise.resolve(null)
    ])

    if (dashRes && dashRes.status === 'success') {
      dashboardData.value = dashRes.data
    } else if (dashRes && dashRes.data) {
      dashboardData.value = dashRes.data
    }

    crmData.value = crmRes?.data || crmRes || null

    loading.value = false
    setTimeout(() => {
      initCharts()
    }, 600)
  } catch (error) {
    console.error('Error loading dashboard data:', error)
    emit('toast', { msg: 'Erro ao carregar dashboard', type: 'danger' })
    loading.value = false
  }
}

async function openTarefa(task) {
  try {
    const res = await apiFetch('tarefas')
    const tarefasList = Array.isArray(res) ? res : (res.data || res.results || [])
    const fullTask = tarefasList.find(t => (t.id || t.id_tarefa) == task.id_tarefa)
    
    if (fullTask) {
      selectedTarefa.value = fullTask
    } else {
      selectedTarefa.value = task
    }
  } catch (e) {
    console.error('Erro ao buscar tarefa', e)
    selectedTarefa.value = task
  }
  showTarefaModal.value = true
}

function closeTarefaModal() {
  selectedTarefa.value = null
  showTarefaModal.value = false
}

async function concluirTarefa(id) {
  try {
    const fd = new FormData()
    fd.append('id', id)
    fd.append('status', '4') // concluído
    await apiFetch('tarefas/mudar-status', { method: 'POST', body: fd })

    if (dashboardData.value?.tasks) {
      dashboardData.value.tasks = dashboardData.value.tasks.filter(t => t.id_tarefa !== id)
    }
    emit('toast', { msg: 'Tarefa concluída com sucesso!' })
  } catch (e) {
    emit('toast', { msg: 'Erro ao concluir tarefa', type: 'danger' })
  }
}

function initCharts() {
  // Finance Chart
  if (financeChartCanvas.value && dashboardData.value?.charts) {
    const charts = dashboardData.value.charts
    const labels = charts.receitas?.map(d => d.mes) || []

    if (financeChart) financeChart.destroy()
    financeChart = new Chart(financeChartCanvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Receitas',
            data: charts.receitas?.map(d => parseFloat(d.receitas) || 0) || [],
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22, 163, 74, 0.1)',
            fill: true,
            tension: 0.4
          },
          {
            label: 'Despesas',
            data: charts.despesas?.map(d => parseFloat(d.despesas) || 0) || [],
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.1)',
            fill: true,
            tension: 0.4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    })
  }

  // Resultado por área
  if (areaChartCanvas.value && dashboardData.value?.charts?.resultadoPorArea?.length) {
    const CATEGORICAL_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948']
    const OUTROS_COLOR = '#898781'
    const MAX_SLICES = 8

    let porArea = dashboardData.value.charts.resultadoPorArea
      .map(r => ({ area: r.area, receitas: parseFloat(r.receitas) || 0 }))
      .filter(r => r.receitas > 0)
      .sort((a, b) => b.receitas - a.receitas)

    if (porArea.length > MAX_SLICES) {
      const principais = porArea.slice(0, MAX_SLICES - 1)
      const outros = porArea.slice(MAX_SLICES - 1).reduce((sum, r) => sum + r.receitas, 0)
      porArea = [...principais, { area: 'Outros', receitas: outros }]
    }

    const totalReceitas = porArea.reduce((sum, r) => sum + r.receitas, 0)
    const colors = porArea.map((r, i) => r.area === 'Outros' ? OUTROS_COLOR : CATEGORICAL_COLORS[i % CATEGORICAL_COLORS.length])

    if (areaChart) areaChart.destroy()
    areaChart = new Chart(areaChartCanvas.value, {
      type: 'pie',
      data: {
        labels: porArea.map(r => r.area),
        datasets: [{
          data: porArea.map(r => r.receitas),
          backgroundColor: colors
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const pct = totalReceitas > 0 ? (ctx.parsed / totalReceitas * 100).toFixed(1) : '0.0'
                return `${ctx.label}: ${formatCurrency(ctx.parsed)} (${pct}%)`
              }
            }
          }
        }
      }
    })
  }

  // CRM Leads Chart
  if (leadsChartCanvas.value && crmData.value?.leadsSource) {
    const leads = crmData.value.leadsSource || []
    const colors = ['#013B51', '#c8922a', '#16a34a', '#d97706', '#dc2626', '#3b82f6']

    if (leadsChart) leadsChart.destroy()
    leadsChart = new Chart(leadsChartCanvas.value, {
      type: 'doughnut',
      data: {
        labels: leads.map(l => l.nome || l.origem),
        datasets: [{
          data: leads.map(l => l.total),
          backgroundColor: colors.slice(0, leads.length)
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { position: 'right' }
        }
      }
    })
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>

.dashboard-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  background: var(--card);
  border-radius: var(--radius);
  padding: 20px;
  display: flex;
  align-items: center;
  box-shadow: var(--shadow);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  position: relative;
  overflow: hidden;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 16px;
  flex-shrink: 0;
}

.kpi-primary .kpi-icon {
  background: rgba(1, 59, 81, 0.1);
  color: var(--primary);
}

.kpi-success .kpi-icon {
  background: rgba(22, 163, 74, 0.1);
  color: var(--success);
}

.kpi-info .kpi-icon {
  background: rgba(59, 130, 246, 0.1);
  color: var(--info);
}

.kpi-danger .kpi-icon {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger);
}

.kpi-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.kpi-title {
  font-size: 11px;
  color: var(--ink-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.crm-summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  padding: 20px;
}

.crm-kpis {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.crm-kpi {
  background: var(--surface);
  padding: 16px;
  border-radius: var(--radius);
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.crm-kpi .label {
  font-size: 12px;
  color: var(--ink-muted);
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 4px;
}

.crm-kpi .value {
  font-size: 20px;
  font-weight: 700;
}

.crm-chart {
  position: relative;
  height: 200px;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.quick-action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 12px;
  border-radius: var(--radius);
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-action-btn:hover {
  background: var(--card);
  border-color: var(--primary);
  box-shadow: var(--shadow);
}

.quick-action-btn i {
  font-size: 20px;
}

.quick-action-btn span {
  font-size: 12px;
  font-weight: 500;
  color: var(--ink);
}

@media (max-width: 1024px) {
  .dashboard-kpis {
    grid-template-columns: 1fr 1fr;
  }

  .charts-grid {
    grid-template-columns: 1fr;
  }

  .crm-summary-grid {
    grid-template-columns: 1fr;
  }
}

.link-hover-primary {
  transition: color 0.2s ease;
}
.link-hover-primary:hover {
  color: var(--primary) !important;
}
</style>
