<template>
  <div class="copiloto-acao" :class="`estado-${estado}`" role="group" :aria-label="`Ação proposta: ${acao.resumo}`">
    <div class="copiloto-acao-titulo">
      <i class="bi bi-pencil-square" aria-hidden="true"></i>
      <strong>{{ acao.resumo }}</strong>
    </div>

    <dl v-if="acao.campos.length" class="copiloto-acao-campos">
      <template v-for="campo in acao.campos" :key="campo.campo">
        <dt>{{ campo.campo }}</dt>
        <dd>{{ campo.valor }}</dd>
      </template>
    </dl>

    <p v-if="estado === 'pendente'" class="copiloto-acao-aviso">
      Nada foi gravado ainda. Confira e confirme até {{ horaExpiracao }}.
    </p>

    <div v-if="estado === 'pendente' || estado === 'confirmando'" class="copiloto-acao-botoes">
      <button type="button" class="btn btn-primary btn-sm" :disabled="estado !== 'pendente'" @click="$emit('confirmar')">
        <span v-if="estado === 'confirmando'" class="spinner-inline" aria-hidden="true"></span>
        {{ estado === 'confirmando' ? 'Gravando...' : 'Confirmar' }}
      </button>
      <button type="button" class="btn btn-ghost btn-sm" :disabled="estado !== 'pendente'" @click="$emit('recusar')">Recusar</button>
    </div>

    <p v-else class="copiloto-acao-status" role="status">
      <template v-if="estado === 'confirmada'"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ resultado || 'Gravado.' }}</template>
      <template v-else-if="estado === 'recusada'"><i class="bi bi-x-circle" aria-hidden="true"></i> Ação recusada. Nada foi gravado.</template>
      <template v-else-if="estado === 'expirada'"><i class="bi bi-clock-history" aria-hidden="true"></i> Esta ação expirou. Peça ao Copiloto para prepará-la de novo.</template>
      <template v-else><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ resultado || 'A ação não foi concluída.' }}</template>
    </p>
  </div>
</template>

<script setup lang="ts">
/**
 * Cartão de confirmação de uma gravação proposta pelo Copiloto. O botão desabilita após o clique para
 * impedir confirmação dupla; recusar não chama a API.
 */
import { computed, type DeepReadonly } from 'vue'
import type { AcaoPendenteCopiloto } from '../../services/copilotoApi'
import type { EstadoAcao } from '../../composables/useCopiloto'

const props = defineProps<{
  acao: DeepReadonly<AcaoPendenteCopiloto>
  estado: EstadoAcao
  resultado?: string
}>()

defineEmits<{ confirmar: []; recusar: [] }>()

const horaExpiracao = computed(() => {
  const data = new Date(props.acao.expira_em)
  return Number.isNaN(data.getTime())
    ? 'expirar'
    : data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
})
</script>

<style scoped>
.copiloto-acao {
  border: 1px solid var(--accent);
  background: #fffaf0;
  border-radius: 10px;
  padding: 10px 12px;
  margin-top: 8px;
  font-size: .85rem;
}
.copiloto-acao.estado-confirmada { border-color: var(--success); background: #f0fdf4; }
.copiloto-acao.estado-recusada,
.copiloto-acao.estado-expirada { border-color: var(--border); background: var(--surface); }
.copiloto-acao.estado-falhou { border-color: var(--danger); background: #fef2f2; }
.copiloto-acao-titulo { display: flex; gap: 6px; align-items: center; color: var(--ink); }
.copiloto-acao-campos {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 2px 10px;
  margin: 8px 0 0;
}
.copiloto-acao-campos dt { color: var(--ink-muted); }
.copiloto-acao-campos dd { margin: 0; color: var(--ink); overflow-wrap: anywhere; }
.copiloto-acao-aviso { margin: 8px 0 0; color: var(--ink-muted); font-size: .8rem; }
.copiloto-acao-botoes { display: flex; gap: 8px; margin-top: 8px; }
.copiloto-acao-status { margin: 8px 0 0; display: flex; gap: 6px; align-items: flex-start; }
.spinner-inline {
  display: inline-block; width: 10px; height: 10px; margin-right: 4px;
  border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%;
  animation: girar .8s linear infinite;
}
@keyframes girar { to { transform: rotate(360deg); } }
</style>
