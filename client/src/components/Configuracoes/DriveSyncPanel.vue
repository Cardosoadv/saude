<template>
  <div>
    <!-- Resumo + ações -->
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-cloud-arrow-up" style="color: var(--accent);"></i> Sincronização de pastas do Google Drive
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
        Cada cliente tem uma pasta na raiz do Drive e cada processo, uma subpasta dentro da pasta do cliente.
        Os pedidos de criação ficam numa fila e são processados em lotes, aqui ou pelo comando
        <code>php spark drive:sync</code> (agende no cron para rodar sozinho).
      </p>

      <div v-if="!resumo && loadingResumo" class="skeleton-line" style="width: 60%; height: 40px;"></div>

      <template v-if="resumo">
        <div v-if="!resumo.configurado" class="badge badge-warning" style="margin-bottom: 14px; display:inline-block;">
          <i class="bi bi-exclamation-triangle"></i> GOOGLE_DRIVE_FOLDER_ID não está configurado (aba Sistema). Nada será processado.
        </div>

        <div style="display: flex; gap: 28px; flex-wrap: wrap; margin-bottom: 16px;">
          <div v-for="c in cartoes" :key="c.rotulo">
            <div style="font-size: 1.6rem; font-weight: 700;" :style="{ color: c.cor || 'var(--ink)' }">{{ c.valor }}</div>
            <div style="font-size: 0.8rem; color: var(--ink-muted);">{{ c.rotulo }}</div>
          </div>
        </div>
      </template>

      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-primary" @click="enfileirarPendentes" :disabled="ocupado || !temSemPasta">
          <i class="bi bi-plus-circle"></i> Enfileirar quem não tem pasta
        </button>
        <button class="btn btn-primary" @click="processar" :disabled="ocupado || !resumo?.configurado || !resumo?.fila.pending">
          <i class="bi bi-play-fill"></i> {{ processando ? 'Processando...' : 'Processar próximo lote' }}
        </button>
        <button class="btn btn-ghost" @click="reprocessarStatus('failed')" :disabled="ocupado || !resumo?.fila.failed">
          <i class="bi bi-arrow-repeat"></i> Reprocessar falhas
        </button>
        <button class="btn btn-ghost" @click="reprocessarStatus('processing')" :disabled="ocupado || !resumo?.fila.travados">
          <i class="bi bi-unlock"></i> Destravar ({{ resumo?.fila.travados ?? 0 }})
        </button>
        <button class="btn btn-ghost" @click="excluirStatus('completed')" :disabled="ocupado || !resumo?.fila.completed">
          <i class="bi bi-trash"></i> Limpar concluídos
        </button>
        <button class="btn btn-ghost" @click="atualizarTudo" :disabled="ocupado">
          <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
      </div>
    </div>

    <!-- Resultado do último lote -->
    <div v-if="ultimoLote" class="card animate-fade-in"
      :style="{ marginBottom: '1.5rem', padding: '16px 20px', border: '1px solid ' + (ultimoLote.falhas ? 'var(--danger)' : 'var(--accent)') }">
      <strong>
        <i :class="ultimoLote.falhas ? 'bi bi-exclamation-circle' : 'bi bi-check-circle'"></i>
        Lote: {{ ultimoLote.processados }} processado(s), {{ ultimoLote.sucesso }} com sucesso,
        {{ ultimoLote.falhas }} com falha. Restam {{ ultimoLote.restantes }} na fila.
      </strong>
    </div>

    <!-- Sub-abas -->
    <div class="filter-tabs" role="tablist" style="margin-bottom: 1rem;">
      <button v-for="s in subAbas" :key="s.id" class="filter-tab" :class="{ active: aba === s.id }"
        role="tab" :aria-selected="aba === s.id" @click="trocarAba(s.id)">
        <i :class="s.icone + ' me-1'"></i> {{ s.rotulo }}
      </button>
    </div>

    <!-- Filtros -->
    <div class="card animate-fade-in" style="padding: 12px 16px; margin-bottom: 1rem; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <input v-model="busca" type="search" class="form-control" style="max-width: 280px;"
        :placeholder="aba === 'fila' ? 'Buscar por nome, número ou erro' : 'Buscar por nome ou número'"
        aria-label="Buscar" @input="buscarComAtraso" />

      <template v-if="aba === 'fila'">
        <select v-model="filtroStatus" class="form-control" style="width:auto;" aria-label="Status" @change="irParaPagina(1)">
          <option value="">Todos os status</option>
          <option value="pending">Pendentes</option>
          <option value="processing">Em processamento</option>
          <option value="completed">Concluídos</option>
          <option value="failed">Com falha</option>
        </select>
        <select v-model="filtroTipo" class="form-control" style="width:auto;" aria-label="Tipo" @change="irParaPagina(1)">
          <option value="">Clientes e processos</option>
          <option value="cliente">Só clientes</option>
          <option value="processo">Só processos</option>
        </select>
      </template>
      <select v-else v-model="filtroSituacao" class="form-control" style="width:auto;" aria-label="Situação" @change="irParaPagina(1)">
        <option value="">Todos</option>
        <option value="sem_pasta">Sem pasta</option>
        <option value="com_pasta">Com pasta</option>
      </select>

      <!-- Ações em lote -->
      <div v-if="selecionados.size" style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        <span style="font-size:0.85rem; color: var(--ink-muted);">{{ selecionados.size }} selecionado(s)</span>
        <template v-if="aba === 'fila'">
          <button class="btn btn-ghost" :disabled="ocupado" @click="reprocessarSelecionados"><i class="bi bi-arrow-repeat"></i> Reprocessar</button>
          <button class="btn btn-ghost" :disabled="ocupado" @click="excluirSelecionados"><i class="bi bi-trash"></i> Remover da fila</button>
        </template>
        <button v-else class="btn btn-primary" :disabled="ocupado" @click="enfileirarSelecionados">
          <i class="bi bi-cloud-arrow-up"></i> {{ filtroSituacao === 'com_pasta' ? 'Sincronizar novamente' : 'Enfileirar' }}
        </button>
      </div>
    </div>

    <!-- Tabela -->
    <div v-if="loading && !itens.length" class="process-list">
      <div class="skeleton-card" v-for="i in 4" :key="i">
        <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
        <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
      </div>
    </div>

    <div v-else class="card animate-fade-in" style="overflow-x: auto;">
      <table class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
            <th style="padding: 12px 16px; width: 36px;">
              <input type="checkbox" :checked="todosMarcados" @change="alternarTodos" aria-label="Selecionar todos da página" />
            </th>
            <template v-if="aba === 'fila'">
              <th style="padding: 12px 16px;">Tipo</th>
              <th style="padding: 12px 16px;">Entidade</th>
              <th style="padding: 12px 16px;">Status</th>
              <th style="padding: 12px 16px;">Atualizado</th>
              <th style="padding: 12px 16px;">Pasta / Erro</th>
              <th style="padding: 12px 16px;"></th>
            </template>
            <template v-else>
              <th style="padding: 12px 16px;">{{ aba === 'clientes' ? 'Cliente' : 'Processo' }}</th>
              <th style="padding: 12px 16px;">Pasta</th>
              <th style="padding: 12px 16px;">Último job</th>
              <th style="padding: 12px 16px;"></th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-if="aba === 'fila'">
            <tr v-for="j in itens" :key="j.id" style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px 16px;"><input type="checkbox" :checked="selecionados.has(Number(j.id))" @change="alternar(Number(j.id))" :aria-label="'Selecionar job ' + j.id" /></td>
              <td style="padding: 12px 16px; color: var(--ink-muted);">{{ j.entity_type === 'cliente' ? 'Cliente' : 'Processo' }}</td>
              <td style="padding: 12px 16px; font-weight: 600; color: var(--ink);">
                <a v-if="j.entidade_nome" class="entidade-link" :href="linkEntidade(j.entity_type, j.entity_id)" target="_blank" rel="noopener" title="Abrir em nova guia">{{ j.entidade_nome }} <i class="bi bi-box-arrow-up-right"></i></a>
                <template v-else>{{ '#' + j.entity_id + ' (removido)' }}</template>
              </td>
              <td style="padding: 12px 16px;"><span class="badge" :class="classeStatus(j.status)">{{ rotuloStatus(j.status) }}</span></td>
              <td style="padding: 12px 16px; color: var(--ink-muted); font-size: 0.88rem;">{{ formatDate(j.updated_at, true) }}</td>
              <td style="padding: 12px 16px; font-size: 0.85rem; max-width: 360px;">
                <a v-if="j.pasta" :href="j.pasta" target="_blank" rel="noopener"><i class="bi bi-folder2-open"></i> Abrir pasta</a>
                <span v-if="j.error_message" style="color: var(--danger); display:block;">{{ j.error_message }}</span>
                <span v-if="!j.pasta && !j.error_message" style="color: var(--ink-muted);">-</span>
              </td>
              <td style="padding: 12px 16px; white-space: nowrap;">
                <button v-if="j.status !== 'pending'" class="btn btn-ghost" :disabled="ocupado" title="Reprocessar" aria-label="Reprocessar job" @click="reprocessarIds([Number(j.id)])"><i class="bi bi-arrow-repeat"></i></button>
                <button class="btn btn-ghost" :disabled="ocupado" title="Remover da fila" aria-label="Remover job" @click="excluirIds([Number(j.id)])"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          </template>
          <template v-else>
            <tr v-for="e in itens" :key="e.id" style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px 16px;"><input type="checkbox" :checked="selecionados.has(Number(e.id))" @change="alternar(Number(e.id))" :aria-label="'Selecionar ' + e.nome" /></td>
              <td style="padding: 12px 16px; font-weight: 600; color: var(--ink);">
                <a class="entidade-link" :href="linkEntidade(aba === 'clientes' ? 'cliente' : 'processo', e.id)" target="_blank" rel="noopener" title="Abrir em nova guia">{{ e.nome }} <i class="bi bi-box-arrow-up-right"></i></a>
              </td>
              <td style="padding: 12px 16px; font-size: 0.88rem;">
                <a v-if="e.pasta" :href="e.pasta" target="_blank" rel="noopener"><i class="bi bi-folder2-open"></i> Abrir pasta</a>
                <span v-else class="badge badge-warning">Sem pasta</span>
              </td>
              <td style="padding: 12px 16px; font-size: 0.85rem;">
                <span v-if="e.ultimo_job_status" class="badge" :class="classeStatus(e.ultimo_job_status)">{{ rotuloStatus(e.ultimo_job_status) }}</span>
                <span v-else style="color: var(--ink-muted);">-</span>
                <span v-if="e.ultimo_job_status === 'failed' && e.ultimo_job_erro" style="color: var(--danger); display:block;">{{ e.ultimo_job_erro }}</span>
              </td>
              <td style="padding: 12px 16px; white-space: nowrap;">
                <button class="btn btn-ghost" :disabled="ocupado" :title="e.pasta ? 'Sincronizar novamente' : 'Enfileirar'" :aria-label="e.pasta ? 'Sincronizar novamente' : 'Enfileirar'" @click="enfileirarIds([Number(e.id)])"><i class="bi bi-cloud-arrow-up"></i></button>
              </td>
            </tr>
          </template>
          <tr v-if="!itens.length">
            <td colspan="7" style="padding: 20px; text-align: center; color: var(--ink-muted);">Nada encontrado.</td>
          </tr>
        </tbody>
      </table>

      <!-- Paginação -->
      <div style="padding: 12px 16px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
        <span style="font-size:0.85rem; color: var(--ink-muted);">{{ total }} registro(s) — página {{ pagina }} de {{ totalPaginas }}</span>
        <div style="display:flex; gap:8px;">
          <button class="btn btn-ghost" :disabled="pagina <= 1 || loading" @click="irParaPagina(pagina - 1)"><i class="bi bi-chevron-left"></i> Anterior</button>
          <button class="btn btn-ghost" :disabled="pagina >= totalPaginas || loading" @click="irParaPagina(pagina + 1)">Próxima <i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Painel de sincronização de pastas do Google Drive: resumo da fila, jobs,
 * clientes e processos (com/sem pasta), enfileiramento, processamento em lote
 * e reprocessamento.
 * @version 1.0.0
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import type { DriveSyncResumo, DriveSyncLote } from '../../types/domain'

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type: string }): void
}>()

