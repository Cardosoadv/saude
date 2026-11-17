<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 420px;">
      <div class="modal-header">
        <h2><i class="bi bi-tags"></i> Gerenciar Etiquetas</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="padding-top:10px;">
        <p style="font-size:.8rem;color:var(--ink-muted);margin-bottom:14px;">Selecione as etiquetas para este processo:
        </p>

        <div
          style="display:flex; flex-direction:column; gap:6px; max-height: 280px; overflow-y: auto; padding-right:4px;">
          <div v-for="tag in availableTags" :key="tag.id || tag.id_etiqueta" @click="$emit('toggle-tag', tag)"
            style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border:1px solid var(--border); border-radius:8px; cursor:pointer; transition:all .12s;"
            :style="isSelected(tag) ? { borderColor: '#' + tag.cor, background: '#' + tag.cor + '15' } : {}">
            <span class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }">{{ tag.nome }}</span>
            <i v-if="isSelected(tag)" class="bi bi-check-circle-fill" :style="{ color: '#' + tag.cor }"></i>
            <i v-else class="bi bi-circle" style="color:var(--border);"></i>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="padding-top:10px;">
        <button class="btn btn-primary btn-sm" @click="$emit('close')">Concluir</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { Etiqueta } from '../../../types/domain'
const props = defineProps({
  selectedTags: {
    type: Array as PropType<Etiqueta[]>,
    default: () => []
  },
  availableTags: {
    type: Array as PropType<Etiqueta[]>,
    default: () => []
  }
})

defineEmits(['close', 'toggle-tag'])

function isSelected(tag) {
  return props.selectedTags.some(t => t.id === tag.id)
}
</script>
