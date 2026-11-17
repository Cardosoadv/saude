<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-people"></i> Partes do Processo</span>
      <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'parte')">
        <i class="bi bi-person-plus"></i> Adicionar Parte
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <h4 style="font-size:.78rem; text-transform:uppercase; color:var(--primary); margin: 8px 0 4px; font-weight:600;">Polo Ativo (Autor)</h4>
      <div v-if="!poloAtivo.length" style="font-size:.8rem;color:var(--ink-muted);padding:6px 0;">Nenhuma parte ativa.</div>
      <div v-for="part in poloAtivo" :key="part.id_parte || part.id" class="parte-item">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <span><i class="bi bi-person-check-fill" style="color:var(--success);margin-right:6px;"></i> {{ part.nome }}</span>
          <span 
            class="badge" 
            :style="getRepresentedStyle(part.is_represented)"
            @click="toggleRepresentada(part)"
            @keydown.enter="toggleRepresentada(part)"
            @keydown.space.prevent="toggleRepresentada(part)"
            role="button"
            tabindex="0"
            :title="getRepresentedTitle(part.is_represented)"
            :aria-pressed="Number(part.is_represented) === 1"
          >
            <i :class="getRepresentedIconClass(part.is_represented)" :style="getRepresentedIconStyle(part.is_represented)"></i>
            {{ getRepresentedLabel(part.is_represented) }}
          </span>
        </div>
        <button class="btn btn-danger btn-sm" @click="removerParte(part.id_parte || part.id)" style="padding:2px 6px; font-size:.7rem;">
          <i class="bi bi-trash"></i>
        </button>
      </div>

      <h4 style="font-size:.78rem; text-transform:uppercase; color:var(--accent); margin: 16px 0 4px; font-weight:600;">Polo Passivo (Réu)</h4>
      <div v-if="!poloPassivo.length" style="font-size:.8rem;color:var(--ink-muted);padding:6px 0;">Nenhuma parte passiva.</div>
      <div v-for="part in poloPassivo" :key="part.id_parte || part.id" class="parte-item">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <span><i class="bi bi-person-x-fill" style="color:var(--danger);margin-right:6px;"></i> {{ part.nome }}</span>
          <span 
            class="badge" 
            :style="getRepresentedStyle(part.is_represented)"
            @click="toggleRepresentada(part)"
            @keydown.enter="toggleRepresentada(part)"
            @keydown.space.prevent="toggleRepresentada(part)"
            role="button"
            tabindex="0"
            :title="getRepresentedTitle(part.is_represented)"
            :aria-pressed="Number(part.is_represented) === 1"
          >
            <i :class="getRepresentedIconClass(part.is_represented)" :style="getRepresentedIconStyle(part.is_represented)"></i>
            {{ getRepresentedLabel(part.is_represented) }}
          </span>
        </div>
        <button class="btn btn-danger btn-sm" @click="removerParte(part.id_parte || part.id)" style="padding:2px 6px; font-size:.7rem;">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { apiFetch } from '../../../services/api'
import type { Parte } from '../../../types/domain'

const props = defineProps({
  processId: { type: Number, required: true },
  partes: { type: Array as PropType<Parte[]>, default: () => [] }
})

const emit = defineEmits(['toast', 'refresh', 'open-modal'])

const poloAtivo = computed(() => {
  return props.partes.filter(p => p.polo === 'A' || p.polo === 'Ativo')
})

const poloPassivo = computed(() => {
  return props.partes.filter(p => p.polo === 'P' || p.polo === 'Passivo')
})

const getRepresentedStyle = (isRepresented) => {
  return Number(isRepresented) === 1
    ? 'background: var(--primary-lt); color: var(--primary); border: 1px solid rgba(1, 59, 81, 0.2); cursor: pointer; transition: all 0.2s;'
    : 'background: #f3f4f6; color: #6b7280; opacity: 0.5; cursor: pointer; transition: all 0.2s;'
}

const getRepresentedLabel = (isRepresented) => {
  return Number(isRepresented) === 1 ? 'Representado' : 'Marcar Representado'
}

const getRepresentedTitle = (isRepresented) => {
  return Number(isRepresented) === 1
    ? 'Representado por nós (Clique para desmarcar)'
    : 'Não representado (Clique para marcar)'
}

const getRepresentedIconClass = (isRepresented) => {
  return Number(isRepresented) === 1 ? 'bi bi-star-fill' : 'bi bi-star'
}

const getRepresentedIconStyle = (isRepresented) => {
  return Number(isRepresented) === 1 ? 'color: var(--accent);' : ''
}

async function removerParte(id) {
  try {
    const res = await apiFetch('processos/removerParte', {
      method: 'POST',
      body: {
        processo_id: props.processId,
        parte_id: id
      }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Parte vinculada excluída!', type: 'success' })
      emit('refresh')
    } else {
      throw new Error(res.message)
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao remover parte.', type: 'danger' })
  }
}

async function toggleRepresentada(part) {
  const currentStatus = Number(part.is_represented) === 1 ? 1 : 0
  const newStatus = currentStatus === 1 ? 0 : 1
  const idParte = part.id_parte || part.id

  try {
    const res = await apiFetch('processos/atualizarParteRepresentada', {
      method: 'POST',
      body: {
        id_processo: props.processId,
        id_parte: idParte,
        is_represented: newStatus
      }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Status de representado atualizado!', type: 'success' })
      emit('refresh')
    } else {
      throw new Error(res.message)
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao atualizar status de representado.', type: 'danger' })
  }
}
</script>