// Cada pasta criada é uma chamada à API do Google: o lote pode demorar.
const PROCESSAR_TIMEOUT_MS = 5 * 60 * 1000
const POR_PAGINA = 25

type Aba = 'fila' | 'clientes' | 'processos'

const subAbas: { id: Aba; rotulo: string; icone: string }[] = [
  { id: 'fila', rotulo: 'Fila de sincronização', icone: 'bi bi-list-task' },
  { id: 'clientes', rotulo: 'Clientes', icone: 'bi bi-person' },
  { id: 'processos', rotulo: 'Processos', icone: 'bi bi-folder' }
]

const resumo = ref<DriveSyncResumo | null>(null)
const loadingResumo = ref(false)
const loading = ref(false)
const processando = ref(false)
const acaoEmCurso = ref(false)
const ultimoLote = ref<DriveSyncLote | null>(null)

const aba = ref<Aba>('fila')
const itens = ref<any[]>([])
const total = ref(0)
const pagina = ref(1)
const totalPaginas = ref(1)
const busca = ref('')
const filtroStatus = ref('')
const filtroTipo = ref('')
const filtroSituacao = ref('')
const selecionados = ref<Set<number>>(new Set())

let timerBusca: ReturnType<typeof setTimeout> | undefined
let requisicao = 0

