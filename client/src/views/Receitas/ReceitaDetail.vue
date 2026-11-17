<template>
  <FinanceiroDetail
    type="receita"
    :transaction-id="receitaId"
    @set-view="(view) => $emit('set-view', view)"
    @toast="(payload) => $emit('toast', payload)"
  />
</template>

<script setup lang="ts">
import FinanceiroDetail from '../../components/Financeiro/FinanceiroDetail.vue'

defineProps({
  receitaId: {
    type: [Number, String],
    default: null
  }
})

defineEmits(['set-view', 'toast'])
</script>
