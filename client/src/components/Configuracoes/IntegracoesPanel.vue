<template>
  <div>
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display: flex; align-items: center; gap: 8px;">
        <i :class="['bi', grupo.icone]" style="color: var(--accent);"></i> {{ grupo.titulo }}
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0; line-height: 1.4;">
        {{ grupo.descricao }} Estas configurações valem só para este escritório.
      </p>
      <p
        v-if="!criptografia && temSegredo"
        class="integracoes-aviso"
        role="note"
        data-testid="aviso-criptografia"
      >
        <i class="bi bi-exclamation-triangle"></i>
        Os segredos estão sendo guardados sem criptografia. Peça ao administrador do servidor para definir a chave
        <code>encryption.key</code>.
      </p>
    </div>

    <form class="card animate-fade-in" style="padding: 20px;" @submit.prevent="salvar" novalidate>
      <section v-for="secao in grupo.secoes" :key="secao.titulo" class="integracoes-secao">
        <h4 v-if="grupo.secoes.length > 1" class="integracoes-secao-titulo">{{ secao.titulo }}</h4>

        <div v-for="campo in secao.campos" :key="campo.chave" class="form-group integracoes-campo">
          <div class="integracoes-rotulo-linha">
            <label :for="'int-' + campo.chave" class="form-label">{{ campo.rotulo }}</label>
            <span :class="['badge', classeDoSelo(campo)]" :data-testid="'selo-' + campo.chave">{{ rotuloDoSelo(campo) }}</span>
          </div>

          <select
            v-if="campo.tipo === 'opcao'"
            :id="'int-' + campo.chave"
            v-model="editados[campo.chave]"
            class="form-control"
            :aria-label="campo.rotulo"
            :disabled="salvando"
          >
            <option v-for="(rotulo, valor) in campo.opcoes" :key="valor" :value="String(valor)">{{ rotulo }}</option>
          </select>

          <template v-else-if="campo.tipo === 'segredo'">
            <div class="integracoes-segredo">
              <input
                :id="'int-' + campo.chave"
                v-model="editados[campo.chave]"
                type="password"
                class="form-control"
                :aria-label="campo.rotulo"
                autocomplete="new-password"
                spellcheck="false"
                :placeholder="placeholderDoSegredo(campo)"
                :disabled="salvando || paraApagar.has(campo.chave)"
              />
              <button
                v-if="campo.tem_valor && campo.origem === 'banco' && !paraApagar.has(campo.chave)"
                type="button"
                class="btn btn-danger btn-sm"
                :disabled="salvando"
                :data-testid="'apagar-' + campo.chave"
                @click="marcarParaApagar(campo.chave)"
              >
                <i class="bi bi-trash"></i> Apagar
              </button>
              <button
                v-if="paraApagar.has(campo.chave)"
                type="button"
                class="btn btn-ghost btn-sm"
                :disabled="salvando"
                @click="desfazerApagar(campo.chave)"
              >
                <i class="bi bi-arrow-counterclockwise"></i> Desfazer
              </button>
            </div>
            <small v-if="paraApagar.has(campo.chave)" class="integracoes-dica integracoes-dica-perigo">
              Será apagado ao salvar.
            </small>
          </template>

          <input
            v-else
            :id="'int-' + campo.chave"
            v-model="editados[campo.chave]"
            :type="campo.tipo === 'url' ? 'url' : 'text'"
            class="form-control"
            :aria-label="campo.rotulo"
            autocomplete="off"
            spellcheck="false"
            :placeholder="campo.tipo === 'url' ? 'https://' : ''"
            :disabled="salvando"
          />

          <small v-if="campo.descricao" class="integracoes-dica">{{ campo.descricao }}</small>
          <small v-if="campo.origem === 'env'" class="integracoes-dica">
            Vem da configuração do servidor. Salvar um valor aqui passa a usar o valor deste escritório.
          </small>
          <small v-if="erros[campo.chave]" class="integracoes-erro" role="alert" :data-testid="'erro-' + campo.chave">
            {{ erros[campo.chave] }}
          </small>
        </div>
      </section>

      <div class="integracoes-acoes">
        <button type="submit" class="btn btn-primary" :disabled="salvando || !alterado" data-testid="salvar">
          <i class="bi bi-check-circle"></i> {{ salvando ? 'Salvando...' : 'Salvar' }}
        </button>
        <button type="button" class="btn btn-ghost" :disabled="salvando || !alterado" @click="descartar">
          Descartar alterações
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
/**
 * Sub-guia de integrações (aba Sistema em Configurações): um grupo do catálogo do servidor (API CNJ, Inteligência
 * artificial, APIBrasil, Google). Segredos nunca são exibidos: o campo vem vazio, em branco significa "manter" e
 * "Apagar" é um pedido explícito. Só o que mudou é enviado.
 * @version 1.0.0
 */
