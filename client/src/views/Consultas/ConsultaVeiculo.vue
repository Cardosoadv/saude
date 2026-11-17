<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Consulta de veículo</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Veículo por placa</h1>
        <p>Consulte os dados cadastrais do veículo a partir da placa (padrão antigo ou Mercosul)</p>
      </div>
    </div>

    <ConsultasNav atual="consulta-veiculo" :homolog="homolog" @set-view="(v: string) => $emit('set-view', v)" />

    <div class="card search-card">
      <div class="card-body">
        <form class="search-form" @submit.prevent="consultar">
          <div class="form-group">
            <label for="placa-input"><i class="bi bi-car-front"></i> Placa</label>
            <input id="placa-input" type="text" class="form-control" v-model="placa"
              @input="placa = normalizarPlaca(placa)" placeholder="ABC1D23" maxlength="7" autocomplete="off" />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="loading || !valida">
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
      <div class="card identity-card">
        <div class="card-body">
          <h2>{{ [v.marca, v.modelo].filter(Boolean).join(' ') || '—' }}</h2>
          <p class="muted">{{ v.versao || '' }}</p>
          <div class="badges">
            <span class="badge badge-muted">Placa {{ v.placa || '—' }}</span>
            <span v-if="v.placaMercosul" class="badge badge-muted">Mercosul {{ v.placaMercosul }}</span>
            <span v-if="v.cor" class="badge badge-muted">{{ v.cor }}</span>
          </div>
        </div>
      </div>

      <div class="report-grid">
        <div v-for="grupo in grupos" :key="grupo.titulo" class="card">
          <div class="card-header"><h3><i class="bi" :class="grupo.icone"></i> {{ grupo.titulo }}</h3></div>
          <div class="card-body info-list">
            <div v-for="c in grupo.campos" :key="c.rotulo" class="info-row">
              <span class="info-label">{{ c.rotulo }}</span>
              <span class="info-value">{{ valor(c.valor) }}</span>
            </div>
          </div>
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
      <i class="bi bi-car-front"></i>
      <h4>Nenhuma consulta realizada</h4>
      <p>Informe a placa acima para consultar os dados do veículo.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { formatCurrency } from '../../services/api'
import ConsultasNav from '../../components/Consultas/ConsultasNav.vue'
import { consultarVeiculo, homologAtivo, normalizarPlaca, placaValida, type ResultadoVeiculo } from '../../services/consultasApi'

const emit = defineEmits(['set-view', 'toast'])

const placa = ref('')
const loading = ref(false)
const erro = ref('')
const resultado = ref<ResultadoVeiculo | null>(null)
const homolog = ref(false)

const valida = computed(() => placaValida(placa.value))
const v = computed<Record<string, any>>(() => resultado.value?.veiculo ?? {})

onMounted(async () => { homolog.value = await homologAtivo() })

function valor(x: unknown) {
  return x === null || x === undefined || x === '' ? '—' : String(x)
}

const grupos = computed(() => {
  const d = v.value
  return [
    { titulo: 'Identificação', icone: 'bi-upc-scan', campos: [
      { rotulo: 'Chassi', valor: d.chassi }, { rotulo: 'Fabricante', valor: d.fabricante },
      { rotulo: 'Ano fabricação / modelo', valor: d.ano_fabricacao ? `${d.ano_fabricacao} / ${d.ano_modelo ?? '—'}` : null },
      { rotulo: 'Tipo', valor: d.tipo_veiculo }, { rotulo: 'Espécie', valor: d.especie },
      { rotulo: 'Nacionalidade', valor: d.nacionalidade },
    ] },
    { titulo: 'Mecânica', icone: 'bi-gear', campos: [
      { rotulo: 'Motor', valor: d.motor_descricao }, { rotulo: 'Número do motor', valor: d.numero_motor },
      { rotulo: 'Combustível', valor: d.combustivel }, { rotulo: 'Transmissão', valor: d.transmissao_descricao },
      { rotulo: 'Cilindradas', valor: d.cilindradas }, { rotulo: 'Potência', valor: d.potencia },
    ] },
    { titulo: 'Capacidade', icone: 'bi-box-seam', campos: [
      { rotulo: 'Lugares', valor: d.quantidade_lugares }, { rotulo: 'Portas', valor: d.quantidade_portas },
      { rotulo: 'Eixos', valor: d.quantidade_eixo }, { rotulo: 'Peso bruto total', valor: d.peso_bruto_total },
      { rotulo: 'Capacidade máx. de tração', valor: d.capacidade_max_tracao },
    ] },
    { titulo: 'Registro', icone: 'bi-geo-alt', campos: [
      { rotulo: 'Cidade / UF', valor: [d.cidade, d.uf_jurisdicao].filter(Boolean).join(' / ') },
      { rotulo: 'Tipo do faturado', valor: d.tipo_faturado }, { rotulo: 'UF do faturado', valor: d.uf_faturado },
    ] },
  ]
})

async function consultar() {
  if (!valida.value) return
  loading.value = true
  erro.value = ''
  resultado.value = null
  try {
    resultado.value = await consultarVeiculo(placa.value)
  } catch (err: any) {
    erro.value = err?.message || 'Erro ao consultar. Tente novamente.'
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    loading.value = false
  }
}

function limpar() {
  placa.value = ''
  resultado.value = null
  erro.value = ''
}
</script>

<style scoped>
.search-card { margin-bottom: 20px; }
.search-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.search-form .form-group { margin-bottom: 0; min-width: 200px; }
.search-form input { text-transform: uppercase; letter-spacing: 0.1em; }
.spin-icon { animation: spin 0.8s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state i { color: var(--danger); }
.report-wrap { margin-top: 20px; display: flex; flex-direction: column; gap: 20px; }
.identity-card { border-left: 4px solid var(--primary); }
.identity-card h2 { font-family: var(--font-head); color: var(--ink); margin: 0 0 4px; font-size: 1.4rem; }
.muted { color: var(--ink-muted); margin: 0 0 10px; }
.badges { display: flex; flex-wrap: wrap; gap: 8px; }
.report-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.info-list { display: flex; flex-direction: column; gap: 10px; }
.info-row { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px dashed var(--border); }
.info-row:last-child { border-bottom: none; padding-bottom: 0; }
.info-label { color: var(--ink-muted); font-size: 0.82rem; }
.info-value { color: var(--ink); font-weight: 600; font-size: 0.9rem; text-align: right; }
.billing-body { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; }
.billing-item { display: flex; flex-direction: column; gap: 2px; }
.billing-message { margin-left: auto; color: var(--ink-muted); font-size: 0.85rem; font-style: italic; }
@media (max-width: 900px) { .report-grid { grid-template-columns: 1fr; } }
</style>
