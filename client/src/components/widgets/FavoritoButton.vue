<template>
  <button
    type="button"
    class="favorito-btn"
    :class="{ 'is-favorito': isFavorito }"
    :disabled="loading"
    :title="isFavorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos'"
    @click.stop.prevent="toggle"
  >
    <span v-if="loading" class="spinner-border spinner-border-sm"></span>
    <i v-else class="bi" :class="isFavorito ? 'bi-star-fill' : 'bi-star'"></i>
  </button>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../services/api'

const props = defineProps({
  tipo: {
    type: String,
    required: true // 'modelo'
  },
  entidadeId: {
    type: [Number, String],
    required: true
  }
})

const emit = defineEmits(['toast'])

const isFavorito = ref(false)
const favoritoId = ref(null)
const loading = ref(true)

onMounted(carregarStatus)

async function carregarStatus() {
  loading.value = true
  try {
    const res = await apiFetch(`conhecimento/favoritos/status?tipo=${props.tipo}&entidade_id=${props.entidadeId}`)
    if (res && res.success) {
      isFavorito.value = !!res.favorito
      favoritoId.value = res.id || null
    }
  } catch (err) {
    console.error('Erro ao carregar status de favorito:', err)
  } finally {
    loading.value = false
  }
}

async function toggle() {
  loading.value = true
  try {
    if (isFavorito.value && favoritoId.value) {
      const res = await apiFetch(`conhecimento/favoritos/${favoritoId.value}`, { method: 'DELETE' })
      if (res && res.success) {
        isFavorito.value = false
        favoritoId.value = null
      }
    } else {
      const res = await apiFetch('conhecimento/favoritos', {
        method: 'POST',
        body: { entidade_type: props.tipo, entidade_id: props.entidadeId }
      })
      if (res && res.success) {
        isFavorito.value = true
        favoritoId.value = res.data?.id || null
      }
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao atualizar favorito.', type: 'danger' })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.favorito-btn {
  border: none;
  background: none;
  color: var(--ink-muted);
  cursor: pointer;
  padding: 2px 6px;
  line-height: 1;
  font-size: 1rem;
  flex-shrink: 0;
}

.favorito-btn:hover {
  color: var(--warning);
}

.favorito-btn.is-favorito {
  color: var(--warning);
}

.favorito-btn:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>
