<template>
  <div>
    <!-- Resumo + ação -->
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-database" style="color: var(--accent);"></i> Migrations do banco de dados
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0 0 14px 0; line-height: 1.4;">
        Equivale a executar <code>php spark migrate</code> no servidor: aplica, em ordem, as migrations do sistema
        ainda pendentes. Se uma delas falhar, o lote é revertido. Faça um backup antes de executar em produção.
      </p>

      <div v-if="status" style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 16px;">
        <div>
          <div style="font-size: 1.6rem; font-weight: 700; color: var(--ink);">{{ status.aplicadas }}</div>
          <div style="font-size: 0.8rem; color: var(--ink-muted);">Aplicadas</div>
        </div>
        <div>
          <div style="font-size: 1.6rem; font-weight: 700;" :style="{ color: status.pendentes ? 'var(--danger)' : 'var(--ink)' }">
            {{ status.pendentes }}
          </div>
          <div style="font-size: 0.8rem; color: var(--ink-muted);">Pendentes</div>
        </div>
        <div>
          <div style="font-size: 1.6rem; font-weight: 700; color: var(--ink);">{{ status.ultimo_lote }}</div>
          <div style="font-size: 0.8rem; color: var(--ink-muted);">Último lote</div>
        </div>
      </div>

      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-primary" @click="executar" :disabled="executando || loading || !status?.pendentes">
          <i class="bi bi-play-fill"></i>
          {{ executando ? 'Executando...' : 'Executar migrations pendentes' }}
        </button>
        <button class="btn btn-ghost" @click="carregar" :disabled="executando || loading">
          <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
      </div>
    </div>

    <!-- Resultado da última execução -->
    <div
      v-if="resultado"
      class="card animate-fade-in"
      :style="{ marginBottom: '1.5rem', padding: '16px 20px', border: '1px solid ' + (resultado.ok ? 'var(--accent)' : 'var(--danger)') }"
    >
      <strong :style="{ color: resultado.ok ? 'var(--accent)' : 'var(--danger)' }">
        <i :class="resultado.ok ? 'bi bi-check-circle' : 'bi bi-x-circle'"></i> {{ resultado.mensagem }}
      </strong>
      <ul v-if="resultado.executadas.length" style="margin: 10px 0 0 0; padding-left: 18px; font-family: monospace; font-size: 0.82rem;">
        <li v-for="m in resultado.executadas" :key="m">{{ m }}</li>
      </ul>
      <pre
        v-if="resultado.mensagens.length"
        style="margin: 10px 0 0 0; font-size: 0.8rem; white-space: pre-wrap; color: var(--ink-muted);"
      >{{ resultado.mensagens.join('\n') }}</pre>
    </div>

    <!-- Loading -->
    <div v-if="loading && !status" class="process-list">
      <div class="skeleton-card" v-for="i in 3" :key="i">
        <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
        <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
      </div>
    </div>

    <!-- Lista -->
    <div v-else-if="status" class="card animate-fade-in" style="overflow-x: auto;">
      <div style="padding: 12px 16px; display: flex; gap: 8px; align-items: center;">
        <label for="migracoes-filtro" class="form-label" style="margin: 0;">Exibir</label>
        <select id="migracoes-filtro" class="form-control" v-model="filtro" style="width: auto;">
          <option value="todas">Todas ({{ status.migracoes.length }})</option>
          <option value="pendente">Pendentes ({{ status.pendentes }})</option>
          <option value="aplicada">Aplicadas ({{ status.aplicadas }})</option>
        </select>
      </div>

      <table class="table-premium" style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border); color: var(--ink);">
            <th style="padding: 12px 16px;">Versão</th>
            <th style="padding: 12px 16px;">Migration</th>
            <th style="padding: 12px 16px;">Status</th>
            <th style="padding: 12px 16px;">Lote</th>
            <th style="padding: 12px 16px;">Aplicada em</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in visiveis" :key="m.versao + m.classe" style="border-bottom: 1px solid var(--border);">
            <td style="padding: 12px 16px; font-family: monospace; font-size: 0.85rem; color: var(--ink-muted);">{{ m.versao }}</td>
            <td style="padding: 12px 16px; font-weight: 600; color: var(--ink);">{{ m.classe }}</td>
            <td style="padding: 12px 16px;">
              <span class="badge" :class="m.status === 'aplicada' ? 'badge-success' : 'badge-warning'">
                {{ m.status === 'aplicada' ? 'Aplicada' : 'Pendente' }}
              </span>
            </td>
            <td style="padding: 12px 16px; color: var(--ink-muted);">{{ m.lote ?? '-' }}</td>
            <td style="padding: 12px 16px; color: var(--ink-muted); font-size: 0.88rem;">{{ m.aplicada_em ? formatDate(m.aplicada_em, true) : '-' }}</td>
          </tr>
          <tr v-if="!visiveis.length">
            <td colspan="5" style="padding: 20px; text-align: center; color: var(--ink-muted);">Nenhuma migration encontrada.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Painel de migrations do banco: consulta o estado e executa as pendentes via HTTP
 * (proxy do `php spark migrate`).
 * @version 1.0.0
 */
