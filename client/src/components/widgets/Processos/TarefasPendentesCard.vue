<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-check2-square"></i> Tarefas Pendentes</span>
      <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'tarefa')" style="padding:2px 8px; font-size:.78rem;">
        <i class="bi bi-plus-circle"></i> Criar
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <div v-if="!tarefas || !tarefas.length" style="font-size:.8rem;color:var(--ink-muted);padding:10px 0;text-align:center;">Nenhuma tarefa vinculada.</div>
      <div 
        v-for="t in tarefas" 
        :key="t.id || t.id_tarefa" 
        style="display:flex; align-items:flex-start; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border);"
      >
        <div style="display:flex; align-items:flex-start; gap:8px;">
          <input 
            type="checkbox" 
            :checked="Number(t.status) === 4" 
            @change="toggleTarefaStatus(t)"
            style="margin-top:3px; accent-color:var(--primary);"
          >
          <div>
            <span :style="getTaskStatusStyle(t.status)" style="font-size:.83rem; font-weight:500;">
              {{ t.tarefa }}
            </span>
            <span style="display:block; font-size:.7rem; color:var(--ink-muted); margin-top:2px;">
              Prazo: {{ formatDate(t.prazo) }}
            </span>
          </div>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
          <button class="btn btn-ghost btn-sm" @click="$emit('edit-tarefa', t)" style="border:none; padding:2px; color:var(--primary);">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-ghost btn-sm" @click="removerTarefa(t.id || t.id_tarefa)" style="border:none; padding:2px; color:var(--danger);">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { formatDate } from '../../../services/api'
import { apiFetch } from '../../../services/api'
import type { Tarefa } from '../../../types/domain'

const props = defineProps({
  processId: { type: Number, required: true },
  tarefas: { type: Array as PropType<Tarefa[]>, default: () => [] }
})

const emit = defineEmits(['open-modal', 'edit-tarefa', 'toast', 'refresh'])

const getTaskStatusStyle = (status) => {
  return Number(status) === 4
    ? { textDecoration: 'line-through', color: 'var(--ink-muted)' }
    : {}
}

async function toggleTarefaStatus(task) {
  const id = task.id || task.id_tarefa
  const currentStatus = Number(task.status)
  const newStatus = currentStatus === 4 ? 2 : 4

  try {
    const res = await apiFetch('tarefas/mudar-status', {
      method: 'POST',
      body: {
        id: id,
        status: newStatus
      }
    })
    if (res && res.success) {
      const text = newStatus === 4 ? 'Tarefa concluída!' : 'Tarefa marcada como pendente'
      emit('toast', { msg: text, type: 'success' })
      emit('refresh')
    } else {
      throw new Error(res.message || 'Erro ao atualizar status da tarefa.')
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao atualizar status da tarefa.', type: 'danger' })
  }
}

async function removerTarefa(id) {
  if (confirm('Confirma a exclusão definitiva desta tarefa?')) {
    try {
      const res = await apiFetch(`tarefas/excluir/${id}`, {
        method: 'DELETE'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Tarefa excluída com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message || 'Erro ao excluir tarefa.')
      }
    } catch (err) {
      emit('toast', { msg: err.message || 'Erro ao excluir tarefa.', type: 'danger' })
    }
  }
}
</script>
