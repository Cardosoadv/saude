/**
 * Regras do cliente para arquivos de contratos. O backend revalida tudo (tipo pelo
 * conteúdo, tamanho e quantidade); aqui só se evita o envio inútil.
 */
import { formatarTamanho } from './midias'

export const CATEGORIAS_ARQUIVO = ['Contrato original', 'Aditivo', 'Anexo', 'Outros'] as const

const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
export const TIPOS_ACEITOS = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/msword', DOCX] as const
export const TAMANHO_MAXIMO = 20 * 1024 * 1024
export const MAX_ARQUIVOS = 5

export const ACCEPT_INPUT = [...TIPOS_ACEITOS, '.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png', '.webp'].join(',')

type ArquivoBasico = { name: string; type: string; size: number }

/** Alguns navegadores deixam o tipo vazio para Word; nesse caso vale a extensão. */
function tipoAceito(arquivo: ArquivoBasico): boolean {
  if ((TIPOS_ACEITOS as readonly string[]).includes(arquivo.type)) return true
  return arquivo.type === '' && /\.(docx?|pdf|jpe?g|png|webp)$/i.test(arquivo.name)
}

/** Mensagem de erro, ou null se o arquivo pode ser enviado. */
export function validarArquivo(arquivo: ArquivoBasico): string | null {
  if (!tipoAceito(arquivo)) {
    return `"${arquivo.name}": envie PDF, Word (DOC/DOCX), JPG, PNG ou WEBP.`
  }
  if (arquivo.size === 0) {
    return `"${arquivo.name}": o arquivo está vazio.`
  }
  if (arquivo.size > TAMANHO_MAXIMO) {
    return `"${arquivo.name}": o arquivo tem ${formatarTamanho(arquivo.size)}; o máximo é ${formatarTamanho(TAMANHO_MAXIMO)}.`
  }
  return null
}

/** Valida o lote inteiro (quantidade e cada arquivo). Devolve a primeira mensagem de erro, ou null. */
export function validarLote(arquivos: ArquivoBasico[]): string | null {
  if (arquivos.length === 0) return 'Selecione ao menos um arquivo.'
  if (arquivos.length > MAX_ARQUIVOS) return `Envie no máximo ${MAX_ARQUIVOS} arquivos por vez.`
  for (const arquivo of arquivos) {
    const erro = validarArquivo(arquivo)
    if (erro) return erro
  }
  return null
}

/** Ícone Bootstrap pelo tipo do arquivo. */
export function iconeDoArquivo(mime: string | null | undefined): string {
  if (mime === 'application/pdf') return 'bi bi-file-earmark-pdf'
  if (mime?.startsWith('image/')) return 'bi bi-file-earmark-image'
  return 'bi bi-file-earmark-word'
}
