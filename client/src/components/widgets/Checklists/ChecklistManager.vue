<template>
  <div>
    <!-- Checklists Header -->
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:1rem;">
      <button v-if="checklists && checklists.length" class="btn btn-sm btn-ghost" @click="printAllChecklists" title="Imprimir Todos">
        <i class="bi bi-printer"></i> Imprimir Todos
      </button>
      <button class="btn btn-sm btn-info text-white" @click="$emit('open-modal', 'ia')">
        <i class="bi bi-robot"></i> Sugerir com IA
      </button>
      <button class="btn btn-sm btn-primary" @click="$emit('open-modal', 'apply')">
        <i class="bi bi-plus-circle"></i> Novo Checklist
      </button>
    </div>

    <div v-if="!checklists || !checklists.length" style="font-size:.82rem;color:var(--ink-muted);text-align:center;padding:20px 0;">
      Nenhum checklist aplicado.
    </div>
    <div v-else class="checklists-wrapper">
      <div v-for="chk in checklists" :key="chk.id" class="checklist-group-card">
        <div class="checklist-group-header">
          <div class="chk-header-title">
            <div v-if="editingChecklistId === chk.id" style="display:flex; gap:8px; align-items:center; width:100%;">
              <input type="text" class="form-control form-control-sm" v-model="editingChecklistData.titulo" />
              <button class="btn btn-sm btn-success" @click="salvarChecklistInline(chk.id)"><i class="bi bi-check"></i></button>
              <button class="btn btn-sm btn-secondary" @click="editingChecklistId = null"><i class="bi bi-x"></i></button>
            </div>
            <div v-else style="display:flex; align-items:center; gap:8px;">
              <strong>{{ chk.titulo }}</strong>
              <button class="btn btn-ghost btn-sm" @click="editChecklist(chk)" title="Editar Seção" style="padding:0; color:var(--ink-muted);"><i class="bi bi-pencil"></i></button>
            </div>
            <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
              <span class="badge badge-accent small">
                {{ chk.itens_cumpridos || 0 }}/{{ chk.total_itens || 0 }} concluídos
              </span>
              <span class="badge small" :style="getChecklistStatusStyle(chk.status)">
                {{ getChecklistStatusLabel(chk.status) }}
              </span>
            </div>
          </div>
          <div style="display:flex; align-items:center; gap:4px;">
            <button class="btn btn-ghost btn-sm" @click="printChecklist(chk)" title="Imprimir Checklist" style="color:var(--ink-muted);">
              <i class="bi bi-printer"></i>
            </button>
            <button class="btn btn-ghost btn-sm btn-delete-chk" @click="removerChecklist(chk.id)" title="Remover Checklist">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>

        <!-- Items List -->
        <div class="checklist-items-list" v-if="chk.itens && chk.itens.length">
          <div v-for="item in chk.itens" :key="item.id" class="checklist-item-row" style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div v-if="editingItemId === (item.id || item.id_item)" style="display:flex; flex-direction:column; gap:8px; width:100%; padding:8px 0;">
              <input type="text" class="form-control form-control-sm" v-model="editingItemData.item_titulo" placeholder="Título do Item" />
              <input type="text" class="form-control form-control-sm" v-model="editingItemData.observacao" placeholder="Observação (opcional)" />
              <div style="display:flex; gap:8px;">
                <button class="btn btn-sm btn-success" @click="salvarItemInline(item.id || item.id_item)">Salvar</button>
                <button class="btn btn-sm btn-secondary" @click="editingItemId = null">Cancelar</button>
              </div>
            </div>
            <label v-else class="chk-item-label" :class="{ completed: Number(item.cumprido || item.concluido) === 1 }" style="flex:1;">
              <input 
                type="checkbox" 
                :checked="Number(item.cumprido || item.concluido) === 1"
                :disabled="chk.status === 'concluido'"
                @change="toggleChecklistItem(item)"
                style="accent-color:var(--accent);"
              />
              <div class="chk-item-text-wrapper">
                <span class="item-title">{{ item.titulo }}</span>
                <span class="item-obs" v-if="item.descricao">{{ item.descricao }}</span>
              </div>
            </label>
            <div v-if="editingItemId !== (item.id || item.id_item)" style="display:flex; gap:4px; margin-top:2px;">
              <button class="btn btn-ghost btn-sm" @click="editItem(item)" title="Editar Item" style="padding:2px 6px; color:var(--primary);"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-ghost btn-sm" @click="excluirItemInline(item.id || item.id_item)" title="Excluir Item" style="padding:2px 6px; color:var(--danger);"><i class="bi bi-trash"></i></button>
            </div>
          </div>
        </div>
        <div v-else style="font-size:.78rem;color:var(--ink-muted);padding:8px 0;text-align:center;">
          Nenhum item cadastrado neste checklist.
        </div>
        
        <!-- Add Item Inline -->
        <div style="margin-top:12px; padding:0 12px 12px;">
          <div v-if="isAddingItemTo === chk.id" style="display:flex; flex-direction:column; gap:8px; background:var(--bg-card); padding:10px; border-radius:6px; border:1px solid var(--border-color);">
            <input type="text" class="form-control form-control-sm" v-model="newItemData.item_titulo" placeholder="Novo item..." />
            <input type="text" class="form-control form-control-sm" v-model="newItemData.observacao" placeholder="Observação (opcional)" />
            <div style="display:flex; gap:8px;">
              <button class="btn btn-sm btn-primary" @click="criarItemAvulso(chk.id)">Adicionar</button>
              <button class="btn btn-sm btn-secondary" @click="isAddingItemTo = null">Cancelar</button>
            </div>
          </div>
          <button v-else class="btn btn-sm btn-ghost" style="color:var(--primary);" @click="isAddingItemTo = chk.id; newItemData.item_titulo=''; newItemData.observacao='';"><i class="bi bi-plus"></i> Novo Item</button>
        </div>
      </div>

      <!-- Add Section Inline -->
      <div style="margin-top:20px;">
        <div v-if="isAddingSection" style="display:flex; flex-direction:column; gap:8px; background:var(--bg-card); padding:16px; border-radius:8px; border:1px solid var(--border-color);">
          <label>Título da Nova Seção:</label>
          <input type="text" class="form-control form-control-sm" v-model="newSectionTitulo" placeholder="Ex: Documentos Iniciais" @keyup.enter="criarSecaoAvulsa" />
          <div style="display:flex; gap:8px;">
            <button class="btn btn-sm btn-primary" @click="criarSecaoAvulsa">Criar Seção</button>
            <button class="btn btn-sm btn-secondary" @click="isAddingSection = false">Cancelar</button>
          </div>
        </div>
        <button v-else class="btn btn-sm btn-outline-primary" style="width:100%; border-style:dashed;" @click="isAddingSection = true"><i class="bi bi-plus-lg"></i> Adicionar Nova Seção</button>
      </div>
    </div>

    <!-- Print View -->
    <Teleport to="body">
      <div v-if="isPrintingAllChecklists || printingChecklistId" class="print-area-global">
        <ReportHeader title="RELATÓRIO DE CHECKLISTS" :subtitle="printSubtitle" />
        
        <div style="margin-top: 20px;">
          <div v-for="chk in checklistsToPrint" :key="chk.id" style="margin-bottom: 30px; page-break-inside: avoid;">
            <h3 style="border-bottom: 2px solid var(--border); padding-bottom: 5px; margin-bottom: 15px; font-size: 1.3rem;">
              {{ chk.titulo }} 
              <span style="font-size: 0.9rem; font-weight: normal; color: var(--ink-muted);">
                ({{ chk.itens_cumpridos || 0 }}/{{ chk.total_itens || 0 }} concluídos)
              </span>
            </h3>
            
            <div v-if="chk.itens && chk.itens.length > 0">
              <div v-for="item in chk.itens" :key="item.id" style="display: flex; gap: 10px; margin-bottom: 12px;">
                <div style="width: 16px; height: 16px; border: 1px solid #000; flex-shrink: 0; margin-top: 4px;" :style="Number(item.cumprido || item.concluido) === 1 ? 'background-color: #000; -webkit-print-color-adjust: exact; color-adjust: exact; print-color-adjust: exact;' : ''"></div>
                <div>
                  <strong style="font-size: 1rem; display: block; margin-bottom: 2px;">{{ item.titulo }}</strong>
                  <div v-if="item.descricao" style="font-size: 0.85rem; color: #555;">{{ item.descricao }}</div>
                </div>
              </div>
            </div>
            <div v-else>
              <p style="color: var(--ink-muted); font-size: 0.9rem;">Nenhum item neste checklist.</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, nextTick, type PropType } from 'vue'
