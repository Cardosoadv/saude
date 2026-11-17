<template>
  <div class="section-card" v-if="acessos">
    <div class="section-header">
      <span>
        <i :class="['bi', acessos.visibilidade === 'restrito' ? 'bi-lock-fill' : 'bi-globe2']"></i>
        Acesso
      </span>
      <span :class="['badge', acessos.visibilidade === 'restrito' ? 'badge-muted' : 'badge-success']">
        {{ acessos.visibilidade === 'restrito' ? 'Restrito' : 'Público' }}
      </span>
    </div>

    <div class="section-body acessos-body">
      <div v-if="acessos.pode_gerenciar" class="visibilidade-toggle" role="group" aria-label="Visibilidade">
        <button
          v-for="opcao in opcoesVisibilidade"
          :key="opcao.valor"
          type="button"
          :class="['btn', 'btn-sm', acessos.visibilidade === opcao.valor ? 'btn-primary' : 'btn-outline-secondary']"
          :aria-pressed="acessos.visibilidade === opcao.valor"
          :disabled="salvando"
          @click="definirVisibilidade(opcao.valor)"
        >
          <i :class="['bi', opcao.icone]"></i> {{ opcao.rotulo }}
        </button>
      </div>

      <p class="acessos-ajuda">
        <template v-if="acessos.visibilidade === 'restrito'">
          Somente o responsável, os advogados abaixo e os administradores veem e editam.
        </template>
        <template v-else>
          Todos os advogados do escritório veem e editam.
        </template>
      </p>

      <ul class="acessos-lista">
        <li v-if="acessos.responsavel">
          <span class="acessos-avatar">{{ inicial(acessos.responsavel.username) }}</span>
          <span class="acessos-nome">{{ acessos.responsavel.username }}</span>
          <span class="acessos-papel">Responsável</span>
        </li>
        <li v-for="u in acessos.usuarios" :key="u.user_id">
          <span class="acessos-avatar">{{ inicial(u.username) }}</span>
          <span class="acessos-nome" :title="u.concedido_por_nome ? 'Liberado por ' + u.concedido_por_nome : ''">{{ u.username }}</span>
          <button
            v-if="acessos.pode_gerenciar"
            type="button"
            class="btn btn-ghost btn-sm acessos-remover"
            :disabled="salvando"
            :aria-label="'Remover acesso de ' + u.username"
            title="Remover acesso"
            @click="revogar(u)"
          >
            <i class="bi bi-x-lg"></i>
          </button>
        </li>
        <li v-if="!acessos.responsavel && !acessos.usuarios.length" class="acessos-vazio">
          Nenhum advogado com acesso além dos administradores.
        </li>
      </ul>

      <form v-if="acessos.pode_gerenciar" class="acessos-adicionar" @submit.prevent="conceder">
        <label class="sr-only" :for="'acesso-novo-' + tipo + '-' + entidadeId">Liberar acesso para</label>
        <select :id="'acesso-novo-' + tipo + '-' + entidadeId" v-model="novoUsuario" class="form-control" :disabled="salvando">
          <option value="">Liberar acesso para...</option>
          <option v-for="adv in advogadosDisponiveis" :key="adv.id" :value="adv.id">{{ adv.username }}</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="!novoUsuario || salvando">
          <i class="bi bi-person-plus"></i> Adicionar
        </button>
      </form>

      <div v-if="tipo === 'cliente' && acessos.pode_gerenciar" class="acessos-propagar">
        <label class="acessos-propagar-opcao">
          <input type="checkbox" v-model="copiarVisibilidade" :disabled="salvando" />
          Copiar também a visibilidade
        </label>
        <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="salvando" @click="propagar">
          <i class="bi bi-diagram-3"></i> Aplicar aos processos e contratos
        </button>
        <p class="acessos-ajuda">
          Libera o responsável e os advogados acima em todos os processos e contratos deste cliente.
          É uma cópia feita agora: mudanças futuras no cliente não se propagam sozinhas.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Privacidade granular: visibilidade e advogados com acesso a um processo,
 * cliente ou contrato. Só quem pode gerenciar (responsável, admin ou
 * privacidade.manage) vê os controles de edição.
 */
import { computed, onMounted, ref, watch, type PropType } from 'vue'
import { apiFetch } from '../../../services/api'
import { useAdvogados } from '../../../composables/useAdvogados'
import type { AcessosEntidade, AcessoUsuario, Id, TipoEntidadeAcesso, Visibilidade } from '../../../types/domain'

const props = defineProps({
  tipo: { type: String as PropType<TipoEntidadeAcesso>, required: true },
  entidadeId: { type: Number, required: true },
  /** Recarrega o card quando o responsável muda em outro componente. */
  responsavelId: { type: [String, Number], default: null }
})

