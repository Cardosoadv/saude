<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Configurações do Site</h1>
        <p>Identidade, contato, SEO, integrações e textos do site público.</p>
      </div>
      <div class="page-header-actions">
        <span v-if="sujo" class="badge-sujo" role="status"><i class="bi bi-pencil"></i> Alterações não salvas</span>
        <button class="btn btn-ghost" :disabled="!sujo || salvando" @click="descartar">Descartar</button>
        <button class="btn btn-accent" :disabled="!sujo || salvando" @click="salvar">
          <span v-if="salvando"><i class="bi bi-arrow-repeat spin"></i> Salvando...</span>
          <span v-else><i class="bi bi-check-lg"></i> Salvar alterações</span>
        </button>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <div v-else class="form-container">
      <div class="abas" role="tablist">
        <button
          v-for="aba in todasAbas"
          :key="aba.id"
          type="button"
          role="tab"
          class="aba"
          :class="{ ativa: abaAtiva === aba.id, 'aba--erro': abaTemErro(aba.id) }"
          :aria-selected="abaAtiva === aba.id"
          @click="abaAtiva = aba.id"
        >
          <i :class="['bi', aba.icone]"></i> {{ aba.rotulo }}
          <span v-if="abaTemErro(aba.id)" class="aba-alerta" aria-label="Há erros nesta aba">!</span>
        </button>
      </div>

      <!-- Abas de campos fixos -->
      <div
        v-for="aba in ABAS"
        v-show="abaAtiva === aba.id"
        :key="aba.id"
        class="form-card glass-panel"
        role="tabpanel"
      >
        <div class="card-body" style="padding: 1.5rem;">
          <div v-for="campo in aba.campos" :key="campo.chave" class="form-group" :class="{ 'campo--erro': erros[campo.chave] }" :data-chave="campo.chave">
            <label v-if="campo.ui === 'checkbox'" class="form-check" :for="idDe(campo.chave)">
              <input
                :id="idDe(campo.chave)"
                type="checkbox"
                :checked="valores[campo.chave]"
                @change="definir(campo.chave, ($event.target as HTMLInputElement).checked)"
              />
              <span>{{ campo.rotulo }}</span>
            </label>
            <label v-else class="form-label" :for="idDe(campo.chave)">
              {{ campo.rotulo }}
              <span v-if="campo.max" class="contador" :class="{ 'contador--excedeu': tamanho(campo.chave) > campo.max }">{{ tamanho(campo.chave) }}/{{ campo.max }}</span>
            </label>

            <MidiaPicker
              v-if="campo.ui === 'imagem'"
              :model-value="valores[campo.chave]"
              @update:model-value="definir(campo.chave, $event)"
              @toast="emit('toast', $event)"
            />

            <textarea
              v-else-if="campo.ui === 'textarea'"
              :id="idDe(campo.chave)"
              class="form-control"
              rows="4"
              :value="valores[campo.chave]"
              @input="definir(campo.chave, ($event.target as HTMLTextAreaElement).value)"
            ></textarea>

            <div v-else-if="campo.ui === 'cor'" class="cor-campo">
              <input
                type="color"
                :value="corValida(valores[campo.chave]) ? valores[campo.chave] : '#1b4965'"
                aria-label="Escolher cor"
                @input="definir(campo.chave, ($event.target as HTMLInputElement).value)"
              />
              <input
                :id="idDe(campo.chave)"
                type="text"
                class="form-control"
                :value="valores[campo.chave]"
                placeholder="#1B4965"
                @input="definir(campo.chave, ($event.target as HTMLInputElement).value)"
              />
              <button v-if="valores[campo.chave]" type="button" class="btn btn-ghost btn-sm" @click="definir(campo.chave, '')">Usar padrão</button>
            </div>

            <!-- Unidades (endereços) -->
            <div v-else-if="campo.ui === 'enderecos'" class="unidades">
              <div v-for="(unidade, i) in unidades(campo.chave)" :key="i" class="unidade" :data-unidade="i">
                <div class="unidade-topo">
                  <strong>Unidade {{ i + 1 }}</strong>
                  <button type="button" class="btn btn-danger btn-sm" title="Remover unidade" @click="removerUnidade(campo.chave, i)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
                <div class="form-row col-2">
                  <div class="form-group"><label class="form-label">Nome da unidade</label>
                    <input class="form-control" :value="unidade.titulo" placeholder="Belo Horizonte / MG" @input="editarUnidade(campo.chave, i, 'titulo', $event)" /></div>
                  <div class="form-group"><label class="form-label">Resumo (uma linha)</label>
                    <input class="form-control" :value="unidade.resumo" @input="editarUnidade(campo.chave, i, 'resumo', $event)" /></div>
                </div>
                <div class="form-row col-2">
                  <div class="form-group"><label class="form-label">Logradouro</label>
                    <input class="form-control" :value="unidade.rua" @input="editarUnidade(campo.chave, i, 'rua', $event)" /></div>
                  <div class="form-group"><label class="form-label">Complemento / bairro</label>
                    <input class="form-control" :value="unidade.linha2" @input="editarUnidade(campo.chave, i, 'linha2', $event)" /></div>
                </div>
                <div class="form-row col-2">
                  <div class="form-group"><label class="form-label">Endereço exibido (linha 1)</label>
                    <input class="form-control" :value="unidade.linha1" @input="editarUnidade(campo.chave, i, 'linha1', $event)" /></div>
                  <div class="form-group"><label class="form-label">CEP</label>
                    <input class="form-control" :value="unidade.cep" placeholder="00000-000" @input="editarUnidade(campo.chave, i, 'cep', $event)" /></div>
                </div>
                <div class="form-row col-2">
                  <div class="form-group"><label class="form-label">Cidade</label>
                    <input class="form-control" :value="unidade.cidade" @input="editarUnidade(campo.chave, i, 'cidade', $event)" /></div>
                  <div class="form-group"><label class="form-label">UF</label>
                    <input class="form-control" maxlength="2" :value="unidade.uf" placeholder="MG" @input="editarUnidade(campo.chave, i, 'uf', $event)" /></div>
                </div>
              </div>
              <button type="button" class="btn btn-ghost btn-sm" :disabled="unidades(campo.chave).length >= 10" @click="adicionarUnidade(campo.chave)">
                <i class="bi bi-plus-lg"></i> Adicionar unidade
              </button>
            </div>

            <template v-else-if="campo.ui === 'checkbox'"></template>

            <input
              v-else
              :id="idDe(campo.chave)"
              :type="campo.ui === 'email' ? 'email' : 'text'"
              class="form-control"
              :value="valores[campo.chave]"
              :placeholder="campo.placeholder"
              :aria-invalid="erros[campo.chave] ? 'true' : null"
              @input="definir(campo.chave, ($event.target as HTMLInputElement).value)"
            />

            <small v-if="campo.ajuda" class="ajuda">{{ campo.ajuda }}</small>
            <p v-if="erros[campo.chave]" class="erro-campo" role="alert">{{ erros[campo.chave] }}</p>
          </div>

          <!-- Prévia no Google -->
          <div v-if="aba.id === 'seo'" class="serp" aria-label="Prévia no Google">
            <small class="ajuda">Prévia no Google</small>
            <div class="serp-url">{{ previa.url }}</div>
            <div class="serp-titulo">{{ previa.titulo }}</div>
            <div class="serp-descricao">{{ previa.descricao }}</div>
          </div>
        </div>
      </div>

      <!-- Textos do site -->
      <div v-show="abaAtiva === ABA_TEXTOS.id" class="form-card glass-panel" role="tabpanel">
        <div class="card-body" style="padding: 1.5rem;">
          <p v-if="!gruposTextos.length" class="ajuda">O template ativo não declara textos editáveis.</p>
          <fieldset v-for="g in gruposTextos" :key="g.grupo" class="grupo-textos">
            <legend>{{ rotuloDoCampo(g.grupo) }}</legend>
            <div v-for="t in g.itens" :key="t.chave" class="form-group" :class="{ 'campo--erro': erros['textos.' + t.chave] }" :data-chave="'textos.' + t.chave">
              <label class="form-label" :for="idDe('textos.' + t.chave)">
                {{ t.chave }}
                <span v-if="personalizado('textos.' + t.chave)" class="selo-personalizado">personalizado</span>
              </label>
              <div class="texto-linha">
                <textarea
                  v-if="t.padrao.length > 80"
                  :id="idDe('textos.' + t.chave)"
                  class="form-control"
                  rows="2"
                  :value="valores['textos.' + t.chave]"
                  :placeholder="t.padrao"
                  @input="definir('textos.' + t.chave, ($event.target as HTMLTextAreaElement).value)"
                ></textarea>
                <input
                  v-else
                  :id="idDe('textos.' + t.chave)"
                  type="text"
                  class="form-control"
                  :value="valores['textos.' + t.chave]"
                  :placeholder="t.padrao"
                  @input="definir('textos.' + t.chave, ($event.target as HTMLInputElement).value)"
                />
                <button
                  type="button"
                  class="btn btn-ghost btn-sm"
                  :disabled="!valores['textos.' + t.chave]"
                  title="Restaurar o texto padrão do template"
                  @click="definir('textos.' + t.chave, '')"
                >Restaurar padrão</button>
              </div>
              <p v-if="erros['textos.' + t.chave]" class="erro-campo" role="alert">{{ erros['textos.' + t.chave] }}</p>
            </div>
          </fieldset>
        </div>
      </div>

      <div v-if="avisos.length" class="glass-panel avisos" role="status">
        <strong><i class="bi bi-info-circle"></i> Avisos</strong>
        <ul><li v-for="a in avisos" :key="a">{{ a }}</li></ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import MidiaPicker from '../../components/site/MidiaPicker.vue'
