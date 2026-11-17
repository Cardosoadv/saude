<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="lancar-titulo">
      <div class="modal-header">
        <h2 id="lancar-titulo"><i class="bi bi-journal-plus"></i> Lançar {{ sugestao?.tipo === 'receita' ? 'receita' : 'despesa' }}</h2>
        <button type="button" class="modal-close" :disabled="enviando" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding: 1.5rem;">
        <p class="mb-3">
          <strong>{{ descricaoMovimento(movimento) }}</strong><br />
          {{ formatDate(movimento.data) }} · {{ formatarCentavos(movimento.valor_centavos) }}
        </p>

        <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Carregando...</div>
        <p v-else-if="erroCarga" class="lanc-erro" role="alert">{{ erroCarga }}</p>
        <p v-else-if="sugestao && !sugestao.pode_lancar" class="lanc-erro" role="alert">Este movimento não está mais pendente.</p>
        <form v-else-if="sugestao" @submit.prevent="enviar">
          <p v-if="sugestao.regra" class="lanc-info"><i class="bi bi-magic"></i> Preenchido pela regra “{{ sugestao.regra.nome }}”.</p>

          <label class="form-label" for="lanc-desc">Descrição</label>
          <input id="lanc-desc" class="form-control mb-3" v-model="descricao" maxlength="255" />

          <template v-if="sugestao.tipo === 'despesa'">
            <label class="form-label" for="lanc-forn">Fornecedor</label>
            <select id="lanc-forn" class="form-control form-select mb-2" v-model="fornecedorId">
              <option value="">— novo fornecedor —</option>
              <option v-for="f in sugestao.contrapartes" :key="String(f.id)" :value="f.id">{{ f.nome }}</option>
            </select>
            <input v-if="!fornecedorId" class="form-control mb-3" v-model="novoFornecedor" placeholder="Nome do novo fornecedor" aria-label="Nome do novo fornecedor" />
          </template>
          <template v-else>
            <label class="form-label" for="lanc-cli">Cliente (opcional)</label>
            <select id="lanc-cli" class="form-control form-select mb-3" v-model="clienteId">
              <option value="">— sem cliente —</option>
              <option v-for="c in sugestao.contrapartes" :key="String(c.id)" :value="c.id">{{ c.nome }}</option>
            </select>
          </template>

          <label class="form-label" for="lanc-cat">Categoria{{ sugestao.tipo === 'despesa' ? '' : ' (opcional)' }}</label>
          <select id="lanc-cat" class="form-control form-select mb-3" v-model="categoriaId">
            <option value="">— selecione —</option>
            <option v-for="c in categorias" :key="c.id_categoria" :value="c.id_categoria">{{ c.categoria }}</option>
          </select>

          <fieldset class="mb-3">
            <legend class="form-label">Rateio entre sócios (opcional)</legend>
            <div v-for="u in socios" :key="u.id" class="lanc-rateio">
              <label :for="'rat-' + u.id">{{ u.text || u.username || u.nome || u.name }}</label>
              <input :id="'rat-' + u.id" type="number" min="0" max="100" step="0.01" class="form-control" v-model.number="rateio[u.id]" placeholder="%" />
            </div>
            <small class="text-ink-muted" v-if="!socios.length">Nenhum sócio disponível para rateio.</small>
          </fieldset>

          <div class="form-check mb-2" v-if="podeLancar">
            <input id="lanc-regra" type="checkbox" class="form-check-input" v-model="criarRegra" />
            <label for="lanc-regra" class="form-check-label">Lembrar esta classificação (criar regra para próximos extratos)</label>
          </div>
        </form>

        <p v-if="erro" class="lanc-erro mt-3" role="alert">{{ erro }}</p>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: .5rem; padding: 1rem;">
        <button type="button" class="btn btn-outline-secondary" :disabled="enviando" @click="fechar">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="enviando || carregando || !sugestao?.pode_lancar" @click="enviar">
          {{ enviando ? 'Lançando...' : 'Lançar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { pode } from '../../composables/useAcesso'
import { apiFetch, formatDate } from '../../services/api'
import { lancarMovimento, sugestaoLancamento, type DadosLancamento, type MovimentoExtrato, type SugestaoLancamento } from '../../services/extratosApi'
import { descricaoMovimento, formatarCentavos, validarRateio } from '../../utils/extratos'

const props = defineProps<{ movimento: MovimentoExtrato }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'feito', mensagem: string): void }>()

