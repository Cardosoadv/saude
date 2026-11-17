import { cabecalhosDeTenant, ehCodigoTenantBloqueio, EVENTO_TENANT_ERRO, MENSAGENS_TENANT } from './tenant';

// Runtime config (config.js / PHP) → Build-time env → fallback
export const BASE_URL = window.__APP_CONFIG__?.apiUrl
  || import.meta.env.VITE_API_URL
  || 'http://localhost/processos/api/v2/';

export const SITE_BASE_URL = window.__APP_CONFIG__?.baseUrl
  || window.BASE_URL
  || 'http://localhost/processos/';

// Reusable formatters to avoid expensive Intl object instantiation in loops/re-renders
const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brlNumberFormatter = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFormatter = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit', month: '2-digit', year: 'numeric',
  hour: '2-digit', minute: '2-digit'
});

// Hoisted Regular Expressions to avoid redundant compilation
const CNJ_RE = /(\d{7})(\d{2})(\d{4})(\d{1})(\d{2})(\d{4})/;
const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const CPF_RE = /(\d{3})(\d{3})(\d{3})(\d{2})/;
const CNPJ_RE = /(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/;
const PHONE8_RE = /(\d{2})(\d{4})(\d{4})/;
const PHONE9_RE = /(\d{2})(\d{5})(\d{4})/;
const NON_DIGITS_RE = /\D/g;

// Cache for formatted results to further boost performance in long lists
// We use simple objects as caches for faster access and to avoid Map overhead in very tight loops
// In a real high-scale app, these could be LRU caches to prevent unbounded growth.
const dateCache: Record<string, string> = Object.create(null);
const currencyCache: Record<string, string> = Object.create(null);
const numberCache: Record<string, string> = Object.create(null);
const cnjCache: Record<string, string> = Object.create(null);
const docCache: Record<string, string> = Object.create(null);
const phoneCache: Record<string, string> = Object.create(null);

export function formatCNJ(valor: string | null | undefined): string {
  if (!valor) return '';
  if (cnjCache[valor]) return cnjCache[valor];

  const n = String(valor).replace(/\D/g, '');
  const result = n.length === 20 ? n.replace(CNJ_RE, "$1-$2.$3.$4.$5.$6") : valor;

  cnjCache[valor] = result;
  return result;
}

export function formatDate(ds: string | null | undefined, withTime = false): string {
  if (!ds || ds === '0000-00-00') return withTime ? '-' : '—';
  const cacheKey = `${ds}_${withTime}`;
  if (dateCache[cacheKey]) return dateCache[cacheKey];

  const normalized = (!withTime && ISO_DATE_RE.test(ds)) ? ds + 'T00:00:00' : ds;
  const d = new Date(normalized);
  if (isNaN(d.getTime())) return ds;

  const formatted = withTime ? dateTimeFormatter.format(d) : dateFormatter.format(d);
  const result = withTime ? formatted.replace(', ', ' ') : formatted;
  dateCache[cacheKey] = result;
  return result;
}

export function formatCurrency(v?: number | string | null): string {
  const val = v || 0;
  if (currencyCache[val]) return currencyCache[val];

  const result = currencyFormatter.format(Number(val));
  currencyCache[val] = result;
  return result;
}

export function formatNumberBr(num?: number | string | null): string {
  if (!num && num !== 0) return '';
  if (numberCache[num]) return numberCache[num];

  const result = brlNumberFormatter.format(Number(num));
  numberCache[num] = result;
  return result;
}

/**
 * Formats CPF (11 digits) or CNPJ (14 digits)
 */
export function formatDocument(doc: string | number | null | undefined, fallback = '—'): string {
  if (!doc) return fallback;
  if (docCache[doc]) return docCache[doc];

  const clean = String(doc).replace(NON_DIGITS_RE, '');
  let result = String(doc);

  if (clean.length === 11) {
    result = clean.replace(CPF_RE, '$1.$2.$3-$4');
  } else if (clean.length === 14) {
    result = clean.replace(CNPJ_RE, '$1.$2.$3/$4-$5');
  }

  // Only cache final formats, not intermediate typing
  if (clean.length === 11 || clean.length === 14) {
    docCache[doc] = result;
  }
  return result;
}

/**
 * Formats Phone (10 digits) or Mobile (11 digits)
 */
export function formatPhone(phone: string | number | null | undefined, fallback = '—'): string {
  if (!phone) return fallback;
  if (phoneCache[phone]) return phoneCache[phone];

  const clean = String(phone).replace(NON_DIGITS_RE, '');
  let result = String(phone);

  if (clean.length === 10) {
    result = clean.replace(PHONE8_RE, '($1) $2-$3');
  } else if (clean.length === 11) {
    result = clean.replace(PHONE9_RE, '($1) $2-$3');
  }

  // Only cache final formats
  if (clean.length === 10 || clean.length === 11) {
    phoneCache[phone] = result;
  }
  return result;
}

export function truncate(text: string | null | undefined, max = 80): string {
  if (!text) return '';
  return text.length > max ? text.substring(0, max) + '...' : text;
}

const RISCO_COLORS: Record<string, string> = {
  'Provável': '#dc2626',
  'Possível': '#d97706',
  'Remoto':   '#16a34a'
};

export function riscoColor(risco: string | null | undefined): string {
  return RISCO_COLORS[risco as string] || '#6b7280';
}

export function debounce<A extends unknown[]>(fn: (...args: A) => void, delay: number): (...args: A) => void {
  let t: ReturnType<typeof setTimeout> | undefined;
  return (...args: A) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

export interface ApiFetchOptions extends Omit<RequestInit, 'body' | 'headers'> {
  headers?: Record<string, string>;
  /** Objetos simples são serializados como JSON; FormData e strings passam direto. */
  body?: BodyInit | object | null;
  /** Tempo máximo da requisição em ms (padrão 30000). */
  timeout?: number;
}

/** Erro lançado por apiFetch (timeout ou resposta HTTP não-2xx). */
export class ApiError extends Error {
  status: number;
  /** Corpo JSON da resposta de erro, quando houver. */
  data?: any;

  constructor(message: string, status: number, data?: any) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }

  /** Código `tenant_*` quando a API bloqueia o acesso por causa do escritório (inativo, não encontrado...). */
  get tenantBloqueio() {
    return ehCodigoTenantBloqueio(this.data?.code) ? this.data.code : undefined;
  }

  /** Slug do módulo quando o 403 é de módulo desativado (filtro `modulo:<slug>` da API). */
  get moduloInativo(): string | undefined {
    return this.status === 403 && this.data?.code === 'modulo_inativo'
      ? (this.data.modulo ?? '')
      : undefined;
  }
}

/** Evento disparado em `window` quando a API acusa módulo desativado. */
export const EVENTO_MODULO_INATIVO = 'modulo-inativo';

export const MENSAGEM_MODULO_INATIVO = 'Este módulo foi desativado pelo administrador.';

let cachedCsrfToken: string | null = null;

/**
 * Faz uma requisição à API. O tipo de retorno é `any` por padrão; informe `T`
 * para tipar a resposta: `apiFetch<Processo[]>('processos')`.
 */
export async function apiFetch<T = any>(endpoint: string, opts: ApiFetchOptions = {}): Promise<T> {
  const url = endpoint.startsWith('http') ? endpoint : BASE_URL + endpoint;
  const headers: Record<string, string> = { 
    'X-Requested-With': 'XMLHttpRequest', 
    'Accept': 'application/json',
    ...cabecalhosDeTenant(),
    ...opts.headers 
  };
  const method = (opts.method || 'GET').toUpperCase();

  if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
    if (cachedCsrfToken === null) {
      cachedCsrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    }
    if (cachedCsrfToken) {
      headers['X-CSRF-TOKEN'] = cachedCsrfToken;
    }
  }

  if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.body);
  }

  const timeoutSignal = AbortSignal.timeout(opts.timeout ?? 30000);
  const signal = opts.signal
    ? (AbortSignal.any ? AbortSignal.any([opts.signal, timeoutSignal]) : opts.signal)
    : timeoutSignal;

  let res: Response;
  try {
    res = await fetch(url, {
      ...opts,
      body: opts.body as BodyInit | null | undefined,
      headers,
      credentials: 'include',
      redirect: 'follow',
      signal
    });
  } catch (err) {
    if (err.name === 'TimeoutError' || err.name === 'AbortError') {
      throw new ApiError('Tempo de conexão esgotado. Verifique sua conexão e tente novamente.', 0);
    }
    // Sem rede (aparelho offline): troca o "Failed to fetch" do navegador por uma mensagem clara.
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      throw new ApiError('Sem conexão com a internet. Verifique sua rede e tente novamente.', 0);
    }
    throw err;
  }

  // Tratar erros HTTP (401, 403, 422, 500, etc.)
  if (!res.ok) {
    // Tentar extrair mensagem de erro do JSON de resposta
    const contentType = res.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      const errorData = await res.json();
      const erro = new ApiError(
        errorData.messages?.error
        || errorData.error
        || errorData.message
        || `HTTP ${res.status}`,
        res.status,
        errorData
      );
      if (erro.moduloInativo !== undefined) {
        erro.message = MENSAGEM_MODULO_INATIVO;
        // Módulo desligado com a sessão aberta: a SPA recarrega o acesso.
        if (typeof window.dispatchEvent === 'function') {
          window.dispatchEvent(new CustomEvent(EVENTO_MODULO_INATIVO, { detail: { modulo: erro.moduloInativo } }));
        }
      }
      const bloqueio = erro.tenantBloqueio;
      if (bloqueio !== undefined) {
        erro.message = errorData.message || MENSAGENS_TENANT[bloqueio].texto;
        // A SPA troca a tela por um aviso dedicado: não adianta tentar de novo neste endereço.
        if (typeof window.dispatchEvent === 'function' && typeof CustomEvent !== 'undefined') {
          window.dispatchEvent(new CustomEvent(EVENTO_TENANT_ERRO, { detail: { codigo: bloqueio, mensagem: erro.message } }));
        }
      }
      throw erro;
    }
    throw new ApiError(`HTTP ${res.status}`, res.status);
  }

  // Parsear resposta
  const contentType = res.headers.get('content-type');
  if (contentType && contentType.includes('application/json')) {
    return res.json();
  } else {
    // Se não é JSON (ex: redirect para HTML), marcar como redirect
    return { success: true, redirected: res.redirected, url: res.url, _isHtml: true } as T;
  }
}

