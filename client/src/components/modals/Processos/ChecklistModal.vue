<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box modal-lg">
      <div class="modal-header">
        <h2 v-if="mode === 'apply'"><i class="bi bi-check2-all"></i> Aplicar Novo Checklist</h2>
        <h2 v-else><i class="bi bi-robot"></i> Sugerir Checklist com IA</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- MODE: APPLY TEMPLATE -->
        <div v-if="mode === 'apply'">
          <div v-if="loadingTemplates" class="loading-state">
            <span class="spinner"></span>
            <p>Carregando templates disponíveis...</p>
          </div>
          <div v-else-if="!templates.length" class="empty-state">
            <i class="bi bi-check-circle-fill text-success"></i>
            <h3>Tudo em dia!</h3>
            <p>Todos os templates de checklist disponíveis já foram aplicados a este processo.</p>
          </div>
          <div v-else>
            <p class="section-desc">Selecione um dos modelos de checklist abaixo para aplicar aos procedimentos deste processo:</p>
            
            <div class="templates-grid">
              <div v-for="temp in templates" :key="temp.id" 
                class="template-card" 
                :class="{ active: selectedTemplateId === temp.id }"
                @click="selectedTemplateId = temp.id">
                <div class="template-radio">
                  <input type="radio" :value="temp.id" v-model="selectedTemplateId">
                </div>
                <div class="template-info">
                  <h4>{{ temp.titulo }}</h4>
                  <p v-if="temp.descricao">{{ temp.descricao }}</p>
                  <span class="badge badge-accent small" v-if="temp.gerar_tarefas">Gera tarefas automáticas</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- MODE: IA SUGGESTIONS -->
        <div v-else>
          <!-- IA LOADING STATE -->
          <div v-if="loadingIA" class="loading-ia-state">
            <i class="bi bi-robot spinner-robot"></i>
            <h3>A Inteligência Artificial está trabalhando...</h3>
            <p class="pulse-text">Analisando o título, número e comentários do processo para estruturar procedimentos adequados...</p>
            <div class="progress-bar-container">
              <div class="progress-bar-fill"></div>
            </div>
          </div>

          <!-- IA SUGGESTIONS DISPLAY -->
          <div v-else>
            <p class="section-desc">Abaixo estão os procedimentos sugeridos pela IA com base no contexto deste processo. Você pode revisar, remover ou adicionar itens antes de gerar o checklist.</p>
            
            <div class="ia-sections-container">
              <div v-for="(secao, sIdx) in suggestedSections" :key="sIdx" class="ia-section-card">
                <div class="ia-section-header">
                  <input type="text" v-model="secao.secao" class="form-control-plain secao-title" placeholder="Nome da Seção">
                  <button class="btn-icon btn-danger-soft" @click="removeSection(sIdx)" title="Remover Seção">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
                
                <div class="ia-section-body">
                  <div v-for="(item, iIdx) in secao.itens" :key="iIdx" class="ia-item-row">
                    <div class="item-checkbox-wrapper">
                      <input type="checkbox" v-model="item.selected" :id="`ia-check-${sIdx}-${iIdx}`">
                    </div>
                    <div class="item-fields">
                      <input type="text" v-model="item.titulo" class="form-control form-control-sm" placeholder="Título da tarefa *">
                      <input type="text" v-model="item.descricao" class="form-control form-control-sm text-muted" placeholder="Observações/Detalhes (Opcional)">
                    </div>
                    <button class="btn-icon btn-danger-soft" @click="removeItem(sIdx, iIdx)" title="Remover Item">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                  
                  <button class="btn btn-sm btn-ghost btn-add-item-inner" @click="addItem(sIdx)">
                    <i class="bi bi-plus-lg"></i> Adicionar Item à Seção
                  </button>
                </div>
              </div>

              <!-- Button to Add a New Section -->
              <button class="btn btn-outline-primary w-100" @click="addNewSection">
                <i class="bi bi-plus-circle"></i> Adicionar Nova Seção
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        
        <!-- Action Buttons -->
        <button v-if="mode === 'apply'" 
          class="btn btn-primary" 
          :disabled="!selectedTemplateId || loadingTemplates" 
          @click="submitApply">
          <i class="bi bi-check-circle"></i> Aplicar Checklist
        </button>
        <button v-else-if="!loadingIA" 
          class="btn btn-primary" 
          @click="submitIA">
          <i class="bi bi-robot"></i> Gerar Checklist IA
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Modal de Gestão de Checklists (Templates & IA)
 * @version 1.3.11
 */
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (v: string) => ['apply', 'ia'].includes(v)
  },
  processId: {
    type: Number,
    required: false
  },
  targetId: {
    type: Number,
    required: false
  },
  targetType: {
    type: String,
    default: 'processo'
  }
})

const emit = defineEmits(['close', 'success', 'warn'])

// Mode: Apply variables
const templates = ref([])
const loadingTemplates = ref(false)
const selectedTemplateId = ref(null)

// Mode: IA variables
const loadingIA = ref(false)
const suggestedSections = ref([])

