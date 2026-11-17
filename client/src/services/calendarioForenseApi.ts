/**
 * Calendário forense e memória de cálculo de prazos (API: api/v2/calendario-forense).
 * A contagem em dias úteis é feita no servidor; aqui só se consulta e se cadastra o calendário.
 */
import { apiFetch } from './api'
import type { Id } from '../types/domain'

export type Abrangencia = 'nacional' | 'estadual' | 'municipal'
export type MotivoDesconto = 'fim_de_semana' | 'feriado' | 'recesso' | 'suspensao'

export interface Feriado {
  id_feriado?: Id
  data: string
  nome: string
  abrangencia: Abrangencia
  uf?: string | null
  municipio?: string | null
  tribunal?: string | null
  recorrente?: number | boolean
}

export interface Suspensao {
  id_suspensao?: Id
  tribunal?: string | null
  data_inicio: string
  data_fim: string
  motivo: string
}

export interface DiaDescontado {
  data: string
  motivo: MotivoDesconto
  descricao: string
}

/** Passos da contagem. Prazos sem número de dias trazem só `metodo`, `vencimento` e `avisos`. */
export interface MemoriaCalculo {
  metodo: 'dias_uteis' | 'estimativa' | 'padrao' | 'data_informada'
  vencimento: string | null
  avisos: string[]
  a_conferir?: boolean
  origem?: string
  tipo_ato?: string | null
  base_legal?: string[]
  entrada?: {
    data_disponibilizacao: string
    dias: number
    tribunal?: string | null
    uf?: string | null
    comarca?: string | null
  }
  data_publicacao?: string
  inicio_contagem?: string
  dias_uteis?: number
  dias_contados?: string[]
  descontados?: DiaDescontado[]
}

export interface RespostaMemoria {
  disponivel: boolean
  a_conferir?: boolean
  prazo?: string | null
  memoria?: MemoriaCalculo | null
  motivo?: string
  data_estimada_ia?: string | null
}

const RAIZ = 'calendario-forense'

export async function memoriaDaTarefa(id: Id): Promise<RespostaMemoria> {
  const res = await apiFetch<{ data: RespostaMemoria }>(`${RAIZ}/memoria/tarefa/${id}`)
  return res.data
}

export async function memoriaDaIntimacao(id: Id): Promise<RespostaMemoria> {
  const res = await apiFetch<{ data: RespostaMemoria }>(`${RAIZ}/memoria/intimacao/${id}`)
  return res.data
}

export async function listarFeriados(ano?: number): Promise<Feriado[]> {
  const res = await apiFetch<{ data?: Feriado[] }>(`${RAIZ}/feriados${ano ? `?ano=${ano}` : ''}`)
  return res?.data ?? []
}

export function salvarFeriado(feriado: Feriado) {
  const id = feriado.id_feriado
  return apiFetch(`${RAIZ}/feriados${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: feriado })
}

export function excluirFeriado(id: Id) {
  return apiFetch(`${RAIZ}/feriados/${id}`, { method: 'DELETE' })
}

export async function listarSuspensoes(): Promise<Suspensao[]> {
  const res = await apiFetch<{ data?: Suspensao[] }>(`${RAIZ}/suspensoes`)
  return res?.data ?? []
}

export function salvarSuspensao(suspensao: Suspensao) {
  const id = suspensao.id_suspensao
  return apiFetch(`${RAIZ}/suspensoes${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: suspensao })
}

export function excluirSuspensao(id: Id) {
  return apiFetch(`${RAIZ}/suspensoes/${id}`, { method: 'DELETE' })
}
