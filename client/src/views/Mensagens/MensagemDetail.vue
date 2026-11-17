<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="voltar">Mensagens</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isNew ? 'Nova Mensagem' : 'Detalhes' }}</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div style="display: flex; gap: 12px; align-items: center;">
        <button class="btn btn-icon" @click="voltar" title="Voltar" style="background: var(--surface); border: 1px solid var(--border);">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div>
          <h1 style="margin: 0; font-size: 1.5rem;">{{ isNew ? 'Escrever Mensagem' : 'Leitura de Mensagem' }}</h1>
        </div>
      </div>
      <div class="toolbar-right" style="display: flex; gap: 8px;" v-if="!isNew">
        <button class="btn btn-outline" @click="responder" v-if="mensagem.remetente_id">
          <i class="bi bi-reply"></i> Responder
        </button>
        <button class="btn btn-danger-outline" @click="excluirMensagem">
          <i class="bi bi-trash"></i> Excluir
        </button>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem; display: flex; justify-content: center;">
      
      <div v-if="loading" class="process-list" style="width: 100%;">
        <div class="skeleton-card">
          <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
          <div class="skeleton-line" style="width: 100%; height: 100px;"></div>
        </div>
      </div>

      <!-- Formulário de Nova Mensagem -->
      <div v-else-if="isNew" class="card" style="width: 100%; max-width: 800px; padding: 24px;">
        <div class="form-group">
          <label>Para (Destinatário ID)</label>
          <input type="number" class="form-control" v-model="form.destinatario_id" placeholder="ID do Usuário Destino" required />
          <small class="text-muted">Em breve, um seletor amigável (autocomplete) de usuários.</small>
        </div>
        <div class="form-group mt-3">
          <label>Assunto</label>
          <input type="text" class="form-control" v-model="form.assunto" placeholder="Assunto da mensagem" required />
        </div>
        <div class="form-group mt-3">
          <label>Mensagem</label>
          <textarea class="form-control" v-model="form.conteudo" rows="8" placeholder="Escreva sua mensagem aqui..." required></textarea>
        </div>
        <div class="form-actions mt-4" style="display: flex; justify-content: flex-end; gap: 12px;">
          <button class="btn btn-outline" @click="voltar">Cancelar</button>
          <button class="btn btn-primary" @click="enviarMensagem" :disabled="sending">
            <span v-if="sending"><i class="bi bi-arrow-repeat spin"></i> Enviando...</span>
            <span v-else><i class="bi bi-send"></i> Enviar Mensagem</span>
          </button>
        </div>
      </div>

      <!-- Leitura de Mensagem Existente -->
      <div v-else-if="mensagem.id" class="card" style="width: 100%; max-width: 800px; padding: 0; overflow: hidden;">
        <div class="msg-header-box" style="padding: 24px; border-bottom: 1px solid var(--border); background: #fafafa;">
          <h3 style="margin: 0 0 16px 0; font-size: 1.4rem; color: var(--ink);">{{ mensagem.assunto }}</h3>
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="display: flex; gap: 16px; align-items: center;">
              <div class="avatar-circle">
                {{ getInitials(mensagem.remetente_nome) }}
              </div>
              <div>
                <div style="font-weight: 600; color: var(--ink); font-size: 1.05rem;">
                  {{ mensagem.remetente_nome }}
                </div>
                <div style="font-size: 0.85rem; color: var(--ink-muted);">
                  Para: {{ mensagem.destinatario_nome }}
                </div>
              </div>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.85rem; color: var(--ink-muted);">
                {{ formatDateTime(mensagem.data_envio) }}
              </div>
            </div>
          </div>
        </div>
        <div class="msg-body-box" style="padding: 32px 24px; font-size: 1rem; color: var(--ink); line-height: 1.6; white-space: pre-wrap;">{{ mensagem.conteudo }}</div>

        <!-- Sugestão de Resposta IA -->
        <div v-if="mensagem.remetente_id" class="ia-resposta-box" style="padding: 24px; border-top: 1px solid var(--border); background: #fafafa;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <strong style="color: var(--primary); font-size: 0.95rem;">
              <i class="bi bi-robot" style="margin-right: 6px;"></i> Sugestão de Resposta (IA)
            </strong>
            <button
              class="btn btn-outline btn-sm"
              @click="gerarSugestaoResposta"
              :disabled="sugestaoLoading"
            >
              <i class="bi" :class="sugestaoGerada ? 'bi-arrow-repeat' : 'bi-stars'"></i>
              {{ sugestaoLoading ? 'Gerando...' : (sugestaoGerada ? 'Gerar Novamente' : 'Gerar Sugestão') }}
            </button>
          </div>

          <div v-if="sugestaoLoading" style="text-align:center; padding: 24px;">
            <div class="spinner-border spinner-border-sm" style="color: var(--accent); width: 1.5rem; height: 1.5rem;" role="status"></div>
            <p style="margin-top: 10px; color: var(--ink-muted); font-size: 0.85rem;">A IA está redigindo um rascunho de resposta com base no processo vinculado...</p>
          </div>

          <div v-else-if="sugestaoErro" style="padding: 15px; background: rgba(220, 53, 69, 0.1); border-left: 3px solid var(--danger); border-radius: 4px; color: var(--danger); font-size: 0.85rem;">
            {{ sugestaoErro }}
          </div>

          <div v-else-if="sugestaoGerada">
            <textarea
              class="form-control"
              v-model="sugestaoResposta"
              rows="6"
              placeholder="Edite a sugestão antes de usá-la como resposta..."
            ></textarea>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px;">
              <button class="btn btn-ghost" @click="copiarSugestao">
                <i class="bi bi-clipboard"></i> Copiar
              </button>
              <button class="btn btn-primary" @click="usarSugestaoComoResposta">
                <i class="bi bi-reply-fill"></i> Usar como Resposta
              </button>
            </div>
          </div>

          <p v-else style="color: var(--ink-muted); font-size: 0.85rem; margin: 0;">
            Gere um rascunho de resposta com IA, fundamentado no processo vinculado a esta mensagem (quando houver), e edite-o livremente antes de enviar.
          </p>
        </div>
      </div>

      <div v-else class="empty-state card" style="width: 100%;">
        <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
        <p style="margin-top: 16px;">Mensagem não encontrada.</p>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { apiFetch, toFormData, formatDate } from '../../services/api'

