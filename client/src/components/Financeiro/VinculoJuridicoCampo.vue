<template>
  <div class="vinculo-juridico">
    <div class="position-relative">
      <div class="input-group" style="display: flex;">
        <input
          type="text"
          class="form-control"
          style="flex: 1;"
          v-model="busca"
          @input="onBusca"
          @focus="aberto = true"
          placeholder="Buscar caso ou processo por título, número ou cliente..."
          aria-label="Buscar caso ou processo"
          autocomplete="off"
        >
        <button
          v-if="podeCadastrar"
          class="btn btn-outline-primary"
          style="border-top-left-radius: 0; border-bottom-left-radius: 0; white-space: nowrap;"
          type="button"
          @click="modalAberto = true"
        >
          <i class="bi bi-plus-lg"></i> Cadastrar
        </button>
      </div>

      <ul v-if="aberto && opcoes.length" class="autocomplete-list glass-panel shadow z-3 position-absolute w-100">
        <li v-for="o in opcoes" :key="o.tipo + o.id" @click="escolher(o)">
          <div class="autocomplete-item-title">
            <span class="vinculo-tag" :class="o.tipo">{{ o.tipo === 'caso' ? 'Caso' : 'Processo' }}</span>
            {{ o.rotulo }}
          </div>
          <small class="autocomplete-item-sub">{{ o.cliente_nome || 'Sem cliente' }}</small>
        </li>
      </ul>
      <small v-else-if="aberto && buscou && busca.trim().length >= 2" class="vinculo-vazio">
        Nenhum caso ou processo encontrado.
      </small>
    </div>

    <div v-if="casoId || processoId" class="vinculo-chips">
      <span v-if="casoId" class="vinculo-chip">
        <span class="vinculo-tag caso">Caso</span> {{ casoRotulo || `#${casoId}` }}
        <button type="button" class="vinculo-remover" title="Remover caso" @click="remover('caso')"><i class="bi bi-x"></i></button>
      </span>
      <span v-if="processoId" class="vinculo-chip">
        <span class="vinculo-tag processo">Processo</span> {{ processoRotulo || `#${processoId}` }}
        <button type="button" class="vinculo-remover" title="Remover processo" @click="remover('processo')"><i class="bi bi-x"></i></button>
      </span>
    </div>

    <div v-if="previa" class="vinculo-previa">
      <i class="bi bi-diagram-3"></i>
      <span>Centro de custo:</span>
      <span v-for="d in previa" :key="d.rotulo"><strong>{{ d.rotulo }}</strong> {{ d.nome }}</span>
    </div>

    <ModalCadastroRapidoJuridico
      v-if="modalAberto"
      :cliente-id="clienteId"
      :cliente-nome="clienteNome"
      @close="modalAberto = false"
      @toast="t => emit('toast', t)"
      @criado="onCriado"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { apiFetch, debounce } from '../../services/api'
import { pode } from '../../composables/useAcesso'
import ModalCadastroRapidoJuridico from './ModalCadastroRapidoJuridico.vue'

export interface OpcaoVinculo {
  tipo: 'caso' | 'processo'
  id: number
  rotulo: string
  cliente_id: number | null
  cliente_nome: string | null
  processo_id?: number | null
}

/**
 * Vínculo de um lançamento financeiro a um caso e/ou processo (issue #892). Busca nos dois módulos com
 * `financeiro.access`, mostra a prévia do centro de custo e permite cadastrar um caso ou processo novo
 * para quem tem `casos.create` / `processos.create`.
 */
const props = withDefaults(defineProps<{
  casoId?: number | string | null
  processoId?: number | string | null
  casoRotulo?: string | null
  processoRotulo?: string | null
  clienteId?: number | string | null
  clienteNome?: string | null
  contratoId?: number | string | null
}>(), {
  casoId: null,
  processoId: null,
  casoRotulo: null,
  processoRotulo: null,
  clienteId: null,
  clienteNome: null,
  contratoId: null,
})

const emit = defineEmits<{
  (e: 'update:casoId', v: number | null): void
  (e: 'update:processoId', v: number | null): void
  (e: 'update:casoRotulo', v: string | null): void
  (e: 'update:processoRotulo', v: string | null): void
  /** Cliente do caso/processo escolhido, para preencher o lançamento que ainda não tem um. */
  (e: 'cliente', c: { id: number, nome: string | null }): void
  (e: 'toast', t: { msg: string, type: string }): void
}>()

