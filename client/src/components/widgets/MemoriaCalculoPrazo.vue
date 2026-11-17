<template>
  <section class="memoria-prazo" aria-label="Como este prazo foi contado">
    <h4 class="memoria-titulo"><i class="bi bi-calculator"></i> Como este prazo foi contado</h4>

    <p v-if="loading" class="memoria-vazio">Carregando memória de cálculo...</p>
    <p v-else-if="erro" class="memoria-vazio memoria-erro">{{ erro }}</p>

    <template v-else-if="dados">
      <div v-if="!memoria || memoria.metodo !== 'dias_uteis'" class="memoria-conferir" role="status">
        <PrazoAConferirBadge v-if="dados.a_conferir || memoria?.a_conferir" />
        <p class="memoria-texto">
          {{ dados.motivo || memoria?.avisos?.[0] || 'Este prazo não foi calculado em dias úteis.' }}
        </p>
        <p v-if="dados.data_estimada_ia || memoria?.vencimento" class="memoria-texto">
          Data estimada: <strong>{{ formatDate(dados.data_estimada_ia || memoria?.vencimento) }}</strong>
        </p>
      </div>

      <template v-else>
        <dl class="memoria-lista">
          <div><dt>Disponibilização</dt><dd>{{ formatDate(memoria.entrada?.data_disponibilizacao) }}</dd></div>
          <div><dt>Publicação</dt><dd>{{ formatDate(memoria.data_publicacao) }}</dd></div>
          <div><dt>Início da contagem</dt><dd>{{ formatDate(memoria.inicio_contagem) }}</dd></div>
          <div><dt>Prazo</dt><dd>{{ memoria.dias_uteis }} dias úteis</dd></div>
          <div v-if="memoria.tipo_ato"><dt>Ato</dt><dd>{{ memoria.tipo_ato }}</dd></div>
          <div v-if="memoria.entrada?.tribunal"><dt>Tribunal</dt><dd>{{ memoria.entrada.tribunal }}</dd></div>
        </dl>

        <div class="memoria-vencimento">
          <span>Vencimento</span>
          <strong>{{ formatDate(memoria.vencimento) }}</strong>
        </div>

        <details class="memoria-descontos" :open="!!memoria.descontados?.length">
          <summary>
            Dias descontados ({{ memoria.descontados?.length ?? 0 }})
          </summary>
          <ul v-if="memoria.descontados?.length">
            <li v-for="d in memoria.descontados" :key="d.data + d.motivo">
              <span class="memoria-data">{{ formatDate(d.data) }}</span>
              <span class="badge badge-muted">{{ rotulo(d.motivo) }}</span>
              <span class="memoria-desc">{{ d.descricao }}</span>
            </li>
          </ul>
          <p v-else class="memoria-vazio">Nenhum dia foi descontado.</p>
        </details>

        <ul v-if="memoria.avisos?.length" class="memoria-avisos">
          <li v-for="a in memoria.avisos" :key="a">{{ a }}</li>
        </ul>
        <p class="memoria-base">
          Confira sempre com a lei processual e as normas do tribunal.
          <span v-if="memoria.base_legal?.length"> Base: {{ memoria.base_legal.join('; ') }}.</span>
        </p>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
/**
 * Painel "Como este prazo foi contado". Busca no servidor a memória de cálculo gravada na
 * tarefa (tipo "tarefa") ou recalculada para a intimação (tipo "intimacao").
 */
import { ref, watch } from 'vue'
import { formatDate } from '../../services/api'
import {
  memoriaDaIntimacao, memoriaDaTarefa,
  type MemoriaCalculo, type MotivoDesconto, type RespostaMemoria,
} from '../../services/calendarioForenseApi'
import { ROTULO_MOTIVO } from '../../utils/prazos'
import PrazoAConferirBadge from './PrazoAConferirBadge.vue'

const props = defineProps<{ tipo: 'tarefa' | 'intimacao'; id: number | string }>()

const dados = ref<RespostaMemoria | null>(null)
const memoria = ref<MemoriaCalculo | null>(null)
const loading = ref(false)
const erro = ref('')

const rotulo = (m: MotivoDesconto) => ROTULO_MOTIVO[m] ?? m

async function carregar() {
  loading.value = true
  erro.value = ''
  dados.value = null
  memoria.value = null
  try {
    const busca = props.tipo === 'tarefa' ? memoriaDaTarefa : memoriaDaIntimacao
    dados.value = await busca(props.id)
    memoria.value = dados.value?.memoria ?? null
  } catch (e: any) {
    erro.value = e?.message || 'Não foi possível carregar a memória de cálculo.'
  } finally {
    loading.value = false
  }
}

watch(() => [props.tipo, props.id], carregar, { immediate: true })
</script>

<style scoped>
.memoria-prazo { padding: 4px 0; }
.memoria-titulo { margin: 0 0 10px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
.memoria-lista { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin: 0 0 12px; }
.memoria-lista dt { font-size: 0.7rem; color: var(--ink-muted); }
.memoria-lista dd { margin: 0; font-weight: 600; font-size: 0.85rem; }
.memoria-vencimento { display: flex; align-items: baseline; gap: 10px; padding: 10px 12px; border-radius: 8px; background: var(--surface-2, rgba(0, 0, 0, 0.04)); margin-bottom: 12px; }
.memoria-vencimento span { font-size: 0.75rem; color: var(--ink-muted); }
.memoria-vencimento strong { font-size: 1.25rem; color: var(--danger); }
.memoria-descontos summary { cursor: pointer; font-size: 0.8rem; font-weight: 600; }
.memoria-descontos ul { list-style: none; padding: 0; margin: 8px 0 0; display: grid; gap: 4px; }
.memoria-descontos li { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 0.78rem; }
.memoria-data { font-variant-numeric: tabular-nums; min-width: 78px; }
.memoria-desc { color: var(--ink-muted); }
.memoria-avisos { margin: 10px 0 0; padding-left: 18px; font-size: 0.75rem; color: var(--warning, #92400e); }
.memoria-base, .memoria-texto, .memoria-vazio { font-size: 0.75rem; color: var(--ink-muted); margin: 8px 0 0; }
.memoria-erro { color: var(--danger); }
.memoria-conferir { display: grid; gap: 4px; justify-items: start; }
</style>