const ocupado = computed(() => processando.value || acaoEmCurso.value)
const temSemPasta = computed(() => !!resumo.value && (resumo.value.clientes.sem_job + resumo.value.processos.sem_job) > 0)
const todosMarcados = computed(() => itens.value.length > 0 && itens.value.every(i => selecionados.value.has(Number(chave(i)))))

const cartoes = computed(() => {
  const r = resumo.value
  if (!r) return []
  return [
    { rotulo: 'Pendentes', valor: r.fila.pending },
    { rotulo: 'Concluídos', valor: r.fila.completed },
    { rotulo: 'Com falha', valor: r.fila.failed, cor: r.fila.failed ? 'var(--danger)' : undefined },
    { rotulo: 'Clientes sem pasta', valor: `${r.clientes.sem_pasta} / ${r.clientes.total}` },
    { rotulo: 'Processos sem pasta', valor: `${r.processos.sem_pasta} / ${r.processos.total}` },
    { rotulo: 'Sem job na fila', valor: r.clientes.sem_job + r.processos.sem_job }
  ]
})

function chave(i: any): number {
  return i.id
}

/** Link (hash) para a tela da entidade; abre em outra guia. */
function linkEntidade(tipo: string, id: number | string): string {
  return tipo === 'cliente' ? `#/clientes/editar/${id}` : `#/processos/detalhes/${id}`
}

