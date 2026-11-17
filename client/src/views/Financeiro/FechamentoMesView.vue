<template>
  <div class="view-container">
    <div class="page-header">
      <h2 class="page-title"><i class="bi bi-lock page-icon"></i> Fechamento do Mês</h2>
      <p class="page-subtitle">Consolidação e bloqueio de saldos mensais</p>
    </div>

    <!-- Sugestão de Próximo Fechamento -->
    <div class="glass-panel mb-4" v-if="loadingSugerido">
      <div class="p-4 text-center text-muted">
        <div class="spinner spinner-sm"></div> Carregando sugestão...
      </div>
    </div>
    <div class="glass-panel mb-4" v-else-if="proximoFechamento">
      <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h4 class="mb-1 text-ink fw-bold">Sugestão de Fechamento: {{ nomeMes(proximoFechamento.mes) }}/{{ proximoFechamento.ano }}</h4>
          <p class="mb-0 text-ink-muted">Feche o mês para consolidar saldos e bloquear edições financeiras no período.</p>
          <p v-if="pendencias && pendencias.total > 0" class="mb-0 mt-2 pendencias-aviso" role="status">
            <i class="bi bi-paperclip"></i>
            {{ textoPendencias(pendencias) }}
            <a href="#" @click.prevent="$emit('set-view', 'despesas-pagas')">Ver despesas sem comprovante</a>
            <ul class="pendencias-contas" v-if="pendencias.contas.length > 1">
              <li v-for="c in pendencias.contas" :key="String(c.conta_id)">{{ c.conta || 'Sem conta' }}: {{ c.despesas + c.receitas }}</li>
            </ul>
          </p>
          <p v-if="pendenciasExtrato && pendenciasExtrato.pendentes > 0" class="mb-0 mt-2 pendencias-aviso" role="status">
            <i class="bi bi-bank"></i> {{ textoPendenciasExtrato(pendenciasExtrato.pendentes) }}
            <a href="#" @click.prevent="$emit('set-view', 'extrato-movimentos')">Conciliar movimentos</a>
          </p>
          <p v-if="pendencias && pendencias.total === 0" class="mb-0 mt-2 pendencias-ok">
            <i class="bi bi-check-circle"></i> Todos os pagamentos do mês têm comprovante.
          </p>
        </div>
        <button 
          class="btn btn-primary" 
          @click="confirmarFechamento(proximoFechamento)"
          :disabled="isProcessing"
        >
          <span v-if="isProcessing" class="spinner spinner-sm me-2"></span>
          <i v-else class="bi bi-lock-fill me-2"></i> Fechar Mês
        </button>
      </div>
    </div>
    <div class="glass-panel mb-4" v-else>
       <div class="p-4 text-center text-ink-muted">
          <i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>
          Todos os meses até o período atual parecem estar fechados ou não há dados suficientes para sugestão.
       </div>
    </div>

    <!-- Histórico de Fechamentos -->
    <div class="glass-panel">
      <div class="card-header border-bottom">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Histórico de Fechamentos</h5>
      </div>
      
      <div class="p-4 text-center text-muted" v-if="loadingLista">
        <div class="spinner spinner-sm"></div> Carregando histórico...
      </div>

      <div class="table-responsive" v-else-if="fechamentos.length > 0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Período</th>
              <th>Tipo</th>
              <th>Conta</th>
              <th>Data do Fechamento</th>
              <th>Responsável</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="fechamento in fechamentos" :key="fechamento.id">
              <td><strong>{{ nomeMes(fechamento.mes) }}/{{ fechamento.ano }}</strong></td>
              <td>
                <span class="badge" :class="fechamento.tipo_fechamento === 'GLOBAL' ? 'bg-primary' : 'bg-info'">
                  {{ fechamento.tipo_fechamento }}
                </span>
              </td>
              <td>{{ fechamento.conta_nome || 'Todas as Contas' }}</td>
              <td>{{ formatDateTime(fechamento.fechado_em) }}</td>
              <td>{{ fechamento.fechado_por_nome || 'Sistema' }}</td>
              <td class="text-end">
                <button 
                  class="btn btn-sm btn-ghost text-danger" 
                  title="Reabrir Mês"
                  @click="confirmarReabertura(fechamento)"
                  :disabled="isProcessing"
                >
                  <i class="bi bi-unlock-fill"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 text-center text-muted" v-else>
        Nenhum fechamento registrado.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import { pendenciasDoMes, type PendenciasDoMes } from '../../services/comprovantesApi'
import { textoPendencias } from '../../utils/comprovantes'
import { resumoMesExtrato, type ResumoMesExtrato } from '../../services/extratosApi'
import { textoPendenciasExtrato } from '../../utils/extratos'

const emit = defineEmits(['toast', 'set-view'])

