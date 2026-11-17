<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Aparência do Site</h1>
        <p>Cores e fontes do template ativo. O conteúdo não muda.</p>
      </div>
      <div class="page-header-actions">
        <span v-if="sujo" class="sujo" role="status"><i class="bi bi-pencil"></i> Alterações não salvas</span>
        <button class="btn btn-ghost" data-restaurar-tudo :disabled="salvando || !algumPersonalizado" @click="restaurarTudo">Restaurar padrão do template</button>
        <button class="btn btn-ghost" data-descartar :disabled="!sujo || salvando" @click="descartar">Descartar</button>
        <button class="btn btn-accent" data-salvar :disabled="!sujo || salvando" @click="salvar">{{ salvando ? 'Salvando…' : 'Salvar aparência' }}</button>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <div v-else-if="tema" class="colunas">
      <div class="coluna-form">
        <div v-if="avisos.length" class="glass-panel avisos" role="status" data-avisos>
          <strong><i class="bi bi-exclamation-triangle"></i> Contraste insuficiente</strong>
          <ul><li v-for="a in avisos" :key="a.texto + a.fundo">{{ a.mensagem }}</li></ul>
          <small>O aviso não impede de salvar, mas textos difíceis de ler afastam visitantes.</small>
        </div>

        <section class="glass-panel bloco" aria-label="Cores">
          <h2>Cores</h2>
          <div v-for="(info, token) in tema.cores" :key="token" class="token" :class="{ 'token--erro': erros['cores.' + token] }" :data-cor="token">
            <label class="token-nome" :for="'cor-' + token">
              {{ token }}
              <span v-if="info.personalizado" class="selo">personalizada</span>
            </label>
            <div class="token-controle">
              <input type="color" :value="hexCompleto(atual.cores[token])" :aria-label="'Escolher cor de ' + token" data-seletor @input="definirCor(String(token), ($event.target as HTMLInputElement).value)" />
              <input :id="'cor-' + token" class="form-control hex" :value="atual.cores[token]" spellcheck="false" data-hex @input="definirCor(String(token), ($event.target as HTMLInputElement).value)" />
              <span class="padrao" :title="'Padrão do template: ' + info.padrao"><i class="amostra" :style="{ background: info.padrao }"></i>{{ info.padrao }}</span>
              <button type="button" class="btn btn-ghost btn-sm" data-restaurar-token :disabled="mesmaCor(atual.cores[token], info.padrao)" @click="definirCor(String(token), info.padrao)">Padrão</button>
            </div>
            <p v-if="erros['cores.' + token]" class="erro" role="alert">{{ erros['cores.' + token] }}</p>
          </div>
        </section>

        <section class="glass-panel bloco" aria-label="Fontes">
          <h2>Fontes</h2>
          <div v-for="(info, token) in tema.fontes" :key="token" class="token" :class="{ 'token--erro': erros['fontes.' + token] }" :data-fonte="token">
            <label class="token-nome" :for="'fonte-' + token">
              {{ token === 'serif' ? 'Títulos (serifada)' : token === 'sans' ? 'Texto (sem serifa)' : token }}
              <span v-if="info.personalizado" class="selo">personalizada</span>
            </label>
            <div class="token-controle">
              <select :id="'fonte-' + token" class="form-control" :value="atual.fontes[token]" data-select-fonte @change="definirFonte(String(token), ($event.target as HTMLSelectElement).value)">
                <option v-for="f in familiasDoToken(String(token), tema.fontes_disponiveis)" :key="f" :value="f">{{ f }}{{ f === info.padrao ? ' (padrão)' : '' }}</option>
              </select>
              <button type="button" class="btn btn-ghost btn-sm" data-restaurar-fonte :disabled="atual.fontes[token] === info.padrao" @click="definirFonte(String(token), info.padrao)">Padrão</button>
            </div>
            <p class="amostra-texto" :style="{ fontFamily: `'${atual.fontes[token]}', ${token === 'sans' ? 'sans-serif' : 'serif'}` }" data-amostra>
              A advocacia exige rigor e clareza — 0123456789
            </p>
            <p v-if="erros['fontes.' + token]" class="erro" role="alert">{{ erros['fontes.' + token] }}</p>
          </div>
        </section>
      </div>

      <aside class="glass-panel previa" aria-label="Prévia da página inicial">
        <div class="previa-topo">
          <strong>Prévia da página inicial</strong>
          <button class="btn btn-ghost btn-sm" data-recarregar-previa title="Recarregar" @click="carregarPrevia"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <p v-if="erroPrevia" class="erro" role="alert">{{ erroPrevia }}</p>
        <iframe v-if="previaUrl" :key="previaChave" :src="previaUrl" title="Prévia da página inicial" sandbox="allow-same-origin allow-scripts allow-popups" data-iframe></iframe>
        <small class="mudo">A prévia mostra o que já foi salvo. Salve para ver as mudanças.</small>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { marcarSujo } from '../../composables/useSaidaProtegida'
import { avisosDe, errosDeCampo, listarPaginas, obterTema, restaurarTema, salvarTema, tokenPreview } from '../../services/siteApi'
import type { SiteTema } from '../../types/site'
import {
  avisosDeContraste, calcularCorpo, errosLocais, estadoDoTema, familiasDoToken, hexCompleto, mesmaCor, temAlteracoes,
  urlFontes, type EstadoTema,
} from '../../utils/aparenciaSite'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void }>()

const carregando = ref(true)
const falhaCarga = ref('')
const salvando = ref(false)
const tema = ref<SiteTema | null>(null)
const atual = ref<EstadoTema>({ cores: {}, fontes: {} })
const erros = ref<Record<string, string>>({})
const previaUrl = ref('')
const previaChave = ref(0)
const erroPrevia = ref('')

