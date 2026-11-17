<template>
  <div class="modal-backdrop animate-fade-in" @click.self="$emit('close')">
    <div class="modal-box modal-lg" role="dialog" aria-modal="true" aria-labelledby="modal-title" style="max-width: 750px;">
      <div class="modal-header">
        <h2 id="modal-title">
          <i class="bi bi-person-gear"></i>
          {{ isEdit ? 'Editar Usuário' : 'Novo Usuário' }}
        </h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        
        <!-- Modal Tabs -->
        <div class="modal-tabs" style="display:flex; border-bottom:1px solid var(--border); margin-bottom:1.5rem; gap:16px;">
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'dados' }"
            @click="activeTab = 'dados'"
            style="background:none; border:none; padding:8px 4px; border-bottom:2px solid transparent; font-weight:600; cursor:pointer;"
            :style="activeTab === 'dados' ? 'border-color:var(--accent);color:var(--accent);' : 'color:var(--ink-muted);'"
          >
            Dados Gerais
          </button>
          <button
            class="tab-btn"
            :class="{ active: activeTab === 'permissoes' }"
            @click="activeTab = 'permissoes'"
            style="background:none; border:none; padding:8px 4px; border-bottom:2px solid transparent; font-weight:600; cursor:pointer;"
            :style="activeTab === 'permissoes' ? 'border-color:var(--accent);color:var(--accent);' : 'color:var(--ink-muted);'"
          >
            Grupos & Permissões
          </button>
        </div>

        <!-- ================= TAB: DADOS GERAIS ================= -->
        <div v-show="activeTab === 'dados'" class="animate-fade-in">
          <div class="row-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            
            <!-- Column left: Avatar & switches -->
            <div style="display:flex; flex-direction:column; gap:16px; align-items:center; justify-content:center; border-right: 1px solid var(--border); padding-right:16px;">
              <div class="profile-photo-uploader">
                <img
                  v-if="avatarPreview"
                  :src="avatarPreview"
                  class="preview-image"
                  alt="Avatar"
                />
                <div v-else class="preview-initials">
                  {{ getInitials(form.username) }}
                </div>
                
                <label class="photo-upload-label" title="Fazer upload de foto">
                  <i class="bi bi-camera-fill"></i>
                  <input type="file" @change="onPhotoChange" accept="image/*" style="display:none;" />
                </label>
              </div>
              <small class="text-muted" style="font-size: 0.76rem; color: var(--ink-muted);">
                Clique na câmera para alterar (Máx: 2MB, JPG/PNG).
              </small>

              <!-- Switches -->
              <div style="width: 100%; display: flex; flex-direction: column; gap: 10px; margin-top: 12px;">
                <label class="d-flex align-items-center justify-content-between" style="cursor:pointer;">
                  <span style="font-weight: 500; font-size: 0.9rem;">Usuário Ativo</span>
                  <input type="checkbox" v-model="form.active" style="width:18px; height:18px; accent-color:var(--accent);" />
                </label>
                
                <label class="d-flex align-items-center justify-content-between" style="cursor:pointer;">
                  <span style="font-weight: 500; font-size: 0.9rem;">Participa do Rateio</span>
                  <input type="checkbox" v-model="form.rateio_ativo" style="width:18px; height:18px; accent-color:var(--accent);" />
                </label>

                <label class="d-flex align-items-center justify-content-between" style="cursor:pointer;">
                  <span style="font-weight: 500; font-size: 0.9rem;" title="Aparece na busca de responsável das tarefas">Responsável por Tarefas</span>
                  <input type="checkbox" v-model="form.responsavel_tarefas" style="width:18px; height:18px; accent-color:var(--accent);" />
                </label>

                <label class="d-flex align-items-center justify-content-between" style="cursor:pointer;">
                  <span style="font-weight: 500; font-size: 0.9rem;" title="Mostra o usuário na equipe da página Quem Somos do site">Exibir em Quem Somos</span>
                  <input type="checkbox" v-model="form.exibir_site" style="width:18px; height:18px; accent-color:var(--accent);" />
                </label>

                <label class="d-flex align-items-center justify-content-between" style="cursor:pointer;">
                  <span style="font-weight: 500; font-size: 0.9rem;">Advogado</span>
                  <input type="checkbox" v-model="form.is_lawyer" style="width:18px; height:18px; accent-color:var(--accent);" />
                </label>
              </div>
            </div>

            <!-- Column right: Main fields -->
            <div style="display:flex; flex-direction:column; gap:12px;">
              <div class="form-group">
                <label class="form-label required-field">Nome de Usuário</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="form.username"
                  placeholder="Nome de usuário..."
                  ref="usernameInput"
                />
              </div>

              <div class="form-group">
                <label class="form-label required-field">E-mail</label>
                <input
                  type="email"
                  class="form-control"
                  v-model="form.email"
                  placeholder="E-mail principal..."
                />
              </div>

              <!-- Password inputs -->
              <div class="form-group">
                <label class="form-label" :class="{ 'required-field': !isEdit }">
                  Senha {{ isEdit ? '(deixe em branco para manter)' : '' }}
                </label>
                <input
                  type="password"
                  class="form-control"
                  v-model="form.password"
                  placeholder="Mínimo 8 caracteres..."
                />
              </div>

              <div class="form-group">
                <label class="form-label" :class="{ 'required-field': !isEdit }">
                  Confirmar Senha
                </label>
                <input
                  type="password"
                  class="form-control"
                  v-model="form.pass_confirm"
                  placeholder="Repita a senha..."
                />
              </div>

              <!-- OAB Details (Displays only if is_lawyer checked) -->
              <div v-if="form.is_lawyer" class="card" style="padding: 12px; border: 1px dashed var(--accent); background: rgba(var(--accent-rgb), 0.05); display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
                <div class="form-group" style="margin:0;">
                  <label class="form-label required-field" style="font-size:0.8rem;">Número da OAB</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.oab_numero"
                    placeholder="Ex: 123456"
                    style="padding: 6px 10px;"
                  />
                </div>
                <div class="form-group" style="margin:0;">
                  <label class="form-label required-field" style="font-size:0.8rem;">UF</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="form.oab_uf"
                    placeholder="UF"
                    maxlength="2"
                    style="padding: 6px 10px; text-transform: uppercase;"
                  />
                </div>
              </div>
            </div>

          </div>

          <!-- Perfil exibido na página Quem Somos -->
          <div v-if="form.exibir_site" class="card" style="margin-top: 16px; padding: 12px; border: 1px dashed var(--accent);">
            <h4 style="margin: 0 0 4px 0; font-size: 0.95rem;">Perfil em Quem Somos</h4>
            <p style="font-size: 0.78rem; color: var(--ink-muted); margin: 0 0 10px;">
              O usuário também pode editar estes dados em Perfil. A foto acima é usada no site.
            </p>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Cargo</label>
                <input type="text" class="form-control" v-model="form.cargo" maxlength="120" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Ordem na equipe</label>
                <input type="number" min="0" class="form-control" v-model.number="form.site_ordem" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Formação</label>
                <input type="text" class="form-control" v-model="form.formacao" maxlength="255" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Especializações</label>
                <input type="text" class="form-control" v-model="form.especializacoes" maxlength="255" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Áreas de atuação</label>
                <input type="text" class="form-control" v-model="form.areas_atuacao" maxlength="255" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">Currículo Lattes (URL)</label>
                <input type="url" class="form-control" v-model="form.lattes_url" />
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size:0.8rem;">LinkedIn (URL)</label>
                <input type="url" class="form-control" v-model="form.linkedin_url" />
              </div>
            </div>
            <div class="form-group" style="margin:10px 0 0;">
              <label class="form-label" style="font-size:0.8rem;">Minicurrículo</label>
              <textarea class="form-control" rows="3" v-model="form.curriculo"></textarea>
            </div>
          </div>
        </div>

        <!-- ================= TAB: GRUPOS & PERMISSOES ================= -->
        <div v-show="activeTab === 'permissoes'" class="animate-fade-in">
          
          <!-- Groups management -->
          <div class="card" style="margin-bottom: 1.5rem; padding: 16px;">
            <h4 style="margin: 0 0 10px 0; font-size: 1rem; color: var(--ink); border-bottom: 1px solid var(--border); padding-bottom: 6px;">
              Grupos de Acesso (Perfil)
            </h4>
            <p style="font-size: 0.8rem; color: var(--ink-muted); margin-bottom: 12px;">
              Selecione quais grupos de segurança este usuário pertence. As permissões serão herdadas acumulativamente.
            </p>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
              <div
                v-for="(grp, key) in groupsInfo.groups"
                :key="key"
                class="group-checkbox-card"
                :class="{ 'group-checkbox-card-selected': form.groups.includes(key) }"
                @click="toggleGroup(key)"
                style="border: 1px solid var(--border); border-radius: 6px; padding: 10px; cursor: pointer; transition: all 0.15s ease;"
              >
                <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size: 0.9rem; margin-bottom: 4px;">
                  <input
                    type="checkbox"
                    :value="key"
                    v-model="form.groups"
                    @click.stop
                    style="accent-color: var(--accent);"
                  />
                  {{ grp.title || key }}
                </div>
                <div style="font-size:0.75rem; color: var(--ink-muted);">
                  {{ grp.description }}
                </div>
              </div>
            </div>
          </div>

          <!-- Advanced/custom permissions management (Optional extra settings) -->
          <div class="card" style="padding: 16px;">
            <h4 style="margin: 0 0 10px 0; font-size: 1rem; color: var(--ink); border-bottom: 1px solid var(--border); padding-bottom: 6px;">
              Permissões Granulares Específicas
            </h4>
            <p style="font-size: 0.8rem; color: var(--ink-muted); margin-bottom: 12px;">
              Selecione permissões granulares adicionais concedidas especificamente a este usuário, ignorando os grupos dele.
            </p>
            <div style="max-height: 250px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px; padding-right: 4px;">
              <label
                v-for="(desc, perm) in sortedPermissions"
                :key="perm"
                style="display: flex; gap: 8px; align-items: flex-start; padding: 6px 10px; border-radius: 4px; background: var(--surface); cursor: pointer; font-size: 0.82rem;"
              >
                <input
                  type="checkbox"
                  :value="perm"
                  v-model="form.permissions"
                  style="margin-top:2px; accent-color: var(--accent);"
                />
                <div>
                  <strong style="display:block; color: var(--ink);">{{ perm }}</strong>
                  <span style="color: var(--ink-muted); font-size:0.75rem;">{{ desc }}</span>
                </div>
              </label>
            </div>
          </div>

        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit" :disabled="submitting">
          <i class="bi bi-check-circle"></i>
          {{ submitting ? 'Salvando...' : 'Salvar Usuário' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.5.56
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { BASE_URL } from '../../../services/api'

const props = defineProps({
  userData: {
    type: Object,
    default: null
  },
  groupsInfo: {
    type: Object,
    default: () => ({ groups: {}, permissions: {}, matrix: {} })
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

// State
const activeTab = ref('dados')
const submitting = ref(false)
const usernameInput = ref(null)
const avatarFile = ref(null)
const avatarPreview = ref(null)

const isEdit = computed(() => !!props.userData)

const form = reactive({
  id: props.userData?.id || null,
  username: props.userData?.username || '',
  email: props.userData?.email || '',
  password: '',
  pass_confirm: '',
  active: props.userData ? (props.userData.active == 1) : true,
  rateio_ativo: props.userData ? (props.userData.rateio_ativo == 1) : false,
  is_lawyer: props.userData ? (props.userData.is_lawyer == 1) : false,
  responsavel_tarefas: props.userData ? (props.userData.responsavel_tarefas == 1) : true,
  exibir_site: props.userData ? (props.userData.exibir_site == 1) : false,
  site_ordem: Number(props.userData?.site_ordem || 0),
  cargo: props.userData?.cargo || '',
  formacao: props.userData?.formacao || '',
  especializacoes: props.userData?.especializacoes || '',
  areas_atuacao: props.userData?.areas_atuacao || '',
  curriculo: props.userData?.curriculo || '',
  lattes_url: props.userData?.lattes_url || '',
  linkedin_url: props.userData?.linkedin_url || '',
  oab_numero: props.userData?.oab_numero || '',
  oab_uf: props.userData?.oab_uf || '',
  groups: props.userData?.groups ? [...props.userData.groups] : [],
  permissions: props.userData?.permissions ? [...props.userData.permissions] : []
})

// Initialize photo preview if edit
onMounted(() => {
  if (props.userData && props.userData.auth_image) {
    const cacheBuster = props.userData.auth_image ? props.userData.auth_image : Date.now()
    avatarPreview.value = `${BASE_URL}usuarios/exibirFoto/${props.userData.id}?t=${cacheBuster}`
  }

  if (usernameInput.value && !isEdit.value) {
    usernameInput.value.focus()
  }
})

// Sorted list of granular permissions for display
const sortedPermissions = computed(() => {
  if (!props.groupsInfo || !props.groupsInfo.permissions) return {}
  return props.groupsInfo.permissions
})

function getInitials(name) {
  if (!name) return 'U'
  return name.trim().split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

function toggleGroup(groupKey) {
  const index = form.groups.indexOf(groupKey)
  if (index > -1) {
    form.groups.splice(index, 1)
  } else {
    form.groups.push(groupKey)
  }
}

function onPhotoChange(event) {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    emit('warn', 'O arquivo de imagem deve ter menos de 2MB.')
    return
  }

  avatarFile.value = file
  
  // Show local preview
  const reader = new FileReader()
  reader.onload = (e) => {
    avatarPreview.value = e.target.result
  }
  reader.readAsDataURL(file)
}

function onSubmit() {
  if (!form.username || !form.username.trim()) {
    emit('warn', 'O nome de usuário é obrigatório.')
    return
  }
  if (!form.email || !form.email.trim()) {
    emit('warn', 'O e-mail é obrigatório.')
    return
  }

  // Passwords validations
  if (!isEdit.value && !form.password) {
    emit('warn', 'A senha é obrigatória para novos usuários.')
    return
  }

  if (form.password) {
    if (form.password.length < 8) {
      emit('warn', 'A senha deve ter no mínimo 8 caracteres.')
      return
    }
    if (form.password !== form.pass_confirm) {
      emit('warn', 'A confirmação de senha não coincide.')
      return
    }
  }

  // Lawyer validations
  if (form.is_lawyer) {
    if (!form.oab_numero || !form.oab_numero.trim()) {
      emit('warn', 'Para advogados, o número da OAB é obrigatório.')
      return
    }
    if (!form.oab_uf || !form.oab_uf.trim()) {
      emit('warn', 'Para advogados, a UF da OAB é obrigatória.')
      return
    }
  }

  submitting.value = true
  
  // Package formData
  const submissionData = {
    ...form,
    photoFile: avatarFile.value
  }

  emit('submit', submissionData)
}
</script>

<style scoped>
.profile-photo-uploader {
  position: relative;
  width: 110px;
  height: 110px;
  border-radius: 50%;
  border: 3px solid var(--border);
  background-color: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-initials {
  font-size: 2.2rem;
  font-weight: bold;
}

.photo-upload-label {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--primary-contrast, white);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border: 2px solid var(--card);
  transition: transform 0.15s ease;
}

.photo-upload-label:hover {
  transform: scale(1.15);
}

.group-checkbox-card {
  background: var(--surface);
}

.group-checkbox-card-selected {
  border-color: var(--accent) !important;
  background: rgba(var(--accent-rgb), 0.05);
}

.modal-box.modal-lg {
  max-width: 750px;
  width: 90%;
}
</style>
