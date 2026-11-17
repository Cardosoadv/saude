/**
 * Leitura do `template.json` de dentro de um `.zip` no navegador, só para mostrar
 * "instalar" ou "atualizar de X para Y" antes do envio. É uma prévia: o backend é quem valida o pacote.
 * Qualquer problema (zip estranho, sem `template.json`, formato de compressão incomum) devolve `null`.
 */
const LIMITE_MANIFESTO = 1024 * 1024

export interface ManifestoDoPacote {
  slug: string
  nome: string
  versao: string
  autor?: string
  motor?: string
}

async function lerBytes(blob: Blob, inicio: number, tamanho: number): Promise<DataView> {
  return new DataView(await blob.slice(inicio, inicio + tamanho).arrayBuffer())
}

async function descomprimir(dados: Uint8Array): Promise<Uint8Array | null> {
  if (typeof DecompressionStream === 'undefined') return null
  const fluxo = new Blob([dados as BlobPart]).stream().pipeThrough(new DecompressionStream('deflate-raw'))
  return new Uint8Array(await new Response(fluxo).arrayBuffer())
}

export async function lerManifestoDoZip(zip: Blob): Promise<ManifestoDoPacote | null> {
  try {
    // 1. Fim do diretório central (EOCD): nos últimos 22 + 65535 bytes.
    const cauda = Math.min(zip.size, 22 + 65535)
    const base = zip.size - cauda
    const fim = await lerBytes(zip, base, cauda)
    let eocd = -1
    for (let i = cauda - 22; i >= 0; i--) {
      if (fim.getUint32(i, true) === 0x06054b50) { eocd = i; break }
    }
    if (eocd < 0) return null

    const total = fim.getUint16(eocd + 10, true)
    const tamanhoDiretorio = fim.getUint32(eocd + 12, true)
    const inicioDiretorio = fim.getUint32(eocd + 16, true)
    if (total === 0xffff || inicioDiretorio === 0xffffffff) return null // zip64: fora do escopo

    // 2. Entradas do diretório central em busca de `template.json` (na raiz ou numa única pasta).
    const diretorio = await lerBytes(zip, inicioDiretorio, tamanhoDiretorio)
    const texto = new TextDecoder()
    let pos = 0
    let achado: { metodo: number; comprimido: number; descomprimido: number; offset: number } | null = null
    for (let n = 0; n < total && pos + 46 <= diretorio.byteLength; n++) {
      if (diretorio.getUint32(pos, true) !== 0x02014b50) return null
      const nomeLen = diretorio.getUint16(pos + 28, true)
      const extraLen = diretorio.getUint16(pos + 30, true)
      const comentLen = diretorio.getUint16(pos + 32, true)
      const nome = texto.decode(new Uint8Array(diretorio.buffer, diretorio.byteOffset + pos + 46, nomeLen))
      if (/^([^/]+\/)?template\.json$/.test(nome)) {
        achado = {
          metodo: diretorio.getUint16(pos + 10, true),
          comprimido: diretorio.getUint32(pos + 20, true),
          descomprimido: diretorio.getUint32(pos + 24, true),
          offset: diretorio.getUint32(pos + 42, true),
        }
        break
      }
      pos += 46 + nomeLen + extraLen + comentLen
    }
    if (!achado || achado.descomprimido > LIMITE_MANIFESTO || achado.comprimido > LIMITE_MANIFESTO) return null

    // 3. Cabeçalho local e dados.
    const local = await lerBytes(zip, achado.offset, 30)
    if (local.getUint32(0, true) !== 0x04034b50) return null
    const inicioDados = achado.offset + 30 + local.getUint16(26, true) + local.getUint16(28, true)
    const bruto = new Uint8Array(await zip.slice(inicioDados, inicioDados + achado.comprimido).arrayBuffer())
    const conteudo = achado.metodo === 0 ? bruto : achado.metodo === 8 ? await descomprimir(bruto) : null
    if (!conteudo) return null

    const m = JSON.parse(texto.decode(conteudo))
    if (typeof m?.slug !== 'string' || typeof m?.versao !== 'string') return null
    return {
      slug: m.slug,
      nome: typeof m.nome === 'string' ? m.nome : m.slug,
      versao: m.versao,
      autor: typeof m.autor === 'string' ? m.autor : undefined,
      motor: typeof m.motor === 'string' ? m.motor : undefined,
    }
  } catch {
    return null
  }
}
