<template>
  <div class="doc-toolbar">
    <div class="doc-toolbar-group">
      <button type="button" class="doc-tb-btn" title="Negrito (Ctrl+B)" @click="cmd('bold')"><i class="bi bi-type-bold"></i></button>
      <button type="button" class="doc-tb-btn" title="Itálico (Ctrl+I)" @click="cmd('italic')"><i class="bi bi-type-italic"></i></button>
      <button type="button" class="doc-tb-btn" title="Sublinhado (Ctrl+U)" @click="cmd('underline')"><i class="bi bi-type-underline"></i></button>
      <button type="button" class="doc-tb-btn" title="Tachado" @click="cmd('strikeThrough')"><i class="bi bi-type-strikethrough"></i></button>
      <button
        v-if="!simples"
        type="button"
        class="doc-tb-btn doc-tb-btn--font"
        title="Aumentar tamanho da fonte"
        aria-label="Aumentar tamanho da fonte"
        @click="cmd('increaseFontSize')"
      >
        <span class="doc-tb-font-label">A<sup>+</sup></span>
      </button>
      <button
        v-if="!simples"
        type="button"
        class="doc-tb-btn doc-tb-btn--font"
        title="Diminuir tamanho da fonte"
        aria-label="Diminuir tamanho da fonte"
        @click="cmd('decreaseFontSize')"
      >
        <span class="doc-tb-font-label">A<sup>−</sup></span>
      </button>
      <label v-if="!simples" class="doc-tb-color" title="Cor do texto">
        <i class="bi bi-palette"></i>
        <input type="color" @input="e => cmd('foreColor', (e.target as HTMLInputElement).value)" />
      </label>
      <label v-if="!simples" class="doc-tb-color" title="Cor de destaque">
        <i class="bi bi-brush"></i>
        <input type="color" @input="e => cmd('hiliteColor', (e.target as HTMLInputElement).value)" />
      </label>
    </div>

    <div class="doc-toolbar-sep"></div>

    <div class="doc-toolbar-group">
      <select class="doc-tb-select" title="Estilo do parágrafo" @change="onBlockChange">
        <option value="P">Parágrafo</option>
        <option value="H1">Título 1</option>
        <option value="H2">Título 2</option>
        <option value="H3">Título 3</option>
      </select>
      <button type="button" class="doc-tb-btn" title="Lista não ordenada" @click="cmd('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
      <button type="button" class="doc-tb-btn" title="Lista ordenada" @click="cmd('insertOrderedList')"><i class="bi bi-list-ol"></i></button>
      <button type="button" class="doc-tb-btn" title="Citação" @click="cmd('formatBlock', '<BLOCKQUOTE>')"><i class="bi bi-quote"></i></button>
      <button v-if="!simples" type="button" class="doc-tb-btn" title="Linha horizontal" @click="cmd('insertHorizontalRule')"><i class="bi bi-hr"></i></button>
    </div>

    <div v-if="!simples" class="doc-toolbar-sep"></div>

    <div v-if="!simples" class="doc-toolbar-group">
      <button type="button" class="doc-tb-btn" title="Alinhar à esquerda" @click="cmd('justifyLeft')"><i class="bi bi-text-left"></i></button>
      <button type="button" class="doc-tb-btn" title="Centralizar" @click="cmd('justifyCenter')"><i class="bi bi-text-center"></i></button>
      <button type="button" class="doc-tb-btn" title="Alinhar à direita" @click="cmd('justifyRight')"><i class="bi bi-text-right"></i></button>
      <button type="button" class="doc-tb-btn" title="Justificado" @click="cmd('justifyFull')"><i class="bi bi-justify"></i></button>
      <button type="button" class="doc-tb-btn" title="Diminuir recuo" @click="cmd('outdent')"><i class="bi bi-text-indent-right"></i></button>
      <button type="button" class="doc-tb-btn" title="Aumentar recuo" @click="cmd('indent')"><i class="bi bi-text-indent-left"></i></button>
    </div>

    <div class="doc-toolbar-sep"></div>

    <div class="doc-toolbar-group">
      <button type="button" class="doc-tb-btn" title="Inserir link" @click="onInsertLink"><i class="bi bi-link-45deg"></i></button>

      <template v-if="imagem">
        <input ref="imageInputEl" type="file" accept="image/png, image/jpeg, image/gif" style="display: none" @change="onImageChosen" />
        <button type="button" class="doc-tb-btn" title="Inserir imagem" @click="imageInputEl.click()"><i class="bi bi-image"></i></button>
      </template>

      <div v-if="!simples" class="doc-tb-dropdown" ref="tableDropdownEl">
        <button type="button" class="doc-tb-btn" title="Inserir tabela" @click="toggleTableGrid"><i class="bi bi-table"></i></button>
        <div v-if="showTableGrid" class="doc-tb-menu doc-tb-grid-menu">
          <div class="doc-tb-grid" @mouseleave="gridHover.r = 0; gridHover.c = 0">
            <div
              v-for="cell in gridCells"
              :key="`${cell.r}-${cell.c}`"
              class="doc-tb-grid-cell"
              :class="{ active: cell.r <= gridHover.r && cell.c <= gridHover.c }"
              @mouseenter="gridHover.r = cell.r; gridHover.c = cell.c"
              @click="chooseTable(cell.r, cell.c)"
            ></div>
          </div>
          <div class="doc-tb-grid-label">{{ gridHover.r || 0 }} x {{ gridHover.c || 0 }}</div>
        </div>
      </div>

      <button v-if="!simples" type="button" class="doc-tb-btn" title="Quebra de página" @click="$emit('insert-page-break')"><i class="bi bi-scissors"></i></button>

      <div v-if="showPlaceholderPicker" class="doc-tb-dropdown" ref="placeholderDropdownEl">
        <button type="button" class="doc-tb-btn doc-tb-btn--accent" title="Inserir campo" @click="togglePlaceholderPicker">
          <i class="bi bi-braces"></i> Campo
        </button>
        <PlaceholderPicker
          v-if="showPlaceholderMenu"
          :catalog="placeholderCatalog"
          @pick="onPickPlaceholder"
          @close="showPlaceholderMenu = false"
        />
      </div>
    </div>

    <div class="doc-toolbar-sep"></div>

    <div class="doc-toolbar-group">
      <button type="button" class="doc-tb-btn" title="Desfazer (Ctrl+Z)" @click="cmd('undo')"><i class="bi bi-arrow-counterclockwise"></i></button>
      <button type="button" class="doc-tb-btn" title="Refazer (Ctrl+Y)" @click="cmd('redo')"><i class="bi bi-arrow-clockwise"></i></button>
      <button type="button" class="doc-tb-btn" title="Limpar formatação" @click="cmd('removeFormat')"><i class="bi bi-eraser"></i></button>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Barra de ferramentas do editor de documentos.
 *
 * @version 1.1.0
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import PlaceholderPicker from './PlaceholderPicker.vue'

