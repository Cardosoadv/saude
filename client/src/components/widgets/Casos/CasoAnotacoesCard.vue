<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-journal-text"></i> Anotações</span>
      <button v-if="!formAberto" class="btn btn-ghost btn-sm" @click="abrirNova" style="padding:2px 8px; font-size:.78rem;">
        <i class="bi bi-plus-circle"></i> Nova
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <!-- Formulário inline (nova / edição) -->
      <form v-if="formAberto" class="anotacao-form" @submit.prevent="salvar">
        <input
          v-model="form.titulo"
          type="text"
          class="form-control form-control-sm"
          maxlength="150"
          placeholder="Título (opcional)"
          aria-label="Título da anotação"
        />
        <textarea
          ref="textoRef"
          v-model="form.anotacao"
          class="form-control form-control-sm"
          rows="4"
          placeholder="Escreva a anotação..."
          aria-label="Texto da anotação"
          required
        ></textarea>
        <div class="anotacao-form-footer">
          <label class="privacidade-toggle" :title="form.privacidade === 3 ? 'Visível para quem acessa o caso' : 'Só você vê'">
            <input type="checkbox" :checked="form.privacidade === 3" @change="form.privacidade = ($event.target as HTMLInputElement).checked ? 3 : 1" />
            <i :class="form.privacidade === 3 ? 'bi bi-unlock' : 'bi bi-lock'"></i>
            {{ form.privacidade === 3 ? 'Pública' : 'Privada' }}
          </label>
          <div style="display:flex; gap:6px;">
            <button type="button" class="btn btn-ghost btn-sm" @click="fechar" :disabled="salvando">Cancelar</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="salvando || !form.anotacao.trim()">
              <i class="bi bi-save"></i> {{ salvando ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </div>
      </form>

      <div v-if="carregando" style="text-align:center; padding:10px;">
        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
      </div>
      <div v-else-if="!anotacoes.length && !formAberto" style="font-size:.8rem;color:var(--ink-muted);padding:10px 0;text-align:center;">
        Nenhuma anotação neste caso.
      </div>

      <div v-for="n in anotacoesPagina" :key="n.id_anotacao" class="anotacao-item">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:4px;">
          <strong style="font-size:.82rem; color:var(--ink);">{{ n.titulo || 'Sem título' }}</strong>
          <span class="badge" :class="Number(n.privacidade) === 1 ? 'badge-warning' : 'badge-success'">
            {{ Number(n.privacidade) === 1 ? 'Privado' : 'Público' }}
          </span>
        </div>
        <p style="font-size:.8rem; color:var(--ink-dim); line-height:1.4; white-space:pre-line; margin:0;">{{ n.anotacao }}</p>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:6px;">
          <small style="font-size:.7rem; color:var(--ink-muted);">
            {{ n.autor_nome || 'Usuário' }} · {{ formatDate(n.created_at, true) }}
          </small>
          <div v-if="n.propria" style="display:flex; gap:4px;">
            <button class="btn btn-ghost btn-sm" @click="editar(n)" style="border:none; padding:2px 8px; color:var(--primary); font-size:.75rem;">
              <i class="bi bi-pencil"></i> Editar
            </button>
            <button class="btn btn-ghost btn-sm" @click="remover(n)" style="border:none; padding:2px 8px; color:var(--danger); font-size:.75rem;">
              <i class="bi bi-trash"></i> Excluir
            </button>
          </div>
        </div>
      </div>

      <div v-if="totalPaginas > 1" class="pagination" role="navigation" aria-label="Paginação das anotações" style="margin-top:10px; justify-content:center;">
        <button class="page-btn" :disabled="pagina === 1" @click="pagina--" aria-label="Página anterior">
          <i class="bi bi-chevron-left"></i>
        </button>
        <span style="font-size:.78rem; color:var(--ink-muted); padding:0 6px;">{{ pagina }} / {{ totalPaginas }}</span>
        <button class="page-btn" :disabled="pagina === totalPaginas" @click="pagina++" aria-label="Próxima página">
          <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../../services/api'

interface AnotacaoCaso {
  id_anotacao: number
  caso_id: number
  user_id: number
  titulo: string | null
  anotacao: string
  privacidade: number | string
  autor_nome?: string
  propria?: boolean
  created_at: string
}

const props = defineProps({
  casoId: { type: Number, required: true }
})

const emit = defineEmits(['toast', 'changed'])

const anotacoes = ref<AnotacaoCaso[]>([])
const carregando = ref(false)
const salvando = ref(false)
const formAberto = ref(false)
const textoRef = ref<HTMLTextAreaElement | null>(null)
const form = reactive({ id_anotacao: null as number | null, titulo: '', anotacao: '', privacidade: 1 })

const POR_PAGINA = 5
const pagina = ref(1)
const totalPaginas = computed(() => Math.max(1, Math.ceil(anotacoes.value.length / POR_PAGINA)))
const anotacoesPagina = computed(() => {
  const inicio = (pagina.value - 1) * POR_PAGINA
  return anotacoes.value.slice(inicio, inicio + POR_PAGINA)
})

watch(totalPaginas, (total) => {
  if (pagina.value > total) pagina.value = total
})

async function carregar() {
  carregando.value = true
  try {
    const res = await apiFetch(`casos/${props.casoId}/anotacoes`)
    anotacoes.value = res?.success ? (res.data || []) : []
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao carregar anotações.', type: 'danger' })
  } finally {
    carregando.value = false
  }
}

function abrirNova() {
  Object.assign(form, { id_anotacao: null, titulo: '', anotacao: '', privacidade: 1 })
  formAberto.value = true
  nextTick(() => textoRef.value?.focus())
}

function editar(n: AnotacaoCaso) {
  Object.assign(form, {
    id_anotacao: n.id_anotacao,
    titulo: n.titulo || '',
    anotacao: n.anotacao,
    privacidade: Number(n.privacidade) === 3 ? 3 : 1
  })
  formAberto.value = true
  nextTick(() => textoRef.value?.focus())
}

function fechar() {
  formAberto.value = false
}

async function salvar() {
  if (!form.anotacao.trim()) return
  salvando.value = true
  try {
    const res = await apiFetch(`casos/${props.casoId}/anotacoes`, {
      method: 'POST',
      body: { ...form }
    })
    if (!res?.success) throw new Error(res?.message || 'Erro ao salvar anotação.')
    emit('toast', { msg: res.message || 'Anotação salva.', type: 'success' })
    formAberto.value = false
    await carregar()
    emit('changed')
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao salvar anotação.', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

async function remover(n: AnotacaoCaso) {
  if (!confirm('Confirma a exclusão desta anotação?')) return
  try {
    const res = await apiFetch(`casos/${props.casoId}/anotacoes/${n.id_anotacao}`, { method: 'DELETE' })
    if (!res?.success) throw new Error(res?.message || 'Erro ao excluir anotação.')
    emit('toast', { msg: 'Anotação excluída.', type: 'success' })
    await carregar()
    emit('changed')
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao excluir anotação.', type: 'danger' })
  }
}

watch(() => props.casoId, () => {
  pagina.value = 1
  formAberto.value = false
  carregar()
})

onMounted(carregar)

defineExpose({ carregar })
</script>

<style scoped>
.anotacao-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--border);
}
.anotacao-form-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.privacidade-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: .78rem;
  color: var(--ink-dim);
  cursor: pointer;
}
.anotacao-item {
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}
.anotacao-item:last-of-type {
  border-bottom: none;
}
</style>
