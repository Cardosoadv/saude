/**
 * Catálogo e regras da tela de configurações do site (`GET/PUT site/admin/configuracoes`).
 * Puro (sem Vue nem DOM) para ser testado em Node.
 *
 * O backend guarda cada configuração como `grupo.nome`. Chaves que ainda não existem no
 * banco precisam do tipo no envio (`{ valor, tipo }`); as existentes mantêm o seu.
 */
import type { SiteConfiguracaoTipo, SiteConfiguracoes } from '../types/site'

export type CampoUi = 'texto' | 'textarea' | 'imagem' | 'cor' | 'url' | 'email' | 'enderecos' | 'checkbox'

export interface CampoConfiguracao {
  chave: string
  rotulo: string
  ui: CampoUi
  tipo: SiteConfiguracaoTipo
  ajuda?: string
  placeholder?: string
  /** Contador de caracteres (limite do backend). */
  max?: number
}

export interface AbaConfiguracao {
  id: string
  rotulo: string
  icone: string
  campos: CampoConfiguracao[]
}

export const ABAS: AbaConfiguracao[] = [
  {
    id: 'identidade', rotulo: 'Identidade', icone: 'bi-building',
    campos: [
      { chave: 'identidade.nome', rotulo: 'Nome do escritório', ui: 'texto', tipo: 'string', placeholder: 'Silva & Souza Advogados Associados' },
      { chave: 'identidade.nome_curto', rotulo: 'Nome curto', ui: 'texto', tipo: 'string', placeholder: 'Silva & Souza', ajuda: 'Usado em títulos, metadados e no papel timbrado.' },
      { chave: 'identidade.logo', rotulo: 'Logo', ui: 'imagem', tipo: 'imagem', ajuda: 'Cabeçalho do site e documentos; também nos e-mails, se não houver logo próprio para eles.' },
      { chave: 'identidade.logo_rodape', rotulo: 'Logo do rodapé', ui: 'imagem', tipo: 'imagem' },
      { chave: 'identidade.logo_email', rotulo: 'Logo dos e-mails', ui: 'imagem', tipo: 'imagem', ajuda: 'Para quando os e-mails usam cores diferentes das do site. Vazio usa o Logo.' },
      { chave: 'identidade.favicon', rotulo: 'Favicon', ui: 'imagem', tipo: 'imagem' },
      { chave: 'identidade.sobre_rodape', rotulo: 'Texto "sobre" do rodapé', ui: 'textarea', tipo: 'text', max: 5000 },
      { chave: 'identidade.cor_documentos', rotulo: 'Cor dos documentos', ui: 'cor', tipo: 'cor', ajuda: 'Cor de destaque do papel timbrado. Vazio usa o padrão.' },
      { chave: 'identidade.cnpj', rotulo: 'CNPJ', ui: 'texto', tipo: 'string', placeholder: '00.000.000/0001-00', ajuda: 'Aparece na Política de Privacidade, como dado do controlador.' },
    ],
  },
  {
    id: 'contato', rotulo: 'Contato', icone: 'bi-telephone',
    campos: [
      { chave: 'contato.telefone', rotulo: 'Telefone', ui: 'texto', tipo: 'string', placeholder: '(31) 9.9999-9999' },
      { chave: 'contato.telefone2', rotulo: 'WhatsApp / segundo telefone', ui: 'texto', tipo: 'string', placeholder: '(31) 9.9999-9999' },
      { chave: 'contato.telefone_whatsapp', rotulo: 'Transformar os telefones em link do WhatsApp', ui: 'checkbox', tipo: 'bool', ajuda: 'No site, os telefones passam a abrir uma conversa no WhatsApp (wa.me).' },
      { chave: 'contato.email', rotulo: 'E-mail', ui: 'email', tipo: 'string', placeholder: 'contato@escritorio.adv.br' },
      { chave: 'contato.site', rotulo: 'Endereço do site', ui: 'texto', tipo: 'string', placeholder: 'www.escritorio.adv.br', ajuda: 'Aparece no rodapé dos documentos. Vazio usa o endereço do sistema.' },
      { chave: 'contato.enderecos', rotulo: 'Unidades', ui: 'enderecos', tipo: 'json' },
    ],
  },
  {
    id: 'redes', rotulo: 'Redes sociais', icone: 'bi-share',
    campos: [
      { chave: 'redes.instagram', rotulo: 'Instagram', ui: 'url', tipo: 'string', placeholder: 'https://www.instagram.com/…' },
      { chave: 'redes.linkedin', rotulo: 'LinkedIn', ui: 'url', tipo: 'string', placeholder: 'https://www.linkedin.com/company/…' },
      { chave: 'redes.facebook', rotulo: 'Facebook', ui: 'url', tipo: 'string', placeholder: 'https://www.facebook.com/…' },
      { chave: 'redes.youtube', rotulo: 'YouTube', ui: 'url', tipo: 'string', placeholder: 'https://www.youtube.com/@…' },
    ],
  },
  {
    id: 'seo', rotulo: 'SEO', icone: 'bi-search',
    campos: [
      { chave: 'seo.titulo_padrao', rotulo: 'Título padrão', ui: 'texto', tipo: 'string' },
      { chave: 'seo.sufixo_titulo', rotulo: 'Sufixo do título', ui: 'texto', tipo: 'string', max: 100, placeholder: ' | Silva & Souza', ajuda: 'Acrescentado ao título das páginas sem título de SEO próprio.' },
      { chave: 'seo.descricao_padrao', rotulo: 'Descrição padrão', ui: 'textarea', tipo: 'text', max: 160 },
      { chave: 'seo.palavras_chave', rotulo: 'Palavras-chave', ui: 'texto', tipo: 'string', ajuda: 'Separadas por vírgula.' },
      { chave: 'seo.og_image', rotulo: 'Imagem de compartilhamento', ui: 'imagem', tipo: 'imagem', ajuda: 'Usada quando a página é compartilhada em redes sociais.' },
    ],
  },
  {
    id: 'integracoes', rotulo: 'Integrações', icone: 'bi-plug',
    campos: [
      { chave: 'integracoes.ga_id', rotulo: 'ID do Google Analytics', ui: 'texto', tipo: 'string', placeholder: 'G-XXXXXXXXXX', ajuda: 'Formato G-XXXXXXX. Vazio desativa o rastreamento. Só grava cookies depois do aceite do visitante.' },
      { chave: 'integracoes.ads_id', rotulo: 'ID da conta do Google Ads', ui: 'texto', tipo: 'string', placeholder: 'AW-123456789', ajuda: 'Formato AW-123456789. Vazio desativa o rastreamento de conversões. Só grava cookies depois do aceite do visitante.' },
      { chave: 'integracoes.ads_conversao_formulario', rotulo: 'Conversão: formulário de contato', ui: 'texto', tipo: 'string', placeholder: 'AbCdEfGhIjKlMnOpQrSt', ajuda: 'Rótulo da conversão: a parte depois da barra em AW-123456789/RÓTULO. Conta quando o formulário é enviado com sucesso.' },
      { chave: 'integracoes.ads_conversao_whatsapp', rotulo: 'Conversão: clique no WhatsApp', ui: 'texto', tipo: 'string', placeholder: 'AbCdEfGhIjKlMnOpQrSt', ajuda: 'Rótulo da conversão: a parte depois da barra em AW-123456789/RÓTULO. Conta o clique em links do WhatsApp do site.' },
    ],
  },
  {
    id: 'lgpd', rotulo: 'Privacidade (LGPD)', icone: 'bi-shield-check',
    campos: [
      { chave: 'lgpd.encarregado_nome', rotulo: 'Encarregado (DPO)', ui: 'texto', tipo: 'string', placeholder: 'Nome completo', ajuda: 'Divulgado na Política de Privacidade (LGPD, art. 41, § 1º).' },
      { chave: 'lgpd.encarregado_email', rotulo: 'E-mail do encarregado', ui: 'email', tipo: 'string', placeholder: 'privacidade@escritorio.adv.br', ajuda: 'Canal para os titulares exercerem seus direitos.' },
      { chave: 'lgpd.politica_versao', rotulo: 'Versão da Política de Privacidade', ui: 'texto', tipo: 'string', max: 20, placeholder: 'AAAA-MM-DD', ajuda: 'Gravada com cada aceite do formulário de contato. Atualize sempre que mudar o texto da política.' },
    ],
  },
]

