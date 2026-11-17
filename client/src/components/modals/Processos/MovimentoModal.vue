<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-arrow-repeat"></i> Nova Movimentação</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Descrição *</label>
          <input class="form-control" type="text" v-model="form.nome" placeholder="Descrição da movimentação...">
        </div>
        <div class="form-group">
          <label class="form-label">Data e Hora *</label>
          <input class="form-control" type="datetime-local" v-model="form.dataHora">
        </div>
        <div class="form-group">
          <label class="form-label">Observações</label>
          <textarea class="form-control" rows="3" v-model="form.observacao" placeholder="Detalhes adicionais..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi bi-check-circle"></i> Adicionar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, onMounted } from 'vue'

const emit = defineEmits(['close', 'submit', 'warn'])

const form = reactive({
  nome: '',
  dataHora: '',
  observacao: ''
})

onMounted(() => {
  const now = new Date()
  form.dataHora = now.toISOString().slice(0, 16)
})

function onSubmit() {
  if (!form.nome) {
    emit('warn', 'Informe a descrição')
    return
  }
  emit('submit', { ...form })
}
</script>
