<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Consulta de processos judiciais</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Processos judiciais</h1>
        <p>Liste as ações e processos em que uma pessoa física figura como parte, a partir do CPF</p>
      </div>
    </div>

    <ConsultasNav atual="consulta-processos" :homolog="homolog" @set-view="(v: string) => $emit('set-view', v)" />

    <div class="card search-card">
      <div class="card-body">
        <form class="search-form" @submit.prevent="consultar">
          <div class="form-group">
            <label for="proc-cpf"><i class="bi bi-person-vcard"></i> CPF</label>
            <input id="proc-cpf" type="text" class="form-control" v-model="cpf" @input="cpf = formatarCpf(cpf)"
              placeholder="000.000.000-00" inputmode="numeric" maxlength="14" autocomplete="off" />
          </div>
          <div class="form-group">
            <label for="proc-cnpj"><i class="bi bi-building"></i> CNPJ (opcional)</label>
            <input id="proc-cnpj" type="text" class="form-control" v-model="cnpj" @input="cnpj = formatarCnpj(cnpj)"
              placeholder="00.000.000/0000-00" inputmode="numeric" maxlength="18" autocomplete="off" />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="loading || !cpfValido || !cnpjValido">
            <i class="bi" :class="loading ? 'bi-arrow-repeat spin-icon' : 'bi-search'"></i>
            {{ loading ? 'Consultando...' : 'Consultar' }}
          </button>
          <button v-if="resultado || erro" type="button" class="btn btn-ghost" @click="limpar">
            <i class="bi bi-x-circle"></i> Limpar
          </button>
        </form>
      </div>
    </div>

    <div v-if="erro" class="empty-state error-state">
      <i class="bi bi-exclamation-triangle"></i>
      <h4>Não foi possível concluir a consulta</h4>
      <p>{{ erro }}</p>
    </div>

    <div v-if="resultado && !loading" class="report-wrap">
      <div v-if="resultado.cadastral?.dadosCadastrais" class="card">
        <div class="card-header"><h3><i class="bi bi-person-lines-fill"></i> Pessoa consultada</h3></div>
        <div class="card-body info-list">
          <div class="info-row"><span class="info-label">Nome</span><span class="info-value">{{ resultado.cadastral.dadosCadastrais.nome || '—' }}</span></div>
          <div class="info-row"><span class="info-label">Nome da mãe</span><span class="info-value">{{ resultado.cadastral.dadosCadastrais.nomeMae || '—' }}</span></div>
          <div class="info-row"><span class="info-label">Data de nascimento</span><span class="info-value">{{ resultado.cadastral.dadosCadastrais.dataNascimento || '—' }}</span></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header importar-header">
          <h3><i class="bi bi-bank"></i> Processos encontrados ({{ resultado.processos.length }})</h3>
          <div v-if="podeImportar && importaveis.length" class="importar-acoes">
            <button type="button" class="btn btn-sm btn-ghost" @click="selecionarTodos(!todosSelecionados)">
              <i class="bi" :class="todosSelecionados ? 'bi-square' : 'bi-check2-square'"></i>
              {{ todosSelecionados ? 'Desmarcar todos' : 'Marcar todos' }}
            </button>
            <button type="button" class="btn btn-sm btn-primary" :disabled="!selecionados.size" @click="importarSelecionados">
              <i class="bi bi-box-arrow-in-down"></i> Importar selecionados ({{ selecionados.size }})
            </button>
          </div>
        </div>
        <p v-if="podeImportar && importaveis.length" class="importar-hint">
          Marque os processos que deseja cadastrar na carteira. Antes de gravar, os dados são conferidos com o DJEN,
          o DataJud e os processos já cadastrados. Até {{ MAX_IMPORTACAO }} processos por vez.
        </p>
        <div v-if="!resultado.processos.length" class="card-body">
          <p class="muted">Nenhum processo encontrado para este CPF.</p>
        </div>
        <div v-else class="card-body processos-list">
          <article v-for="(p, i) in resultado.processos" :key="p.numeroProcessoUnico || i" class="processo-item">
            <header>
              <input v-if="podeImportar && chaveImportavel(p)" type="checkbox" class="importar-check"
                :checked="selecionados.has(chaveImportavel(p)!)" @change="alternar(p)"
                :aria-label="`Selecionar o processo ${formatarNumeroCnj(p.numeroProcessoUnico || '')} para importar`" />
              <strong class="numero">{{ formatarNumeroCnj(p.numeroProcessoUnico || '') || 'Sem número' }}</strong>
              <span class="badge" :class="statusClasse(p)">{{ p.statusPj?.statusProcesso || 'Situação não informada' }}</span>
              <span v-if="p.segredoJustica" class="badge badge-warning">Segredo de justiça</span>
            </header>
            <div class="info-list">
              <div class="info-row"><span class="info-label">Tribunal / UF</span><span class="info-value">{{ [p.tribunal, p.uf].filter(Boolean).join(' / ') || '—' }}</span></div>
              <div class="info-row"><span class="info-label">Órgão julgador</span><span class="info-value">{{ p.orgaoJulgador || '—' }}</span></div>
              <div class="info-row"><span class="info-label">Classe</span><span class="info-value">{{ p.classeProcessual?.nome || '—' }}</span></div>
              <div class="info-row"><span class="info-label">Assunto principal</span><span class="info-value">{{ assuntoPrincipal(p) }}</span></div>
              <div class="info-row"><span class="info-label">Posição do consultado</span><span class="info-value">{{ posicao(p) }}</span></div>
              <div class="info-row"><span class="info-label">Distribuição</span><span class="info-value">{{ formatDate(p.dataDistribuicao) }}</span></div>
              <div class="info-row"><span class="info-label">Valor da causa</span><span class="info-value">{{ p.valorCausa?.valor ? formatCurrency(p.valorCausa.valor) : '—' }}</span></div>
            </div>
            <details v-if="p.partes?.length" class="partes">
              <summary>Partes ({{ p.partes.length }})</summary>
              <ul>
                <li v-for="(parte, j) in p.partes" :key="j">{{ parte.nome }} — {{ [parte.polo, parte.tipo].filter(Boolean).join(' / ') }}</li>
              </ul>
            </details>
            <a v-if="linkSeguro(p.urlProcesso)" :href="p.urlProcesso" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost">
              <i class="bi bi-box-arrow-up-right"></i> Abrir no tribunal
            </a>
          </article>
        </div>
      </div>

      <div class="card billing-card">
        <div class="card-body billing-body">
          <div class="billing-item"><span class="info-label">Valor cobrado</span><span class="info-value">{{ formatCurrency(resultado.cobranca.valor_consulta) }}</span></div>
          <div class="billing-item"><span class="info-label">Saldo atual</span><span class="info-value">{{ resultado.cobranca.balance ?? '—' }}</span></div>
          <div class="billing-message" v-if="resultado.cobranca.message"><i class="bi bi-info-circle"></i> {{ resultado.cobranca.message }}</div>
        </div>
      </div>
    </div>

    <div v-if="!resultado && !loading && !erro" class="empty-state">
      <i class="bi bi-bank"></i>
      <h4>Nenhuma consulta realizada</h4>
      <p>Informe um CPF acima para listar ações e processos judiciais.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { formatDate, formatCurrency } from '../../services/api'
