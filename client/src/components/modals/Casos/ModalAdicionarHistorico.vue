<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-clock-history"></i> Adicionar Histórico</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding: 1.5rem;">
        <div class="form-group">
          <label for="hist_acao" class="required-field">Ação / Título</label>
          <input type="text" id="hist_acao" v-model="historicoForm.acao" class="form-control" placeholder="Ex: Contato com cliente, Reunião agendada..." />
        </div>
        <div class="form-group" style="margin-top: 1rem;">
          <label for="hist_desc">Descrição (Opcional)</label>
          <textarea id="hist_desc" v-model="historicoForm.descricao" class="form-control" rows="3" placeholder="Detalhes do ocorrido..."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
        <button class="btn btn-outline-secondary" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="salvarHistorico" :disabled="savingHistorico || !historicoForm.acao">
          {{ savingHistorico ? 'Salvando...' : 'Salvar Histórico' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  casoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'toast', 'success'])

const savingHistorico = ref(false)
const historicoForm = reactive({
  acao: '',
  descricao: ''
})

async function salvarHistorico() {
  if (!historicoForm.acao) return
  savingHistorico.value = true
  try {
    const res = await apiFetch(`casos/historico/${props.casoId}`, {
      method: 'POST',
      body: {
        acao: historicoForm.acao,
        descricao: historicoForm.descricao
      }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Histórico adicionado com sucesso!', type: 'success' })
      emit('success')
      emit('close')
    } else {
      throw new Error(res.message || 'Erro ao adicionar histórico.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Erro ao adicionar histórico.', type: 'danger' })
  } finally {
    savingHistorico.value = false
  }
}
</script>
