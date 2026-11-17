/**
 * Regra de acesso de cada tela (`currentView`): módulo + permissões aceitas,
 * espelhando os filtros das rotas da API (`modulo:<slug>` + `permission:`).
 * Tela fora do mapa é livre (dashboard, configurações, etiquetas...).
 */
import { liberado } from '../composables/useAcesso'

type Regra = [slug: string, ...permissoes: string[]]

const PROCESSOS: Regra = ['processos', 'processos.access', 'processos.exclusive']
const LIQUIDACOES: Regra = ['processos', 'liquidacoes.access', 'liquidacoes.exclusive']
const FINANCEIRO: Regra = ['financeiro', 'financeiro.access']
const CONTABIL: Regra = ['contabil', 'contabil.access']
const CONHECIMENTO: Regra = ['conhecimento', 'conhecimento.access']
// Site público (ordojuris#654): módulo `site` + a permissão de cada grupo de rotas da API.
const SITE_CONTEUDO: Regra = ['site', 'site.conteudo']
const SITE_CONFIGURACOES: Regra = ['site', 'site.configuracoes']

export const REGRAS_TELAS: Record<string, Regra> = {
  'list': PROCESSOS,
  'detail': PROCESSOS,
  'process-form': PROCESSOS,
  'busca-automatica': ['processos', 'processos.access'],
  'importacao-processos': ['processos', 'processos.create'],
  'liquidacoes': LIQUIDACOES,
  'liquidacao-detail': LIQUIDACOES,

  'clientes': ['clientes', 'clientes.access'],
  'cliente-detail': ['clientes', 'clientes.access'],
  'crm': ['crm', 'clientes.access'],
  'crm-leads': ['crm', 'clientes.access'],
  'casos': ['casos', 'casos.access'],
  'caso-detail': ['casos', 'casos.access'],
  'contratos': ['contratos', 'contratos.access'],
  'contrato-detail': ['contratos', 'contratos.access'],
  'fornecedores': ['fornecedores', 'fornecedores.access'],
  'fornecedor-detail': ['fornecedores', 'fornecedores.access'],
  'objetos': ['objetos', 'objetos.access'],
  'objeto-detail': ['objetos', 'objetos.access'],
  'intimacoes': ['intimacoes', 'intimacoes.access'],
  'intimacao-detail': ['intimacoes', 'intimacoes.access'],
  'tarefas': ['tarefas', 'tarefas.access'],

  // Documentação interna (ordojuris#699): módulo `documentacao` + leitura.
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

  // Rotas de checklist exigem só login: nem todo grupo tem checklist.*.
  'checklists': ['checklist'],
  'checklist-detail': ['checklist'],

  'business-canvas': ['gestao', 'business_canvas.access', 'business_canvas.exclusive'],
  'legal-ops': ['gestao'],
  'consulta-cpf': ['gestao', 'consultas.access'],
  'consulta-processos': ['gestao', 'consultas.access'],
  'consulta-veiculo': ['gestao', 'consultas.access'],

  'conhecimento': CONHECIMENTO,
  'modelos': CONHECIMENTO,
  'modelo-editor': CONHECIMENTO,
  'gerar-documento': CONHECIMENTO,

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
