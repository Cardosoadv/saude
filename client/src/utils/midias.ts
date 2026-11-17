/**
 * Regras do cliente para a biblioteca de mídias do site. O backend revalida tudo
 * (tipo pelo conteúdo, tamanho e dimensões); aqui só se evita o envio inútil.
 */
export const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'] as const
export const TAMANHO_MAXIMO = 5 * 1024 * 1024

// `.ico` explícito: alguns navegadores não associam o tipo ao seletor de arquivos.
export const ACCEPT_INPUT = [...TIPOS_ACEITOS, '.ico'].join(',')

/** O navegador às vezes informa o .ico com tipo vazio ou genérico; aí vale a extensão. */
function ehIco(arquivo: { name: string; type: string }): boolean {
  return /\.ico$/i.test(arquivo.name) && ['', 'application/octet-stream'].includes(arquivo.type)
}

export function formatarTamanho(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`
}

/** Mensagem de erro, ou null se o arquivo pode ser enviado. */
export function validarArquivo(arquivo: { name: string; type: string; size: number }): string | null {
  if (!(TIPOS_ACEITOS as readonly string[]).includes(arquivo.type) && !ehIco(arquivo)) {
    return `"${arquivo.name}": use uma imagem JPEG, PNG, GIF, WebP ou ICO.`
  }
  if (arquivo.size > TAMANHO_MAXIMO) {
    return `"${arquivo.name}": o arquivo tem ${formatarTamanho(arquivo.size)}; o máximo é ${formatarTamanho(TAMANHO_MAXIMO)}.`
  }
  if (arquivo.size === 0) {
    return `"${arquivo.name}": o arquivo está vazio.`
  }
  return null
}

export interface ResultadoEnvio<T> {
  nome: string
  ok: boolean
  midia?: T
  erro?: string
}

/**
 * Envia os arquivos um a um (o servidor processa imagens grandes; em paralelo estouraria o tempo).
 * Arquivo inválido nem é enviado. `aoProgredir` recebe o resultado de cada arquivo assim que sai.
 */
export async function enviarVarios<T>(
  arquivos: Array<File>,
  enviar: (arquivo: File) => Promise<T>,
  mensagemDeErro: (e: unknown) => string,
  aoProgredir?: (resultado: ResultadoEnvio<T>) => void,
): Promise<Array<ResultadoEnvio<T>>> {
  const resultados: Array<ResultadoEnvio<T>> = []
  for (const arquivo of arquivos) {
    const invalido = validarArquivo(arquivo)
    let resultado: ResultadoEnvio<T>
    if (invalido) {
      resultado = { nome: arquivo.name, ok: false, erro: invalido }
    } else {
      try {
        resultado = { nome: arquivo.name, ok: true, midia: await enviar(arquivo) }
      } catch (e) {
        resultado = { nome: arquivo.name, ok: false, erro: `"${arquivo.name}": ${mensagemDeErro(e)}` }
      }
    }
    resultados.push(resultado)
    aoProgredir?.(resultado)
  }
  return resultados
}