import { marcarSujo } from '../../composables/useSaidaProtegida'
import { avisosDe, errosDeCampo, obterConfiguracoes, obterSchema, salvarConfiguracoes } from '../../services/siteApi'
import {
  ABA_TEXTOS, ABAS, abaDoErro, agruparTextos, calcularAlteracoes, chavesExistentes, estadoInicial,
  novaUnidade, previaGoogle, validarTodos, type Valores,
} from '../../utils/configuracoesSite'
import { rotuloDoCampo } from '../../utils/schemaForm'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void }>()

const carregando = ref(true)
const falhaCarga = ref('')
const salvando = ref(false)

const textos = ref<Record<string, string>>({})
const inicial = ref<Valores>({})
const valores = ref<Valores>({})
const existentes = ref<Set<string>>(new Set())
const erros = ref<Record<string, string>>({})
const avisos = ref<string[]>([])
const abaAtiva = ref(ABAS[0].id)

const todasAbas = [...ABAS, ABA_TEXTOS]
const gruposTextos = computed(() => agruparTextos(textos.value))
const alteracoes = computed(() => calcularAlteracoes(inicial.value, valores.value, existentes.value, textos.value))
const sujo = computed(() => Object.keys(alteracoes.value).length > 0)
const previa = computed(() => previaGoogle(valores.value))

