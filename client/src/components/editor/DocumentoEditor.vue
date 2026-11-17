<template>
  <div class="doc-editor" :class="{ 'doc-editor--readonly': readonly }">
    <EditorToolbar
      v-if="!readonly"
      :placeholder-catalog="placeholderCatalog"
      :show-placeholder-picker="showPlaceholderPicker"
      :simples="simples"
      :imagem="imagem"
      @command="onToolbarCommand"
      @insert-placeholder="onInsertPlaceholder"
      @insert-page-break="onInsertPageBreak"
      @insert-table="onInsertTable"
      @insert-link="onInsertLink"
      @insert-image="onInsertImage"
    />
    <div class="doc-editor-frame" :class="{ 'doc-editor-frame--flat': !pageFrame }">
      <div
        ref="editorEl"
        class="doc-editor-body"
        :class="{ 'doc-editor-body--page': pageFrame }"
        :contenteditable="!readonly"
        spellcheck="false"
        @input="onInput"
        @paste="onPaste"
        @keydown="onKeydown"
        @mouseup="editor.saveSelection"
        @keyup="editor.saveSelection"
      ></div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Editor de documentos rico compartilhado entre Estudo de casos, Modelos e Notícias.
 *
 * @version 1.1.0
 */
import { ref, watch, onMounted } from 'vue'
import { useDocumentoEditor } from '../../composables/useDocumentoEditor'
import EditorToolbar from './EditorToolbar.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholderCatalog: { type: Object, default: () => ({}) },
  readonly: { type: Boolean, default: false },
  showPlaceholderPicker: { type: Boolean, default: true },
  pageFrame: { type: Boolean, default: true },
  /** Barra reduzida ao que o site público aceita (rich text das seções). */
  simples: { type: Boolean, default: false },
  /** Mostra o botão de inserir imagem na barra. */
  imagem: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'dirty', 'save-shortcut'])

const editorEl = ref(null)
const editor = useDocumentoEditor(editorEl)

let applyingExternalValue = false

function setContent(html) {
  applyingExternalValue = true
  editorEl.value.innerHTML = editor.hydrate(html || '')
  applyingExternalValue = false
}

function syncModel() {
  if (!editorEl.value) return
  const serialized = editor.serialize(editorEl.value.innerHTML)
  emit('update:modelValue', serialized)
  emit('dirty')
}

function onInput() {
  if (applyingExternalValue) return
  syncModel()
}

function onPaste(event) {
  if (props.readonly) return
  editor.sanitizePaste(event)
  syncModel()
}

function onKeydown(event) {
  if (props.readonly) return

  if (editor.handleBoundaryDeletion(event)) {
    syncModel()
    return
  }

  const mod = event.ctrlKey || event.metaKey
  if (!mod) return

  const key = event.key.toLowerCase()
  if (key === 's') {
    event.preventDefault()
    emit('save-shortcut')
  } else if (key === 'b') {
    event.preventDefault()
    editor.exec('bold')
    syncModel()
  } else if (key === 'i') {
    event.preventDefault()
    editor.exec('italic')
    syncModel()
  } else if (key === 'u') {
    event.preventDefault()
    editor.exec('underline')
    syncModel()
  }
  // Ctrl+Z/Ctrl+Y: desfazer/refazer nativos do contenteditable
}

function onToolbarCommand({ command, value }) {
  editor.exec(command, value)
  syncModel()
}

function onInsertPlaceholder({ origem, campo }) {
  editor.insertPlaceholder(origem, campo)
  syncModel()
}

function onInsertPageBreak() {
  editor.insertPageBreak()
  syncModel()
}

function onInsertTable({ rows, cols }) {
  editor.insertTable(rows, cols)
  syncModel()
}

function onInsertLink({ url, texto }) {
  editor.insertLink(url, texto)
  syncModel()
}

function onInsertImage({ src }) {
  editor.insertImage(src)
  syncModel()
}

watch(() => props.modelValue, (novo) => {
  if (applyingExternalValue || !editorEl.value) return
  const atual = editor.serialize(editorEl.value.innerHTML)
  if (novo !== atual) setContent(novo)
})