const sujo = computed(() => !!tema.value && temAlteracoes(tema.value, atual.value))
const avisos = computed(() => avisosDeContraste(atual.value.cores))
const algumPersonalizado = computed(() =>
  !!tema.value && [...Object.values(tema.value.cores), ...Object.values(tema.value.fontes)].some((t) => t.personalizado))
const mensagemDe = (e: unknown) => Object.values(errosDeCampo(e))[0] ?? (e as Error)?.message ?? 'erro desconhecido'

watch(sujo, (v) => marcarSujo(v))

// Amostras: carrega do Google Fonts só as famílias da lista permitida que estão em uso na tela.
const carregadas = new Set<string>()
watch(() => [tema.value, atual.value.fontes] as const, () => {
  if (!tema.value) return
  const novas = Object.values(atual.value.fontes).filter((f) => !carregadas.has(f))
  const href = urlFontes(novas, tema.value.fontes_disponiveis)
  if (!href || typeof document === 'undefined') return
  novas.forEach((f) => carregadas.add(f))
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = href
  document.head.appendChild(link)
}, { deep: true })

function aplicar(t: SiteTema) {
  tema.value = t
  atual.value = estadoDoTema(t)
  erros.value = {}
}

async function carregar() {
  carregando.value = true
  falhaCarga.value = ''
  try {
    aplicar((await obterTema()).data)
    carregarPrevia()
  } catch (e) {
    falhaCarga.value = 'Não foi possível carregar a aparência: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}

function definirCor(token: string, valor: string) {
  atual.value = { ...atual.value, cores: { ...atual.value.cores, [token]: valor.trim() } }
  const { [`cores.${token}`]: _, ...resto } = erros.value
  erros.value = resto
}

function definirFonte(token: string, valor: string) {
  atual.value = { ...atual.value, fontes: { ...atual.value.fontes, [token]: valor } }
}

function descartar() {
  if (tema.value) aplicar(tema.value)
}

async function salvar() {
  if (!tema.value) return
  const locais = errosLocais(atual.value)
  if (Object.keys(locais).length) {
    erros.value = locais
    emit('toast', { msg: 'Corrija as cores destacadas antes de salvar.', type: 'warning' })
    return
  }

  salvando.value = true
  try {
    const r = await salvarTema(calcularCorpo(tema.value, atual.value))
    aplicar(r.data)
    emit('toast', { msg: 'Aparência salva.', type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
    carregarPrevia()
  } catch (e) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) {
      erros.value = doServidor
      emit('toast', { msg: 'A aparência não foi salva: corrija os campos destacados.', type: 'danger' })
    } else {
      emit('toast', { msg: 'Erro ao salvar: ' + mensagemDe(e), type: 'danger' })
    }
  } finally {
    salvando.value = false
  }
}

async function restaurarTudo() {
  if (!window.confirm('Restaurar todas as cores e fontes para o padrão do template? As personalizações serão perdidas.')) return
  salvando.value = true
  try {
    aplicar((await restaurarTema()).data)
    emit('toast', { msg: 'Aparência restaurada para o padrão do template.', type: 'success' })
    carregarPrevia()
  } catch (e) {
    emit('toast', { msg: 'Erro ao restaurar: ' + mensagemDe(e), type: 'danger' })
  } finally {
    salvando.value = false
  }
}

/** Prévia da página inicial com o link assinado (funciona também se ela estiver em rascunho). */
async function carregarPrevia() {
  erroPrevia.value = ''
  try {
    const home = (await listarPaginas({ tipo: 'home' })).data[0]
    if (!home) {
      previaUrl.value = ''
      erroPrevia.value = 'O site ainda não tem página inicial.'
      return
    }
    previaUrl.value = (await tokenPreview(home.id)).data.url
    previaChave.value += 1
  } catch (e) {
    previaUrl.value = ''
    erroPrevia.value = 'Não foi possível gerar a prévia: ' + mensagemDe(e)
  }
}

function aoFechar(e: BeforeUnloadEvent) {
  if (sujo.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

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
.sujo { align-self: center; font-size: .85rem; color: var(--accent); }
.colunas { display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, .8fr); gap: 14px; align-items: start; }
@media (max-width: 1100px) { .colunas { grid-template-columns: 1fr; } }
.coluna-form { display: flex; flex-direction: column; gap: 14px; }
.bloco { padding: 1rem 1.25rem; }
.bloco h2 { margin: 0 0 10px; font-size: 1.05rem; }
.token { padding: 8px 0; border-bottom: 1px solid var(--border); }
.token:last-child { border-bottom: 0; }
.token--erro .hex { border-color: var(--danger); }
.token-nome { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
.token-controle { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.hex { max-width: 130px; font-family: monospace; }
.padrao { display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; color: var(--ink-dim); }
.amostra { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid var(--border); }
.selo { margin-left: 8px; font-size: .7rem; font-weight: 600; color: var(--accent); }
.amostra-texto { margin: 6px 0 0; font-size: 1.1rem; }
.erro { margin: 4px 0 0; font-size: .82rem; color: var(--danger); }
.mudo { color: var(--ink-dim); }
.avisos { padding: 10px 14px; border-left: 4px solid var(--accent); }
.avisos ul { margin: 4px 0; padding-left: 18px; font-size: .88rem; }
.previa { padding: 12px; position: sticky; top: 12px; }
.previa-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.previa iframe { width: 100%; height: 640px; border: 0; border-radius: 6px; background: #fff; }
</style>
