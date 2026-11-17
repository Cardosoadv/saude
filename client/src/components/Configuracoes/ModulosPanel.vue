<template>
  <div>
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-grid-3x3-gap" style="color: var(--accent);"></i> Módulos do sistema
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
        Um módulo desativado some do menu e dos detalhes (cards e abas) para todos os usuários, e a API
        passa a recusar as requisições dele. Os dados não são apagados: ao reativar, tudo volta.
      </p>
      <button class="btn btn-ghost" @click="carregar" :disabled="loading">
        <i class="bi bi-arrow-clockwise"></i> Atualizar
      </button>
    </div>

    <div v-if="loading && !modulos.length" class="process-list">
      <div class="skeleton-card" v-for="i in 3" :key="i">
        <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
        <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
      </div>
    </div>

    <div v-else class="card animate-fade-in" style="overflow-x: auto;">
      <table class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
            <th style="padding: 12px 16px;">Módulo</th>
            <th style="padding: 12px 16px;">Identificador</th>
            <th style="padding: 12px 16px;">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in modulos" :key="m.id" style="border-bottom: 1px solid var(--border);">
            <td style="padding: 12px 16px; font-weight: 600; color: var(--ink);">{{ m.nome }}</td>
            <td style="padding: 12px 16px; font-family: monospace; font-size: 0.85rem; color: var(--ink-muted);">{{ m.slug }}</td>
            <td style="padding: 12px 16px;">
              <div class="form-switch">
                <input
                  type="checkbox"
                  :id="'modulo-' + m.slug"
                  class="switch-input"
                  :checked="m.ativo"
                  :disabled="salvando === m.slug || (m.ativo && essencial(m))"
                  :title="m.ativo && essencial(m) ? 'Módulo essencial: não pode ser desativado.' : ''"
                  @change="alternar(m)"
                />
                <label :for="'modulo-' + m.slug" class="switch-label">
                  {{ m.ativo ? 'Ativo' : 'Inativo' }}
                </label>
              </div>
            </td>
          </tr>
          <tr v-if="!modulos.length">
            <td colspan="3" style="padding: 20px; text-align: center; color: var(--ink-muted);">Nenhum módulo encontrado.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Painel de módulos: lista os módulos do sistema (GET api/v2/modulos) e
 * ativa/desativa (POST api/v2/modulos/toggleStatus).
 * @version 1.0.0
 */
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../services/api'
import { recarregarAcesso } from '../../composables/useAcesso'
import { alternarModulo, moduloEssencial, type Modulo, type RespostaToggle } from '../../utils/alternarModulo'

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type: string }): void
}>()

const modulos = ref<Modulo[]>([])
const loading = ref(false)
/** Slug do módulo sendo gravado (evita cliques repetidos). */
const salvando = ref<string | null>(null)

const essencial = moduloEssencial

async function alternar(m: Modulo) {
  if (!m.slug || salvando.value) return
  salvando.value = m.slug
  const { ok, mensagem } = await alternarModulo(m, (slug, ativo) =>
    apiFetch<RespostaToggle>('modulos/toggleStatus', { method: 'POST', body: { slug, ativo } }),
  )
  // O menu e as telas de quem alterou já refletem a mudança.
  if (ok) await recarregarAcesso()
  salvando.value = null
  emit('toast', { msg: mensagem, type: ok ? 'success' : 'error' })
}

async function carregar() {
  loading.value = true
  try {
    const res = await apiFetch<{ success: boolean; data?: Modulo[]; message?: string }>('modulos')
    if (res?.success) {
      modulos.value = res.data ?? []
    } else {
      emit('toast', { msg: res?.message || 'Erro ao carregar módulos.', type: 'error' })
    }
  } catch (err: any) {
    emit('toast', { msg: err?.message || 'Erro ao carregar módulos do backend.', type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.form-switch {
  display: flex;
  align-items: center;
  gap: 10px;
}

.switch-input {
  width: 44px;
  height: 22px;
  appearance: none;
  background-color: var(--border);
  border-radius: 20px;
  position: relative;
  outline: none;
  cursor: pointer;
  transition: background-color 0.2s;
}

.switch-input:checked {
  background-color: var(--accent);
}

.switch-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.switch-input::before {
  content: '';
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background-color: white;
  position: absolute;
  top: 2px;
  left: 2px;
  transition: transform 0.2s;
}

.switch-input:checked::before {
  transform: translateX(22px);
}

.switch-input:focus-visible {
  box-shadow: 0 0 0 3px var(--accent-light, rgba(0, 0, 0, 0.15));
}

.switch-label {
  font-size: 0.85rem;
  color: var(--ink-muted);
}
</style>