onMounted(() => {
  setContent(props.modelValue)
})

defineExpose({
  extractPlaceholdersUsed: () => editor.extractPlaceholdersUsed(props.modelValue || ''),
})
</script>

<style scoped>
.doc-editor {
  display: flex;
  flex-direction: column;
  background: var(--surface);
  border-radius: var(--radius);
  overflow: hidden;
}
.doc-editor--readonly { background: transparent; }

.doc-editor-frame {
  flex: 1;
  overflow: auto;
  padding: 24px;
  display: flex;
  justify-content: center;
  background: var(--surface);
}
.doc-editor-frame--flat {
  padding: 0;
  display: block;
  background: transparent;
}

.doc-editor-body {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 0 0 var(--radius) var(--radius);
  padding: 16px 20px;
  min-height: 200px;
  outline: none;
  font-size: .9rem;
  color: var(--ink);
  line-height: 1.6;
}
.doc-editor--readonly .doc-editor-body {
  border-radius: var(--radius);
  cursor: default;
}

.doc-editor-body--page {
  width: 21cm;
  min-height: 29.7cm;
  max-width: 100%;
  padding: 2cm;
  margin: 0 auto;
  font-family: 'Times New Roman', Georgia, serif;
  font-size: 12pt;
  box-shadow: var(--shadow-lg);
  border-radius: 4px;
}

/* Conteúdo inserido via innerHTML (fora do compilador do Vue) precisa de :deep() */
.doc-editor-body :deep(p) { margin: 0 0 .6em; }
.doc-editor-body :deep(h1),
.doc-editor-body :deep(h2),
.doc-editor-body :deep(h3) {
  font-family: var(--font-head);
  margin: .8em 0 .4em;
  line-height: 1.25;
}
.doc-editor-body :deep(blockquote) {
  margin: .6em 0;
  padding: 4px 16px;
  border-left: 3px solid var(--accent);
  color: var(--ink-dim);
  font-style: italic;
}
.doc-editor-body :deep(ul),
.doc-editor-body :deep(ol) { padding-left: 1.5em; margin: 0 0 .6em; }
.doc-editor-body :deep(img) { max-width: 100%; height: auto; }
.doc-editor-body :deep(a) { color: var(--primary); }
.doc-editor-body :deep(hr) { border: none; border-top: 1px solid var(--border); margin: 1em 0; }
.doc-editor-body :deep(font[size="1"]) { font-size: 0.75em; }
.doc-editor-body :deep(font[size="2"]) { font-size: 0.875em; }
.doc-editor-body :deep(font[size="3"]) { font-size: 1em; }
.doc-editor-body :deep(font[size="4"]) { font-size: 1.15em; }
.doc-editor-body :deep(font[size="5"]) { font-size: 1.35em; }
.doc-editor-body :deep(font[size="6"]) { font-size: 1.6em; }
.doc-editor-body :deep(font[size="7"]) { font-size: 2em; }

.doc-editor-body :deep(.ph-chip) {
  display: inline-block;
  background: var(--primary-lt);
  color: var(--primary);
  border: 1px solid var(--primary);
  border-radius: 4px;
  padding: 0 4px;
  font-size: .85em;
  font-weight: 600;
  white-space: nowrap;
  user-select: none;
}

.doc-editor-body :deep(.ph-page-break) {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 1em 0;
  color: var(--ink-muted);
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  user-select: none;
}
.doc-editor-body :deep(.ph-page-break)::before,
.doc-editor-body :deep(.ph-page-break)::after {
  content: '';
  flex: 1;
  border-top: 1px dashed var(--border);
}

.doc-editor-body :deep(.ph-table) {
  border-collapse: collapse;
  width: 100%;
  margin: .6em 0;
}
.doc-editor-body :deep(.ph-table td) {
  border: 1px solid var(--border);
  padding: 6px 8px;
  min-width: 40px;
}

@media print {
  .doc-editor-body :deep(.ph-page-break) { break-after: page; }
}
</style>
