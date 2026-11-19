<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>DRE</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Demonstrativo de Resultados</h1>
        <p>Acompanhamento de receitas, despesas e lucro/prejuízo</p>
      </div>
      <button class="btn btn-danger" @click="exportarPDF">
        <i class="bi bi-file-pdf"></i> Exportar PDF
      </button>
    </div>

    <!-- Toolbar Filters -->
    <div class="toolbar" style="margin-bottom: 15px;">
      <div class="toolbar-left" style="display:flex; gap:10px; align-items:center;">
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Data Início</label>
          <input type="date" class="form-control" v-model="filtro.data_inicio" @change="fetchData" style="height:38px;">
        </div>
        <div style="display:flex; flex-direction:column;">
          <label style="font-size: 0.8rem; font-weight: bold; margin-bottom:2px;">Data Fim</label>
          <input type="date" class="form-control" v-model="filtro.data_fim" @change="fetchData" style="height:38px;">
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

        <div v-else-if="!dreData" class="empty-state card">
          <i class="bi bi-bar-chart-line" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum dado encontrado</h4>
          <p>Não há registros de DRE para o período selecionado.</p>
        </div>

        <div v-else class="card" style="padding: 2rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
             <h5 style="margin: 0; color: var(--success);">{{ dreData.receita_bruta?.titulo || 'RECEITA OPERACIONAL BRUTA' }}</h5>
             <h5 style="margin: 0; color: var(--success);">{{ formatCurrency(dreData.receita_bruta?.valor || 0) }}</h5>
          </div>
          <div v-if="filteredItens(dreData.receita_bruta?.itens).length" style="margin-bottom: 1rem; font-size: 0.85rem; padding-left: 1rem; border-left: 2px solid var(--border);">
            <div v-for="(item, idx) in filteredItens(dreData.receita_bruta.itens)" :key="'rb'+idx" style="display:flex; justify-content:space-between; padding: 0.2rem 0; color: var(--ink-muted);">
              <span>{{ item.categoria || item.descricao || item.nome }}</span>
              <span>{{ formatCurrency(itemValor(item)) }}</span>
            </div>
          </div>
          
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
             <h5 style="margin: 0; color: var(--danger);">{{ dreData.deducoes?.titulo || '(-) DEDUÇÕES DA RECEITA BRUTA' }}</h5>
             <h5 style="margin: 0; color: var(--danger);">{{ formatCurrency(dreData.deducoes?.valor || 0) }}</h5>
          </div>
          <div v-if="filteredItens(dreData.deducoes?.itens).length" style="margin-bottom: 1rem; font-size: 0.85rem; padding-left: 1rem; border-left: 2px solid var(--border);">
            <div v-for="(item, idx) in filteredItens(dreData.deducoes.itens)" :key="'d'+idx" style="display:flex; justify-content:space-between; padding: 0.2rem 0; color: var(--ink-muted);">
              <span>{{ item.categoria || item.descricao || item.nome }}</span>
              <span>{{ formatCurrency(itemValor(item)) }}</span>
            </div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; background: var(--surface-hover); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
             <strong style="margin: 0; color: var(--ink);">{{ dreData.receita_liquida?.titulo || '(=) RECEITA OPERACIONAL LÍQUIDA' }}</strong>
             <strong style="margin: 0; color: var(--ink);">{{ formatCurrency(dreData.receita_liquida?.valor || 0) }}</strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
             <h5 style="margin: 0; color: var(--danger);">{{ dreData.despesas_operacionais?.titulo || '(-) DESPESAS OPERACIONAIS' }}</h5>
             <h5 style="margin: 0; color: var(--danger);">{{ formatCurrency(dreData.despesas_operacionais?.valor || 0) }}</h5>
          </div>
          <div v-if="filteredItens(dreData.despesas_operacionais?.itens).length" style="margin-bottom: 1rem; font-size: 0.85rem; padding-left: 1rem; border-left: 2px solid var(--border);">
            <div v-for="(item, idx) in filteredItens(dreData.despesas_operacionais.itens)" :key="'do'+idx" style="display:flex; justify-content:space-between; padding: 0.2rem 0; color: var(--ink-muted);">
              <span>{{ item.categoria || item.descricao || item.nome }}</span>
              <span>{{ formatCurrency(itemValor(item)) }}</span>
            </div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; background: var(--surface-hover); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
             <strong style="margin: 0; color: var(--ink);">{{ dreData.resultado_operacional?.titulo || '(=) RESULTADO OPERACIONAL (EBITDA)' }}</strong>
             <strong style="margin: 0; color: var(--ink);">{{ formatCurrency(dreData.resultado_operacional?.valor || 0) }}</strong>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
             <h5 style="margin: 0; color: var(--warning);">{{ dreData.nao_operacional?.titulo || '(+/-) RESULTADO NÃO OPERACIONAL' }}</h5>
             <h5 style="margin: 0; color: var(--warning);">{{ formatCurrency(dreData.nao_operacional?.valor || 0) }}</h5>
          </div>
          <div v-if="filteredItens(dreData.nao_operacional?.itens).length" style="margin-bottom: 1rem; font-size: 0.85rem; padding-left: 1rem; border-left: 2px solid var(--border);">
            <div v-for="(item, idx) in filteredItens(dreData.nao_operacional.itens)" :key="'no'+idx" style="display:flex; justify-content:space-between; padding: 0.2rem 0; color: var(--ink-muted);">
              <span>{{ item.categoria || item.descricao || item.nome }}</span>
              <span>{{ formatCurrency(itemValor(item)) }}</span>
            </div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; background: var(--primary); color: white; padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;">
             <h3 style="margin: 0; color: white;"><i class="bi bi-trophy me-2"></i> {{ dreData.resultado_liquido?.titulo || '(=) RESULTADO LÍQUIDO DO EXERCÍCIO' }}</h3>
             <h3 style="margin: 0; color: white;">
               {{ formatCurrency(dreData.resultado_liquido?.valor || 0) }}
             </h3>
          </div>
        </div>

        <div class="alert alert-info" style="margin-top: 1.5rem; font-size: 0.9rem; padding: 1rem; border-radius: 8px; background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
          <i class="bi bi-info-circle" style="margin-right: 0.5rem;"></i>
          <strong>DRE - Demonstrativo de Resultados do Exercício:</strong> Este relatório apresenta de forma resumida as receitas e despesas da empresa, resultando no lucro ou prejuízo do período selecionado.
        </div>
      </div>
      
      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-funnel"></i> Filtros Rápidos</h3>
          </div>
          <ul class="widget-list">
            <li role="button" tabindex="0" @click="setPeriodoMesAtual" @keydown.enter="setPeriodoMesAtual" @keydown.space.prevent="setPeriodoMesAtual">
              <a href="#" @click.prevent style="text-decoration: none; display: flex; justify-content: space-between; width: 100%; color: var(--ink);" tabindex="-1">
                <span>Este Mês</span> <i class="bi bi-calendar-event" style="color: var(--ink-muted);"></i>
              </a>
            </li>
            <li role="button" tabindex="0" @click="setPeriodoMesPassado" @keydown.enter="setPeriodoMesPassado" @keydown.space.prevent="setPeriodoMesPassado">
              <a href="#" @click.prevent style="text-decoration: none; display: flex; justify-content: space-between; width: 100%; color: var(--ink);" tabindex="-1">
                <span>Mês Passado</span> <i class="bi bi-calendar-minus" style="color: var(--ink-muted);"></i>
              </a>
            </li>
            <li role="button" tabindex="0" @click="setPeriodoAnoAtual" @keydown.enter="setPeriodoAnoAtual" @keydown.space.prevent="setPeriodoAnoAtual">
              <a href="#" @click.prevent style="text-decoration: none; display: flex; justify-content: space-between; width: 100%; color: var(--ink);" tabindex="-1">
                <span>Este Ano</span> <i class="bi bi-calendar3" style="color: var(--ink-muted);"></i>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Legenda</h3>
          </div>
          <ul class="widget-list">
            <li style="display: flex; align-items: center; gap: 8px; color: var(--ink); cursor: default;">
              <i class="bi bi-arrow-up-circle text-success" style="font-size: 1.2rem;"></i> <span>Receitas</span>
            </li>
            <li style="display: flex; align-items: center; gap: 8px; color: var(--ink); cursor: default;">
              <i class="bi bi-arrow-down-circle text-danger" style="font-size: 1.2rem;"></i> <span>Deduções / Despesas</span>
            </li>
            <li style="display: flex; align-items: center; gap: 8px; color: var(--ink); cursor: default;">
              <i class="bi bi-trophy text-primary" style="font-size: 1.2rem;"></i> <span>Resultado / Totais</span>
            </li>
          </ul>
        </div>

        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-gear"></i> Ações</h3>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem;">
             <button class="btn btn-danger w-100" @click="exportarPDF">
               <i class="bi bi-file-pdf"></i> Exportar PDF
             </button>
             <button class="btn btn-outline-info w-100" @click="$emit('set-view', 'extrato')">
               <i class="bi bi-file-text"></i> Ver Extrato
             </button>
             <button class="btn btn-outline-secondary w-100" @click="$emit('set-view', 'balanco')">
               <i class="bi bi-arrow-left"></i> Voltar ao Balanço
             </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, BASE_URL, formatCurrency } from '../../services/api'

