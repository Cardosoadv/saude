<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Extrato Financeiro</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Extrato Financeiro</h1>
        <p>Acompanhamento de movimentações e saldo</p>
      </div>
      <div style="display:flex; gap:10px;">
        <button v-if="podeAnexar" class="btn btn-outline-primary" :disabled="!selecionados.length" @click="abrirLote(selecionados)">
          <i class="bi bi-paperclip"></i> Anexar comprovante<template v-if="selecionados.length"> ({{ selecionados.length }})</template>
        </button>
        <button class="btn btn-success" @click="exportarExtrato('pdf')">
          <i class="bi bi-printer"></i> Imprimir
        </button>
      </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="toolbar" style="margin-bottom: 15px;">
      <div class="toolbar-left" style="display:flex; gap:10px; align-items:center;">
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Data Inicial</label>
          <input type="date" class="form-control" v-model="filtro.dataInicial" @change="fetchData" style="height:38px;">
        </div>
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Data Final</label>
          <input type="date" class="form-control" v-model="filtro.dataFinal" @change="fetchData" style="height:38px;">
        </div>
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Conta</label>
          <select class="form-control form-select" v-model="filtro.conta_id" @change="fetchData" style="height:38px; min-width: 150px;">
            <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">{{ c.conta }}</option>
          </select>
        </div>
      </div>
      <div class="toolbar-right">
        <button class="btn btn-outline-success" @click="openRelatorioCategorias" style="margin-right: 10px;">
          <i class="bi bi-diagram-3"></i> Relatório por Categorias
        </button>
        <button class="btn btn-outline-primary" @click="fetchData">
          <i class="bi bi-search"></i> Filtrar
        </button>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <div>
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 4" :key="i">
            <div class="skeleton-line" style="width: 100%; height: 40px; margin-bottom: 5px;"></div>
          </div>
        </div>

        <div v-else-if="!extrato.length" class="empty-state card">
          <i class="bi bi-wallet2" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum movimento encontrado</h4>
          <p>Não há registros para o período selecionado.</p>
        </div>

        <div v-else class="card" style="overflow-x: auto; padding: 0;">
          <table class="table table-striped table-hover" style="width: 100%; margin: 0; border-collapse: collapse;">
            <thead style="background-color: var(--surface-hover); border-bottom: 2px solid var(--border);">
              <tr>
                <th v-if="podeAnexar" style="padding: 12px 8px; width: 32px;"></th>
                <th style="padding: 12px 15px; text-align: left;">Data</th>
                <th style="padding: 12px 15px; text-align: left;">Descrição</th>
                <th style="padding: 12px 15px; text-align: left;">Cliente / Fornecedor</th>
                <th style="padding: 12px 15px; text-align: right;">Valor</th>
                <th style="padding: 12px 15px; text-align: right;">Saldo</th>
                <th style="padding: 12px 15px; text-align: center;" title="Comprovantes">Comprov.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(registro, idx) in extrato" :key="idx" style="border-bottom: 1px solid var(--border);">
                <td v-if="podeAnexar" style="padding: 12px 8px;">
                  <input v-if="ehPagamento(registro)" type="checkbox" :checked="estaSelecionado(registro)" @change="alternar(registro)">
                </td>
                <td style="padding: 12px 15px;">{{ formatDate(registro.data) }}</td>
                <td style="padding: 12px 15px;">{{ registro.descricao }}</td>
                <td style="padding: 12px 15px;">{{ registro.cliente_fornecedor }}</td>
                <td style="padding: 12px 15px; text-align: right;" :style="{ color: registro.valor < 0 ? 'var(--danger)' : 'var(--success)' }">
                  {{ formatCurrency(registro.valor) }}
                </td>
                <td style="padding: 12px 15px; text-align: right; font-weight: bold;">
                  {{ formatCurrency(registro.saldo) }}
                </td>
                <td style="padding: 12px 15px; text-align: center; white-space: nowrap;">
                  <template v-if="ehPagamento(registro)">
                    <span v-if="Number(registro.total_comprovantes) > 0" class="extrato-clip" title="Comprovantes anexados">
                      <i class="bi bi-paperclip"></i> {{ registro.total_comprovantes }}
                    </span>
                    <button v-if="podeAnexar" type="button" class="btn btn-ghost btn-sm" title="Anexar comprovante" @click="abrirLote([registro])">
                      <i class="bi bi-plus-circle" style="color:var(--accent);"></i>
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-file-earmark-arrow-down"></i> Exportar</h3>
          </div>
          <div class="card-body" style="display:flex; flex-direction:column; gap:8px;">
            <button class="btn btn-outline-secondary" @click="exportarExtrato('pdf')" style="text-align: left;">
              <i class="bi bi-file-pdf text-danger"></i> Imprimir em PDF
            </button>
            <button class="btn btn-outline-secondary" @click="exportarExtrato('pdf_rateio')" style="text-align: left;">
              <i class="bi bi-file-pdf text-danger"></i> Imprimir PDF (com Rateio)
            </button>
            <button class="btn btn-outline-secondary" @click="exportarExtrato('csv')" style="text-align: left;">
              <i class="bi bi-filetype-csv text-success"></i> Exportar em CSV
            </button>
          </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-pie-chart"></i> Rateio Acumulado</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!Object.keys(rateioDados).length" style="color:var(--ink-muted);font-size:.82rem;">Nenhum dado de rateio.</li>
            <li v-for="(item, key) in rateioDados" :key="key">
              <div style="display:flex; justify-content:space-between; width:100%;">
                <span class="process-num">{{ item.nome }}</span>
                <span class="process-date" :style="{ color: item.rateio_acumulado < 0 ? 'var(--danger)' : 'var(--success)', fontWeight: 'bold' }">
                  {{ formatCurrency(item.rateio_acumulado) }}
                </span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <ComprovanteLoteModal
      v-if="lote.length"
      :pagamentos="lote.map((r) => ({ tipo: r.tipo, id: r.id }))"
      @close="lote = []"
      @enviado="fetchData"
      @toast="(t) => emit('toast', t)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import ComprovanteLoteModal from '../../components/Financeiro/ComprovanteLoteModal.vue'
