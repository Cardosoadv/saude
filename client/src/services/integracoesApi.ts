import { apiFetch, ApiError } from './api';

/**
 * Credenciais e parâmetros de integração (IA, API do CNJ, APIBrasil, Google) do escritório, uma sub-guia por grupo.
 * Antes viviam no .env do servidor; agora cada escritório as configura aqui (GET/POST api/v2/configuracoes/integracoes).
 */

export type TipoCampoIntegracao = 'texto' | 'url' | 'segredo' | 'opcao';

/** De onde vem o valor efetivo: `banco` (deste escritório), `env` (servidor, só no escritório padrão) ou `vazio`. */
export type OrigemIntegracao = 'banco' | 'env' | 'vazio';

export interface CampoIntegracao {
  chave: string;
  rotulo: string;
  tipo: TipoCampoIntegracao;
  descricao: string;
  /** valor => rótulo, só para `opcao`. */
  opcoes?: Record<string, string>;
  /** Sempre `null` para segredos: o valor nunca volta do servidor. */
  valor: string | null;
  tem_valor: boolean;
  origem: OrigemIntegracao;
}

export interface SecaoIntegracao {
  titulo: string;
  campos: CampoIntegracao[];
}

export interface GrupoIntegracao {
  slug: string;
  titulo: string;
  descricao: string;
  icone: string;
  secoes: SecaoIntegracao[];
}

export interface CatalogoIntegracoes {
  grupos: GrupoIntegracao[];
  /** `false` quando o servidor não tem `encryption.key`: os segredos ficam gravados em texto puro. */
  criptografia: boolean;
}

export interface ResultadoSalvar {
  alteradas: string[];
}

/** Erro de validação (422) com a mensagem de cada chave. */
export class ErroValidacaoIntegracao extends Error {
  erros: Record<string, string>;

  constructor(mensagem: string, erros: Record<string, string>) {
    super(mensagem);
    this.name = 'ErroValidacaoIntegracao';
    this.erros = erros;
  }
}

export async function listarIntegracoes(): Promise<CatalogoIntegracoes> {
  const res = await apiFetch<{ success: boolean; message?: string; data?: CatalogoIntegracoes }>('configuracoes/integracoes');
  if (!res?.success || !res.data) {
    throw new Error(res?.message || 'Não foi possível carregar as integrações.');
  }

  return res.data;
}

/**
 * Grava só o que mudou. `limpar` apaga segredos de propósito (segredo em branco significa "manter o atual").
 * Lança `ErroValidacaoIntegracao` quando o servidor recusa algum campo (nada é gravado nesse caso).
 */
export async function salvarIntegracoes(valores: Record<string, string>, limpar: string[] = []): Promise<ResultadoSalvar> {
  try {
    const res = await apiFetch<{ success: boolean; message?: string; alteradas?: string[] }>('configuracoes/integracoes', {
      method: 'POST',
      body: { valores, limpar },
    });
    if (!res?.success) {
      throw new Error(res?.message || 'Não foi possível salvar as integrações.');
    }

    return { alteradas: res.alteradas ?? [] };
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 422 && erro.data?.errors) {
      throw new ErroValidacaoIntegracao(erro.message, erro.data.errors as Record<string, string>);
    }
    throw erro;
  }
}
