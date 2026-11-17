
// Runtime config (config.js / PHP) → Build-time env → fallback
export const BASE_URL = window.__APP_CONFIG__?.apiUrl
  || import.meta.env.VITE_API_URL
  || 'http://localhost/saude/api/v2/';

export const SITE_BASE_URL = window.__APP_CONFIG__?.baseUrl
  || window.BASE_URL
  || 'http://localhost/saude/';

// Reusable formatters to avoid expensive Intl object instantiation in loops/re-renders
const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brlNumberFormatter = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFormatter = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit', month: '2-digit', year: 'numeric',
  hour: '2-digit', minute: '2-digit'
});

// Hoisted Regular Expressions to avoid redundant compilation
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
const docCache: Record<string, string> = Object.create(null);
const phoneCache: Record<string, string> = Object.create(null);

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
 * para tipar a resposta: `apiFetch<Cliente[]>('clientes')`.
 */
export async function apiFetch<T = any>(endpoint: string, opts: ApiFetchOptions = {}): Promise<T> {
  const url = endpoint.startsWith('http') ? endpoint : BASE_URL + endpoint;
  const headers: Record<string, string> = { 
    'X-Requested-With': 'XMLHttpRequest', 
    'Accept': 'application/json',
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
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
