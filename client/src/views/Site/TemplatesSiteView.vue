<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Templates do Site</h1>
        <p>Instale, ative e exporte templates (.zip). Ativar um template nunca apaga nem altera o conteúdo do site.</p>
      </div>
      <div class="page-header-actions">
        <label class="btn btn-primary" data-enviar>
          <i class="bi bi-upload"></i> Enviar template (.zip)
          <input ref="entrada" type="file" accept=".zip,application/zip,application/x-zip-compressed" class="oculto" data-arquivo @change="aoEscolher" />
        </label>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>
    <p v-else-if="!templates.length" class="mudo">Nenhum template instalado.</p>

    <ul v-else class="galeria">
      <li v-for="t in templates" :key="t.slug" class="cartao" :class="{ 'cartao--ativo': t.ativo }" :data-template="t.slug">
        <div class="previa">
          <img v-if="t.preview_url" :src="t.preview_url" :alt="'Prévia do template ' + t.nome" loading="lazy" />
          <i v-else class="bi bi-layout-text-window-reverse" aria-hidden="true"></i>
          <span v-if="t.ativo" class="selo-ativo" data-ativo>Ativo</span>
        </div>
        <div class="info">
          <h3>{{ t.nome }}</h3>
          <small>Versão {{ t.versao }}{{ t.autor ? ' · ' + t.autor : '' }}</small>
          <small data-origem>{{ ROTULO_ORIGEM[t.origem] ?? t.origem }} · {{ t.motor }}</small>
          <p v-if="t.descricao" class="descricao">{{ t.descricao }}</p>
        </div>
        <div class="acoes">
          <button v-if="!t.ativo" class="btn btn-primary btn-sm" data-ativar @click="pedirAtivacao(t)"><i class="bi bi-check2-circle"></i> Ativar</button>
          <button class="btn btn-ghost btn-sm" data-exemplo title="Adicionar as páginas de exemplo do template" @click="abrirExemplo(t)"><i class="bi bi-file-earmark-plus"></i> Páginas de exemplo</button>
          <button class="btn btn-ghost btn-sm" data-exportar @click="exportando = t"><i class="bi bi-download"></i> Exportar</button>
          <button class="btn btn-danger btn-sm" data-remover :disabled="!t.removivel" :title="t.removivel ? 'Remover template' : t.ativo ? 'O template ativo não pode ser removido' : 'Templates do sistema não podem ser removidos'" @click="remover(t)">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </li>
    </ul>

    <!-- Envio do .zip -->
    <ModalSite v-if="envio" titulo="Enviar template" :largura="640" @fechar="fecharEnvio">
      <p><strong>{{ envio.arquivo.name }}</strong> <small class="mudo">({{ formatarTamanho(envio.arquivo.size) }})</small></p>

      <template v-if="!envio.resultado && !envio.relatorio">
        <p v-if="envio.resumo" class="resumo" :class="'resumo--' + envio.resumo.acao" data-resumo>{{ envio.resumo.texto }}</p>
        <p v-else class="mudo" data-sem-resumo>Não foi possível ler o pacote no navegador; ele será validado no envio.</p>

        <div v-if="envio.enviando" class="progresso" role="progressbar" :aria-valuenow="Math.round(envio.progresso * 100)" aria-valuemin="0" aria-valuemax="100" data-progresso>
          <div class="progresso-barra" :style="{ width: Math.round(envio.progresso * 100) + '%' }"></div>
          <span>{{ envio.progresso >= 1 ? 'Validando o pacote…' : Math.round(envio.progresso * 100) + '%' }}</span>
        </div>
        <p v-if="envio.erroGeral" class="erro" role="alert">{{ envio.erroGeral }}</p>
      </template>

      <!-- Sucesso -->
      <div v-if="envio.resultado" data-sucesso>
        <p class="ok"><i class="bi bi-check-circle"></i>
          {{ envio.resultado.atualizado ? 'Template atualizado' : 'Template instalado' }}: <strong>{{ envio.resultado.data.nome }}</strong> {{ envio.resultado.data.versao }}.
          Ele ainda não está ativo.
        </p>
        <template v-if="envio.resultado.avisos.length">
          <strong>Avisos</strong>
          <ul class="relatorio" data-avisos><li v-for="a in envio.resultado.avisos" :key="a">{{ a }}</li></ul>
        </template>
      </div>

      <!-- Relatório de validação (422) -->
      <div v-if="envio.relatorio" data-relatorio>
        <p class="erro"><i class="bi bi-x-octagon"></i> O pacote foi recusado e nada foi instalado.</p>
        <strong>Erros que bloqueiam</strong>
        <ul class="relatorio relatorio--erro" data-erros>
          <li v-for="l in envio.relatorio.erros" :key="l.onde + l.mensagem"><code>{{ l.onde }}</code> {{ l.mensagem }}</li>
        </ul>
        <template v-if="envio.relatorio.avisos.length">
          <strong>Avisos</strong>
          <ul class="relatorio" data-avisos><li v-for="a in envio.relatorio.avisos" :key="a">{{ a }}</li></ul>
        </template>
      </div>

      <template #rodape>
        <template v-if="envio.resultado">
          <button class="btn btn-ghost" @click="fecharEnvio">Fechar</button>
          <button class="btn btn-primary" data-ativar-agora @click="ativarRecemInstalado">Ativar agora</button>
        </template>
        <template v-else-if="envio.relatorio">
          <button class="btn btn-primary" @click="fecharEnvio">Fechar</button>
        </template>
        <template v-else>
          <button class="btn btn-ghost" :disabled="envio.enviando" @click="fecharEnvio">Cancelar</button>
          <button class="btn btn-accent" data-confirmar-envio :disabled="envio.enviando || envio.resumo?.acao === 'bloqueado'" @click="enviar">
            {{ envio.enviando ? 'Enviando…' : envio.resumo?.acao === 'atualizar' ? 'Atualizar' : 'Instalar' }}
          </button>
        </template>
      </template>
    </ModalSite>

    <!-- Confirmação de ativação -->
    <ModalSite v-if="ativacao" :titulo="`Ativar “${ativacao.template.nome}”`" :largura="560" @fechar="fecharAtivacao">
      <template v-if="!ativacao.relatorio">
        <p>O visual do site passa a ser o deste template.</p>
        <ul>
          <li>O <strong>conteúdo é mantido</strong>: páginas, textos, menus e imagens não são apagados nem sobrescritos.</li>
          <li>Seções de um tipo que o novo template não tem ficam <strong>ocultas</strong>, com o conteúdo preservado. Se você voltar ao template anterior, elas reaparecem.</li>
          <li>Só em um site <strong>sem nenhuma página</strong> o conteúdo de exemplo do template é importado.</li>
        </ul>
        <p v-if="ativacao.erro" class="erro" role="alert">{{ ativacao.erro }}</p>
      </template>

      <!-- Relatório de compatibilidade -->
      <div v-else data-relatorio-ativacao>
        <p class="ok"><i class="bi bi-check-circle"></i> Template ativado.</p>
        <p v-if="ativacao.relatorio.seed" class="mudo">O site não tinha páginas: o conteúdo de exemplo do template foi importado.</p>

        <template v-if="grupos.length">
          <strong>Seções que precisam de atenção</strong>
          <div v-for="g in grupos" :key="g.paginaId" class="grupo" :data-grupo="g.paginaId">
            <div class="grupo-topo">
              <span>{{ g.pagina }}</span>
              <button class="btn btn-ghost btn-sm" data-abrir-editor @click="irParaPagina(g.paginaId)">Abrir no editor</button>
            </div>
            <ul class="relatorio">
              <li v-for="s in g.itens" :key="s.id">Seção <code>{{ s.tipo }}</code> (#{{ s.id }}): {{ descreverAcao(s.acao) }}</li>
            </ul>
          </div>
        </template>
        <p v-else class="mudo" data-sem-pendencias>Nenhuma seção ficou incompatível.</p>

        <template v-if="ativacao.relatorio.avisos.length">
          <strong>Avisos</strong>
          <ul class="relatorio" data-avisos><li v-for="a in ativacao.relatorio.avisos" :key="a">{{ a }}</li></ul>
        </template>
      </div>

      <template #rodape>
        <template v-if="ativacao.relatorio"><button class="btn btn-primary" @click="fecharAtivacao">Fechar</button></template>
        <template v-else>
          <button class="btn btn-ghost" :disabled="ativacao.ativando" @click="fecharAtivacao">Cancelar</button>
          <button class="btn btn-accent" data-confirmar-ativacao :disabled="ativacao.ativando" @click="confirmarAtivacao">{{ ativacao.ativando ? 'Ativando…' : 'Ativar template' }}</button>
        </template>
      </template>
    </ModalSite>

    <!-- Páginas de exemplo -->
    <ModalSite v-if="exemplo" :titulo="`Páginas de exemplo de “${exemplo.template.nome}”`" :largura="560" @fechar="exemplo = null">
      <p v-if="exemplo.carregando" class="mudo">Verificando o que seria criado…</p>
      <p v-else-if="exemplo.erro" class="erro" role="alert" data-erro-exemplo>{{ exemplo.erro }}</p>

      <template v-else-if="exemplo.previa">
        <template v-if="exemplo.previa.criar.length">
          <p>Serão criadas, como <strong>rascunho</strong>, as páginas abaixo. Nada que já existe é alterado.</p>
          <ul class="relatorio" data-criar>
            <li v-for="p in exemplo.previa.criar" :key="p.slug"><strong>{{ p.titulo }}</strong> <code>/pagina/{{ p.slug }}</code> · {{ p.secoes }} {{ p.secoes === 1 ? 'seção' : 'seções' }}</li>
          </ul>
        </template>
        <p v-else class="mudo" data-nada-a-criar>Todas as páginas de exemplo já existem no site. Nada será criado.</p>

        <template v-if="exemplo.previa.ignoradas.length">
          <small class="mudo">Já existem e serão mantidas: {{ exemplo.previa.ignoradas.join(', ') }}.</small>
        </template>
        <p v-if="exemplo.feito" class="ok" data-criadas><i class="bi bi-check-circle"></i> {{ exemplo.feito }}</p>
      </template>

      <template #rodape>
        <button class="btn btn-ghost" @click="exemplo = null">{{ exemplo.feito ? 'Fechar' : 'Cancelar' }}</button>
        <button v-if="!exemplo.feito" class="btn btn-accent" data-confirmar-exemplo :disabled="!exemplo.previa?.criar.length || exemplo.criando" @click="criarExemplo">
          {{ exemplo.criando ? 'Criando…' : 'Criar páginas' }}
        </button>
      </template>
    </ModalSite>

    <!-- Exportar -->
    <ModalSite v-if="exportando" :titulo="`Exportar “${exportando.nome}”`" :largura="520" @fechar="exportando = null">
      <p>Baixa um <code>.zip</code> que pode ser instalado em outro site.</p>
      <ul class="exportar">
        <li><a class="btn btn-primary" :href="urlExportarTemplate(exportando.slug)" download data-exportar-sem><i class="bi bi-download"></i> Só o template</a>
          <small>Layouts, seções, estilos e conteúdo de exemplo do template.</small></li>
        <li><a class="btn btn-ghost" :href="urlExportarTemplate(exportando.slug, true)" download data-exportar-com><i class="bi bi-download"></i> Template com o conteúdo atual do site</a>
          <small>O conteúdo atual (páginas, menus e textos) vira o conteúdo inicial do pacote. Imagens da biblioteca e configurações de imagem não vão.</small></li>
      </ul>
    </ModalSite>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ModalSite from '../../components/site/ModalSite.vue'
import {
  ativarTemplate, avisosDe, errosDeCampo, instalarTemplateComProgresso, listarTemplates, paginasExemploPrevia,
  paginasExemploTemplate, removerTemplate, urlExportarTemplate, type RespostaInstalacao,
} from '../../services/siteApi'
import type { SiteTemplate, SiteTemplateAtivacao } from '../../types/site'
import { formatarTamanho } from '../../utils/midias'
import {
  ROTULO_ORIGEM, agruparPorPagina, descreverAcao, linhasDoRelatorio, resumoDeInstalacao, type ResumoDeInstalacao,
} from '../../utils/templatesSite'
import { lerManifestoDoZip } from '../../utils/zipManifesto'

const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: string }): void
  (e: 'abrir-pagina', id: number): void
}>()

