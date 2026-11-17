import { onMounted, ref } from 'vue'
import { apiFetch } from '../services/api'
import type { Id } from '../types/domain'

export interface AdvogadoOpcao {
  id: Id
  username: string
}

/** Compartilhado entre as instâncias para não repetir a requisição. */
let cache: Promise<AdvogadoOpcao[]> | null = null

/**
 * Advogados ativos (GET acessos/advogados), para escolher responsável e
 * liberar acessos na privacidade granular.
 */
export function useAdvogados() {
  const advogados = ref<AdvogadoOpcao[]>([])

  onMounted(async () => {
    cache ??= apiFetch('acessos/advogados')
      .then(res => (res?.data || []) as AdvogadoOpcao[])
      .catch(() => {
        cache = null
        return []
      })
    advogados.value = await cache
  })

  return { advogados }
}
