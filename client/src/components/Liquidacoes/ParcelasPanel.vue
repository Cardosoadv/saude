<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-calendar2-check"></i> Parcelas e pagamentos</span>
      <button v-if="podeEditar" type="button" class="btn btn-ghost btn-sm" @click="abrirPlano">
        <i class="bi bi-calendar-plus"></i> {{ abertas.length || pagas.length ? 'Refazer parcelamento' : 'Gerar parcelamento' }}
      </button>
    </div>

    <div class="section-body">
      <div class="parc-resumo">
        <div><small>Valor atualizado</small><strong>{{ formatarReais(liquidacao.valor_total) }}</strong></div>
        <div><small>Pago</small><strong class="ok">{{ formatarReais(resumo.total_pago) }}</strong></div>
        <div><small>Saldo a receber</small><strong>{{ formatarReais(resumo.saldo_a_receber) }}</strong></div>
        <div v-if="resumo.total_vencido > 0"><small>Em atraso</small><strong class="atraso">{{ formatarReais(resumo.total_vencido) }}</strong></div>
        <div v-if="resumo.proxima_parcela"><small>Próxima parcela</small><strong>{{ formatDate(resumo.proxima_parcela.data_vencimento) }}</strong></div>
      </div>

      <p v-if="resumo.diferenca_parcelamento" class="parc-aviso" role="status">
        <i class="bi bi-exclamation-triangle"></i>
        O plano de parcelas ({{ formatarReais(resumo.valor_parcelado) }}) difere do valor atualizado em
        {{ formatarReais(Math.abs(resumo.diferenca_parcelamento)) }}. Refaça o parcelamento se o valor deve acompanhar o recálculo.
      </p>

      <div v-if="!parcelas.length" class="parc-vazio">Nenhuma parcela gerada.</div>
      <div v-else class="parc-tabela">
        <table class="table-premium">
          <thead><tr><th>Parcela</th><th>Vencimento</th><th class="num">Valor</th><th>Situação</th><th>Pagamento</th><th></th></tr></thead>
          <tbody>
            <tr v-for="p in parcelas" :key="p.pagamento_id" :class="{ atrasada: atrasada(p) }">
              <td>{{ Number(p.numero_parcela) === 0 ? 'Entrada' : p.numero_parcela }}</td>
              <td>{{ formatDate(p.data_vencimento) }}</td>
              <td class="num">{{ formatarReais(p.valor_parcela) }}</td>
              <td><span :class="['badge', 'badge-' + tomParcela(atrasada(p) ? 'vencida' : p.status_pagamento)]">{{ STATUS_PARCELA[atrasada(p) ? 'vencida' : p.status_pagamento] }}</span></td>
              <td>
                <template v-if="p.status_pagamento === 'paga'">
                  {{ formatDate(p.data_pagamento) }}<span v-if="p.forma_pagamento"> · {{ p.forma_pagamento }}</span>
                  <a v-if="p.comprovante_url" :href="p.comprovante_url" target="_blank" rel="noopener" class="parc-link" title="Abrir comprovante"><i class="bi bi-paperclip"></i></a>
                </template>
                <span v-else-if="p.observacao" class="parc-obs">{{ p.observacao }}</span>
              </td>
              <td class="acoes">
                <template v-if="podeEditar && ['pendente', 'vencida'].includes(p.status_pagamento)">
                  <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Baixar parcela ${p.numero_parcela}`" title="Baixar" @click="abrirBaixa(p)"><i class="bi bi-cash-coin"></i></button>
                  <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Cancelar parcela ${p.numero_parcela}`" title="Cancelar" @click="cancelar(p)"><i class="bi bi-x-circle"></i></button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Gerar parcelamento -->
    <div v-if="plano.aberto" class="modal-backdrop" @click.self="plano.aberto = false">
      <div class="modal-box parc-modal" role="dialog" aria-modal="true" aria-label="Gerar parcelamento">
        <div class="modal-header">
          <h2><i class="bi bi-calendar-plus"></i> Gerar parcelamento</h2>
          <button type="button" class="modal-close" :disabled="plano.salvando" aria-label="Fechar" @click="plano.aberto = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <form class="modal-body" style="padding:1.25rem;" @submit.prevent="gerar">
          <p class="parc-ajuda">Parcela o valor atualizado ({{ formatarReais(liquidacao.valor_total) }}). Os centavos que sobram ficam na última parcela.</p>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label" for="pl-n">Número de parcelas</label>
              <input id="pl-n" class="form-control" type="number" min="1" max="360" v-model.number="plano.n" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="pl-per">Periodicidade</label>
              <select id="pl-per" class="form-control form-select" v-model="plano.periodicidade">
                <option v-for="(r, k) in PERIODICIDADES" :key="k" :value="k">{{ r }}</option>
              </select>
            </div>
          </div>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label" for="pl-venc">Primeiro vencimento</label>
              <input id="pl-venc" class="form-control" type="date" v-model="plano.primeiro" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="pl-ent">Entrada (R$, opcional)</label>
              <input id="pl-ent" class="form-control" type="text" inputmode="decimal" v-model="plano.entrada" placeholder="0,00" />
            </div>
          </div>
          <div class="form-group" v-if="plano.entrada.trim()">
            <label class="form-label" for="pl-dent">Data da entrada</label>
            <input id="pl-dent" class="form-control" type="date" v-model="plano.dataEntrada" />
            <small class="parc-ajuda">Em branco: mesma data do primeiro vencimento.</small>
          </div>
          <label v-if="abertas.length" class="parc-check">
            <input type="checkbox" v-model="plano.substituir" />
            Substituir as {{ abertas.length }} parcela(s) em aberto por este plano
          </label>
          <p v-if="plano.erro" class="parc-erro" role="alert">{{ plano.erro }}</p>
          <div class="parc-rodape">
            <button type="button" class="btn btn-outline-secondary" :disabled="plano.salvando" @click="plano.aberto = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="plano.salvando">{{ plano.salvando ? 'Gerando...' : 'Gerar' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Baixar parcela -->
    <div v-if="baixa.parcela" class="modal-backdrop" @click.self="fecharBaixa">
      <div class="modal-box parc-modal" role="dialog" aria-modal="true" aria-label="Baixar parcela">
        <div class="modal-header">
          <h2><i class="bi bi-cash-coin"></i> Baixar {{ Number(baixa.parcela.numero_parcela) === 0 ? 'entrada' : 'parcela ' + baixa.parcela.numero_parcela }}</h2>
          <button type="button" class="modal-close" :disabled="baixa.salvando" aria-label="Fechar" @click="fecharBaixa"><i class="bi bi-x-lg"></i></button>
        </div>
        <form class="modal-body" style="padding:1.25rem;" @submit.prevent="baixar">
          <p class="parc-ajuda">Valor: <strong>{{ formatarReais(baixa.parcela.valor_parcela) }}</strong> · vencimento {{ formatDate(baixa.parcela.data_vencimento) }}</p>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label" for="bx-data">Data do pagamento</label>
              <input id="bx-data" class="form-control" type="date" v-model="baixa.data" :max="hoje" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="bx-forma">Forma</label>
              <input id="bx-forma" class="form-control" type="text" list="formas-pagamento" v-model="baixa.forma" maxlength="50" placeholder="PIX, depósito, alvará..." />
              <datalist id="formas-pagamento"><option>PIX</option><option>Transferência</option><option>Depósito judicial</option><option>Alvará</option><option>Boleto</option></datalist>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="bx-obs">Observação</label>
            <input id="bx-obs" class="form-control" type="text" v-model="baixa.observacao" maxlength="255" />
          </div>
          <div v-if="liquidacao.lancar_honorarios && Number(liquidacao.lancar_honorarios)" class="form-group">
            <label class="form-label" for="bx-conta">Conta que recebeu (honorários no Financeiro)</label>
            <input id="bx-conta" class="form-control" type="text" inputmode="numeric" v-model="baixa.conta" placeholder="Número da conta (opcional)" />
            <small class="parc-ajuda">Esta liquidação lança honorários no Financeiro ao baixar. Sem conta, a receita fica "a receber".</small>
          </div>
          <div class="form-group">
            <input ref="seletor" type="file" multiple :accept="ACCEPT_INPUT" hidden @change="escolher" />
            <button type="button" class="btn btn-outline-primary btn-sm" :disabled="baixa.salvando" @click="seletor?.click()"><i class="bi bi-paperclip"></i> Anexar comprovante</button>
            <ul v-if="baixa.arquivos.length" class="parc-arquivos">
              <li v-for="(a, i) in baixa.arquivos" :key="i">{{ a.name }} <small>({{ formatarTamanho(a.size) }})</small>
                <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Tirar ${a.name}`" @click="baixa.arquivos.splice(i, 1)"><i class="bi bi-x"></i></button>
              </li>
            </ul>
          </div>
          <p v-if="baixa.erro" class="parc-erro" role="alert">{{ baixa.erro }}</p>
          <div class="parc-rodape">
            <button type="button" class="btn btn-outline-secondary" :disabled="baixa.salvando" @click="fecharBaixa">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="baixa.salvando">{{ baixa.salvando ? 'Baixando...' : 'Confirmar baixa' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ApiError, formatDate } from '../../services/api'
import { baixarParcela, cancelarParcela, gerarParcelas } from '../../services/liquidacoesApi'
import type { LiquidacaoDetalhe, Pagamento } from '../../types/liquidacao'
import { ACCEPT_INPUT, validarLote } from '../../utils/contratosArquivos'
import { PERIODICIDADES, STATUS_PARCELA, formatarReais, hojeISO, parcelaAtrasada, textoParaCentavos, tomParcela } from '../../utils/liquidacao'
import { formatarTamanho } from '../../utils/midias'

const props = defineProps<{ liquidacao: LiquidacaoDetalhe; podeEditar: boolean }>()
const emit = defineEmits<{
  (e: 'atualizar'): void
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

const hoje = hojeISO()
const resumo = computed(() => props.liquidacao.resumo_pagamentos)
const parcelas = computed(() => [...(props.liquidacao.pagamentos ?? [])].sort((a, b) => Number(a.numero_parcela) - Number(b.numero_parcela)))
const abertas = computed(() => parcelas.value.filter((p) => ['pendente', 'vencida'].includes(p.status_pagamento)))
const pagas = computed(() => parcelas.value.filter((p) => p.status_pagamento === 'paga'))
const atrasada = (p: Pagamento) => parcelaAtrasada(p, hoje)

const msgErro = (e: unknown, padrao: string) => {
  if (e instanceof ApiError) {
    const detalhes = e.data?.errors && typeof e.data.errors === 'object' ? Object.values(e.data.errors).join(' ') : ''
    return [e.message, detalhes].filter(Boolean).join(' ')
  }
  return e instanceof Error ? e.message : padrao
}

// ---- Parcelamento -----------------------------------------------------
const plano = reactive({ aberto: false, salvando: false, erro: '', n: 12, periodicidade: 'mensal', primeiro: '', entrada: '', dataEntrada: '', substituir: false })

function abrirPlano() {
  Object.assign(plano, { aberto: true, salvando: false, erro: '', substituir: false, entrada: '', dataEntrada: '' })
}

async function gerar() {
  const entrada = plano.entrada.trim() ? textoParaCentavos(plano.entrada) : 0
  if (entrada === null || entrada < 0) { plano.erro = 'Entrada inválida. Use o formato 1.234,56.'; return }
  if (pagas.value.length) { plano.erro = 'Há parcelas já pagas; não é possível refazer o parcelamento.'; return }
  if (abertas.value.length && !plano.substituir) { plano.erro = 'Marque "Substituir" para refazer o plano.'; return }

  plano.salvando = true
  plano.erro = ''
  try {
    await gerarParcelas(props.liquidacao.liquidacao_id, {
      numero_parcelas: plano.n,
      primeiro_vencimento: plano.primeiro,
      periodicidade: plano.periodicidade,
      entrada: entrada || undefined,
      data_entrada: entrada && plano.dataEntrada ? plano.dataEntrada : undefined,
      substituir: plano.substituir || undefined,
    })
    plano.aberto = false
    emit('toast', { msg: 'Parcelamento gerado.', type: 'success' })
    emit('atualizar')
  } catch (e) {
    plano.erro = msgErro(e, 'Não foi possível gerar as parcelas.')
  } finally {
    plano.salvando = false
  }
}

// ---- Baixa -----------------------------------------------------------
const seletor = ref<HTMLInputElement | null>(null)
const baixa = reactive({
  parcela: null as Pagamento | null, salvando: false, erro: '',
  data: hoje, forma: '', observacao: '', conta: '', arquivos: [] as File[],
})

function abrirBaixa(p: Pagamento) {
  Object.assign(baixa, { parcela: p, salvando: false, erro: '', data: hoje, forma: '', observacao: '', conta: '', arquivos: [] })
}
function fecharBaixa() { if (!baixa.salvando) baixa.parcela = null }

function escolher(evento: Event) {
  const campo = evento.target as HTMLInputElement
  const novos = Array.from(campo.files ?? [])
  campo.value = ''
  baixa.arquivos = [...baixa.arquivos, ...novos]
  baixa.erro = baixa.arquivos.length ? (validarLote(baixa.arquivos) ?? '') : ''
}

async function baixar() {
  if (!baixa.parcela) return
  if (baixa.arquivos.length) {
    const invalido = validarLote(baixa.arquivos)
    if (invalido) { baixa.erro = invalido; return }
  }
  baixa.salvando = true
  baixa.erro = ''
  try {
    const r = await baixarParcela(baixa.parcela.pagamento_id, {
      data_pagamento: baixa.data,
      forma_pagamento: baixa.forma.trim() || undefined,
      observacao: baixa.observacao.trim() || undefined,
      conta_id: baixa.conta.trim() || undefined,
    }, baixa.arquivos)
    baixa.parcela = null
    const falhas = r.erros_upload?.length ?? 0
    emit('toast', { msg: (r.message || 'Parcela baixada.') + (falhas ? ` ${falhas} arquivo(s) não foram enviados.` : ''), type: falhas ? 'warning' : 'success' })
    emit('atualizar')
  } catch (e) {
    baixa.erro = msgErro(e, 'Não foi possível baixar a parcela.')
  } finally {
    baixa.salvando = false
  }
}

async function cancelar(p: Pagamento) {
  if (!confirm(`Cancelar ${Number(p.numero_parcela) === 0 ? 'a entrada' : 'a parcela ' + p.numero_parcela} de ${formatarReais(p.valor_parcela)}?`)) return
  try {
    await cancelarParcela(p.pagamento_id)
    emit('toast', { msg: 'Parcela cancelada.', type: 'success' })
    emit('atualizar')
  } catch (e) {
    emit('toast', { msg: msgErro(e, 'Não foi possível cancelar a parcela.'), type: 'danger' })
  }
}
</script>

<style scoped>
.parc-resumo { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; }
.parc-resumo div { display: flex; flex-direction: column; }
.parc-resumo small { color: var(--ink-muted); font-size: .72rem; }
.parc-resumo strong { font-size: 1rem; }
.ok { color: var(--success); }
.atraso { color: var(--danger); }
.parc-aviso { background: #fef9c3; color: #92400e; border-radius: 6px; padding: 8px 10px; font-size: .82rem; margin: 0 0 12px; }
.parc-vazio { text-align: center; color: var(--ink-muted); padding: 14px; font-size: .88rem; }
.parc-tabela { overflow-x: auto; }
.table-premium { width: 100%; border-collapse: collapse; font-size: .85rem; }
.table-premium th, .table-premium td { padding: 7px 8px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
.num { text-align: right !important; white-space: nowrap; }
tr.atrasada td { background: #fef2f2; }
.acoes { white-space: nowrap; text-align: right !important; }
.parc-link { margin-left: 6px; color: var(--accent); }
.parc-obs { color: var(--ink-muted); font-size: .78rem; }
.parc-modal { max-width: 560px; width: 100%; }
.parc-ajuda { color: var(--ink-muted); font-size: .8rem; margin: 0 0 10px; display: block; }
.parc-check { display: flex; gap: 8px; align-items: center; font-size: .85rem; margin: 6px 0; }
.parc-erro { color: var(--danger); font-size: .85rem; }
.parc-rodape { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 12px; }
.parc-arquivos { list-style: none; margin: 8px 0 0; padding: 0; font-size: .82rem; }
</style>