const emit = defineEmits(['toast', 'changed', 'loaded'])

const opcoesVisibilidade: { valor: Visibilidade; rotulo: string; icone: string }[] = [
  { valor: 'restrito', rotulo: 'Restrito', icone: 'bi-lock' },
  { valor: 'publico', rotulo: 'Público', icone: 'bi-globe2' }
]

const acessos = ref<AcessosEntidade | null>(null)
const { advogados } = useAdvogados()
const novoUsuario = ref<Id | ''>('')
const salvando = ref(false)
const copiarVisibilidade = ref(false)

const base = computed(() => `acessos/${props.tipo}/${props.entidadeId}`)

const advogadosDisponiveis = computed(() => {
  if (!acessos.value) return []
  const ocupados = new Set(acessos.value.usuarios.map(u => String(u.user_id)))
  if (acessos.value.responsavel) ocupados.add(String(acessos.value.responsavel.user_id))
  return advogados.value.filter(a => !ocupados.has(String(a.id)))
})

function inicial(nome?: string | null) {
  return (nome || '?').charAt(0).toUpperCase()
}

async function carregar() {
  try {
    const res = await apiFetch(base.value)
    acessos.value = res?.success ? res.data : null
    emit('loaded', acessos.value)
  } catch {
    // Sem acesso ao registro ou à funcionalidade: o card simplesmente não aparece.
    acessos.value = null
    emit('loaded', null)
  }
}

async function executar(acao: () => Promise<any>, sucesso: string) {
  salvando.value = true
  try {
    const res = await acao()
    if (!res?.success) throw new Error(res?.message)
    if (res.data?.visibilidade) {
      acessos.value = { ...res.data, pode_gerenciar: acessos.value?.pode_gerenciar ?? false }
      emit('loaded', acessos.value)
    }
    emit('toast', { msg: res.message || sucesso, type: 'success' })
    emit('changed')
  } catch (err) {
    emit('toast', { msg: err?.message || 'Erro ao atualizar os acessos.', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

function definirVisibilidade(visibilidade: Visibilidade) {
  if (acessos.value?.visibilidade === visibilidade) return
  return executar(
    () => apiFetch(`${base.value}/visibilidade`, { method: 'POST', body: { visibilidade } }),
    'Visibilidade atualizada.'
  )
}

async function conceder() {
  if (!novoUsuario.value) return
  await executar(
    () => apiFetch(`${base.value}/usuarios`, { method: 'POST', body: { user_id: novoUsuario.value } }),
    'Acesso concedido.'
  )
  novoUsuario.value = ''
}

function revogar(usuario: AcessoUsuario) {
  if (!confirm(`Remover o acesso de ${usuario.username}?`)) return
  return executar(
    () => apiFetch(`${base.value}/usuarios/${usuario.user_id}`, { method: 'DELETE' }),
    'Acesso removido.'
  )
}

function propagar() {
  const alvo = copiarVisibilidade.value ? 'acessos e a visibilidade' : 'acessos'
  if (!confirm(`Aplicar os ${alvo} deste cliente a todos os processos e contratos dele?`)) return
  return executar(
    () => apiFetch(`acessos/cliente/${props.entidadeId}/propagar`, { method: 'POST', body: { copiar_visibilidade: copiarVisibilidade.value } }),
    'Acessos aplicados.'
  )
}

watch(() => [props.tipo, props.entidadeId, props.responsavelId], carregar)
onMounted(carregar)
</script>

<style scoped>
.acessos-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.visibilidade-toggle {
  display: flex;
  gap: 6px;
}

.visibilidade-toggle .btn {
  flex: 1;
}

.acessos-ajuda {
  margin: 0;
  font-size: 0.78rem;
  color: var(--ink-muted);
}

.acessos-lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.acessos-lista li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--ink-dim);
}

.acessos-avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: bold;
  flex-shrink: 0;
}

.acessos-nome {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.acessos-papel {
  font-size: 0.72rem;
  color: var(--ink-muted);
}

.acessos-remover {
  padding: 2px 6px;
  color: var(--ink-muted);
}

.acessos-remover:hover {
  color: var(--danger);
}

.acessos-vazio {
  font-size: 0.8rem;
  color: var(--ink-muted);
}

.acessos-adicionar {
  display: flex;
  gap: 6px;
}

.acessos-adicionar select {
  flex: 1;
  min-width: 0;
  padding: 6px 8px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.2);
  color: var(--ink);
  border: 1px solid var(--border);
}

.acessos-propagar {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 10px;
  border-top: 1px solid var(--border);
}

.acessos-propagar-opcao {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: var(--ink-dim);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}
</style>
