<template>
  <div>
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-graph-up" style="color: var(--accent);"></i> Índices econômicos
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
        Séries usadas na correção monetária e nos juros das liquidações. Uma competência faltando impede o cálculo
        daquele período; complete a série pelo Banco Central ou importe um CSV (colunas <code>competencia</code> e <code>valor</code>).
      </p>
      <button class="btn btn-outline-primary" :disabled="atualizando" @click="atualizarTodos">
        <i class="bi bi-arrow-repeat"></i> {{ atualizando ? 'Atualizando...' : 'Atualizar pelo Banco Central' }}
      </button>
    </div>

    <p v-if="erro" class="idx-erro" role="alert">{{ erro }}</p>
    <div v-else-if="carregando" class="skeleton-line" style="width: 60%; height: 40px;"></div>

    <div v-else class="card animate-fade-in" style="padding: 20px;">
      <h4 style="margin:0 0 12px;">Séries cadastradas</h4>
      <div style="overflow-x:auto;">
        <table class="table-premium idx-table">
          <thead>
            <tr>
              <th>Índice</th>
              <th>Fonte</th>
              <th>Periodicidade</th>
              <th>Última competência</th>
              <th>Situação</th>
              <th class="idx-acoes">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in indices" :key="i.id_indice">
              <td><strong>{{ i.codigo }}</strong><small class="idx-nome">{{ i.nome }}</small></td>
              <td>{{ FONTES[i.fonte] ?? i.fonte }}</td>
              <td>{{ i.periodicidade === 'diaria' ? 'Diária' : 'Mensal' }}</td>
              <td>{{ i.ultima_competencia || '—' }}</td>
              <td>
                <span v-if="i.atrasado" class="badge badge-warning" title="A série não cobre o mês corrente">Atrasado</span>
                <span v-else-if="i.lacunas" class="badge badge-danger">{{ i.lacunas }} lacuna(s)</span>
                <span v-else class="badge badge-success">Completo</span>
              </td>
              <td class="idx-acoes">
                <button v-if="i.lacunas" class="btn btn-ghost btn-sm" @click="verLacunas(i)">Ver lacunas</button>
                <label class="btn btn-ghost btn-sm" style="margin:0;" :aria-label="`Importar CSV de ${i.codigo}`">
                  <i class="bi bi-upload"></i> CSV
                  <input type="file" accept=".csv,text/csv" hidden @change="(e) => importar(i, e)" />
                </label>
              </td>
            </tr>
            <tr v-if="!indices.length">
              <td colspan="6" class="idx-vazio">Nenhum índice cadastrado.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="lacunas" class="modal-backdrop" @click.self="lacunas = null">
      <div class="modal-box" role="dialog" aria-modal="true" :aria-label="`Lacunas de ${lacunas.codigo}`">
        <div class="modal-header">
          <h2>Competências faltando em {{ lacunas.codigo }}</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="lacunas = null"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" style="padding:1.25rem;">
          <p v-if="!lacunas.lista.length">Nenhuma lacuna.</p>
          <ul v-else class="idx-lacunas">
            <li v-for="c in lacunas.lista" :key="c">{{ c }}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { atualizarIndicesSgs, importarCsvIndice, lacunasDoIndice, listarIndices } from '../../services/liquidacoesApi'
import type { IndiceEconomico } from '../../types/liquidacao'

const emit = defineEmits<{ (e: 'toast', payload: { msg: string; type: 'success' | 'error' }): void }>()

const FONTES: Record<string, string> = { sgs: 'Banco Central (SGS)', manual: 'Manual', csv: 'CSV' }

const indices = ref<IndiceEconomico[]>([])
const carregando = ref(false)
const atualizando = ref(false)
const erro = ref('')
const lacunas = ref<{ codigo: string; lista: string[] } | null>(null)

const mensagem = (e: unknown, padrao: string) => (e instanceof Error && e.message ? e.message : padrao)

async function carregar() {
  carregando.value = true
  erro.value = ''
  try {
    indices.value = await listarIndices()
  } catch (e) {
    erro.value = mensagem(e, 'Não foi possível carregar os índices.')
  } finally {
    carregando.value = false
  }
}

async function verLacunas(i: IndiceEconomico) {
  try {
    lacunas.value = { codigo: i.codigo, lista: await lacunasDoIndice(i.codigo) }
  } catch (e) {
    emit('toast', { msg: mensagem(e, 'Erro ao buscar as lacunas.'), type: 'error' })
  }
}

async function importar(i: IndiceEconomico, ev: Event) {
  const input = ev.target as HTMLInputElement
  const arquivo = input.files?.[0]
  input.value = ''
  if (!arquivo) return
  try {
    const r = await importarCsvIndice(i.codigo, arquivo)
    emit('toast', { msg: `${i.codigo}: ${r?.novos ?? 0} novo(s), ${r?.atualizados ?? 0} atualizado(s).`, type: 'success' })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagem(e, 'Erro ao importar o CSV.'), type: 'error' })
  }
}

async function atualizarTodos() {
  atualizando.value = true
  try {
    const r = await atualizarIndicesSgs()
    const falhas = Object.entries(r ?? {}).filter(([, v]) => v.status !== 'ok')
    emit('toast', {
      msg: falhas.length ? `Atualização incompleta: ${falhas.map(([c]) => c).join(', ')}.` : 'Índices atualizados.',
      type: falhas.length ? 'error' : 'success',
    })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagem(e, 'Erro ao atualizar os índices.'), type: 'error' })
  } finally {
    atualizando.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.idx-erro { color: var(--danger); }
.idx-table { width: 100%; border-collapse: collapse; text-align: left; }
.idx-table th { padding: 10px 12px; border-bottom: 2px solid var(--border); font-weight: 600; white-space: nowrap; }
.idx-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.idx-nome { display: block; color: var(--ink-muted); font-size: 0.8rem; }
.idx-acoes { text-align: right; white-space: nowrap; }
.idx-vazio { padding: 16px; text-align: center; color: var(--ink-muted); }
.idx-lacunas { columns: 3; margin: 0; padding-left: 1.2rem; }
</style>
