<template>
  <div class="ex-card-body">
    <div class="d-flex flex-wrap gap-3 align-items-end mb-3">
      <div class="ex-field">
        <label class="form-label" for="conf-mes">Mês</label>
        <input id="conf-mes" type="month" class="form-control" v-model="mes" @change="carregar" />
      </div>
    </div>
    <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Conferindo...</div>
    <p v-else-if="erro" class="conf-erro" role="alert">{{ erro }}</p>
    <template v-else-if="c">
      <p class="mb-3"><strong>{{ textoSituacaoSaldo(c.situacao) }}.</strong> {{ c.mensagem }}</p>
      <div class="ex-totais" v-if="c.saldo_banco !== null">
        <div><span>{{ formatarCentavos(c.saldo_banco) }}</span> saldo do banco em {{ formatDate(c.data_saldo) }}</div>
        <div><span>{{ formatarCentavos(c.saldo_sistema) }}</span> saldo do sistema</div>
        <div :class="{ 'ex-conflito': c.diferenca !== 0 }"><span>{{ formatarCentavos(c.diferenca) }}</span> diferença</div>
        <div><span>{{ c.total_pendentes }}</span> pendentes ({{ formatarCentavos(c.soma_pendentes) }})</div>
      </div>
      <div class="ex-table-wrap mt-3" v-if="c.pendentes.length">
        <table class="ex-table">
          <thead><tr><th>Data</th><th>Descrição</th><th class="num">Valor</th></tr></thead>
          <tbody>
            <tr v-for="p in c.pendentes" :key="String(p.id_movimento)">
              <td>{{ formatDate(p.data) }}</td><td>{{ descricaoMovimento(p) }}</td><td class="num">{{ formatarCentavos(p.valor_centavos) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { formatDate } from '../../services/api'
import { conferirSaldo, type ConferenciaSaldo } from '../../services/extratosApi'
import { descricaoMovimento, formatarCentavos, textoSituacaoSaldo } from '../../utils/extratos'

const props = defineProps<{ contaId: number | string | null }>()

const hoje = new Date()
const mes = ref(`${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`)
const c = ref<ConferenciaSaldo | null>(null)
const carregando = ref(false)
const erro = ref('')

async function carregar() {
  if (!props.contaId || !mes.value) return
  const [ano, m] = mes.value.split('-').map(Number)
  carregando.value = true
  erro.value = ''
  try {
    c.value = await conferirSaldo(props.contaId, ano, m)
  } catch (e) {
    c.value = null
    erro.value = e instanceof Error ? e.message : 'Não foi possível conferir o saldo.'
  } finally {
    carregando.value = false
  }
}

watch(() => props.contaId, carregar)
onMounted(carregar)
defineExpose({ carregar })
</script>

<style scoped>
.ex-conflito span { color: #d97706; }
.conf-erro { color: #b91c1c; }
</style>
