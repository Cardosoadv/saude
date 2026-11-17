/**
 * Ativa/desativa um módulo com atualização otimista: a tela muda na hora e
 * volta ao estado anterior se a API recusar.
 */
export interface Modulo {
  id: number
  slug: string | null
  nome: string
  ativo: boolean
}

export interface RespostaToggle {
  success: boolean
  message?: string
  data?: Modulo
}

/** Espelha ModulosService::ESSENCIAIS no backend: sem eles ninguém reativa os demais. */
export const MODULOS_ESSENCIAIS = ['administrativo']

export const moduloEssencial = (m: Modulo) => !!m.slug && MODULOS_ESSENCIAIS.includes(m.slug)

/**
 * Inverte `m.ativo`, chama `salvar` e devolve a mensagem para o aviso.
 * Em erro, restaura `m.ativo` e devolve `ok: false`.
 */
export async function alternarModulo(
  m: Modulo,
  salvar: (slug: string, ativo: boolean) => Promise<RespostaToggle>,
): Promise<{ ok: boolean; mensagem: string }> {
  if (!m.slug) return { ok: false, mensagem: 'Módulo sem identificador.' }

  const anterior = m.ativo
  m.ativo = !anterior
  try {
    const res = await salvar(m.slug, m.ativo)
    if (!res?.success) throw new Error(res?.message || 'Não foi possível alterar o módulo.')
    if (res.data) Object.assign(m, res.data)
    return { ok: true, mensagem: res.message || (m.ativo ? 'Módulo ativado.' : 'Módulo desativado.') }
  } catch (err: any) {
    m.ativo = anterior
    // Erros de validação da API (400) chegam em data.messages.
    const detalhe = err?.data?.messages ? Object.values(err.data.messages).join(' ') : ''
    return { ok: false, mensagem: detalhe || err?.message || 'Erro ao alterar o módulo.' }
  }
}
