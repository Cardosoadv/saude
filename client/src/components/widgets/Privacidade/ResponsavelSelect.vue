<template>
  <select
    :id="id"
    class="form-control"
    :value="modelValue ?? ''"
    :disabled="disabled"
    @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <option value="">{{ placeholder }}</option>
    <option v-for="adv in advogados" :key="adv.id" :value="String(adv.id)">{{ adv.username }}</option>
  </select>
</template>

<script setup lang="ts">
/**
 * Seleção do advogado responsável (privacidade granular) para clientes e
 * contratos, e filtro por responsável nas listagens.
 */
import { useAdvogados } from '../../../composables/useAdvogados'

defineProps({
  id: { type: String, default: 'responsavel_id' },
  modelValue: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
  /** Texto da opção vazia (ex.: "Todos os responsáveis" em filtros). */
  placeholder: { type: String, default: 'Sem responsável' }
})

const emit = defineEmits(['update:modelValue'])

const { advogados } = useAdvogados()
</script>
