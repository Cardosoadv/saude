<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Contas Bancárias</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Contas Bancárias</h1>
        <p>Contas usadas nos pagamentos, transferências e na importação de extratos</p>
      </div>
      <button class="btn btn-success" @click="abrirForm(null)" aria-label="Cadastrar nova conta" title="Cadastrar nova conta">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> Nova Conta
      </button>
    </div>

    <div class="content-grid" style="margin-top:1.5rem;">
      <div>
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 3" :key="i">
            <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
            <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
          </div>
        </div>

        <div v-else-if="!contas.length" class="empty-state card">
          <i class="bi bi-bank" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhuma conta cadastrada</h4>
          <p>Cadastre ao menos uma conta para lançar pagamentos e importar extratos.</p>
        </div>

        <div v-else class="process-list">
          <div v-for="c in contas" :key="c.id_conta" class="process-card">
            <div class="process-main" @click="abrirForm(c)">
              <a href="#" class="process-cnj" @click.prevent="abrirForm(c)">{{ c.conta }}</a>
              <div class="process-meta" style="margin-top:8px;">
                <span><i class="bi bi-bank" aria-hidden="true"></i> Banco: <strong>{{ c.banco || '—' }}</strong></span>
                <span>Agência: <strong>{{ c.agencia || '—' }}</strong></span>
                <span>Conta: <strong>{{ c.numero_conta || '—' }}</strong></span>
                <span v-if="c.pix"><i class="bi bi-qr-code" aria-hidden="true"></i> PIX: <strong>{{ c.pix }}</strong></span>
                <span v-if="!dadosCompletos(c)" class="aviso-extrato" title="A importação de extratos exige banco, agência e número da conta">
                  <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Incompleta para importar extrato
                </span>
              </div>
            </div>
            <div class="process-actions" @click.stop>
              <div style="display:flex;gap:4px;margin-top:6px;">
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="abrirForm(c)" title="Editar conta" aria-label="Editar conta">
                  <i class="bi bi-pencil-square" style="color:var(--accent);" aria-hidden="true"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="excluir(c)" title="Excluir conta" aria-label="Excluir conta">
                  <i class="bi bi-trash" style="color:var(--danger);" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="formAberto" class="modal-backdrop" @click.self="fecharForm">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="titulo-conta">
        <div class="modal-header">
          <h2 id="titulo-conta"><i class="bi bi-bank"></i> {{ form.id_conta ? 'Editar conta' : 'Nova conta' }}</h2>
          <button type="button" class="modal-close" :disabled="salvando" @click="fecharForm" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
        </div>
        <form @submit.prevent="salvar">
          <div class="modal-body" style="padding: 1.5rem;">
            <div class="form-group">
              <label for="conta-nome" class="required-field">Nome da conta</label>
              <input id="conta-nome" class="form-control" v-model.trim="form.conta" maxlength="50" placeholder="Ex: Conta principal da clínica" :class="{ 'is-invalid': erros.conta }" />
              <small v-if="erros.conta" class="text-danger">{{ erros.conta }}</small>
            </div>
            <div class="form-group">
              <label for="conta-banco">Banco</label>
              <input id="conta-banco" class="form-control" v-model.trim="form.banco" maxlength="50" placeholder="Ex: Banco Inter" />
            </div>
            <div class="form-row col-2">
              <div class="form-group">
                <label for="conta-agencia">Agência</label>
                <input id="conta-agencia" class="form-control" v-model.trim="form.agencia" maxlength="50" inputmode="numeric" />
              </div>
              <div class="form-group">
                <label for="conta-numero">Número da conta</label>
                <input id="conta-numero" class="form-control" v-model.trim="form.numero_conta" maxlength="50" />
              </div>
            </div>
            <small class="dica">Banco, agência e número da conta são obrigatórios para importar extratos (OFX).</small>
            <div class="form-group" style="margin-top:1rem;">
              <label for="conta-pix">Chave PIX</label>
              <input id="conta-pix" class="form-control" v-model.trim="form.pix" maxlength="50" />
            </div>
            <div class="form-group">
              <label for="conta-obs">Observações</label>
              <textarea id="conta-obs" class="form-control" rows="2" v-model="form.comentarios"></textarea>
            </div>
          </div>
          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
            <button type="button" class="btn btn-outline-secondary" :disabled="salvando" @click="fecharForm">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="salvando">{{ salvando ? 'Salvando...' : 'Salvar' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, ApiError } from '../../services/api'
import { contaCompleta, formVazio, type ContaBancaria } from '../../utils/contasBancarias'

const emit = defineEmits<{
  (e: 'set-view', v: string): void
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

const contas = ref<ContaBancaria[]>([])
const loading = ref(true)
const formAberto = ref(false)
const salvando = ref(false)
const form = reactive<ContaBancaria>(formVazio())
const erros = reactive<{ conta?: string }>({})

const dadosCompletos = contaCompleta

async function carregar() {
  loading.value = true
  try {
    const res = await apiFetch('financeiro/contas/listar')
    contas.value = (res?.data || []) as ContaBancaria[]
  } catch (e) {
    emit('toast', { msg: 'Erro ao carregar as contas', type: 'danger' })
  } finally {
    loading.value = false
  }
}

function abrirForm(c: ContaBancaria | null) {
  Object.assign(form, formVazio(), c ?? {})
  erros.conta = undefined
  formAberto.value = true
}

function fecharForm() {
  if (!salvando.value) formAberto.value = false
}

async function salvar() {
  erros.conta = form.conta?.trim() ? undefined : 'Informe o nome da conta.'
  if (erros.conta) return
  salvando.value = true
  try {
    const res = await apiFetch('financeiro/contas/salvar', { method: 'POST', body: { ...form } })
    if (res?.success) {
      emit('toast', { msg: res.message || 'Conta salva com sucesso!', type: 'success' })
      formAberto.value = false
      await carregar()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar a conta', type: 'danger' })
    }
  } catch (e) {
    emit('toast', { msg: e instanceof ApiError ? e.message : 'Erro de comunicação ao salvar', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

async function excluir(c: ContaBancaria) {
  if (!confirm(`Deseja realmente excluir a conta "${c.conta}"?`)) return
  try {
    const res = await apiFetch(`financeiro/contas/excluir/${c.id_conta}`, { method: 'DELETE' })
    if (res?.success) {
      emit('toast', { msg: res.message || 'Conta excluída', type: 'success' })
      await carregar()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao excluir a conta', type: 'danger' })
    }
  } catch (e) {
    // 409: conta com vínculos; a API explica o motivo na mensagem.
    emit('toast', { msg: e instanceof ApiError ? e.message : 'Erro de comunicação ao excluir', type: 'danger' })
  }
}

onMounted(carregar)
</script>

<style scoped>
.empty-state { text-align: center; padding: 3rem 1rem; color: var(--ink-muted); }
.empty-state h4 { margin: 1rem 0 0.5rem; color: var(--text-main); }
.aviso-extrato { color: var(--warning, #b45309); }
.dica { color: var(--ink-muted); }
</style>