const podeLancar = pode('financeiro.extratos_lancar')
const sugestao = ref<SugestaoLancamento | null>(null)
const categorias = ref<any[]>([])
const socios = ref<any[]>([])
const carregando = ref(true)
const erroCarga = ref('')
const erro = ref('')
const enviando = ref(false)

const descricao = ref('')
const fornecedorId = ref<number | string>('')
const novoFornecedor = ref('')
const clienteId = ref<number | string>('')
const categoriaId = ref<number | string>('')
const rateio = reactive<Record<string, number | undefined>>({})
const criarRegra = ref(false)

function fechar() {
  if (!enviando.value) emit('close')
}

function montarDados(tipo: 'despesa' | 'receita'): DadosLancamento | string {
  const dados: DadosLancamento = { descricao: descricao.value.trim() || undefined }
  if (tipo === 'despesa') {
    if (fornecedorId.value) dados.fornecedor_id = fornecedorId.value
    else if (novoFornecedor.value.trim()) dados.novo_fornecedor = { nome: novoFornecedor.value.trim() }
    else return 'Informe o fornecedor.'
    if (!categoriaId.value) return 'Selecione a categoria.'
  } else if (clienteId.value) {
    dados.cliente_id = clienteId.value
  }
  if (categoriaId.value) dados.categoria_id = categoriaId.value

  const partes = Object.entries(rateio)
    .filter(([, p]) => p !== undefined && Number(p) > 0)
    .map(([id, percentual]) => ({ id, percentual: Number(percentual) }))
  const problema = validarRateio(partes)
  if (problema) return problema
  if (partes.length) dados.rateio = partes
  if (criarRegra.value) dados.criar_regra = true
  return dados
}

async function enviar() {
  // Guarda contra clique duplo: o servidor também recusa o segundo lançamento do mesmo movimento.
  if (enviando.value || !sugestao.value?.pode_lancar) return
  const dados = montarDados(sugestao.value.tipo)
  if (typeof dados === 'string') {
    erro.value = dados
    return
  }
  enviando.value = true
  erro.value = ''
  try {
    const r = await lancarMovimento(props.movimento.id_movimento, dados)
    emit('feito', r.regra_erro ? `Lançado. A regra não foi criada: ${r.regra_erro}` : 'Movimento lançado.')
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Não foi possível lançar o movimento.'
  } finally {
    enviando.value = false
  }
}

onMounted(async () => {
  try {
    const [s, cats, usuarios] = await Promise.all([
      sugestaoLancamento(props.movimento.id_movimento),
      apiFetch('financeiro/categorias/listar').catch(() => null),
      apiFetch('usuarios/participa-rateio').catch(() => null),
    ])
    sugestao.value = s
    categorias.value = cats?.data || []
    socios.value = usuarios?.results || usuarios?.data || (Array.isArray(usuarios) ? usuarios : [])
    descricao.value = s.descricao_sugerida || ''
    const r = s.regra
    if (r) {
      // A regra pode apontar para alguém que não está nas contrapartes parecidas.
      const alvo = r.fornecedor_id ?? r.cliente_id
      if (alvo && !s.contrapartes.some((c) => String(c.id) === String(alvo))) {
        s.contrapartes.push({ id: alvo, nome: `Cadastro #${alvo} (da regra)` })
      }
      if (r.fornecedor_id) fornecedorId.value = r.fornecedor_id
      if (r.cliente_id) clienteId.value = r.cliente_id
      if (r.categoria_id) categoriaId.value = r.categoria_id
      r.rateio.forEach((p) => { rateio[String(p.id)] = p.percentual })
    }
  } catch (e) {
    erroCarga.value = e instanceof Error ? e.message : 'Não foi possível carregar a sugestão.'
  } finally {
    carregando.value = false
  }
})
</script>

<style scoped>
.lanc-erro { color: #b91c1c; }
.lanc-info { color: var(--ink-muted); }
.lanc-rateio { display: flex; align-items: center; gap: .75rem; margin-bottom: .4rem; }
.lanc-rateio label { flex: 1; }
.lanc-rateio input { width: 6rem; }
</style>
