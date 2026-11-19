<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#/noticias" @click.prevent="emit('set-view', 'noticias')">Notícias</a>
      <i class="bi bi-chevron-right"></i>
      <span>Categorias</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Categorias das Notícias</h1>
        <p>Organize os artigos do site por assunto. Uma categoria com notícias não pode ser excluída.</p>
      </div>
      <button class="btn btn-accent" data-nova @click="abrirNova"><i class="bi bi-plus-lg"></i> Nova categoria</button>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <div v-else class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Endereço (slug)</th>
              <th class="center">Notícias</th>
              <th class="center">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in categorias" :key="c.id" :data-categoria="c.id">
              <td>
                <strong>{{ c.name }}</strong>
                <div v-if="c.meta_description" class="descricao">{{ c.meta_description }}</div>
              </td>
              <td><code>{{ c.slug }}</code></td>
              <td class="center" data-total>{{ c.total_noticias }}</td>
              <td class="center nowrap">
                <button class="btn btn-ghost btn-sm" data-editar title="Editar" @click="abrirEdicao(c)"><i class="bi bi-pencil-square"></i></button>
                <button
                  class="btn btn-ghost btn-sm"
                  data-excluir
                  :disabled="c.total_noticias > 0"
                  :title="c.total_noticias > 0 ? 'Há notícias nesta categoria' : 'Excluir'"
                  @click="excluir(c)"
                ><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!categorias.length">
              <td colspan="4" class="table-empty"><i class="bi bi-tags"></i><span>Nenhuma categoria cadastrada.</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ModalSite v-if="modal" :titulo="modal.id ? 'Editar categoria' : 'Nova categoria'" :largura="520" @fechar="modal = null">
      <div class="form-group">
        <label class="form-label required-field" for="cat-nome">Nome</label>
        <input id="cat-nome" class="form-control" type="text" maxlength="100" v-model="form.name" />
        <small v-if="erros.name" class="text-danger" data-campo="name">{{ erros.name }}</small>
      </div>
      <div class="form-group">
        <label class="form-label" for="cat-slug">Endereço (slug)</label>
        <input id="cat-slug" class="form-control" type="text" maxlength="120" v-model="form.slug" :placeholder="slugSugerido" />
        <small v-if="erros.slug" class="text-danger" data-campo="slug">{{ erros.slug }}</small>
        <small v-else class="ajuda">Em branco: gerado a partir do nome.</small>
      </div>
      <div class="form-group">
        <label class="form-label" for="cat-meta">Descrição (SEO)</label>
        <textarea id="cat-meta" class="form-control" rows="3" maxlength="160" v-model="form.meta_description"></textarea>
        <small v-if="erros.meta_description" class="text-danger" data-campo="meta_description">{{ erros.meta_description }}</small>
      </div>
      <template #rodape>
        <button class="btn btn-ghost" @click="modal = null">Cancelar</button>
        <button class="btn btn-accent" data-salvar :disabled="salvando" @click="salvar">{{ salvando ? 'Salvando…' : 'Salvar' }}</button>
      </template>
    </ModalSite>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import ModalSite from '../../components/site/ModalSite.vue'
import {
  atualizarCategoriaNoticia, criarCategoriaNoticia, errosDeCampo, excluirCategoriaNoticia, listarCategoriasNoticias,
} from '../../services/siteApi'
import type { SiteNoticiaCategoria } from '../../types/site'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void; (e: 'set-view', v: string): void }>()

const categorias = ref<SiteNoticiaCategoria[]>([])
const carregando = ref(true)
const falhaCarga = ref('')
const modal = ref<{ id: number | null } | null>(null)
const salvando = ref(false)
const form = reactive({ name: '', slug: '', meta_description: '' })
const erros = reactive<Record<string, string>>({})

const semAcento = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '')
const slugSugerido = computed(() =>
  semAcento(form.name).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''))

async function carregar() {
  carregando.value = true
  falhaCarga.value = ''
  try {
    categorias.value = (await listarCategoriasNoticias()).data
  } catch (e) {
    falhaCarga.value = (e as Error).message || 'Não foi possível carregar as categorias.'
  } finally {
    carregando.value = false
  }
}

function limparErros() {
  for (const k of Object.keys(erros)) delete erros[k]
}

function abrirNova() {
  Object.assign(form, { name: '', slug: '', meta_description: '' })
  limparErros()
  modal.value = { id: null }
}

function abrirEdicao(c: SiteNoticiaCategoria) {
  Object.assign(form, { name: c.name, slug: c.slug, meta_description: c.meta_description ?? '' })
  limparErros()
  modal.value = { id: c.id }
}

async function salvar() {
  limparErros()
  const nome = form.name.trim()
  if (!nome) {
    erros.name = 'Informe o nome da categoria.'
    return
  }
  const entrada = { name: nome, slug: form.slug.trim(), meta_description: form.meta_description.trim() }
  salvando.value = true
  try {
    if (modal.value?.id) await atualizarCategoriaNoticia(modal.value.id, entrada)
    else await criarCategoriaNoticia(entrada)
    emit('toast', { msg: modal.value?.id ? 'Categoria atualizada.' : 'Categoria criada.', type: 'success' })
    modal.value = null
    await carregar()
  } catch (e) {
    const porCampo = errosDeCampo(e)
    if (Object.keys(porCampo).length) Object.assign(erros, porCampo)
    else emit('toast', { msg: (e as Error).message || 'Não foi possível salvar.', type: 'danger' })
  } finally {
    salvando.value = false
  }
}

async function excluir(c: SiteNoticiaCategoria) {
  if (!window.confirm(`Excluir a categoria "${c.name}"?`)) return
  try {
    await excluirCategoriaNoticia(c.id)
    emit('toast', { msg: 'Categoria excluída.', type: 'success' })
    await carregar()
  } catch (e) {
    const porCampo = errosDeCampo(e)
    emit('toast', { msg: Object.values(porCampo)[0] || (e as Error).message || 'Não foi possível excluir.', type: 'danger' })
  }
}

onMounted(carregar)
</script>

<style scoped>
.descricao { font-size: .78rem; color: var(--ink-muted); }
.ajuda { color: var(--ink-muted); }
</style>
