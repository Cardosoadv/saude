<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box nova-modal" role="dialog" aria-modal="true" aria-label="Nova liquidação">
      <div class="modal-header">
        <h2><i class="bi bi-calculator"></i> Nova liquidação</h2>
        <button type="button" class="modal-close" :disabled="salvando" aria-label="Fechar" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>
      <form class="modal-body" style="padding:1.25rem;" @submit.prevent="criar">
        <div class="form-group" v-if="!processoId">
          <label class="form-label" for="nl-proc">Processo</label>
          <div class="nova-busca">
            <input id="nl-proc" class="form-control" type="text" v-model="busca" autocomplete="off" placeholder="Número, título ou parte do processo..."
                   @input="aoDigitar" @focus="showDropdown = busca.trim().length >= 2" />
            <button v-if="escolhido" type="button" class="btn btn-ghost btn-sm" aria-label="Trocar processo" @click="limpar"><i class="bi bi-x-lg"></i></button>
            <ul v-if="showDropdown && !escolhido" class="nova-lista" role="listbox">
              <li v-if="carregandoBusca" class="nova-aviso">Buscando...</li>
              <li v-else-if="!resultados.length" class="nova-aviso">Nenhum processo encontrado.</li>
              <li v-for="p in resultados" :key="p.id" role="option" tabindex="0" @click="escolher(p)" @keydown.enter="escolher(p)">{{ p.text }}</li>
            </ul>
          </div>
        </div>
        <p v-else class="nova-fixo"><i class="bi bi-bank"></i> {{ processoRotulo || 'Processo #' + processoId }}</p>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" for="nl-tipo">Tipo</label>
            <select id="nl-tipo" class="form-control form-select" v-model="tipo">
              <option v-for="(r, k) in TIPOS_LIQUIDACAO" :key="k" :value="k">{{ r }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="nl-base">Data-base do cálculo</label>
            <input id="nl-base" class="form-control" type="date" v-model="dataBase" required />
          </div>
        </div>
        <p v-if="erro" class="nova-erro" role="alert">{{ erro }}</p>
        <div class="nova-rodape">
          <button type="button" class="btn btn-outline-secondary" :disabled="salvando" @click="fechar">Cancelar</button>
          <button type="submit" class="btn btn-primary" :disabled="salvando || !(processoId || escolhido)">{{ salvando ? 'Criando...' : 'Criar liquidação' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '../../services/api'
import { criarLiquidacao } from '../../services/liquidacoesApi'
import { useSearchAutocomplete } from '../../composables/useSearchAutocomplete'
import type { Id } from '../../types/domain'
import type { TipoLiquidacao } from '../../types/liquidacao'
import { TIPOS_LIQUIDACAO, hojeISO } from '../../utils/liquidacao'

const props = defineProps<{ processoId?: Id | null; processoRotulo?: string }>()
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'criada', id: Id): void
}>()

interface ProcessoOpcao { id: Id; text: string }

const tipo = ref<TipoLiquidacao>('por_calculo')
const dataBase = ref(hojeISO())
const salvando = ref(false)
const erro = ref('')
const busca = ref('')
const escolhido = ref<ProcessoOpcao | null>(null)

const { results: resultados, isLoading: carregandoBusca, showDropdown, onInput, clear } =
  useSearchAutocomplete<ProcessoOpcao>({ searchEndpoint: 'processos/buscar' })

function aoDigitar() {
  escolhido.value = null
  onInput(busca.value)
}
function escolher(p: ProcessoOpcao) {
  escolhido.value = p
  busca.value = p.text
  showDropdown.value = false
}
function limpar() {
  escolhido.value = null
  busca.value = ''
  clear()
}

async function criar() {
  const processo = props.processoId ?? escolhido.value?.id
  if (!processo) { erro.value = 'Escolha o processo.'; return }
  salvando.value = true
  erro.value = ''
  try {
    const id = await criarLiquidacao({ processo_id: processo, tipo_liquidacao: tipo.value, data_base: dataBase.value, status: 'rascunho' })
    emit('criada', id)
  } catch (e) {
    erro.value = e instanceof ApiError || e instanceof Error ? e.message : 'Não foi possível criar a liquidação.'
  } finally {
    salvando.value = false
  }
}

function fechar() { if (!salvando.value) emit('close') }
</script>

<style scoped>
.nova-modal { max-width: 560px; width: 100%; }
.nova-busca { position: relative; display: flex; gap: 4px; }
.nova-lista { position: absolute; top: 100%; left: 0; right: 0; z-index: 20; background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 6px; margin: 2px 0 0; padding: 0; list-style: none; max-height: 220px; overflow-y: auto; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
.nova-lista li { padding: 8px 10px; cursor: pointer; font-size: .85rem; }
.nova-lista li:hover, .nova-lista li:focus { background: var(--surface-alt, #f3f4f6); }
.nova-aviso { color: var(--ink-muted); cursor: default !important; }
.nova-fixo { margin: 0 0 12px; font-size: .9rem; }
.nova-erro { color: var(--danger); font-size: .85rem; }
.nova-rodape { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 12px; }
</style>
