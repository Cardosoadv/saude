<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Previsão de Fluxo de Caixa</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Previsão de Fluxo de Caixa</h1>
        <p>Projeção de saldo e detecção de anomalias em despesas e receitas</p>
      </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="toolbar" style="margin-bottom: 15px;">
      <div class="toolbar-left" style="display:flex; gap:10px; align-items:center;">
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Conta</label>
          <select class="form-control form-select" v-model="filtro.conta_id" @change="fetchData" style="height:38px; min-width: 180px;">
            <option :value="null">Consolidado (todas as contas)</option>
            <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">{{ c.conta }}</option>
          </select>
        </div>
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Meses a Projetar</label>
          <select class="form-control form-select" v-model.number="filtro.meses_projecao" @change="fetchData" style="height:38px;">
            <option :value="3">3 meses</option>
            <option :value="6">6 meses</option>
          </select>
        </div>
      </div>
      <div class="toolbar-right">
        <button class="btn btn-outline-primary" @click="fetchData" title="Atualizar Dados" aria-label="Atualizar Dados">
          <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
      </div>
    </div>

    <Transition name="fade" mode="out-in" @after-enter="onContentEnter">
      <div v-if="loading" class="empty-state mt-4" key="loading">
        <div class="spinner"></div>
        <p>Calculando a previsão de fluxo de caixa...</p>
      </div>

      <div v-else-if="loadError" class="empty-state card" key="error">
        <i class="bi bi-exclamation-triangle" style="font-size:3rem;color:var(--warning);"></i>
        <h4>Não foi possível carregar a previsão</h4>
        <p>{{ loadError }}</p>
      </div>

      <div v-else key="content">
        <!-- KPIs -->
        <div class="dashboard-kpis mt-4">
          <div class="kpi-card kpi-primary">
            <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div class="kpi-info-text">
              <span class="kpi-title">Saldo Atual</span>
              <span class="kpi-value">{{ formatCurrency(saldoAtual) }}</span>
            </div>
          </div>
          <div class="kpi-card" :class="tendenciaClasse">
            <div class="kpi-icon"><i :class="tendenciaIcone"></i></div>
            <div class="kpi-info-text">
              <span class="kpi-title">Saldo Projetado ({{ previsao.meses_projecao || 0 }}m)</span>
              <span class="kpi-value">{{ formatCurrency(saldoProjetadoFinal) }}</span>
            </div>
          </div>
          <div class="kpi-card" :class="tendenciaClasse">
            <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-info-text">
              <span class="kpi-title">Tendência</span>
              <span class="kpi-value">{{ tendenciaLabel }}</span>
            </div>
          </div>
          <div class="kpi-card" :class="anomalias.length ? 'kpi-danger' : 'kpi-info'">
            <div class="kpi-icon"><i class="bi bi-exclamation-diamond"></i></div>
            <div class="kpi-info-text">
              <span class="kpi-title">Anomalias Detectadas</span>
              <span class="kpi-value">{{ anomalias.length }}</span>
            </div>
          </div>
        </div>

        <div v-if="previsao.saldo_inicial_estimado" class="alert alert-info" style="margin-top: 1rem; font-size: 0.85rem; padding: 0.85rem 1rem; border-radius: 8px; background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
          <i class="bi bi-info-circle" style="margin-right: 0.5rem;"></i>
          Nenhum fechamento mensal encontrado para este período: o saldo exibido é uma estimativa relativa, calculada a partir da movimentação de receitas e despesas.
        </div>

        <!-- Chart -->
        <div class="section-card mt-4">
          <div class="section-header">
            <h3 class="m-0 font-weight-bold"><i class="bi bi-graph-up me-2"></i>Saldo Projetado vs. Realizado</h3>
          </div>
          <div class="section-body p-3">
            <div class="chart-container" style="position: relative; height:320px;">
              <canvas ref="previsaoCanvas"></canvas>
            </div>
          </div>
        </div>

        <!-- Anomalias -->
        <div class="section-card mt-4">
          <div class="section-header">
            <h3 class="m-0 font-weight-bold"><i class="bi bi-exclamation-diamond me-2"></i>Alertas de Anomalias</h3>
          </div>
          <div class="section-body p-0">
            <div v-if="!anomalias.length" class="empty-state" style="padding: 2rem;">
              <i class="bi bi-check2-circle" style="font-size:2.5rem;color:var(--success);"></i>
              <p>Nenhum lançamento fora do padrão foi detectado no período analisado.</p>
            </div>
            <ul v-else class="widget-list">
              <li v-for="anomalia in anomalias" :key="anomalia.tipo + '-' + anomalia.id" style="display:flex; justify-content:space-between; align-items:flex-start; gap: 1rem; padding: 0.85rem 1rem;">
                <div style="display:flex; gap: 0.75rem; align-items:flex-start;">
                  <i
                    class="bi"
                    :class="anomalia.severidade === 'alta' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-info-circle-fill text-warning'"
                    style="font-size: 1.1rem; margin-top: 2px;"
                  ></i>
                  <div>
                    <div style="font-weight: 600; color: var(--ink);">{{ anomalia.descricao }}</div>
                    <div style="font-size: 0.8rem; color: var(--ink-muted);">
                      {{ anomalia.tipo === 'despesa' ? 'Despesa' : 'Receita' }} &middot; {{ anomalia.categoria_nome }} &middot; {{ formatDate(anomalia.data) }}
                    </div>
                    <div style="font-size: 0.78rem; color: var(--ink-muted);">
                      Média da categoria: {{ formatCurrency(anomalia.media_categoria) }} &middot; z-score: {{ anomalia.z_score }}
                    </div>
                  </div>
                </div>
                <div style="text-align:right; white-space:nowrap;">
                  <div style="font-weight: 700; color: var(--ink);">{{ formatCurrency(anomalia.valor) }}</div>
                  <span
                    class="badge"
                    :style="{ background: anomalia.severidade === 'alta' ? 'var(--danger)' : 'var(--warning)', color: '#fff' }"
                  >{{ anomalia.severidade === 'alta' ? 'Severidade Alta' : 'Severidade Média' }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, formatCurrency, formatDate } from '../../services/api'