const busca = ref('')
const opcoes = ref<OpcaoVinculo[]>([])
const aberto = ref(false)
const buscou = ref(false)
const modalAberto = ref(false)
const dimensoes = ref<Record<string, { id: number, nome: string } | null> | null>(null)

const podeCadastrar = computed(() => pode('casos.create', 'processos.create'))

const ROTULOS: Record<string, string> = { processo: 'Processo', caso: 'Caso', cliente: 'Cliente', ramo_direito: 'Ramo' }

const previa = computed(() => {
  if (!dimensoes.value || !(props.casoId || props.processoId)) return null
  const itens = Object.entries(ROTULOS)
    .filter(([tipo]) => dimensoes.value?.[tipo])
    .map(([tipo, rotulo]) => ({ rotulo, nome: dimensoes.value![tipo]!.nome }))
  return itens.length ? itens : null
})

const buscar = debounce(async (termo: string) => {
  if (termo.trim().length < 2) {
    opcoes.value = []
    buscou.value = false
    return
  }
  try {
    const res = await apiFetch(`financeiro/vinculos/buscar?q=${encodeURIComponent(termo.trim())}&limite=10`)
    opcoes.value = res?.success ? (res.data || []) : []
    buscou.value = true
    aberto.value = true
  } catch (e) {
    console.error('Erro ao buscar casos e processos:', e)
  }
}, 300)

function onBusca() {
  buscar(busca.value)
}

function escolher(o: OpcaoVinculo) {
  if (o.tipo === 'caso') {
    emit('update:casoId', o.id)
    emit('update:casoRotulo', o.rotulo)
  } else {
    emit('update:processoId', o.id)
    emit('update:processoRotulo', o.rotulo)
  }
  if (o.cliente_id && !props.clienteId) {
    emit('cliente', { id: o.cliente_id, nome: o.cliente_nome })
  }
  busca.value = ''
  opcoes.value = []
  aberto.value = false
}

function remover(tipo: 'caso' | 'processo') {
  if (tipo === 'caso') {
    emit('update:casoId', null)
    emit('update:casoRotulo', null)
  } else {
    emit('update:processoId', null)
    emit('update:processoRotulo', null)
  }
}

function onCriado(o: OpcaoVinculo) {
  modalAberto.value = false
  escolher(o)
}

const resolver = debounce(async () => {
  if (!props.casoId && !props.processoId) {
    dimensoes.value = null
    return
  }
  const params = new URLSearchParams()
  for (const [k, v] of Object.entries({ caso_id: props.casoId, processo_id: props.processoId, cliente_id: props.clienteId, contrato_id: props.contratoId })) {
    if (v) params.set(k, String(v))
  }
  try {
    const res = await apiFetch(`financeiro/vinculos/resolver?${params}`)
    dimensoes.value = res?.success ? res.data : null
  } catch (e) {
    dimensoes.value = null
  }
}, 200)

watch(() => [props.casoId, props.processoId, props.clienteId, props.contratoId], () => resolver(), { immediate: true })

function fecharFora(e: MouseEvent) {
  if (!(e.target as HTMLElement | null)?.closest('.vinculo-juridico')) aberto.value = false
}

onMounted(() => document.addEventListener('click', fecharFora))
onUnmounted(() => document.removeEventListener('click', fecharFora))
</script>

<style scoped>
.vinculo-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.vinculo-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 4px 3px 8px; border-radius: 999px;
  background: var(--input-bg); border: 1px solid var(--border-color); font-size: .82rem;
}
.vinculo-tag {
  font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
  padding: 1px 6px; border-radius: 4px; color: #fff; background: var(--primary);
}
.vinculo-tag.processo { background: var(--accent, var(--primary)); }
.vinculo-remover { border: 0; background: transparent; color: var(--ink-muted); padding: 0 4px; cursor: pointer; }
.vinculo-remover:hover { color: var(--danger, #c0392b); }
.vinculo-previa {
  display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center;
  margin-top: 8px; font-size: .78rem; color: var(--ink-muted);
}
.vinculo-vazio { display: block; margin-top: 4px; font-size: .78rem; color: var(--ink-muted); }
</style>
