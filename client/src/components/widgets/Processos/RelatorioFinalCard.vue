<template>
  <div v-if="processo.encerrado == 1 && processo.relatorio_final" class="section-card">
    <div class="section-header" style="background-color: var(--accent-lt); color: var(--accent);">
      <span><i class="bi bi-robot"></i> Relatório Final (IA)</span>
      <div style="display:flex; gap:8px;">
        <button class="btn btn-ghost btn-sm" @click="toggleEdit" title="Editar Relatório">
          <i class="bi" :class="isEditingRelatorio ? 'bi-x-lg' : 'bi-pencil'"></i> {{ isEditingRelatorio ? 'Cancelar' : 'Editar' }}
        </button>
        <button class="btn btn-ghost btn-sm" @click="regenerateRelatorio" title="Regerar Relatório com IA">
          <i class="bi bi-arrow-clockwise"></i> Regenerar
        </button>
      </div>
    </div>
    <div class="section-body">
      <div v-if="!isEditingRelatorio">
        <div style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
          <span class="badge" :class="processo.relatorio_final_publicado == 1 ? 'badge-success' : 'badge-warning'">
            {{ processo.relatorio_final_publicado == 1 ? 'Publicado na Área do Cliente' : 'Não Publicado (Rascunho)' }}
          </span>
          <button class="btn btn-outline-secondary btn-sm" @click="toggleRelatorioPublicado">
            {{ processo.relatorio_final_publicado == 1 ? 'Ocultar do Cliente' : 'Publicar para Cliente' }}
          </button>
        </div>
        <div v-html="processo.relatorio_final" style="font-size: 0.85rem; line-height: 1.5; color: var(--ink); background: var(--bg-body); padding: 15px; border-radius: 6px; border: 1px solid var(--border);"></div>
      </div>
      <div v-else>
        <textarea v-model="editRelatorioText" class="form-control" rows="8" style="width: 100%; margin-bottom: 10px; font-family: inherit; font-size: 0.85rem; padding: 10px;"></textarea>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
          <button class="btn btn-primary btn-sm" @click="saveRelatorio" :disabled="savingRelatorio">
            <span v-if="savingRelatorio" class="spinner-border spinner-border-sm"></span>
            <i v-else class="bi bi-check-lg"></i> Salvar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processId: { type: Number, required: true },
  processo: { type: Object, required: true }
})

const emit = defineEmits(['toast', 'refresh'])

const isEditingRelatorio = ref(false)
const editRelatorioText = ref('')
const savingRelatorio = ref(false)

function toggleEdit() {
  isEditingRelatorio.value = !isEditingRelatorio.value
  if (isEditingRelatorio.value) {
    editRelatorioText.value = props.processo.relatorio_final
  }
}

function toggleRelatorioPublicado() {
  const published = props.processo.relatorio_final_publicado == 1 ? 0 : 1
  apiFetch(`processos/relatorioFinal/${props.processId}/save`, {
    method: 'POST',
    body: { relatorio_final_publicado: published }
  }).then(res => {
    if (res && res.success) {
      emit('toast', { msg: 'Visibilidade do relatório atualizada', type: 'success' })
      emit('refresh')
    }
  }).catch(e => emit('toast', { msg: 'Erro ao atualizar visibilidade', type: 'danger' }))
}

function saveRelatorio() {
  savingRelatorio.value = true
  apiFetch(`processos/relatorioFinal/${props.processId}/save`, {
    method: 'POST',
    body: { relatorio_final: editRelatorioText.value }
  }).then(res => {
    if (res && res.success) {
      isEditingRelatorio.value = false
      emit('toast', { msg: 'Relatório salvo com sucesso', type: 'success' })
      emit('refresh')
    }
  }).catch(e => emit('toast', { msg: 'Erro ao salvar relatório', type: 'danger' }))
    .finally(() => savingRelatorio.value = false)
}

function regenerateRelatorio() {
  if (!confirm('Deseja realmente gerar um novo relatório? O atual será substituído e a visibilidade mantida.')) return
  emit('toast', { msg: 'Gerando novo relatório...', type: 'info' })
  apiFetch(`processos/relatorioFinal/${props.processId}/regenerate`, {
    method: 'POST'
  }).then(res => {
    if (res && res.success && res.data && res.data.relatorio_final) {
      emit('toast', { msg: 'Relatório gerado com sucesso', type: 'success' })
      emit('refresh')
    }
  }).catch(e => emit('toast', { msg: 'Erro ao gerar relatório', type: 'danger' }))
}
</script>
