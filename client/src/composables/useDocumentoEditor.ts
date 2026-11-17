/**
 * Lógica de comandos do editor de documentos (Plano 3, §4/§5).
 * Não renderiza UI: opera diretamente sobre o elemento contenteditable
 * referenciado por `editorRef` e sobre strings de HTML (serialize/hydrate).
 *
 * @version 1.1.0
 */

import type { Ref } from 'vue'

export interface PlaceholderRef {
  origem: string
  campo: string
}

const PLACEHOLDER_RE = /\{\{\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*\}\}/g
const MIN_FONT_SIZE = 1
const DEFAULT_FONT_SIZE = 3
const MAX_FONT_SIZE = 7

const STRIP_TAGS = new Set(['STYLE', 'SCRIPT', 'IFRAME', 'META', 'LINK', 'TITLE', 'HEAD'])
const ALLOWED_TAGS = new Set([
  'P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE',
  'UL', 'OL', 'LI', 'A', 'H1', 'H2', 'H3', 'BLOCKQUOTE'
])
const ALLOWED_ATTRS: Record<string, string[]> = {
  A: ['href'],
}

function sanitizeNode(root: Node): void {
  const children = Array.from(root.childNodes)
  for (const node of children) {
    if (node.nodeType === Node.TEXT_NODE) continue
    if (node.nodeType !== Node.ELEMENT_NODE) {
      node.remove()
      continue
    }
    const child = node as Element
    const tag = child.tagName
    if (STRIP_TAGS.has(tag)) {
      child.remove()
      continue
    }
    if (!ALLOWED_TAGS.has(tag)) {
      // Desconhecido/não permitido: mantém o texto, descarta a tag (unwrap)
      const parent = child.parentNode
      while (child.firstChild) parent.insertBefore(child.firstChild, child)
      parent.removeChild(child)
      continue
    }
    const allowedAttrs = ALLOWED_ATTRS[tag] || []
    Array.from(child.attributes).forEach(attr => {
      if (!allowedAttrs.includes(attr.name.toLowerCase())) child.removeAttribute(attr.name)
    })
    sanitizeNode(child)
  }
}

