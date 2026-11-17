// @vitest-environment happy-dom
/**
 * Testes para controle de tamanho da fonte (aumentar/diminuir) no DocumentoEditor.
 *
 * @version 1.0.0
 */
import { describe, it, expect, afterEach } from 'vitest'
import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import DocumentoEditor from '../src/components/editor/DocumentoEditor.vue'

const montados: { unmount(): void }[] = []

function montar(inicial = '<p>Texto de exemplo</p>', simples = false) {
  const valor = ref(inicial)
  const dirtyCount = ref(0)
  const raiz = document.createElement('div')
  document.body.appendChild(raiz)

  const Pai = defineComponent({
    render: () => h(DocumentoEditor, {
      modelValue: valor.value,
      simples,
      'onUpdate:modelValue': (v: string) => { valor.value = v },
      onDirty: () => { dirtyCount.value++ },
    }),
  })

  const app = createApp(Pai)
  app.mount(raiz)
  montados.push(app)

  return { raiz, valor, dirtyCount }
}

function selecionarTexto(editorBody: HTMLElement, textoAlvo: string) {
  const walker = document.createTreeWalker(editorBody, NodeFilter.SHOW_TEXT)
  let node: Node | null
  while ((node = walker.nextNode())) {
    const content = node.textContent || ''
    const idx = content.indexOf(textoAlvo)
    if (idx >= 0) {
      const range = document.createRange()
      range.setStart(node, idx)
      range.setEnd(node, idx + textoAlvo.length)
      const sel = window.getSelection()!
      sel.removeAllRanges()
      sel.addRange(range)
      editorBody.dispatchEvent(new Event('mouseup', { bubbles: true }))
      return
    }
  }
  throw new Error(`Texto "${textoAlvo}" não encontrado no editor`)
}

describe('DocumentoEditor - tamanho da fonte', () => {
  afterEach(() => {
    montados.splice(0).forEach((app) => { try { app.unmount() } catch { /* já desmontado */ } })
    document.body.innerHTML = ''
    window.getSelection()?.removeAllRanges()
  })

  it('exibe os botões de aumentar e diminuir fonte quando simples é false', async () => {
    const { raiz } = montar('<p>Texto</p>', false)
    await nextTick()

    expect(raiz.querySelector('button[title="Aumentar tamanho da fonte"]')).not.toBeNull()
    expect(raiz.querySelector('button[title="Diminuir tamanho da fonte"]')).not.toBeNull()
  })

  it('omite os botões de aumentar e diminuir fonte quando simples é true', async () => {
    const { raiz } = montar('<p>Texto</p>', true)
    await nextTick()

    expect(raiz.querySelector('button[title="Aumentar tamanho da fonte"]')).toBeNull()
    expect(raiz.querySelector('button[title="Diminuir tamanho da fonte"]')).toBeNull()
  })

  it('aumenta o tamanho da fonte do texto selecionado até o limite máximo (7) e atualiza v-model e dirty', async () => {
    const { raiz, valor, dirtyCount } = montar('<p>Texto de exemplo</p>', false)
    await nextTick()

    const editorBody = raiz.querySelector('.doc-editor-body') as HTMLElement
    const btnAumentar = raiz.querySelector('button[title="Aumentar tamanho da fonte"]') as HTMLButtonElement

    selecionarTexto(editorBody, 'exemplo')
    btnAumentar.click()
    await nextTick()

    expect(dirtyCount.value).toBe(1)
    expect(valor.value).toContain('<font size="4">exemplo</font>')

    for (const esperado of [5, 6, 7]) {
      btnAumentar.click()
      await nextTick()
      expect(valor.value).toContain(`<font size="${esperado}">exemplo</font>`)
    }

    // Não ultrapassa o máximo (7)
    btnAumentar.click()
    await nextTick()
    expect(valor.value).toContain('<font size="7">exemplo</font>')
    expect(dirtyCount.value).toBe(5)
  })

  it('diminui o tamanho da fonte do texto selecionado até o limite mínimo (1)', async () => {
    const { raiz, valor, dirtyCount } = montar('<p>Texto de exemplo</p>', false)
    await nextTick()

    const editorBody = raiz.querySelector('.doc-editor-body') as HTMLElement
    const btnDiminuir = raiz.querySelector('button[title="Diminuir tamanho da fonte"]') as HTMLButtonElement

    selecionarTexto(editorBody, 'exemplo')
    btnDiminuir.click()
    await nextTick()

    expect(dirtyCount.value).toBe(1)
    expect(valor.value).toContain('<font size="2">exemplo</font>')

    btnDiminuir.click()
    await nextTick()
    expect(valor.value).toContain('<font size="1">exemplo</font>')

    // Não reduz abaixo do mínimo (1)
    btnDiminuir.click()
    await nextTick()
    expect(valor.value).toContain('<font size="1">exemplo</font>')
    expect(dirtyCount.value).toBe(3)
  })
})
