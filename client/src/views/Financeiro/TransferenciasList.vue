<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Transferências</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Transferências entre Contas</h1>
        <p>Gerenciamento de movimentações financeiras e rateios</p>
      </div>
      <button class="btn btn-success" @click="$emit('open-detail', null)" aria-label="Cadastrar uma nova transferência entre contas" title="Cadastrar nova transferência">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Nova Transferência
      </button>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <div>
        <Transition name="fade" mode="out-in">
          <!-- Skeletons Loading -->
          <div v-if="loading" class="process-list">
            <div class="skeleton-card" v-for="i in 4" :key="i">
              <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
              <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
              <div class="skeleton-line" style="width: 30%; height: 10px;"></div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else-if="!listTransferencias.length" class="empty-state card">
            <i class="bi bi-arrow-left-right" style="font-size:3rem;color:var(--accent);"></i>
            <h4>Nenhuma transferência encontrada</h4>
            <p>Não há registros de transferências ou não há dados para exibir.</p>
          </div>

          <!-- Transfer Card List -->
          <div v-else class="process-list">
            <TransitionGroup name="fade" tag="div" class="process-list">
              <div
                v-for="transfer in listTransferencias"
                :key="transfer.id_transferencia"
                class="process-card"
                @click.self="$emit('open-detail', transfer.id_transferencia)"
                role="button"
                tabindex="0"
                @keydown.enter="$emit('open-detail', transfer.id_transferencia)"
                @keydown.space.prevent="$emit('open-detail', transfer.id_transferencia)"
                :aria-label="'Ver detalhes da transferência: ' + transfer.transferencia"
              >
                <div class="process-main" @click="$emit('open-detail', transfer.id_transferencia)">
                  <a href="#" class="process-cnj" @click.prevent="$emit('open-detail', transfer.id_transferencia)" @keydown.space.prevent="$emit('open-detail', transfer.id_transferencia)">
                    {{ transfer.transferencia }}
                  </a>
                  <div class="process-meta" style="margin-top:8px;">
                    <span><i class="bi bi-calendar" aria-hidden="true"></i> Data: <strong>{{ formatDate(transfer.data_transferencia) }}</strong></span>
                    <span style="color:var(--danger)">
                      <i class="bi bi-dash-circle" aria-hidden="true"></i> Origem: <strong>{{ transfer.conta_origem_nome || 'Conta ID ' + transfer.id_conta_origem }}</strong>
                    </span>
                    <span style="color:var(--success)">
                      <i class="bi bi-plus-circle" aria-hidden="true"></i> Destino: <strong>{{ transfer.conta_destino_nome || 'Conta ID ' + transfer.id_conta_destino }}</strong>
                    </span>
                    <span style="color:var(--text-main)">
                      <i class="bi bi-cash-stack" aria-hidden="true"></i> Valor: <strong>{{ formatCurrency(transfer.valor) }}</strong>
                    </span>
                  </div>
                </div>

                <!-- Card Right actions -->
                <div class="process-actions" @click.stop>
                  <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                    <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="$emit('open-detail', transfer.id_transferencia)" title="Editar transferência" aria-label="Editar transferência">
                      <i class="bi bi-pencil-square" style="color:var(--accent);" aria-hidden="true"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmDelete(transfer)" title="Excluir transferência" aria-label="Excluir transferência">
                      <i class="bi bi-trash" style="color:var(--danger);" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
              </div>
            </TransitionGroup>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch, formatDate, formatCurrency } from '../../services/api'

const emit = defineEmits(['set-view', 'open-detail', 'toast'])

const listTransferencias = ref([])
const loading = ref(true)

async function fetchTransferencias() {
  loading.value = true
  try {
    const res = await apiFetch('financeiro/transferencias/listar')
    if (res && res.success) {
      listTransferencias.value = res.data || []
    } else {
      listTransferencias.value = res.data || []
    }
  } catch (error) {
    console.error('Erro ao buscar transferências:', error)
    emit('toast', { msg: 'Erro ao carregar lista de transferências', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function confirmDelete(transfer) {
  if (!confirm(`Deseja realmente excluir a transferência "${transfer.transferencia}"?`)) return
  
  try {
    const res = await apiFetch(`financeiro/transferencias/excluir/${transfer.id_transferencia}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: 'Transferência excluída com sucesso', type: 'success' })
      fetchTransferencias()
    } else {
      emit('toast', { msg: res.message || 'Erro ao excluir transferência', type: 'danger' })
    }
  } catch (error) {
    console.error('Erro ao excluir:', error)
    emit('toast', { msg: 'Erro de comunicação ao excluir', type: 'danger' })
  }
}

onMounted(() => {
  fetchTransferencias()
})
</script>

<style scoped>
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--ink-muted);
}
.empty-state h4 {
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: var(--text-main);
}
</style>
