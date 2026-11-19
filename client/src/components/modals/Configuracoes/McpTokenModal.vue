<template>
  <div class="modal-backdrop animate-fade-in" @click.self="$emit('close')">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="mcp-modal-title">
      <div class="modal-header">
        <h2 id="mcp-modal-title">
          <i class="bi bi-key"></i>
          Token de IA gerado
        </h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <div class="mcp-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Copie o token agora. Por segurança, ele não será exibido novamente — se perdê-lo, será
          necessário gerar um novo.
        </div>

        <div class="form-group">
          <label class="form-label">Token ("{{ tokenName }}")</label>
          <div class="mcp-copy-field">
            <input class="form-control" type="text" :value="token" readonly @focus="($event.target as HTMLInputElement).select()" />
            <button class="btn btn-ghost btn-sm" @click="copy(token, 'Token copiado para a área de transferência!')" title="Copiar token">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Endpoint MCP</label>
          <div class="mcp-copy-field">
            <input class="form-control" type="text" :value="mcpUrl" readonly @focus="($event.target as HTMLInputElement).select()" />
            <button class="btn btn-ghost btn-sm" @click="copy(mcpUrl, 'Endpoint copiado!')" title="Copiar endpoint">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Configuração pronta para o cliente de IA</label>
          <p class="mcp-hint">
            Baixe o arquivo já preenchido e cole no seu cliente MCP (ex.: <code>claude_desktop_config.json</code>
            do Claude Desktop) ou copie o trecho abaixo.
          </p>
          <pre class="mcp-config-preview">{{ configJson }}</pre>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="copy(configJson, 'Configuração copiada!')">
          <i class="bi bi-clipboard"></i> Copiar JSON
        </button>
        <button class="btn btn-primary" @click="baixarConfig">
          <i class="bi bi-download"></i> Baixar claude_desktop_config.json
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.0.0
 * Exibe o token de IA/MCP recém-gerado e entrega uma configuração pronta
 * (JSON) para o usuário colar/baixar e plugar em um cliente MCP externo.
 */
import { computed } from 'vue'

const props = defineProps({
  token: { type: String, required: true },
  tokenName: { type: String, default: 'Claude Desktop' },
  mcpUrl: { type: String, required: true }
})

const emit = defineEmits(['close', 'toast'])

const configJson = computed(() => JSON.stringify({
  mcpServers: {
    saude: {
      url: props.mcpUrl,
      headers: {
        Authorization: `Bearer ${props.token}`
      }
    }
  }
}, null, 2))

async function copy(text, mensagem) {
  try {
    await navigator.clipboard.writeText(text)
    emit('toast', { msg: mensagem, type: 'success' })
  } catch (err) {
    emit('toast', { msg: 'Não foi possível copiar automaticamente. Selecione e copie manualmente.', type: 'error' })
  }
}

function baixarConfig() {
  const blob = new Blob([configJson.value], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'claude_desktop_config.json'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
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
  margin: 0 0 8px 0;
  line-height: 1.4;
}

.mcp-config-preview {
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 8px;
  padding: 14px;
  font-size: 0.8rem;
  overflow-x: auto;
  margin: 0;
}
</style>