import ConsultasNav from '../../components/Consultas/ConsultasNav.vue'
import {
  consultarProcessos, homologAtivo, formatarCpf, formatarCnpj, formatarNumeroCnj, somenteDigitos,
  linkSeguro, processoParaImportar, enviarParaImportacao,
  type ResultadoProcessos, type ProcessoJudicial,
} from '../../services/consultasApi'
import { pode } from '../../composables/useAcesso'

const emit = defineEmits(['set-view', 'toast'])

/** Mesmo limite da importação em lote. */
const MAX_IMPORTACAO = 50

const cpf = ref('')
const cnpj = ref('')
const loading = ref(false)
const erro = ref('')
const resultado = ref<ResultadoProcessos | null>(null)
const homolog = ref(false)

const cpfValido = computed(() => somenteDigitos(cpf.value).length === 11)
const cnpjValido = computed(() => !cnpj.value || somenteDigitos(cnpj.value).length === 14)

onMounted(async () => { homolog.value = await homologAtivo() })

function assuntoPrincipal(p: ProcessoJudicial) {
  const a = p.assuntosCNJ?.find((x) => x.principal) ?? p.assuntosCNJ?.[0]
  return a?.titulo || '—'
}

function posicao(p: ProcessoJudicial) {
  const i = p.informacoesPartePessoaConsultada?.[0]
  return i ? [i.polo, i.tipo].filter(Boolean).join(' / ') : '—'
}

function statusClasse(p: ProcessoJudicial) {
  const s = (p.statusPj?.statusProcesso || '').toUpperCase()
  if (s.includes('TRAMITA')) return 'badge-warning'
  if (s.includes('ARQUIV')) return 'badge-muted'
  return 'badge-muted'
}

// ------------------------------------------------- importação para a carteira