export function useDocumentoEditor(editorRef: Ref<HTMLElement | null> | null = null) {
  let savedRange: Range | null = null

  function focusEditor() {
    editorRef?.value?.focus()
  }

  function isSelectionInsideEditor() {
    const sel = window.getSelection()
    if (!sel || sel.rangeCount === 0 || !editorRef?.value) return false
    return editorRef.value.contains(sel.getRangeAt(0).commonAncestorContainer)
  }

  function saveSelection() {
    const sel = window.getSelection()
    if (sel && sel.rangeCount > 0 && isSelectionInsideEditor()) {
      savedRange = sel.getRangeAt(0).cloneRange()
    }
  }

  function restoreSelection() {
    const sel = window.getSelection()
    sel.removeAllRanges()
    if (savedRange) {
      sel.addRange(savedRange)
      return
    }
    if (editorRef?.value) {
      const range = document.createRange()
      range.selectNodeContents(editorRef.value)
      range.collapse(false)
      sel.addRange(range)
    }
  }

  function getActiveRange() {
    if (isSelectionInsideEditor()) {
      saveSelection()
    }
    focusEditor()
    restoreSelection()
    return window.getSelection().getRangeAt(0)
  }

  function placeCursor(node: Node, atStart = false): void {
    const sel = window.getSelection()
    const range = document.createRange()
    if (atStart) {
      range.setStart(node, 0)
    } else {
      range.setStartAfter(node)
    }
    range.collapse(true)
    sel.removeAllRanges()
    sel.addRange(range)
    savedRange = range.cloneRange()
  }

  function commitInsertion(nodes: Node[], cursorNode: Node, atStart = false): void {
    const range = getActiveRange()
    range.deleteContents()
    const frag = document.createDocumentFragment()
    nodes.forEach(n => frag.appendChild(n))
    range.insertNode(frag)
    placeCursor(cursorNode, atStart)
  }

  const BLOCK_ANCESTOR_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'BLOCKQUOTE', 'LI'])

  function findBlockAncestor(node: Node): Element | null {
    let el = node.nodeType === Node.TEXT_NODE ? node.parentElement : (node as Element)
    while (el && el !== editorRef?.value) {
      if (BLOCK_ANCESTOR_TAGS.has(el.tagName)) return el
      el = el.parentElement
    }
    return null
  }

  // Insere nós de nível de bloco (tabela, quebra de página, parágrafos colados)
  // sem produzir HTML inválido (ex.: <table>/<p> aninhado dentro de <p>/<b>).
  // Quando o cursor está no meio de um bloco existente, o bloco é "partido":
  // o conteúdo antes do cursor permanece, os novos nós são inseridos como
  // irmãos, e o restante (se houver) vira um novo bloco do mesmo tipo depois.
  function insertBlockAtCursor(nodes: Node[], cursorNode: Node, atStart = true): void {
    const range = getActiveRange()
    range.deleteContents()

    const blockAncestor = findBlockAncestor(range.startContainer)
    const frag = document.createDocumentFragment()
    nodes.forEach(n => frag.appendChild(n))

    if (!blockAncestor) {
      range.insertNode(frag)
      placeCursor(cursorNode, atStart)
      return
    }

    const afterRange = document.createRange()
    afterRange.setStart(range.startContainer, range.startOffset)
    afterRange.setEnd(blockAncestor, blockAncestor.childNodes.length)
    const afterFragment = afterRange.extractContents()

    if (!blockAncestor.hasChildNodes()) {
      blockAncestor.appendChild(document.createElement('br'))
    }

    const parent = blockAncestor.parentNode
    const refNode = blockAncestor.nextSibling
    parent.insertBefore(frag, refNode)

    const hasAfterContent = afterFragment.textContent.trim() !== ''
      || afterFragment.querySelector('br, img, table, .ph-chip')
    if (hasAfterContent) {
      const afterBlock = document.createElement(blockAncestor.tagName)
      afterBlock.appendChild(afterFragment)
      parent.insertBefore(afterBlock, refNode)
    }

    placeCursor(cursorNode, atStart)
  }

  function parseFontSize(el: Element | null): number | null {
    if (!el || el.tagName !== 'FONT') return null
    const raw = Number.parseInt(el.getAttribute('size') || '', 10)
    return Number.isFinite(raw) && raw >= MIN_FONT_SIZE && raw <= MAX_FONT_SIZE ? raw : null
  }

  function findFontElement(range: Range): HTMLElement | null {
    let el: HTMLElement | null = range.startContainer.nodeType === Node.TEXT_NODE
      ? range.startContainer.parentElement
      : (range.startContainer as HTMLElement)

    while (el && el !== editorRef?.value) {
      if (el.tagName === 'FONT' && el.hasAttribute('size')) return el
      el = el.parentElement
    }

    if (!range.collapsed && range.startContainer.nodeType === Node.ELEMENT_NODE) {
      const candidate = range.startContainer.childNodes[range.startOffset]
      if (candidate && candidate.nodeType === Node.ELEMENT_NODE) {
        const candidateEl = candidate as HTMLElement
        if (candidateEl.tagName === 'FONT' && candidateEl.hasAttribute('size')) {
          return candidateEl
        }
        const nestedFont = candidateEl.querySelector<HTMLElement>('font[size]')
        if (nestedFont && (nestedFont.textContent || '') === range.toString()) {
          return nestedFont
        }
      }
    }

    return null
  }

  function getCurrentFontSize(range?: Range): number {
    if (range) {
      const fontEl = findFontElement(range)
      const size = parseFontSize(fontEl)
      if (size !== null) return size

      if (!range.collapsed) {
        const ancestor = range.commonAncestorContainer
        const ancestorEl = ancestor.nodeType === Node.TEXT_NODE
          ? ancestor.parentElement
          : (ancestor as Element | null)

        if (ancestorEl && editorRef?.value?.contains(ancestorEl)) {
          const fonts = Array.from(ancestorEl.querySelectorAll('font[size]'))
          for (const font of fonts) {
            if (range.intersectsNode(font)) {
              const nestedSize = parseFontSize(font)
              if (nestedSize !== null) return nestedSize
            }
          }
        }
      }
    }

    if (typeof document.queryCommandValue === 'function') {
      const val = Number.parseInt(document.queryCommandValue('fontSize'), 10)
      if (Number.isFinite(val) && val >= MIN_FONT_SIZE && val <= MAX_FONT_SIZE) {
        return val
      }
    }

    return DEFAULT_FONT_SIZE
  }

  function unwrapNestedFonts(root: ParentNode): void {
    const fonts = Array.from(root.querySelectorAll('font[size]'))
    for (const font of fonts) {
      const parent = font.parentNode
      if (!parent) continue
      while (font.firstChild) parent.insertBefore(font.firstChild, font)
      parent.removeChild(font)
    }
  }

  function selectElementContents(el: HTMLElement): void {
    const sel = window.getSelection()
    const newRange = document.createRange()
    newRange.selectNodeContents(el)
    sel?.removeAllRanges()
    sel?.addRange(newRange)
    savedRange = newRange.cloneRange()
    saveSelection()
  }

  function changeFontSize(delta: number): void {
    const range = getActiveRange()
    const current = getCurrentFontSize(range)
    const nextSize = Math.min(MAX_FONT_SIZE, Math.max(MIN_FONT_SIZE, current + delta))
    const existingFont = findFontElement(range)

    if (existingFont && (range.collapsed || (existingFont.textContent || '') === range.toString())) {
      existingFont.setAttribute('size', String(nextSize))
      if (!range.collapsed) {
        selectElementContents(existingFont)
      } else {
        saveSelection()
      }
      return
    }

    if (!range.collapsed) {
      if (typeof document.execCommand === 'function') {
        const htmlBefore = editorRef?.value?.innerHTML
        const applied = document.execCommand('fontSize', false, String(nextSize))
        const changed = !editorRef?.value || editorRef.value.innerHTML !== htmlBefore
        if (applied && changed) {
          saveSelection()
          return
        }
      }

      const fragment = range.extractContents()
      unwrapNestedFonts(fragment)
      const font = document.createElement('font')
      font.setAttribute('size', String(nextSize))
      font.appendChild(fragment)
      range.insertNode(font)
      selectElementContents(font)
      return
    }

    if (typeof document.execCommand === 'function') {
      document.execCommand('fontSize', false, String(nextSize))
      saveSelection()
    }
  }

  function increaseFontSize(): void {
    changeFontSize(1)
  }

  function decreaseFontSize(): void {
    changeFontSize(-1)
  }

  function exec(command: string, value: string | null = null): void {
    if (command === 'increaseFontSize') {
      increaseFontSize()
      return
    }
    if (command === 'decreaseFontSize') {
      decreaseFontSize()
      return
    }
    if (isSelectionInsideEditor()) {
      saveSelection()
    }
    focusEditor()
    restoreSelection()
    document.execCommand?.(command, false, value)
    saveSelection()
  }

  function insertPlaceholder(origem: string, campo: string): void {
    const chip = document.createElement('span')
    chip.className = 'ph-chip'
    chip.setAttribute('contenteditable', 'false')
    chip.dataset.origem = origem
    chip.dataset.campo = campo
    chip.textContent = `{{${origem}.${campo}}}`
    const spacer = document.createTextNode('\u200B')
    commitInsertion([chip, spacer], spacer, false)
  }

  function insertPageBreak() {
    const marker = document.createElement('div')
    marker.className = 'ph-page-break'
    marker.setAttribute('contenteditable', 'false')
    const label = document.createElement('span')
    label.textContent = 'Quebra de página'
    marker.appendChild(label)
    const after = document.createElement('p')
    after.appendChild(document.createElement('br'))
    insertBlockAtCursor([marker, after], after, true)
  }

  function insertTable(rows: number, cols: number): void {
    const r = Math.max(1, rows | 0)
    const c = Math.max(1, cols | 0)
    const table = document.createElement('table')
    table.className = 'ph-table'
    const tbody = document.createElement('tbody')
    for (let i = 0; i < r; i++) {
      const tr = document.createElement('tr')
      for (let j = 0; j < c; j++) {
        const td = document.createElement('td')
        td.appendChild(document.createElement('br'))
        tr.appendChild(td)
      }
      tbody.appendChild(tr)
    }
    table.appendChild(tbody)
    const after = document.createElement('p')
    after.appendChild(document.createElement('br'))
    insertBlockAtCursor([table, after], table.rows[0].cells[0], true)
  }

  function insertLink(url: string, texto?: string): void {
    const link = document.createElement('a')
    link.href = url
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    link.textContent = texto || url
    commitInsertion([link], link, false)
  }

  function insertImage(src: string): void {
    const img = document.createElement('img')
    img.src = src
    img.alt = 'imagem inserida'
    img.style.maxWidth = '100%'
    img.style.height = 'auto'
    commitInsertion([img], img, false)
  }

  function sanitizePaste(event: ClipboardEvent): void {
    const clipboard = event.clipboardData
    if (!clipboard) return
    event.preventDefault()

    const html = clipboard.getData('text/html')
    const container = document.createElement('div')

    if (html) {
      container.innerHTML = html
      sanitizeNode(container)
    } else {
      const text = clipboard.getData('text/plain') || ''
      text.split(/\r?\n/).forEach(line => {
        const p = document.createElement('p')
        if (line) p.textContent = line
        else p.appendChild(document.createElement('br'))
        container.appendChild(p)
      })
    }

    const nodes = Array.from(container.childNodes)
    if (!nodes.length) return

    const hasBlockContent = nodes.some(n => {
      if (n.nodeType !== Node.ELEMENT_NODE) return false
      const tagName = (n as Element).tagName
      return BLOCK_ANCESTOR_TAGS.has(tagName) || tagName === 'UL' || tagName === 'OL'
    })
    if (hasBlockContent) {
      insertBlockAtCursor(nodes, nodes[nodes.length - 1], false)
    } else {
      commitInsertion(nodes, nodes[nodes.length - 1], false)
    }
  }

  function handleBoundaryDeletion(event: KeyboardEvent): boolean {
    if (event.key !== 'Backspace' && event.key !== 'Delete') return false
    const sel = window.getSelection()
    if (!sel || sel.rangeCount === 0 || !sel.isCollapsed) return false
    const range = sel.getRangeAt(0)
    const node = event.key === 'Backspace' ? nodeBefore(range) : nodeAfter(range)
    if (node && node.nodeType === Node.ELEMENT_NODE && (node as Element).classList.contains('ph-chip')) {
      event.preventDefault()
      if (isZwspOnly(node.nextSibling)) (node.nextSibling as ChildNode).remove()
      ;(node as ChildNode).remove()
      return true
    }
    return false
  }

  function isZwspOnly(node: Node | null): boolean {
    return !!node && node.nodeType === Node.TEXT_NODE && node.textContent === '\u200B'
  }

  // O marcador invisível (\u200B) inserido logo após um chip é apenas um
  // ponto de ancoragem do cursor — não conta como conteúdo real ao decidir
  // se o Backspace/Delete está "encostado" no chip.
  function nodeBefore(range: Range): Node | null {
    const { startContainer, startOffset } = range
    let node: Node | null
    if (startContainer.nodeType === Node.TEXT_NODE) {
      node = startOffset > 0 ? null : startContainer.previousSibling
    } else {
      node = startContainer.childNodes[startOffset - 1] || null
    }
    if (isZwspOnly(node)) node = node.previousSibling
    return node
  }

  function nodeAfter(range: Range): Node | null {
    const { startContainer, startOffset } = range
    let node: Node | null
    if (startContainer.nodeType === Node.TEXT_NODE) {
      node = startOffset < (startContainer as Text).length ? null : startContainer.nextSibling
    } else {
      node = startContainer.childNodes[startOffset] || null
    }
    if (isZwspOnly(node)) node = node.nextSibling
    return node
  }

  function serialize(html?: string | null): string {
    if (!html) return ''
    const container = document.createElement('div')
    container.innerHTML = html
    container.querySelectorAll<HTMLElement>('.ph-chip').forEach(chip => {
      chip.replaceWith(document.createTextNode(`{{${chip.dataset.origem}.${chip.dataset.campo}}}`))
    })
    container.querySelectorAll('.ph-page-break').forEach(marker => {
      const clean = document.createElement('div')
      clean.className = 'doc-page-break'
      marker.replaceWith(clean)
    })
    return container.innerHTML.replace(/\u200B/g, '')
  }

  function hydrate(html?: string | null): string {
    if (!html) return ''
    let result = html.replace(PLACEHOLDER_RE, (match, origem, campo) => {
      return `<span class="ph-chip" contenteditable="false" data-origem="${origem}" data-campo="${campo}">{{${origem}.${campo}}}</span>\u200B`
    })
    result = result.replace(
      /<div class="doc-page-break"><\/div>/g,
      '<div class="ph-page-break" contenteditable="false"><span>Quebra de página</span></div>'
    )
    return result
  }

  function extractPlaceholdersUsed(html?: string | null): PlaceholderRef[] {
    if (!html) return []
    const container = document.createElement('div')
    container.innerHTML = html
    const text = container.textContent || ''
    const found = new Map<string, PlaceholderRef>()
    const re = new RegExp(PLACEHOLDER_RE.source, 'g')
    let match: RegExpExecArray | null
    while ((match = re.exec(text)) !== null) {
      const key = `${match[1]}.${match[2]}`
      if (!found.has(key)) found.set(key, { origem: match[1], campo: match[2] })
    }
    return Array.from(found.values())
  }

  return {
    exec,
    changeFontSize,
    increaseFontSize,
    decreaseFontSize,
    insertPlaceholder,
    insertPageBreak,
    insertTable,
    insertLink,
    insertImage,
    sanitizePaste,
    handleBoundaryDeletion,
    saveSelection,
    restoreSelection,
    serialize,
    hydrate,
    extractPlaceholdersUsed,
  }
}