defineProps({
  placeholderCatalog: { type: Object, default: () => ({}) },
  showPlaceholderPicker: { type: Boolean, default: true },
  /** Só o que o site público aceita (negrito, itálico, sublinhado, tachado, títulos, listas, citação, link). */
  simples: { type: Boolean, default: false },
  /** Habilita o botão de inserir imagem (embutida em Base64). */
  imagem: { type: Boolean, default: false },
})

const emit = defineEmits(['command', 'insert-placeholder', 'insert-page-break', 'insert-table', 'insert-link', 'insert-image'])

const showTableGrid = ref(false)
const showPlaceholderMenu = ref(false)
const gridHover = reactive({ r: 0, c: 0 })
const GRID_MAX = 6
const gridCells = computed(() => {
  const cells = []
  for (let r = 1; r <= GRID_MAX; r++) {
    for (let c = 1; c <= GRID_MAX; c++) cells.push({ r, c })
  }
  return cells
})

const imageInputEl = ref(null)
const tableDropdownEl = ref(null)
const placeholderDropdownEl = ref(null)

function cmd(command, value = null) {
  emit('command', { command, value })
}

function onBlockChange(event) {
  cmd('formatBlock', `<${event.target.value}>`)
}

function toggleTableGrid() {
  showTableGrid.value = !showTableGrid.value
  showPlaceholderMenu.value = false
  gridHover.r = 0
  gridHover.c = 0
}

function chooseTable(r, c) {
  emit('insert-table', { rows: r, cols: c })
  showTableGrid.value = false
}

function togglePlaceholderPicker() {
  showPlaceholderMenu.value = !showPlaceholderMenu.value
  showTableGrid.value = false
}

function onPickPlaceholder(payload) {
  emit('insert-placeholder', payload)
  showPlaceholderMenu.value = false
}

function onInsertLink() {
  const url = window.prompt('URL do link:')
  if (!url) return
  const texto = window.prompt('Texto do link:', url)
  emit('insert-link', { url, texto: texto || url })
}

function onImageChosen(event) {
  const file = event.target.files[0]
  event.target.value = ''
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => emit('insert-image', { src: reader.result })
  reader.readAsDataURL(file)
}

function onDocumentClick(event) {
  if (showTableGrid.value && tableDropdownEl.value && !tableDropdownEl.value.contains(event.target)) {
    showTableGrid.value = false
  }
  if (showPlaceholderMenu.value && placeholderDropdownEl.value && !placeholderDropdownEl.value.contains(event.target)) {
    showPlaceholderMenu.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick))
</script>

<style scoped>
.doc-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  padding: 8px 10px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius) var(--radius) 0 0;
}

.doc-toolbar-group { display: flex; align-items: center; gap: 2px; }
.doc-toolbar-sep { width: 1px; align-self: stretch; background: var(--border); margin: 2px 4px; }

.doc-tb-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  width: 32px;
  height: 32px;
  justify-content: center;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: var(--ink-dim);
  font-size: .9rem;
  cursor: pointer;
  transition: all .12s;
}
.doc-tb-btn:hover { background: var(--surface); color: var(--primary); }
.doc-tb-btn--accent { width: auto; padding: 0 10px; font-size: .78rem; font-weight: 500; color: var(--primary); }
.doc-tb-btn--accent:hover { background: var(--primary-lt); }
.doc-tb-font-label {
  font-weight: 700;
  font-size: .82rem;
  line-height: 1;
  letter-spacing: -0.02em;
}
.doc-tb-font-label sup {
  font-size: .65em;
  font-weight: 700;
}

.doc-tb-color {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  color: var(--ink-dim);
}
.doc-tb-color:hover { background: var(--surface); color: var(--primary); }
.doc-tb-color input[type="color"] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.doc-tb-select {
  height: 32px;
  padding: 0 8px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--card);
  color: var(--ink-dim);
  font-family: var(--font-body);
  font-size: .78rem;
  cursor: pointer;
}

.doc-tb-dropdown { position: relative; }

.doc-tb-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: var(--shadow-lg);
  z-index: 300;
  padding: 10px;
}

.doc-tb-grid { display: grid; grid-template-columns: repeat(6, 18px); gap: 3px; }
.doc-tb-grid-cell {
  width: 18px;
  height: 18px;
  border: 1px solid var(--border);
  border-radius: 2px;
  background: var(--surface);
}
.doc-tb-grid-cell.active { background: var(--primary-lt); border-color: var(--primary); }
.doc-tb-grid-label { margin-top: 6px; text-align: center; font-size: .75rem; color: var(--ink-muted); }
</style>
