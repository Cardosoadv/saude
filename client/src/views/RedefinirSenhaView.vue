<template>
  <div class="reset-page">
    <div class="reset-card card">
      <div class="reset-header">
        <img src="../assets/logo.png" alt="Saúde" class="reset-logo">
        <h1 class="reset-title">Criar nova senha</h1>
      </div>

      <div v-if="!token || !email" class="reset-erro">
        <i class="bi bi-exclamation-circle"></i>
        Link inválido. Peça uma nova redefinição na tela de login.
      </div>

      <form v-else-if="!concluido" @submit.prevent="enviar">
        <p class="reset-texto">Conta: <strong>{{ email }}</strong></p>

        <div class="form-group">
          <label for="nova-senha" class="form-label">Nova senha</label>
          <input id="nova-senha" v-model="senha" type="password" class="form-control" minlength="8"
                 autocomplete="new-password" placeholder="Mínimo 8 caracteres" required :disabled="loading">
        </div>
        <div class="form-group">
          <label for="conf-senha" class="form-label">Confirmar nova senha</label>
          <input id="conf-senha" v-model="confirmacao" type="password" class="form-control" minlength="8"
                 autocomplete="new-password" required :disabled="loading">
        </div>

        <div v-if="erro" class="reset-erro"><i class="bi bi-exclamation-circle"></i> {{ erro }}</div>

        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          {{ loading ? 'Salvando...' : 'Redefinir senha' }}
        </button>
      </form>

      <div v-else class="reset-ok">
        <i class="bi bi-check-circle"></i> Senha redefinida com sucesso.
      </div>

      <button type="button" class="link-btn" @click="irParaLogin">
        <i class="bi bi-arrow-left"></i> Ir para o login
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.0.0
 * Tela aberta pelo link do e-mail "Esqueci minha senha": `#/redefinir-senha?token=…&email=…`.
 */
import { ref } from 'vue'
import { perfilApi } from '../services/perfilApi'

const emit = defineEmits(['concluido'])

const parametros = new URLSearchParams(window.location.hash.split('?')[1] || '')
const token = parametros.get('token') || ''
const email = parametros.get('email') || ''

const senha = ref('')
const confirmacao = ref('')
const loading = ref(false)
const erro = ref('')
const concluido = ref(false)

async function enviar() {
  erro.value = ''
  if (senha.value.length < 8) {
    erro.value = 'A senha deve ter no mínimo 8 caracteres.'
    return
  }
  if (senha.value !== confirmacao.value) {
    erro.value = 'A confirmação não coincide com a nova senha.'
    return
  }
  loading.value = true
  try {
    await perfilApi.redefinirSenha({
      email, token, password: senha.value, pass_confirm: confirmacao.value
    })
    concluido.value = true
  } catch (err) {
    erro.value = err.message || 'Não foi possível redefinir a senha.'
  } finally {
    loading.value = false
  }
}

function irParaLogin() {
  window.location.hash = '#/'
  emit('concluido')
}
</script>

<style scoped>
.reset-page {
  flex: 1; display: flex; align-items: center; justify-content: center;
  min-height: 100vh; width: 100%; background: var(--surface); padding: 20px;
}
.reset-card {
  width: 100%; max-width: 400px; padding: 40px 30px; background: var(--primary);
  border-top: 8px solid var(--accent); box-shadow: var(--shadow-lg);
}
.reset-header { text-align: center; margin-bottom: 24px; }
.reset-logo { width: 100%; border-radius: 8px; margin-bottom: 14px; }
.reset-title { color: var(--accent); font-size: 1.15rem; font-weight: 600; margin: 0; }
.reset-texto { color: var(--accent); font-size: 0.88rem; margin: 0 0 16px; }
.form-label { color: var(--accent); }
.btn-block {
  width: 100%; justify-content: center; padding: 12px; font-size: 1rem;
  background: #C9A84C; border-color: #C9A84C; color: #0D1B2A; font-weight: 700;
}
.btn-block:hover:not(:disabled) { background: #E2C47A; border-color: #E2C47A; }
.reset-erro {
  background: #fef2f2; color: var(--danger); padding: 10px 15px; border-radius: 8px;
  font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #fecaca;
  display: flex; align-items: center; gap: 8px;
}
.reset-ok {
  background: #f0fdf4; color: #166534; padding: 12px 15px; border-radius: 8px;
  font-size: 0.9rem; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 8px;
}
.link-btn {
  display: block; margin: 18px auto 0; background: none; border: none; padding: 0;
  color: var(--accent); font-size: 0.85rem; text-decoration: underline; cursor: pointer;
}
</style>
