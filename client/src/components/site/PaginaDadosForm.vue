<template>
  <div class="pagina-form">
    <div class="form-group" :class="{ 'campo--erro': erros.titulo }" data-campo="titulo">
      <label class="form-label required-field" for="pg-titulo">Título</label>
      <input id="pg-titulo" class="form-control" :value="modelValue.titulo" placeholder="Ex.: Direito Previdenciário" @input="aoDigitarTitulo(($event.target as HTMLInputElement).value)" />
      <p v-if="erros.titulo" class="erro" role="alert">{{ erros.titulo }}</p>
    </div>

    <div class="form-group" :class="{ 'campo--erro': erros.slug }" data-campo="slug">
      <label class="form-label required-field" for="pg-slug">Endereço (slug)</label>
      <div class="slug-linha">
        <span class="slug-prefixo">/pagina/</span>
        <input id="pg-slug" class="form-control" :value="modelValue.slug" placeholder="direito-previdenciario" :disabled="slugFixo" @input="aoDigitarSlug(($event.target as HTMLInputElement).value)" />
      </div>
      <small class="ajuda">Só letras minúsculas, números e hífen.</small>
      <p v-if="avisoSlug" class="aviso" role="status" data-aviso-slug><i class="bi bi-exclamation-triangle"></i> {{ avisoSlug }}</p>
      <p v-if="erros.slug" class="erro" role="alert">{{ erros.slug }}</p>
    </div>

    <div class="form-row col-2">
      <div v-if="modo === 'criar'" class="form-group" data-campo="tipo">
        <label class="form-label" for="pg-tipo">Tipo</label>
        <select id="pg-tipo" class="form-control" :value="modelValue.tipo" @change="atualizar({ tipo: ($event.target as HTMLSelectElement).value as FormPagina['tipo'] })">
          <option v-for="t in TIPOS_CRIAVEIS" :key="t.valor" :value="t.valor">{{ t.rotulo }}</option>
        </select>
        <p v-if="erros.tipo" class="erro" role="alert">{{ erros.tipo }}</p>
      </div>
      <div class="form-group" :class="{ 'campo--erro': erros.layout }" data-campo="layout">
        <label class="form-label" for="pg-layout">Layout</label>
        <select id="pg-layout" class="form-control" :value="modelValue.layout" @change="atualizar({ layout: ($event.target as HTMLSelectElement).value })">
          <option value="">{{ modo === 'criar' ? 'Padrão do tipo' : 'Manter' }}</option>
          <option v-for="l in layouts" :key="l" :value="l">{{ l }}</option>
        </select>
        <p v-if="erros.layout" class="erro" role="alert">{{ erros.layout }}</p>
      </div>
    </div>

    <fieldset class="grupo">
      <legend>SEO</legend>
      <div class="form-group" :class="{ 'campo--erro': erros.meta_title }" data-campo="meta_title">
        <label class="form-label" for="pg-meta-title">Meta título</label>
        <input id="pg-meta-title" class="form-control" :value="modelValue.meta_title" placeholder="Vazio usa o título + sufixo do site" @input="atualizar({ meta_title: ($event.target as HTMLInputElement).value })" />
        <p v-if="erros.meta_title" class="erro" role="alert">{{ erros.meta_title }}</p>
      </div>
      <div class="form-group" :class="{ 'campo--erro': erros.meta_description }" data-campo="meta_description">
        <label class="form-label" for="pg-meta-desc">
          Meta descrição
          <span class="contador" :class="{ 'contador--excedeu': modelValue.meta_description.length > 160 }">{{ modelValue.meta_description.length }}/160</span>
        </label>
        <textarea id="pg-meta-desc" class="form-control" rows="3" :value="modelValue.meta_description" @input="atualizar({ meta_description: ($event.target as HTMLTextAreaElement).value })"></textarea>
        <p v-if="erros.meta_description" class="erro" role="alert">{{ erros.meta_description }}</p>
      </div>
      <div class="form-group" data-campo="og_image_id">
        <label class="form-label">Imagem de compartilhamento</label>
        <MidiaPicker :model-value="modelValue.og_image_id" @update:model-value="atualizar({ og_image_id: $event })" />
        <p v-if="erros.og_image_id" class="erro" role="alert">{{ erros.og_image_id }}</p>
      </div>
    </fieldset>

    <fieldset class="grupo">
      <legend>Sitemap</legend>
      <label class="switch" data-campo="exibir_sitemap">
        <input type="checkbox" role="switch" :checked="modelValue.exibir_sitemap" @change="atualizar({ exibir_sitemap: ($event.target as HTMLInputElement).checked })" />
        <span>Incluir no sitemap (aparece nos buscadores)</span>
      </label>
      <div v-if="modelValue.exibir_sitemap" class="form-row col-2" style="margin-top: 10px;">
        <div class="form-group" :class="{ 'campo--erro': erros.prioridade_sitemap }" data-campo="prioridade_sitemap">
          <label class="form-label" for="pg-prio">Prioridade (0 a 1)</label>
          <input id="pg-prio" type="number" min="0" max="1" step="0.1" class="form-control" :value="modelValue.prioridade_sitemap" @input="atualizar({ prioridade_sitemap: Number(($event.target as HTMLInputElement).value) })" />
          <p v-if="erros.prioridade_sitemap" class="erro" role="alert">{{ erros.prioridade_sitemap }}</p>
        </div>
        <div class="form-group" data-campo="changefreq">
          <label class="form-label" for="pg-freq">Frequência de atualização</label>
          <select id="pg-freq" class="form-control" :value="modelValue.changefreq" @change="atualizar({ changefreq: ($event.target as HTMLSelectElement).value })">
            <option v-for="f in CHANGEFREQ" :key="f.valor" :value="f.valor">{{ f.rotulo }}</option>
          </select>
        </div>
      </div>
    </fieldset>
  </div>