import { apiFetch } from '../../../services/api'
import ReportHeader from '../../reports/ReportHeader.vue'
import type { Checklist } from '../../../types/domain'

const props = defineProps({
  checklists: {
    type: Array as PropType<Checklist[]>,
    default: () => []
  },
  targetId: {
    type: [Number, String],
    required: true
  },
  targetType: {
    type: String,
    required: true, // 'processo' or 'caso'
  },
  printSubtitle: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['refresh', 'toast', 'open-modal'])

// Checklists Inline Editing
const editingChecklistId = ref(null)
const editingChecklistData = reactive({ titulo: '' })

const editingItemId = ref(null)
const editingItemData = reactive({ item_titulo: '', observacao: '' })

const isAddingSection = ref(false)
const newSectionTitulo = ref('')

const isAddingItemTo = ref(null)
const newItemData = reactive({ item_titulo: '', observacao: '' })

// Printing
const isPrintingAllChecklists = ref(false)
const printingChecklistId = ref(null)

const checklistsToPrint = computed(() => {
  if (!props.checklists) return []
  if (isPrintingAllChecklists.value) return props.checklists
  if (printingChecklistId.value) return props.checklists.filter(c => c.id === printingChecklistId.value)
  return []
})

function printAllChecklists() {
  isPrintingAllChecklists.value = true
  nextTick(() => {
    document.body.classList.add('is-printing-all')
    setTimeout(() => {
      window.print()
      document.body.classList.remove('is-printing-all')
      isPrintingAllChecklists.value = false
    }, 500)
  })
}

function printChecklist(chk) {
  printingChecklistId.value = chk.id
  nextTick(() => {
    document.body.classList.add('is-printing-all')
    setTimeout(() => {
      window.print()
      document.body.classList.remove('is-printing-all')
      printingChecklistId.value = null
    }, 500)
  })
}

function getChecklistStatusStyle(status) {
  return status === 'concluido'
    ? { background: 'var(--success-lt)', color: 'var(--success)' }
    : { background: 'var(--warning-lt)', color: 'var(--warning)' }
}

function getChecklistStatusLabel(status) {
  return status === 'concluido' ? 'Concluído' : 'Em Andamento'
}

async function toggleChecklistItem(item) {
  const itemId = item.id || item.id_item
  try {
    const res = await apiFetch('checklist/toggleItem/' + itemId, {
      method: 'POST'
    })
    if (res && res.success) {
      emit('refresh')
    } else {
      emit('toast', { msg: 'Não foi possível atualizar o item.', type: 'danger' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao atualizar o item do checklist.', type: 'danger' })
  }
}

async function removerChecklist(id) {
  if (confirm('Confirma a remoção deste checklist? Todos os itens e o histórico serão perdidos.')) {
    try {
      const res = await apiFetch(`checklist/excluir/${id}`, {
        method: 'DELETE'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Checklist excluído com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message || 'Erro ao excluir checklist.')
      }
    } catch (err) {
      emit('toast', { msg: err.message || 'Erro ao remover checklist.', type: 'danger' })
    }
  }
}

function editChecklist(chk) {
  editingChecklistId.value = chk.id
  editingChecklistData.titulo = chk.titulo
}

async function salvarChecklistInline(id) {
  try {
    const res = await apiFetch(`checklist/atualizar/${id}`, {
      method: 'POST',
      body: { titulo: editingChecklistData.titulo }
    })
    if (res && res.success) {
      editingChecklistId.value = null
      emit('refresh')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar seção.', type: 'danger' })
    }
  } catch (e) {
    emit('toast', { msg: 'Erro ao salvar seção.', type: 'danger' })
  }
}

async function criarSecaoAvulsa() {
  if (!newSectionTitulo.value) return
  try {
    const res = await apiFetch('checklist/criarAvulso', {
      method: 'POST',
      body: { type: props.targetType, target_id: props.targetId, titulo: newSectionTitulo.value }
    })
    if (res && res.success) {
      isAddingSection.value = false
      newSectionTitulo.value = ''
      emit('refresh')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao criar seção.', type: 'danger' })
    }
  } catch (e) {
    emit('toast', { msg: 'Erro ao criar seção.', type: 'danger' })
  }
}

function editItem(item) {
  editingItemId.value = item.id || item.id_item
  editingItemData.item_titulo = item.titulo
  editingItemData.observacao = item.descricao || item.observacao || ''
}

async function salvarItemInline(id) {
  try {
    const res = await apiFetch(`checklist/atualizarItem/${id}`, {
      method: 'POST',
      body: editingItemData
    })
    if (res && res.success) {
      editingItemId.value = null
      emit('refresh')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar item.', type: 'danger' })
    }
  } catch (e) {
    emit('toast', { msg: 'Erro ao salvar item.', type: 'danger' })
  }
}

async function criarItemAvulso(checklistId) {
  if (!newItemData.item_titulo) return
  try {
    const res = await apiFetch('checklist/adicionarItemAvulso', {
      method: 'POST',
      body: {
        checklist_id: checklistId,
        dados: {
           secao_titulo: '',
           item_titulo: newItemData.item_titulo,
           observacao: newItemData.observacao
        }
      }
    })
    if (res && res.success) {
      isAddingItemTo.value = null
      newItemData.item_titulo = ''
      newItemData.observacao = ''
      emit('refresh')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao adicionar item.', type: 'danger' })
    }
  } catch (e) {
    emit('toast', { msg: 'Erro ao adicionar item.', type: 'danger' })
  }
}

async function excluirItemInline(id) {
  if (confirm('Deseja realmente excluir este item?')) {
    try {
      const res = await apiFetch(`checklist/excluirItem/${id}`, { method: 'DELETE' })
      if (res && res.success) {
        emit('refresh')
      } else {
        emit('toast', { msg: res?.message || 'Erro ao excluir item.', type: 'danger' })
      }
    } catch (e) {
      emit('toast', { msg: 'Erro ao excluir item.', type: 'danger' })
    }
  }
}
</script>

<style scoped>
/* Optional: any specific styles for checklists */
</style>
