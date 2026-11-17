<template>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <h3><i class="bi bi-clock-history"></i> Últimos Movimentados</h3>
    </div>
    <ul class="widget-list">
      <li v-if="!items.length" style="color:var(--ink-muted);font-size:.82rem;">Carregando...</li>
      <li
        v-for="m in items"
        :key="m.id_processo || m.id"
        @click="$emit('select-process', m.id_processo || m.id)"
        role="button"
        tabindex="0"
        @keydown.enter="$emit('select-process', m.id_processo || m.id)"
        @keydown.space.prevent="$emit('select-process', m.id_processo || m.id)"
        :aria-label="'Ver processo: ' + (m.numero_processo || m.nome)"
      >
        <span class="process-num">{{ formatCNJ(m.numero_processo) }}</span>
        <span class="process-date">{{ m.nome || m.titulo_processo || 'Sem título' }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { formatCNJ } from '../../../services/api'
import type { ProcessoResumo } from '../../../types/domain'

defineProps({
  items: {
    type: Array as PropType<ProcessoResumo[]>,
    required: true
  }
})

defineEmits(['select-process'])
</script>
