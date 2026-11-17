/**
 * Regra de acesso de cada tela (`currentView`): módulo + permissões aceitas,
 * espelhando os filtros das rotas da API (`modulo:<slug>` + `permission:`).
 * Tela fora do mapa é livre (dashboard, configurações...).
 */
import { liberado } from '../composables/useAcesso'

type Regra = [slug: string, ...permissoes: string[]]

const FINANCEIRO: Regra = ['financeiro', 'financeiro.access']
const CONTABIL: Regra = ['contabil', 'contabil.access']
// Site público: módulo `site` + a permissão de cada grupo de rotas da API.
const SITE_CONTEUDO: Regra = ['site', 'site.conteudo']
const SITE_CONFIGURACOES: Regra = ['site', 'site.configuracoes']

export const REGRAS_TELAS: Record<string, Regra> = {

  'clientes': ['clientes', 'clientes.access'],
  'cliente-detail': ['clientes', 'clientes.access'],
  'crm': ['crm', 'clientes.access'],
  'crm-leads': ['crm', 'clientes.access'],
  'contratos': ['contratos', 'contratos.access'],
  'contrato-detail': ['contratos', 'contratos.access'],
  'fornecedores': ['fornecedores', 'fornecedores.access'],
  'fornecedor-detail': ['fornecedores', 'fornecedores.access'],
  'tarefas': ['tarefas', 'tarefas.access'],

  // Documentação interna: módulo `documentacao` + leitura.
  'documentacao': ['documentacao', 'documentacao.access'],
  'documentacao-admin': ['documentacao', 'documentacao.editar'],
  'documentacao-editor': ['documentacao', 'documentacao.editar'],

  'extrato': FINANCEIRO,
  'extrato-importacao': FINANCEIRO,
  'contas': FINANCEIRO,
  'extrato-movimentos': FINANCEIRO,
  'relatorio-categorias': FINANCEIRO,
  'balanco': FINANCEIRO,
  'dre': FINANCEIRO,
  'previsao-fluxo-caixa': FINANCEIRO,
  'fechamento-mes': FINANCEIRO,
  'despesas': FINANCEIRO,
  'despesa-detail': FINANCEIRO,
  'despesas-pagas': FINANCEIRO,
  'despesa-paga-detail': FINANCEIRO,
  'receitas': FINANCEIRO,
  'receita-detail': FINANCEIRO,
  'receitas-recebidas': FINANCEIRO,
  'receita-recebida-detail': FINANCEIRO,
  'transferencias': FINANCEIRO,
  'transferencia-detail': FINANCEIRO,

  'contabil': CONTABIL,
  'contabil-importar': CONTABIL,
  'contabil-lancamentos': CONTABIL,
  'contabil-plano-contas': CONTABIL,
  'contabil-relatorios': CONTABIL,
  'contabil-livros': CONTABIL,
  'contabil-periodos': CONTABIL,

  'business-canvas': ['gestao', 'business_canvas.access', 'business_canvas.exclusive'],

  'noticias': SITE_CONTEUDO,
  'noticias-form': SITE_CONTEUDO,
  'noticias-categorias': SITE_CONTEUDO,
  'site-paginas': SITE_CONTEUDO,
  'site-pagina-editor': SITE_CONTEUDO,
  'site-menus': SITE_CONTEUDO,
  'site-midias': SITE_CONTEUDO,
  'site-configuracoes': SITE_CONFIGURACOES,
  'site-aparencia': SITE_CONFIGURACOES,
  'site-templates': ['site', 'site.templates'],
}

/** Verdadeiro se o usuário pode abrir a tela. */
export function telaLiberada(view: string): boolean {
  const regra = REGRAS_TELAS[view]
  return !regra || liberado(...regra)
}
