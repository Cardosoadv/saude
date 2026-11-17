<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box item-modal" role="dialog" aria-modal="true" :aria-label="item ? 'Editar item' : 'Novo item'">
      <div class="modal-header">
        <h2><i class="bi bi-calculator"></i> {{ item ? 'Editar item' : 'Novo item' }}</h2>
        <button type="button" class="modal-close" :disabled="salvando" aria-label="Fechar" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding: 1.25rem;">
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" for="item-tipo">Tipo</label>
            <select id="item-tipo" class="form-control form-select" v-model="form.tipo_item">
              <option v-for="(rotulo, slug) in TIPOS_ITEM" :key="slug" :value="slug">{{ rotulo }}</option>
            </select>
          </div>
          <div class="form-group" v-if="!percentual">
            <label class="form-label" for="item-valor">Valor base (R$)</label>
            <input id="item-valor" class="form-control" type="text" inputmode="decimal" v-model="form.valor_base" placeholder="0,00" />
            <small class="item-ajuda">Principal devido na data do vencimento, sem correção.</small>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="item-desc">Descrição</label>
          <input id="item-desc" class="form-control" type="text" v-model="form.descricao" maxlength="500" placeholder="Ex.: Diferenças salariais" />
        </div>

        <CriterioForm v-model="criterio" :indices="indices" :percentual="percentual" />

        <div v-if="!percentual" class="item-simulacao">
          <button type="button" class="btn btn-outline-primary btn-sm" :disabled="simulando" @click="calcularAgora">
            <i class="bi bi-lightning-charge"></i> {{ simulando ? 'Calculando...' : 'Calcular agora' }}
          </button>
          <span v-if="simulado !== null" class="item-resultado">Valor na data-base: <strong>{{ formatarReais(simulado) }}</strong> (não gravado)</span>
        </div>
        <p v-else class="item-ajuda">O valor de multa e honorários sai do percentual sobre os demais itens; aparece após salvar.</p>

        <p v-if="erro" class="item-erro" role="alert">{{ erro }}</p>
      </div>

      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:.5rem; padding:1rem;">
        <button type="button" class="btn btn-outline-secondary" :disabled="salvando" @click="fechar">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="salvando" @click="salvar">{{ salvando ? 'Salvando...' : 'Salvar e recalcular' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ApiError } from '../../services/api'
import { salvarItem, simular } from '../../services/liquidacoesApi'
import type { Criterio, ItemLiquidacao, LiquidacaoDetalhe, TipoItem } from '../../types/liquidacao'
import { ITENS_PERCENTUAIS, TIPOS_ITEM, centavosParaTexto, formatarReais, limparCriterio, textoParaCentavos } from '../../utils/liquidacao'
import CriterioForm from './CriterioForm.vue'

const props = defineProps<{
  liquidacao: Pick<LiquidacaoDetalhe, 'liquidacao_id' | 'data_base' | 'criterio_padrao'>
  item?: ItemLiquidacao | null
  indices: string[]
  /** Critério sugerido pela IA e já confirmado pelo advogado, para preencher o formulário. */
  criterioInicial?: Criterio | null
}>()
const emit = defineEmits<{
  (e: 'close'): void
  (e: 'salvo'): void
}>()

const form = reactive({
  tipo_item: (props.item?.tipo_item ?? 'principal') as TipoItem,
  descricao: props.item?.descricao ?? '',
  valor_base: centavosParaTexto(props.item?.valor_base),
})
const criterio = ref<Criterio>(props.criterioInicial ?? props.item?.criterio ?? {})
const percentual = computed(() => ITENS_PERCENTUAIS.includes(form.tipo_item))

const salvando = ref(false)
const simulando = ref(false)
const simulado = ref<number | null>(null)
const erro = ref('')

function valorBase(): number | null {
  if (percentual.value) return 0
  if (form.valor_base.trim() === '') return 0
  return textoParaCentavos(form.valor_base)
}

function mensagem(e: unknown, padrao: string): string {
  if (e instanceof ApiError) {
    const detalhes = e.data?.errors && typeof e.data.errors === 'object' ? Object.values(e.data.errors).join(' ') : ''
    return [e.message, detalhes].filter(Boolean).join(' ')
  }
  return e instanceof Error ? e.message : padrao
}

async function calcularAgora() {
  const base = valorBase()
  if (base === null) { erro.value = 'Valor base inválido. Use o formato 1.234,56.'; return }
  simulando.value = true
  erro.value = ''
  try {
    const r = await simular({
      item: { tipo_item: form.tipo_item, descricao: form.descricao, valor_base: base, criterio: limparCriterio(criterio.value) },
      criterio_padrao: props.liquidacao.criterio_padrao ?? {},
      data_base: props.liquidacao.data_base ?? undefined,
    })
    simulado.value = r.cenarios[0]?.valor ?? null
  } catch (e) {
    simulado.value = null
    erro.value = mensagem(e, 'Não foi possível calcular.')
  } finally {
    simulando.value = false
  }
}

async function salvar() {
  const base = valorBase()
  if (base === null) { erro.value = 'Valor base inválido. Use o formato 1.234,56.'; return }
  if (!form.descricao.trim()) { erro.value = 'Informe a descrição do item.'; return }

  salvando.value = true
  erro.value = ''
  try {
    await salvarItem(props.liquidacao.liquidacao_id, {
      tipo_item: form.tipo_item,
      descricao: form.descricao.trim(),
      valor_base: base,
      criterio: limparCriterio(criterio.value),
    }, props.item?.item_id)
    emit('salvo')
    emit('close')
  } catch (e) {
    erro.value = mensagem(e, 'Não foi possível salvar o item.')
  } finally {
    salvando.value = false
  }
}

function fechar() {
  if (!salvando.value) emit('close')
}
</script>

<style scoped>
.item-modal { max-width: 760px; width: 100%; max-height: 92vh; display: flex; flex-direction: column; }
.item-modal .modal-body { overflow-y: auto; }
.item-ajuda { color: var(--ink-muted); font-size: .78rem; }
.item-simulacao { display: flex; align-items: center; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
.item-resultado { font-size: .9rem; }
.item-erro { color: var(--danger); font-size: .85rem; margin: 12px 0 0; }
</style>
