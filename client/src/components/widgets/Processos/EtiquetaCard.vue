<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-tags"></i> Etiquetas</span>
      <a href="#" @click.prevent="$emit('open-modal', 'etiqueta')" style="font-size:.78rem; color:var(--primary); text-decoration:none; font-weight:500;">
        <i class="bi bi-pencil"></i> Gerenciar
      </a>
    </div>
    <div class="section-body" style="display:flex; flex-wrap:wrap; gap:6px;">
      <span v-if="!etiquetas || !etiquetas.length" style="font-size:.8rem;color:var(--ink-muted);">Nenhuma etiqueta vinculada.</span>
      <span 
        v-for="t in etiquetas" 
        :key="t.id || t.id_etiqueta"
        class="badge"
        :style="{ background: '#' + t.cor, color: '#fff', fontSize: '.75rem', padding: '4px 10px' }"
      >
        {{ t.nome }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { Etiqueta } from '../../../types/domain'
defineProps({
  etiquetas: {
    type: Array as PropType<Etiqueta[]>,
    default: () => []
  }
})
defineEmits(['open-modal'])
</script>
