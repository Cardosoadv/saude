<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="nfse-config-titulo" style="max-width:760px;">
      <div class="modal-header">
        <h2 id="nfse-config-titulo"><i class="bi bi-gear"></i> Configurar NFS-e</h2>
        <button type="button" class="modal-close" :disabled="ocupado" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding:1.5rem;">
        <NotaFiscalConfigForm
          @toast="(t) => emit('toast', t)"
          @alterado="alterou = true"
          @ocupado="ocupado = $event"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Modal "Configurar NFS-e" (aberto na emissão da nota). O conteúdo é o `NotaFiscalConfigForm`, o mesmo da sub-guia NFS-e
 * em Configurações > Sistema; aqui ficam só a moldura do modal e o aviso de "salvo" ao fechar.
 */
import { ref } from 'vue'
import NotaFiscalConfigForm from './NotaFiscalConfigForm.vue'

const emit = defineEmits<{
  (e: 'fechar'): void
  (e: 'salvo'): void
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

const alterou = ref(false)
const ocupado = ref(false)

function fechar() {
  if (ocupado.value) return
  if (alterou.value) emit('salvo')
  emit('fechar')
}
</script>
