<template>
  <nav v-if="itens.length" class="doc-sumario" aria-label="Nesta página">
    <p class="doc-sumario-rotulo">Nesta página</p>
    <ul>
      <li v-for="item in itens" :key="item.ancora" :class="`nivel-${item.nivel}`">
        <a
          :href="hashDaDocumentacao(slug, item.ancora)"
          :class="{ ativo: item.ancora === ativa }"
          :aria-current="item.ancora === ativa ? 'location' : null"
          :data-sumario="item.ancora"
          @click.prevent="emit('ir', item.ancora)"
        >{{ item.texto }}</a>
      </li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
import { hashDaDocumentacao } from '../../utils/rotasDocumentacao'
import type { DocSumarioItem } from '../../types/documentacao'

defineProps<{
  itens: DocSumarioItem[]
  slug: string
  ativa: string | null
}>()

const emit = defineEmits<{ (e: 'ir', ancora: string): void }>()
</script>
