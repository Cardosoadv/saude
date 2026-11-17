<template>
  <div class="modal-site-fundo" role="dialog" aria-modal="true" :aria-label="titulo" @click.self="emit('fechar')" @keydown.esc="emit('fechar')">
    <div class="modal-site" :style="{ width: `min(${largura}px, 100%)` }">
      <div class="modal-site-cabecalho">
        <h3>{{ titulo }}</h3>
        <button type="button" class="btn btn-ghost btn-sm" aria-label="Fechar" data-fechar-modal @click="emit('fechar')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-site-corpo"><slot /></div>
      <div v-if="$slots.rodape" class="modal-site-rodape"><slot name="rodape" /></div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ titulo: string; largura?: number }>(), { largura: 720 })
const emit = defineEmits<{ (e: 'fechar'): void }>()
</script>

<style scoped>
.modal-site-fundo { position: fixed; inset: 0; z-index: 2000; background: rgba(0, 0, 0, .45); display: flex; align-items: center; justify-content: center; padding: 16px; }
.modal-site { background: var(--surface, #fff); border-radius: 12px; max-height: 92vh; display: flex; flex-direction: column; }
.modal-site-cabecalho { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.modal-site-cabecalho h3 { margin: 0; font-size: 1.05rem; }
.modal-site-corpo { padding: 16px 18px; overflow: auto; }
.modal-site-rodape { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--border); }
</style>
