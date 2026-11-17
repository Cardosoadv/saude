<template>
  <div class="biblioteca">
    <!-- Envio por arrastar e soltar -->
    <div
      class="envio"
      :class="{ 'envio--sobre': sobre, 'envio--ocupado': enviando }"
      data-envio
      @dragover.prevent="sobre = true"
      @dragleave.prevent="sobre = false"
      @drop.prevent="aoSoltar"
    >
      <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
      <span>Arraste imagens aqui ou</span>
      <label class="btn btn-ghost btn-sm">
        escolha arquivos
        <input ref="entrada" type="file" multiple :accept="ACCEPT_INPUT" class="envio-input" data-arquivos @change="aoEscolher" />
      </label>
      <small>JPEG, PNG, GIF, WebP ou ICO, até {{ formatarTamanho(TAMANHO_MAXIMO) }} cada.</small>
    </div>

    <ul v-if="resultados.length" class="envios" aria-label="Resultado do envio">
      <li v-for="(r, i) in resultados" :key="i" :class="r.ok ? 'envios--ok' : 'envios--erro'">
        <i :class="['bi', r.ok ? 'bi-check-circle' : 'bi-exclamation-triangle']" aria-hidden="true"></i>
        {{ r.ok ? `"${r.nome}" enviado.` : r.erro }}
      </li>
    </ul>

    <!-- Busca -->
    <div class="busca">
      <input
        type="search"
        class="form-control"
        v-model="busca"
        placeholder="Buscar por nome ou texto alternativo"
        aria-label="Buscar mídias"
        @keyup.enter="buscarAgora"
      />
      <span v-if="pager" class="busca-total">{{ pager.total }} {{ pager.total === 1 ? 'imagem' : 'imagens' }}</span>
    </div>

    <p v-if="erro" class="erro" role="alert">{{ erro }}</p>
    <p v-else-if="!carregando && !itens.length" class="vazio">
      {{ buscaAplicada ? 'Nenhuma imagem encontrada.' : 'A biblioteca ainda não tem imagens.' }}
    </p>

    <!-- Grade -->
    <ul class="grade" :class="{ 'grade--carregando': carregando }">
      <li
        v-for="m in itens"
        :key="m.id"
        class="cartao"
        :class="{ 'cartao--escolhida': modoSelecao && Number(m.id) === selecionadoId }"
        :data-midia="m.id"
      >
        <button
          type="button"
          class="miniatura"
          :title="modoSelecao ? 'Escolher esta imagem' : 'Abrir imagem'"
          @click="modoSelecao ? emit('selecionar', m) : abrir(m)"
        >
          <img :src="m.miniatura_url" :alt="m.alt || m.nome_original" loading="lazy" />
        </button>
        <div class="cartao-info">
          <span class="nome" :title="m.nome_original">{{ m.nome_original }}</span>
          <small>{{ m.largura }}×{{ m.altura }} · {{ formatarTamanho(m.tamanho) }}</small>

          <div v-if="editandoAlt === m.id" class="alt-edicao">
            <input
              class="form-control"
              v-model="altRascunho"
              maxlength="255"
              placeholder="Descreva a imagem"
              aria-label="Texto alternativo"
              data-alt-input
              @keyup.enter="salvarAlt(m)"
              @keyup.esc="editandoAlt = null"
            />
            <button type="button" class="btn btn-accent btn-sm" data-salvar-alt @click="salvarAlt(m)">Salvar</button>
          </div>
          <button v-else type="button" class="alt-texto" :class="{ 'alt-texto--vazio': !m.alt }" data-editar-alt @click="editarAlt(m)">
            <i class="bi bi-pencil" aria-hidden="true"></i> {{ m.alt || 'Sem texto alternativo' }}
          </button>

          <div class="acoes">
            <button type="button" class="btn btn-ghost btn-sm" title="Onde esta imagem é usada" data-usos @click="verUsos(m)">
              <i class="bi bi-diagram-3"></i> Usos
            </button>
            <button type="button" class="btn btn-danger btn-sm" title="Excluir imagem" data-excluir @click="excluir(m)">
              <i class="bi bi-trash"></i>
            </button>
          </div>

          <ul v-if="usosDe === m.id" class="usos" data-lista-usos>
            <li v-if="!usos.length">Não está em uso.</li>
            <li v-for="(u, i) in usos" :key="i">{{ descreverUso(u) }}</li>
          </ul>
        </div>
      </li>
    </ul>

    <!-- Paginação -->
    <nav v-if="pager && pager.last_page > 1" class="paginacao" aria-label="Páginas da biblioteca">
      <button type="button" class="btn btn-ghost btn-sm" :disabled="pager.page <= 1" data-anterior @click="irPara(pager.page - 1)">
        <i class="bi bi-chevron-left"></i> Anterior
      </button>
      <span>Página {{ pager.page }} de {{ pager.last_page }}</span>
      <button type="button" class="btn btn-ghost btn-sm" :disabled="pager.page >= pager.last_page" data-proxima @click="irPara(pager.page + 1)">
        Próxima <i class="bi bi-chevron-right"></i>
      </button>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { esquecerMidia, registrarMidia } from '../../composables/useMidiaCache'
