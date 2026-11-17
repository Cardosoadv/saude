<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-link-45deg"></i> Vincular Contrato</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding: 1.5rem;">
        <div class="form-group autocomplete-container contract-autocomplete-container" style="position: relative;" ref="containerRef">
          <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Buscar Contrato</label>
          <div class="input-group">
            <input type="text" class="form-control" v-model="contractSearch" @input="onContractInput"
              @focus="showContracts = true" placeholder="Digite número ou nome do contrato..." autocomplete="off">
            <button v-if="selectedContractId" type="button" class="btn-clear" @click="clearContract" title="Limpar contrato">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>
          <ul v-if="showContracts && contractResults.length" class="autocomplete-dropdown" style="z-index: 9999;">
            <li v-for="res in contractResults" :key="res.id" @click="selectContract(res)">
              {{ res.text }}
            </li>
          </ul>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
        <button class="btn btn-outline-secondary" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="confirmVincularContrato" :disabled="!selectedContractId || linkingContract">
          {{ linkingContract ? 'Vinculando...' : 'Confirmar Vínculo' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  casoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'toast', 'success'])

const containerRef = ref(null)
const linkingContract = ref(false)
const contractSearch = ref('')
const contractResults = ref([])
const showContracts = ref(false)
const selectedContractId = ref(null)
let contractTimeout = null

function onContractInput() {
  clearTimeout(contractTimeout)
  if (contractSearch.value.trim().length < 2) {
    contractResults.value = []
    showContracts.value = false
    return
  }
  contractTimeout = setTimeout(async () => {
    try {
      const res = await apiFetch('processos/contratos-busca?term=' + encodeURIComponent(contractSearch.value))
      if (res && res.results) {
        contractResults.value = res.results
        showContracts.value = true
      }
    } catch (err) {
      console.error(err)
    }
  }, 350)
}

function selectContract(item) {
  selectedContractId.value = item.id
  contractSearch.value = item.text
  contractResults.value = []
  showContracts.value = false
}

function clearContract() {
  selectedContractId.value = null
  contractSearch.value = ''
  contractResults.value = []
  showContracts.value = false
}

async function confirmVincularContrato() {
  if (!selectedContractId.value) return
  linkingContract.value = true
  try {
    const res = await apiFetch(`casos/vincular-contrato/${props.casoId}`, {
      method: 'POST',
      body: { contrato_id: selectedContractId.value }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Contrato vinculado com sucesso!', type: 'success' })
      emit('success')
      emit('close')
    } else {
      throw new Error(res.message || 'Erro ao vincular.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Erro ao vincular contrato.', type: 'danger' })
  } finally {
    linkingContract.value = false
  }
}

// Fechar autocomplete ao clicar fora
function handleClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    showContracts.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
  clearTimeout(contractTimeout)
})
</script>

<style scoped>
/* Os estilos .autocomplete-container, .input-group, .btn-clear, .autocomplete-dropdown 
   devem ser migrados para o CSS global conforme o plano, mas por enquanto funcionam via classes globais se já existirem ou podem ficar aqui caso o global não esteja pronto. */
</style>