const fechamentos = ref([])
const proximoFechamento = ref(null)
const pendencias = ref<PendenciasDoMes | null>(null)
const pendenciasExtrato = ref<ResumoMesExtrato | null>(null)

const loadingLista = ref(true)
const loadingSugerido = ref(true)
const isProcessing = ref(false)

const meses = [
  '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
]

onMounted(async () => {
  await carregarSugerido()
  await carregarFechamentos()
})

const nomeMes = (mes) => meses[mes] || mes

const formatDateTime = (dateString) => formatDate(dateString, true)

async function carregarFechamentos() {
  loadingLista.value = true
  try {
    const res = await apiFetch('financeiro/fechamento/listar')
    if (res && res.data) {
      fechamentos.value = res.data
    }
  } catch (error) {
    emit('toast', { type: 'error', message: 'Erro ao carregar histórico de fechamentos.' })
  } finally {
    loadingLista.value = false
  }
}

async function carregarSugerido() {
  loadingSugerido.value = true
  try {
    const res = await apiFetch('financeiro/fechamento/sugerir')
    if (res && res.data && res.data.ano && res.data.mes) {
      proximoFechamento.value = res.data
      await carregarPendencias(res.data.ano, res.data.mes)
      await carregarPendenciasExtrato(res.data.ano, res.data.mes)
    } else {
      pendencias.value = null
      proximoFechamento.value = null
    }
  } catch (error) {
    console.warn('Erro ao carregar sugestão de fechamento:', error)
  } finally {
    loadingSugerido.value = false
  }
}

// Informativo: falha aqui não atrapalha o fechamento.
async function carregarPendencias(ano, mes) {
  try {
    pendencias.value = await pendenciasDoMes(Number(ano), Number(mes))
  } catch (error) {
    pendencias.value = null
    console.warn('Erro ao carregar pendências de comprovantes:', error)
  }
}

async function carregarPendenciasExtrato(ano, mes) {
  try {
    pendenciasExtrato.value = await resumoMesExtrato(Number(ano), Number(mes))
  } catch (error) {
    pendenciasExtrato.value = null
    console.warn('Erro ao carregar pendências do extrato:', error)
  }
}

async function confirmarFechamento(sugestao) {
  if (!sugestao) return
  const aviso = pendencias.value && pendencias.value.total > 0
    ? `\n\nAtenção: ${textoPendencias(pendencias.value)} Depois de fechar, não será possível anexar comprovantes a eles sem reabrir o mês.`
    : ''
  if (!confirm(`Deseja fechar o mês de ${nomeMes(sugestao.mes)}/${sugestao.ano}? Operações financeiras anteriores a este período não poderão ser alteradas.${aviso}`)) return

  isProcessing.value = true
  try {
    const formData = new FormData()
    formData.append('ano', sugestao.ano)
    formData.append('mes', sugestao.mes)

    const res = await apiFetch('financeiro/fechamento/fechar', {
      method: 'POST',
      body: formData
    })

    if (res && res.status === 'success') {
      emit('toast', { type: 'success', message: res.message || 'Mês fechado com sucesso.' })
      await carregarSugerido()
      await carregarFechamentos()
    } else {
      emit('toast', { type: 'error', message: res?.message || 'Erro ao fechar o mês.' })
    }
  } catch (error) {
    emit('toast', { type: 'error', message: error.message || 'Ocorreu um erro inesperado.' })
  } finally {
    isProcessing.value = false
  }
}

async function confirmarReabertura(fechamento) {
  if (!fechamento) return
  if (!confirm(`Atenção: Tem certeza que deseja reabrir o mês de ${nomeMes(fechamento.mes)}/${fechamento.ano}? Isso permitirá edições nos saldos e registros deste período.`)) return

  isProcessing.value = true
  try {
    const formData = new FormData()
    formData.append('ano', fechamento.ano)
    formData.append('mes', fechamento.mes)
    if (fechamento.conta_id) {
      formData.append('conta_id', fechamento.conta_id)
    }

    const res = await apiFetch('financeiro/fechamento/reabrir', {
      method: 'POST',
      body: formData
    })

    if (res && res.status === 'success') {
      emit('toast', { type: 'success', message: res.message || 'Mês reaberto com sucesso.' })
      await carregarSugerido()
      await carregarFechamentos()
    } else {
      emit('toast', { type: 'error', message: res?.message || 'Erro ao reabrir o mês.' })
    }
  } catch (error) {
    emit('toast', { type: 'error', message: error.message || 'Ocorreu um erro inesperado.' })
  } finally {
    isProcessing.value = false
  }
}
</script>

<style scoped>
.pendencias-aviso { color: var(--warning); font-size: .9rem; }
.pendencias-ok { color: var(--success); font-size: .9rem; }
.pendencias-contas { margin: 4px 0 0; padding-left: 18px; color: var(--ink-muted); }
</style>