const props = defineProps({
  mensagemId: [String, Number]
})

const isNew = computed(() => props.mensagemId === 'nova' || !props.mensagemId)
const loading = ref(false)
const sending = ref(false)
const mensagem = ref<Record<string, any>>({})

const form = ref({
  destinatario_id: '',
  assunto: '',
  conteudo: ''
})

const sugestaoLoading = ref(false)
const sugestaoErro = ref(null)
const sugestaoResposta = ref('')
const sugestaoGerada = ref(false)

const loadMensagem = async () => {
  if (isNew.value) return
  loading.value = true
  try {
    const res = await apiFetch(`mensagens/detalhes/${props.mensagemId}`)
    if (res && res.success) {
      mensagem.value = res.data
    }
  } catch (err) {
    console.error('Erro ao carregar mensagem:', err)
  } finally {
    loading.value = false
  }
}

const enviarMensagem = async () => {
  if (!form.value.destinatario_id || !form.value.assunto || !form.value.conteudo) {
    alert('Preencha todos os campos obrigatórios.')
    return
  }
  sending.value = true
  try {
    const res = await apiFetch('mensagens/salvar', {
      method: 'POST',
      body: toFormData(form.value)
    })
    if (res && res.success) {
      voltar()
    }
  } catch (err) {
    console.error('Erro ao enviar mensagem:', err)
    alert(err.message || 'Erro ao enviar mensagem')
  } finally {
    sending.value = false
  }
}

const responder = (corpoPersonalizado) => {
  form.value.destinatario_id = mensagem.value.remetente_id
  form.value.assunto = `Re: ${mensagem.value.assunto}`
  form.value.conteudo = corpoPersonalizado !== undefined
    ? corpoPersonalizado
    : `\n\n--- Em ${formatDateTime(mensagem.value.data_envio)}, ${mensagem.value.remetente_nome} escreveu:\n> ` + mensagem.value.conteudo.split('\n').join('\n> ')
  window.location.hash = `#/mensagens/nova`
}

const gerarSugestaoResposta = async () => {
  sugestaoLoading.value = true
  sugestaoErro.value = null
  try {
    const res = await apiFetch(`mensagens/sugerirResposta/${props.mensagemId}`)
    if (res && res.success) {
      sugestaoResposta.value = res.data
      sugestaoGerada.value = true
    } else {
      sugestaoErro.value = res.message || 'Falha ao gerar sugestão de resposta.'
    }
  } catch (err) {
    sugestaoErro.value = err.message || 'Erro de comunicação com a IA.'
  } finally {
    sugestaoLoading.value = false
  }
}

const copiarSugestao = async () => {
  if (!sugestaoResposta.value) return
  try {
    await navigator.clipboard.writeText(sugestaoResposta.value)
  } catch (err) {
    console.error('Erro ao copiar sugestão:', err)
  }
}

const usarSugestaoComoResposta = () => {
  responder(sugestaoResposta.value)
}

const excluirMensagem = async () => {
  if (!confirm('Deseja realmente excluir esta mensagem?')) return
  try {
    const res = await apiFetch(`mensagens/excluir/${props.mensagemId}`, { method: 'DELETE' })
    if (res && res.success) {
      voltar()
    }
  } catch (err) {
    alert(err.message || 'Erro ao excluir')
  }
}

const voltar = () => {
  window.location.hash = `#/mensagens`
}

const formatDateTime = (ds) => {
  return formatDate(ds, true)
}

const getInitials = (name) => {
  if (!name) return '?'
  return name.substring(0, 2).toUpperCase()
}

onMounted(() => {
  loadMensagem()
})
</script>

<style scoped>
.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
}
.form-group label {
  font-weight: 500;
  color: var(--ink);
  margin-bottom: 6px;
  display: block;
}
.form-control {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 0.95rem;
  background: var(--bg);
  transition: border-color 0.2s;
}
.form-control:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(1, 59, 81, 0.1);
}
</style>