</template>

<script setup lang="ts">
/**
 * Dados de uma página (título, endereço, layout, SEO e sitemap). Na criação o endereço acompanha o
 * título até ser editado à mão; ao mudar o endereço de uma página publicada, avisa antes de salvar.
 * Reutilizado pelo editor de página (sistema#167).
 */
import { computed, ref, watch } from 'vue'
import MidiaPicker from './MidiaPicker.vue'
import { CHANGEFREQ, TIPOS_CRIAVEIS, avisoMudancaDeSlug, gerarSlug, type FormPagina } from '../../utils/paginasSite'
import type { SitePagina } from '../../types/site'

const props = withDefaults(defineProps<{
  modelValue: FormPagina
  erros?: Record<string, string>
  modo: 'criar' | 'editar'
  layouts?: string[]
  /** Página que está sendo editada (para o aviso de mudança de endereço). */
  original?: Pick<SitePagina, 'status' | 'slug'> | null
  slugFixo?: boolean
}>(), { erros: () => ({}), layouts: () => [], original: null, slugFixo: false })

const emit = defineEmits<{ (e: 'update:modelValue', v: FormPagina): void }>()

const slugEditado = ref(props.modo === 'editar')

// Último valor emitido: duas alterações antes da tela re-renderizar não perdem a primeira.
let ultimo = props.modelValue
watch(() => props.modelValue, (v) => { ultimo = v })

const atualizar = (parcial: Partial<FormPagina>) => {
  ultimo = { ...ultimo, ...parcial }
  emit('update:modelValue', ultimo)
}

function aoDigitarTitulo(titulo: string) {
  atualizar(slugEditado.value ? { titulo } : { titulo, slug: gerarSlug(titulo) })
}

function aoDigitarSlug(slug: string) {
  slugEditado.value = true
  atualizar({ slug: slug.toLowerCase() })
}

const avisoSlug = computed(() => (props.original ? avisoMudancaDeSlug(props.original, props.modelValue.slug) : null))
</script>

<style scoped>
.slug-linha { display: flex; align-items: center; gap: 6px; }
.slug-prefixo { color: var(--ink-dim); white-space: nowrap; }
.ajuda { display: block; margin-top: 4px; color: var(--ink-dim); }
.erro { margin: 4px 0 0; font-size: .8rem; color: var(--danger); }
.aviso { margin: 6px 0 0; font-size: .85rem; color: var(--accent); }
.campo--erro .form-control { border-color: var(--danger); }
.contador { margin-left: 8px; font-weight: 400; font-size: .75rem; color: var(--ink-dim); }
.contador--excedeu { color: var(--danger); font-weight: 600; }
.grupo { border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px 4px; margin: 0 0 14px; }
.grupo legend { padding: 0 6px; font-weight: 600; font-size: .9rem; }
.switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 10px; }
</style>
