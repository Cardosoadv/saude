<template>
  <div class="toast-container">
    <div v-for="toast in toasts" :key="toast.id" :class="['toast', toast.type]">
      <i :class="toast.type === 'success' ? 'bi bi-check-circle-fill' : toast.type === 'warning' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-x-circle-fill'"></i>
      {{ toast.msg }}
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { Toast } from '../../types/domain'
defineProps({
  toasts: {
    type: Array as PropType<Toast[]>,
    required: true
  }
})
</script>
