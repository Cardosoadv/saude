<template>
  <div class="tenant-bloqueado">
    <div class="tenant-bloqueado-card card" role="alert">
      <img src="../assets/logo.png" alt="Ordo Juris" class="tenant-bloqueado-logo">
      <h1 class="tenant-bloqueado-titulo">{{ conteudo.titulo }}</h1>
      <p class="tenant-bloqueado-texto">{{ mensagem || conteudo.texto }}</p>
      <button type="button" class="btn btn-primary" @click="tentarNovamente">
        <i class="bi bi-arrow-clockwise"></i> Tentar novamente
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { MENSAGENS_TENANT, type CodigoTenantBloqueio } from '../services/tenant'

const props = defineProps<{
  codigo: CodigoTenantBloqueio
  mensagem?: string
}>()

const conteudo = computed(() => MENSAGENS_TENANT[props.codigo] ?? MENSAGENS_TENANT.tenant_indisponivel)

function tentarNovamente() {
  window.location.reload()
}
</script>

<style scoped>
.tenant-bloqueado {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: var(--surface);
  z-index: 2100;
}
.tenant-bloqueado-card {
  max-width: 28rem;
  width: 100%;
  padding: 2rem 1.5rem;
  text-align: center;
}
.tenant-bloqueado-logo {
  max-width: 10rem;
  margin-bottom: 1rem;
}
.tenant-bloqueado-titulo {
  font-size: 1.35rem;
  margin: 0 0 0.75rem;
}
.tenant-bloqueado-texto {
  margin: 0 0 1.5rem;
  color: var(--text-muted, inherit);
}
</style>
