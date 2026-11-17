<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-arrow-repeat"></i> Histórico de Movimentações</span>
      <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'movimento')">
        <i class="bi bi-plus-circle"></i> Nova Movimentação
      </button>
    </div>
    <div class="section-body">
      <div v-if="!movimentacoes || !movimentacoes.length" style="font-size:.82rem;color:var(--ink-muted);text-align:center;padding:20px 0;">Nenhuma movimentação lançada.</div>
      <div v-else class="movimento-list">
        <div v-for="m in movimentacoes" :key="movimentoId(m)" class="movimento-item">
          <div class="movimento-date">{{ formatDate(m.dataHora, true) }}</div>
          <div class="movimento-desc">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              <strong style="color:var(--ink); font-weight:600;">{{ m.nome }}</strong>
              <span
                v-if="m.sentiment"
                class="sentiment-badge"
                :class="sentimentMeta(m.sentiment).className"
                :title="m.sentiment_rationale || ''"
              >
                <i :class="sentimentMeta(m.sentiment).icon"></i> {{ sentimentMeta(m.sentiment).label }}
              </span>
            </div>
            <p v-if="m.observacao" style="font-size:.78rem; color:var(--ink-muted); margin-top:2px;">{{ m.observacao }}</p>
            <div style="display:flex; gap:8px; margin-top:4px;">
              <button class="btn btn-ghost btn-sm" @click="traduzirMovimento(m.nome + ' ' + (m.observacao || ''))" style="padding: 2px 6px; font-size: 0.75rem; color: var(--accent);">
                <i class="bi bi-translate"></i> Traduzir IA
              </button>
              <button
                v-if="!m.sentiment"
                class="btn btn-ghost btn-sm"
                :disabled="analisando[movimentoId(m)]"
                @click="analisarSentimento(m)"
                style="padding: 2px 6px; font-size: 0.75rem; color: var(--accent);"
              >
                <i :class="analisando[movimentoId(m)] ? 'bi bi-hourglass-split' : 'bi bi-emoji-neutral'"></i>
                {{ analisando[movimentoId(m)] ? 'Analisando...' : 'Analisar Sentimento' }}
              </button>
            </div>
          </div>
          <button class="btn btn-ghost btn-sm" @click="removerMovimento(m.id)" style="padding: 2px 6px; border:none; color:var(--danger);">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, type PropType } from 'vue'
import { apiFetch, formatDate } from '../../../services/api'
import type { Movimentacao } from '../../../types/domain'

const props = defineProps({
  movimentacoes: {
    type: Array as PropType<Movimentacao[]>,
    default: () => []
  }
})

const emit = defineEmits(['open-modal', 'toast', 'traduzir-ia'])

const analisando = reactive({})

const SENTIMENT_META = {
  Positivo: { label: 'Favorável', icon: 'bi bi-hand-thumbs-up-fill', className: 'sentiment-positivo' },
  Negativo: { label: 'Desfavorável', icon: 'bi bi-hand-thumbs-down-fill', className: 'sentiment-negativo' },
  Neutro:   { label: 'Neutro', icon: 'bi bi-dash-circle-fill', className: 'sentiment-neutro' }
}

function sentimentMeta(sentiment) {
  return SENTIMENT_META[sentiment] || { label: sentiment, icon: 'bi bi-question-circle', className: 'sentiment-neutro' }
}

function movimentoId(m) {
  return m.id_movimento ?? m.id
}

function traduzirMovimento(texto) {
  emit('traduzir-ia', texto)
}

function removerMovimento(id) {
  emit('toast', { msg: 'Exclusão de movimentação não suportada na API.', type: 'warning' })
}

async function analisarSentimento(m) {
  const id = movimentoId(m)
  if (!id || analisando[id]) return

  analisando[id] = true
  try {
    const res = await apiFetch('processos/analisarSentimentoMovimento/' + id)
    if (res && res.success) {
      m.sentiment = res.data.sentimento
      m.sentiment_score = res.data.score_confianca
      m.sentiment_rationale = res.data.justificativa
    } else {
      emit('toast', { msg: res?.message || 'Falha ao analisar sentimento do movimento.', type: 'danger' })
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao analisar sentimento do movimento.', type: 'danger' })
  } finally {
    analisando[id] = false
  }
}
</script>

<style scoped>
.movimento-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.movimento-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.movimento-date {
  font-size: .75rem;
  color: var(--ink-dim);
  white-space: nowrap;
  margin-right: 12px;
}
.movimento-desc {
  flex: 1;
  font-size: .85rem;
}
.sentiment-badge {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  cursor: help;
  white-space: nowrap;
}
.sentiment-positivo {
  background: rgba(22, 163, 74, 0.12);
  color: var(--success, #16a34a);
}
.sentiment-negativo {
  background: rgba(220, 38, 38, 0.12);
  color: var(--danger, #dc2626);
}
.sentiment-neutro {
  background: rgba(107, 114, 128, 0.12);
  color: var(--ink-muted, #6b7280);
}
</style>
