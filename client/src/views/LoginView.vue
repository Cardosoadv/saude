<template>
  <div class="login-page">
    <div class="login-card card">
      <div class="login-header">
        <div class="login-logo-wrap">
          <img src="../assets/logo.png" alt="Saúde" class="login-logo">
        </div>
        <div class="login-brand-divider"></div>
        <h1 class="login-title">Acesso ao sistema</h1>
        <p>Acesse sua conta para continuar</p>
      </div>

      <form v-if="modo === 'login'" @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label for="username" class="form-label">Usuário ou E-mail</label>
          <div class="input-with-icon">
            <i class="bi bi-person"></i>
            <input
              id="username"
              v-model="credentials.email"
              type="text"
              class="form-control"
              placeholder="Digite seu usuário"
              required
              :disabled="loading"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Senha</label>
          <div class="input-with-icon">
            <i class="bi bi-lock"></i>
            <input
              id="password"
              v-model="credentials.password"
              type="password"
              class="form-control"
              placeholder="Digite sua senha"
              required
              :disabled="loading"
            >
          </div>
        </div>

        <div class="form-group remember-me">
          <label class="checkbox-container">
            <input type="checkbox" v-model="credentials.remember" :disabled="loading">
            <span>Lembrar de mim</span>
          </label>
          <button type="button" class="link-btn" :disabled="loading" @click="abrirEsqueci">Esqueci minha senha</button>
        </div>

        <div v-if="error" class="login-error">
          <i class="bi bi-exclamation-circle"></i>
          {{ error }}
        </div>

        <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <form v-else class="login-form" @submit.prevent="handleEsqueci">
        <p class="esqueci-texto">
          Informe o e-mail da sua conta. Se ele estiver cadastrado, enviaremos um link para você criar uma nova senha.
        </p>
        <div class="form-group">
          <label for="esqueci-email" class="form-label">E-mail</label>
          <div class="input-with-icon">
            <i class="bi bi-envelope"></i>
            <input
              id="esqueci-email"
              v-model="emailRecuperacao"
              type="email"
              class="form-control"
              placeholder="seu@email.com.br"
              required
              :disabled="loading || enviado"
            >
          </div>
        </div>

        <div v-if="error" class="login-error">
          <i class="bi bi-exclamation-circle"></i>
          {{ error }}
        </div>
        <div v-if="aviso" class="login-aviso">
          <i class="bi bi-check-circle"></i>
          {{ aviso }}
        </div>

        <button v-if="!enviado" type="submit" class="btn btn-primary btn-block" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'Enviando...' : 'Enviar link de redefinição' }}
        </button>
        <button type="button" class="link-btn voltar-btn" @click="voltarLogin">
          <i class="bi bi-arrow-left"></i> Voltar ao login
        </button>
      </form>

      <div class="login-footer">
        <p>&copy; {{ new Date().getFullYear() }} Saúde. Todos os direitos reservados.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { login } from '../services/auth'
import { perfilApi } from '../services/perfilApi'

const emit = defineEmits(['login-success'])

const credentials = reactive({
  email: '',
  password: '',
  remember: false
})

const loading = ref(false)
const error = ref('')
const modo = ref<'login' | 'esqueci'>('login')
const emailRecuperacao = ref('')
const aviso = ref('')
const enviado = ref(false)

function abrirEsqueci() {
  error.value = ''
  aviso.value = ''
  enviado.value = false
  // Aproveita o e-mail já digitado no login.
  if (credentials.email.includes('@')) emailRecuperacao.value = credentials.email
  modo.value = 'esqueci'
}

function voltarLogin() {
  error.value = ''
  aviso.value = ''
  modo.value = 'login'
}

async function handleEsqueci() {
  loading.value = true
  error.value = ''
  aviso.value = ''
  try {
    const res = await perfilApi.esqueciSenha(emailRecuperacao.value.trim())
    aviso.value = res?.message || 'Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha.'
    enviado.value = true
  } catch (err) {
    error.value = err.message || 'Não foi possível enviar o link. Tente novamente.'
  } finally {
    loading.value = false
  }
}

async function handleLogin() {
  loading.value = true
  error.value = ''

  try {
    const response = await login(credentials)
    
    // O auth.login agora retorna { success: true, user: { ... } }
    // ou o objeto direto da API com user aninhado
    const userData = response.user || response
    
    if (!userData || (!userData.id && !userData.user_id && !userData.username && !userData.email)) {
      // Se não recebemos dados válidos de usuário, o login falhou
      error.value = 'Não foi possível obter os dados do usuário. Tente novamente.'
      return
    }
    
    emit('login-success', userData)
  } catch (err) {
    console.error('Login error:', err)
    // Exibir mensagem de erro específica do backend quando disponível
    error.value = err.message || 'Credenciais inválidas. Por favor, tente novamente.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  width: 100%;
  background: var(--surface);
  padding: 20px;
}

.login-card {
  width: 100%;
  max-width: 400px;
  padding: 40px 30px;
  background: var(--primary);
  border-top: 8px solid var(--accent);
  box-shadow: var(--shadow-lg);
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

.login-logo-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--primary);
  border-radius: 12px;
}

.login-logo {
  width: 100%;
  height: 100%;
  object-fit: fill;
  object-position: center;
  border-radius: 8px;
  filter: brightness(1.08) contrast(1.05);
}

.login-brand-divider {
  width: 40px;
  height: 2px;
  background: var(--accent);
  border-radius: 2px;
  margin: 0 auto 14px;
  opacity: 0.8;
}


.login-title {
  color: var(--accent);
  font-size: 1.15rem;
  font-weight: 600;
  margin: 0 0 6px;
}

.login-header p {
  color: var(--accent);
  font-size: 0.9rem;
}

.form-label {
  color: var(--accent);
}

.input-with-icon {
  position: relative;
}

.input-with-icon i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--accent);
}

.input-with-icon .form-control {
  padding-left: 38px;
}

.remember-me {
  margin-top: 5px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.link-btn {
  background: none;
  border: none;
  padding: 0;
  color: var(--accent);
  font-size: 0.85rem;
  text-decoration: underline;
  cursor: pointer;
}

.voltar-btn {
  display: block;
  margin: 16px auto 0;
}

.esqueci-texto {
  color: var(--accent);
  font-size: 0.88rem;
  margin: 0 0 16px;
}

.login-aviso {
  background: #f0fdf4;
  color: #166534;
  padding: 10px 15px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #bbf7d0;
}

.checkbox-container {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--accent);
  cursor: pointer;
  user-select: none;
}

.checkbox-container input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--accent);
}

.btn-block {
  width: 100%;
  justify-content: center;
  padding: 12px;
  font-size: 1rem;
  background: #C9A84C;
  border-color: #C9A84C;
  color: #0D1B2A;
  font-weight: 700;
}

.btn-block:hover:not(:disabled) {
  background: #E2C47A;
  border-color: #E2C47A;
  color: #0D1B2A;
}

.btn-block:disabled {
  background: #C9A84C;
  border-color: #C9A84C;
  opacity: 0.6;
}

.login-error {
  background: #fef2f2;
  color: var(--danger);
  padding: 10px 15px;
  border-radius: 8px;
  font-size: 0.85rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #fecaca;
}

.login-footer {
  margin-top: 30px;
  text-align: center;
  font-size: 0.75rem;
  color: var(--ink-muted);
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #fff;
  animation: spin 0.8s linear infinite;
  margin-right: 8px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
