<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Consulta de CPF</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Consulta de CPF</h1>
        <p>Consulte dados cadastrais, endereço e informações de crédito de uma pessoa física pelo CPF</p>
      </div>
      <button v-if="resultado" class="btn btn-ghost" @click="imprimirRelatorio">
        <i class="bi bi-printer"></i> Imprimir Relatório
      </button>
    </div>

    <ConsultasNav atual="consulta-cpf" :homolog="homolog" @set-view="(v: string) => emit('set-view', v)" />

    <!-- Search Card -->
    <div class="card search-card">
      <div class="card-body">
        <form class="search-form" @submit.prevent="consultar">
          <div class="form-group search-input-group">
            <label for="cpf-input"><i class="bi bi-person-vcard"></i> CPF</label>
            <input
              id="cpf-input"
              type="text"
              class="form-control"
              v-model="cpfInput"
              @input="formatCpfInput"
              placeholder="000.000.000-00"
              inputmode="numeric"
              maxlength="14"
              autocomplete="off"
            />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="loading || !cpfIsValid">
            <i class="bi" :class="loading ? 'bi-arrow-repeat spin-icon' : 'bi-search'"></i>
            {{ loading ? 'Consultando...' : 'Consultar' }}
          </button>
          <button v-if="resultado || erro" type="button" class="btn btn-ghost" @click="limpar">
            <i class="bi bi-x-circle"></i> Limpar
          </button>
        </form>
      </div>
    </div>

    <!-- Error state -->
    <div v-if="erro" class="empty-state error-state">
      <i class="bi bi-exclamation-triangle"></i>
      <h4>Não foi possível concluir a consulta</h4>
      <p>{{ erro }}</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="skeleton-wrap">
      <div class="skeleton-card" v-for="i in 3" :key="i">
        <div class="skeleton-line" style="width: 100%; height: 70px;"></div>
      </div>
    </div>

    <!-- Report -->
    <div v-if="resultado && !loading" class="report-wrap">
      <!-- CPF já cadastrado: sugere enriquecer com esta consulta (sem nova cobrança) -->
      <div v-for="c in cadastros" :key="c.tipo + c.id" class="cadastro-alert">
        <i class="bi bi-person-check-fill cadastro-alert-icone"></i>
        <div class="cadastro-alert-texto">
          <div>
            Este CPF é de um <strong>{{ c.tipo === 'cliente' ? 'cliente' : 'fornecedor' }}</strong> cadastrado:
            <strong>{{ c.nome }}</strong>.
          </div>
          <small v-if="c.pode_editar">Aproveite esta consulta para completar o cadastro, sem nova cobrança.</small>
        </div>
        <div class="cadastro-alert-acoes">
          <button type="button" class="btn btn-sm btn-ghost" @click="abrirCadastro(c)">
            <i class="bi bi-box-arrow-up-right"></i> Abrir cadastro
          </button>
          <button v-if="c.pode_editar" type="button" class="btn btn-sm btn-primary" @click="enriquecerAlvo = c">
            <i class="bi bi-stars"></i> Enriquecer cadastro
          </button>
        </div>
      </div>

      <!-- Identity summary card -->
      <div class="card identity-card">
        <div class="card-body identity-body">
          <div class="identity-main">
            <h2>{{ resultado.nome || '—' }}</h2>
            <div v-if="resultado.nome_social" class="identity-social">Nome social: {{ resultado.nome_social }}</div>
            <div class="identity-badges">
              <span class="badge" :class="docStatusBadgeClass">
                <i class="bi bi-file-earmark-check"></i> {{ resultado.documento?.status || 'Status desconhecido' }}
              </span>
              <span v-if="resultado.idade" class="badge badge-muted">
                <i class="bi bi-calendar3"></i> {{ resultado.idade }} anos
              </span>
              <span v-if="resultado.genero" class="badge badge-muted">
                <i class="bi bi-gender-ambiguous"></i> {{ resultado.genero }}
              </span>
              <span v-if="resultado.indicadores?.pep" class="badge badge-warning">
                <i class="bi bi-flag"></i> Pessoa Politicamente Exposta
              </span>
              <span v-if="resultado.indicadores?.obito" class="badge badge-danger">
                <i class="bi bi-exclamation-octagon"></i> Óbito registrado
              </span>
              <span v-if="resultado.indicadores?.agente_publico" class="badge badge-warning">
                <i class="bi bi-bank2"></i> Agente Público
              </span>
            </div>
          </div>
          <div class="identity-doc">
            <span class="info-label">CPF</span>
            <span class="info-value doc-value">{{ formatDocument(resultado.documento?.numero) }}</span>
          </div>
        </div>
      </div>

      <div class="report-grid">
        <!-- Dados Pessoais -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-person-lines-fill"></i> Dados Pessoais</h3>
          </div>
          <div class="card-body info-list">
            <div class="info-row">
              <span class="info-label">Nome da mãe</span>
              <span class="info-value">{{ resultado.nome_mae || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Data de nascimento</span>
              <span class="info-value">{{ formatDate(resultado.data_nascimento) }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Gênero</span>
              <span class="info-value">{{ resultado.genero || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Ocupação</span>
              <span class="info-value">{{ resultado.classificacao_profissao_natureza_ocupacao || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Documento -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-file-earmark-person"></i> Documento</h3>
          </div>
          <div class="card-body info-list">
            <div class="info-row">
              <span class="info-label">Tipo</span>
              <span class="info-value">{{ resultado.documento?.tipo || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Número</span>
              <span class="info-value">{{ formatDocument(resultado.documento?.numero) }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Situação</span>
              <span class="info-value">{{ resultado.documento?.status || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Última atualização</span>
              <span class="info-value">{{ formatDate(resultado.documento?.ultima_atualizacao) }}</span>
            </div>
          </div>
        </div>

        <!-- Endereço -->
        <div class="card address-card">
          <div class="card-header">
            <h3><i class="bi bi-geo-alt-fill"></i> Endereço</h3>
            <button v-if="enderecoCompleto" class="btn btn-sm btn-ghost" @click="copiarEndereco" title="Copiar endereço">
              <i class="bi bi-clipboard"></i> Copiar
            </button>
          </div>
          <div class="card-body">
            <p v-if="enderecoCompleto" class="address-line">{{ enderecoCompleto }}</p>
            <p v-else class="address-line empty">Endereço não informado.</p>
            <div class="info-list address-details">
              <div class="info-row">
                <span class="info-label">Bairro</span>
                <span class="info-value">{{ resultado.endereco?.bairro || '—' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Cidade / UF</span>
                <span class="info-value">{{ cidadeUf }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">CEP</span>
                <span class="info-value">{{ formatCep(resultado.endereco?.cep) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Financeiro -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-cash-coin"></i> Perfil Financeiro</h3>
          </div>
          <div class="card-body info-list">
            <div class="info-row">
              <span class="info-label">Faixa de renda</span>
              <span class="info-value">{{ resultado.financeiro?.faixa_renda_descricao || 'Não informado' }}</span>
            </div>
            <div class="info-row" v-if="resultado.financeiro?.faixa_renda_valor">
              <span class="info-label">Renda estimada</span>
              <span class="info-value">{{ formatCurrency(resultado.financeiro.faixa_renda_valor) }}</span>
            </div>
          </div>
        </div>

        <!-- Indicadores -->
        <div class="card indicators-card">
          <div class="card-header">
            <h3><i class="bi bi-shield-check"></i> Indicadores de Compliance</h3>
          </div>
          <div class="card-body indicators-grid">
            <div v-for="ind in indicadoresList" :key="ind.key" class="indicator-item">
              <span class="indicator-label">{{ ind.label }}</span>
              <span class="badge" :class="indicatorBadgeClass(ind.value)">
                <i class="bi" :class="indicatorIcon(ind.value)"></i> {{ indicatorText(ind.value) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Ocorrências / Listas Restritivas -->
        <div class="card occurrences-card">
          <div class="card-header">
            <h3><i class="bi bi-list-check"></i> Ocorrências e Listas Restritivas</h3>
          </div>
          <div class="card-body indicators-grid">
            <div v-for="oc in ocorrenciasList" :key="oc.key" class="indicator-item">
              <span class="indicator-label">{{ oc.label }}</span>
              <span class="badge" :class="oc.count > 0 ? 'badge-danger' : 'badge-success'">
                <i class="bi" :class="oc.count > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle'"></i>
                {{ oc.count > 0 ? `${oc.count} ocorrência(s)` : 'Nenhuma ocorrência' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Billing info -->
      <div class="card billing-card">
        <div class="card-body billing-body">
          <div class="billing-item">
            <span class="info-label">Valor cobrado</span>
            <span class="info-value">{{ formatCurrency(consultaInfo.valor_consulta) }}</span>
          </div>
          <div class="billing-item">
            <span class="info-label">Saldo anterior</span>
            <span class="info-value">{{ consultaInfo.balance_before ?? '—' }}</span>
          </div>
          <div class="billing-item">
            <span class="info-label">Saldo atual</span>
            <span class="info-value">{{ consultaInfo.balance ?? '—' }}</span>
          </div>
          <div class="billing-message" v-if="consultaInfo.message">
            <i class="bi bi-info-circle"></i> {{ consultaInfo.message }}
          </div>
        </div>
      </div>
    </div>

    <!-- Empty initial state -->
    <div v-if="!resultado && !loading && !erro" class="empty-state">
      <i class="bi bi-person-vcard"></i>
      <h4>Nenhuma consulta realizada</h4>
      <p>Informe um CPF acima para consultar dados cadastrais e endereço.</p>
    </div>

    <ModalEnriquecerCadastro
      v-if="enriquecerAlvo"
      :show="true"
      :tipo="enriquecerAlvo.tipo"
      :id="enriquecerAlvo.id"
      :nome="enriquecerAlvo.nome"
      :documento="cpfConsultado"
      :resultado-cpf="resultado"
      @close="enriquecerAlvo = null"
      @toast="(t) => emit('toast', t)"
    />

    <!-- Print View -->
    <Teleport to="body">
      <div v-if="printing" class="print-area-global">
        <ReportHeader title="Relatório de Consulta de CPF" :subtitle="resultado?.nome || ''" />

        <div class="print-report">
          <!-- Identity block -->
          <div class="print-identity">
            <div class="print-identity-main">
              <h3>{{ resultado?.nome || '—' }}</h3>
              <div v-if="resultado?.nome_social" class="print-muted">Nome social: {{ resultado.nome_social }}</div>
              <div class="print-tags">
                <span class="print-tag">{{ resultado?.documento?.status || 'Status desconhecido' }}</span>
                <span v-if="resultado?.idade" class="print-tag print-tag-muted">{{ resultado.idade }} anos</span>
                <span v-if="resultado?.genero" class="print-tag print-tag-muted">{{ resultado.genero }}</span>
                <span v-if="resultado?.indicadores?.pep" class="print-tag print-tag-warn">Pessoa Politicamente Exposta</span>
                <span v-if="resultado?.indicadores?.obito" class="print-tag print-tag-danger">Óbito registrado</span>
                <span v-if="resultado?.indicadores?.agente_publico" class="print-tag print-tag-warn">Agente Público</span>
              </div>
            </div>
            <div class="print-identity-doc">
              <span class="print-label">CPF</span>
              <span class="print-doc-value">{{ formatDocument(resultado?.documento?.numero) }}</span>
            </div>
          </div>

          <!-- Dados Pessoais / Documento -->
          <div class="print-section-grid">
            <div class="print-section">
              <h4>Dados Pessoais</h4>
              <div class="print-info-row"><span class="print-label">Nome da mãe</span><span>{{ resultado?.nome_mae || '—' }}</span></div>
              <div class="print-info-row"><span class="print-label">Data de nascimento</span><span>{{ formatDate(resultado?.data_nascimento) }}</span></div>
              <div class="print-info-row"><span class="print-label">Gênero</span><span>{{ resultado?.genero || '—' }}</span></div>
              <div class="print-info-row"><span class="print-label">Ocupação</span><span>{{ resultado?.classificacao_profissao_natureza_ocupacao || '—' }}</span></div>
            </div>
            <div class="print-section">
              <h4>Documento</h4>
              <div class="print-info-row"><span class="print-label">Tipo</span><span>{{ resultado?.documento?.tipo || '—' }}</span></div>
              <div class="print-info-row"><span class="print-label">Número</span><span>{{ formatDocument(resultado?.documento?.numero) }}</span></div>
              <div class="print-info-row"><span class="print-label">Situação</span><span>{{ resultado?.documento?.status || '—' }}</span></div>
              <div class="print-info-row"><span class="print-label">Última atualização</span><span>{{ formatDate(resultado?.documento?.ultima_atualizacao) }}</span></div>
            </div>
          </div>

          <!-- Endereço -->
          <div class="print-section">
            <h4>Endereço</h4>
            <p class="print-address">{{ enderecoCompleto || 'Endereço não informado.' }}</p>
            <div class="print-section-grid print-section-grid-3">
              <div class="print-info-row"><span class="print-label">Bairro</span><span>{{ resultado?.endereco?.bairro || '—' }}</span></div>
              <div class="print-info-row"><span class="print-label">Cidade / UF</span><span>{{ cidadeUf }}</span></div>
              <div class="print-info-row"><span class="print-label">CEP</span><span>{{ formatCep(resultado?.endereco?.cep) }}</span></div>
            </div>
          </div>

          <!-- Perfil Financeiro -->
          <div class="print-section">
            <h4>Perfil Financeiro</h4>
            <div class="print-info-row"><span class="print-label">Faixa de renda</span><span>{{ resultado?.financeiro?.faixa_renda_descricao || 'Não informado' }}</span></div>
            <div class="print-info-row" v-if="resultado?.financeiro?.faixa_renda_valor"><span class="print-label">Renda estimada</span><span>{{ formatCurrency(resultado.financeiro.faixa_renda_valor) }}</span></div>
          </div>

          <!-- Indicadores -->
          <div class="print-section">
            <h4>Indicadores de Compliance</h4>
            <div class="print-check-grid">
              <div v-for="ind in indicadoresList" :key="ind.key" class="print-check-row">
                <span>{{ ind.label }}</span>
                <span class="print-tag" :class="'print-tag-' + (ind.value === true ? 'danger' : ind.value === false ? 'ok' : 'muted')">{{ indicatorText(ind.value) }}</span>
              </div>
            </div>
          </div>

          <!-- Ocorrências -->
          <div class="print-section">
            <h4>Ocorrências e Listas Restritivas</h4>
            <div class="print-check-grid">
              <div v-for="oc in ocorrenciasList" :key="oc.key" class="print-check-row">
                <span>{{ oc.label }}</span>
                <span class="print-tag" :class="oc.count > 0 ? 'print-tag-danger' : 'print-tag-ok'">{{ oc.count > 0 ? `${oc.count} ocorrência(s)` : 'Nenhuma ocorrência' }}</span>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="print-footer">
            <span>Consulta realizada em {{ new Date().toLocaleString('pt-BR') }} via API Brasil.</span>
            <span>Valor cobrado: {{ formatCurrency(consultaInfo.valor_consulta) }}</span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, formatDate, formatCurrency, formatDocument } from '../../services/api'
import ReportHeader from '../../components/reports/ReportHeader.vue'
import ConsultasNav from '../../components/Consultas/ConsultasNav.vue'
import ModalEnriquecerCadastro from '../../components/Consultas/ModalEnriquecerCadastro.vue'
import { homologAtivo, cadastrosPorDocumento, type CadastroEncontrado } from '../../services/consultasApi'

const emit = defineEmits(['set-view', 'toast', 'open-client-detail', 'open-supplier-detail'])

/** Clientes/fornecedores com o CPF consultado e o alvo do modal de enriquecimento. */
const cadastros = ref<CadastroEncontrado[]>([])
const enriquecerAlvo = ref<CadastroEncontrado | null>(null)
const cpfConsultado = ref('')

async function buscarCadastros(cpf: string) {
  try {
    cadastros.value = await cadastrosPorDocumento(cpf)
  } catch (err) {
    // Sugestão é opcional: sem acesso a clientes/fornecedores, só não aparece.
    console.warn('Não foi possível verificar cadastros com este CPF:', err)
    cadastros.value = []
  }
}

function abrirCadastro(c: CadastroEncontrado) {
  emit(c.tipo === 'cliente' ? 'open-client-detail' : 'open-supplier-detail', c.id)
}

const homolog = ref(false)
onMounted(async () => { homolog.value = await homologAtivo() })

const cpfInput = ref('')
const loading = ref(false)
const erro = ref('')
const resultado = ref(null)
const printing = ref(false)
const consultaInfo = reactive({
  valor_consulta: 0,
  balance: null,
  balance_before: null,
  message: ''
})

const cpfDigits = computed(() => cpfInput.value.replace(/\D/g, ''))
const cpfIsValid = computed(() => cpfDigits.value.length === 11)

function formatCpfInput() {
  let v = cpfInput.value.replace(/\D/g, '').slice(0, 11)
  if (v.length > 9) {
    v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4')
  } else if (v.length > 6) {
    v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3')
  } else if (v.length > 3) {
    v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2')
  }
  cpfInput.value = v
}

function formatCep(cep) {
  if (!cep) return '—'
  const clean = String(cep).replace(/\D/g, '')
  return clean.length === 8 ? clean.replace(/(\d{5})(\d{3})/, '$1-$2') : cep
}

const enderecoCompleto = computed(() => {
  const e = resultado.value?.endereco
  if (!e) return ''
  const parts = []
  if (e.logradouro) parts.push(e.logradouro + (e.numero ? `, ${e.numero}` : ''))
  if (e.complemento) parts.push(e.complemento)
  if (e.bairro) parts.push(e.bairro)
  return parts.join(' - ')
})

const cidadeUf = computed(() => {
  const e = resultado.value?.endereco
  if (!e || (!e.cidade && !e.estado)) return '—'
  return [e.cidade, e.estado].filter(Boolean).join(' / ')
})

const docStatusBadgeClass = computed(() => {
  const status = (resultado.value?.documento?.status || '').toUpperCase()
  if (status === 'REGULAR') return 'badge-success'
  if (!status) return 'badge-muted'
  return 'badge-warning'
})

const INDICADORES_LABELS = {
  pep: 'Pessoa Politicamente Exposta',
  obito: 'Óbito registrado',
  agente_publico: 'Agente Público',
  beneficiario_social: 'Beneficiário de programa social',
  restricao_regulatoria: 'Restrição regulatória',
  cpf_duplicado: 'CPF duplicado',
  interdicao_judicial: 'Interdição judicial',
  paraisos_fiscais: 'Vínculo com paraísos fiscais',
  vicio_jogos: 'Indício de vício em jogos'
}

const indicadoresList = computed(() => {
  const ind = resultado.value?.indicadores || {}
  return Object.keys(INDICADORES_LABELS).map(key => ({
    key,
    label: INDICADORES_LABELS[key],
    value: ind[key]
  }))
})

function indicatorBadgeClass(value) {
  if (value === true) return 'badge-danger'
  if (value === false) return 'badge-success'
  return 'badge-muted'
}

function indicatorIcon(value) {
  if (value === true) return 'bi-exclamation-triangle'
  if (value === false) return 'bi-check-circle'
  return 'bi-dash-circle'
}

function indicatorText(value) {
  if (value === true) return 'Sim'
  if (value === false) return 'Não'
  return 'Não informado'
}

const OCORRENCIAS_LABELS = {
  ceis: 'Empresas Inidôneas e Suspensas (CEIS)',
  cnep: 'Empresas Punidas (CNEP)',
  embargos_ambientais: 'Embargos Ambientais',
  processos_criminais: 'Processos Criminais',
  participacao_sociedade_risco: 'Participação em Sociedade de Risco',
  lista_restritiva_internacional: 'Lista Restritiva Internacional',
  inelegibilidade_improbidade_administrativa: 'Improbidade Administrativa'
}

const ocorrenciasList = computed(() => {
  const r = resultado.value || {}
  return Object.keys(OCORRENCIAS_LABELS).map(key => ({
    key,
    label: OCORRENCIAS_LABELS[key],
    count: Array.isArray(r[key]) ? r[key].length : (r[key] ? 1 : 0)
  }))
})

async function consultar() {
  if (!cpfIsValid.value) return
  loading.value = true
  erro.value = ''
  resultado.value = null
  cadastros.value = []
  enriquecerAlvo.value = null

  try {
    const res = await apiFetch('consultas/cpf', {
      method: 'POST',
      body: { cpf: cpfDigits.value }
    })

    if (res?.error || res?.success === false) {
      erro.value = res?.message || 'Não foi possível concluir a consulta.'
      return
    }

    const data = res?.data
    resultado.value = data?.data?.resultado || null

    if (!resultado.value) {
      erro.value = 'A consulta não retornou dados para este CPF.'
      return
    }

    cpfConsultado.value = cpfDigits.value
    buscarCadastros(cpfDigits.value)

    consultaInfo.valor_consulta = data?.valor_consulta || 0
    consultaInfo.balance = data?.balance ?? null
    consultaInfo.balance_before = data?.balance_before ?? null
    consultaInfo.message = data?.message || ''
  } catch (err) {
    console.error('Erro ao consultar CPF:', err)
    // O corpo de erro traz `error: true`; a mensagem útil está em `message`.
    erro.value = err?.data?.message || err?.message || 'Erro ao consultar. Tente novamente.'
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    loading.value = false
  }
}

function limpar() {
  cpfInput.value = ''
  resultado.value = null
  erro.value = ''
  cadastros.value = []
  enriquecerAlvo.value = null
}

async function copiarEndereco() {
  try {
    await navigator.clipboard.writeText(enderecoCompleto.value + (cidadeUf.value !== '—' ? ` - ${cidadeUf.value}` : ''))
    emit('toast', { msg: 'Endereço copiado para a área de transferência.', type: 'success' })
  } catch (err) {
    console.error('Erro ao copiar endereço:', err)
  }
}

function imprimirRelatorio() {
  printing.value = true
  document.body.classList.add('is-printing-all')
  setTimeout(() => {
    window.print()
    document.body.classList.remove('is-printing-all')
    printing.value = false
  }, 100)
}
</script>

<style scoped>
.cadastro-alert {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  padding: 14px 18px;
  border: 1px solid var(--primary);
  border-left-width: 4px;
  border-radius: var(--radius, 10px);
  background: var(--surface);
}

.cadastro-alert-icone {
  font-size: 1.4rem;
  color: var(--primary);
}

.cadastro-alert-texto {
  flex: 1;
  min-width: 220px;
  color: var(--ink);
  font-size: 0.92rem;
}

.cadastro-alert-texto small {
  color: var(--ink-muted);
}

.cadastro-alert-acoes {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.search-card {
  margin-bottom: 20px;
}

.search-form {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input-group {
  margin-bottom: 0;
  min-width: 240px;
}

.spin-icon {
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.skeleton-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 20px;
}

.error-state i {
  color: var(--danger);
}

.report-wrap {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.identity-card {
  border-left: 4px solid var(--primary);
}

.identity-body {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  flex-wrap: wrap;
}

.identity-main h2 {
  font-family: var(--font-head);
  color: var(--ink);
  margin: 0 0 4px 0;
  font-size: 1.4rem;
}

.identity-social {
  color: var(--ink-muted);
  font-size: 0.85rem;
  margin-bottom: 10px;
}

.identity-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.identity-doc {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  flex-shrink: 0;
}

.doc-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--primary);
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.address-card,
.indicators-card,
.occurrences-card {
  grid-column: 1 / -1;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 8px;
  border-bottom: 1px dashed var(--border);
}

.info-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.info-label {
  color: var(--ink-muted);
  font-size: 0.82rem;
}

.info-value {
  color: var(--ink);
  font-weight: 600;
  font-size: 0.9rem;
  text-align: right;
}

.address-line {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 16px 0;
}

.address-line.empty {
  color: var(--ink-muted);
  font-weight: 400;
  font-style: italic;
}

.address-details {
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.indicators-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 10px 20px;
}

.indicator-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px dashed var(--border);
}

.indicator-label {
  font-size: 0.85rem;
  color: var(--ink-dim);
}

.billing-card {
  background: var(--surface);
}

.billing-body {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  align-items: center;
}

.billing-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.billing-message {
  margin-left: auto;
  color: var(--ink-muted);
  font-size: 0.85rem;
  font-style: italic;
}

@media (max-width: 900px) {
  .report-grid {
    grid-template-columns: 1fr;
  }
  .identity-body {
    flex-direction: column;
  }
  .identity-doc {
    align-items: flex-start;
  }
}

/* ─── Print report ───────────────────────── */
.print-report {
  margin-top: 20px;
  font-family: var(--font-body, sans-serif);
  color: #1f2937;
}

.print-identity {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid #cbd5e1;
}

.print-identity-main h3 {
  margin: 0 0 4px 0;
  font-size: 1.25rem;
  color: #003056;
}

.print-muted {
  color: #6b7280;
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.print-identity-doc {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
}

.print-doc-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #003056;
}

.print-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.print-tag {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 999px;
  border: 1px solid #94a3b8;
  color: #334155;
  background: #f1f5f9;
}

.print-tag-muted {
  border-color: #cbd5e1;
  color: #64748b;
  background: #f8fafc;
}

.print-tag-warn {
  border-color: #d97706;
  color: #92400e;
  background: #fef3c7;
}

.print-tag-danger {
  border-color: #dc2626;
  color: #991b1b;
  background: #fee2e2;
}

.print-tag-ok {
  border-color: #16a34a;
  color: #166534;
  background: #dcfce7;
}

.print-section {
  margin-bottom: 18px;
  page-break-inside: avoid;
}

.print-section h4 {
  margin: 0 0 10px 0;
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #003056;
  border-bottom: 2px solid #003056;
  padding-bottom: 4px;
}

.print-section-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 32px;
}

.print-section-grid-3 {
  grid-template-columns: repeat(3, 1fr);
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #cbd5e1;
}

.print-address {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.print-info-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 5px 0;
  border-bottom: 1px dashed #e2e8f0;
  font-size: 0.85rem;
}

.print-info-row:last-child {
  border-bottom: none;
}

.print-info-row .print-label {
  color: #6b7280;
}

.print-info-row span:last-child {
  font-weight: 600;
  text-align: right;
}

.print-check-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 24px;
}

.print-check-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 5px 0;
  border-bottom: 1px dashed #e2e8f0;
  font-size: 0.82rem;
}

.print-footer {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 20px;
  padding-top: 12px;
  border-top: 1px solid #cbd5e1;
  font-size: 0.78rem;
  color: #6b7280;
  font-style: italic;
}

@media print {
  .print-tag,
  .print-tag-muted,
  .print-tag-warn,
  .print-tag-danger,
  .print-tag-ok {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
