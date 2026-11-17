<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-journal-text"></i> {{ form.id_anotacao ? 'Editar Anotação' : 'Nova Anotação' }}</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Título *</label>
          <input class="form-control" type="text" v-model="form.titulo" placeholder="Título da anotação...">
        </div>
        <div class="form-group">
          <label class="form-label">Anotação *</label>
          <textarea class="form-control" rows="5" v-model="form.anotacao" placeholder="Escreva sua anotação aqui..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Privacidade</label>
          <select class="form-control form-select" v-model="form.nivel_privacidade">
            <option value="1">Privado</option>
            <option value="3">Público</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi bi-check-circle"></i> Salvar Anotação
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'

const props = defineProps({
  anotacaoData: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

const form = reactive({
  id_anotacao: props.anotacaoData?.id_anotacao || props.anotacaoData?.id || null,
  titulo: props.anotacaoData?.titulo || '',
  anotacao: props.anotacaoData?.anotacao || '',
  nivel_privacidade: String(props.anotacaoData?.privacidade || props.anotacaoData?.nivel_privacidade || '1')
})

function onSubmit() {
  if (!form.titulo || !form.anotacao) {
    emit('warn', 'Preencha todos os campos')
    return
  }
  emit('submit', { ...form })
}
</script>
