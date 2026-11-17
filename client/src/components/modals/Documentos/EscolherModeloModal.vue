<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 520px;">
      <div class="modal-header">
        <h2><i class="bi bi-file-earmark-plus"></i> Usar Modelo</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="empty-state" style="padding: 24px 0;">
          <div class="spinner"></div>
        </div>

        <template v-else>
          <!-- Modo A: entidade já conhecida (aberto a partir de um Processo/Caso) -->
          <template v-if="modoPorEntidade">
            <div class="form-group">
              <label class="form-label"><i class="bi bi-search"></i> Buscar modelo</label>
              <input type="text" class="form-control" v-model="busca" placeholder="Título do modelo...">
            </div>

            <div v-if="!modelosFiltrados.length" class="empty-state" style="padding: 20px 0;">
              <i class="bi bi-file-earmark-ruled" style="font-size:2rem;color:var(--accent);"></i>
              <p class="text-muted small mb-2">
                Nenhum modelo cadastrado para {{ entidadeTipo === 'processo' ? 'Processos' : 'Casos' }}.
              </p>
              <a href="#" @click.prevent="$emit('close'); $emit('set-view', 'modelos')">Criar um modelo</a>
            </div>

            <ul v-else class="modelo-list">
              <li
                v-for="m in modelosFiltrados" :key="m.id"
                class="modelo-item" :class="{ active: modeloSelecionadoId === m.id }"
                @click="modeloSelecionadoId = m.id"
              >
                <div>
                  <strong>{{ m.titulo }}</strong>
                  <div class="text-muted small">{{ m.ramo_nome || 'Geral' }}</div>
                </div>
                <i v-if="modeloSelecionadoId === m.id" class="bi bi-check-circle-fill" style="color: var(--accent);"></i>
                <i v-else class="bi bi-circle" style="color:var(--border);"></i>
              </li>
            </ul>
          </template>

          <!-- Modo B: modelo já conhecido (aberto a partir do Gerenciador de Modelos) -->
          <template v-else>
            <p class="text-muted small mb-3">Modelo: <strong>{{ modelo?.titulo }}</strong></p>

            <div class="aba-tipo">
              <button
                type="button" class="aba-btn" :class="{ active: abaEntidade === 'processo' }"
                :disabled="!abaHabilitada('processo')" @click="selecionarAba('processo')"
              ><i class="bi bi-folder-symlink"></i> Processo</button>
              <button
                type="button" class="aba-btn" :class="{ active: abaEntidade === 'caso' }"
                :disabled="!abaHabilitada('caso')" @click="selecionarAba('caso')"
              ><i class="bi bi-briefcase"></i> Caso</button>
            </div>

            <div class="form-group position-relative" style="margin-top: 12px;">
              <label class="form-label">Buscar {{ abaEntidade === 'processo' ? 'processo (CNJ ou título)' : 'caso' }}</label>
              <input
                id="entidade-busca-input"
                type="text" class="form-control" v-model="entidadeBusca"
                placeholder="Digite ao menos 2 caracteres..."
                autocomplete="off" @input="onEntidadeInput" @focus="onEntidadeFocus"
              >
              <div v-if="showDropdown" class="autocomplete-results shadow-lg">
                <div v-if="isLoadingBusca" class="autocomplete-loading"><span class="spinner-small"></span> Buscando...</div>
                <div v-else-if="!resultadosBusca.length" class="autocomplete-no-results">Nenhum resultado encontrado</div>
                <ul v-else class="autocomplete-list">
                  <li v-for="r in resultadosBusca" :key="r.id" class="autocomplete-item" @click="selecionarEntidade(r)">
                    {{ r.text }}
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="entidadeSelecionada" class="entidade-selecionada">
              <i class="bi bi-check-circle-fill" style="color: var(--accent);"></i> {{ entidadeSelecionada.text }}
            </div>
          </template>
        </template>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" :disabled="!podeConfirmar || saving" @click="confirmar">
          <i class="bi bi-check-circle"></i> {{ saving ? 'Gerando...' : 'Usar modelo' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch } from '../../../services/api'
import { useSearchAutocomplete } from '../../../composables/useSearchAutocomplete'

const props = defineProps({
  // Modo A: entidade já conhecida
  entidadeTipo: { type: String, default: null }, // 'processo' | 'caso'
  entidadeId: { type: [Number, String], default: null },
  // Modo B: modelo já conhecido
  modeloId: { type: [Number, String], default: null },
  saving: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'submit', 'set-view'])

const modoPorEntidade = computed(() => !!(props.entidadeTipo && props.entidadeId))

const loading = ref(true)

// --- Modo A: lista de modelos compatíveis ---
const modelos = ref([])
const busca = ref('')
const modeloSelecionadoId = ref(null)

const modelosFiltrados = computed(() => {
  const termo = busca.value.trim().toLowerCase()
  return modelos.value.filter(m => !termo || m.titulo.toLowerCase().includes(termo))
})

async function carregarModelosCompativeis() {
  try {
    const res = await apiFetch('conhecimento/modelos?status=Publicado&ativo=1&per_page=200')
    const lista = res?.data || []
    modelos.value = lista.filter(m => {
      const tipoAlvo = m.tipo_alvo || 'ambos'
      return tipoAlvo === 'ambos' || tipoAlvo === props.entidadeTipo
    })
  } catch (err) {
    console.error('Erro ao carregar modelos:', err)
  }
}

// --- Modo B: modelo conhecido, escolher processo ou caso ---
const modelo = ref(null)
const abaEntidade = ref('processo')
const entidadeBusca = ref('')
const entidadeSelecionada = ref(null)

const {
  results: resultadosBusca,
  isLoading: isLoadingBusca,
  showDropdown,
  onInput: buscarEntidade,
  clear: limparBusca
} = useSearchAutocomplete({
  searchEndpoint: (q) => (abaEntidade.value === 'processo' ? 'processos/buscar' : 'casos/buscar') + '?term=' + encodeURIComponent(q)
})

function abaHabilitada(tipo) {
  const tipoAlvo = modelo.value?.tipo_alvo || 'ambos'
  return tipoAlvo === 'ambos' || tipoAlvo === tipo
}

function selecionarAba(tipo) {
  if (abaEntidade.value === tipo) return
  abaEntidade.value = tipo
  entidadeBusca.value = ''
  entidadeSelecionada.value = null
  limparBusca()
}

function onEntidadeInput() {
  entidadeSelecionada.value = null
  buscarEntidade(entidadeBusca.value)
}

function onEntidadeFocus() {
  if (entidadeBusca.value.trim().length >= 2) showDropdown.value = true
}

function selecionarEntidade(r) {
  entidadeSelecionada.value = r
  entidadeBusca.value = r.text
  showDropdown.value = false
}

const clickOutsideHandler = (event) => {
  const dropdown = document.querySelector('.autocomplete-results')
  const input = document.querySelector('#entidade-busca-input')
  if (dropdown && !dropdown.contains(event.target) && event.target !== input) {
    showDropdown.value = false
  }
}

async function carregarModelo() {
  try {
    modelo.value = await apiFetch(`conhecimento/modelos/${props.modeloId}`)
    const tipoAlvo = modelo.value?.tipo_alvo || 'ambos'
    abaEntidade.value = tipoAlvo === 'caso' ? 'caso' : 'processo'
  } catch (err) {
    console.error('Erro ao carregar modelo:', err)
  }
}

const podeConfirmar = computed(() => {
  if (modoPorEntidade.value) return !!modeloSelecionadoId.value
  return !!entidadeSelecionada.value
})

function confirmar() {
  if (!podeConfirmar.value) return
  const payload = modoPorEntidade.value
    ? { modelo_id: modeloSelecionadoId.value, entidade_tipo: props.entidadeTipo, entidade_id: Number(props.entidadeId) }
    : { modelo_id: Number(props.modeloId), entidade_tipo: abaEntidade.value, entidade_id: Number(entidadeSelecionada.value.id) }
  emit('submit', payload)
}

watch(() => props.entidadeTipo, () => {
  if (modoPorEntidade.value) carregarModelosCompativeis()
})

onMounted(async () => {
  loading.value = true
  document.addEventListener('click', clickOutsideHandler)
  if (modoPorEntidade.value) {
    await carregarModelosCompativeis()
  } else if (props.modeloId) {
    await carregarModelo()
  }
  loading.value = false
})

onBeforeUnmount(() => {
  document.removeEventListener('click', clickOutsideHandler)
})
</script>

<style scoped>
.modelo-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 320px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.modelo-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all .12s;
}

