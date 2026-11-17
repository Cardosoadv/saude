<template>
  <div class="modal-backdrop animate-fade-in" @click.self="$emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="oauth-modal-title">
      <div class="modal-header">
        <h2 id="oauth-modal-title">
          <i class="bi bi-shield-lock"></i>
          Conector "{{ cliente.nome }}" criado
        </h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="mcp-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Copie o Client Secret agora. Por segurança, ele não será exibido novamente — se perdê-lo,
          remova este conector e crie outro.
        </div>

        <p class="mcp-hint">
          Preencha a tela de conector personalizado (ex.: Gemini) com os valores abaixo. Ao conectar, o
          Gemini abrirá a tela de autorização do sistema.
        </p>

        <div class="form-group" v-for="campo in campos" :key="campo.label">
          <label class="form-label">{{ campo.label }}</label>
          <div class="mcp-copy-field">
            <input class="form-control" type="text" :value="campo.valor" readonly @focus="($event.target as HTMLInputElement).select()" />
            <button class="btn btn-ghost btn-sm" @click="copy(campo.valor, `${campo.label} copiado!`)" :title="`Copiar ${campo.label}`">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" @click="$emit('close')">Concluir</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.0.0
 * Exibe as credenciais (Client ID/Secret) e as URLs de um conector OAuth
 * recém-cadastrado, para colar em clientes de IA sem Dynamic Client
 * Registration, como o Gemini. O Client Secret só aparece aqui.
 */
import { computed } from 'vue'

const props = defineProps({
  cliente: { type: Object, required: true }
})

const emit = defineEmits(['close', 'toast'])

const campos = computed(() => [
  { label: 'URL do servidor MCP', valor: props.cliente.mcp_url },
  { label: 'Client ID', valor: props.cliente.client_id },
  { label: 'Client Secret', valor: props.cliente.client_secret },
  { label: 'URL de autorização', valor: props.cliente.authorization_url },
  { label: 'URL do token', valor: props.cliente.token_url },
  { label: 'Escopo', valor: props.cliente.scope }
])

async function copy(text, mensagem) {
  try {
    await navigator.clipboard.writeText(text)
    emit('toast', { msg: mensagem, type: 'success' })
  } catch (err) {
    emit('toast', { msg: 'Não foi possível copiar automaticamente. Selecione e copie manualmente.', type: 'error' })
  }
}
</script>

<style scoped>
.mcp-warning {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #fef9c3;
  color: #92400e;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.85rem;
  line-height: 1.4;
  margin-bottom: 16px;
}

.mcp-warning i { margin-top: 2px; }

.mcp-copy-field {
  display: flex;
  gap: 8px;
}

.mcp-copy-field .form-control {
  font-family: monospace;
  font-size: 0.85rem;
}

.mcp-hint {
  font-size: 0.82rem;
  color: var(--ink-muted);
  margin: 0 0 12px 0;
  line-height: 1.4;
}
</style>
