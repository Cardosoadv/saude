<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#/noticias" @click.prevent="emit('set-view', 'noticias')">Notícias</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ idEdicao ? 'Editar notícia' : 'Nova notícia' }}</span>
    </div>

    <div class="page-header">
      <div>
        <h1>{{ idEdicao ? 'Editar notícia' : 'Nova notícia' }}</h1>
        <p>O artigo só aparece no site quando está <strong>publicado</strong>.</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-ghost" @click="emit('set-view', 'noticias')">Voltar</button>
        <button class="btn btn-accent" data-salvar :disabled="salvando || carregando" @click="salvar">
          {{ salvando ? 'Salvando…' : 'Salvar' }}
        </button>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>

    <div v-else class="card" style="padding: 20px;">
      <div class="form-group">
        <label class="form-label required-field" for="not-titulo">Título</label>
        <input id="not-titulo" class="form-control" type="text" maxlength="255" v-model="form.title" />
        <small v-if="erros.title" class="text-danger">{{ erros.title }}</small>
      </div>

      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label required-field" for="not-categoria">Categoria</label>
          <select id="not-categoria" class="form-control form-select" v-model="form.category_id">
            <option value="">Selecione…</option>
            <option v-for="c in categorias" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <small v-if="erros.category_id" class="text-danger">{{ erros.category_id }}</small>
        </div>
        <div class="form-group">
          <label class="form-label" for="not-status">Situação</label>
          <select id="not-status" class="form-control form-select" v-model="form.status">
            <option value="draft">Rascunho</option>
            <option value="published">Publicada</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="not-data">Data de publicação</label>
          <input id="not-data" class="form-control" type="datetime-local" v-model="form.published_at" />
          <small v-if="erros.published_at" class="text-danger">{{ erros.published_at }}</small>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label required-field" for="not-resumo">Resumo</label>
        <textarea id="not-resumo" class="form-control" rows="2" maxlength="500" v-model="form.summary"></textarea>
        <small v-if="erros.summary" class="text-danger">{{ erros.summary }}</small>
      </div>

      <div class="form-group">
        <label class="form-label required-field">Conteúdo</label>
        <DocumentoEditor v-model="form.content" :show-placeholder-picker="false" />
        <small v-if="erros.content" class="text-danger">{{ erros.content }}</small>
      </div>

      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label" for="not-meta-titulo">Título para buscadores (SEO)</label>
          <input id="not-meta-titulo" class="form-control" type="text" maxlength="255" v-model="form.meta_title" />
        </div>
        <div class="form-group">
          <label class="form-label" for="not-meta-desc">Descrição para buscadores (até 160)</label>
          <input id="not-meta-desc" class="form-control" type="text" maxlength="160" v-model="form.meta_description" />
          <small v-if="erros.meta_description" class="text-danger">{{ erros.meta_description }}</small>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DocumentoEditor from '../../components/editor/DocumentoEditor.vue'
import {
  atualizarNoticia, criarNoticia, errosDeCampo, listarCategoriasNoticias, obterNoticia,
} from '../../services/siteApi'
import type { SiteNoticiaCategoria } from '../../types/site'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void; (e: 'set-view', v: string): void }>()

/** O id vem do hash: `#/noticias/editar/{id}`; sem ele, é uma notícia nova. */
const idEdicao = (() => {
  const m = window.location.hash.match(/^#\/noticias\/editar\/(\d+)/)
  return m ? Number(m[1]) : null
})()

const categorias = ref<SiteNoticiaCategoria[]>([])
const carregando = ref(true)
const salvando = ref(false)
const erros = reactive<Record<string, string>>({})
const form = reactive({
  title: '', category_id: '', status: 'draft' as 'draft' | 'published', summary: '', content: '',
  meta_title: '', meta_description: '', published_at: '',
})

/** `2026-03-12 10:21:54` → `2026-03-12T10:21` (formato do datetime-local). */
const paraInput = (v?: string | null) => (v ? v.replace(' ', 'T').slice(0, 16) : '')

async function carregar() {
  try {
    categorias.value = (await listarCategoriasNoticias()).data
    if (idEdicao) {
      const n = (await obterNoticia(idEdicao)).data
      Object.assign(form, {
        title: n.title, category_id: String(n.category_id), status: n.status, summary: n.summary ?? '', content: n.content ?? '',
        meta_title: n.meta_title ?? '', meta_description: n.meta_description ?? '', published_at: paraInput(n.published_at),
      })
    }
  } catch (e) {
    emit('toast', { msg: (e as Error).message || 'Não foi possível carregar a notícia.', type: 'danger' })
  } finally {
    carregando.value = false
  }
}

async function salvar() {
  for (const k of Object.keys(erros)) delete erros[k]
  const entrada = {
    title: form.title.trim(), category_id: form.category_id, status: form.status, summary: form.summary.trim(),
    content: form.content, meta_title: form.meta_title.trim(), meta_description: form.meta_description.trim(),
    published_at: form.published_at,
  }
  salvando.value = true
  try {
    if (idEdicao) await atualizarNoticia(idEdicao, entrada)
    else await criarNoticia(entrada)
    emit('toast', { msg: idEdicao ? 'Notícia atualizada.' : 'Notícia criada.', type: 'success' })
    emit('set-view', 'noticias')
  } catch (e) {
    const porCampo = errosDeCampo(e)
    if (Object.keys(porCampo).length) Object.assign(erros, porCampo)
    else emit('toast', { msg: (e as Error).message || 'Não foi possível salvar.', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

onMounted(carregar)
</script>