.modelo-item:hover { border-color: var(--accent); }
.modelo-item.active { border-color: var(--accent); background: rgba(212, 175, 55, .08); }

.aba-tipo {
  display: flex;
  gap: 6px;
}

.aba-btn {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: transparent;
  color: var(--ink-dim);
  cursor: pointer;
  font-size: .85rem;
  font-weight: 600;
  transition: all .15s;
}

.aba-btn.active {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(212, 175, 55, .08);
}

.aba-btn:disabled {
  opacity: .4;
  cursor: not-allowed;
}

.position-relative { position: relative; }

.autocomplete-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1100;
  background: rgba(18, 30, 49, 0.95);
  backdrop-filter: blur(10px);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-top: 4px;
  max-height: 200px;
  overflow-y: auto;
}

.autocomplete-loading,
.autocomplete-no-results {
  padding: 12px;
  font-size: 0.82rem;
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  gap: 8px;
}

.spinner-small {
  width: 12px;
  height: 12px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes spin { to { transform: rotate(360deg); } }

.autocomplete-list { list-style: none; padding: 0; margin: 0; }

.autocomplete-item {
  padding: 10px 12px;
  font-size: 0.83rem;
  color: var(--ink-dim);
  cursor: pointer;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover { background: rgba(212, 175, 55, 0.1); color: var(--accent); }

.entidade-selecionada {
  margin-top: 10px;
  font-size: .85rem;
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ink);
}
</style>
