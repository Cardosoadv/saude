<template>
  <div class="icone-seletor">
    <div class="icone-atual">
      <i v-if="valido" :class="['bi', modelValue]" aria-hidden="true"></i>
      <input
        type="text"
        class="form-control"
        :value="modelValue"
        :placeholder="'bi-award'"
        :aria-invalid="invalido ? 'true' : null"
        :disabled="readonly"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value.trim())"
      />
    </div>
    <input
      v-if="!readonly"
      type="search"
      class="form-control icone-busca"
      v-model="busca"
      placeholder="Buscar ícone (ex.: pessoa, casa, balança)"
      aria-label="Buscar ícone"
    />
    <div v-if="!readonly" class="icone-grade" role="listbox" aria-label="Ícones">
      <button
        v-for="nome in sugestoes"
        :key="nome"
        type="button"
        class="icone-opcao"
        :class="{ ativo: nome === modelValue }"
        :title="nome"
        role="option"
        :aria-selected="nome === modelValue"
        @click="emit('update:modelValue', nome)"
      >
        <i :class="['bi', nome]" aria-hidden="true"></i>
      </button>
      <span v-if="!sugestoes.length" class="icone-vazio">
        Nenhuma sugestão. Digite o nome de qualquer ícone do Bootstrap Icons (bi-…).
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { buscarIcones, iconeValido } from '../../utils/schemaForm'

const props = defineProps<{ modelValue: string; readonly?: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', valor: string): void }>()

const busca = ref('')
const sugestoes = computed(() => buscarIcones(busca.value))
const valido = computed(() => iconeValido(props.modelValue || ''))
const invalido = computed(() => !!props.modelValue && !valido.value)
</script>

<style scoped>
.icone-atual { display: flex; align-items: center; gap: 10px; }
.icone-atual i { font-size: 1.4rem; color: var(--primary); }
.icone-busca { margin-top: 8px; }
.icone-grade { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; max-height: 132px; overflow: auto; }
.icone-opcao { width: 36px; height: 36px; border: 1px solid var(--border); background: var(--surface); border-radius: 6px; cursor: pointer; }
.icone-opcao:hover, .icone-opcao.ativo { border-color: var(--primary); color: var(--primary); }
.icone-vazio { font-size: .8rem; color: var(--ink-dim); }
</style>