import { ref, computed, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import type { MigracoesStatus, MigracoesResultado } from '../../types/domain'

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type: string }): void
}>()

// Migrations longas podem passar do timeout padrão de 30s do apiFetch.
const EXECUTAR_TIMEOUT_MS = 5 * 60 * 1000

const status = ref<MigracoesStatus | null>(null)
const loading = ref(false)
const executando = ref(false)
const filtro = ref<'todas' | 'pendente' | 'aplicada'>('pendente')
const resultado = ref<{ ok: boolean; mensagem: string; executadas: string[]; mensagens: string[] } | null>(null)

const visiveis = computed(() => {
  const lista = status.value?.migracoes ?? []
  const filtradas = filtro.value === 'todas' ? lista : lista.filter(m => m.status === filtro.value)
  // Mais recentes primeiro
  return [...filtradas].reverse()
})

async function carregar() {
  loading.value = true
  try {
    const res = await apiFetch('configuracoes/migracoes')
    if (res?.success) {
      status.value = res.data as MigracoesStatus
      if (filtro.value === 'pendente' && !status.value.pendentes) filtro.value = 'todas'
    } else {
      emit('toast', { msg: res?.message || 'Erro ao carregar migrations.', type: 'error' })
    }
  } catch (err: any) {
    emit('toast', { msg: err?.message || 'Erro ao carregar migrations do backend.', type: 'error' })
  } finally {
    loading.value = false
  }
}

async function executar() {
  const n = status.value?.pendentes ?? 0
  if (!confirm(`Executar ${n} migration(s) pendente(s) agora? Esta ação altera a estrutura do banco de dados.`)) return

  executando.value = true
  resultado.value = null
  try {
    const res = await apiFetch<{ success: boolean; message: string; data?: MigracoesResultado }>(
      'configuracoes/migracoes/executar',
      { method: 'POST', timeout: EXECUTAR_TIMEOUT_MS }
    )
    resultado.value = {
      ok: !!res?.success,
      mensagem: res?.message || '',
      executadas: res?.data?.executadas ?? [],
      mensagens: res?.data?.mensagens ?? []
    }
    emit('toast', { msg: resultado.value.mensagem, type: res?.success ? 'success' : 'error' })
  } catch (err: any) {
    // Erros HTTP (ex.: 409 execução simultânea, 500 falha) trazem o JSON em err.data
    const data = err?.data
    resultado.value = {
      ok: false,
      mensagem: err?.message || 'Erro ao executar migrations.',
      executadas: data?.data?.executadas ?? [],
      mensagens: data?.data?.mensagens ?? []
    }
    emit('toast', { msg: resultado.value.mensagem, type: 'error' })
  } finally {
    executando.value = false
    await carregar()
  }
}

onMounted(carregar)
</script>
