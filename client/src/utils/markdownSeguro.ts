/**
 * Markdown mínimo e seguro para as respostas do Copiloto.
 *
 * Todo HTML é escapado ANTES de qualquer formatação, então nada que venha do modelo vira tag. Suporta:
 * parágrafos, quebras de linha, títulos (como negrito), listas, **negrito**, *itálico*, `código` e links
 * `[rótulo](ordo://tipo/id)`, que viram botões com `data-tipo`/`data-id` para a navegação interna.
 * Qualquer outro link vira só o rótulo.
 */

export const TIPOS_REFERENCIA = ['processo', 'cliente', 'contrato', 'caso', 'liquidacao', 'intimacao', 'tarefa'] as const

export function escaparHtml(texto: string): string {
  return texto
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function inline(texto: string): string {
  // Código primeiro, guardado fora das demais regras.
  const codigos: string[] = []
  let t = texto.replace(/`([^`\n]+)`/g, (_, c: string) => {
    codigos.push(`<code>${c}</code>`)
    return `\u0000${codigos.length - 1}\u0000`
  })

  t = t.replace(/\[([^\]\n]{1,200})\]\(([^)\s]+)\)/g, (_, rotulo: string, url: string) => {
    const m = /^ordo:\/\/([a-z]+)\/(\d{1,12})$/.exec(url)
    if (m && (TIPOS_REFERENCIA as readonly string[]).includes(m[1])) {
      return `<button type="button" class="copiloto-ref" data-tipo="${m[1]}" data-id="${m[2]}">${rotulo}</button>`
    }
    return rotulo
  })

  t = t
    .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
    .replace(/__([^_\n]+)__/g, '<strong>$1</strong>')
    .replace(/(^|[^*\w])\*([^*\n]+)\*(?!\w)/g, '$1<em>$2</em>')
    .replace(/(^|[^_\w])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>')

  return t.replace(/\u0000(\d+)\u0000/g, (_, i: string) => codigos[Number(i)] ?? '')
}

export function renderizarMarkdown(markdown: string): string {
  const linhas = escaparHtml(markdown.replace(/\r\n?/g, '\n')).split('\n')
  const blocos: string[] = []
  let paragrafo: string[] = []
  let lista: { tipo: 'ul' | 'ol'; itens: string[] } | null = null

  const fecharParagrafo = () => {
    if (paragrafo.length) blocos.push(`<p>${paragrafo.map(inline).join('<br>')}</p>`)
    paragrafo = []
  }
  const fecharLista = () => {
    if (lista) blocos.push(`<${lista.tipo}>${lista.itens.map((i) => `<li>${inline(i)}</li>`).join('')}</${lista.tipo}>`)
    lista = null
  }

  for (const linha of linhas) {
    const marcador = /^\s*(?:[-*•]|(\d+)[.)])\s+(.*)$/.exec(linha)
    const titulo = /^\s{0,3}#{1,6}\s+(.*)$/.exec(linha)

    if (linha.trim() === '') {
      fecharParagrafo()
      fecharLista()
    } else if (marcador) {
      fecharParagrafo()
      const tipo = marcador[1] ? 'ol' : 'ul'
      if (lista && lista.tipo !== tipo) fecharLista()
      lista ??= { tipo, itens: [] }
      lista.itens.push(marcador[2])
    } else if (titulo) {
      fecharParagrafo()
      fecharLista()
      blocos.push(`<p><strong>${inline(titulo[1])}</strong></p>`)
    } else {
      fecharLista()
      paragrafo.push(linha)
    }
  }
  fecharParagrafo()
  fecharLista()

  return blocos.join('')
}