const emit = defineEmits(['set-view', 'toast'])

const dreData = ref(null)
const loading = ref(true)

const today = new Date()
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0]

const filtro = reactive({
  data_inicio: firstDay,
  data_fim: lastDay
})

function setPeriodoMesAtual() {
  const d = new Date()
  filtro.data_inicio = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
  filtro.data_fim = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
  fetchData()
}

function setPeriodoMesPassado() {
  const d = new Date()
  filtro.data_inicio = new Date(d.getFullYear(), d.getMonth() - 1, 1).toISOString().split('T')[0]
  filtro.data_fim = new Date(d.getFullYear(), d.getMonth(), 0).toISOString().split('T')[0]
  fetchData()
}

function setPeriodoAnoAtual() {
  const d = new Date()
  filtro.data_inicio = new Date(d.getFullYear(), 0, 1).toISOString().split('T')[0]
  filtro.data_fim = new Date(d.getFullYear(), 11, 31).toISOString().split('T')[0]
  fetchData()
}

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filtro.data_inicio) params.append('data_inicio', filtro.data_inicio)
    if (filtro.data_fim) params.append('data_fim', filtro.data_fim)

    const res = await apiFetch('financeiro/dre?' + params.toString())
    if (res && res.success !== false) {
      dreData.value = res.data || res.dre || (res.receita_bruta !== undefined ? res : null)
      if (!dreData.value) {
        throw new Error('Formato de resposta inválido ou sem dados.')
      }
    } else {
      dreData.value = null
      throw new Error(res.message || 'Endpoint DRE não disponível ainda.')
    }
  } catch (err) {
    console.error(err)
    dreData.value = null
  } finally {
    loading.value = false
  }
}

/**
 * Extrai o valor monetário de um item da DRE.
 * A API retorna totais separados por tipo (receita/despesa) e hierarquia (próprio/filhos).
 */
function itemValor(item) {
  const val = item.valor || item.total_receitas_com_filhos || item.total_despesas_com_filhos || item.total_receitas_proprio || item.total_despesas_proprio || 0
  return parseFloat(val) || 0
}

/**
 * Filtra itens com valor zero para não poluir a visualização.
 */
function filteredItens(itens) {
  if (!itens || !itens.length) return []
  return itens.filter(item => itemValor(item) !== 0)
}

function exportarPDF() {
  const url = `${BASE_URL}financeiro/dre?format=pdf&data_inicio=${filtro.data_inicio}&data_fim=${filtro.data_fim}`
  window.open(url, '_blank')
}

onMounted(() => {
  fetchData()
})
</script>
