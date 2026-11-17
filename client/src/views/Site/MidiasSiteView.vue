<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Mídias do Site</h1>
        <p>Biblioteca de imagens usadas no site público. Descreva cada imagem no texto alternativo.</p>
      </div>
    </div>
    <div class="glass-panel" style="padding: 1.25rem;">
      <MidiaBiblioteca @toast="emit('toast', $event)" />
    </div>
  </div>
</template>

<script setup lang="ts">
import MidiaBiblioteca from '../../components/site/MidiaBiblioteca.vue'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void }>()
</script>
