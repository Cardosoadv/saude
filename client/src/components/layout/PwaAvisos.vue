<template>
  <div class="pwa-avisos">
    <div v-if="!online" class="pwa-offline" role="status" aria-live="polite">
      <i class="bi bi-wifi-off"></i>
      Sem conexão. Os dados podem estar desatualizados e alterações não serão salvas até a conexão voltar.
    </div>

    <div v-if="atualizacaoDisponivel" class="pwa-atualizacao card" role="alert">
      <i class="bi bi-arrow-repeat"></i>
      <span>Nova versão do sistema disponível.</span>
      <button class="btn btn-primary btn-sm" type="button" @click="atualizar" :disabled="atualizando">
        {{ atualizando ? 'Atualizando…' : 'Atualizar' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePwa } from '../../composables/usePwa'

const { online, atualizacaoDisponivel, aplicarAtualizacao } = usePwa()
const atualizando = ref(false)

function atualizar() {
  atualizando.value = true
  aplicarAtualizacao()
}
</script>

<style scoped>
.pwa-offline {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 6px 16px;
  background: var(--warning);
  color: #fff;
  font-size: .82rem;
  font-weight: 500;
  text-align: center;
}

.pwa-atualizacao {
  position: fixed;
  left: 16px;
  bottom: 16px;
  z-index: 10000;
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: calc(100vw - 32px);
  padding: 12px 14px;
  box-shadow: var(--shadow-lg);
  font-size: .88rem;
}

.pwa-atualizacao > i {
  color: var(--primary);
  font-size: 1.1rem;
}
</style>