const idDe = (chave: string) => `cfg-${chave.replace(/\./g, '-')}`
const tamanho = (chave: string) => String(valores.value[chave] ?? '').length
const corValida = (v: unknown) => /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(String(v ?? ''))
const personalizado = (chave: string) => existentes.value.has(chave) && !!valores.value[chave]

const abaTemErro = (id: string) => Object.keys(erros.value).some((c) => c.split('.')[0] === id)

function definir(chave: string, valor: unknown) {
  valores.value = { ...valores.value, [chave]: valor }
  if (erros.value[chave]) {
    const { [chave]: _removido, ...resto } = erros.value
    erros.value = resto
  }
}

function aplicarServidor(grupos: Parameters<typeof estadoInicial>[0]) {
  inicial.value = estadoInicial(grupos, textos.value)
  valores.value = JSON.parse(JSON.stringify(inicial.value))
  existentes.value = chavesExistentes(grupos)
}

async function carregar() {
  carregando.value = true
  falhaCarga.value = ''
  try {
    const [config, schema] = await Promise.all([
      obterConfiguracoes(),
      obterSchema().catch(() => null), // sem template ativo (404) a aba de textos fica vazia
    ])
    textos.value = schema?.data.textos ?? {}
    aplicarServidor(config.data)
  } catch (e: any) {
    falhaCarga.value = 'Não foi possível carregar as configurações: ' + (e?.message ?? 'erro desconhecido')
  } finally {
    carregando.value = false
  }
}

