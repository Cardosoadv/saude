<template>
  <div v-if="hasDriveFolder" class="section-card">
    <div class="section-header">
      <span><i class="bi bi-google"></i> Google Drive</span>
      <div style="display:flex; align-items:center; gap:4px;">
        <button type="button" class="btn btn-ghost btn-sm" title="Recarregar arquivos da pasta" :disabled="loadingDriveFiles" style="padding: 2px 6px; font-size: 0.78rem;" @click="fetchDriveFiles">
          <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
        <a :href="driveUrl" target="_blank" class="btn btn-ghost btn-sm" title="Abrir Pasta no Google Drive" style="padding: 2px 6px; font-size: 0.78rem; text-decoration: none;">
          <i class="bi bi-box-arrow-up-right"></i> Abrir Pasta
        </a>
      </div>
    </div>
    <div class="section-body">
      <div class="drive-container" style="display:flex; flex-direction:column; gap:12px;">
        <div class="upload-zone" 
          style="border: 2px dashed var(--border); border-radius: 8px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.2s;"
          :style="{ borderColor: isDragover ? 'var(--accent)' : 'var(--border)', backgroundColor: isDragover ? 'rgba(0,0,0,0.2)' : 'rgba(0,0,0,0.1)' }"
          @click="triggerFileInput" 
          @dragenter.prevent="isDragover = true"
          @dragleave.prevent="isDragover = false"
          @dragover.prevent
          @drop.prevent="handleFileDrop"
        >
          <input type="file" ref="fileInput" @change="handleFileUpload" style="display:none;" />
          <div v-if="uploading" class="upload-loading" style="display:flex; flex-direction:column; align-items:center; gap:8px; padding: 10px 0;">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="color:var(--accent);"></span>
            <span style="font-size:0.8rem; color:var(--ink);">Enviando arquivo...</span>
          </div>
          <div v-else class="upload-prompt" style="display:flex; flex-direction:column; align-items:center; gap:6px;">
            <i class="bi bi-cloud-arrow-up" style="font-size:1.8rem; color:var(--accent);"></i>
            <span style="font-size:0.8rem; font-weight:500; color:var(--ink);">Clique ou arraste um arquivo aqui</span>
            <span style="font-size:0.7rem; color:var(--ink-muted);">para enviar diretamente</span>
          </div>
        </div>

        <div v-if="leituraLimitada" style="font-size:0.75rem; color:var(--ink-muted); border:1px solid var(--border); border-radius:6px; padding:8px;">
          <i class="bi bi-exclamation-triangle"></i>
          Só aparecem os arquivos enviados pelo sistema. Para listar também os adicionados direto no Google Drive, um administrador precisa reconectar a conta em Configurações &gt; Integração Google.
        </div>

        <!-- Drive Files List -->
        <div v-if="loadingDriveFiles" style="text-align:center; padding: 15px;">
          <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
          <div style="font-size: 0.8rem; color: var(--ink-muted); margin-top: 5px;">Carregando arquivos...</div>
        </div>
        <div v-else-if="driveFiles && driveFiles.length > 0" class="drive-files-list" style="display:flex; flex-direction:column; gap:8px; max-height: 250px; overflow-y: auto; padding-right: 5px;">
          <div v-for="file in driveFiles" :key="file.id" style="display:flex; align-items:center; justify-content:space-between; padding:8px; border:1px solid var(--border); border-radius:6px; background:var(--bg-body);">
            <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
              <img v-if="file.iconLink" :src="file.iconLink" alt="icon" style="width:16px; height:16px;" />
              <i v-else class="bi bi-file-earmark-text" style="font-size:1rem; color:var(--ink-muted);"></i>
              <a :href="file.webViewLink" target="_blank" style="font-size:0.8rem; color:var(--ink); text-decoration:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;" :title="file.name">
                {{ file.name }}
              </a>
            </div>
            <a v-if="file.webContentLink" :href="file.webContentLink" class="btn btn-ghost btn-sm" target="_blank" title="Baixar Arquivo" style="padding:2px 6px; color:var(--primary);">
              <i class="bi bi-download"></i>
            </a>
          </div>
        </div>
        <div v-else style="font-size:0.8rem; color:var(--ink-muted); text-align:center; padding: 10px;">
          Nenhum arquivo na pasta.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processId: { type: Number, default: null },
  driveUrl: { type: String, default: null },
  // Endpoints alternativos (ex.: casos). Sem eles, usa os de processos.
  listEndpoint: { type: String, default: null },
  uploadEndpoint: { type: String, default: null }
})

const urlListagem = computed(() => props.listEndpoint || `processos/${props.processId}/arquivos-drive`)
const urlUpload = computed(() => props.uploadEndpoint || `processos/uploadDrive/${props.processId}`)

const emit = defineEmits(['toast'])

const isDragover = ref(false)
const uploading = ref(false)
const fileInput = ref(null)
const driveFiles = ref([])
const loadingDriveFiles = ref(false)
const leituraLimitada = ref(false)

const hasDriveFolder = computed(() => {
  return props.driveUrl && props.driveUrl.includes('drive.google.com')
})

function triggerFileInput() {
  fileInput.value?.click()
}

async function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  await uploadFile(file)
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function handleFileDrop(event) {
  isDragover.value = false
  const file = event.dataTransfer.files[0]
  if (!file) return
  await uploadFile(file)
}

async function uploadFile(file) {
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file_upload', file)

    const res = await apiFetch(urlUpload.value, {
      method: 'POST',
      body: formData
    })

    if (res && res.success) {
      emit('toast', { msg: 'Arquivo enviado para o Google Drive com sucesso!', type: 'success' })
      fetchDriveFiles()
    } else {
      throw new Error(res.message || 'Erro ao enviar arquivo.')
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao enviar arquivo para o Google Drive.', type: 'danger' })
  } finally {
    uploading.value = false
  }
}

async function fetchDriveFiles() {
  if (!hasDriveFolder.value) return
  
  loadingDriveFiles.value = true
  try {
    const res = await apiFetch(urlListagem.value)
    if (res && res.success) {
      driveFiles.value = res.data || []
      leituraLimitada.value = !!res.leitura_limitada
    }
  } catch (err) {
    console.error('Erro ao carregar arquivos do Drive', err)
  } finally {
    loadingDriveFiles.value = false
  }
}

onMounted(() => {
  if (hasDriveFolder.value) {
    fetchDriveFiles()
  }
})

watch(() => props.driveUrl, (newVal) => {
  if (newVal && newVal.includes('drive.google.com')) {
    fetchDriveFiles()
  }
})
</script>
