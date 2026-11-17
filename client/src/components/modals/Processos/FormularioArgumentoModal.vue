<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 650px;">
      <div class="modal-header">
        <h2>
          <i class="bi" :class="isEditing ? 'bi-pencil-square' : 'bi-plus-circle'"></i> 
          {{ isEditing ? 'Editar Argumento' : 'Novo Argumento Manual' }}
        </h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="salvar" class="d-flex flex-column gap-3">
          <div class="form-group">
            <label class="form-label">Título do Argumento <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="form.titulo" required placeholder="Ex: Preliminar de Ilegitimidade Passiva">
          </div>
          
          <div class="form-group">
            <label class="form-label">Estratégia Recomendada <span class="text-danger">*</span></label>
            <textarea class="form-control" v-model="form.estrategia_recomendada" rows="5" required placeholder="Descreva a estratégia detalhada para a defesa ou tese..."></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Teses Relacionadas <span class="text-muted">(Opcional)</span></label>
            <textarea class="form-control" v-model="form.teses_relacionadas" rows="3" placeholder="Insira as teses, súmulas ou artigos separados por linha..."></textarea>
            <small class="form-text text-muted">Uma tese por linha.</small>
          </div>

          <div class="form-group">
            <label class="form-label">Ramo do Direito <span class="text-muted">(Opcional)</span></label>
            <select class="form-control form-select" v-model="form.ramo_id">
              <option :value="null">Nenhum</option>
              <option v-for="ramo in ramos" :key="ramo.id" :value="ramo.id">{{ ramo.nome }}</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')" :disabled="saving">Cancelar</button>
        <button class="btn btn-primary" :disabled="saving || !isFormValid" @click="salvar">
          <span v-if="saving" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="margin-right: 5px;"></span>
          <i v-else class="bi bi-check2-circle" style="margin-right: 5px;"></i>
          Salvar Argumento
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processoId: {
    type: Number,
    default: null
  },
  casoId: {
    type: Number,
    default: null
  },
  // Se for null, é criação. Se for um objeto, é edição.
  argumentoParaEditar: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'toast', 'saved'])

const saving = ref(false)
const isEditing = computed(() => !!props.argumentoParaEditar)
const ramos = ref([])

const form = ref({
  titulo: '',
  estrategia_recomendada: '',
  teses_relacionadas: '',
  ramo_id: null
})

const isFormValid = computed(() => {
  return form.value.titulo.trim() !== '' && form.value.estrategia_recomendada.trim() !== ''
})

onMounted(() => {
  carregarRamos()

  if (isEditing.value) {
    form.value.titulo = props.argumentoParaEditar.titulo || ''
    form.value.estrategia_recomendada = props.argumentoParaEditar.estrategia_recomendada || ''
    form.value.ramo_id = props.argumentoParaEditar.ramo_id || null

    const teses = props.argumentoParaEditar.teses_relacionadas
    if (Array.isArray(teses)) {
      form.value.teses_relacionadas = teses.join('\n')
    } else if (typeof teses === 'string') {
      form.value.teses_relacionadas = teses
    }
  }
})

async function carregarRamos() {
  try {
    const res = await apiFetch('argumentos/ramos-options')
    if (res && res.success) {
      ramos.value = res.data || []
    }
  } catch (err) {
    console.error('Erro ao carregar ramos do direito:', err)
  }
}

async function salvar() {
  if (!isFormValid.value) return
  
  saving.value = true
  try {
    const payload = {
      titulo: form.value.titulo,
      estrategia_recomendada: form.value.estrategia_recomendada,
      teses_relacionadas: form.value.teses_relacionadas,
      ramo_id: form.value.ramo_id,
      processo_id: props.processoId,
      caso_id: props.casoId
    }

    let res
    if (isEditing.value) {
      res = await apiFetch(`argumentos/${props.argumentoParaEditar.id_argumento}`, {
        method: 'PUT',
        body: payload
      })
    } else {
      res = await apiFetch('argumentos', {
        method: 'POST',
        body: payload
      })
    }

    if (res && res.success) {
      emit('toast', { msg: isEditing.value ? 'Argumento atualizado com sucesso!' : 'Argumento criado com sucesso!', type: 'success' })
      emit('saved')
      emit('close')
    } else {
      emit('toast', { msg: res.message || 'Erro ao salvar argumento.', type: 'danger' })
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Falha na comunicação ao tentar salvar.', type: 'danger' })
  } finally {
    saving.value = false
  }
}
</script>
