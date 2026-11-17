<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h2>Gerenciar Procedimentos</h2>
        <button class="close-btn" @click="$emit('close')"><i class="bi bi-x"></i></button>
      </div>

      <div class="modal-body bg-light">
        <div class="row">
          <div class="col-md-5">
            <div class="card border-0 shadow-sm mb-3 p-3 rounded-4">
              <h5 class="mb-4 fw-bold text-primary">
                <i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-plus-circle'"></i> 
                {{ editando ? 'Editar Procedimento' : 'Novo Procedimento' }}
              </h5>
              <form @submit.prevent="salvarProcedimento">
                <div class="mb-3">
                  <label class="form-label">Nome <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.nome" required placeholder="Ex: Auditoria Interna" />
                </div>
                <div class="mb-3">
                  <label class="form-label">Descrição</label>
                  <textarea class="form-control" v-model="form.descricao" rows="3" placeholder="Opcional"></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                  <button type="button" class="btn btn-light" v-if="editando" @click="cancelarEdicao">Cancelar</button>
                  <button type="submit" class="btn btn-primary" :disabled="salvando">
                    <span v-if="salvando" class="spinner-border spinner-border-sm me-1"></span>
                    {{ editando ? 'Atualizar' : 'Adicionar' }}
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="col-md-7">
            <div class="card border-0 shadow-sm p-3 rounded-4" style="min-height: 350px;">
              <h5 class="mb-4 fw-bold text-secondary">Procedimentos Cadastrados</h5>
              
              <div v-if="loading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
              </div>

              <div v-else-if="!procedimentos.length" class="text-center py-5 text-muted d-flex flex-column align-items-center">
                <i class="bi bi-inbox fs-1 mb-2 text-light-muted"></i>
                <p class="mb-0">Nenhum procedimento cadastrado ainda.</p>
              </div>

              <div v-else class="list-group list-group-flush px-1" style="max-height: 400px; overflow-y: auto;">
                <div 
                  class="list-group-item d-flex justify-content-between align-items-center mb-2 rounded-3 border-0 shadow-sm proc-item" 
                  v-for="proc in procedimentos" :key="proc.id"
                >
                  <div class="pe-3">
                    <h6 class="mb-1 fw-bold text-dark">{{ proc.nome }}</h6>
                    <small class="text-muted" v-if="proc.descricao">{{ proc.descricao }}</small>
                  </div>
                  <div class="d-flex gap-2 opacity-75 proc-actions">
                    <button class="btn btn-sm btn-light text-primary border-0 rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;" @click="editar(proc)" title="Editar">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-light text-danger border-0 rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;" @click="excluir(proc.id)" title="Excluir">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const emit = defineEmits(['close', 'toast', 'updated'])

const procedimentos = ref([])
const loading = ref(true)
const salvando = ref(false)
const editando = ref(false)

const form = ref({
  id: null,
  nome: '',
  descricao: ''
})

async function carregarProcedimentos() {
  loading.value = true
  try {
    const res = await apiFetch('procedimentos?limit=100')
    if (res && res.success) {
      procedimentos.value = res.data.data || []
    } else {
      procedimentos.value = []
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar procedimentos', type: 'error' })
  } finally {
    loading.value = false
  }
}

async function salvarProcedimento() {
  salvando.value = true
  try {
    const res = await apiFetch('procedimentos/salvar', {
      method: 'POST',
      body: form.value
    })
    
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Procedimento salvo com sucesso', type: 'success' })
      emit('updated')
      cancelarEdicao()
      await carregarProcedimentos()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar procedimento', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao salvar', type: 'error' })
  } finally {
    salvando.value = false
  }
}

function editar(proc) {
  editando.value = true
  form.value = {
    id: proc.id,
    nome: proc.nome,
    descricao: proc.descricao || ''
  }
}

function cancelarEdicao() {
  editando.value = false
  form.value = {
    id: null,
    nome: '',
    descricao: ''
  }
}

async function excluir(id) {
  if (!confirm('Deseja realmente excluir este procedimento?')) return
  
  try {
    const res = await apiFetch(`procedimentos/excluir/${id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Procedimento excluído', type: 'success' })
      emit('updated')
      if (form.value.id === id) cancelarEdicao()
      await carregarProcedimentos()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao excluir. O procedimento pode estar em uso.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao excluir', type: 'error' })
  }
}

onMounted(() => {
  carregarProcedimentos()
})
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  backdrop-filter: blur(4px);
}
.modal-content {
  background: var(--surface);
  border-radius: 12px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  animation: slideDown 0.3s ease-out;
}
.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}
.close-btn {
  background: transparent;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
}
.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}
.proc-item {
  transition: all 0.2s ease-in-out;
  background-color: var(--surface);
}
.proc-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
  background-color: var(--surface-hover, #f8f9fa);
}
.proc-actions {
  transition: opacity 0.2s;
}
.proc-item:hover .proc-actions {
  opacity: 1 !important;
}
.text-light-muted {
  color: #adb5bd;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
