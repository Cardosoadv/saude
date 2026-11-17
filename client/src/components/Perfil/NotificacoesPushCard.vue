<template>
  <section class="card push-card" aria-labelledby="push-titulo">
    <div class="card-header">
      <h3 id="push-titulo"><i class="bi bi-bell"></i> Notificações neste aparelho</h3>
      <span v-if="situacao === 'ativado'" class="push-selo"><i class="bi bi-check-circle-fill"></i> Ativadas</span>
    </div>

    <div class="card-body">
      <p class="push-texto">
        Receba um aviso no celular ou no computador quando o prazo de uma tarefa sua estiver vencendo,
        mesmo com o sistema fechado.
      </p>

      <div v-if="situacao === 'carregando'" class="push-aviso"><span class="spinner-sm"></span> Verificando…</div>

      <div v-else-if="situacao === 'instalar-ios'" class="push-aviso">
        <i class="bi bi-phone"></i>
        <span>No iPhone e no iPad, as notificações só funcionam com o app instalado: toque em
          <i class="bi bi-box-arrow-up" aria-label="Compartilhar"></i>, depois em <strong>Adicionar à Tela de Início</strong>,
          e abra o Saúde pelo ícone criado.</span>
      </div>

      <div v-else-if="situacao === 'sem-suporte'" class="push-aviso">
        <i class="bi bi-info-circle"></i>
        <span>Este navegador não oferece notificações para o Saúde. Use o Chrome, o Edge ou o Firefox
          atualizados, ou o app instalado.</span>
      </div>

      <div v-else-if="situacao === 'servidor-off'" class="push-aviso">
        <i class="bi bi-info-circle"></i>
        <span>As notificações ainda não foram configuradas no servidor da clínica. Fale com o administrador.</span>
      </div>

      <div v-else-if="situacao === 'erro'" class="push-aviso">
        <i class="bi bi-exclamation-triangle"></i>
        <span>Não foi possível consultar as notificações agora.</span>
        <button class="btn btn-sm btn-ghost" type="button" @click="carregarPush()">Tentar de novo</button>
      </div>

      <div v-else-if="situacao === 'bloqueado'" class="push-aviso push-aviso-alerta">
        <i class="bi bi-bell-slash"></i>
        <span>As notificações estão bloqueadas para este site no navegador. Libere em
          <strong>Configurações do site → Notificações</strong> (ícone ao lado do endereço) e recarregue a página.</span>
      </div>

      <template v-else>
        <fieldset class="push-preferencias" :disabled="ocupado">
          <legend class="form-label">Quero ser avisado sobre</legend>
          <label class="push-opcao">
            <input type="checkbox" v-model="escolha.prazos" @change="aoMudarPreferencia" />
            <span><strong>Prazos das minhas tarefas</strong><small>No dia e na véspera do prazo.</small></span>
          </label>
        </fieldset>
        <small class="perfil-dica">
          <template v-if="horario">Avisos são enviados entre {{ horario.inicio }}h e {{ horario.fim }}h. </template>Cada aparelho tem sua própria escolha.
        </small>
      </template>
    </div>

    <div v-if="situacao === 'desativado' || situacao === 'ativado'" class="perfil-rodape push-rodape">
      <template v-if="situacao === 'ativado'">
        <button class="btn btn-ghost" type="button" :disabled="ocupado" @click="desativar">
          <i class="bi bi-bell-slash"></i> Desativar neste aparelho
        </button>
        <button class="btn btn-primary" type="button" :disabled="ocupado" @click="testar">
          <i class="bi bi-send"></i> Enviar teste
        </button>
      </template>
      <button v-else class="btn btn-primary" type="button" :disabled="ocupado || !escolha.prazos" @click="ativar">
        <i class="bi bi-bell"></i> {{ ocupado ? 'Ativando…' : 'Ativar notificações' }}
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
/**
 * @version 1.0.0
 * Ativação das notificações push (PWA) do aparelho atual e preferências.
 */
import { reactive, watch, onMounted } from 'vue'
import {
  usePush, carregarPush, ativarPush, desativarPush, salvarPreferenciasPush, testarPush,
} from '../../composables/usePush'

const emit = defineEmits(['toast'])
const { situacao, preferencias, ocupado, horario } = usePush()

const escolha = reactive({ prazos: true })
watch(preferencias, (p) => Object.assign(escolha, p), { immediate: true })

function erro(e: unknown, padrao: string) {
  emit('toast', { msg: (e as Error)?.message || padrao, type: 'danger' })
}

async function ativar() {
  try {
    await ativarPush({ ...escolha })
    emit('toast', { msg: 'Notificações ativadas neste aparelho.', type: 'success' })
  } catch (e) {
    erro(e, 'Não foi possível ativar as notificações.')
  }
}

async function desativar() {
  try {
    await desativarPush()
    emit('toast', { msg: 'Notificações desativadas neste aparelho.', type: 'success' })
  } catch (e) {
    erro(e, 'Não foi possível desativar as notificações.')
  }
}

async function aoMudarPreferencia() {
  if (situacao.value !== 'ativado') return
  if (!escolha.prazos) {
    // Nada marcado = desativar de vez neste aparelho.
    await desativar()
    Object.assign(escolha, { prazos: true })
    return
  }
  try {
    await salvarPreferenciasPush({ ...escolha })
    emit('toast', { msg: 'Preferências de notificação salvas.', type: 'success' })
  } catch (e) {
    Object.assign(escolha, preferencias.value)
    erro(e, 'Não foi possível salvar as preferências.')
  }
}

async function testar() {
  try {
    emit('toast', { msg: await testarPush(), type: 'success' })
  } catch (e) {
    erro(e, 'Não foi possível enviar o teste.')
  }
}

onMounted(() => { carregarPush() })
</script>

<style scoped>
.card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.push-selo { font-size: .78rem; color: var(--success); font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.push-texto { font-size: .86rem; color: var(--ink-dim); line-height: 1.5; margin-bottom: 14px; }
.push-aviso {
  display: flex; align-items: flex-start; gap: 10px; flex-wrap: wrap;
  padding: 12px 14px; border-radius: 10px; background: var(--primary-lt);
  font-size: .84rem; color: var(--ink-dim); line-height: 1.45;
}
.push-aviso > i { color: var(--primary); font-size: 1rem; margin-top: 1px; }
.push-aviso > span { flex: 1; min-width: 200px; }
.push-aviso-alerta { background: #fef3c7; }
.push-aviso-alerta > i { color: var(--warning); }
.push-preferencias { border: 0; padding: 0; margin: 0 0 6px; }
.push-preferencias legend { margin-bottom: 8px; }
.push-opcao { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; cursor: pointer; }
.push-opcao input { margin-top: 3px; accent-color: var(--primary); width: 16px; height: 16px; flex-shrink: 0; }
.push-opcao strong { display: block; font-size: .88rem; font-weight: 600; color: var(--ink); }
.push-opcao small { display: block; font-size: .78rem; color: var(--ink-muted); }
.perfil-dica { display: block; font-size: .75rem; color: var(--ink-muted); margin-top: 4px; }
.perfil-rodape {
  display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; padding: 12px 20px;
  border-top: 1px solid var(--border); background: var(--surface);
  border-radius: 0 0 var(--radius) var(--radius);
}
.spinner-sm {
  width: 14px; height: 14px; border: 2px solid var(--border); border-top-color: var(--primary);
  border-radius: 50%; display: inline-block; animation: push-gira .8s linear infinite;
}
@keyframes push-gira { to { transform: rotate(360deg); } }
</style>
