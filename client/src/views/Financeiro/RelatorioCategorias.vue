<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#" @click.prevent="$emit('set-view', 'extrato')">Extrato Financeiro</a>
      <i class="bi bi-chevron-right"></i>
      <span>Relatório por Categorias</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Relatório por Categorias</h1>
        <p>Análise financeira hierárquica baseada no extrato</p>
      </div>
      <button class="btn btn-outline-secondary" @click="$emit('set-view', 'extrato')">
        <i class="bi bi-arrow-left"></i> Voltar
      </button>
    </div>

    <!-- Filtros de Leitura (Herdados do Extrato) -->
    <div class="toolbar" style="margin-bottom: 15px;">
      <div class="toolbar-left" style="display:flex; gap:10px; align-items:center;">
        <span class="badge bg-secondary" style="font-size: 0.9rem; padding: 8px 12px;">
          <i class="bi bi-calendar3"></i> Período: {{ formatDateBr(filtro.dataInicial) }} até {{ formatDateBr(filtro.dataFinal) }}
        </span>
        <span class="badge bg-secondary" style="font-size: 0.9rem; padding: 8px 12px;">
          <i class="bi bi-bank"></i> Conta ID: {{ filtro.conta_id }}
        </span>
      </div>
      <div class="toolbar-right">
        <button class="btn btn-primary" @click="fetchReportData" :disabled="isLoading">
          <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="process-list" style="margin-top: 20px;">
      <div class="skeleton-card" v-for="i in 3" :key="i">
        <div class="skeleton-line" style="width: 100%; height: 60px;"></div>
      </div>
    </div>

    <!-- Relatório Financeiro -->
    <div v-else class="financial-report" style="margin-top: 20px;">
      <div class="process-list">
        <div 
          v-for="category in reportData" 
          :key="category.id" 
          class="category-group"
        >
          <!-- Card da Categoria Pai -->
          <div 
            class="process-card parent-card" 
            :class="getCategoryClass(category)"
            @click="toggleExpand(category)"
          >
            <div class="process-main">
              <span class="process-cnj">{{ category.name }}</span>
              <div class="process-title">{{ category.description }}</div>
              <div class="process-meta">
                <span v-if="category.children && category.children.length">
                  <i class="bi bi-diagram-3"></i> {{ category.children.length }} subcategorias
                </span>
              </div>
            </div>
            
            <div class="process-actions">
              <div class="financial-value" :class="getCategoryClass(category)">
                {{ formatCurrency(calculateTotal(category)) }}
              </div>
              <div class="percentage-value" v-if="category.percentage">
                {{ category.percentage }}% do total
              </div>
              <i :class="['bi', category.expanded ? 'bi-chevron-up' : 'bi-chevron-down', 'expand-icon']"></i>
            </div>
          </div>

          <!-- Cards dos Filhos -->
          <div v-if="category.expanded && category.children" class="children-container">
            <div 
              v-for="child in category.children" 
              :key="child.id" 
              class="process-card child-card"
            >
              <div class="process-main">
                <span class="process-cnj">{{ child.name }}</span>
                <div class="process-title">{{ child.description }}</div>
              </div>
              <div class="process-actions">
                <div class="financial-value">
                  {{ formatCurrency(child.value) }}
                </div>
              </div>
            </div>
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

const reportData = ref([])
const isLoading = ref(false)

const today = new Date()
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0]

// Recupera os filtros do Extrato salvos no sessionStorage
const storedFilters = sessionStorage.getItem('extratoFilters')
const defaultFilters = storedFilters ? JSON.parse(storedFilters) : { conta_id: 1, dataInicial: firstDay, dataFinal: lastDay }

const filtro = reactive(defaultFilters)

onMounted(() => {
  fetchReportData()
})

async function fetchReportData() {
  isLoading.value = true
  try {
    const params = new URLSearchParams()
    if (filtro.conta_id) params.append('conta_id', filtro.conta_id)
    if (filtro.dataInicial) params.append('dataInicial', filtro.dataInicial)
    if (filtro.dataFinal) params.append('dataFinal', filtro.dataFinal)
    
    const response = await apiFetch('financeiro/relatorio-categorias?' + params.toString());
    reportData.value = response;
  } catch (error) {
    console.error('Erro ao buscar dados do relatório:', error)
    emit('toast', { msg: 'Erro ao buscar dados do relatório.', type: 'danger' })
  } finally {
    isLoading.value = false
  }
}

function calculateTotal(category) {
  if (!category.children || category.children.length === 0) {
    return category.value || 0
  }
  return category.children.reduce((acc, child) => acc + (child.value || 0), 0)
}

function toggleExpand(category) {
  category.expanded = !category.expanded
}

function getCategoryClass(category) {
  if (category.type === 'receita') return 'receita'
  if (category.type === 'despesa') return 'despesa'
  return ''
}

function formatDateBr(dateString) {
  if (!dateString) return ''
  const parts = dateString.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateString
}
</script>

<style scoped>
/* Base Process Cards Adaptados */
.process-list { 
  display: flex; 
  flex-direction: column; 
  gap: 12px; 
}

.process-card {
  background: var(--card, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: var(--radius, 8px);
  padding: 16px 20px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  transition: all .18s;
  position: relative;
  overflow: hidden;
}

.process-card::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 4px;
  border-radius: 2px 0 0 2px;
  background: transparent;
}

.process-main { 
  flex: 1; 
  min-width: 0; 
}

.process-cnj {
  font-family: var(--font-head, inherit);
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--primary, #1e293b);
  display: block;
  margin-bottom: 4px;
}

.process-title {
  font-size: .85rem;
  color: var(--ink-dim, #64748b);
  margin-bottom: 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.process-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: .8rem;
  color: var(--ink-muted, #94a3b8);
}

.process-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

/* Parent Cards */
.parent-card {
  cursor: pointer;
}

.parent-card:hover {
  border-color: #cbd5e1;
  box-shadow: var(--shadow-lg, 0 4px 6px -1px rgba(0, 0, 0, 0.05));
  transform: translateY(-1px);
}

.parent-card.receita::before { 
  background: var(--success, #10b981); 
}

.parent-card.despesa::before { 
  background: var(--danger, #ef4444); 
}

.financial-value {
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--primary, #1e293b);
}

.financial-value.receita {
  color: var(--success, #10b981);
}

.financial-value.despesa {
  color: var(--danger, #ef4444);
}

.percentage-value {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--ink-dim, #64748b);
  background: var(--bg-muted, #f1f5f9);
  padding: 3px 8px;
  border-radius: 12px;
}

.expand-icon {
  margin-top: 6px;
  color: var(--ink-muted, #94a3b8);
  font-size: 0.9rem;
}

/* Children Cards */
.children-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 6px;
  margin-left: 24px;
  padding-left: 12px;
  border-left: 2px dashed var(--border, #e2e8f0);
}

.child-card {
  padding: 12px 16px;
  background: transparent;
  border: 1px solid var(--border-light, #f1f5f9);
  box-shadow: none;
  cursor: default;
}

.child-card::before {
  width: 2px;
  background: var(--border, #cbd5e1);
}

.child-card:hover {
  transform: none;
  box-shadow: none;
  border-color: var(--border, #e2e8f0);
}

.child-card .process-cnj {
  font-size: 0.95rem;
  color: var(--ink, #334155);
}

.child-card .financial-value {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--ink, #334155);
}
</style>
