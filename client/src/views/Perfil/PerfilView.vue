<template>
  <div class="page-content perfil-page animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Meu perfil</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Meu perfil</h1>
        <p>Seus dados de acesso, sua apresentação profissional e sua senha.</p>
      </div>
    </div>

    <div v-if="loading" class="perfil-loading"><div class="spinner"></div></div>

    <div v-else-if="perfil" class="perfil-layout">
      <!-- ================= IDENTIDADE ================= -->
      <aside class="card perfil-identidade">
        <div class="perfil-capa"></div>
        <div class="perfil-identidade-corpo">
          <div class="profile-photo-uploader">
            <img v-if="avatarPreview" :src="avatarPreview" class="preview-image" alt="Minha foto" />
            <div v-else class="preview-initials">{{ iniciais }}</div>
            <label class="photo-upload-label" title="Alterar foto">
              <i class="bi bi-camera-fill"></i>
              <span class="sr-only">Alterar foto</span>
              <input type="file" accept="image/png,image/jpeg" class="sr-only" @change="onFoto" />
            </label>
          </div>

          <div class="perfil-nome">{{ form.username || 'Usuário' }}</div>
          <div v-if="form.cargo" class="perfil-cargo">{{ form.cargo }}</div>
          <div class="perfil-email"><i class="bi bi-envelope"></i> {{ form.email }}</div>

          <div class="perfil-selos">
            <span v-if="Number(perfil.is_profissional) === 1" class="badge badge-info">
              <i class="bi bi-briefcase"></i> {{ registro ? `Registro ${registro}` : 'Profissional de saúde' }}
            </span>
            <span v-if="Number(perfil.exibir_site) === 1" class="badge perfil-selo-site" title="Aparece na página Quem Somos do site">
              <i class="bi bi-globe2"></i> Em Quem Somos
            </span>
          </div>

          <div v-if="avatarFile" class="perfil-aviso">
            <i class="bi bi-info-circle"></i> Nova foto selecionada. Clique em <strong>Salvar perfil</strong> para aplicar.
          </div>
          <small v-else class="perfil-dica">Clique na câmera para trocar a foto (JPG ou PNG, até 2MB).</small>
        </div>
      </aside>

      <div class="perfil-principal">
        <!-- ================= DADOS ================= -->
        <form class="card" @submit.prevent="salvarPerfil">
          <div class="card-header">
            <h3><i class="bi bi-person-badge"></i> Dados de acesso</h3>
          </div>
          <div class="card-body">
            <div class="perfil-linha">
              <div class="form-group">
                <label class="form-label required-field" for="perfil-username">Nome de usuário</label>
                <input id="perfil-username" v-model="form.username" class="form-control" type="text" maxlength="30" required />
              </div>
              <div class="form-group">
                <label class="form-label required-field" for="perfil-email">E-mail</label>
                <input id="perfil-email" v-model="form.email" class="form-control" type="email" required />
                <small class="perfil-dica">Usado para entrar no sistema e para recuperar a senha.</small>
              </div>
            </div>
            <div v-if="Number(perfil.is_profissional) === 1" class="perfil-linha">
              <div class="form-group">
                <label class="form-label" for="perfil-registro">Registro no conselho</label>
                <input id="perfil-registro" class="form-control" type="text" :value="registro || '—'" disabled />
              </div>
            </div>
          </div>

          <div class="card-header perfil-secao">
            <h3><i class="bi bi-mortarboard"></i> Apresentação profissional</h3>
            <span v-if="Number(perfil.exibir_site) === 1" class="badge perfil-selo-site">
              <i class="bi bi-globe2"></i> Público
            </span>
          </div>
          <div class="card-body">
            <p class="perfil-nota" :class="{ 'perfil-nota-publica': Number(perfil.exibir_site) === 1 }">
              <i :class="Number(perfil.exibir_site) === 1 ? 'bi bi-eye' : 'bi bi-eye-slash'"></i>
              <span>{{ Number(perfil.exibir_site) === 1
                ? 'Estas informações aparecem publicamente na página Quem Somos do site.'
                : 'Estas informações só vão aparecer na página Quem Somos se um administrador incluir você na equipe.' }}</span>
            </p>

            <div class="perfil-linha">
              <div class="form-group">
                <label class="form-label" for="perfil-cargo">Cargo</label>
                <input id="perfil-cargo" v-model="form.cargo" class="form-control" type="text" maxlength="120" placeholder="Ex.: Sócio-fundador" />
              </div>
              <div class="form-group">
                <label class="form-label" for="perfil-formacao">Formação</label>
                <input id="perfil-formacao" v-model="form.formacao" class="form-control" type="text" maxlength="255" />
              </div>
              <div class="form-group">
                <label class="form-label" for="perfil-espec">Especializações</label>
                <input id="perfil-espec" v-model="form.especializacoes" class="form-control" type="text" maxlength="255" />
              </div>
              <div class="form-group">
                <label class="form-label" for="perfil-areas">Áreas de atuação</label>
                <input id="perfil-areas" v-model="form.areas_atuacao" class="form-control" type="text" maxlength="255" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="perfil-curriculo">Minicurrículo</label>
              <textarea id="perfil-curriculo" v-model="form.curriculo" class="form-control perfil-textarea" rows="5" placeholder="Resumo da sua trajetória profissional."></textarea>
            </div>
            <div class="perfil-linha">
              <div class="form-group">
                <label class="form-label" for="perfil-lattes">Currículo Lattes</label>
                <div class="perfil-input-icone">
                  <i class="bi bi-journal-text"></i>
                  <input id="perfil-lattes" v-model="form.lattes_url" class="form-control" type="url" placeholder="https://lattes.cnpq.br/…" />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="perfil-linkedin">LinkedIn</label>
                <div class="perfil-input-icone">
                  <i class="bi bi-linkedin"></i>
                  <input id="perfil-linkedin" v-model="form.linkedin_url" class="form-control" type="url" placeholder="https://www.linkedin.com/in/…" />
                </div>
              </div>
            </div>
          </div>

          <div class="perfil-rodape">
            <button class="btn btn-primary" type="submit" :disabled="salvando">
              <i class="bi bi-check-circle"></i> {{ salvando ? 'Salvando...' : 'Salvar perfil' }}
            </button>
          </div>
        </form>

        <!-- ================= SENHA ================= -->
        <form class="card" @submit.prevent="salvarSenha">
          <div class="card-header">
            <h3><i class="bi bi-shield-lock"></i> Alterar senha</h3>
          </div>
          <div class="card-body">
            <div class="perfil-linha perfil-linha-3">
              <div class="form-group">
                <label class="form-label required-field" for="senha-atual">Senha atual</label>
                <input id="senha-atual" v-model="senha.atual" class="form-control" type="password" autocomplete="current-password" required />
              </div>
              <div class="form-group">
                <label class="form-label required-field" for="senha-nova">Nova senha</label>
                <input id="senha-nova" v-model="senha.nova" class="form-control" type="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required />
              </div>
              <div class="form-group">
                <label class="form-label required-field" for="senha-conf">Confirmar nova senha</label>
                <input id="senha-conf" v-model="senha.confirmacao" class="form-control" :class="{ 'is-invalid': senhaDivergente }" type="password" minlength="8" autocomplete="new-password" required />
                <small v-if="senhaDivergente" class="perfil-erro">As senhas não coincidem.</small>
              </div>
            </div>
          </div>
          <div class="perfil-rodape">
            <button class="btn btn-primary" type="submit" :disabled="trocandoSenha">
              <i class="bi bi-key"></i> {{ trocandoSenha ? 'Alterando...' : 'Alterar senha' }}
            </button>
          </div>
        </form>

        <!-- ================= NOTIFICAÇÕES PUSH ================= -->
        <NotificacoesPushCard @toast="(t) => emit('toast', t)" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.1.0
 * Perfil do usuário logado: dados pessoais, apresentação profissional (usada em Quem Somos),
 * foto e troca de senha.
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { BASE_URL } from '../../services/api'
import { perfilApi, type PerfilUsuario } from '../../services/perfilApi'
import NotificacoesPushCard from '../../components/Perfil/NotificacoesPushCard.vue'

