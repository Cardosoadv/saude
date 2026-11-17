/**
 * Regras da tela de templates do site. Puro (sem Vue nem DOM) para testar em Node.
 */
import type { SiteTemplate, SiteTemplateAtivacao } from '../types/site'
import type { ManifestoDoPacote } from './zipManifesto'

export const ROTULO_ORIGEM: Record<string, string> = {
  core: 'Padrão do sistema',
  upload: 'Enviado pelo painel',
  cli: 'Instalado pela linha de comando',
}

/** Compara versões `1.2.10` × `1.2.9` por partes numéricas (sufixos como `-beta` são ignorados). Devolve -1, 0 ou 1. */
export function compararVersoes(a: string, b: string): number {
  const partes = (v: string) => v.split(/[.-]/).map((p) => parseInt(p, 10)).map((n) => (Number.isNaN(n) ? 0 : n))
  const [pa, pb] = [partes(a), partes(b)]
  for (let i = 0; i < Math.max(pa.length, pb.length, 3); i++) {
    const [x, y] = [pa[i] ?? 0, pb[i] ?? 0]
    if (x !== y) return x < y ? -1 : 1
  }
  return 0
}

export type AcaoDeInstalacao = 'instalar' | 'atualizar' | 'reinstalar' | 'versao-antiga' | 'bloqueado'

export interface ResumoDeInstalacao {
  acao: AcaoDeInstalacao
  texto: string
}

/** O que o envio vai fazer, a partir do `template.json` do pacote e dos templates já instalados. */
export function resumoDeInstalacao(pacote: ManifestoDoPacote, instalados: Pick<SiteTemplate, 'slug' | 'versao' | 'origem' | 'nome'>[]): ResumoDeInstalacao {
  const existente = instalados.find((t) => t.slug === pacote.slug)
  const nome = `"${pacote.nome}" ${pacote.versao}`

  if (!existente) return { acao: 'instalar', texto: `Instalar ${nome}. O template não será ativado automaticamente.` }
  if (existente.origem !== 'upload') {
    return { acao: 'bloqueado', texto: `Já existe o template "${existente.nome}" (${existente.origem === 'core' ? 'padrão do sistema' : 'instalado pela linha de comando'}) com o mesmo identificador "${pacote.slug}". Ele não pode ser sobrescrito por aqui; troque o slug no pacote.` }
  }
  const cmp = compararVersoes(pacote.versao, existente.versao)
  if (cmp > 0) return { acao: 'atualizar', texto: `Atualizar "${existente.nome}" de ${existente.versao} para ${pacote.versao}. Os arquivos do template são trocados; o conteúdo do site não muda.` }
  if (cmp === 0) return { acao: 'reinstalar', texto: `A versão ${pacote.versao} de "${existente.nome}" já está instalada. Enviar de novo troca os arquivos pelos do pacote.` }
  return { acao: 'versao-antiga', texto: `O pacote (${pacote.versao}) é mais antigo que o instalado (${existente.versao}). Enviar troca os arquivos e volta o template para a versão antiga.` }
}

/** Seções da ativação que pedem atenção, agrupadas por página (para o relatório de compatibilidade). */
export function agruparPorPagina(secoes: SiteTemplateAtivacao['secoes']): Array<{ pagina: string; paginaId: number; itens: SiteTemplateAtivacao['secoes'] }> {
  const grupos = new Map<number, { pagina: string; paginaId: number; itens: SiteTemplateAtivacao['secoes'] }>()
  for (const s of secoes) {
    const g = grupos.get(s.pagina_id) ?? { pagina: s.pagina, paginaId: s.pagina_id, itens: [] }
    g.itens.push(s)
    grupos.set(s.pagina_id, g)
  }
  return [...grupos.values()]
}

export const descreverAcao = (acao: 'incompativel' | 'revisar'): string =>
  acao === 'incompativel' ? 'tipo inexistente neste template (fica oculta, conteúdo preservado)' : 'precisa de revisão no novo template'

/** Erros do relatório de validação (422 do upload): `{caminho: mensagem}` → lista ordenada. */
export function linhasDoRelatorio(erros: Record<string, string>): Array<{ onde: string; mensagem: string }> {
  return Object.entries(erros).map(([onde, mensagem]) => ({ onde, mensagem }))
}