onMounted(() => {
  if (props.mode === 'apply') {
    fetchTemplates()
  } else {
    fetchIASuggestions()
  }
})

// --- MODE: APPLY FUNCTIONS ---
async function fetchTemplates() {
  loadingTemplates.value = true
  try {
    const tId = props.targetId || props.processId
    const res = await apiFetch(`checklist/getTemplatesDisponiveis/${props.targetType}/${tId}`)
    if (res && res.success && Array.isArray(res.data)) {
      templates.value = res.data
    } else if (res && Array.isArray(res)) {
      templates.value = res
    } else {
      templates.value = []
    }
  } catch (err) {
    console.error('Erro ao buscar templates disponíveis:', err)
    emit('warn', 'Erro ao obter os templates disponíveis no servidor.')
  } finally {
    loadingTemplates.value = false
  }
}

async function submitApply() {
  if (!selectedTemplateId.value) return
  
  try {
    const res = await apiFetch('checklist/aplicarTemplate', {
      method: 'POST',
      body: {
        type: props.targetType,
        target_id: props.targetId || props.processId,
        template_id: selectedTemplateId.value
      }
    })

    if (res && res.success) {
      emit('success', 'Checklist de template aplicado com sucesso! As tarefas vinculadas foram criadas.')
    } else {
      emit('warn', res?.message || 'Não foi possível aplicar o template.')
    }
  } catch (err) {
    console.error('Erro ao aplicar checklist de template:', err)
    emit('warn', 'Falha na comunicação ao aplicar o template.')
  }
}

// --- MODE: IA FUNCTIONS ---
async function fetchIASuggestions() {
  loadingIA.value = true
  try {
    const tId = props.targetId || props.processId
    const res = await apiFetch(`checklist/sugerirIA?type=${props.targetType}&id=${tId}`)
    if (res && res.success && Array.isArray(res.data)) {
      // Map suggestions to include client-side reactive "selected" states
      suggestedSections.value = res.data.map(secao => ({
        secao: secao.secao || 'Geral',
        itens: (secao.itens || []).map(item => ({
          titulo: item.titulo || '',
          descricao: item.descricao || '',
          selected: true
        }))
      }))
    } else if (res && Array.isArray(res)) { // Alternate response mapping
      suggestedSections.value = res.map(secao => ({
        secao: secao.secao || 'Geral',
        itens: (secao.itens || []).map(item => ({
          titulo: item.titulo || '',
          descricao: item.descricao || '',
          selected: true
        }))
      }))
    } else {
      // Fallback mockup items if IA returns empty or service is currently inactive
      suggestedSections.value = [
        {
          secao: 'Análise Inicial',
          itens: [
            { titulo: 'Verificar tempestividade do prazo recursal', descricao: 'Cruzar data de publicação com prazo legal', selected: true },
            { titulo: 'Confirmar procuração e substabelecimento nos autos', descricao: 'Garantir representação processual ativa', selected: true },
            { titulo: 'Auditar cálculo de custas recursais e preparo', descricao: 'Confirmar guias anexadas corretamente', selected: true }
          ]
        },
        {
          secao: 'Elaboração Técnico-Jurídica',
          itens: [
            { titulo: 'Redigir petição e razões recursais', descricao: 'Focar em preliminares de cerceamento de defesa', selected: true },
            { titulo: 'Elaborar quadro de jurisprudência confrontada', descricao: 'Destacar divergência em acórdãos do TST', selected: true }
          ]
        }
      ]
    }
  } catch (err) {
    console.error('Erro ao obter sugestões da IA:', err)
    emit('warn', 'Erro ao falar com a Inteligência Artificial. Fornecemos um esqueleto padrão.')
    // Fallback skeletons
    suggestedSections.value = [
      {
        secao: 'Procedimentos Sugeridos',
        itens: [
          { titulo: 'Revisar petição e procuração do processo', descricao: 'Conferência técnica', selected: true },
          { titulo: 'Cadastrar prazos fatais no calendário principal', descricao: 'Evitar perdas de prazos judiciais', selected: true }
        ]
      }
    ]
  } finally {
    loadingIA.value = false
  }
}

function addNewSection() {
  suggestedSections.value.push({
    secao: `Nova Seção #${suggestedSections.value.length + 1}`,
    itens: []
  })
}

function removeSection(sIdx) {
  suggestedSections.value.splice(sIdx, 1)
}

function addItem(sIdx) {
  suggestedSections.value[sIdx].itens.push({
    titulo: '',
    descricao: '',
    selected: true
  })
}

function removeItem(sIdx, iIdx) {
  suggestedSections.value[sIdx].itens.splice(iIdx, 1)
}

