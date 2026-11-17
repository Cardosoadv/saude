<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-lightning-charge"></i> Ações Rápidas</span>
    </div>
    <div class="section-body" style="display:flex; flex-direction:column; gap:8px;">
      <button class="btn btn-primary btn-sm" @click="$emit('open-modal', 'edicaoRapida')" style="width: 100%; justify-content: center; margin-bottom: 2px;">
        <i class="bi bi-pencil-square"></i> Edição Rápida
      </button>
      <button class="btn btn-accent btn-sm" @click="$emit('open-modal', 'sugerirArgumentos')" style="width: 100%; justify-content: center; margin-bottom: 2px; background: var(--primary); color: white; border: none;">
        <i class="bi bi-lightbulb"></i> Sugerir Argumentos (IA)
      </button>
      <button class="btn btn-accent btn-sm" @click="$emit('open-modal', 'analiseIA')" style="width: 100%; justify-content: center; margin-bottom: 2px; background: var(--primary); color: white; border: none;">
        <i class="bi bi-graph-up-arrow"></i> Previsão de Desfecho (IA)
      </button>
      <div class="actions-quick">
        <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'movimento')"><i class="bi bi-arrow-repeat"></i> Movimento</button>
        <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'parte')"><i class="bi bi-person-plus"></i> Parte</button>
      </div>
      <div class="actions-quick">
        <button v-if="liberado('tarefas', 'tarefas.access')" class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'tarefa')"><i class="bi bi-check2-square"></i> Tarefa</button>
        <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'anotacao')"><i class="bi bi-journal-text"></i> Anotação</button>
      </div>
      
      <hr style="border:none; border-top: 1px solid var(--border); margin:4px 0;">

      <button class="btn btn-accent btn-sm" @click="toggleArquivar">
        <i :class="isArquivado ? 'bi bi-arrow-up-circle' : 'bi bi-archive'"></i>
        {{ isArquivado ? 'Desarquivar Processo' : 'Arquivar Processo' }}
      </button>
      <button class="btn btn-ghost btn-sm" :disabled="encerrando" @click="toggleEncerrar">
        <i :class="isEncerrado ? 'bi bi-play-circle' : 'bi bi-check-circle'"></i>
        {{ isEncerrado ? 'Reabrir Processo' : 'Encerrar Processo' }}
      </button>
      <button class="btn btn-danger btn-sm" @click="excluirProcesso">
        <i class="bi bi-trash"></i> Excluir Processo
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { apiFetch } from '../../../services/api'
import { liberado } from '../../../composables/useAcesso'

const props = defineProps({
  processId: { type: Number, required: true },
  isArquivado: { type: Boolean, default: false },
  isEncerrado: { type: Boolean, default: false }
})

const emit = defineEmits(['open-modal', 'toast', 'refresh', 'process-deleted'])

async function toggleArquivar() {
  if (props.isArquivado) {
    try {
      const res = await apiFetch('processos/desarquivar/' + props.processId, {
        method: 'POST'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Processo desarquivado com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message || 'Erro ao desarquivar processo.')
      }
    } catch (err) {
      emit('toast', { msg: err.message || 'Erro ao desarquivar processo.', type: 'danger' })
    }
  } else {
    try {
      const res = await apiFetch('processos/arquivarEmLote', {
        method: 'POST',
        body: { processos: [props.processId] }
      })
      if (res && res.success) {
        emit('toast', { msg: 'Processo arquivado com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message)
      }
    } catch (err) {
      emit('toast', { msg: 'Erro ao arquivar processo.', type: 'danger' })
    }
  }
}

const encerrando = ref(false)

async function toggleEncerrar() {
  const reabrir = props.isEncerrado
  const hoje = new Date().toLocaleDateString('pt-BR')
  const pergunta = reabrir
    ? 'Reabrir este processo? A data de encerramento será removida.'
    : `Encerrar este processo com data de hoje (${hoje})? O Relatório Final (IA) será gerado e disponibilizado ao cliente.`
  if (!confirm(pergunta)) return

  encerrando.value = true
  try {
    const res = await apiFetch(`processos/${reabrir ? 'reabrir' : 'encerrar'}/` + props.processId, {
      method: 'POST'
    })
    if (res && res.success) {
      emit('toast', { msg: reabrir ? 'Processo reaberto com sucesso!' : 'Processo encerrado com sucesso!', type: 'success' })
      emit('refresh')
    } else {
      throw new Error(res?.message)
    }
  } catch (err) {
    emit('toast', { msg: err.message || (reabrir ? 'Erro ao reabrir processo.' : 'Erro ao encerrar processo.'), type: 'danger' })
  } finally {
    encerrando.value = false
  }
}

async function excluirProcesso() {
  if (confirm('Confirma a exclusão definitiva deste processo? Esta ação não pode ser desfeita.')) {
    try {
      const res = await apiFetch('processos/excluir/' + props.processId, {
        method: 'POST'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Processo excluído com sucesso!', type: 'success' })
        emit('process-deleted', props.processId)
      } else {
        throw new Error(res.message)
      }
    } catch (err) {
      emit('toast', { msg: 'Erro ao excluir processo.', type: 'danger' })
    }
  }
}
</script>

<style scoped>
.actions-quick {
  display: flex;
  gap: 8px;
}
.actions-quick .btn {
  flex: 1;
}
</style>