function rotuloStatus(s: string): string {
  return ({ pending: 'Pendente', processing: 'Processando', completed: 'Concluído', failed: 'Falhou' } as Record<string, string>)[s] ?? s
}

function classeStatus(s: string): string {
  return s === 'completed' ? 'badge-success' : s === 'failed' ? 'badge-danger' : 'badge-warning'
}

function mensagemErro(err: any, padrao: string): string {
  return err?.data?.message || err?.message || padrao
}

// ── Carregamento ─────────────────────────────────────────────
async function carregarResumo() {
  loadingResumo.value = true
  try {
    const res = await apiFetch('drive/resumo')
    if (res?.success) resumo.value = res.data as DriveSyncResumo
  } catch (err: any) {
    emit('toast', { msg: mensagemErro(err, 'Erro ao carregar o resumo do Drive.'), type: 'error' })
  } finally {
    loadingResumo.value = false
  }
}

async function carregarLista() {
  const id = ++requisicao
  loading.value = true
  const params = new URLSearchParams({ pagina: String(pagina.value), por_pagina: String(POR_PAGINA) })
  if (busca.value.trim()) params.set('busca', busca.value.trim())

  let endpoint: string
  if (aba.value === 'fila') {
    if (filtroStatus.value) params.set('status', filtroStatus.value)
    if (filtroTipo.value) params.set('tipo', filtroTipo.value)
    endpoint = 'drive/jobs'
  } else {
    params.set('tipo', aba.value === 'clientes' ? 'cliente' : 'processo')
    if (filtroSituacao.value) params.set('situacao', filtroSituacao.value)
    endpoint = 'drive/entidades'
  }

  try {
    const res = await apiFetch(`${endpoint}?${params}`)
    if (id !== requisicao) return // resposta de uma consulta antiga
    if (res?.success) {
      itens.value = res.data.itens
      total.value = res.data.total
      totalPaginas.value = res.data.total_paginas
    }
  } catch (err: any) {
    if (id === requisicao) emit('toast', { msg: mensagemErro(err, 'Erro ao carregar a lista.'), type: 'error' })
  } finally {
    if (id === requisicao) loading.value = false
  }
}

