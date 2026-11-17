<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="nfse-emitir-titulo" style="max-width:720px;">
      <div class="modal-header">
        <h2 id="nfse-emitir-titulo"><i class="bi bi-receipt-cutoff"></i> Emitir NFS-e</h2>
        <button type="button" class="modal-close" :disabled="enviando" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding:1.5rem;">
        <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Montando a nota...</div>
        <p v-else-if="erroCarga" class="nfse-erro" role="alert">{{ erroCarga }}</p>

        <template v-else-if="previa">
          <section class="nfse-bloco">
            <h6>Tomador do serviço</h6>
            <template v-if="previa.tomador">
              <p class="mb-1"><strong>{{ previa.tomador.razao_social }}</strong> · {{ formatDocument(previa.tomador.cpf || previa.tomador.cnpj) }}</p>
              <p class="text-ink-muted mb-0" style="font-size:.85rem;">
                {{ enderecoTomador }}<template v-if="previa.tomador.email"> · {{ previa.tomador.email }}</template>
              </p>
            </template>
            <p v-else class="text-ink-muted mb-0">Complete o cadastro do cliente para emitir a nota.</p>
          </section>

          <section class="nfse-bloco">
            <h6>Serviço</h6>
            <div class="nfse-grid">
              <div class="form-group">
                <label for="nfse-valor">Valor do serviço (R$)</label>
                <input id="nfse-valor" v-model="campos.valor" type="text" inputmode="decimal" class="form-control" />
              </div>
              <div class="form-group">
                <label for="nfse-competencia">Competência</label>
                <input id="nfse-competencia" v-model="campos.data_competencia" type="date" class="form-control" />
              </div>
            </div>
            <div class="form-group" style="margin-top:10px;">
              <label for="nfse-discriminacao">Discriminação</label>
              <textarea id="nfse-discriminacao" v-model="campos.discriminacao" class="form-control" rows="3" maxlength="2000"></textarea>
            </div>
          </section>

          <details class="nfse-bloco" :open="faltaFiscal">
            <summary><h6 style="display:inline;">Dados fiscais</h6> <small class="text-ink-muted">(padrões da configuração)</small></summary>
            <div class="nfse-grid" style="margin-top:10px;">
              <div class="form-group">
                <label for="nfse-codigo">Código do serviço (LC 116)</label>
                <input id="nfse-codigo" v-model="campos.servico_codigo" class="form-control" />
              </div>
              <div class="form-group">
                <label for="nfse-nacional">Código de tributação nacional</label>
                <input id="nfse-nacional" v-model="campos.codigo_nacional" class="form-control" />
              </div>
              <div class="form-group">
                <label for="nfse-nbs">Código NBS</label>
                <input id="nfse-nbs" v-model="campos.codigo_nbs" class="form-control" maxlength="12" />
              </div>
              <div class="form-group">
                <label for="nfse-cnae">CNAE</label>
                <input id="nfse-cnae" v-model="campos.codigo_cnae" class="form-control" maxlength="10" />
              </div>
              <div class="form-group">
                <label for="nfse-municipio">Município da prestação (IBGE)</label>
                <input id="nfse-municipio" v-model="campos.codigo_municipio" class="form-control" maxlength="7" />
              </div>
              <div class="form-group">
                <label for="nfse-trib-mun">Código de tributação municipal</label>
                <input id="nfse-trib-mun" v-model="campos.codigo_tributacao_municipio" class="form-control" />
              </div>
              <div class="form-group">
                <label for="nfse-aliquota">Alíquota do ISS (%)</label>
                <input id="nfse-aliquota" v-model="campos.aliquota_iss" type="text" inputmode="decimal" class="form-control" />
              </div>
              <div class="form-group nfse-check">
                <label><input v-model="campos.iss_retido" type="checkbox" /> ISS retido pelo tomador</label>
              </div>
            </div>
          </details>

          <div v-if="previa.pendencias.length" class="nfse-pendencias" role="alert">
            <strong>Antes de emitir:</strong>
            <ul><li v-for="p in previa.pendencias" :key="p">{{ p }}</li></ul>
            <button type="button" class="btn btn-sm btn-outline-primary" :disabled="carregando" @click="carregar(true)">Conferir de novo</button>
          </div>

          <label class="nfse-confirma">
            <input v-model="confirmado" type="checkbox" />
            Entendo que a nota é real, vai para a prefeitura e tem validade fiscal (R$ 0,50 por nota autorizada).
          </label>

          <p v-if="erro" class="nfse-erro" role="alert">{{ erro }}</p>
        </template>
      </div>

      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px; padding:1rem;">
        <button type="button" class="btn btn-outline-secondary" :disabled="enviando" @click="fechar">Cancelar</button>
        <button
          type="button"
          class="btn btn-success"
          :disabled="enviando || carregando || !previa || !previa.tomador || !confirmado"
          @click="emitir"
        >
          <i class="bi bi-send"></i> {{ enviando ? 'Enviando à prefeitura...' : 'Emitir nota' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { formatDocument, formatNumberBr } from '../../services/api'
import { emitirNota, previaNota, type CamposEmissao, type NotaFiscal, type PreviaNota } from '../../services/notasFiscaisApi'

const props = defineProps<{ receitaId: number | string }>()
const emit = defineEmits<{
  (e: 'fechar'): void
  (e: 'emitida', nota: NotaFiscal, mensagem: string): void
}>()

const previa = ref<PreviaNota | null>(null)
const carregando = ref(false)
const enviando = ref(false)
const erroCarga = ref('')
const erro = ref('')
const confirmado = ref(false)
const campos = reactive<Record<string, any>>({})

const enderecoTomador = computed(() => {
  const e = previa.value?.tomador?.endereco ?? {}
  return [e.logradouro, e.numero, e.complemento, e.bairro, e.uf, e.cep].filter(Boolean).join(', ')
})

const faltaFiscal = computed(() => !campos.servico_codigo || !campos.codigo_nbs || !campos.codigo_municipio)

/** Valores digitados → corpo da API (o backend aceita "1.234,56"). */
function corpo(): Partial<CamposEmissao> {
  const saida: Record<string, any> = { ...campos }
  saida.iss_retido = !!campos.iss_retido
  return saida
}

async function carregar(comAjustes = false) {
  carregando.value = true
  erroCarga.value = ''
  try {
    const dados = await previaNota(props.receitaId, comAjustes ? corpo() : {})
    previa.value = dados
    Object.assign(campos, dados.campos, {
      valor: dados.campos.valor !== null ? formatNumberBr(dados.campos.valor) : '',
      aliquota_iss: dados.campos.aliquota_iss ?? '',
    })
  } catch (e) {
    erroCarga.value = e instanceof Error ? e.message : 'Não foi possível montar a nota.'
  } finally {
    carregando.value = false
  }
}

async function emitir() {
  if (enviando.value) return
  enviando.value = true
  erro.value = ''
  try {
    const res = await emitirNota(props.receitaId, corpo())
    emit('emitida', res.data, res.message || 'Nota enviada.')
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Não foi possível emitir a nota.'
  } finally {
    enviando.value = false
  }
}

function fechar() {
  if (!enviando.value) emit('fechar')
}

onMounted(() => carregar())
</script>

<style scoped>
.nfse-bloco { border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
.nfse-bloco h6 { margin: 0 0 8px; }
.nfse-bloco summary { cursor: pointer; }
.nfse-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.nfse-check { display: flex; align-items: flex-end; }
.nfse-check label { display: flex; gap: 6px; align-items: center; margin: 0; }
.nfse-pendencias { font-size: .85rem; background: rgba(220, 53, 69, .06); border: 1px solid rgba(220, 53, 69, .3); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
.nfse-pendencias ul { margin: 6px 0 8px; padding-left: 18px; }
.nfse-confirma { display: flex; gap: 8px; align-items: flex-start; font-size: .85rem; }
.nfse-erro { color: var(--danger); font-size: .85rem; margin: 10px 0 0; }
</style>