import Chart from 'chart.js/auto'

const emit = defineEmits(['set-view', 'toast'])

const loading = ref(true)
const loadError = ref('')
const contas = ref([])

const previsao = ref({
  historico: [],
  projecao: [],
  tendencia: { inclinacao_mensal: 0, r2: 0, direcao: 'estavel' },
  saldo_inicial_estimado: false,
  meses_projecao: 6,
})
const anomalias = ref([])

const filtro = reactive({
  conta_id: null,
  meses_projecao: 6,
})

const previsaoCanvas = ref(null)
let previsaoChart = null

const saldoAtual = computed(() => {
  const historico = previsao.value.historico || []
  if (!historico.length) return 0
  return parseFloat(historico[historico.length - 1].saldo_acumulado) || 0
})

const saldoProjetadoFinal = computed(() => {
  const projecao = previsao.value.projecao || []
  if (!projecao.length) return saldoAtual.value
  return parseFloat(projecao[projecao.length - 1].saldo_projetado) || 0
})

const tendenciaLabel = computed(() => {
  const direcao = previsao.value.tendencia?.direcao
  if (direcao === 'alta') return 'Crescimento'
  if (direcao === 'queda') return 'Retração'
  return 'Estável'
})

const tendenciaClasse = computed(() => {
  const direcao = previsao.value.tendencia?.direcao
  if (direcao === 'alta') return 'kpi-success'
  if (direcao === 'queda') return 'kpi-danger'
  return 'kpi-info'
})

const tendenciaIcone = computed(() => {
  const direcao = previsao.value.tendencia?.direcao
  if (direcao === 'alta') return 'bi bi-graph-up-arrow'
  if (direcao === 'queda') return 'bi bi-graph-down-arrow'
  return 'bi bi-dash-lg'
})

