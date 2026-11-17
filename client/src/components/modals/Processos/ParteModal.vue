<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-person-plus"></i> Adicionar Parte</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Polo</label>
          <select class="form-control form-select" v-model="form.polo">
            <option value="A">Polo Ativo (Autor)</option>
            <option value="P">Polo Passivo (Réu)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nome da Parte *</label>
          <input class="form-control" type="text" v-model="form.nome" placeholder="Nome completo da parte..." @input="conflictWarning = null">
        </div>
        <div v-if="conflictWarning" style="margin-top: 15px; padding: 12px; background: rgba(220, 53, 69, 0.1); border-left: 4px solid var(--danger); border-radius: 4px; color: var(--danger); font-size: 0.85rem;">
          <strong><i class="bi bi-exclamation-triangle-fill"></i> Alerta de Conflito Ético:</strong><br/>
          {{ conflictWarning }}
          <br/><span style="display:block; margin-top:6px; font-weight:bold; color: var(--danger);">Deseja ignorar o risco e adicionar a parte mesmo assim?</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')" :disabled="checkingConflict">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit" :disabled="checkingConflict">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" v-if="checkingConflict" style="margin-right: 6px;"></span>
          <i class="bi bi-check-circle" v-else></i> {{ conflictWarning ? 'Ignorar e Adicionar' : 'Adicionar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { apiFetch } from '../../../services/api'

const emit = defineEmits(['close', 'submit', 'warn'])

const form = reactive({
  polo: 'A',
  nome: ''
})

const checkingConflict = ref(false)
const conflictWarning = ref(null)

async function onSubmit() {
  if (!form.nome) {
    emit('warn', 'Informe o nome da parte')
    return
  }

  if (conflictWarning.value) {
    emit('submit', { ...form })
    return
  }

  checkingConflict.value = true
  try {
    const res = await apiFetch(`processos/verificarConflito?nome=${encodeURIComponent(form.nome)}`)
    if (res && res.success && res.data && res.data.has_conflict) {
      conflictWarning.value = res.data.message || 'Potencial conflito de interesse detectado com esta parte.'
    } else {
      emit('submit', { ...form })
    }
  } catch (err) {
    emit('submit', { ...form })
  } finally {
    checkingConflict.value = false
  }
}
</script>
