import type { CampoIntegracao, GrupoIntegracao } from '../services/integracoesApi';

/** Todos os campos de um grupo, na ordem da tela. */
export function camposDoGrupo(grupo: GrupoIntegracao): CampoIntegracao[] {
  return grupo.secoes.flatMap((secao) => secao.campos);
}

/** Valor inicial do formulário: segredos começam vazios (o servidor nunca os devolve). */
export function valoresIniciais(grupo: GrupoIntegracao): Record<string, string> {
  const valores: Record<string, string> = {};
  for (const campo of camposDoGrupo(grupo)) {
    valores[campo.chave] = campo.tipo === 'segredo' ? '' : (campo.valor ?? '');
  }

  return valores;
}

/**
 * O que enviar ao servidor: só o que mudou.
 * - campo comum: enviado se for diferente do valor atual (inclusive vazio, para limpar);
 * - segredo: enviado só se foi digitado algo (em branco mantém o atual); apagar é pedido à parte, em `limpar`.
 */
export function montarEnvio(
  grupo: GrupoIntegracao,
  editados: Record<string, string>,
  paraApagar: ReadonlySet<string>,
): { valores: Record<string, string>; limpar: string[] } {
  const valores: Record<string, string> = {};
  const limpar: string[] = [];

  for (const campo of camposDoGrupo(grupo)) {
    const novo = (editados[campo.chave] ?? '').trim();

    if (campo.tipo === 'segredo') {
      if (paraApagar.has(campo.chave)) {
        limpar.push(campo.chave);
      } else if (novo !== '') {
        valores[campo.chave] = novo;
      }
      continue;
    }

    if (novo !== (campo.valor ?? '')) {
      valores[campo.chave] = novo;
    }
  }

  return { valores, limpar };
}

export function haAlteracoes(envio: { valores: Record<string, string>; limpar: string[] }): boolean {
  return Object.keys(envio.valores).length > 0 || envio.limpar.length > 0;
}

/** Situação de um campo para o selo ao lado do rótulo. */
export function situacaoDoCampo(campo: CampoIntegracao): { texto: string; tom: 'ok' | 'aviso' | 'neutro' } {
  if (!campo.tem_valor) {
    return { texto: 'Não configurado', tom: 'neutro' };
  }

  return campo.origem === 'env'
    ? { texto: 'Valor do servidor (.env)', tom: 'aviso' }
    : { texto: 'Configurado', tom: 'ok' };
}
