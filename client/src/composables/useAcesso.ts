/**
 * Acesso do usuário logado: permissões efetivas e módulos ativos, vindos de
 * `user/info`. Estado único compartilhado por toda a SPA.
 *
 * A palavra final é sempre do backend (filtro `modulo:<slug>` e permissões
 * nas rotas); aqui só se decide o que exibir.
 *
 * Backend antigo, sem `permissoes`/`modulos_ativos` no `user/info`: o campo
 * ausente vira `null` e libera tudo, mantendo o comportamento anterior.
 */
import { reactive, readonly } from 'vue'
import { getCurrentUser, type AuthUser } from '../services/auth'

interface EstadoAcesso {
  /** null = backend não informou: tudo liberado. */
  permissoes: string[] | null
  modulosAtivos: string[] | null
}

const estado = reactive<EstadoAcesso>({
  permissoes: null,
  modulosAtivos: null,
})

function normalizar(lista: unknown): string[] | null {
  return Array.isArray(lista) ? lista.map((item) => String(item).toLowerCase()) : null
}

/** Aplica os dados de acesso de um usuário vindo de `user/info`. */
export function aplicarAcesso(user: AuthUser | null | undefined): void {
  estado.permissoes = normalizar(user?.permissoes)
  estado.modulosAtivos = normalizar(user?.modulos_ativos)
}

export function limparAcesso(): void {
  aplicarAcesso(null)
}

/** Verdadeiro se o usuário tem ao menos uma das permissões. */
export function pode(...permissoes: string[]): boolean {
  const concedidas = estado.permissoes
  return concedidas === null || permissoes.some((p) => concedidas.includes(p.toLowerCase()))
}

export function moduloAtivo(slug: string): boolean {
  return estado.modulosAtivos === null || estado.modulosAtivos.includes(slug.toLowerCase())
}

/**
 * Módulo ativo e ao menos uma das permissões (sem permissões, basta o
 * módulo). Mesma regra do VisibilidadeModulosService no backend.
 */
export function liberado(slug: string, ...permissoes: string[]): boolean {
  return moduloAtivo(slug) && (permissoes.length === 0 || pode(...permissoes))
}

/** Busca `user/info` de novo (ex.: após ligar/desligar um módulo). */
export async function recarregarAcesso(): Promise<void> {
  aplicarAcesso(await getCurrentUser())
}

export function useAcesso() {
  return {
    estado: readonly(estado),
    pode,
    moduloAtivo,
    liberado,
    aplicarAcesso,
    limparAcesso,
    recarregarAcesso,
  }
}
