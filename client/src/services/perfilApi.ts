/**
 * @version 1.0.0
 * Perfil do usuário logado, troca de senha e recuperação de senha.
 */
import { apiFetch } from './api'

export interface PerfilUsuario {
  id: number
  username: string
  email: string
  is_lawyer?: number | string
  oab_numero?: string | null
  oab_uf?: string | null
  exibir_site?: number | string
  responsavel_tarefas?: number | string
  cargo?: string | null
  formacao?: string | null
  especializacoes?: string | null
  areas_atuacao?: string | null
  curriculo?: string | null
  lattes_url?: string | null
  linkedin_url?: string | null
  auth_image?: string | null
}

export interface RespostaApi<T = unknown> {
  success?: boolean
  status?: string
  message?: string
  errors?: Record<string, string>
  data?: T
}

export const perfilApi = {
  obter: () => apiFetch<RespostaApi<PerfilUsuario>>('perfil'),

  /** Envia como multipart para aceitar a foto (`foto-perfil`). */
  atualizar: (formData: FormData) =>
    apiFetch<RespostaApi>('perfil/atualizar', { method: 'POST', body: formData }),

  alterarSenha: (dados: { senha_atual: string; nova_senha: string; pass_confirm: string }) =>
    apiFetch<RespostaApi>('perfil/senha', { method: 'POST', body: dados }),

  esqueciSenha: (email: string) =>
    apiFetch<RespostaApi>('esqueci-senha', { method: 'POST', body: { email } }),

  redefinirSenha: (dados: { email: string; token: string; password: string; pass_confirm: string }) =>
    apiFetch<RespostaApi>('redefinir-senha', { method: 'POST', body: dados }),
}
