<template>
  <div ref="raiz" class="autocomplete-container cliente-autocomplete">
    <div v-if="isLoading" class="searching-spinner">
      <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Buscando...</span></div>
    </div>
    <input
      type="text"
      :class="['form-control', { 'form-control-sm': pequeno }]"
      v-model="clientSearch"
      :placeholder="placeholder"
      :aria-label="rotulo"
      :disabled="disabled"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      aria-haspopup="listbox"
      :aria-expanded="aberto"
      :aria-controls="idLista"
      :aria-activedescendant="activeIndex >= 0 ? idLista + '-' + activeIndex : undefined"
      @input="onClientInput"
      @focus="onClientInput"
      @keydown="teclas"
    >
    <ul v-if="aberto" :id="idLista" class="autocomplete-dropdown" role="listbox">
      <li
        v-for="(r, i) in clientResults"
        :key="r.id"
        :id="idLista + '-' + i"
        role="option"
        :aria-selected="i === activeIndex"
        :class="{ active: i === activeIndex }"
        @mousedown.prevent="escolher(r)"
        @mouseenter="activeIndex = i"
      >
        {{ r.text }}
      </li>
      <li v-if="!isLoading && !clientResults.length" class="cliente-autocomplete-vazio" role="option" aria-disabled="true">
        Nenhum cliente encontrado
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useClientAutocomplete, type ClientOption, type ClientEndpoint } from '../composables/useClientAutocomplete'

/**
 * Campo de busca de cliente com autocompletar, para usar várias vezes na
 * mesma tela. Emite "select" com o cliente escolhido e limpa o campo.
 */
const props = withDefaults(defineProps<{
  placeholder?: string
  rotulo?: string
  pequeno?: boolean
  disabled?: boolean
  endpoint?: ClientEndpoint
}>(), {
  placeholder: 'Buscar cliente pelo nome ou documento...',
  rotulo: 'Buscar cliente',
  pequeno: false,
  disabled: false,
  endpoint: 'clientes',
})

const emit = defineEmits<{ (e: 'select', cliente: ClientOption): void }>()

const idLista = `cliente-ac-${Math.random().toString(36).slice(2, 9)}`
const raiz = ref<HTMLElement | null>(null)

const {
  clientSearch,
  clientResults,
  showClients,
  isLoading,
  activeIndex,
  termoValido,
  onClientInput,
  onClientKeydown,
  clearClient,
  handleClickOutsideClient,
} = useClientAutocomplete(undefined, { endpoint: props.endpoint, container: raiz })

const aberto = computed(() => showClients.value && termoValido.value)

function escolher(r: ClientOption) {
  emit('select', r)
  clearClient()
}

function teclas(e: KeyboardEvent) {
  const escolhido = onClientKeydown(e, idLista)
  if (escolhido) escolher(escolhido)
}

onMounted(() => document.addEventListener('mousedown', handleClickOutsideClient))
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutsideClient))
</script>

<style scoped>
.cliente-autocomplete { position: relative; }
.cliente-autocomplete .autocomplete-dropdown { z-index: 20; }
.cliente-autocomplete-vazio { color: var(--ink-muted); cursor: default; font-style: italic; }
</style>
