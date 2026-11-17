<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Balanço</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Balanço Consolidado</h1>
        <p>Acompanhamento de saldos e rateios na data de referência</p>
      </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="toolbar" style="margin-bottom: 15px;">
      <div class="toolbar-left" style="display:flex; gap:10px; align-items:center;">
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Data de Referência</label>
          <input type="date" class="form-control" v-model="filtro.dataReferencia" @change="fetchData" style="height:38px;">
        </div>
      </div>
      <div class="toolbar-right">
        <button class="btn btn-outline-primary" @click="fetchData">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <div>
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 3" :key="i">
            <div class="skeleton-line" style="width: 100%; height: 60px; margin-bottom: 10px;"></div>
          </div>
        </div>

        <div v-else-if="!balancoData" class="empty-state card">
          <i class="bi bi-bar-chart" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum dado encontrado</h4>
          <p>Não há registros de balanço para a data selecionada.</p>
        </div>

        <div v-else class="card" style="padding: 2rem;">
          <h4 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Resumo Geral</h4>
          <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
             <div style="flex: 1; min-width: 200px; background: var(--surface-hover); padding: 1.5rem; border-radius: 8px;">
               <h5 style="color: var(--ink-muted); margin-bottom: 0.5rem;">Saldo Total</h5>
               <h2 style="color: var(--success); margin: 0;">{{ formatCurrency(balancoData.saldo_total || 0) }}</h2>
             </div>
             <div style="flex: 1; min-width: 200px; background: var(--surface-hover); padding: 1.5rem; border-radius: 8px;">
               <h5 style="color: var(--ink-muted); margin-bottom: 0.5rem;">Rateio Total</h5>
               <h2 style="color: var(--warning); margin: 0;">{{ formatCurrency(balancoData.rateio_total || 0) }}</h2>
             </div>
             <div style="flex: 1; min-width: 200px; background: var(--surface-hover); padding: 1.5rem; border-radius: 8px;">
               <h5 style="color: var(--ink-muted); margin-bottom: 0.5rem;">Diferença</h5>
               <h2 :style="{ color: balancoData.diferenca !== 0 ? 'var(--danger)' : 'var(--success)', margin: 0 }">
                 {{ formatCurrency(balancoData.diferenca || 0) }}
               </h2>
             </div>
          </div>

          <div style="margin-top: 2rem; display: flex; gap: 2rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 300px;">
              <h5 style="border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem;">Saldos das Contas</h5>
              <ul class="widget-list">
                <li v-for="(conta, key) in balancoData.saldos_contas" :key="'c'+key" style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                  <span>{{ conta.conta_nome }}</span>
                  <strong :style="{ color: conta.saldo < 0 ? 'var(--danger)' : 'var(--success)' }">{{ formatCurrency(conta.saldo || 0) }}</strong>
                </li>
                <li v-if="!balancoData.saldos_contas || Object.keys(balancoData.saldos_contas).length === 0" style="color:var(--ink-muted);font-size:.82rem;">
                  Nenhuma conta encontrada.
                </li>
              </ul>
            </div>
            <div style="flex: 1; min-width: 300px;">
              <h5 style="border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem;">Rateios por Advogado</h5>
              <ul class="widget-list">
                <li v-for="(adv, key) in balancoData.rateios_advogados" :key="'a'+key" style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                  <span>{{ adv.nome }}</span>
                  <strong :style="{ color: adv.valor < 0 ? 'var(--danger)' : 'var(--success)' }">{{ formatCurrency(adv.valor || 0) }}</strong>
                </li>
                <li v-if="!balancoData.rateios_advogados || Object.keys(balancoData.rateios_advogados).length === 0" style="color:var(--ink-muted);font-size:.82rem;">
                  Nenhum rateio encontrado.
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-compass"></i> Navegação</h3>
          </div>
          <ul class="widget-list">
            <li>
              <a href="#/extrato" style="text-decoration: none; display: flex; align-items: center; width: 100%; color: var(--ink);">
                <i class="bi bi-file-text" style="margin-right: 8px;"></i> Extrato de Período
              </a>
            </li>
            <li>
              <a href="#/despesas" style="text-decoration: none; display: flex; align-items: center; width: 100%; color: var(--ink);">
                <i class="bi bi-tag" style="margin-right: 8px;"></i> Despesas
              </a>
            </li>
            <li>
              <a href="#/receitas" style="text-decoration: none; display: flex; align-items: center; width: 100%; color: var(--ink);">
                <i class="bi bi-wallet2" style="margin-right: 8px;"></i> Receitas
              </a>
            </li>
          </ul>
        </div>
        
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-lightbulb"></i> Entenda o Balanço</h3>
          </div>
          <div style="padding: 1rem; font-size: 0.85rem; color: var(--ink-muted); line-height: 1.5;">
            <p style="margin-bottom: 0.5rem;">
              <strong style="color: var(--ink);">Saldo Total:</strong> Soma de todas as contas cadastradas na data de referência.
            </p>
            <p style="margin-bottom: 0.5rem;">
              <strong style="color: var(--ink);">Rateio Total:</strong> Soma de todos os valores destinados aos advogados nesta data.
            </p>
            <p style="margin-bottom: 0;">
              <i class="bi bi-exclamation-triangle" style="color: var(--warning);"></i>
              <strong style="color: var(--ink);">Diferença:</strong> Se houver diferença, verifique transações ou saldos iniciais não rateados.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, formatCurrency } from '../../services/api'

const emit = defineEmits(['set-view', 'toast'])

const balancoData = ref(null)
const loading = ref(true)

const today = new Date().toISOString().split('T')[0]

const filtro = reactive({
  dataReferencia: today
})

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filtro.dataReferencia) params.append('dataReferencia', filtro.dataReferencia)

    // Using existing apiFetch, assuming endpoint exists or will exist
    const res = await apiFetch('financeiro/balanco?' + params.toString())
    if (res && res.success !== false) {
      // Se a API retornar o payload diretamente, usa o 'res' completo
      balancoData.value = res.data || res.balanco || (res.saldo_total !== undefined ? res : null)
      if (!balancoData.value) {
        throw new Error('Formato de resposta inválido ou sem dados.')
      }
    } else {
      // Fallback empty state if endpoint not ready
      balancoData.value = null
      throw new Error(res.message || 'Endpoint de balanço não disponível ainda.')
    }
  } catch (err) {
    console.error(err)
    // Silently fall back to empty state to avoid breaking UI while API is in dev
    balancoData.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
