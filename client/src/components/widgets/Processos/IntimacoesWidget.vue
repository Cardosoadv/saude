<template>
  <div class="card">
    <div class="card-header">
      <h3><i class="bi bi-envelope"></i> Últimas Intimações</h3>
    </div>
    <ul class="widget-list">
      <li v-if="!items.length" style="color:var(--ink-muted);font-size:.82rem;">Carregando...</li>
      <li v-for="i in items" :key="i.numero_processo">
        <span class="process-num">{{ formatCNJ(i.numero_processo) }}</span>
        <span class="process-date">{{ i.tipoComunicacao || 'Intimação' }} · {{ formatDate(i.data_disponibilizacao)
          }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { formatCNJ, formatDate } from '../../../services/api'
import type { Intimacao } from '../../../types/domain'

defineProps({
  items: {
    type: Array as PropType<Intimacao[]>,
    required: true
  }
})
</script>