import {
  atualizarAltMidia, enviarMidia, errosDeCampo, excluirMidia, listarMidias, usosMidia,
} from '../../services/siteApi'
import type { SiteMidia, SiteMidiaPager, SiteMidiaUso } from '../../types/site'
import { ACCEPT_INPUT, TAMANHO_MAXIMO, enviarVarios, formatarTamanho, type ResultadoEnvio } from '../../utils/midias'

const props = defineProps<{ modoSelecao?: boolean; selecionadoId?: number | null; porPagina?: number }>()
const emit = defineEmits<{
  (e: 'selecionar', midia: SiteMidia): void
  (e: 'toast', t: { msg: string; type: string }): void
}>()

const itens = ref<SiteMidia[]>([])
const pager = ref<SiteMidiaPager | null>(null)
const carregando = ref(false)
const erro = ref('')
const busca = ref('')
const buscaAplicada = ref('')
const pagina = ref(1)

const sobre = ref(false)
const enviando = ref(false)
const resultados = ref<Array<ResultadoEnvio<SiteMidia>>>([])
const entrada = ref<HTMLInputElement | null>(null)

const editandoAlt = ref<number | null>(null)
const altRascunho = ref('')
const usosDe = ref<number | null>(null)
const usos = ref<SiteMidiaUso[]>([])

const mensagemDe = (e: unknown): string => {
  const campos = errosDeCampo(e)
  return Object.values(campos)[0] ?? (e as Error)?.message ?? 'erro desconhecido'
}

