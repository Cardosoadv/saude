<template>
  <div class="schema-form">
    <div
      v-for="(campo, nome) in campos"
      :key="nome"
      class="form-group schema-campo"
      :class="{ 'schema-campo--erro': temErro(String(nome)) }"
      :data-campo="caminho(String(nome))"
    >
      <label class="form-label" :class="{ 'required-field': campo.obrigatorio }" :for="idDe(String(nome))">
        {{ rotuloDoCampo(String(nome)) }}
        <span
          v-if="campo.max && (campo.tipo === 'texto' || campo.tipo === 'texto_inline' || campo.tipo === 'textarea')"
          class="schema-contador"
          :class="{ 'schema-contador--excedeu': tamanho(String(nome)) > campo.max }"
        >{{ tamanho(String(nome)) }}/{{ campo.max }}</span>
      </label>

      <!-- texto e texto_inline -->
      <input
        v-if="campo.tipo === 'texto' || campo.tipo === 'texto_inline'"
        :id="idDe(String(nome))"
        type="text"
        class="form-control"
        :value="valorTexto(String(nome))"
        :disabled="readonly"
        :aria-invalid="temErro(String(nome)) ? 'true' : null"
        @input="definir(String(nome), ($event.target as HTMLInputElement).value)"
      />

      <!-- textarea -->
      <textarea
        v-else-if="campo.tipo === 'textarea'"
        :id="idDe(String(nome))"
        class="form-control"
        rows="4"
        :value="valorTexto(String(nome))"
        :disabled="readonly"
        :aria-invalid="temErro(String(nome)) ? 'true' : null"
        @input="definir(String(nome), ($event.target as HTMLTextAreaElement).value)"
      ></textarea>

      <!-- rich_text: mesma lista de tags do sanitizador do site -->
      <DocumentoEditor
        v-else-if="campo.tipo === 'rich_text'"
        :model-value="valorTexto(String(nome))"
        :show-placeholder-picker="false"
        :page-frame="false"
        :readonly="readonly"
        simples
        @update:model-value="definir(String(nome), $event)"
      />

      <!-- imagem -->
      <MidiaPicker
        v-else-if="campo.tipo === 'imagem'"
        :model-value="valorNumeroOuNulo(String(nome))"
        :readonly="readonly"
        exigir-alt
        @update:model-value="definir(String(nome), $event)"
      />

      <!-- icone -->
      <IconeSeletor
        v-else-if="campo.tipo === 'icone'"
        :model-value="valorTexto(String(nome))"
        :readonly="readonly"
        @update:model-value="definir(String(nome), $event)"
      />

      <!-- link -->
      <LinkSeletor
        v-else-if="campo.tipo === 'link'"
        :model-value="modelValue?.[nome]"
        :paginas="paginas"
        :readonly="readonly"
        @update:model-value="definir(String(nome), $event)"
      />

      <!-- opcao -->
      <select
        v-else-if="campo.tipo === 'opcao'"
        :id="idDe(String(nome))"
        class="form-control"
        :value="valorTexto(String(nome))"
        :disabled="readonly"
        @change="definir(String(nome), ($event.target as HTMLSelectElement).value)"
      >
        <option v-for="opcao in campo.opcoes ?? []" :key="opcao" :value="opcao">{{ opcao }}</option>
      </select>

      <!-- referencia_menu -->
      <select
        v-else-if="campo.tipo === 'referencia_menu'"
        :id="idDe(String(nome))"
        class="form-control"
        :value="valorTexto(String(nome))"
        :disabled="readonly"
        @change="definir(String(nome), ($event.target as HTMLSelectElement).value)"
      >
        <option value="">Selecione um menu…</option>
        <option v-for="local in menus" :key="local" :value="local">{{ local }}</option>
      </select>

      <!-- bool -->
      <label v-else-if="campo.tipo === 'bool'" class="schema-switch">
        <input
          :id="idDe(String(nome))"
          type="checkbox"
          role="switch"
          :checked="!!modelValue?.[nome]"
          :disabled="readonly"
          @change="definir(String(nome), ($event.target as HTMLInputElement).checked)"
        />
        <span>{{ modelValue?.[nome] ? 'Sim' : 'Não' }}</span>
      </label>

      <!-- dinamico: só leitura -->
      <p v-else-if="campo.tipo === 'dinamico'" class="schema-dinamico">
        <i class="bi bi-lightning-charge" aria-hidden="true"></i>
        Conteúdo automático: {{ rotuloDaFonte(campo.fonte) }}.
      </p>

      <!-- lista: repetidor com adicionar, remover e reordenar -->
      <div v-else-if="campo.tipo === 'lista'" class="schema-lista">
        <div
          v-for="(item, i) in itensDe(String(nome))"
          :key="i"
          class="schema-item"
          :class="{ 'schema-item--alvo': arrastando !== null && alvo === `${nome}:${i}` }"
          :data-item="`${caminho(String(nome))}.${i}`"
          @dragover.prevent="alvo = `${nome}:${i}`"
          @drop.prevent="soltar(String(nome), i)"
        >
          <div class="schema-item-topo">
            <span
              class="schema-alca"
              :draggable="!readonly"
              title="Arraste para reordenar"
              aria-hidden="true"
              @dragstart="iniciarArraste(String(nome), i)"
              @dragend="encerrarArraste"
            ><i class="bi bi-grip-vertical"></i></span>
            <strong>{{ rotuloDoCampo(String(nome)) }} {{ i + 1 }}</strong>
            <span v-if="!readonly" class="schema-item-acoes">
              <button type="button" class="btn btn-ghost btn-sm" :disabled="i === 0" title="Mover para cima" @click="mover(String(nome), i, i - 1)">
                <i class="bi bi-arrow-up"></i>
              </button>
              <button type="button" class="btn btn-ghost btn-sm" :disabled="i === itensDe(String(nome)).length - 1" title="Mover para baixo" @click="mover(String(nome), i, i + 1)">
                <i class="bi bi-arrow-down"></i>
              </button>
              <button type="button" class="btn btn-danger btn-sm" title="Remover" @click="remover(String(nome), i)">
                <i class="bi bi-trash"></i>
              </button>
            </span>
          </div>
          <SchemaForm
            :campos="campo.itens ?? {}"
            :model-value="item"
            :errors="errors"
            :caminho-base="`${caminho(String(nome))}.${i}`"
            :paginas="paginas"
            :menus="menus"
            :readonly="readonly"
            @update:model-value="atualizarItem(String(nome), i, $event)"
          />
        </div>
        <button v-if="!readonly" type="button" class="btn btn-ghost btn-sm" @click="adicionar(String(nome), campo)">
          <i class="bi bi-plus-lg"></i> Adicionar
        </button>
      </div>

      <p v-if="mensagem(String(nome))" class="schema-erro" role="alert">{{ mensagem(String(nome)) }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Formulário gerado a partir dos `campos` do schema de um tipo de seção
 * (`GET site/admin/template/schema`). Cada tipo de campo vira um componente
 * (ver docs/api-site-admin.md\); listas são repetidores recursivos.
 *
 * `errors` usa os caminhos da API já sem o prefixo `dados.` (`botoes.1.rotulo`);
 * use `errosSemPrefixo(erros, 'dados.')` ao receber o 422 de uma seção.
 */
import { ref, watch } from 'vue'
import DocumentoEditor from '../editor/DocumentoEditor.vue'
import IconeSeletor from './IconeSeletor.vue'
import LinkSeletor from './LinkSeletor.vue'
import MidiaPicker from './MidiaPicker.vue'
import type { CampoSchema, CamposSchema } from '../../types/site'
import {
  caminhoDe, moverItem, novoItem, removerItem, rotuloDoCampo, temErroEm, type Dados,
} from '../../utils/schemaForm'

const props = withDefaults(defineProps<{
  campos: CamposSchema
  modelValue?: Dados | null
  errors?: Record<string, string>
  /** Caminho deste formulário dentro do formulário raiz (uso interno das listas). */
  caminhoBase?: string
  paginas?: Array<{ id: number; titulo: string }>
  menus?: string[]
  readonly?: boolean
}>(), {
  modelValue: () => ({}),
  errors: () => ({}),
  caminhoBase: '',
  menus: () => [],
  readonly: false,
})

const emit = defineEmits<{ (e: 'update:modelValue', valor: Dados): void }>()

const arrastando = ref<{ campo: string; indice: number } | null>(null)
const alvo = ref<string | null>(null)

const caminho = (nome: string) => caminhoDe(props.caminhoBase, nome)
const idDe = (nome: string) => `campo-${caminho(nome).replace(/\./g, '-')}`
const temErro = (nome: string) => temErroEm(props.errors, caminho(nome))
/** Mensagem do próprio campo; erros de itens aparecem junto de cada item. */
const mensagem = (nome: string) => props.errors[caminho(nome)] ?? ''

const valorTexto = (nome: string): string => {
  const v = props.modelValue?.[nome]
  return v === null || v === undefined ? '' : String(v)
}
const valorNumeroOuNulo = (nome: string): number | null => {
  const v = props.modelValue?.[nome]
  return v === null || v === undefined || v === '' ? null : Number(v)
}
const tamanho = (nome: string) => valorTexto(nome).length
const itensDe = (nome: string): Dados[] => {
  const v = props.modelValue?.[nome]
  return Array.isArray(v) ? v : []
}

// Último valor emitido: duas alterações antes da tela re-renderizar não perdem a primeira.
let ultimo: Dados = props.modelValue ?? {}
watch(() => props.modelValue, (v) => { ultimo = v ?? {} })

function definir(nome: string, valor: unknown) {
  ultimo = { ...ultimo, [nome]: valor }
  emit('update:modelValue', ultimo)
}

// As alterações partem do último valor emitido, não do que a tela já desenhou.
const itensAtuais = (nome: string): Dados[] => (Array.isArray(ultimo?.[nome]) ? ultimo[nome] : [])
const adicionar = (nome: string, campo: CampoSchema) => definir(nome, [...itensAtuais(nome), novoItem(campo)])
const remover = (nome: string, i: number) => definir(nome, removerItem(itensAtuais(nome), i))
const mover = (nome: string, de: number, para: number) => definir(nome, moverItem(itensAtuais(nome), de, para))
const atualizarItem = (nome: string, i: number, valor: Dados) =>
  definir(nome, itensAtuais(nome).map((item, j) => (j === i ? valor : item)))

function iniciarArraste(campo: string, indice: number) {
  arrastando.value = { campo, indice }
}
function encerrarArraste() {
  arrastando.value = null
  alvo.value = null
}
function soltar(campo: string, indice: number) {
  const origem = arrastando.value
  encerrarArraste()
  if (origem && origem.campo === campo) mover(campo, origem.indice, indice)
}

const FONTES: Record<string, string> = { ultimas_noticias: 'últimas notícias', areas: 'áreas de atuação' }
const rotuloDaFonte = (fonte?: string) => (fonte && FONTES[fonte]) || fonte || 'preenchido pelo site'
</script>

<style scoped>
.schema-campo--erro > .form-label { color: var(--danger); }
.schema-campo--erro > .form-control { border-color: var(--danger); }
.schema-contador { margin-left: 8px; font-weight: 400; font-size: .75rem; color: var(--ink-dim); }
.schema-contador--excedeu { color: var(--danger); font-weight: 600; }
.schema-erro { margin: 4px 0 0; font-size: .8rem; color: var(--danger); }
.schema-dinamico { margin: 0; padding: 8px 10px; border: 1px dashed var(--border); border-radius: 6px; color: var(--ink-dim); font-size: .85rem; }
.schema-switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.schema-lista { display: flex; flex-direction: column; gap: 10px; }
.schema-item { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; background: var(--surface); }
.schema-item--alvo { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(1, 81, 110, .15); }
.schema-item-topo { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.schema-alca { cursor: grab; color: var(--ink-dim); }
.schema-item-acoes { margin-left: auto; display: inline-flex; gap: 4px; }
</style>
