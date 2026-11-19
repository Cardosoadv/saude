<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'fornecedores')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#" @click.prevent="$emit('set-view', 'fornecedores')">Fornecedores</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isNew ? 'Novo Fornecedor' : form.nome }}</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>{{ isNew ? 'Novo Fornecedor' : 'Editar Fornecedor' }}</h1>
        <p>{{ isNew ? 'Cadastre um novo fornecedor ou parceiro' : 'Gerencie os dados e informações bancárias do fornecedor' }}</p>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;">
        <button
          v-if="!isNew && ehCliente"
          class="btn btn-outline-primary"
          @click="$emit('open-client-detail', Number(props.supplierId))"
          title="Este cadastro também é cliente: abrir a ficha de cliente"
        >
          <i class="bi bi-person-check"></i> Ver ficha de cliente
        </button>
        <button
          v-else-if="!isNew"
          class="btn btn-outline-primary"
          @click="showTransformModal = true"
          title="Marcar este cadastro também como cliente (mesma pessoa, sem cópia)"
        >
          <i class="bi bi-person-plus"></i> Marcar como Cliente
        </button>
        <button class="btn btn-outline-secondary" @click="$emit('set-view', 'fornecedores')">
          <i class="bi bi-arrow-left-circle"></i> Voltar
        </button>
        <button class="btn btn-primary" @click="saveSupplier" :disabled="saving">
          <i class="bi bi-save"></i> {{ saving ? 'Salvando...' : 'Salvar Alterações' }}
        </button>
      </div>
    </div>

    <!-- Workspace Grid -->
    <div class="content-grid">
      <!-- Main Form Column Left -->
      <div>
        <div class="card glass-panel form-card">
          <div class="card-header">
            <h3><i class="bi bi-truck"></i> Dados Cadastrais</h3>
            <span v-if="ehCliente" class="tag-papel" title="Mesmo cadastro em Clientes/CRM">
              <i class="bi bi-person-check"></i> Também é cliente
            </span>
          </div>

          <div class="card-body">
            <!-- Basic Info -->
            <div class="form-row col-2">
              <div class="form-group">
                <label for="tipo_pessoa"><i class="bi bi-person-badge"></i> Tipo de Pessoa</label>
                <select v-model="form.tipo_pessoa" id="tipo_pessoa" class="form-control">
                  <option value="J">Pessoa Jurídica</option>
                  <option value="F">Pessoa Física</option>
                </select>
              </div>
              <div class="form-group">
                <label for="categoria"><i class="bi bi-tag"></i> Categoria</label>
                <select v-model="form.categoria" id="categoria" class="form-control">
                  <option value="">Selecione uma Categoria</option>
                  <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                </select>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="nome" class="required-field"><i class="bi bi-person"></i> Nome Fantasia / Nome</label>
                <input type="text" v-model="form.nome" id="nome" class="form-control" required placeholder="Digite o nome" />
              </div>
              <div class="form-group">
                <label for="documento" class="required-field"><i class="bi bi-file-earmark-person"></i> CNPJ / CPF</label>
                <input type="text" v-model="form.documento" id="documento" @input="formatDocumentInput" class="form-control" required placeholder="00.000.000/0000-00" />
              </div>
            </div>

            <div class="form-row col-1">
              <div class="form-group">
                <label for="razao_social"><i class="bi bi-building"></i> Razão Social</label>
                <input type="text" v-model="form.razao_social" id="razao_social" class="form-control" placeholder="Razão Social completa" />
              </div>
            </div>

            <!-- Contacts -->
            <div class="form-row col-2">
              <div class="form-group">
                <label for="email"><i class="bi bi-envelope"></i> E-mail</label>
                <input type="email" v-model="form.email" id="email" class="form-control" placeholder="contato@fornecedor.com" />
              </div>
              <div class="form-group">
                <label for="telefone"><i class="bi bi-telephone"></i> Telefone</label>
                <input type="text" v-model="form.telefone" id="telefone" @input="formatPhoneInput" class="form-control" placeholder="(00) 00000-0000" />
              </div>
            </div>

            <!-- Address -->
            <div class="form-row" style="display:grid;grid-template-columns: 140px 1fr 140px;gap: 1rem;">
              <div class="form-group">
                <label for="cep"><i class="bi bi-mailbox"></i> CEP</label>
                <input type="text" v-model="form.cep" id="cep" @input="handleCepInput" class="form-control" placeholder="00000-000" />
              </div>
              <div class="form-group">
                <label for="endereco"><i class="bi bi-geo-alt"></i> Endereço</label>
                <input type="text" v-model="form.endereco" id="endereco" class="form-control" placeholder="Rua, Av..." />
              </div>
              <div class="form-group">
                <label for="numero"><i class="bi bi-123"></i> Número</label>
                <input type="text" v-model="form.numero" id="numero" class="form-control" placeholder="123" />
              </div>
            </div>

            <div class="form-row col-3">
               <div class="form-group">
                <label for="complemento"><i class="bi bi-info-square"></i> Complemento</label>
                <input type="text" v-model="form.complemento" id="complemento" class="form-control" placeholder="Sala, Apto..." />
              </div>
              <div class="form-group">
                <label for="cidade"><i class="bi bi-pin-map"></i> Cidade</label>
                <input type="text" v-model="form.cidade" id="cidade" class="form-control" placeholder="Nome da cidade" />
              </div>
              <div class="form-group">
                <label for="uf"><i class="bi bi-globe"></i> UF</label>
                <input type="text" v-model="form.uf" id="uf" class="form-control" maxlength="2" placeholder="SP" style="text-transform:uppercase;" />
              </div>
            </div>
          </div>
        </div>

        <!-- Bank Details Card -->
        <div class="card glass-panel form-card" style="margin-top:1.5rem;">
          <div class="card-header">
            <h3><i class="bi bi-bank"></i> Dados Bancários & Pagamento</h3>
          </div>
          <div class="card-body">
            <div class="form-row col-3">
              <div class="form-group">
                <label for="banco">Banco</label>
                <input type="text" v-model="form.banco" id="banco" class="form-control" placeholder="Ex: Itaú" />
              </div>
              <div class="form-group">
                <label for="agencia">Agência</label>
                <input type="text" v-model="form.agencia" id="agencia" class="form-control" placeholder="0001" />
              </div>
              <div class="form-group">
                <label for="conta">Conta</label>
                <input type="text" v-model="form.conta" id="conta" class="form-control" placeholder="12345-6" />
              </div>
            </div>
            <div class="form-row col-2">
              <div class="form-group">
                <label for="tipo_conta">Tipo de Conta</label>
                <select v-model="form.tipo_conta" id="tipo_conta" class="form-control">
                  <option value="">Selecione...</option>
                  <option value="corrente">Conta Corrente</option>
                  <option value="poupanca">Poupança</option>
                  <option value="pagamento">Pagamento</option>
                </select>
              </div>
              <div class="form-group">
                <label for="pix">Chave PIX</label>
                <input type="text" v-model="form.pix" id="pix" class="form-control" placeholder="E-mail, CPF, CNPJ ou Celular" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-gear"></i> Configurações</h3>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label for="ativo"><i class="bi bi-toggle-on"></i> Status</label>
              <select v-model="form.ativo" id="ativo" class="form-control">
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
              </select>
            </div>
            <div class="form-group" style="margin-top:1rem;" v-if="!isNew">
               <label>Cadastrado em:</label>
               <div style="color:var(--ink-muted);">{{ formatDate(form.created_at) }}</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-shield-check"></i> Auditoria</h3>
          </div>
          <div style="padding:16px 18px; color:var(--ink-muted); font-size:0.82rem; line-height:1.5;">
            <p>Certifique-se de validar os dados bancários antes de realizar qualquer transferência.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Transformar em Cliente -->
    <ModalTransformarEmCliente
      :show="showTransformModal"
      :fornecedor="form"
      @close="showTransformModal = false"
      @toast="$emit('toast', $event)"
      @success="onTransformSuccess"
    />

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { apiFetch, formatDate, formatDocument, formatPhone } from '../../services/api'
import ModalTransformarEmCliente from '../../components/modals/Fornecedores/ModalTransformarEmCliente.vue'
import { liberado, pode } from '../../composables/useAcesso'