import { computed, reactive, ref, watch } from 'vue'
import {
  salvarIntegracoes,
  ErroValidacaoIntegracao,
  type CampoIntegracao,
  type GrupoIntegracao,
} from '../../services/integracoesApi'
import { camposDoGrupo, haAlteracoes, montarEnvio, situacaoDoCampo, valoresIniciais } from '../../utils/integracoes'

const props = defineProps<{
  grupo: GrupoIntegracao
  criptografia: boolean
}>()

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type: string }): void
  (e: 'salvo'): void
}>()

const editados = reactive<Record<string, string>>({})
const paraApagar = ref<Set<string>>(new Set())
const erros = ref<Record<string, string>>({})
const salvando = ref(false)

const temSegredo = computed(() => camposDoGrupo(props.grupo).some((c) => c.tipo === 'segredo'))
const envio = computed(() => montarEnvio(props.grupo, editados, paraApagar.value))
const alterado = computed(() => haAlteracoes(envio.value))

function reiniciar() {
  for (const chave of Object.keys(editados)) delete editados[chave]
  Object.assign(editados, valoresIniciais(props.grupo))
  paraApagar.value = new Set()
  erros.value = {}
}

// Recomeça quando chega um catálogo novo (depois de salvar) ou quando troca de sub-guia.
watch(() => props.grupo, reiniciar, { immediate: true })

function rotuloDoSelo(campo: CampoIntegracao) {
  return situacaoDoCampo(campo).texto
}

function classeDoSelo(campo: CampoIntegracao) {
  const tom = situacaoDoCampo(campo).tom
  return tom === 'ok' ? 'badge-success' : tom === 'aviso' ? 'badge-warning' : 'badge-muted'
}

function placeholderDoSegredo(campo: CampoIntegracao) {
  return campo.tem_valor ? 'Deixe em branco para manter o atual' : 'Cole aqui'
}

function marcarParaApagar(chave: string) {
  paraApagar.value = new Set(paraApagar.value).add(chave)
  editados[chave] = ''
}

function desfazerApagar(chave: string) {
  const proximo = new Set(paraApagar.value)
  proximo.delete(chave)
  paraApagar.value = proximo
}

function descartar() {
  reiniciar()
}

async function salvar() {
  if (salvando.value || !alterado.value) return
  salvando.value = true
  erros.value = {}
  try {
    const { alteradas } = await salvarIntegracoes(envio.value.valores, envio.value.limpar)
    emit('toast', { msg: alteradas.length ? 'Integrações salvas.' : 'Nada foi alterado.', type: 'success' })
    // O pai recarrega o catálogo: segredos digitados saem da memória e os selos passam a "Configurado".
    emit('salvo')
  } catch (err: any) {
    if (err instanceof ErroValidacaoIntegracao) {
      erros.value = err.erros
      emit('toast', { msg: 'Corrija os campos indicados.', type: 'error' })
    } else {
      emit('toast', { msg: err?.message || 'Erro ao salvar as integrações.', type: 'error' })
    }
  } finally {
    salvando.value = false
  }
}
</script>

<style scoped>
.integracoes-secao + .integracoes-secao {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border);
}
.integracoes-secao-titulo {
  margin: 0 0 12px 0;
  font-size: 0.95rem;
  color: var(--ink);
}
.integracoes-campo {
  margin-bottom: 14px;
}
.integracoes-rotulo-linha {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 4px;
}
.integracoes-rotulo-linha .form-label {
  margin: 0;
}
.integracoes-segredo {
  display: flex;
  gap: 8px;
  align-items: center;
}
.integracoes-segredo .form-control {
  flex: 1 1 auto;
}
.integracoes-dica {
  display: block;
  margin-top: 4px;
  font-size: 0.75rem;
  color: var(--ink-muted);
  line-height: 1.35;
}
.integracoes-dica-perigo,
.integracoes-erro {
  color: var(--danger);
}
.integracoes-erro {
  display: block;
  margin-top: 4px;
  font-size: 0.78rem;
}
.integracoes-aviso {
  margin: 12px 0 0 0;
  padding: 8px 12px;
  font-size: 0.8rem;
  line-height: 1.4;
  color: #92400e;
  background: #fef9c3;
  border-radius: 6px;
}
.integracoes-acoes {
  display: flex;
  gap: 8px;
  margin-top: 20px;
  flex-wrap: wrap;
}
</style>