async function atualizarTudo() {
  await Promise.all([carregarResumo(), carregarLista()])
}

function trocarAba(nova: Aba) {
  aba.value = nova
  busca.value = ''
  filtroStatus.value = ''
  filtroTipo.value = ''
  filtroSituacao.value = nova === 'fila' ? '' : 'sem_pasta'
  irParaPagina(1)
}

function irParaPagina(p: number) {
  pagina.value = p
  selecionados.value = new Set()
  carregarLista()
}

function buscarComAtraso() {
  clearTimeout(timerBusca)
  timerBusca = setTimeout(() => irParaPagina(1), 350)
}

// ── Seleção ──────────────────────────────────────────────────
function alternar(id: number) {
  const s = new Set(selecionados.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selecionados.value = s
}

function alternarTodos() {
  selecionados.value = todosMarcados.value ? new Set() : new Set(itens.value.map(i => Number(chave(i))))
}

// ── Ações ────────────────────────────────────────────────────
async function executar(endpoint: string, body: Record<string, unknown>, erroPadrao: string, timeout?: number) {
  acaoEmCurso.value = true
  try {
    const res = await apiFetch(endpoint, { method: 'POST', body, ...(timeout ? { timeout } : {}) })
    emit('toast', { msg: res?.message || 'Concluído.', type: res?.success ? 'success' : 'error' })
    return res
  } catch (err: any) {
    emit('toast', { msg: mensagemErro(err, erroPadrao), type: 'error' })
    return null
  } finally {
    acaoEmCurso.value = false
    selecionados.value = new Set()
    await atualizarTudo()
  }
}

async function enfileirarPendentes() {
  if (!confirm('Enfileirar todos os clientes e processos que ainda não têm pasta?')) return
  await executar('drive/sincronizar-retroativo', {}, 'Erro ao enfileirar pendências.')
}

async function processar() {
  processando.value = true
  try {
    const res = await apiFetch('drive/processar', { method: 'POST', body: { limite: 20 }, timeout: PROCESSAR_TIMEOUT_MS })
    ultimoLote.value = res?.data ?? null
    emit('toast', { msg: res?.message || 'Lote processado.', type: res?.data?.falhas ? 'warning' : 'success' })
  } catch (err: any) {
    emit('toast', { msg: mensagemErro(err, 'Erro ao processar a fila.'), type: 'error' })
  } finally {
    processando.value = false
    await atualizarTudo()
  }
}

async function reprocessarStatus(status: 'failed' | 'processing') {
  await executar('drive/jobs/reprocessar', { status }, 'Erro ao reprocessar jobs.')
}

async function excluirStatus(status: 'completed') {
  if (!confirm('Remover da fila todos os jobs concluídos? As pastas já criadas não são afetadas.')) return
  await executar('drive/jobs/excluir', { status }, 'Erro ao limpar jobs.')
}

async function reprocessarIds(ids: number[]) {
  await executar('drive/jobs/reprocessar', { ids }, 'Erro ao reprocessar jobs.')
}

async function excluirIds(ids: number[]) {
  if (!confirm(`Remover ${ids.length} job(s) da fila?`)) return
  await executar('drive/jobs/excluir', { ids }, 'Erro ao remover jobs.')
}

const reprocessarSelecionados = () => reprocessarIds([...selecionados.value])
const excluirSelecionados = () => excluirIds([...selecionados.value])

async function enfileirarIds(ids: number[]) {
  await executar('drive/enfileirar', { tipo: aba.value === 'clientes' ? 'cliente' : 'processo', ids }, 'Erro ao enfileirar.')
}

const enfileirarSelecionados = () => enfileirarIds([...selecionados.value])

onMounted(atualizarTudo)
onBeforeUnmount(() => clearTimeout(timerBusca))
</script>

<style scoped>
.entidade-link {
  color: var(--accent);
  text-decoration: none;
  font-weight: 600;
  border-bottom: 1px solid transparent;
  transition: border-color 0.15s, opacity 0.15s;
}
.entidade-link i {
  font-size: 0.7rem;
  opacity: 0.6;
  margin-left: 2px;
}
.entidade-link:hover {
  border-bottom-color: currentColor;
}
.entidade-link:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: 2px;
}
</style>