export const ABA_TEXTOS = { id: 'textos', rotulo: 'Textos do site', icone: 'bi-chat-left-text' }

/** Campos conhecidos por chave. */
export const CAMPOS_POR_CHAVE: Record<string, CampoConfiguracao> = Object.fromEntries(
  ABAS.flatMap((a) => a.campos).map((c) => [c.chave, c]),
)

export const CAMPOS_UNIDADE = ['titulo', 'linha1', 'linha2', 'resumo', 'rua', 'cidade', 'uf', 'cep'] as const
export type Unidade = Record<(typeof CAMPOS_UNIDADE)[number], string>

export const novaUnidade = (): Unidade =>
  Object.fromEntries(CAMPOS_UNIDADE.map((c) => [c, ''])) as Unidade

export type Valores = Record<string, any>

/** Todas as configurações do GET como `{ chave: valor }`. */
export function achatar(grupos: SiteConfiguracoes | null | undefined): Valores {
  const saida: Valores = {}
  for (const lista of Object.values(grupos ?? {})) {
    for (const item of lista) saida[item.chave] = item.valor
  }
  return saida
}

/** Valor normalizado de um campo para edição e comparação. */
export function normalizar(campo: CampoConfiguracao, valor: unknown): any {
  switch (campo.ui) {
    case 'imagem':
      return valor === null || valor === undefined || valor === '' ? null : Number(valor)
    case 'checkbox':
      return valor === true || valor === 1 || valor === '1' || valor === 'true'
    case 'enderecos':
      return (Array.isArray(valor) ? valor : [])
        .map((u) => Object.fromEntries(CAMPOS_UNIDADE.map((c) => [c, String(u?.[c] ?? '').trim()])) as Unidade)
        .filter((u) => Object.values(u).some((v) => v !== ''))
    default:
      return valor === null || valor === undefined ? '' : String(valor).trim()
  }
}

