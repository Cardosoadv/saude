<template>
  <div ref="raiz" class="autocomplete-container contrato-autocomplete">
    <div v-if="isLoading" class="searching-spinner">
      <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Buscando...</span></div>
    </div>
    <input
      type="text"
      :class="['form-control', { 'form-control-sm': pequeno }]"
      v-model="termo"
      :placeholder="placeholder"
      :aria-label="rotulo"
      :disabled="disabled"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      aria-haspopup="listbox"
      :aria-expanded="aberto"
      :aria-controls="idLista"
      :aria-activedescendant="ativo >= 0 ? idLista + '-' + ativo : undefined"
      @input="buscar"
      @focus="buscar"
      @keydown="teclas"
    >
    <ul v-if="aberto" :id="idLista" class="autocomplete-dropdown" role="listbox">
      <li
        v-for="(r, i) in results"
        :key="r.id"
        :id="idLista + '-' + i"
        role="option"
        :aria-selected="i === ativo"
        :class="{ active: i === ativo }"
        @mousedown.prevent="escolher(r)"
        @mouseenter="ativo = i"
      >
        {{ r.text }}<small v-if="r.objeto" class="contrato-autocomplete-objeto"> · {{ r.objeto }}</small>
      </li>
      <li v-if="!isLoading && !results.length" class="contrato-autocomplete-vazio" role="option" aria-disabled="true">
        Nenhum contrato encontrado
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useSearchAutocomplete } from '../composables/useSearchAutocomplete'

export interface ContratoOption {
  id: number | string
  /** "Contrato #número - Cliente" */
  text: string
  numero_contrato?: string | null
  nome_cliente?: string | null
  objeto?: string | null
}

/**
 * Campo de busca de contrato com autocompletar (processos/contratos-busca),
 * para usar várias vezes na mesma tela. Emite "select" e limpa o campo.
 */
withDefaults(defineProps<{
  placeholder?: string
  rotulo?: string
  pequeno?: boolean
  disabled?: boolean
}>(), {
  placeholder: 'Buscar contrato pelo número ou cliente...',
  rotulo: 'Buscar contrato',
  pequeno: false,
  disabled: false,
})

const emit = defineEmits<{ (e: 'select', contrato: ContratoOption): void }>()

const MIN_CARACTERES = 2
const idLista = `contrato-ac-${Math.random().toString(36).slice(2, 9)}`
const raiz = ref<HTMLElement | null>(null)
const termo = ref('')
const ativo = ref(-1)

const { results, isLoading, showDropdown, onInput, clear } = useSearchAutocomplete<ContratoOption>({
  searchEndpoint: 'processos/contratos-busca',
  minChars: MIN_CARACTERES,
  cache: false,
})

const aberto = computed(() => showDropdown.value && termo.value.trim().length >= MIN_CARACTERES)

function buscar() {
  ativo.value = -1
  onInput(termo.value)
}

function escolher(r: ContratoOption) {
  emit('select', r)
  termo.value = ''
  ativo.value = -1
  clear()
}

function teclas(e: KeyboardEvent) {
  if (!aberto.value) return
  const total = results.value.length
  if (e.key === 'ArrowDown' && total) {
    e.preventDefault()
    ativo.value = (ativo.value + 1) % total
  } else if (e.key === 'ArrowUp' && total) {
    e.preventDefault()
    ativo.value = ativo.value <= 0 ? total - 1 : ativo.value - 1
  } else if (e.key === 'Enter' && ativo.value >= 0 && results.value[ativo.value]) {
    e.preventDefault()
    escolher(results.value[ativo.value])
  } else if (e.key === 'Escape') {
    clear()
  }
}

function foraDoCampo(e: MouseEvent) {
  if (raiz.value && !raiz.value.contains(e.target as Node)) showDropdown.value = false
}

onMounted(() => document.addEventListener('mousedown', foraDoCampo))
onBeforeUnmount(() => document.removeEventListener('mousedown', foraDoCampo))
</script>

<style scoped>
.contrato-autocomplete { position: relative; }
.contrato-autocomplete .autocomplete-dropdown { z-index: 20; }
.contrato-autocomplete-objeto { color: var(--ink-muted); }
.contrato-autocomplete-vazio { color: var(--ink-muted); cursor: default; font-style: italic; }
</style>
