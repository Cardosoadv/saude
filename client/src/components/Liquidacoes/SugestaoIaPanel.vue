<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-stars"></i> Sugestão de critérios por IA</span>
      <button type="button" class="btn btn-ghost btn-sm" :aria-expanded="aberto" @click="aberto = !aberto">
        <i :class="aberto ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
      </button>
    </div>
    <div v-if="aberto" class="section-body">
      <p class="ia-ajuda">
        Cole o trecho da sentença ou do acórdão que trata da correção, dos juros, da multa e dos honorários.
        A IA <strong>apenas sugere</strong>: você confere os trechos citados e confirma antes de qualquer aplicação.
        O texto enviado vai ao provedor de IA configurado no sistema.
      </p>
      <label class="form-label" for="ia-texto">Trecho da decisão</label>
      <textarea id="ia-texto" class="form-control" rows="6" v-model="texto" :disabled="carregando" maxlength="30000"
                placeholder="Ex.: ...condeno a ré ao pagamento das diferenças, corrigidas pelo IPCA-E desde o vencimento e acrescidas de juros de mora de 1% ao mês a partir da citação..."></textarea>
      <div class="ia-acoes">
        <button type="button" class="btn btn-outline-primary btn-sm" :disabled="carregando || !texto.trim()" @click="sugerir">
          <i class="bi bi-magic"></i> {{ carregando ? 'Analisando...' : 'Sugerir critérios' }}
        </button>
      </div>
      <p v-if="erro" class="ia-erro" role="alert">{{ erro }}</p>

      <div v-if="resultado" class="ia-resultado" aria-live="polite">
        <div class="ia-selo"><i class="bi bi-robot"></i> Sugerido por IA — não aplicado</div>

        <p v-if="resumo" class="ia-resumo"><strong>Critério sugerido:</strong> {{ resumo }}</p>
        <p v-else class="ia-resumo">A IA não identificou correção nem juros no texto.</p>
        <p v-if="percentuais.length" class="ia-resumo"><strong>Percentuais citados:</strong> {{ percentuais.join('; ') }} (cadastre como itens de multa/honorários, se for o caso).</p>

        <div v-if="resultado.trechos.length">
          <strong class="ia-sub">Trechos que fundamentam a sugestão</strong>
          <ul class="ia-trechos"><li v-for="t in resultado.trechos" :key="t">“{{ t }}”</li></ul>
        </div>
        <p v-if="resultado.observacoes" class="ia-obs"><strong>Observações da IA:</strong> {{ resultado.observacoes }}</p>
        <ul v-if="resultado.avisos.length" class="ia-avisos">
          <li v-for="a in resultado.avisos" :key="a"><i class="bi bi-exclamation-triangle"></i> {{ a }}</li>
        </ul>

        <template v-if="podeEditar && resumo">
          <label class="ia-confirma">
            <input type="checkbox" v-model="confirmado" />
            Conferi a decisão e confirmo que este critério está correto.
          </label>
          <button type="button" class="btn btn-primary btn-sm" :disabled="!confirmado" @click="aplicar">
            <i class="bi bi-check2-circle"></i> Aplicar como critério padrão da liquidação
          </button>
          <small class="ia-ajuda">Substitui o critério padrão atual e fica registrado nas observações. Depois, use “Recalcular”.</small>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '../../services/api'
import { sugerirCriterio } from '../../services/liquidacoesApi'
import type { Criterio, SugestaoIa } from '../../types/liquidacao'
import type { Id } from '../../types/domain'
import { resumirCriterio } from '../../utils/liquidacao'

const props = defineProps<{ liquidacaoId: Id; podeEditar: boolean }>()
const emit = defineEmits<{ (e: 'aplicar', criterio: Criterio, resumo: string): void }>()

const aberto = ref(false)
const texto = ref('')
const carregando = ref(false)
const erro = ref('')
const resultado = ref<SugestaoIa | null>(null)
const confirmado = ref(false)

const resumo = computed(() => {
  const c = resultado.value?.criterio
  return c && Object.keys(c).length ? resumirCriterio(c) : ''
})

const percentuais = computed(() => {
  const s = resultado.value?.sugestao
  const saida: string[] = []
  if (s?.multa_percentual) saida.push(`multa ${s.multa_percentual.replace('.', ',')}%`)
  if (s?.honorarios_percentual) saida.push(`honorários ${s.honorarios_percentual.replace('.', ',')}%`)
  return saida
})

async function sugerir() {
  carregando.value = true
  erro.value = ''
  confirmado.value = false
  resultado.value = null
  try {
    resultado.value = await sugerirCriterio(props.liquidacaoId, texto.value)
  } catch (e) {
    erro.value = e instanceof ApiError || e instanceof Error ? e.message : 'Não foi possível obter a sugestão.'
  } finally {
    carregando.value = false
  }
}

function aplicar() {
  if (!resultado.value || !confirmado.value) return
  emit('aplicar', resultado.value.criterio, resumo.value)
  confirmado.value = false
}
</script>

<style scoped>
.ia-ajuda { color: var(--ink-muted); font-size: .8rem; margin: 0 0 10px; display: block; }
.ia-acoes { margin: 10px 0; }
.ia-erro { color: var(--danger); font-size: .85rem; }
.ia-resultado { border: 1px dashed var(--accent); border-radius: 8px; padding: 12px; margin-top: 8px; }
.ia-selo { display: inline-block; background: #ede9fe; color: #5b21b6; border-radius: 12px; padding: 2px 10px; font-size: .75rem; font-weight: 600; margin-bottom: 8px; }
.ia-resumo { font-size: .88rem; margin: 6px 0; }
.ia-sub { font-size: .8rem; }
.ia-trechos { margin: 4px 0 8px 18px; padding: 0; font-size: .82rem; color: var(--ink-muted); }
.ia-obs { font-size: .82rem; }
.ia-avisos { list-style: none; margin: 6px 0; padding: 0; color: #92400e; font-size: .82rem; }
.ia-confirma { display: flex; gap: 8px; align-items: center; font-size: .85rem; margin: 10px 0 8px; }
</style>