import { pode } from '../../composables/useAcesso'
import { BASE_URL, apiFetch, formatDate, formatCurrency } from '../../services/api'

const emit = defineEmits(['set-view', 'toast'])

const extrato = ref([])
const rateioDados = ref<Record<string, any>>({})
const loading = ref(true)
const contas = ref([])

// Anexo de comprovantes: só despesas e receitas têm comprovante (transferências não).
const podeAnexar = computed(() => pode('financeiro.edit', 'financeiro.create'))
const marcados = ref<Record<string, any>>({})
const lote = ref<any[]>([])
const selecionados = computed(() => Object.values(marcados.value))
const chave = (r: any) => `${r.tipo}:${r.id}`
const ehPagamento = (r: any) => (r.tipo === 'despesa' || r.tipo === 'receita') && !!r.id
const estaSelecionado = (r: any) => chave(r) in marcados.value
function alternar(r: any) {
  const k = chave(r)
  if (k in marcados.value) delete marcados.value[k]
  else marcados.value[k] = r
}
function abrirLote(registros: any[]) {
  lote.value = registros
}

const today = new Date()
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0]

const filtro = reactive({
  conta_id: 1,
  dataInicial: firstDay,
  dataFinal: lastDay
})

async function fetchData() {
  loading.value = true
  marcados.value = {}
  try {
    const params = new URLSearchParams()
    if (filtro.conta_id) params.append('conta_id', String(filtro.conta_id))
    if (filtro.dataInicial) params.append('dataInicial', filtro.dataInicial)
    if (filtro.dataFinal) params.append('dataFinal', filtro.dataFinal)

    const res = await apiFetch('financeiro/extrato?' + params.toString())
    if (res && res.success !== false) {
      extrato.value = res.transacoes || res.data || []
      rateioDados.value = res.rateio_acumulado || {}
    } else {
      throw new Error(res.message || 'Erro ao carregar extrato.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Não foi possível carregar o extrato.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

function exportarExtrato(tipo) {
  const params = new URLSearchParams()
  params.append('export', tipo)
  if (filtro.conta_id) params.append('conta_id', String(filtro.conta_id))
  if (filtro.dataInicial) params.append('dataInicial', filtro.dataInicial)
  if (filtro.dataFinal) params.append('dataFinal', filtro.dataFinal)

  const url = `${BASE_URL}financeiro/extrato?${params.toString()}`
  window.open(url, '_blank')
}

function openRelatorioCategorias() {
  sessionStorage.setItem('extratoFilters', JSON.stringify(filtro))
  emit('set-view', 'relatorio-categorias')
}

async function fetchContas() {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    if (res && res.success) {
      contas.value = res.data || []
      if (contas.value.length > 0 && !contas.value.some(c => c.id_conta == filtro.conta_id)) {
        filtro.conta_id = contas.value[0].id_conta
      }
    }
  } catch(e) { console.error('Erro contas:', e) }
}

onMounted(async () => {
  await fetchContas()
  fetchData()
})
</script>

<style scoped>
.extrato-clip { color: var(--ink-muted); font-size: .85rem; margin-right: 4px; }
</style>
