import { apiFetch, ApiError } from './api'

/** Envelope devolvido pelo backend em /consultas/*; `data` é o retorno bruto da API Brasil. */
export interface RespostaConsulta {
  success?: boolean
  error?: boolean
  message?: string
  data?: any
}

export interface InfoCobranca {
  valor_consulta: number
  balance: string | null
  balance_before: string | null
  message: string
  homolog: boolean
}

export class ConsultaErro extends Error {}

/** Placa antiga (ABC1234) ou Mercosul (ABC1D23), sem hífen. */
export function normalizarPlaca(valor: string): string {
  return (valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7)
}

export function placaValida(valor: string): boolean {
  return /^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/.test(normalizarPlaca(valor))
}

export function somenteDigitos(valor: string): string {
  return (valor || '').replace(/\D/g, '')
}

export function formatarCpf(valor: string): string {
  const v = somenteDigitos(valor).slice(0, 11)
  if (v.length > 9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4')
  if (v.length > 6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3')
  if (v.length > 3) return v.replace(/(\d{3})(\d{1,3})/, '$1.$2')
  return v
}

export function formatarCnpj(valor: string): string {
  const v = somenteDigitos(valor).slice(0, 14)
  return v
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
}

/** Número CNJ (20 dígitos) no formato NNNNNNN-DD.AAAA.J.TR.OOOO; devolve o original se não tiver 20 dígitos. */
export function formatarNumeroCnj(valor: string): string {
  const v = somenteDigitos(valor)
  if (v.length !== 20) return valor || ''
  return `${v.slice(0, 7)}-${v.slice(7, 9)}.${v.slice(9, 13)}.${v.slice(13, 14)}.${v.slice(14, 16)}.${v.slice(16)}`
}

export function infoCobranca(res: RespostaConsulta): InfoCobranca {
  const d = res?.data ?? {}
  return {
    valor_consulta: Number(d.valor_consulta) || 0,
    balance: d.balance ?? null,
    balance_before: d.balance_before ?? null,
    message: d.message || res?.message || '',
    homolog: d.homolog === true,
  }
}

async function enviar(rota: string, corpo: Record<string, unknown>): Promise<RespostaConsulta> {
  let res: RespostaConsulta
  try {
    res = await apiFetch(rota, { method: 'POST', body: corpo })
  } catch (err) {
    // O corpo de erro traz `error: true` (booleano); a mensagem útil está em `message`.
    if (err instanceof ApiError && typeof err.data?.message === 'string' && err.data.message) {
      throw new ConsultaErro(err.data.message)
    }
    throw err
  }
  if (res?.error || res?.success === false) {
    throw new ConsultaErro(res?.message || 'Não foi possível concluir a consulta.')
  }
  return res
}

export interface ProcessoJudicial {
  numeroProcessoUnico?: string
  tribunal?: string
  uf?: string
  segmento?: string
  orgaoJulgador?: string
  classeProcessual?: { nome?: string }
  assuntosCNJ?: { titulo?: string; principal?: boolean }[]
  dataDistribuicao?: string
  statusPj?: { statusProcesso?: string; ramoDireito?: string }
  informacoesPartePessoaConsultada?: { polo?: string; tipo?: string }[]
  partes?: { nome?: string; polo?: string; tipo?: string }[]
  valorCausa?: { moeda?: string; valor?: number }
  segredoJustica?: boolean
  urlProcesso?: string
  [k: string]: unknown
}

export interface ResultadoProcessos {
  processos: ProcessoJudicial[]
  cadastral: any | null
  cobranca: InfoCobranca
}

/** Consulta ações e processos judiciais por CPF (CNPJ opcional). */
export async function consultarProcessos(cpf: string, cnpj?: string): Promise<ResultadoProcessos> {
  const corpo: Record<string, unknown> = { cpf: somenteDigitos(cpf) }
  if (cnpj && somenteDigitos(cnpj)) corpo.cnpj = somenteDigitos(cnpj)
  const res = await enviar('consultas/processos', corpo)
  const dados = res?.data?.data ?? {}
  const processos = dados?.acoesProcessos?.acoes?.processos
  return {
    processos: Array.isArray(processos) ? processos : [],
    cadastral: dados?.cadastralCompleto ?? null,
    cobranca: infoCobranca(res),
  }
}

export interface ResultadoVeiculo {
  veiculo: Record<string, any>
  cobranca: InfoCobranca
}

/** Consulta dados do veículo pela placa. */
export async function consultarVeiculo(placa: string): Promise<ResultadoVeiculo> {
  const res = await enviar('consultas/veiculo', { placa: normalizarPlaca(placa) })
  const veiculo = res?.data?.data
  if (!veiculo || typeof veiculo !== 'object') {
    throw new ConsultaErro('A consulta não retornou dados para esta placa.')
  }
  return { veiculo, cobranca: infoCobranca(res) }
}

/** Indica se o backend está em modo de homologação (dados fictícios, sem tarifa). */
export async function homologAtivo(): Promise<boolean> {
  try {
    const res: any = await apiFetch('consultas/status')
    return res?.homolog === true
  } catch {
    return false
  }
}

// ------------------------------------------------- importação para a carteira

/** Processo da consulta pronto para a conferência da importação em lote (mesmo formato de `importacao/ler`). */
export interface ProcessoParaImportar {
  linha: number
  numero_processo: string
  numeroprocessocommascara: string
  arquivo: Record<string, unknown>
  avisos: string[]
}

/** Polo da parte (A = ativo, P = passivo) a partir do texto da API Brasil; null se não for possível saber. */
export function poloDaParte(parte: { polo?: string; tipo?: string }): 'A' | 'P' | null {
  for (const texto of [parte?.polo, parte?.tipo]) {
    const t = (texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
    if (/ativ|autor|requerente|exequente|reclamante|impetrante|apelante|embargante/.test(t)) return 'A'
    if (/passiv|reu\b|requerid|executad|reclamad|impetrad|apelad|embargad/.test(t)) return 'P'
  }
  return null
}

/** "2023-01-10T00:00:00", "2023-01-10" ou "10/01/2023" → "2023-01-10"; null se não reconhecer. */
export function dataIso(valor?: string): string | null {
  const v = (valor || '').trim()
  let m = v.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (m) return `${m[1]}-${m[2]}-${m[3]}`
  m = v.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/)
  if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`
  return null
}

/** Só aceita links http(s): o retorno vem de terceiros. */
export function linkSeguro(url?: string): boolean {
  return !!url && /^https?:\/\//i.test(url)
}

/**
 * Converte um processo da API Brasil para a importação em lote. Os dados entram
 * como a fonte "arquivo" da conferência e são comparados com DJEN, DataJud e a
 * carteira antes de gravar. O CPF consultado vai como documento do cliente: se
 * já for cliente, a conferência o vincula sozinha.
 *
 * @returns null se o processo não tiver número CNJ válido (20 dígitos).
 */
export function processoParaImportar(p: ProcessoJudicial, cpfConsultado?: string): ProcessoParaImportar | null {
  const numero = somenteDigitos(p?.numeroProcessoUnico || '')
  if (numero.length !== 20) return null

  const sigla = (p.tribunal || '').toUpperCase().replace(/\s+/g, '')
  const valor = Number(p.valorCausa?.valor)
  const poloAtivo: string[] = []
  const poloPassivo: string[] = []
  for (const parte of p.partes || []) {
    const nome = (parte?.nome || '').trim()
    const polo = poloDaParte(parte)
    if (!nome || !polo) continue
    const lista = polo === 'A' ? poloAtivo : poloPassivo
    if (!lista.includes(nome)) lista.push(nome)
  }
  const documento = somenteDigitos(cpfConsultado || '')

  return {
    linha: 0,
    numero_processo: numero,
    numeroprocessocommascara: formatarNumeroCnj(numero),
    arquivo: {
      titulo_processo: null,
      cliente_documento: documento.length === 11 ? documento : null,
      cliente_nome: null,
      siglaTribunal: /^[A-Z0-9]{2,12}$/.test(sigla) ? sigla : null,
      nomeOrgao: p.orgaoJulgador?.trim() || null,
      tipoDocumento: p.classeProcessual?.nome?.trim() || null,
      dataDistribuicao: dataIso(p.dataDistribuicao),
      link: linkSeguro(p.urlProcesso) ? p.urlProcesso : null,
      valorCausa: Number.isFinite(valor) && valor > 0 ? valor.toFixed(2) : null,
      etiquetas: [],
      poloAtivo,
      poloPassivo,
    },
    avisos: [],
  }
}

/**
 * Processos enviados da consulta para a tela de importação. Fica só em memória
 * (contém dados pessoais) e é lido uma única vez.
 */
let importacaoPendente: ProcessoParaImportar[] | null = null

export function enviarParaImportacao(processos: ProcessoParaImportar[]): void {
  importacaoPendente = processos.length ? processos : null
}

export function retirarImportacaoPendente(): ProcessoParaImportar[] | null {
  const lista = importacaoPendente
  importacaoPendente = null
  return lista
}

// ------------------------------------------------- enriquecimento de cadastros

export type TipoCadastro = 'cliente' | 'fornecedor'

/** Cliente ou fornecedor com o documento consultado (só os que o usuário pode ver). */
export interface CadastroEncontrado {
  tipo: TipoCadastro
  id: number
  nome: string
  pode_editar: boolean
}

export interface SugestaoEnriquecimento {
  campo: string
  rotulo: string
  atual: string | null
  sugerido: string
}

export interface ResultadoEnriquecimento {
  /** cpf = API Brasil (tarifada); cnpj = BrasilAPI (gratuita). */
  fonte: 'cpf' | 'cnpj'
  sugestoes: SugestaoEnriquecimento[]
  /** Cobrança da consulta; null quando reaproveitou uma consulta já feita ou foi CNPJ. */
  consulta: { valor_consulta: number; balance: string | null; homolog: boolean } | null
  message: string
}

/** Cadastros com o CPF/CNPJ. Não consulta a API Brasil (sem custo). */
export async function cadastrosPorDocumento(documento: string): Promise<CadastroEncontrado[]> {
  const doc = somenteDigitos(documento)
  if (doc.length !== 11 && doc.length !== 14) return []
  const res: RespostaConsulta = await apiFetch(`consultas/cadastros/${doc}`)
  return Array.isArray(res?.data) ? res.data : []
}

/**
 * Sugestões para enriquecer o cadastro. Para CPF, `resultado` (o `data.resultado`
 * de uma consulta de CPF já feita) evita uma nova consulta tarifada.
 */
export async function sugerirEnriquecimento(tipo: TipoCadastro, id: number, resultado?: unknown): Promise<ResultadoEnriquecimento> {
  const res = await enviar(`consultas/enriquecimento/${tipo}/${id}`, resultado ? { resultado } : {})
  const d = res?.data ?? {}
  return {
    fonte: d.fonte === 'cnpj' ? 'cnpj' : 'cpf',
    sugestoes: Array.isArray(d.sugestoes) ? d.sugestoes : [],
    consulta: d.consulta ?? null,
    message: res?.message || '',
  }
}

/** Grava no cadastro os campos escolhidos; devolve os valores gravados. */
export async function aplicarEnriquecimento(tipo: TipoCadastro, id: number, campos: Record<string, string>): Promise<{ campos: Record<string, string>; message: string }> {
  const res = await enviar(`consultas/enriquecimento/${tipo}/${id}/aplicar`, { campos })
  return { campos: res?.data ?? {}, message: res?.message || 'Cadastro atualizado.' }
}

/** Documento pode ser enriquecido (CPF ou CNPJ completo). */
export function documentoEnriquecivel(documento?: string | null): boolean {
  const d = somenteDigitos(documento || '')
  return d.length === 11 || d.length === 14
}