const props = defineProps({
  supplierId: { type: Number, default: null }
})

const emit = defineEmits(['set-view', 'toast', 'open-client-detail'])

const isNew = computed(() => !props.supplierId)
const saving = ref(false)
const showTransformModal = ref(false)

/** Cadastro único: o mesmo registro pode ser cliente/CRM e fornecedor. */
const ehCliente = ref(false)
function onTransformSuccess(payload: { clienteId: number; message: string }) {
  if (payload.clienteId) {
    emit('open-client-detail', payload.clienteId)
  } else {
    emit('set-view', 'clientes')
  }
}
const categorias = ['Tecnologia', 'Papelaria', 'Serviços Gerais', 'Consultoria', 'Marketing', 'Aluguel', 'Energia', 'Telecomunicações']

const form = reactive({
  id_fornecedor: '',
  nome: '',
  razao_social: '',
  documento: '',
  tipo_pessoa: 'J',
  email: '',
  telefone: '',
  cep: '',
  endereco: '',
  numero: '',
  complemento: '',
  cidade: '',
  uf: '',
  categoria: '',
  banco: '',
  agencia: '',
  conta: '',
  tipo_conta: '',
  pix: '',
  ativo: '1',
  created_at: new Date().toISOString().substring(0, 10)
})

async function handleCepInput() {
  const clean = form.cep.replace(/\D/g, '')
  if (clean.length === 8) {
    try {
      const response = await fetch(`https://viacep.com.br/ws/${clean}/json/`)
      const data = await response.json()
      if (data && !data.erro) {
        form.endereco = data.logradouro || ''
        form.cidade = data.localidade || ''
        form.uf = data.uf || ''
        emit('toast', { msg: 'CEP encontrado!', type: 'success' })
      }
    } catch (err) { console.error(err) }
  }
}

