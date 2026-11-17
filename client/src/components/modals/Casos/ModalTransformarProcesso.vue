<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 520px;">
      <div class="modal-header">
        <h2><i class="bi bi-arrow-right-circle"></i> Transformar em Processo</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding: 1.5rem;">
        <div class="alert alert-warning" style="font-size: 0.82rem; margin-bottom: 1.2rem;">
          <i class="bi bi-exclamation-triangle"></i>
          Ao transformar o caso em processo, um novo processo judicial será criado com os dados deste caso.
          Esta ação não pode ser desfeita.
        </div>

        <div class="form-group">
          <label for="numero_processo_cnj" class="required-field"><i class="bi bi-hash"></i> Número do Processo (CNJ)</label>
          <input
            type="text"
            id="numero_processo_cnj"
            v-model="transformarForm.numero_processo"
            @input="onNumeroProcessoInput"
            class="form-control"
            :class="{ 'input-error': transformarValidacao.erro, 'input-success': transformarValidacao.valido && !transformarValidacao.erro }"
            placeholder="0000000-00.0000.0.00.0000"
            maxlength="25"
            autocomplete="off"
          />
          <div v-if="transformarValidacao.mensagem" class="validacao-msg" :class="transformarValidacao.erro ? 'validacao-erro' : transformarValidacao.valido ? 'validacao-ok' : ''">
            <i :class="transformarValidacao.erro ? 'bi bi-x-circle' : transformarValidacao.valido ? 'bi bi-check-circle' : 'bi bi-info-circle'"></i>
            {{ transformarValidacao.mensagem }}
          </div>
          <div v-if="transformarValidacao.processoExistente" class="alert alert-danger" style="font-size: 0.8rem; margin-top: 8px; padding: 8px 12px;">
            <i class="bi bi-exclamation-octagon"></i>
            Este número de processo já está cadastrado: <strong>{{ transformarValidacao.processoExistente.titulo }}</strong>
            (Cliente: {{ transformarValidacao.processoExistente.cliente }})
          </div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
          <label for="data_distribuicao"><i class="bi bi-calendar-event"></i> Data de Distribuição (Opcional)</label>
          <input type="date" id="data_distribuicao" v-model="transformarForm.data_distribuicao" class="form-control" />
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
        <button class="btn btn-outline-secondary" @click="$emit('close')">Cancelar</button>
        <button
          v-if="transformarValidacao.processoExistente"
          class="btn btn-info text-white"
          @click="vincularProcessoExistente"
          :disabled="transformando"
        >
          <i class="bi bi-link-45deg"></i> Vincular ao Processo Existente
        </button>
        <button
          class="btn btn-success"
          @click="confirmarTransformacao"
          :disabled="transformando || !transformarValidacao.valido || transformarValidacao.erro || transformarValidacao.processoExistente"
        >
          <i class="bi bi-arrow-right-circle"></i>
          {{ transformando ? 'Transformando...' : 'Confirmar Transformação' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  casoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'toast', 'success'])

const transformando = ref(false)
const transformarForm = reactive({
  numero_processo: '',
  data_distribuicao: ''
})
const transformarValidacao = reactive({
  valido: false,
  erro: false,
  mensagem: '',
  processoExistente: null
})
let validarTimeout = null

// Utils locales para máscara
function applyCNJMask(value) {
  let limpo = value.replace(/\D/g, '').substring(0, 20)
  let formatted = ''
  for (let i = 0; i < limpo.length; i++) {
    if (i === 7) formatted += '-'
    if (i === 9) formatted += '.'
    if (i === 13) formatted += '.'
    if (i === 14) formatted += '.'
    if (i === 16) formatted += '.'
    formatted += limpo[i]
  }
  return formatted
}

function onNumeroProcessoInput(e) {
  const masked = applyCNJMask(e.target.value)
  transformarForm.numero_processo = masked
  
  const limpo = masked.replace(/\D/g, '')
  
  // Reset validation
  transformarValidacao.processoExistente = null
  
  if (limpo.length === 0) {
    transformarValidacao.valido = false
    transformarValidacao.erro = false
    transformarValidacao.mensagem = ''
    return
  }
  
  if (limpo.length < 20) {
    transformarValidacao.valido = false
    transformarValidacao.erro = false
    transformarValidacao.mensagem = `${limpo.length}/20 dígitos`
    return
  }
  
  if (limpo.length === 20) {
    transformarValidacao.valido = false
    transformarValidacao.erro = false
    transformarValidacao.mensagem = 'Validando...'
    
    clearTimeout(validarTimeout)
    validarTimeout = setTimeout(() => validarNumeroProcesso(limpo), 400)
  }
}

async function validarNumeroProcesso(numero) {
  try {
    const res = await apiFetch('casos/validarNumeroProcesso', {
      method: 'POST',
      body: { numero }
    })
    if (res && res.success && res.data) {
      if (res.data.existe) {
        transformarValidacao.valido = false
        transformarValidacao.erro = true
        transformarValidacao.mensagem = res.data.mensagem || 'Número já cadastrado.'
        transformarValidacao.processoExistente = res.data.processo || null
      } else if (res.data.valido) {
        transformarValidacao.valido = true
        transformarValidacao.erro = false
        transformarValidacao.mensagem = 'Número válido ✓'
        transformarValidacao.processoExistente = null
      } else {
        transformarValidacao.valido = false
        transformarValidacao.erro = true
        transformarValidacao.mensagem = res.data.mensagem || 'Número inválido.'
      }
    }
  } catch (err) {
    transformarValidacao.valido = false
    transformarValidacao.erro = true
    transformarValidacao.mensagem = 'Erro ao validar número.'
  }
}

async function confirmarTransformacao() {
  if (!transformarValidacao.valido || transformarValidacao.erro) return
  
  transformando.value = true
  try {
    const res = await apiFetch(`casos/transformar/${props.casoId}`, {
      method: 'POST',
      body: {
        numero_processo: transformarForm.numero_processo,
        data_distribuicao: transformarForm.data_distribuicao || null
      }
    })
    
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Caso transformado em processo com sucesso!', type: 'success' })
      emit('success')
      emit('close')
    } else {
      throw new Error(res.message || 'Erro ao transformar caso.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao transformar o caso em processo.', type: 'danger' })
  } finally {
    transformando.value = false
  }
}

async function vincularProcessoExistente() {
  if (!transformarValidacao.processoExistente) return
  
  transformando.value = true
  try {
    const res = await apiFetch(`casos/vincularProcessoExistente/${props.casoId}`, {
      method: 'POST',
      body: {
        processo_id: transformarValidacao.processoExistente.id
      }
    })
    
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Caso vinculado ao processo com sucesso!', type: 'success' })
      emit('success')
      emit('close')
    } else {
      throw new Error(res.message || 'Erro ao vincular caso.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao vincular o caso ao processo.', type: 'danger' })
  } finally {
    transformando.value = false
  }
}

onUnmounted(() => {
  clearTimeout(validarTimeout)
})
</script>

<style scoped>
.input-error {
  border-color: var(--danger) !important;
  box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.15) !important;
}

.input-success {
  border-color: var(--success) !important;
  box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.15) !important;
}

.validacao-msg {
  font-size: 0.78rem;
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 0;
  color: var(--ink-muted);
  transition: all 0.2s ease;
}

.validacao-erro {
  color: var(--danger);
  font-weight: 600;
}

.validacao-ok {
  color: var(--success);
  font-weight: 600;
}
</style>