const templates = ref<SiteTemplate[]>([])
const carregando = ref(true)
const falhaCarga = ref('')
const entrada = ref<HTMLInputElement | null>(null)

interface Envio {
  arquivo: File
  resumo: ResumoDeInstalacao | null
  enviando: boolean
  progresso: number
  erroGeral: string
  resultado: RespostaInstalacao | null
  relatorio: { erros: Array<{ onde: string; mensagem: string }>; avisos: string[] } | null
}
const envio = ref<Envio | null>(null)
const ativacao = ref<{ template: SiteTemplate; ativando: boolean; erro: string; relatorio: SiteTemplateAtivacao | null } | null>(null)
const exemplo = ref<{
  template: SiteTemplate; carregando: boolean; erro: string; criando: boolean; feito: string
  previa: { criar: Array<{ slug: string; titulo: string; secoes: number }>; ignoradas: string[] } | null
} | null>(null)
const exportando = ref<SiteTemplate | null>(null)

const grupos = computed(() => agruparPorPagina(ativacao.value?.relatorio?.secoes ?? []))
const mensagemDe = (e: unknown) => Object.values(errosDeCampo(e))[0] ?? (e as Error)?.message ?? 'erro desconhecido'

async function carregar() {
  carregando.value = true
  falhaCarga.value = ''
  try {
    templates.value = (await listarTemplates()).data
  } catch (e) {
    falhaCarga.value = 'Não foi possível carregar os templates: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}
onMounted(carregar)

// ---- enviar .zip
async function aoEscolher(e: Event) {
  const input = e.target as HTMLInputElement
  const arquivo = input.files?.[0]
  input.value = ''
  if (!arquivo) return

  if (!/\.zip$/i.test(arquivo.name)) {
    emit('toast', { msg: 'O pacote deve ser um arquivo .zip.', type: 'warning' })
    return
  }
  envio.value = { arquivo, resumo: null, enviando: false, progresso: 0, erroGeral: '', resultado: null, relatorio: null }
  const este = envio.value
  const manifesto = await lerManifestoDoZip(arquivo)
  // O usuário pode ter fechado ou trocado o envio enquanto o pacote era lido.
  if (envio.value === este && manifesto) este.resumo = resumoDeInstalacao(manifesto, templates.value)
}

async function enviar() {
  const atual = envio.value
  if (!atual || atual.enviando) return
  atual.enviando = true
  atual.progresso = 0
  atual.erroGeral = ''
  try {
    atual.resultado = await instalarTemplateComProgresso(atual.arquivo, (f) => { atual.progresso = f })
    await carregar()
    emit('toast', { msg: atual.resultado.atualizado ? 'Template atualizado.' : 'Template instalado.', type: 'success' })
  } catch (e) {
    const erros = errosDeCampo(e)
    if (Object.keys(erros).length) {
      atual.relatorio = { erros: linhasDoRelatorio(erros), avisos: avisosDe(e) }
    } else {
      atual.erroGeral = 'Erro ao enviar: ' + mensagemDe(e)
    }
  } finally {
    atual.enviando = false
  }
}

const fecharEnvio = () => { if (!envio.value?.enviando) envio.value = null }

function ativarRecemInstalado() {
  const slug = envio.value?.resultado?.data.slug
  const t = templates.value.find((x) => x.slug === slug)
  envio.value = null
  if (t) pedirAtivacao(t)
}

// ---- ativar
const pedirAtivacao = (t: SiteTemplate) => { ativacao.value = { template: t, ativando: false, erro: '', relatorio: null } }

async function confirmarAtivacao() {
  const a = ativacao.value
  if (!a) return
  a.ativando = true
  a.erro = ''
  try {
    a.relatorio = (await ativarTemplate(a.template.slug)).data
    emit('toast', { msg: `Template "${a.template.nome}" ativado.`, type: 'success' })
    await carregar()
  } catch (e) {
    a.erro = 'Não foi possível ativar: ' + mensagemDe(e)
  } finally {
    a.ativando = false
  }
}

const fecharAtivacao = () => { if (!ativacao.value?.ativando) ativacao.value = null }

function irParaPagina(id: number) {
  ativacao.value = null
  emit('abrir-pagina', id)
}

// ---- páginas de exemplo
async function abrirExemplo(t: SiteTemplate) {
  exemplo.value = { template: t, carregando: true, erro: '', criando: false, feito: '', previa: null }
  const atual = exemplo.value
  try {
    atual.previa = (await paginasExemploPrevia(t.slug)).data
  } catch (e) {
    atual.erro = mensagemDe(e)
  } finally {
    atual.carregando = false
  }
}

async function criarExemplo() {
  const atual = exemplo.value
  if (!atual) return
  atual.criando = true
  try {
    const r = (await paginasExemploTemplate(atual.template.slug)).data
    atual.feito = `${r.paginas.length} ${r.paginas.length === 1 ? 'página criada' : 'páginas criadas'} como rascunho.`
    emit('toast', { msg: atual.feito, type: 'success' })
    if (Object.keys(r.erros ?? {}).length) emit('toast', { msg: 'Algumas páginas não puderam ser criadas: ' + Object.values(r.erros).join('; '), type: 'warning' })
  } catch (e) {
    atual.erro = 'Não foi possível criar as páginas: ' + mensagemDe(e)
  } finally {
    atual.criando = false
  }
}

// ---- remover
async function remover(t: SiteTemplate) {
  if (!t.removivel) return
  if (!window.confirm(`Remover o template "${t.nome}"? Os arquivos dele são apagados do servidor. O conteúdo do site não é afetado.`)) return
  try {
    await removerTemplate(t.slug)
    emit('toast', { msg: 'Template removido.', type: 'success' })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}
</script>

<style scoped>
.oculto { display: none; }
.mudo { color: var(--ink-dim); }
.erro { color: var(--danger); }
.ok { color: var(--success); }
.galeria { list-style: none; margin: 14px 0 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.cartao { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface); display: flex; flex-direction: column; }
.cartao--ativo { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(1, 81, 110, .2); }
.previa { position: relative; aspect-ratio: 16 / 10; background: #f3f1ec; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--ink-dim); }
.previa img { width: 100%; height: 100%; object-fit: cover; }
.selo-ativo { position: absolute; top: 8px; right: 8px; background: var(--primary); color: #fff; font-size: .75rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
.info { padding: 12px 14px; display: flex; flex-direction: column; gap: 2px; flex: 1; }
.info h3 { margin: 0; font-size: 1.05rem; }
.info small { color: var(--ink-dim); }
.descricao { margin: 6px 0 0; font-size: .85rem; }
.acoes { display: flex; flex-wrap: wrap; gap: 6px; padding: 10px 14px 14px; }
.resumo { padding: 10px 12px; border-radius: 8px; background: #eef6fb; }
.resumo--bloqueado, .resumo--versao-antiga { background: #fff7ed; }
.progresso { position: relative; height: 22px; background: #e9e6df; border-radius: 6px; overflow: hidden; margin: 10px 0; }
.progresso-barra { position: absolute; inset: 0 auto 0 0; background: var(--primary); transition: width .15s; }
.progresso span { position: relative; display: block; text-align: center; font-size: .8rem; line-height: 22px; color: #fff; mix-blend-mode: difference; }
.relatorio { margin: 6px 0 12px; padding-left: 18px; font-size: .88rem; }
.relatorio--erro { color: var(--danger); }
.grupo { border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; margin: 8px 0; }
.grupo-topo { display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
.exportar { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.exportar li { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
.exportar small { color: var(--ink-dim); }
</style>
