<template>
  <div>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:1rem;" v-if="!isNew">
      <button class="btn btn-sm btn-primary" @click="$emit('open-historico-modal')">
        <i class="bi bi-plus-circle"></i> Adicionar Histórico
      </button>
    </div>
    <div class="timeline">
      <div class="timeline-item" v-for="(h, idx) in historico" :key="idx">
        <div class="timeline-badge"><i class="bi bi-clock-history"></i></div>
        <div class="timeline-card glass-panel">
          <div class="timeline-header">
            <strong>{{ h.acao }}</strong>
            <span class="timeline-date">{{ formatDate(h.created_at, true) }}</span>
          </div>
          <p class="timeline-body" v-if="h.descricao">{{ h.descricao }}</p>
        </div>
      </div>
      <div class="timeline-empty" v-if="!historico.length">
        Nenhum histórico registrado para este caso.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { formatDate } from '../../../services/api'
import type { HistoricoCaso } from '../../../types/domain'

defineProps({
  historico: {
    type: Array as PropType<HistoricoCaso[]>,
    default: () => []
  },
  isNew: {
    type: Boolean,
    default: false
  }
})

defineEmits(['open-historico-modal'])
</script>

<style scoped>
/* A estilização .timeline que estava em CasoDetail.vue */
.timeline { display: flex; flex-direction: column; gap: 1.5rem; position: relative; padding-left: 2rem; }
.timeline::before { content: ''; position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background-color: rgba(255, 255, 255, 0.1); }
.timeline-item { position: relative; }
.timeline-badge { position: absolute; left: -2rem; top: 0.5rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; background-color: var(--accent); display: flex; justify-content: center; align-items: center; color: white; font-size: 0.75rem; }
.timeline-card { padding: 1rem; border-radius: 6px; }
.timeline-header { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; }
.timeline-date { color: var(--ink-muted); }
.timeline-body { margin: 0; font-size: 0.9rem; color: var(--ink-muted); line-height: 1.5; }
.timeline-empty { padding: 2rem; text-align: center; color: var(--ink-muted); }
</style>
