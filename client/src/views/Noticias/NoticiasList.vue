<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Notícias do Site</h1>
        <p>Artigos publicados na área de notícias do site da clínica.</p>
      </div>
      <div class="page-header-actions">
        <a class="btn btn-ghost" href="#/noticias/categorias" @click.prevent="emit('set-view', 'noticias-categorias')">
          <i class="bi bi-tags"></i> Categorias
        </a>
        <a class="btn btn-accent" href="#/noticias/nova" data-nova @click.prevent="irPara('#/noticias/nova')">
          <i class="bi bi-plus-lg"></i> Nova notícia
        </a>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar(1)">Tentar de novo</button>
    </div>

    <div v-else class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Categoria</th>
              <th class="center">Situação</th>
              <th class="nowrap">Publicação</th>
              <th class="center">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="n in noticias" :key="n.id" :data-noticia="n.id">
              <td>
                <a :href="`#/noticias/editar/${n.id}`" class="table-link" @click.prevent="irPara(`#/noticias/editar/${n.id}`)">{{ n.title }}</a>
                <div class="descricao"><code>{{ n.slug }}</code></div>
              </td>
              <td>{{ n.category_name || '—' }}</td>
              <td class="center">
                <span class="badge" :class="n.status === 'published' ? 'badge-success' : 'badge-muted'">
                  {{ n.status === 'published' ? 'Publicada' : 'Rascunho' }}
                </span>
              </td>
              <td class="nowrap">{{ n.published_at ? formatDate(n.published_at, true) : '—' }}</td>
              <td class="center nowrap">
                <button class="btn btn-ghost btn-sm" title="Editar" @click="irPara(`#/noticias/editar/${n.id}`)"><i class="bi bi-pencil-square"></i></button>
                <button class="btn btn-ghost btn-sm" data-excluir title="Excluir" @click="excluir(n)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!noticias.length">
              <td colspan="5" class="table-empty"><i class="bi bi-newspaper"></i><span>Nenhuma notícia encontrada.</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="ultimaPagina > 1" class="paginacao">
        <button class="btn btn-ghost btn-sm" :disabled="pagina <= 1" @click="carregar(pagina - 1)">Anterior</button>
        <span>Página {{ pagina }} de {{ ultimaPagina }}</span>
        <button class="btn btn-ghost btn-sm" :disabled="pagina >= ultimaPagina" @click="carregar(pagina + 1)">Próxima</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { formatDate } from '../../services/api'
import { excluirNoticia, listarNoticias } from '../../services/siteApi'
import type { SiteNoticia } from '../../types/site'

const props = defineProps<{ searchQuery?: string }>()
const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void; (e: 'set-view', v: string): void }>()

const noticias = ref<SiteNoticia[]>([])
const carregando = ref(true)
const falhaCarga = ref('')
const pagina = ref(1)
const ultimaPagina = ref(1)

function irPara(hash: string) {
  window.location.hash = hash
}

async function carregar(p = 1) {
  carregando.value = true
  falhaCarga.value = ''
  try {
    const r = await listarNoticias({ search: props.searchQuery || undefined, page: p, limit: 20 })
    noticias.value = r.data
    pagina.value = r.current_page
    ultimaPagina.value = r.last_page
  } catch (e) {
    falhaCarga.value = (e as Error).message || 'Não foi possível carregar as notícias.'
  } finally {
    carregando.value = false
  }
}

async function excluir(n: SiteNoticia) {
  if (!window.confirm(`Excluir a notícia "${n.title}"?`)) return
  try {
    await excluirNoticia(n.id)
    emit('toast', { msg: 'Notícia excluída.', type: 'success' })
    await carregar(pagina.value)
  } catch (e) {
    emit('toast', { msg: (e as Error).message || 'Não foi possível excluir.', type: 'danger' })
  }
}

watch(() => props.searchQuery, () => carregar(1))
onMounted(() => carregar(1))
</script>

<style scoped>
.descricao { font-size: .78rem; color: var(--ink-muted); }
.paginacao { display: flex; gap: 12px; align-items: center; justify-content: center; padding: 12px; }
</style>