function formatDocumentInput() {
  const maxLength = form.tipo_pessoa === 'F' ? 11 : 14
  const clean = form.documento.replace(/\D/g, '').substring(0, maxLength)
  form.documento = formatDocument(clean, clean)
}

function formatPhoneInput() {
  const clean = form.telefone.replace(/\D/g, '').substring(0, 11)
  form.telefone = formatPhone(clean, clean)
}

async function loadDetails() {
  if (isNew.value) return
  try {
    const res = await apiFetch(`fornecedores/detalhes/${props.supplierId}`)
    if (res && res.success) {
      Object.keys(form).forEach(k => {
        if (res.data[k] !== undefined && res.data[k] !== null) form[k] = String(res.data[k])
      })
      ehCliente.value = !!res.data.eh_cliente
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar dados do fornecedor.', type: 'danger' })
  }
}

async function saveSupplier() {
  if (!form.nome || form.documento.length < 11) {
    emit('toast', { msg: 'Preencha os campos obrigatórios corretamente.', type: 'warning' })
    return
  }
  saving.value = true
  try {
    const res = await apiFetch('fornecedores/salvar', {
      method: 'POST',
      body: form
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Fornecedor salvo com sucesso!', type: 'success' })
      emit('set-view', 'fornecedores')
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao salvar fornecedor.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

onMounted(loadDetails)
</script>

<style scoped>
.form-card { border-radius: 8px; }
.form-row { margin-bottom: 1.25rem; }
.form-row.col-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.form-row.col-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.form-row.col-1 { display: grid; grid-template-columns: 1fr; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.35rem; }
.required-field::after { content: ' *'; color: var(--danger); font-weight: bold; }
.card-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.tag-papel {
  font-size: .75rem; font-weight: 600; padding: 2px 10px; border-radius: 999px;
  background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent);
  display: inline-flex; align-items: center; gap: .3rem;
}
</style>
