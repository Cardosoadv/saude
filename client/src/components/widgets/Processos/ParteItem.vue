<template>
  <div class="parte-item">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      <span>
        <i :class="iconClass" :style="{ color: iconColor, marginRight: '6px' }"></i>
        {{ part.nome }}
      </span>
      <span
        class="badge"
        :style="badgeStyle"
        @click="$emit('toggle-represented', part)"
        :title="badgeTitle"
        role="button"
        tabindex="0"
        @keydown.enter="$emit('toggle-represented', part)"
        @keydown.space.prevent="$emit('toggle-represented', part)"
        :aria-label="badgeTitle"
      >
        <i :class="starIconClass" :style="starIconStyle"></i>
        {{ badgeText }}
      </span>
    </div>
    <button class="btn btn-danger btn-sm" @click="$emit('remove', part.id_parte || part.id)" style="padding:2px 6px; font-size:.7rem;">
      <i class="bi bi-trash"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import type { Parte } from '../../../types/domain'

const props = defineProps({
  part: {
    type: Object as PropType<Parte>,
    required: true
  },
  poloType: {
    type: String,
    required: true,
    validator: (value: string) => ['ativo', 'passivo'].includes(value.toLowerCase())
  }
})

defineEmits(['toggle-represented', 'remove'])

const isRepresented = computed(() => Number(props.part.is_represented) === 1)

const iconClass = computed(() =>
  props.poloType.toLowerCase() === 'ativo' ? 'bi bi-person-check-fill' : 'bi bi-person-x-fill'
)

const iconColor = computed(() =>
  props.poloType.toLowerCase() === 'ativo' ? 'var(--success)' : 'var(--danger)'
)

const badgeStyle = computed(() =>
  isRepresented.value
    ? 'background: var(--primary-lt); color: var(--primary); border: 1px solid rgba(1, 59, 81, 0.2); cursor: pointer; transition: all 0.2s;'
    : 'background: #f3f4f6; color: #6b7280; opacity: 0.5; cursor: pointer; transition: all 0.2s;'
)

const badgeTitle = computed(() =>
  isRepresented.value
    ? 'Representado por nós (Clique para desmarcar)'
    : 'Não representado (Clique para marcar)'
)

const starIconClass = computed(() =>
  isRepresented.value ? 'bi bi-star-fill' : 'bi bi-star'
)

const starIconStyle = computed(() =>
  isRepresented.value ? 'color: var(--accent);' : ''
)

const badgeText = computed(() =>
  isRepresented.value ? 'Representado' : 'Marcar Representado'
)
</script>
