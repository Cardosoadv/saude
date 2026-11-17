<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-lightbulb"></i> Argumentos IA</span>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" @click="$emit('open-form', null)" style="padding:2px 8px; font-size:.78rem;">
          <i class="bi bi-plus-lg"></i> Novo Argumento Manual
        </button>
        <button v-if="props.processoId" class="btn btn-primary btn-sm" @click="$emit('open-ia')" style="padding:2px 8px; font-size:.78rem;">
          <i class="bi bi-robot"></i> Gerar Novas Sugestões
        </button>
      </div>
    </div>
    <div class="section-body">
      <div v-if="loading" style="text-align:center; padding: 20px;">
        <div class="spinner-border spinner-border-sm" style="color: var(--accent);" role="status"></div>
      </div>
      <div v-else-if="!argumentos || argumentos.length === 0" style="font-size:0.8rem; color:var(--ink-muted); text-align:center; padding: 10px;">
        Nenhum argumento salvo para este processo.
      </div>
      <div v-else>
        <div v-for="arg in argumentos" :key="arg.id_argumento" style="padding: 12px; border-bottom: 1px solid var(--border); font-size: 0.85rem;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
            <strong style="color:var(--ink); flex: 1;">{{ arg.titulo }}</strong>
            <div style="display: flex; gap: 8px; align-items: center;">
              <button class="btn btn-ghost btn-sm" style="color: var(--primary); font-size: 0.8rem; padding: 2px 6px;" @click="$emit('open-form', arg)" title="Editar Argumento">
                <i class="bi bi-pencil-square"></i>
              </button>
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm" :class="arg.avaliacao === 'like' ? 'btn-success' : 'btn-outline-secondary'" @click="avaliar(arg, 'like')" style="padding: 2px 6px; font-size: 0.8rem;" title="Bem Sucedido">
                  <i class="bi bi-hand-thumbs-up-fill"></i>
                </button>
                <button type="button" class="btn btn-sm" :class="arg.avaliacao === 'dislike' ? 'btn-danger' : 'btn-outline-secondary'" @click="avaliar(arg, 'dislike')" style="padding: 2px 6px; font-size: 0.8rem;" title="Mal Sucedido">
                  <i class="bi bi-hand-thumbs-down-fill"></i>
                </button>
              </div>
            </div>
          </div>
          <p style="margin: 0; color: var(--ink-dim); line-height: 1.4; white-space: pre-wrap;">{{ arg.estrategia_recomendada }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processoId: {
    type: Number,
    default: null
  },
  casoId: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['open-ia', 'open-form', 'toast'])

const loading = ref(true)
const argumentos = ref([])

async function fetchArgumentos() {
  if (!props.processoId && !props.casoId) return
  
  loading.value = true
  try {
    const endpoint = props.processoId 
      ? `argumentos/processo/${props.processoId}` 
      : `argumentos/caso/${props.casoId}`
      
    const res = await apiFetch(endpoint)
    if (res && res.success) {
      argumentos.value = res.data || []
    }
  } catch (err) {
    console.error('Erro ao buscar argumentos:', err)
  } finally {
    loading.value = false
  }
}

async function avaliar(arg, tipo) {
  // Se clicou no que já estava selecionado, desmarca (null)
  const novaAvaliacao = arg.avaliacao === tipo ? null : tipo
  const avaliacaoAntiga = arg.avaliacao
  
  // Otimista
  arg.avaliacao = novaAvaliacao

  try {
    const res = await apiFetch(`argumentos/${arg.id_processo_argumento}/avaliar`, {
      method: 'PATCH',
      body: { avaliacao: novaAvaliacao }
    })
    if (!res || !res.success) {
      arg.avaliacao = avaliacaoAntiga // rollback
      emit('toast', { msg: 'Erro ao avaliar', type: 'danger' })
    }
  } catch (err) {
    arg.avaliacao = avaliacaoAntiga // rollback
    emit('toast', { msg: 'Falha na comunicação.', type: 'danger' })
  }
}

onMounted(() => {
  fetchArgumentos()
})

defineExpose({ fetchArgumentos })
</script>
