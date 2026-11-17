<template>
  <div class="ph-picker">
    <div class="ph-picker-search">
      <i class="bi bi-search"></i>
      <input
        ref="searchInput"
        v-model="query"
        type="text"
        placeholder="Buscar campo..."
        @keydown.escape="$emit('close')"
      />
    </div>
    <div class="ph-picker-list">
      <template v-for="(campos, origem) in filteredCatalog" :key="origem">
        <div v-if="Object.keys(campos).length" class="ph-picker-group">
          <div class="ph-picker-group-title">{{ originLabel(origem) }}</div>
          <button
            v-for="(label, campo) in campos"
            :key="campo"
            type="button"
            class="ph-picker-item"
            @click="pick(origem, campo)"
          >
            {{ label }}
          </button>
        </div>
      </template>
      <div v-if="!hasResults" class="ph-picker-empty">Nenhum campo encontrado.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'

const props = defineProps({
  catalog: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['pick', 'close'])

const query = ref('')
const searchInput = ref(null)

const ORIGIN_LABELS = {
  processo: 'Processo',
  cliente: 'Cliente',
  caso: 'Caso',
  contrato: 'Contrato',
  hoje: 'Hoje',
}

function originLabel(origem) {
  return ORIGIN_LABELS[origem] || (origem.charAt(0).toUpperCase() + origem.slice(1))
}

const filteredCatalog = computed(() => {
  const term = query.value.trim().toLowerCase()
  const result = {}
  for (const [origem, campos] of Object.entries(props.catalog || {})) {
    const filteredCampos = {}
    for (const [campo, label] of Object.entries(campos || {})) {
      const matches = !term
        || String(label).toLowerCase().includes(term)
        || campo.toLowerCase().includes(term)
        || origem.toLowerCase().includes(term)
      if (matches) filteredCampos[campo] = label
    }
    result[origem] = filteredCampos
  }
  return result
})

const hasResults = computed(() => Object.values(filteredCatalog.value).some(campos => Object.keys(campos).length))

function pick(origem, campo) {
  emit('pick', { origem, campo })
}

onMounted(() => {
  nextTick(() => searchInput.value?.focus())
})
</script>

<style scoped>
.ph-picker {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 280px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: var(--shadow-lg);
  z-index: 300;
  overflow: hidden;
}

.ph-picker-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
}
.ph-picker-search i { color: var(--ink-muted); font-size: .85rem; }
.ph-picker-search input {
  flex: 1;
  border: none;
  outline: none;
  font-family: var(--font-body);
  font-size: .82rem;
  color: var(--ink);
  background: transparent;
}

.ph-picker-list {
  max-height: 260px;
  overflow-y: auto;
  padding: 4px 0;
}

.ph-picker-group-title {
  padding: 8px 12px 2px;
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink-muted);
}

.ph-picker-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 7px 16px;
  border: none;
  background: transparent;
  font-size: .82rem;
  color: var(--ink-dim);
  cursor: pointer;
  transition: background .12s;
}
.ph-picker-item:hover { background: var(--surface); color: var(--primary); }

.ph-picker-empty {
  padding: 16px;
  text-align: center;
  font-size: .8rem;
  color: var(--ink-muted);
}
</style>
