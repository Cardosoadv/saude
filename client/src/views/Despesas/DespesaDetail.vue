<template>
  <FinanceiroDetail
    type="despesa"
    :transaction-id="despesaId"
    @set-view="(view) => $emit('set-view', view)"
    @toast="(payload) => $emit('toast', payload)"
  />
</template>

<script setup lang="ts">
import FinanceiroDetail from '../../components/Financeiro/FinanceiroDetail.vue'

defineProps({
  despesaId: {
    type: [Number, String],
    default: null
  }
})

defineEmits(['set-view', 'toast'])
</script>
