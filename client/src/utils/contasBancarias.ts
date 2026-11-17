export interface ContaBancaria {
  id_conta?: number | null
  conta: string
  banco?: string | null
  agencia?: string | null
  numero_conta?: string | null
  pix?: string | null
  comentarios?: string | null
}

export function formVazio(): ContaBancaria {
  return { id_conta: null, conta: '', banco: '', agencia: '', numero_conta: '', pix: '', comentarios: '' }
}

/** A importação de extratos exige banco, agência e número da conta (senão a API devolve 422). */
export function contaCompleta(c: Pick<ContaBancaria, 'banco' | 'agencia' | 'numero_conta'>): boolean {
  return [c.banco, c.agencia, c.numero_conta].every((v) => !!v && String(v).trim() !== '')
}
