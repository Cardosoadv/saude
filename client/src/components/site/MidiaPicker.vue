<template>
  <div class="picker">
    <div class="picker-atual">
      <div class="picker-miniatura" :class="{ 'picker-miniatura--vazia': !midia }">
        <img v-if="midia" :src="midia.miniatura_url" :alt="midia.alt || midia.nome_original" />
        <i v-else class="bi bi-image" aria-hidden="true"></i>
      </div>
      <div class="picker-info">
        <template v-if="modelValue && midia">
          <strong :title="midia.nome_original">{{ midia.nome_original }}</strong>
          <small>{{ midia.largura }}×{{ midia.altura }}</small>
          <small v-if="midia.alt">{{ midia.alt }}</small>
          <small v-else class="picker-sem-alt">Sem texto alternativo</small>
        </template>
        <small v-else-if="modelValue && carregando">Carregando imagem #{{ modelValue }}…</small>
        <small v-else-if="modelValue" class="picker-sem-alt">A imagem #{{ modelValue }} não foi encontrada na biblioteca.</small>
        <small v-else>Nenhuma imagem escolhida.</small>
      </div>
      <div v-if="!readonly" class="picker-acoes">
        <button type="button" class="btn btn-ghost btn-sm" data-abrir @click="abrir">
          <i class="bi bi-images"></i> {{ modelValue ? 'Trocar' : 'Escolher imagem' }}
        </button>
        <button v-if="modelValue" type="button" class="btn btn-ghost btn-sm" data-remover @click="emit('update:modelValue', null)">
          <i class="bi bi-x-lg"></i> Remover
        </button>
      </div>
    </div>

    <div v-if="aberto" class="picker-fundo" role="dialog" aria-modal="true" aria-label="Biblioteca de imagens" @click.self="fechar" @keydown.esc="fechar">
      <div class="picker-modal">
        <div class="picker-cabecalho">
          <h3><i class="bi bi-images"></i> Biblioteca de imagens</h3>
          <button type="button" class="btn btn-ghost btn-sm" aria-label="Fechar" data-fechar @click="fechar"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="picker-corpo">
          <!-- Texto alternativo obrigatório antes de usar a imagem -->
          <div v-if="pendente" class="picker-alt" data-alt-obrigatorio role="group" aria-label="Texto alternativo">
            <img :src="pendente.miniatura_url" :alt="pendente.nome_original" />
            <div>
              <p><strong>Descreva a imagem</strong> para quem não consegue vê-la (leitores de tela e buscadores). Esta descrição é obrigatória.</p>
              <input class="form-control" v-model="altRascunho" maxlength="255" placeholder="Ex.: Fachada da clínica em Belo Horizonte" aria-label="Texto alternativo" data-alt-input @keyup.enter="confirmarAlt" />
              <p v-if="erroAlt" class="picker-erro" role="alert">{{ erroAlt }}</p>
              <div class="picker-alt-acoes">
                <button type="button" class="btn btn-ghost btn-sm" data-voltar @click="pendente = null">Voltar</button>
                <button type="button" class="btn btn-accent btn-sm" data-usar :disabled="salvandoAlt" @click="confirmarAlt">Usar imagem</button>
              </div>
            </div>
          </div>

          <MidiaBiblioteca
            v-show="!pendente"
            modo-selecao
            :selecionado-id="modelValue"
            @selecionar="escolher"
            @toast="emit('toast', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Escolha de uma imagem da biblioteca do site: mostra a escolhida, abre a biblioteca num modal
 * (com envio na hora) e devolve o id da mídia (`v-model`). Com `exigir-alt`, uma imagem sem texto
 * alternativo só é usada depois de descrita (acessibilidade nas seções do site).
 */
import { ref, watch } from 'vue'
import MidiaBiblioteca from './MidiaBiblioteca.vue'
import { buscarMidia, registrarMidia } from '../../composables/useMidiaCache'
import { atualizarAltMidia, errosDeCampo } from '../../services/siteApi'
import type { SiteMidia } from '../../types/site'

const props = defineProps<{ modelValue: number | null | undefined; readonly?: boolean; exigirAlt?: boolean }>()
const emit = defineEmits<{
  (e: 'update:modelValue', id: number | null): void
  (e: 'toast', t: { msg: string; type: string }): void
}>()

const midia = ref<SiteMidia | null>(null)
const carregando = ref(false)
const aberto = ref(false)
const pendente = ref<SiteMidia | null>(null)
const altRascunho = ref('')
const erroAlt = ref('')
const salvandoAlt = ref(false)

watch(
  () => props.modelValue,
  async (id) => {
    midia.value = null
    if (!id) return
    carregando.value = true
    const achada = await buscarMidia(id)
    if (props.modelValue === id) midia.value = achada
    carregando.value = false
  },
  { immediate: true },
)

const abrir = () => { aberto.value = true; pendente.value = null }
const fechar = () => { aberto.value = false; pendente.value = null }

function usar(m: SiteMidia) {
  registrarMidia(m)
  midia.value = m
  emit('update:modelValue', Number(m.id)) // a API devolve o id como texto
  fechar()
}

function escolher(m: SiteMidia) {
  if (props.exigirAlt && !(m.alt ?? '').trim()) {
    pendente.value = m
    altRascunho.value = ''
    erroAlt.value = ''
    return
  }
  usar(m)
}

async function confirmarAlt() {
  const alt = altRascunho.value.trim()
  if (!alt || !pendente.value) {
    erroAlt.value = 'Informe o texto alternativo para usar a imagem.'
    return
  }
  salvandoAlt.value = true
  try {
    usar((await atualizarAltMidia(pendente.value.id, alt)).data)
  } catch (e) {
    erroAlt.value = Object.values(errosDeCampo(e))[0] ?? (e as Error).message
  } finally {
    salvandoAlt.value = false
  }
}
</script>

<style scoped>
.picker-atual { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.picker-miniatura { width: 96px; height: 72px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #f3f1ec; display: flex; align-items: center; justify-content: center; }
.picker-miniatura img { width: 100%; height: 100%; object-fit: contain; }
.picker-miniatura--vazia { color: var(--ink-dim); font-size: 1.6rem; }
.picker-info { display: flex; flex-direction: column; min-width: 0; flex: 1 1 160px; }
.picker-info strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.picker-info small { color: var(--ink-dim); }
.picker-sem-alt { color: var(--accent) !important; }
.picker-acoes { display: flex; gap: 6px; }
.picker-fundo { position: fixed; inset: 0; z-index: 2000; background: rgba(0, 0, 0, .45); display: flex; align-items: center; justify-content: center; padding: 16px; }
.picker-modal { background: var(--surface, #fff); border-radius: 12px; width: min(980px, 100%); max-height: 90vh; display: flex; flex-direction: column; }
.picker-cabecalho { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.picker-cabecalho h3 { margin: 0; font-size: 1.05rem; }
.picker-corpo { padding: 16px 18px; overflow: auto; }
.picker-alt { display: flex; gap: 16px; align-items: flex-start; }
.picker-alt img { width: 160px; border-radius: 8px; border: 1px solid var(--border); }
.picker-alt > div { flex: 1; }
.picker-alt-acoes { display: flex; gap: 8px; margin-top: 10px; }
.picker-erro { color: var(--danger); font-size: .85rem; margin: 6px 0 0; }
</style>
