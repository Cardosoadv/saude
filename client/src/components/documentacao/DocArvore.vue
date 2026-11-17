<template>
  <nav class="doc-arvore" aria-label="Índice da documentação">
    <slot name="topo"></slot>
    <a
      class="doc-arvore-inicio"
      :class="{ ativo: !slugAtual }"
      :href="hashDaDocumentacao()"
      :aria-current="!slugAtual ? 'page' : null"
      @click.prevent="emit('navegar', null)"
    >
      <i class="bi bi-house"></i> Início
    </a>
    <div v-for="secao in indice" :key="secao.slug" class="doc-arvore-secao" :data-arvore-secao="secao.slug">
      <a
        v-if="secao.capa"
        class="doc-arvore-titulo"
        :class="{ ativo: slugAtual === secao.slug }"
        :href="hashDaDocumentacao(secao.slug)"
        :aria-current="slugAtual === secao.slug ? 'page' : null"
        @click.prevent="emit('navegar', secao.slug)"
      >{{ secao.titulo }}</a>
      <span v-else class="doc-arvore-titulo">{{ secao.titulo }}</span>
      <ul>
        <li v-for="p in secao.paginas" :key="p.slug">
          <a
            :class="{ ativo: slugAtual === p.slug }"
            :href="hashDaDocumentacao(p.slug)"
            :aria-current="slugAtual === p.slug ? 'page' : null"
            :data-arvore-link="p.slug"
            @click.prevent="emit('navegar', p.slug)"
          >{{ p.titulo }}<span v-if="p.status === 'rascunho'" class="doc-arvore-rascunho" title="Rascunho"> · rascunho</span></a>
        </li>
      </ul>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { hashDaDocumentacao } from '../../utils/rotasDocumentacao'
import type { DocSecao } from '../../types/documentacao'

defineProps<{
  indice: DocSecao[]
  slugAtual: string | null
}>()

const emit = defineEmits<{ (e: 'navegar', slug: string | null): void }>()
</script>