async function carregar() {
  carregando.value = true
  erro.value = ''
  try {
    const r = await listarMidias({ page: pagina.value, per_page: props.porPagina ?? 24, busca: buscaAplicada.value })
    itens.value = r.data
    pager.value = r.pager
    r.data.forEach(registrarMidia)
  } catch (e) {
    erro.value = 'Não foi possível carregar a biblioteca: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}

function buscarAgora() {
  clearTimeout(temporizador)
  buscaAplicada.value = busca.value.trim()
  pagina.value = 1
  return carregar()
}

let temporizador: ReturnType<typeof setTimeout> | undefined
watch(busca, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(buscarAgora, 300)
})

function irPara(p: number) {
  pagina.value = p
  return carregar()
}

// ---- envio
async function enviar(arquivos: File[]) {
  if (!arquivos.length || enviando.value) return
  enviando.value = true
  resultados.value = []
  try {
    const res = await enviarVarios(arquivos, async (a) => (await enviarMidia(a)).data, mensagemDe, (r) => {
      resultados.value = [...resultados.value, r]
      if (r.midia) registrarMidia(r.midia)
    })
    const ok = res.filter((r) => r.ok).length
    if (ok) emit('toast', { msg: ok === 1 ? 'Imagem enviada.' : `${ok} imagens enviadas.`, type: 'success' })
    if (ok < res.length) emit('toast', { msg: `${res.length - ok} arquivo(s) não foram enviados.`, type: 'warning' })
    if (ok) {
      busca.value = ''
      buscaAplicada.value = ''
      pagina.value = 1
      await carregar()
    }
  } finally {
    enviando.value = false
  }
}

function aoSoltar(e: DragEvent) {
  sobre.value = false
  enviar(Array.from(e.dataTransfer?.files ?? []))
}

function aoEscolher(e: Event) {
  const input = e.target as HTMLInputElement
  enviar(Array.from(input.files ?? []))
  input.value = ''
}

const abrir = (m: SiteMidia) => window.open(m.url, '_blank', 'noopener')

// ---- texto alternativo
function editarAlt(m: SiteMidia) {
  editandoAlt.value = m.id
  altRascunho.value = m.alt ?? ''
}

async function salvarAlt(m: SiteMidia) {
  try {
    const r = await atualizarAltMidia(m.id, altRascunho.value.trim() || null)
    itens.value = itens.value.map((x) => (x.id === m.id ? r.data : x))
    registrarMidia(r.data)
    editandoAlt.value = null
    emit('toast', { msg: 'Texto alternativo salvo.', type: 'success' })
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

// ---- usos e exclusão
const ROTULO_USO: Record<SiteMidiaUso['tipo'], (u: any) => string> = {
  configuracao: (u) => `Configuração do site: ${u.chave}`,
  pagina: (u) => `Imagem de compartilhamento da página "${u.titulo}"`,
  secao: (u) => `Seção "${u.secao_tipo}" da página "${u.titulo}"`,
}
const descreverUso = (u: SiteMidiaUso) => ROTULO_USO[u.tipo](u)

async function verUsos(m: SiteMidia) {
  if (usosDe.value === m.id) {
    usosDe.value = null
    return
  }
  try {
    usos.value = (await usosMidia(m.id)).data
    usosDe.value = m.id
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

async function excluir(m: SiteMidia) {
  if (!window.confirm(`Excluir "${m.nome_original}"? Esta ação não pode ser desfeita.`)) return
  try {
    await excluirMidia(m.id)
    esquecerMidia(m.id)
    emit('toast', { msg: 'Imagem excluída.', type: 'success' })
    if (itens.value.length === 1 && pagina.value > 1) pagina.value -= 1
    await carregar()
  } catch (e) {
    // 422 quando a imagem está em uso: a mensagem da API diz onde.
    erro.value = mensagemDe(e)
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

onMounted(carregar)
onBeforeUnmount(() => clearTimeout(temporizador))
defineExpose({ recarregar: carregar })
</script>

<style scoped>
.envio { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px; padding: 16px; border: 2px dashed var(--border); border-radius: 10px; color: var(--ink-dim); text-align: center; }
.envio--sobre { border-color: var(--primary); background: rgba(1, 81, 110, .05); }
.envio--ocupado { opacity: .6; pointer-events: none; }
.envio-input { display: none; }
.envios { list-style: none; margin: 10px 0 0; padding: 0; font-size: .85rem; }
.envios--ok { color: var(--success); }
.envios--erro { color: var(--danger); }
.busca { display: flex; align-items: center; gap: 12px; margin: 14px 0; }
.busca .form-control { max-width: 360px; }
.busca-total { color: var(--ink-dim); font-size: .85rem; }
.erro { color: var(--danger); }
.vazio { color: var(--ink-dim); }
.grade { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
.grade--carregando { opacity: .5; }
.cartao { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--surface); }
.cartao--escolhida { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(1, 81, 110, .25); }
.miniatura { display: block; width: 100%; aspect-ratio: 4 / 3; border: 0; padding: 0; cursor: pointer; background: #f3f1ec; }
.miniatura img { width: 100%; height: 100%; object-fit: contain; }
.cartao-info { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 4px; }
.nome { font-weight: 600; font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.alt-texto { text-align: left; border: 0; background: transparent; padding: 0; cursor: pointer; font-size: .8rem; color: var(--ink); }
.alt-texto--vazio { color: var(--accent); }
.alt-edicao { display: flex; gap: 6px; }
.acoes { display: flex; justify-content: space-between; gap: 6px; margin-top: 4px; }
.usos { margin: 6px 0 0; padding-left: 16px; font-size: .78rem; color: var(--ink-dim); }
.paginacao { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 16px; }
</style>
