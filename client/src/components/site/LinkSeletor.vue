<template>
  <div class="link-seletor">
    <select
      class="form-control link-tipo"
      :value="valor.tipo"
      :disabled="readonly"
      aria-label="Tipo de destino"
      @change="trocarTipo(($event.target as HTMLSelectElement).value as SiteLinkValor['tipo'])"
    >
      <option value="pagina">Página do site</option>
      <option value="ancora">Âncora na página</option>
      <option value="url">Endereço externo (URL)</option>
    </select>

    <select
      v-if="valor.tipo === 'pagina'"
      class="form-control"
      :value="valor.pagina_id ?? ''"
      :disabled="readonly"
      aria-label="Página de destino"
      @change="emitir({ tipo: 'pagina', pagina_id: numeroOuNulo(($event.target as HTMLSelectElement).value) })"
    >
      <option value="">Selecione uma página…</option>
      <option v-for="p in opcoes" :key="p.id" :value="p.id">{{ p.titulo }}</option>
    </select>

    <input
      v-else-if="valor.tipo === 'ancora'"
      type="text"
      class="form-control"
      :value="valor.ancora"
      :disabled="readonly"
      placeholder="contato"
      aria-label="Âncora"
      @input="emitir({ tipo: 'ancora', ancora: ($event.target as HTMLInputElement).value.trim() })"
    />

    <input
      v-else
      type="text"
      class="form-control"
      :value="valor.url"
      :disabled="readonly"
      placeholder="https://… , mailto:… , tel:… ou /caminho"
      aria-label="Endereço"
      @input="emitir({ tipo: 'url', url: ($event.target as HTMLInputElement).value.trim() })"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listarPaginas } from '../../services/siteApi'
import type { SiteLinkValor } from '../../types/site'
import { linkComTipo } from '../../utils/schemaForm'

const props = defineProps<{
  modelValue: SiteLinkValor | null | undefined
  /** Páginas para escolher; sem elas, o componente busca na API. */
  paginas?: Array<{ id: number; titulo: string }>
  readonly?: boolean
}>()
const emit = defineEmits<{ (e: 'update:modelValue', valor: SiteLinkValor): void }>()

const valor = computed<SiteLinkValor>(() => props.modelValue ?? linkComTipo('pagina'))

const carregadas = ref<Array<{ id: number; titulo: string }>>([])
const opcoes = computed(() => props.paginas ?? carregadas.value)

onMounted(async () => {
  if (props.paginas) return
  try {
    const r = await listarPaginas()
    carregadas.value = r.data.map((p) => ({ id: p.id, titulo: p.titulo }))
  } catch {
    carregadas.value = []
  }
})

const emitir = (v: SiteLinkValor) => emit('update:modelValue', v)
const trocarTipo = (tipo: SiteLinkValor['tipo']) => emitir(linkComTipo(tipo))
const numeroOuNulo = (texto: string): number | null => (texto === '' ? null : Number(texto))
</script>

<style scoped>
.link-seletor { display: flex; flex-wrap: wrap; gap: 8px; }
.link-tipo { flex: 0 0 210px; }
.link-seletor > :not(.link-tipo) { flex: 1 1 240px; }
</style>