const podeImportar = computed(() => pode('processos.create'))
/** Números CNJ (20 dígitos) marcados para importar. */
const selecionados = ref(new Set<string>())
/** CPF da consulta exibida (o campo pode ter sido editado depois). */
const cpfConsultado = ref('')

/** Número CNJ do processo, ou null se não tiver os 20 dígitos (não dá para importar). */
function chaveImportavel(p: ProcessoJudicial): string | null {
  const n = somenteDigitos(p.numeroProcessoUnico || '')
  return n.length === 20 ? n : null
}

const importaveis = computed(() => {
  const vistos = new Set<string>()
  return (resultado.value?.processos || []).filter((p) => {
    const n = chaveImportavel(p)
    if (!n || vistos.has(n)) return false
    vistos.add(n)
    return true
  })
})

const todosSelecionados = computed(() =>
  importaveis.value.length > 0 &&
  importaveis.value.slice(0, MAX_IMPORTACAO).every((p) => selecionados.value.has(chaveImportavel(p)!)))

function alternar(p: ProcessoJudicial) {
  const n = chaveImportavel(p)
  if (!n) return
  const novo = new Set(selecionados.value)
  if (novo.has(n)) {
    novo.delete(n)
  } else if (novo.size >= MAX_IMPORTACAO) {
    emit('toast', { msg: `Limite de ${MAX_IMPORTACAO} processos por vez. Importe os demais em seguida.`, type: 'warning' })
    return
  } else {
    novo.add(n)
  }
  selecionados.value = novo
}

function selecionarTodos(marcar: boolean) {
  if (!marcar) {
    selecionados.value = new Set()
    return
  }
  const lista = importaveis.value.map((p) => chaveImportavel(p)!)
  selecionados.value = new Set(lista.slice(0, MAX_IMPORTACAO))
  if (lista.length > MAX_IMPORTACAO) {
    emit('toast', { msg: `Limite de ${MAX_IMPORTACAO} processos por vez: ${lista.length - MAX_IMPORTACAO} ficaram de fora. Importe-os em seguida.`, type: 'warning' })
  }
}

/** Leva os processos marcados para a conferência da importação em lote. */
function importarSelecionados() {
  const lista = importaveis.value
    .filter((p) => selecionados.value.has(chaveImportavel(p)!))
    .map((p) => processoParaImportar(p, cpfConsultado.value))
    .filter((p): p is NonNullable<typeof p> => p !== null)
  if (!lista.length) return
  enviarParaImportacao(lista)
  emit('set-view', 'importacao-processos')
}

async function consultar() {
  if (!cpfValido.value || !cnpjValido.value) return
  loading.value = true
  erro.value = ''
  resultado.value = null
  selecionados.value = new Set()
  cpfConsultado.value = somenteDigitos(cpf.value)
  try {
    resultado.value = await consultarProcessos(cpf.value, cnpj.value)
  } catch (err: any) {
    erro.value = err?.message || 'Erro ao consultar. Tente novamente.'
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    loading.value = false
  }
}

function limpar() {
  cpf.value = ''
  cnpj.value = ''
  resultado.value = null
  erro.value = ''
  selecionados.value = new Set()
  cpfConsultado.value = ''
}
</script>

<style scoped>
.search-card { margin-bottom: 20px; }
.search-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.search-form .form-group { margin-bottom: 0; min-width: 220px; }
.spin-icon { animation: spin 0.8s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state i { color: var(--danger); }
.report-wrap { margin-top: 20px; display: flex; flex-direction: column; gap: 20px; }
.info-list { display: flex; flex-direction: column; gap: 10px; }
.info-row { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--border); }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-label { color: var(--ink-muted); font-size: 0.82rem; }
.info-value { color: var(--ink); font-weight: 600; font-size: 0.9rem; text-align: right; }
.muted { color: var(--ink-muted); }
.processos-list { display: flex; flex-direction: column; gap: 16px; }
.processo-item { border: 1px solid var(--border); border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.processo-item header { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.numero { font-family: var(--font-head); color: var(--primary); }
.importar-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.importar-acoes { display: flex; flex-wrap: wrap; gap: 8px; }
.importar-hint { margin: 12px 20px 0; color: var(--ink-muted); font-size: 0.85rem; }
.importar-check { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
.partes ul { margin: 8px 0 0 18px; font-size: 0.85rem; color: var(--ink-dim); }
.billing-body { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; }
.billing-item { display: flex; flex-direction: column; gap: 2px; }
.billing-message { margin-left: auto; color: var(--ink-muted); font-size: 0.85rem; font-style: italic; }
</style>
