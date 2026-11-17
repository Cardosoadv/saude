/**
 * Dicionário categoria financeira -> conta contábil - utilitários puros (sem acesso à API).
 *
 * A conta "da categoria" é o crédito nas receitas e o débito nas despesas. Cada transação
 * guarda a conta que foi sugerida (`sugestao_conta_id`), para que a tela perceba quando o
 * usuário trocou a sugestão e ofereça gravar a troca no dicionário.
 *
 * @version 1.0.0
 */
import type { DivisaoIntegracao, EntradaDicionarioCategoria, TransacaoIntegracao } from '../services/contabil'

type TipoCategoria = 'receita' | 'despesa'

/** Troca de conta de uma categoria, candidata a ir para o dicionário. */
export interface AlteracaoDicionario {
  categoria_id: number
  categoria: string
  tipo: TipoCategoria
  /** Conta escolhida pelo usuário. */
  account_id: number
  /** Conta que estava no dicionário (nula se a categoria ainda não tinha associação). */
  anterior_id: number | null
}

export const chaveDicionario = (tipo: string, categoriaId: number) => `${tipo}:${categoriaId}`

/** Campo da conta que a categoria define: crédito nas receitas, débito nas despesas. */
export function ladoCategoria(tipo: TransacaoIntegracao['tipo']): 'debito_id' | 'credito_id' {
  return tipo === 'receita' ? 'credito_id' : 'debito_id'
}

export function mapaDicionario(entradas: EntradaDicionarioCategoria[]): Record<string, number> {
  const mapa: Record<string, number> = {}
  for (const e of entradas) {
    if (e.account_id) mapa[chaveDicionario(e.tipo, e.categoria_id)] = e.account_id
  }
  return mapa
}

const temCategoria = (t: TransacaoIntegracao): t is TransacaoIntegracao & { tipo: TipoCategoria } =>
  t.tipo === 'receita' || t.tipo === 'despesa'

/** Guarda em cada transação (e parte da divisão) a conta sugerida pelo servidor. */
export function registrarSugestoes(transacoes: TransacaoIntegracao[]): void {
  for (const t of transacoes) {
    if (!temCategoria(t)) continue
    const lado = ladoCategoria(t.tipo)
    t.sugestao_conta_id = t[lado]
    t.divisoes?.forEach((d) => (d.sugestao_conta_id = d[lado]))
  }
}

/**
 * Categorias cuja conta o usuário trocou em relação à sugestão e que ainda diferem do
 * dicionário. Com várias partes da mesma categoria, vale a última.
 */
export function alteracoesDicionario(t: TransacaoIntegracao, mapa: Record<string, number>): AlteracaoDicionario[] {
  if (!temCategoria(t)) return []
  const lado = ladoCategoria(t.tipo)
  const nomeCategoria = (id: number) => t.categorias?.find((c) => c.categoria_id === id)?.categoria || `Categoria #${id}`

  const candidatos: { categoria_id: number; categoria: string; conta?: number; sugestao?: number }[] = t.divisoes?.length
    ? t.divisoes
        .filter((d): d is DivisaoIntegracao & { categoria_id: number } => !!d.categoria_id)
        .map((d) => ({ categoria_id: d.categoria_id, categoria: d.categoria || nomeCategoria(d.categoria_id), conta: d[lado], sugestao: d.sugestao_conta_id }))
    : t.categoria_id
      ? [{ categoria_id: t.categoria_id, categoria: nomeCategoria(t.categoria_id), conta: t[lado], sugestao: t.sugestao_conta_id }]
      : []

  const porCategoria = new Map<number, AlteracaoDicionario>()
  for (const c of candidatos) {
    const atual = mapa[chaveDicionario(t.tipo, c.categoria_id)] ?? null
    if (!c.conta || c.conta === c.sugestao || c.conta === atual) continue
    porCategoria.set(c.categoria_id, {
      categoria_id: c.categoria_id,
      categoria: c.categoria,
      tipo: t.tipo,
      account_id: c.conta,
      anterior_id: atual,
    })
  }
  return [...porCategoria.values()]
}

/**
 * Aplica as novas associações às transações pendentes. Só troca contas que o usuário não
 * mexeu (ainda iguais à sugestão); a sugestão passa a ser a conta do dicionário.
 *
 * @returns quantas transações tiveram alguma conta trocada
 */
export function aplicarDicionario(transacoes: TransacaoIntegracao[], alteracoes: AlteracaoDicionario[]): number {
  const novas = new Map(alteracoes.map((a) => [chaveDicionario(a.tipo, a.categoria_id), a.account_id]))
  let alteradas = 0

  for (const t of transacoes) {
    if (t.status_contabil !== 'pendente' || !temCategoria(t)) continue
    const lado = ladoCategoria(t.tipo)
    let mudou = false

    const aplicar = (alvo: TransacaoIntegracao | DivisaoIntegracao, categoriaId?: number | null) => {
      const nova = categoriaId ? novas.get(chaveDicionario(t.tipo, categoriaId)) : undefined
      if (nova === undefined) return
      if (alvo[lado] === alvo.sugestao_conta_id && alvo[lado] !== nova) {
        alvo[lado] = nova
        mudou = true
      }
      if (alvo[lado] === nova) {
        alvo.sugestao_conta_id = nova
        alvo.sugestao_origem = 'dicionario'
      }
    }

    if (t.divisoes?.length) t.divisoes.forEach((d) => aplicar(d, d.categoria_id))
    else aplicar(t, t.categoria_id)

    if (mudou) alteradas++
  }
  return alteradas
}