async function fetchContas() {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    contas.value = res?.data || res || []
  } catch (error) {
    console.error('Erro ao carregar contas:', error)
  }
}

async function fetchData() {
  loading.value = true
  loadError.value = ''
  try {
    const params = new URLSearchParams()
    if (filtro.conta_id) params.append('conta_id', filtro.conta_id)
    params.append('meses_projecao', String(filtro.meses_projecao))

    const res = await apiFetch('financeiro/previsao-fluxo-caixa/dashboard?' + params.toString())
    const data = res?.data || res || {}

    previsao.value = data.previsao || previsao.value
    anomalias.value = data.anomalias || []

    loading.value = false
  } catch (error) {
    console.error('Erro ao carregar previsão de fluxo de caixa:', error)
    loadError.value = 'Ocorreu um erro ao calcular a previsão. Tente novamente em instantes.'
    loading.value = false
    emit('toast', { msg: 'Erro ao carregar a previsão de fluxo de caixa', type: 'danger' })
  }
}

function onContentEnter() {
  // O gráfico só existe dentro do bloco "content"; ignora a transição do bloco "loading".
  if (!loading.value) renderChart()
}

function renderChart() {
  if (previsaoChart) {
    previsaoChart.destroy()
    previsaoChart = null
  }
  if (!previsaoCanvas.value) return

  const historico = previsao.value.historico || []
  const projecao = previsao.value.projecao || []

  const labels = [...historico.map(h => h.label), ...projecao.map(p => p.label)]

  const realizado = [
    ...historico.map(h => parseFloat(h.saldo_acumulado) || 0),
    ...projecao.map(() => null),
  ]

  // Conecta a linha projetada ao último ponto realizado, para continuidade visual.
  const projetado = [
    ...historico.map(() => null),
    ...projecao.map(p => parseFloat(p.saldo_projetado) || 0),
  ]
  if (historico.length) {
    projetado[historico.length - 1] = realizado[historico.length - 1]
  }

  previsaoChart = new Chart(previsaoCanvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Saldo Realizado',
          data: realizado,
          borderColor: '#013B51',
          backgroundColor: 'rgba(1, 59, 81, 0.1)',
          fill: true,
          tension: 0.3,
          spanGaps: false,
        },
        {
          label: 'Saldo Projetado',
          data: projetado,
          borderColor: '#c8922a',
          backgroundColor: 'rgba(200, 146, 42, 0.1)',
          borderDash: [6, 4],
          fill: false,
          tension: 0.3,
          spanGaps: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`,
          },
        },
      },
      scales: {
        y: {
          ticks: {
            callback: (value) => formatCurrency(value),
          },
        },
      },
    },
  })
}

onMounted(() => {
  fetchContas()
  fetchData()
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
  border-left: 4px solid transparent;
}
.kpi-primary { border-left-color: var(--primary); }
.kpi-info { border-left-color: var(--info); }
.kpi-success { border-left-color: var(--success); }
.kpi-warning { border-left-color: var(--warning); }
.kpi-danger { border-left-color: var(--danger); }
.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 16px;
}
.kpi-primary .kpi-icon { background: rgba(1, 59, 81, 0.1); color: var(--primary); }
.kpi-info .kpi-icon { background: rgba(59, 130, 246, 0.1); color: var(--info); }
.kpi-success .kpi-icon { background: rgba(22, 163, 74, 0.1); color: var(--success); }
.kpi-warning .kpi-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.kpi-danger .kpi-icon { background: rgba(220, 38, 38, 0.1); color: var(--danger); }
.kpi-info-text { display: flex; flex-direction: column; }
.kpi-title { font-size: 13px; color: var(--ink-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
.kpi-value { font-size: 20px; font-weight: 700; color: var(--ink); line-height: 1.2; }

.widget-list { list-style: none; margin: 0; padding: 0; }
.widget-list li:not(:last-child) { border-bottom: 1px solid var(--border); }

@media (max-width: 1024px) {
  .dashboard-kpis { grid-template-columns: 1fr 1fr; }
}
</style>
