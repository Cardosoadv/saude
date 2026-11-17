<template>
  <div class="modal-backdrop animate-fade-in" @click.self="$emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="modal-header">
        <h2 id="modal-title">
          <i class="bi bi-sliders"></i>
          {{ isEdit ? 'Editar Configuração' : 'Nova Configuração' }}
        </h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Chave/Nome -->
        <div class="form-group">
          <label class="form-label required-field">Parâmetro (Chave)</label>
          <input
            class="form-control"
            type="text"
            v-model="form.nome"
            placeholder="Ex: SISTEMA_NOME, LIMITE_BUSCA..."
            :disabled="isEdit"
            ref="nomeInput"
          />
          <small class="form-text text-muted" v-if="isEdit" style="font-size: 0.78rem; color: var(--ink-muted);">
            O identificador do parâmetro não pode ser alterado após criação.
          </small>
        </div>

        <!-- Valor -->
        <div class="form-group">
          <label class="form-label required-field">Valor</label>
          <input
            class="form-control"
            type="text"
            v-model="form.valor"
            placeholder="Valor da configuração..."
          />
        </div>

        <!-- Descrição -->
        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea
            class="form-control"
            rows="3"
            v-model="form.descricao"
            placeholder="Descreva o propósito desta configuração para o sistema..."
          ></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi bi-check-circle"></i> Salvar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.0.0
 */
import { reactive, computed, onMounted, ref } from 'vue'

const props = defineProps({
  configData: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

const isEdit = computed(() => !!props.configData)
const nomeInput = ref(null)

const form = reactive({
  id: props.configData?.id || null,
  nome: props.configData?.nome || '',
  valor: props.configData?.valor || '',
  descricao: props.configData?.descricao || ''
})

function onSubmit() {
  if (!form.nome || !form.nome.trim()) {
    emit('warn', 'O nome do parâmetro é obrigatório.')
    return
  }
  if (!form.valor || !form.valor.trim()) {
    emit('warn', 'O valor do parâmetro é obrigatório.')
    return
  }
  emit('submit', { ...form })
}

onMounted(() => {
  if (nomeInput.value && !isEdit.value) {
    nomeInput.value.focus()
  }
})
</script>
