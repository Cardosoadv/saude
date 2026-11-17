import { describe, it, expect } from 'vitest'
import { renderizarMarkdown } from '../src/utils/markdownSeguro'

describe('renderizarMarkdown', () => {
  it('escapa HTML vindo do modelo', () => {
    const html = renderizarMarkdown('<script>alert(1)</script> <img src=x onerror="alert(1)"> **<b>x</b>**')
    expect(html).not.toContain('<script')
    expect(html).not.toContain('<img')
    expect(html).not.toContain('<b>')
    expect(html).toContain('&lt;script&gt;')
    expect(html).toContain('<strong>&lt;b&gt;x&lt;/b&gt;</strong>')
  })

  it('só transforma links ordo:// de tipo conhecido em botões', () => {
    const html = renderizarMarkdown('[P](ordo://processo/12) [J](javascript:alert(1)) [S](https://mal.example) [U](ordo://usuario/3) [X](ordo://processo/12" onclick="x)')
    expect(html).toContain('<button type="button" class="copiloto-ref" data-tipo="processo" data-id="12">P</button>')
    expect(html).not.toContain('javascript:')
    expect(html).not.toContain('href')
    expect(html).not.toContain('mal.example')
    expect(html).not.toContain('data-tipo="usuario"')
    expect(html).not.toMatch(/<[^>]+onclick/)
  })

  it('formata parágrafos, listas, títulos, negrito, itálico e código', () => {
    const html = renderizarMarkdown('# Resumo\nlinha 1\nlinha 2\n\n- item **forte**\n- item *leve*\n\n1. um\n2. `codigo`')
    expect(html).toBe(
      '<p><strong>Resumo</strong></p><p>linha 1<br>linha 2</p>'
      + '<ul><li>item <strong>forte</strong></li><li>item <em>leve</em></li></ul>'
      + '<ol><li>um</li><li><code>codigo</code></li></ol>',
    )
  })

  it('não aplica formatação dentro de código', () => {
    expect(renderizarMarkdown('`**x** [a](ordo://processo/1)`')).toBe('<p><code>**x** [a](ordo://processo/1)</code></p>')
  })
})
