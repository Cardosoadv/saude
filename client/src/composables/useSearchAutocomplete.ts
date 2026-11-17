import { ref, onScopeDispose } from 'vue'
import { apiFetch } from '../services/api'

export interface SearchAutocompleteOptions<T = any> {
  /** Endpoint da API ou função que recebe o termo e devolve o endpoint completo. */
  searchEndpoint: string | ((query: string) => string)
  /** Mínimo de caracteres para disparar a busca. */
  minChars?: number
  /** Tempo de debounce em milissegundos. */
  debounceMs?: number
  /** Processa/filtra os resultados antes de exibi-los. */
  processResults?: (results: T[]) => T[]
  /**
   * Extrai a lista da resposta da API. Padrão: `res.results`. Use para
   * endpoints que devolvem outro formato (ex.: `{ success, data: [...] }`).
   */
  extractResults?: (res: any) => T[]
  /** Guarda as respostas em cache entre as telas (padrão: true). */
  cache?: boolean
}

// Module-level cache to share results across components and prevent redundant API hits
const searchCache = new Map<string, any>()
const MAX_CACHE_SIZE = 100

/**
 * Composable for handling search autocomplete logic with debouncing
 */
export function useSearchAutocomplete<T = any>(options: SearchAutocompleteOptions<T>) {
  const {
    searchEndpoint,
    minChars = 2,
    debounceMs = 300,
    processResults = (results: T[]) => results,
    extractResults = (res: any) => (res && Array.isArray(res.results) ? res.results : []),
    cache = true
  } = options

  const results = ref<T[]>([])
  const isLoading = ref(false)
  const showDropdown = ref(false)
  let searchTimeout: ReturnType<typeof setTimeout> | null = null
  let currentRequestId = 0

  /**
   * Main search handler to be called on input
   */
  const onInput = (query: string | null | undefined) => {
    if (searchTimeout) clearTimeout(searchTimeout)

    if (!query || query.trim().length < minChars) {
      results.value = []
      showDropdown.value = false
      return
    }

    isLoading.value = true
    showDropdown.value = true

    searchTimeout = setTimeout(async () => {
      const requestId = ++currentRequestId
      try {
        const url = typeof searchEndpoint === 'function'
          ? searchEndpoint(query)
          : `${searchEndpoint}?term=${encodeURIComponent(query)}`

        // Check cache first
        let res = cache ? searchCache.get(url) : undefined

        if (!res) {
          res = await apiFetch(url)

          // Store in cache if successful
          if (cache && res && res.success) {
            if (searchCache.size >= MAX_CACHE_SIZE) {
              const firstKey = searchCache.keys().next().value as string
              searchCache.delete(firstKey)
            }
            searchCache.set(url, res)
          }
        }

        if (requestId !== currentRequestId) return

        results.value = processResults(extractResults(res) || [])
      } catch (err) {
        if (requestId !== currentRequestId) return
        console.error('Search error:', err)
        results.value = []
      } finally {
        if (requestId === currentRequestId) {
          isLoading.value = false
        }
      }
    }, debounceMs)
  }

  const clear = () => {
    results.value = []
    showDropdown.value = false
    currentRequestId++
    if (searchTimeout) clearTimeout(searchTimeout)
  }

  onScopeDispose(() => {
    if (searchTimeout) clearTimeout(searchTimeout)
  })

  return {
    results,
    isLoading,
    showDropdown,
    onInput,
    clear
  }
}
