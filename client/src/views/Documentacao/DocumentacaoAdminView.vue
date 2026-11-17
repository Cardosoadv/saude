<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Edição da documentação</h1>
        <p>Crie páginas novas ou edite cópias das páginas do manual. Rascunhos só aparecem para quem edita.</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-ghost" data-ver-documentacao @click="emit('abrir-documentacao', { slug: null, ancora: null })">
          <i class="bi bi-book"></i> Ver documentação
        </button>
        <button class="btn btn-accent" data-nova @click="emit('nova')">
          <i class="bi bi-plus-lg"></i> Nova página
        </button>
      </div>
    </div>

    <div class="toolbar filtros">
      <input v-model="busca" type="search" class="form-control" placeholder="Buscar por título ou endereço" aria-label="Buscar páginas" data-busca-edicao>
      <select v-model="filtro" class="form-control" aria-label="Filtrar páginas" data-filtro>
        <option value="">Todas</option>
        <option value="banco">Criadas ou editadas aqui</option>
        <option value="rascunho">Rascunhos</option>
        <option value="arquivo">Só do manual oficial (arquivo)</option>
      </select>
    </div>

    <div v-if="carregando" class="glass-panel estado" role="status">Carregando…</div>
    <div v-else-if="falha" class="glass-panel estado" role="alert">
      <p>{{ falha }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>
    <div v-else class="glass-panel tabela-container">
      <p v-if="!filtradas.length" class="estado">Nenhuma página encontrada.</p>
      <table v-else class="table-premium">
        <thead>
          <tr>
            <th>Título</th>
            <th>Endereço</th>
            <th>Origem</th>
            <th>Status</th>
            <th>Alterada em</th>
            <th class="acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filtradas" :key="p.slug" :data-pagina-edicao="p.slug">
            <td>{{ p.titulo }}</td>
            <td><code>{{ p.slug }}</code></td>
            <td>
              <span v-if="p.origem === 'arquivo'" class="selo">Manual oficial</span>
              <span v-else class="selo selo-banco" :title="p.tem_arquivo ? 'Substitui a página do manual oficial quando publicada' : ''">
                {{ p.tem_arquivo ? 'Cópia editada' : 'Criada aqui' }}
              </span>
            </td>
            <td>
              <span class="selo" :class="p.status === 'rascunho' ? 'selo-rascunho' : 'selo-publicado'">
                {{ p.status === 'rascunho' ? 'Rascunho' : 'Publicado' }}
              </span>
            </td>
            <td>{{ p.atualizado_em ? new Date(p.atualizado_em).toLocaleDateString('pt-BR') : '—' }}</td>
            <td class="acoes">
              <button class="btn btn-ghost btn-sm" title="Ver na documentação" data-ver @click="emit('abrir-documentacao', { slug: p.slug, ancora: null })">
                <i class="bi bi-eye"></i>
              </button>
              <button v-if="p.id" class="btn btn-ghost btn-sm" title="Editar" data-editar @click="emit('abrir-editor', p.id)">
                <i class="bi bi-pencil"></i>
              </button>
              <button v-else class="btn btn-ghost btn-sm" title="Criar uma cópia editável" data-copiar :disabled="copiando === p.slug" @click="copiar(p)">
                <i class="bi bi-pencil-square"></i> {{ copiando === p.slug ? 'Copiando…' : 'Editar cópia' }}
              </button>
              <button v-if="p.id" class="btn btn-ghost btn-sm perigo" title="Excluir" data-excluir @click="excluir(p)">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { copiarDoArquivo, excluirPagina, listarParaEdicao } from '../../services/documentacaoApi'
import type { DocItemEdicao } from '../../types/documentacao'

const emit = defineEmits<{
  (e: 'abrir-editor', id: number): void
  (e: 'nova'): void
  (e: 'abrir-documentacao', destino: { slug: string | null; ancora: string | null }): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

const paginas = ref<DocItemEdicao[]>([])
const carregando = ref(true)
const falha = ref('')
const busca = ref('')
const filtro = ref('')
const copiando = ref<string | null>(null)

const semAcento = (t: string) => t.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()

const filtradas = computed(() => {
  const termo = semAcento(busca.value.trim())
  return paginas.value.filter((p) => {
    if (filtro.value === 'banco' && p.origem !== 'banco') return false
    if (filtro.value === 'arquivo' && p.origem !== 'arquivo') return false
    if (filtro.value === 'rascunho' && p.status !== 'rascunho') return false
    return !termo || semAcento(`${p.titulo} ${p.slug}`).includes(termo)
  })
})

async function carregar() {
  carregando.value = true
  falha.value = ''
  try {
    paginas.value = (await listarParaEdicao()).data
  } catch {
    falha.value = 'Não foi possível carregar as páginas.'
  } finally {
    carregando.value = false
  }
}

async function copiar(p: DocItemEdicao) {
  copiando.value = p.slug
  try {
    const { data } = await copiarDoArquivo(p.slug)
    emit('toast', { msg: 'Cópia criada como rascunho. A versão oficial continua no ar até você publicar.', type: 'success' })
    emit('abrir-editor', data.id)
  } catch (erro: any) {
    emit('toast', { msg: erro?.message || 'Não foi possível criar a cópia.', type: 'danger' })
  } finally {
    copiando.value = null
  }
}

async function excluir(p: DocItemEdicao) {
  const aviso = p.tem_arquivo
    ? `Excluir a cópia editada de "${p.titulo}"? A página volta a mostrar a versão do manual oficial.`
    : `Excluir a página "${p.titulo}"? Ela some da documentação.`
  if (!p.id || !window.confirm(aviso)) return
  try {
    await excluirPagina(p.id)
    emit('toast', { msg: 'Página excluída.', type: 'success' })
    await carregar()
  } catch (erro: any) {
    emit('toast', { msg: erro?.message || 'Não foi possível excluir.', type: 'danger' })
  }
}

onMounted(carregar)
</script>

<style scoped>
.filtros { display: flex; gap: 12px; margin-bottom: 16px; }
.filtros input { flex: 1; max-width: 420px; }
.filtros select { max-width: 260px; }
.estado { padding: 1.5rem; }
.tabela-container { overflow-x: auto; }
.table-premium { width: 100%; border-collapse: collapse; }
.table-premium th, .table-premium td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
.acoes { white-space: nowrap; text-align: right !important; }
code { font-size: .85em; color: var(--ink-dim); }
.selo { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .75rem; background: var(--surface); color: var(--ink-dim); border: 1px solid var(--border); }
.selo-banco { color: var(--primary); }
.selo-rascunho { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.selo-publicado { background: #dcfce7; color: #166534; border-color: #86efac; }
.perigo { color: var(--danger); }
@media (max-width: 768px) { .filtros { flex-direction: column; } .filtros input, .filtros select { max-width: none; } }
</style>
