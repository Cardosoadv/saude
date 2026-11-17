<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="conciliar-titulo">
      <div class="modal-header">
        <h2 id="conciliar-titulo"><i class="bi bi-link-45deg"></i> Conciliar com lançamento existente</h2>
        <button type="button" class="modal-close" :disabled="enviando" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding: 1.5rem;">
        <p class="mb-3">
          <strong>{{ descricaoMovimento(movimento) }}</strong><br />
          {{ formatDate(movimento.data) }} · {{ formatarCentavos(movimento.valor_centavos) }}
        </p>

        <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Buscando sugestões...</div>
        <p v-else-if="erroCarga" class="conc-erro" role="alert">{{ erroCarga }}</p>
        <template v-else-if="sugestoes">
          <h6>Pagamentos já lançados</h6>
          <p v-if="!sugestoes.pagamentos.length" class="text-ink-muted">Nenhum pagamento com o mesmo valor em até {{ sugestoes.janela_dias }} dia(s).</p>
          <ul class="conc-lista" v-else>
            <li v-for="c in sugestoes.pagamentos" :key="c.tipo + c.id">
              <div>
                <strong>{{ c.descricao || c.contraparte || `${c.tipo} #${c.id}` }}</strong>
                <span class="badge bg-info ms-2" v-if="c.nome_confere">nome confere</span><br />
                <small class="text-ink-muted">{{ rotuloTipo(c.tipo) }} · {{ formatDate(c.data) }} · {{ formatarCentavos(c.valor_centavos) }} · {{ c.pontos }} pontos</small>
              </div>
              <button type="button" class="btn btn-primary btn-sm" :disabled="enviando" @click="conciliar(c)">Conciliar</button>
            </li>
          </ul>

          <template v-if="sugestoes.em_aberto.length">
            <h6 class="mt-3">Lançamentos em aberto (quitar com este movimento)</h6>
            <ul class="conc-lista">
              <li v-for="c in sugestoes.em_aberto" :key="c.tipo + c.id">
                <div>
                  <strong>{{ c.descricao || c.contraparte || `#${c.id}` }}</strong><br />
                  <small class="text-ink-muted">{{ formatDate(c.data) }} · {{ formatarCentavos(c.valor_centavos) }}</small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" :disabled="enviando || !podeLancar" @click="quitar(c)">Quitar</button>
              </li>
            </ul>
          </template>

          <template v-if="sugestoes.pares_transferencia.length">
            <h6 class="mt-3">Possível transferência entre contas</h6>
            <p class="text-ink-muted mb-0">Há movimentos de outra conta com o valor oposto. Use <strong>Lançar</strong> para criar a transferência.</p>
          </template>
        </template>

        <p v-if="erro" class="conc-erro mt-3" role="alert">{{ erro }}</p>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 1rem;">
        <button type="button" class="btn btn-outline-secondary" :disabled="enviando" @click="fechar">Fechar</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { pode } from '../../composables/useAcesso'
import { formatDate } from '../../services/api'
import {
  conciliarMovimento, lancarMovimento, sugestoesConciliacao,
  type CandidatoPagamento, type MovimentoExtrato, type SugestoesConciliacao, type TipoPagamentoExtrato,
} from '../../services/extratosApi'
import { descricaoMovimento, formatarCentavos } from '../../utils/extratos'

const props = defineProps<{ movimento: MovimentoExtrato }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'feito', mensagem: string): void }>()

const sugestoes = ref<SugestoesConciliacao | null>(null)
const carregando = ref(true)
const erroCarga = ref('')
const erro = ref('')
const enviando = ref(false)
const podeLancar = pode('financeiro.extratos_lancar')

const rotuloTipo = (t: string) => ({ despesa: 'Despesa paga', receita: 'Receita recebida', transferencia: 'Transferência' } as Record<string, string>)[t] ?? t

function fechar() {
  if (!enviando.value) emit('close')
}

async function executar(acao: () => Promise<unknown>, mensagem: string) {
  if (enviando.value) return
  enviando.value = true
  erro.value = ''
  try {
    await acao()
    emit('feito', mensagem)
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Não foi possível concluir a ação.'
  } finally {
    enviando.value = false
  }
}

function conciliar(c: CandidatoPagamento) {
  return executar(() => conciliarMovimento(props.movimento.id_movimento, c.tipo as TipoPagamentoExtrato, c.id), 'Movimento conciliado.')
}

function quitar(c: CandidatoPagamento) {
  const tipo = c.tipo.startsWith('despesa') ? 'despesa' : 'receita'
  return executar(() => lancarMovimento(props.movimento.id_movimento, { quitar: { tipo, id: c.id } }), 'Lançamento quitado e movimento conciliado.')
}

onMounted(async () => {
  try {
    sugestoes.value = await sugestoesConciliacao(props.movimento.id_movimento)
  } catch (e) {
    erroCarga.value = e instanceof Error ? e.message : 'Não foi possível buscar sugestões.'
  } finally {
    carregando.value = false
  }
})
</script>

<style scoped>
.conc-lista { list-style: none; padding: 0; margin: 0; display: grid; gap: .5rem; }
.conc-lista li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .6rem .8rem; border: 1px solid var(--border, #e2e8f0); border-radius: 8px; }
.conc-erro { color: #b91c1c; }
</style>
