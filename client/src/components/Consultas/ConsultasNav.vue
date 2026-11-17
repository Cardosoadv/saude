<template>
  <nav class="consultas-nav" aria-label="Tipos de consulta">
    <a
      v-for="aba in abas"
      :key="aba.view"
      role="button"
      tabindex="0"
      :class="{ active: atual === aba.view }"
      :aria-current="atual === aba.view ? 'page' : null"
      @click="$emit('set-view', aba.view)"
      @keydown.enter="$emit('set-view', aba.view)"
      @keydown.space.prevent="$emit('set-view', aba.view)"
    >
      <i class="bi" :class="aba.icon"></i> {{ aba.label }}
    </a>
    <span v-if="homolog" class="badge badge-warning homolog-badge" title="Dados fictícios, sem tarifação">
      <i class="bi bi-flask"></i> Homologação
    </span>
  </nav>
</template>

<script setup lang="ts">
defineProps<{ atual: string; homolog?: boolean }>()
defineEmits(['set-view'])

const abas = [
  { view: 'consulta-cpf', label: 'CPF', icon: 'bi-person-vcard' },
  { view: 'consulta-processos', label: 'Processos judiciais', icon: 'bi-bank' },
  { view: 'consulta-veiculo', label: 'Veículo por placa', icon: 'bi-car-front' },
]
</script>

<style scoped>
.consultas-nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}
.consultas-nav a {
  cursor: pointer;
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid var(--border);
  color: var(--ink-dim);
  font-size: 0.88rem;
}
.consultas-nav a.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}
.homolog-badge { margin-left: auto; }
</style>
