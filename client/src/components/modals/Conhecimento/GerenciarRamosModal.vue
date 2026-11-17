<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 820px;">
      <div class="modal-header">
        <h2><i class="bi bi-diagram-3"></i> Gerenciar Ramos do Direito</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body">
        <div class="ramos-manager-grid">
          <!-- Formulário -->
          <div class="ramos-form-col">
            <h5 class="ramos-col-title">
              <i class="bi" :class="editingId ? 'bi-pencil-square' : 'bi-plus-circle'"></i>
              {{ editingId ? 'Editar Ramo' : 'Novo Ramo' }}
            </h5>
            <form @submit.prevent="salvar" class="d-flex flex-column">
              <div class="form-group">
                <label class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.nome" required placeholder="Ex: Direito Tributário">
              </div>
              <div class="form-group">
                <label class="form-label">Ramo Pai <span class="text-muted">(Opcional)</span></label>
                <select class="form-control form-select" v-model="form.pai_id">
                  <option :value="null">Nenhum (Ramo Principal)</option>
                  <option v-for="opt in paiOptions" :key="opt.id" :value="opt.id">
                    {{ '  '.repeat(opt.nivel) }}{{ opt.nivel > 0 ? '↳ ' : '' }}{{ opt.nome }}
                  </option>
                </select>
              </div>
              <div class="form-actions">
                <button type="button" class="btn btn-ghost" v-if="editingId" @click="cancelarEdicao">Cancelar</button>
                <button type="submit" class="btn btn-primary" :disabled="saving || !form.nome.trim()">
                  <span v-if="saving" class="btn-spinner"></span>
                  {{ editingId ? 'Atualizar' : 'Adicionar' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Lista -->
          <div class="ramos-list-col">
            <h5 class="ramos-col-title">Ramos Cadastrados</h5>

            <div v-if="loading" class="empty-state" style="padding: 30px 0;">
              <div class="spinner"></div>
            </div>

            <div v-else-if="!ramosFlat.length" class="empty-state" style="padding: 30px 0;">
              <i class="bi bi-diagram-3"></i>
              <p class="m-0">Nenhum ramo cadastrado ainda.</p>
            </div>

            <ul v-else class="ramos-tree-list">
              <li v-for="ramo in ramosFlat" :key="ramo.id" class="ramo-item" :style="{ paddingLeft: (ramo.nivel * 20) + 'px' }">
                <span class="ramo-item-nome">
                  <i v-if="ramo.nivel > 0" class="bi bi-arrow-return-right ramo-item-icon"></i>
                  {{ ramo.nome }}
                </span>
                <span class="ramo-item-actions">
                  <button class="btn-icon" title="Editar" @click="editar(ramo)">
                    <i class="bi bi-pencil-fill"></i>
                  </button>
                  <button class="btn-icon btn-icon-danger" title="Excluir" @click="excluir(ramo)">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const emit = defineEmits(['close', 'toast', 'updated'])

const ramosArvore = ref([])
const loading = ref(true)
const saving = ref(false)
const editingId = ref(null)

const form = ref({ nome: '', pai_id: null })

function flatten(nodes, nivel = 0) {
  let out = []
  for (const nodo of nodes || []) {
    out.push({ ...nodo, nivel })
    if (nodo.filhos?.length) {
      out = out.concat(flatten(nodo.filhos, nivel + 1))
    }
  }
  return out
}

const ramosFlat = computed(() => flatten(ramosArvore.value))

function collectDescendantIds(nodo) {
  let ids = [nodo.id]
  for (const filho of nodo.filhos || []) {
    ids = ids.concat(collectDescendantIds(filho))
  }
  return ids
}

function findNode(nodes, id) {
  for (const nodo of nodes || []) {
    if (nodo.id === id) return nodo
    const encontrado = findNode(nodo.filhos, id)
    if (encontrado) return encontrado
  }
  return null
}

const paiOptions = computed(() => {
  if (!editingId.value) return ramosFlat.value
  const nodo = findNode(ramosArvore.value, editingId.value)
  const excluidos = new Set(nodo ? collectDescendantIds(nodo) : [editingId.value])
  return ramosFlat.value.filter(r => !excluidos.has(r.id))
})

async function carregarRamos() {
  loading.value = true
  try {
    const res = await apiFetch('conhecimento/ramos')
    ramosArvore.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (error) {
    emit('toast', { msg: 'Erro ao carregar ramos do direito', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function salvar() {
  if (!form.value.nome.trim()) return
  saving.value = true
  try {
    const payload = { nome: form.value.nome.trim(), pai_id: form.value.pai_id || null }
    const res = editingId.value
      ? await apiFetch(`conhecimento/ramos/atualizar/${editingId.value}`, { method: 'PUT', body: payload })
      : await apiFetch('conhecimento/ramos/salvar', { method: 'POST', body: payload })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Ramo salvo com sucesso', type: 'success' })
      cancelarEdicao()
      await carregarRamos()
      emit('updated')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar ramo', type: 'danger' })
    }
  } catch (error) {
    emit('toast', { msg: error.message || 'Erro ao salvar ramo do direito', type: 'danger' })
  } finally {
    saving.value = false
  }
}

function editar(ramo) {
  editingId.value = ramo.id
  form.value = { nome: ramo.nome, pai_id: ramo.pai_id || null }
}

function cancelarEdicao() {
  editingId.value = null
  form.value = { nome: '', pai_id: null }
}

async function excluir(ramo) {
  if (!confirm(`Deseja realmente excluir o ramo "${ramo.nome}"?`)) return
  try {
    const res = await apiFetch(`conhecimento/ramos/deletar/${ramo.id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Ramo excluído com sucesso', type: 'success' })
      if (editingId.value === ramo.id) cancelarEdicao()
      await carregarRamos()
      emit('updated')
    } else {
      emit('toast', { msg: res?.message || 'Erro ao excluir ramo', type: 'danger' })
    }
  } catch (error) {
    emit('toast', { msg: error.message || 'Erro ao excluir ramo. Verifique se não há sub-ramos ou vínculos.', type: 'danger' })
  }
}

onMounted(() => {
  carregarRamos()
})
</script>

<style scoped>
.ramos-manager-grid {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
}

.ramos-form-col {
  flex: 1 1 260px;
  min-width: 240px;
}

.ramos-list-col {
  flex: 1 1 340px;
  min-width: 280px;
  border-left: 1px solid var(--border);
  padding-left: 24px;
}

@media (max-width: 640px) {
  .ramos-list-col {
    border-left: none;
    padding-left: 0;
    border-top: 1px solid var(--border);
    padding-top: 20px;
  }
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 4px;
}

.btn-spinner {
  display: inline-block;
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, .5);
  border-top-color: #fff;
  border-radius: 50%;
  animation: btn-spin .7s linear infinite;
}

@keyframes btn-spin {
  to { transform: rotate(360deg); }
}

.ramos-col-title {
  font-family: var(--font-head);
  font-size: 1rem;
  color: var(--ink);
  margin-bottom: 16px;
}

.ramos-tree-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 400px;
  overflow-y: auto;
}

.ramo-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 6px;
  border-bottom: 1px solid var(--border);
}

.ramo-item-nome {
  flex: 1 1 auto;
  min-width: 0;
  font-size: .9rem;
  color: var(--ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ramo-item-icon {
  color: var(--ink-muted);
  margin-right: 4px;
}

.ramo-item-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.btn-icon {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--ink-muted);
  border-radius: 6px;
  cursor: pointer;
  font-size: .8rem;
  transition: all .12s;
}

.btn-icon:hover {
  background: var(--surface);
  color: var(--primary);
}

.btn-icon-danger:hover {
  color: var(--danger);
}
</style>
