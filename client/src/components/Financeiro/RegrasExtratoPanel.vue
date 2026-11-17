<template>
  <div class="ex-card-body">
    <p class="text-ink-muted">Regras preenchem fornecedor, categoria e rateio ao lançar movimentos parecidos. Para criar uma, marque “Lembrar esta classificação” ao lançar um movimento.</p>
    <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Carregando regras...</div>
    <p v-else-if="erro" class="regra-erro" role="alert">{{ erro }}</p>
    <p v-else-if="!regras.length" class="text-center text-muted">Nenhuma regra cadastrada.</p>
    <div class="ex-table-wrap" v-else>
      <table class="ex-table">
        <thead><tr><th>Nome</th><th>Padrão</th><th>Campo</th><th>Sentido</th><th class="num">Ações</th></tr></thead>
        <tbody>
          <tr v-for="r in regras" :key="String(r.id_regra)">
            <td>{{ r.nome }}</td><td>{{ r.padrao }}</td><td>{{ r.campo }}</td><td>{{ r.sinal }}</td>
            <td class="num">
              <button v-if="podeExcluir" class="btn btn-sm btn-ghost text-danger" :disabled="excluindo === r.id_regra" :title="'Excluir regra ' + r.nome" @click="excluir(r)">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { pode } from '../../composables/useAcesso'
import { excluirRegra, listarRegras, type RegraExtrato } from '../../services/extratosApi'

const props = defineProps<{ contaId: number | string | null }>()
const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: 'success' | 'danger' }): void }>()

const podeExcluir = pode('financeiro.extratos_lancar')
const regras = ref<RegraExtrato[]>([])
const carregando = ref(false)
const erro = ref('')
const excluindo = ref<number | string | null>(null)

async function carregar() {
  carregando.value = true
  erro.value = ''
  try {
    regras.value = await listarRegras(props.contaId)
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Não foi possível carregar as regras.'
  } finally {
    carregando.value = false
  }
}

async function excluir(r: RegraExtrato) {
  if (excluindo.value || !window.confirm(`Excluir a regra "${r.nome}"?`)) return
  excluindo.value = r.id_regra
  try {
    await excluirRegra(r.id_regra)
    regras.value = regras.value.filter((x) => x.id_regra !== r.id_regra)
    emit('toast', { msg: 'Regra excluída.', type: 'success' })
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível excluir a regra.', type: 'danger' })
  } finally {
    excluindo.value = null
  }
}

watch(() => props.contaId, carregar)
onMounted(carregar)
</script>

<style scoped>
.regra-erro { color: #b91c1c; }
</style>