/** Estado de edição: um valor normalizado por campo conhecido e por texto do template. */
export function estadoInicial(grupos: SiteConfiguracoes | null | undefined, textos: Record<string, string> = {}): Valores {
  const atuais = achatar(grupos)
  const estado: Valores = {}
  for (const campo of Object.values(CAMPOS_POR_CHAVE)) {
    estado[campo.chave] = normalizar(campo, atuais[campo.chave])
  }
  for (const chave of Object.keys(textos)) {
    estado[`textos.${chave}`] = normalizar(CAMPO_TEXTO, atuais[`textos.${chave}`])
  }
  return estado
}

const CAMPO_TEXTO: CampoConfiguracao = { chave: '', rotulo: '', ui: 'texto', tipo: 'string' }

/**
 * Corpo do PUT: só o que mudou. Chaves que ainda não existem no banco levam o tipo
 * (`{ valor, tipo }`); vazio em `textos.*` volta ao texto padrão do template (o backend remove a linha).
 */
export function calcularAlteracoes(
  inicial: Valores,
  atual: Valores,
  existentes: Set<string>,
  textos: Record<string, string> = {},
): Record<string, unknown> {
  const corpo: Record<string, unknown> = {}
  for (const [chave, valor] of Object.entries(atual)) {
    if (JSON.stringify(valor) === JSON.stringify(inicial[chave])) continue

    const ehTexto = chave.startsWith('textos.')
    if (ehTexto && valor === '') {
      // sem linha no banco não há o que restaurar
      if (existentes.has(chave)) corpo[chave] = ''
      continue
    }

    const campo = ehTexto ? tipoDoTexto(chave, textos) : CAMPOS_POR_CHAVE[chave]
    if (!campo) continue
    corpo[chave] = existentes.has(chave) ? valor : { valor, tipo: campo.tipo }
  }
  return corpo
}

function tipoDoTexto(chave: string, textos: Record<string, string>): CampoConfiguracao {
  const padrao = textos[chave.slice('textos.'.length)] ?? ''
  return { ...CAMPO_TEXTO, chave, tipo: padrao.length > 200 ? 'text' : 'string' }
}

export function chavesExistentes(grupos: SiteConfiguracoes | null | undefined): Set<string> {
  return new Set(Object.keys(achatar(grupos)))
}