async function submitIA() {
  // Validate that we have at least one selected item
  const validSections = suggestedSections.value.map(secao => {
    return {
      secao: secao.secao,
      itens: secao.itens.filter(item => item.selected && item.titulo.trim().length > 0)
    }
  }).filter(secao => secao.itens.length > 0)

  if (validSections.length === 0) {
    emit('warn', 'Por favor, selecione ou digite pelo menos um item com título válido.')
    return
  }

  try {
    const res = await apiFetch('checklist/salvarSugestoesIA', {
      method: 'POST',
      body: {
        type: props.targetType,
        target_id: props.targetId || props.processId,
        sugestoes: validSections
      }
    })

    if (res && res.success) {
      emit('success', 'Checklist sugerido pela IA foi gerado com êxito! As tarefas correspondentes já estão agendadas.')
    } else {
      emit('warn', res?.message || 'Falha ao salvar o checklist da IA.')
    }
  } catch (err) {
    console.error('Erro ao salvar sugestões de checklists da IA:', err)
    emit('warn', 'Não foi possível salvar o checklist gerado pela IA.')
  }
}
</script>

<style scoped>
.modal-lg {
  max-width: 800px;
  width: 95%;
}

.section-desc {
  color: var(--ink-dim);
  font-size: 0.88rem;
  margin-bottom: 1.5rem;
  line-height: 1.4;
}

.loading-state, .loading-ia-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  text-align: center;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(212, 175, 55, 0.1);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.spinner-robot {
  font-size: 3.2rem;
  color: var(--accent);
  margin-bottom: 1.2rem;
  animation: float 2.5s ease-in-out infinite;
  display: inline-block;
}

.pulse-text {
  font-size: 0.85rem;
  color: var(--ink-muted);
  animation: pulse 2s infinite ease-in-out;
  margin-bottom: 1.5rem;
}

.progress-bar-container {
  width: 60%;
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  width: 75%;
  background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
  border-radius: 4px;
  animation: fillProgress 3s infinite linear;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes float {
  0% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-12px) rotate(4deg); }
  100% { transform: translateY(0) rotate(0deg); }
}

@keyframes pulse {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}

@keyframes fillProgress {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(150%); }
}

/* Templates grid and cards */
.templates-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 380px;
  overflow-y: auto;
  padding-right: 4px;
}

.template-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 14px;
  background: rgba(255, 255, 255, 0.01);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.template-card:hover {
  background: rgba(212, 175, 55, 0.03);
  border-color: rgba(212, 175, 55, 0.3);
}

.template-card.active {
  background: rgba(212, 175, 55, 0.08);
  border-color: var(--accent);
  box-shadow: 0 0 12px rgba(212, 175, 55, 0.15);
}

.template-radio {
  padding-top: 2px;
}

.template-info h4 {
  font-family: 'DM Sans', sans-serif;
  color: var(--ink-bright);
  font-size: 0.95rem;
  margin: 0 0 6px 0;
  font-weight: 600;
}

.template-info p {
  color: var(--ink-dim);
  font-size: 0.8rem;
  margin: 0 0 8px 0;
  line-height: 1.3;
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  text-align: center;
}

.empty-state i {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--ink-bright);
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--ink-muted);
  font-size: 0.85rem;
  max-width: 320px;
}

/* IA Editor styling */
.ia-sections-container {
  display: flex;
  flex-direction: column;
  gap: 18px;
  max-height: 420px;
  overflow-y: auto;
  padding-right: 6px;
  margin-bottom: 18px;
}

.ia-section-card {
  background: rgba(255, 255, 255, 0.01);
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
}

.ia-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid var(--border);
}

.secao-title {
  background: transparent;
  border: none;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--accent);
  padding: 2px 4px;
  width: 80%;
  font-family: 'DM Sans', sans-serif;
}

.secao-title:focus {
  background: rgba(255, 255, 255, 0.05);
  outline: 1px solid rgba(212, 175, 55, 0.5);
  border-radius: 4px;
}

.ia-section-body {
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ia-item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.03);
  border-radius: 6px;
  padding: 8px 10px;
}

.item-checkbox-wrapper {
  display: flex;
  align-items: center;
}

.item-checkbox-wrapper input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--accent);
}

.item-fields {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex-grow: 1;
}

.item-fields input {
  background: transparent;
  border: none;
  padding: 2px 4px;
  width: 100%;
}

.item-fields input:focus {
  background: rgba(255, 255, 255, 0.05);
  outline: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.item-fields .text-muted {
  font-size: 0.76rem;
  color: var(--ink-muted);
}

.btn-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--ink-muted);
  transition: all 0.2s ease;
}

.btn-icon:hover {
  color: var(--danger);
  background: rgba(220, 53, 69, 0.1);
}

.btn-danger-soft {
  color: rgba(220, 53, 69, 0.7);
}

.btn-danger-soft:hover {
  color: var(--danger);
  background: rgba(220, 53, 69, 0.15);
}

.btn-add-item-inner {
  align-self: flex-start;
  font-size: 0.78rem;
  padding: 4px 10px;
  margin-top: 4px;
  color: var(--accent);
}

.btn-add-item-inner:hover {
  background: rgba(212, 175, 55, 0.1);
}

.form-control-sm {
  font-size: 0.82rem;
  color: var(--ink-bright);
}
</style>