/**
 * Baixa um arquivo da API (CSV, PDF...) e o entrega ao navegador como download.
 * Usa o mesmo BASE_URL e os cookies do apiFetch; se a API responder erro, lança ApiError com a mensagem dela.
 */
export async function baixarArquivo(
  endpoint: string,
  opts: { method?: 'GET' | 'POST'; body?: FormData; nomePadrao?: string } = {},
): Promise<void> {
  const res = await fetch(BASE_URL + endpoint, {
    method: opts.method ?? 'GET',
    body: opts.body,
    credentials: 'include',
    headers: { 'X-Requested-With': 'XMLHttpRequest', ...cabecalhosDeTenant() },
    signal: AbortSignal.timeout(60000),
  });

  const tipo = res.headers.get('content-type') || '';
  if (!res.ok || tipo.includes('application/json')) {
    let mensagem = `HTTP ${res.status}`;
    try { mensagem = (await res.json()).message || mensagem; } catch { /* corpo não JSON */ }
    throw new ApiError(mensagem, res.status);
  }

  const disposicao = res.headers.get('content-disposition') || '';
  const nome = /filename="?([^";]+)"?/i.exec(disposicao)?.[1] || opts.nomePadrao || 'arquivo';
  const url = URL.createObjectURL(await res.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = nome;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

type FormDataValue = string | number | boolean | Blob;

export function toFormData(obj: Record<string, FormDataValue | FormDataValue[] | null | undefined>): FormData {
  const fd = new FormData();
  for (const [key, val] of Object.entries(obj)) {
    if (val !== undefined && val !== null) {
      if (Array.isArray(val)) {
        val.forEach(v => fd.append(`${key}[]`, v as string | Blob));
      } else {
        fd.append(key, val as string | Blob);
      }
    }
  }
  return fd;
}

// ─── Mock data for standalone demo ───────────────────────────────────────────
export function generateMockProcessos(count = 20) {
  const tribunais = ['TJMG', 'TJSP', 'TRF1', 'STJ', 'TJDF'];
  const riscos = ['Provável', 'Possível', 'Remoto'];
  const etiquetasPool = [
    { id: 4, nome: 'Civil', cor: '2F2AC3' },
    { id: 7, nome: 'Trabalho', cor: '4DBFC3' },
    { id: 5, nome: 'Criminal', cor: 'C34D30' },
    { id: 12, nome: 'Usucapião', cor: 'A349A4' },
    { id: 13, nome: 'Execução Fiscal', cor: 'EC1C23' },
    { id: 14, nome: 'Cumpri. de Sentença', cor: 'C3B716' },
  ];
  const result = [];
  for (let i = 0; i < count; i++) {
    const id = 1400 + i;
    const digits = String(Math.floor(Math.random() * 1e20)).padStart(20, '0');
    result.push({
      id_processo: id,
      numero_processo: digits,
      titulo_processo: `Processo ${['Usucapião', 'Execução Fiscal', 'Ação de Cobrança', 'Cumprimento de Sentença', 'Ação Rescisória'][i % 5]} nº ${id}`,
      cliente_nome: ['João da Silva', 'Maria Oliveira', 'Pedro Santos', 'Ana Costa', 'Carlos Pereira'][i % 5],
      siglaTribunal: tribunais[i % tribunais.length],
      risco: riscos[i % riscos.length],
      encerrado: i % 7 === 0 ? 1 : 0,
      arquived_at: i % 11 === 0 ? '2024-01-01' : null,
      dataDistribuicao: `202${3 - (i % 3)}-0${(i % 9) + 1}-15`,
      created_at: `202${3 - (i % 3)}-0${(i % 9) + 1}-15`,
      etiquetas: etiquetasPool.filter((_, idx) => idx % (i % 3 + 1) === 0).slice(0, 2),
    });
  }
  return result;
}

export function generateMockFornecedores(count = 15) {
  const categorias = ['Tecnologia', 'Papelaria', 'Serviços Gerais', 'Consultoria', 'Marketing'];
  const result = [];
  for (let i = 0; i < count; i++) {
    const id = 500 + i;
    result.push({
      id_fornecedor: id,
      nome: ['Dell Technologies', 'Kalunga S.A.', 'Limpeza Express', 'Consultoria Jurídica X', 'Agência Digital Y'][i % 5],
      razao_social: ['Dell Computadores do Brasil Ltda', 'Kalunga Comércio e Indústria Gráfica S.A.', 'Limpeza e Conservação Eireli', 'X Consultores Associados', 'Y Marketing Digital Ltda'][i % 5],
      documento: String(Math.floor(Math.random() * 1e14)).padStart(14, '0'),
      email: `contato@${['dell', 'kalunga', 'limpeza', 'consultoria', 'agencia'][i % 5]}.com.br`,
      telefone: `(11) 9${Math.floor(Math.random() * 90000000 + 10000000)}`,
      categoria: categorias[i % categorias.length],
      ativo: i % 10 === 0 ? 0 : 1,
      created_at: `2024-02-${(i % 28) + 1}`,
    });
  }
  return result;
}

export function generateMockSupplierDetail(id: number | string) {
  return {
    id_fornecedor: id,
    nome: 'Dell Technologies',
    razao_social: 'Dell Computadores do Brasil Ltda',
    documento: '12.345.678/0001-90',
    tipo_pessoa: 'J',
    email: 'contato@techsupplies.com.br',
    telefone: '(11) 98888-7777',
    cep: '04578-000',
    endereco: 'Avenida das Nações Unidas',
    numero: '12901',
    complemento: 'Torre Oeste',
    cidade: 'São Paulo',
    uf: 'SP',
    categoria: 'Tecnologia',
    banco: 'Itaú',
    agencia: '0001',
    conta: '12345-6',
    pix: 'financeiro@dell.com.br',
    ativo: 1,
    created_at: '2024-01-15'
  };
}

export function generateMockCasos(count = 10) {
  const statuses = ['aberto', 'em_andamento', 'concluido', 'arquivado'];
  const result = [];
  for (let i = 0; i < count; i++) {
    const id = 300 + i;
    result.push({
      id_caso: id,
      titulo: `Caso Extrajudicial: ${['Contrato Social', 'Parecer Tributário', 'Acordo Extrajudicial', 'Planejamento Sucessório'][i % 4]} nº ${id}`,
      cliente_nome: ['João da Silva', 'Maria Oliveira', 'Pedro Santos', 'Ana Costa', 'Carlos Pereira'][i % 5],
      status: statuses[i % statuses.length],
      valor: 1500 + (i * 250),
      created_at: `2024-03-${(i % 28) + 1}`,
    });
  }
  return result;
}

export function generateMockCasoDetail(id: number | string) {
  return {
    id_caso: id,
    titulo: 'Elaboração de Contrato Social - Empresa XPTO',
    id_cliente: 1,
    cliente_nome: 'João da Silva',
    status: 'em_andamento',
    valor: 5000,
    descricao: 'Caso para elaboração e registro de contrato social para holding familiar.',
    created_at: '2024-03-10'
  };
}

export function generateMockContratos(count = 10) {
  const result = [];
  const statuses = ['ativo', 'vencendo', 'encerrado'];
  for (let i = 0; i < count; i++) {
    const id = 100 + i;
    result.push({
      id_contrato: id,
      numero: `2024/${String(i + 1).padStart(3, '0')}`,
      titulo: `Honorários - ${['Consultoria Mensal', 'Parecer Técnico', 'Defesa Judicial', 'Assessoria Especial'][i % 4]}`,
      cliente_nome: ['João da Silva', 'Maria Oliveira', 'Pedro Santos', 'Ana Costa', 'Carlos Pereira'][i % 5],
      status: statuses[i % statuses.length],
      valor: 2500 + (i * 500),
      data_inicio: '2024-01-10',
      created_at: '2024-01-10'
    });
  }
  return result;
}

export function generateMockContratoDetail(id: number | string) {
  return {
    id_contrato: id,
    numero: '2024/001',
    numero_contrato: '2024/001',
    titulo: 'Honorários - Consultoria Mensal',
    id_cliente: 1,
    cliente_nome: 'João da Silva',
    tipo_contrato: 'Consultoria',
    objeto: 'Prestação de serviços de consultoria jurídica mensal em matéria civil e empresarial.',
    status: 'Ativo',
    data_inicio: '2024-01-10',
    data_fim: '2025-01-10',
    prazo_vigencia: 12,
    prazo_tipo: 'meses',
    valor: 5000,
    honorarios_exito: 10,
    forma_pagamento: 'Mensal',
    dia_vencimento: 10,
    renovacao_automatica: 'Não',
    prazo_aviso: 30,
    comentarios: 'Contrato de consultoria regular.',
    responsavel_interno: 'Carlos Advogado',
    area_atuacao: 'Empresarial',
    numero_parcelas: 12,
    indice_reajuste: 'IGP-M',
    pasta: 'https://drive.google.com/drive/folders/12345',
    clausulas_especiais: '',
    created_at: '2024-01-10T10:00:00',
    updated_at: '2024-01-15T14:30:00',
    processos: [
      { id_processo: 1, numero_processo: '10803668920268130024', titulo_processo: 'Ação de Cobrança' }
    ],
    casos: [
      { id_caso: 300, titulo: 'Elaboração de Contrato Social' }
    ],
    receitas: [
      { id_receita: 10, receita: 'Mensalidade Janeiro', valor: 5000, vencimento_dt: '2024-01-10', status: 'Pago' },
      { id_receita: 11, receita: 'Mensalidade Fevereiro', valor: 5000, vencimento_dt: '2024-02-10', status: 'Pendente' }
    ],
    despesas: []
  };
}

export function generateMockDetail(id: number | string) {
  return {
    processo: {
      id_processo: id,
      responsavel_id: null,
      numero_processo: '10803668920268130024',
      titulo_processo: 'Cumprimento de Sentença - Lote Porto Seguro',
      nomeOrgao: '8ª Vara Cível de Belo Horizonte',
      tipoDocumento: 'CUMPRIMENTO DE SENTENÇA',
      instancia: '1ª Instância',
      classe: 'CUMPRIMENTO DE SENTENÇA',
      encerrado: 0,
      risco: 'Possível',
      valorCausa: 25000,
      valorCondenacao: 18500,
      dataRevisao: '2026-03-10',
      created_at: '2022-05-15',
      updated_at: '2026-03-10',
      ramo_direito_nome: 'Direito Civil',
    },
    partes: [
      { id: 1, nome: 'João da Silva Santos', polo: 'A' },
      { id: 2, nome: 'Prefeitura Municipal de Belo Horizonte', polo: 'P' },
      { id: 3, nome: 'Secretaria Municipal de Habitação', polo: 'P' },
    ],
    etiquetas: [
      { id: 4, nome: 'Civil', cor: '2F2AC3' },
      { id: 14, nome: 'Cumpri. de Sentença', cor: 'C3B716' },
      { id: 26, nome: 'Porto Seguro', cor: 'EEE3AF' },
    ],
    movimentacoes: [
      { id: 1, nome: 'Despacho: Cumprimento iniciado', dataHora: '2026-03-10 09:30', observacao: '' },
      { id: 2, nome: 'Juntada de documentos comprobatórios', dataHora: '2026-02-14 14:15', observacao: 'Planilha de cálculo atualizada' },
      { id: 3, nome: 'Penhora de bens deferida', dataHora: '2026-01-22 10:00', observacao: '' },
      { id: 4, nome: 'Impugnação ao cumprimento recebida', dataHora: '2025-11-05 11:20', observacao: 'Prazo de 15 dias para manifestação' },
    ],
    intimacoes: [
      { id: 1, tipoComunicacao: 'Intimação para pagamento', data_disponibilizacao: '2026-03-10', numero_processo: '10803668920268130024' },
    ],
    tarefas: [
      { id: 1, tarefa: 'Preparar planilha de cálculo atualizada', prazo: '2026-05-30', status: 2 },
      { id: 2, tarefa: 'Protocolar manifestação sobre impugnação', prazo: '2026-05-25', status: 3 },
    ],
    anotacoes: [
      { id: 1, titulo: 'Observação sobre penhora', anotacao: 'Verificar se a penhora online foi efetivada no sistema do Bacen.', nivel_privacidade: 1 },
    ],
  };
}

