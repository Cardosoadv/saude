<template>
  <div v-if="show" class="modal-backdrop" @click.self="fechar">
    <div class="modal-box" style="max-width: 720px;">
      <div class="modal-header">
        <h2><i class="bi bi-stars text-accent"></i> Enriquecer dados {{ tipo === 'cliente' ? 'do cliente' : 'do fornecedor' }}</h2>
        <button class="modal-close" @click="fechar" aria-label="Fechar">&times;</button>
      </div>

      <div class="modal-body enr-body">
        <div v-if="nome" class="enr-alvo">
          <i class="bi" :class="tipo === 'cliente' ? 'bi-person' : 'bi-truck'"></i>
          <strong>{{ nome }}</strong>
          <span v-if="documento" class="enr-doc">{{ formatDocument(documento) }}</span>
        </div>

        <!-- Confirmação de consulta tarifada (CPF sem consulta prévia) -->
        <div v-if="etapa === 'confirmar'" class="alert alert-warning enr-alert">
          <i class="bi bi-cash-coin"></i>
          <div>
            Os dados serão buscados na consulta de CPF da <strong>API Brasil</strong>,
            <template v-if="homolog">em <strong>modo de homologação</strong> (dados fictícios, sem cobrança).</template>
            <template v-else>que é <strong>tarifada</strong> a cada consulta.</template>
            Nada é gravado sem a sua confirmação.
          </div>
        </div>

        <div v-else-if="etapa === 'carregando'" class="enr-estado">
          <i class="bi bi-arrow-repeat spin-icon"></i> Buscando dados...
        </div>

        <div v-else-if="etapa === 'erro'" class="alert alert-danger enr-alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div>{{ erro }}</div>
        </div>

        <template v-else-if="etapa === 'sugestoes'">
          <div v-if="!sugestoes.length" class="enr-estado enr-ok">
            <i class="bi bi-check-circle"></i> O cadastro já está atualizado com os dados encontrados.
          </div>
          <template v-else>
            <p class="enr-ajuda">
              Marque os campos que deseja atualizar. Campos vazios no cadastro já vêm marcados;
              os que substituiriam um valor existente ficam desmarcados.
            </p>
            <div class="table-responsive">
              <table class="table enr-tabela">
                <thead>
                  <tr>
                    <th class="enr-check">
                      <input type="checkbox" :checked="todosMarcados" :indeterminate.prop="algunsMarcados" @change="marcarTodos(($event.target as HTMLInputElement).checked)" aria-label="Marcar todos" />
                    </th>
                    <th>Campo</th>
                    <th>Atual</th>
                    <th>Encontrado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in sugestoes" :key="s.campo" :class="{ 'enr-marcado': marcados[s.campo] }" @click="marcados[s.campo] = !marcados[s.campo]">
                    <td class="enr-check"><input type="checkbox" v-model="marcados[s.campo]" @click.stop :aria-label="`Atualizar ${s.rotulo}`" /></td>
                    <td class="enr-rotulo">{{ s.rotulo }}</td>
                    <td class="enr-atual">{{ exibir(s.campo, s.atual) || '—' }}</td>
                    <td class="enr-sugerido">{{ exibir(s.campo, s.sugerido) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          <div class="enr-fonte">
            <i class="bi bi-info-circle"></i>
            Fonte: {{ fonte === 'cnpj' ? 'Receita Federal (BrasilAPI)' : 'API Brasil — consulta de CPF' }}<span v-if="consulta">
              · valor cobrado {{ formatCurrency(consulta.valor_consulta) }}<span v-if="consulta.homolog"> (homologação)</span></span>
          </div>
        </template>
      </div>

      <div class="modal-footer enr-footer">
        <button class="btn btn-outline-secondary" @click="fechar" :disabled="salvando">
          {{ etapa === 'sugestoes' && sugestoes.length ? 'Cancelar' : 'Fechar' }}
        </button>
        <button v-if="etapa === 'confirmar'" class="btn btn-primary" @click="buscar">
          <i class="bi bi-search"></i> Consultar CPF
        </button>
        <button v-if="etapa === 'erro'" class="btn btn-primary" @click="buscar">
          <i class="bi bi-arrow-clockwise"></i> Tentar novamente
        </button>
        <button v-if="etapa === 'sugestoes' && sugestoes.length" class="btn btn-primary" @click="aplicar" :disabled="salvando || !qtdMarcados">
          <i class="bi" :class="salvando ? 'bi-arrow-repeat spin-icon' : 'bi-check2-circle'"></i>
          {{ salvando ? 'Salvando...' : `Atualizar ${qtdMarcados} campo(s)` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { formatDate, formatCurrency, formatDocument } from '../../services/api'
import {
  sugerirEnriquecimento,
  aplicarEnriquecimento,
  homologAtivo,
  somenteDigitos,
  type TipoCadastro,
  type SugestaoEnriquecimento,
  type ResultadoEnriquecimento,
} from '../../services/consultasApi'

const props = defineProps<{
  show: boolean
  tipo: TipoCadastro
  id: number
  nome?: string
  documento?: string
  /** `data.resultado` de uma consulta de CPF já feita: evita nova cobrança. */
  resultadoCpf?: unknown
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'aplicado', campos: Record<string, string>): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

type Etapa = 'confirmar' | 'carregando' | 'erro' | 'sugestoes'

const etapa = ref<Etapa>('carregando')
const erro = ref('')
const homolog = ref(false)
const salvando = ref(false)
const sugestoes = ref<SugestaoEnriquecimento[]>([])
const fonte = ref<ResultadoEnriquecimento['fonte']>('cpf')
const consulta = ref<ResultadoEnriquecimento['consulta']>(null)
const marcados = reactive<Record<string, boolean>>({})

const qtdMarcados = computed(() => sugestoes.value.filter(s => marcados[s.campo]).length)
const todosMarcados = computed(() => sugestoes.value.length > 0 && qtdMarcados.value === sugestoes.value.length)
const algunsMarcados = computed(() => qtdMarcados.value > 0 && !todosMarcados.value)

/** CPF sem consulta prévia cobra: pede confirmação. CNPJ (BrasilAPI) é gratuito. */
const precisaConfirmar = computed(() => somenteDigitos(props.documento || '').length !== 14 && !props.resultadoCpf)

watch(() => props.show, async (aberto) => {
  if (!aberto) return
  erro.value = ''
  sugestoes.value = []
  consulta.value = null
  salvando.value = false
  if (precisaConfirmar.value) {
    etapa.value = 'confirmar'
    homolog.value = await homologAtivo()
  } else {
    buscar()
  }
}, { immediate: true })

async function buscar() {
  etapa.value = 'carregando'
  try {
    const r = await sugerirEnriquecimento(props.tipo, props.id, props.resultadoCpf)
    sugestoes.value = r.sugestoes
    fonte.value = r.fonte
    consulta.value = r.consulta
    Object.keys(marcados).forEach(k => delete marcados[k])
    r.sugestoes.forEach(s => { marcados[s.campo] = !s.atual })
    etapa.value = 'sugestoes'
  } catch (err: any) {
    erro.value = err?.data?.message || err?.message || 'Não foi possível buscar os dados.'
    etapa.value = 'erro'
  }
}

function marcarTodos(valor: boolean) {
  sugestoes.value.forEach(s => { marcados[s.campo] = valor })
}

function exibir(campo: string, valor: string | null): string {
  if (!valor) return ''
  return campo === 'data_nascimento' ? formatDate(valor) : valor
}

async function aplicar() {
  const campos: Record<string, string> = {}
  sugestoes.value.forEach(s => { if (marcados[s.campo]) campos[s.campo] = s.sugerido })
  salvando.value = true
  try {
    const r = await aplicarEnriquecimento(props.tipo, props.id, campos)
    emit('toast', { msg: r.message, type: 'success' })
    emit('aplicado', r.campos)
    emit('close')
  } catch (err: any) {
    emit('toast', { msg: err?.data?.message || err?.message || 'Não foi possível atualizar o cadastro.', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

function fechar() {
  if (!salvando.value) emit('close')
}
</script>

<style scoped>
.enr-body {
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.enr-alvo {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink);
}

.enr-doc {
  color: var(--ink-muted);
  font-size: 0.85rem;
}

.enr-alert {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 0.88rem;
  line-height: 1.45;
  margin: 0;
}

.enr-estado {
  padding: 24px 0;
  text-align: center;
  color: var(--ink-muted);
}

.enr-ok i {
  color: var(--success);
}

.enr-ajuda {
  margin: 0;
  font-size: 0.85rem;
  color: var(--ink-muted);
}

.enr-tabela {
  width: 100%;
  font-size: 0.86rem;
}

.enr-tabela tbody tr {
  cursor: pointer;
}

.enr-tabela tbody tr.enr-marcado {
  background: var(--surface);
}

.enr-check {
  width: 36px;
  text-align: center;
}

.enr-rotulo {
  font-weight: 600;
  white-space: nowrap;
}

.enr-atual {
  color: var(--ink-muted);
}

.enr-marcado .enr-atual {
  text-decoration: line-through;
}

.enr-sugerido {
  color: var(--ink);
  font-weight: 600;
}

.enr-fonte {
  font-size: 0.78rem;
  color: var(--ink-muted);
  font-style: italic;
}

.enr-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
}

.spin-icon {
  animation: enr-spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes enr-spin {
  to { transform: rotate(360deg); }
}
</style>