function descartar() {
  valores.value = JSON.parse(JSON.stringify(inicial.value))
  erros.value = {}
}

async function salvar() {
  if (!sujo.value) return

  const locais = validarTodos(Object.fromEntries(Object.keys(alteracoes.value).map((c) => [c, valores.value[c]])))
  if (Object.keys(locais).length) {
    erros.value = locais
    abaAtiva.value = abaDoErro(locais) ?? abaAtiva.value
    emit('toast', { msg: 'Corrija os campos destacados antes de salvar.', type: 'warning' })
    return
  }

  salvando.value = true
  try {
    const r = await salvarConfiguracoes(alteracoes.value)
    aplicarServidor(r.data)
    erros.value = {}
    avisos.value = avisosDe(r)
    emit('toast', { msg: 'Configurações do site salvas.', type: 'success' })
  } catch (e: any) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) {
      erros.value = doServidor
      abaAtiva.value = abaDoErro(doServidor) ?? abaAtiva.value
      emit('toast', { msg: 'Algumas configurações não foram aceitas. Nada foi salvo.', type: 'danger' })
    } else {
      emit('toast', { msg: 'Erro ao salvar: ' + (e?.message ?? 'erro desconhecido'), type: 'danger' })
    }
  } finally {
    salvando.value = false
  }
}

// Unidades
const unidades = (chave: string): Array<Record<string, string>> => valores.value[chave] ?? []
const adicionarUnidade = (chave: string) => definir(chave, [...unidades(chave), novaUnidade()])
const removerUnidade = (chave: string, i: number) => definir(chave, unidades(chave).filter((_, j) => j !== i))
function editarUnidade(chave: string, i: number, campo: string, evento: Event) {
  const valor = (evento.target as HTMLInputElement).value
  definir(chave, unidades(chave).map((u, j) => (j === i ? { ...u, [campo]: valor } : u)))
}

// Aviso ao fechar/recarregar a aba do navegador com alterações pendentes
function aoFechar(e: BeforeUnloadEvent) {
  if (sujo.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}
watch(sujo, (v) => marcarSujo(v))

onMounted(() => {
  window.addEventListener('beforeunload', aoFechar)
  carregar()
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', aoFechar)
  marcarSujo(false)
})
</script>

<style scoped>
.abas { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
.aba { border: 0; background: transparent; padding: 10px 14px; cursor: pointer; color: var(--ink-dim); border-bottom: 2px solid transparent; }
.aba.ativa { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
.aba--erro { color: var(--danger); }
.aba-alerta { margin-left: 4px; background: var(--danger); color: #fff; border-radius: 50%; width: 16px; height: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: .7rem; }
.badge-sujo { align-self: center; font-size: .8rem; color: var(--accent); }
.contador { margin-left: 8px; font-weight: 400; font-size: .75rem; color: var(--ink-dim); }
.contador--excedeu { color: var(--danger); font-weight: 600; }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; }
.ajuda { display: block; margin-top: 4px; color: var(--ink-dim); }
.erro-campo { margin: 4px 0 0; font-size: .8rem; color: var(--danger); }
.campo--erro .form-control { border-color: var(--danger); }
.cor-campo { display: flex; align-items: center; gap: 8px; }
.cor-campo input[type='text'] { max-width: 160px; }
.unidade { border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 12px; }
.unidade-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.serp { margin-top: 18px; padding: 14px; border: 1px solid var(--border); border-radius: 8px; background: #fff; max-width: 600px; }
.serp-url { color: #202124; font-size: .8rem; }
.serp-titulo { color: #1a0dab; font-size: 1.15rem; line-height: 1.3; }
.serp-descricao { color: #4d5156; font-size: .85rem; }
.grupo-textos { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; margin: 0 0 16px; }
.grupo-textos legend { padding: 0 6px; font-weight: 600; }
.texto-linha { display: flex; gap: 8px; align-items: flex-start; }
.texto-linha .form-control { flex: 1; }
.selo-personalizado { margin-left: 8px; font-size: .7rem; font-weight: 600; color: var(--accent); }
.avisos { margin-top: 12px; padding: 12px 16px; }
</style>
