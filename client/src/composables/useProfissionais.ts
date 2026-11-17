import { onMounted, ref } from 'vue'
import { apiFetch } from '../services/api'
import type { Id } from '../types/domain'

export interface ProfissionalOpcao {
  id: Id
  username: string
}

/** Compartilhado entre as instâncias para não repetir a requisição. */
let cache: Promise<ProfissionalOpcao[]> | null = null

/**
 * Profissionais ativos (GET acessos/profissionais), para escolher responsável e
 * liberar acessos na privacidade granular.
 */
export function useProfissionais() {
  const profissionais = ref<ProfissionalOpcao[]>([])

  onMounted(async () => {
    cache ??= apiFetch('acessos/profissionais')
      .then(res => (res?.data || []) as ProfissionalOpcao[])
      .catch(() => {
        cache = null
        return []
      })
    profissionais.value = await cache
  })

  return { profissionais }
}