export const alterado = (inicial: Valores, atual: Valores): boolean =>
  Object.keys(calcularAlteracoes(inicial, atual, new Set(Object.keys(inicial)))).length > 0

/** Validação feita antes de enviar (a API valida de novo). */
export function validarLocal(chave: string, valor: unknown): string | null {
  const texto = typeof valor === 'string' ? valor : ''
  if (texto === '') return null

  if (chave === 'integracoes.ga_id' && !/^G-[A-Z0-9]{4,20}$/.test(texto)) return 'ID do Google Analytics inválido (formato G-XXXXXXX).'
  if (chave === 'integracoes.ads_id' && !/^AW-\d{6,15}$/.test(texto)) return 'ID do Google Ads inválido (formato AW-123456789).'
  if ((chave === 'integracoes.ads_conversao_formulario' || chave === 'integracoes.ads_conversao_whatsapp') && !/^[A-Za-z0-9_-]{10,40}$/.test(texto)) return 'Rótulo de conversão inválido (parte após a barra em AW-…/RÓTULO).'
  if ((chave === 'contato.email' || chave === 'lgpd.encarregado_email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(texto)) return 'E-mail inválido.'
  if (chave === 'lgpd.politica_versao' && texto.length > 20) return 'Máximo de 20 caracteres.'
  if (chave === 'seo.descricao_padrao' && texto.length > 160) return 'Máximo de 160 caracteres.'
  if (chave === 'seo.sufixo_titulo' && texto.length > 100) return 'Máximo de 100 caracteres.'
  if (chave.startsWith('redes.') && !/^https?:\/\/\S+$/i.test(texto)) return 'Informe a URL completa (http ou https).'
  if (chave === 'identidade.cor_documentos' && !/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(texto)) return 'Cor deve ser hexadecimal (#RGB ou #RRGGBB).'
  return null
}

/** Erros locais de todos os campos alterados, por chave. */
export function validarTodos(valores: Valores): Record<string, string> {
  const erros: Record<string, string> = {}
  for (const [chave, valor] of Object.entries(valores)) {
    const erro = validarLocal(chave, valor)
    if (erro) erros[chave] = erro
  }
  return erros
}

/** Aba que contém a primeira chave com erro (para abrir a aba certa). */
export function abaDoErro(erros: Record<string, string>): string | null {
  const chave = Object.keys(erros)[0]
  if (!chave) return null
  const grupo = chave.split('.')[0]
  return grupo === 'textos' ? ABA_TEXTOS.id : (ABAS.find((a) => a.id === grupo)?.id ?? null)
}

export interface PreviaGoogle {
  titulo: string
  url: string
  descricao: string
}

/** Como o resultado aparece no Google: título até 60 caracteres e descrição até 160. */
export function previaGoogle(valores: Valores): PreviaGoogle {
  const base = String(valores['seo.titulo_padrao'] || valores['identidade.nome'] || 'Título do site')
  const titulo = (base + String(valores['seo.sufixo_titulo'] ?? ''))
  const site = String(valores['contato.site'] || 'www.seusite.com.br').replace(/^https?:\/\//i, '').replace(/\/$/, '')
  return {
    titulo: titulo.length > 60 ? titulo.slice(0, 59).trimEnd() + '…' : titulo,
    url: `https://${site}`,
    descricao: String(valores['seo.descricao_padrao'] || 'Sem descrição padrão: o Google escolherá um trecho da página.').slice(0, 160),
  }
}

/** Agrupa as chaves de `textos` do template pelo prefixo (`contato.nome` → grupo `contato`). */
export function agruparTextos(textos: Record<string, string>): Array<{ grupo: string; itens: Array<{ chave: string; padrao: string }> }> {
  const grupos = new Map<string, Array<{ chave: string; padrao: string }>>()
  for (const [chave, padrao] of Object.entries(textos)) {
    const grupo = chave.includes('.') ? chave.split('.')[0] : 'geral'
    if (!grupos.has(grupo)) grupos.set(grupo, [])
    grupos.get(grupo)!.push({ chave, padrao })
  }
  return [...grupos.entries()].map(([grupo, itens]) => ({ grupo, itens }))
}