const emit = defineEmits(['toast', 'perfil-atualizado', 'set-view'])

const loading = ref(true)
const salvando = ref(false)
const trocandoSenha = ref(false)
const perfil = ref<PerfilUsuario | null>(null)
const avatarFile = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)

const form = reactive({
  username: '', email: '', cargo: '', formacao: '', especializacoes: '',
  areas_atuacao: '', curriculo: '', lattes_url: '', linkedin_url: ''
})
const senha = reactive({ atual: '', nova: '', confirmacao: '' })

const iniciais = computed(() =>
  (form.username || 'U').trim().split(/\s+/).map(n => n[0]).join('').toUpperCase().slice(0, 2))
const senhaDivergente = computed(() =>
  senha.confirmacao.length > 0 && senha.nova !== senha.confirmacao)
const registro = computed(() =>
  [perfil.value?.registro_numero, perfil.value?.registro_uf].filter(Boolean).join(' / '))

function primeiroErro(err: any, padrao: string): string {
  const erros = err?.data?.errors
  if (erros && typeof erros === 'object') {
    const primeiro = Object.values(erros)[0]
    if (primeiro) return String(primeiro)
  }
  return err?.message || padrao
}

async function carregar() {
  loading.value = true
  try {
    const res = await perfilApi.obter()
    if (res?.success && res.data) {
      perfil.value = res.data
      Object.assign(form, {
        username: res.data.username || '',
        email: res.data.email || '',
        cargo: res.data.cargo || '',
        formacao: res.data.formacao || '',
        especializacoes: res.data.especializacoes || '',
        areas_atuacao: res.data.areas_atuacao || '',
        curriculo: res.data.curriculo || '',
        lattes_url: res.data.lattes_url || '',
        linkedin_url: res.data.linkedin_url || ''
      })
      avatarPreview.value = res.data.auth_image
        ? `${BASE_URL}usuarios/exibirFoto/${res.data.id}?t=${res.data.auth_image}`
        : null
    } else {
      emit('toast', { msg: res?.message || 'Não foi possível carregar o perfil.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: primeiroErro(err, 'Erro ao carregar o perfil.'), type: 'error' })
  } finally {
    loading.value = false
  }
}

function onFoto(event: Event) {
  const arquivo = (event.target as HTMLInputElement).files?.[0]
  if (!arquivo) return
  if (!['image/jpeg', 'image/png'].includes(arquivo.type)) {
    emit('toast', { msg: 'Use uma imagem JPG ou PNG.', type: 'warning' })
    return
  }
  if (arquivo.size > 2 * 1024 * 1024) {
    emit('toast', { msg: 'O arquivo de imagem deve ter menos de 2MB.', type: 'warning' })
    return
  }
  avatarFile.value = arquivo
  const leitor = new FileReader()
  leitor.onload = (e) => { avatarPreview.value = String(e.target?.result || '') }
  leitor.readAsDataURL(arquivo)
}

async function salvarPerfil() {
  if (!form.username.trim() || !form.email.trim()) {
    emit('toast', { msg: 'Nome de usuário e e-mail são obrigatórios.', type: 'warning' })
    return
  }
  salvando.value = true
  try {
    const dados = new FormData()
    Object.entries(form).forEach(([chave, valor]) => dados.append(chave, valor))
    if (avatarFile.value) dados.append('foto-perfil', avatarFile.value)

    const res = await perfilApi.atualizar(dados)
    if (res?.success) {
      emit('toast', { msg: res.message || 'Perfil atualizado!', type: 'success' })
      emit('perfil-atualizado', {
        username: form.username,
        email: form.email,
        foto: avatarFile.value ? Date.now() : null
      })
      avatarFile.value = null
      await carregar()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar o perfil.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: primeiroErro(err, 'Erro ao salvar o perfil.'), type: 'error' })
  } finally {
    salvando.value = false
  }
}

async function salvarSenha() {
  if (senha.nova.length < 8) {
    emit('toast', { msg: 'A nova senha deve ter no mínimo 8 caracteres.', type: 'warning' })
    return
  }
  if (senha.nova !== senha.confirmacao) {
    emit('toast', { msg: 'A confirmação não coincide com a nova senha.', type: 'warning' })
    return
  }
  trocandoSenha.value = true
  try {
    const res = await perfilApi.alterarSenha({
      senha_atual: senha.atual,
      nova_senha: senha.nova,
      pass_confirm: senha.confirmacao
    })
    if (res?.success) {
      emit('toast', { msg: res.message || 'Senha alterada!', type: 'success' })
      senha.atual = senha.nova = senha.confirmacao = ''
    } else {
      emit('toast', { msg: res?.message || 'Erro ao alterar a senha.', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: primeiroErro(err, 'Erro ao alterar a senha.'), type: 'error' })
  } finally {
    trocandoSenha.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.perfil-loading { display: flex; justify-content: center; padding: 60px 0; }

.perfil-layout {
  display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 20px;
  align-items: start; max-width: 1100px;
}
.perfil-principal { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

/* ---------- Cartão de identidade ---------- */
.perfil-identidade { position: sticky; top: 0; overflow: hidden; }
.perfil-capa { height: 84px; background: linear-gradient(135deg, var(--primary) 0%, #01516e 60%, var(--accent) 140%); }
.perfil-identidade-corpo {
  display: flex; flex-direction: column; align-items: center; text-align: center;
  gap: 6px; padding: 0 20px 22px; margin-top: -56px;
}
.profile-photo-uploader {
  position: relative; width: 112px; height: 112px; border-radius: 50%;
  border: 4px solid var(--card); background-color: var(--primary); color: #fff;
  display: flex; align-items: center; justify-content: center;
  box-shadow: var(--shadow); margin-bottom: 8px;
}
.preview-image { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.preview-initials { font-family: var(--font-head); font-size: 2.3rem; font-weight: 600; }
.photo-upload-label {
  position: absolute; right: 0; bottom: 2px; width: 34px; height: 34px; border-radius: 50%;
  background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center;
  cursor: pointer; border: 3px solid var(--card); transition: transform .15s, background .15s;
}
.photo-upload-label:hover { background: #b07b20; transform: scale(1.08); }
.photo-upload-label:focus-within { outline: 2px solid var(--primary); outline-offset: 2px; }

.perfil-nome { font-family: var(--font-head); font-size: 1.25rem; font-weight: 600; color: var(--ink); line-height: 1.25; word-break: break-word; }
.perfil-cargo { font-size: .85rem; color: var(--ink-dim); }
.perfil-email { font-size: .8rem; color: var(--ink-muted); display: flex; align-items: center; gap: 5px; word-break: break-all; }
.perfil-selos { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin: 6px 0 4px; }
.perfil-selos:empty { display: none; }
.perfil-selo-site { background: rgba(200, 146, 42, .14); color: #92600f; }
.perfil-aviso {
  margin-top: 6px; font-size: .78rem; color: var(--primary); background: var(--primary-lt);
  border-radius: 8px; padding: 8px 10px; text-align: left; line-height: 1.4;
}

/* ---------- Formulários ---------- */
.perfil-secao { border-top: 1px solid var(--border); }
.perfil-linha { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 16px; }
.perfil-linha-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.perfil-dica { display: block; font-size: .75rem; color: var(--ink-muted); margin-top: 4px; }
.perfil-erro { display: block; font-size: .75rem; color: var(--danger); margin-top: 4px; }
.form-control.is-invalid { border-color: var(--danger); }
.perfil-textarea { resize: vertical; min-height: 110px; line-height: 1.5; }

.perfil-nota {
  display: flex; gap: 8px; align-items: flex-start; margin: 0 0 16px;
  font-size: .8rem; color: var(--ink-dim); background: var(--surface);
  border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px;
}
.perfil-nota i { margin-top: 1px; color: var(--ink-muted); }
.perfil-nota-publica { background: rgba(200, 146, 42, .08); border-color: rgba(200, 146, 42, .35); }
.perfil-nota-publica i { color: var(--accent); }

.perfil-input-icone { position: relative; }
.perfil-input-icone i {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  color: var(--ink-muted); font-size: .9rem; pointer-events: none;
}
.perfil-input-icone .form-control { padding-left: 34px; }

.perfil-rodape {
  display: flex; justify-content: flex-end; padding: 12px 20px;
  border-top: 1px solid var(--border); background: var(--surface);
  border-radius: 0 0 var(--radius) var(--radius);
}

.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}

@media (max-width: 960px) {
  .perfil-layout { grid-template-columns: 1fr; }
  .perfil-identidade { position: static; }
}
@media (max-width: 640px) {
  .perfil-linha, .perfil-linha-3 { grid-template-columns: 1fr; }
  .perfil-rodape .btn { width: 100%; justify-content: center; }
}
</style>
