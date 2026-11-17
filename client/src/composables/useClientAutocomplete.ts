import { ref, computed, nextTick, type Ref } from 'vue'
import { useSearchAutocomplete } from './useSearchAutocomplete'

export interface ClientOption {
  id: number | string
  /** Texto exibido na lista (nome + documento). */
  text: string
  /** Só o nome do cliente, sem o documento. */
  nome: string
}

/** Formulário que recebe o cliente selecionado. */
interface ClientForm {
  id_cliente: number | string | null
}

interface ClientSearchItem {
  id_cliente: number | string
  nome: string
  cpf_cnpj?: string | null
}

/**
 * Endpoint da busca:
 *  - "clientes" → GET clientes/buscar (exige o módulo Clientes e clientes.access);
 *  - "processos" → GET processos/clientes-busca (liberado a quem acessa Processos,
 *    para escolher o cliente de um processo sem acesso ao módulo Clientes).
 */
export type ClientEndpoint = 'clientes' | 'processos'

export interface ClientAutocompleteOptions {
  endpoint?: ClientEndpoint
  /**
   * Elemento que envolve o campo, para fechar a lista ao clicar fora. Sem ele,
   * usa o primeiro `.client-autocomplete-container` da página (comportamento
   * antigo, que só serve quando há um único campo na tela).
   */
  container?: Ref<HTMLElement | null>
  minChars?: number
  debounceMs?: number
}

/** "Nome - 123.456.789-09" (processos/clientes-busca) → "Nome". */
function semDocumento(texto: string): string {
  return texto.replace(/\s+-\s+[\d./-]+\s*$/, '').trim()
}

const ENDPOINTS: Record<ClientEndpoint, { url: (q: string) => string, extrair: (res: any) => ClientOption[] }> = {
  clientes: {
    url: q => 'clientes/buscar?term=' + encodeURIComponent(q),
    extrair: res => (res?.success && Array.isArray(res.data) ? res.data : []).map((c: ClientSearchItem) => ({
      id: c.id_cliente,
      text: c.nome + (c.cpf_cnpj ? ` (${c.cpf_cnpj})` : ''),
      nome: c.nome,
    })),
  },
  processos: {
    url: q => 'processos/clientes-busca?term=' + encodeURIComponent(q),
    extrair: res => (Array.isArray(res?.results) ? res.results : []).map((r: { id: number | string, text: string }) => ({
      id: r.id,
      text: r.text,
      nome: semDocumento(r.text),
    })),
  },
}

/**
 * Busca de cliente com autocompletar: debounce, descarte de respostas fora de
 * ordem, indicador de carregamento e navegação por teclado.
 *
 * @param initialForm Formulário que recebe `id_cliente` ao selecionar (opcional).
 */
export function useClientAutocomplete(initialForm?: ClientForm, options: ClientAutocompleteOptions = {}) {
  const fonte = ENDPOINTS[options.endpoint ?? 'clientes']

  const clientSearch = ref('')
  const activeIndex = ref(-1)

  const {
    results: clientResults,
    isLoading,
    showDropdown,
    onInput,
    clear,
  } = useSearchAutocomplete<ClientOption>({
    searchEndpoint: fonte.url,
    extractResults: fonte.extrair,
    // Sem cache: um cliente recém-cadastrado precisa aparecer na próxima busca.
    cache: false,
    minChars: options.minChars ?? 2,
    debounceMs: options.debounceMs ?? 350,
  })

  /** Lista aberta (mantido como ref gravável: as telas fazem `showClients = true` no foco). */
  const showClients = showDropdown

  /** Termo curto demais para buscar: a lista não deve aparecer. */
  const termoValido = computed(() => clientSearch.value.trim().length >= (options.minChars ?? 2))

  /** Busca pelo termo atual (aceita ser ligado direto em `@input`). */
  function onClientInput() {
    activeIndex.value = -1
    onInput(clientSearch.value)
  }

  function selectClient(item: ClientOption) {
    if (initialForm) {
      initialForm.id_cliente = item.id
    }
    clientSearch.value = item.text
    activeIndex.value = -1
    clear()
  }

  function clearClient() {
    if (initialForm) {
      initialForm.id_cliente = ''
    }
    clientSearch.value = ''
    activeIndex.value = -1
    clear()
  }

  /** Setas, Enter, Esc e Tab. Devolve o cliente escolhido com Enter, se houver. */
  function onClientKeydown(e: KeyboardEvent, idLista?: string): ClientOption | null {
    const total = clientResults.value.length
    if (e.key === 'Escape' || e.key === 'Tab') {
      activeIndex.value = -1
      clear()
      return null
    }
    if (!showClients.value || !total) return null

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      activeIndex.value = e.key === 'ArrowDown'
        ? (activeIndex.value + 1) % total
        : (activeIndex.value - 1 + total) % total
      if (idLista) {
        nextTick(() => document.getElementById(idLista + '-' + activeIndex.value)?.scrollIntoView({ block: 'nearest' }))
      }
      return null
    }
    if (e.key === 'Enter') {
      e.preventDefault()
      const item = clientResults.value[activeIndex.value >= 0 ? activeIndex.value : 0]
      return item ?? null
    }
    return null
  }

  function handleClickOutsideClient(e: MouseEvent) {
    const container = options.container?.value ?? document.querySelector('.client-autocomplete-container')
    if (container && !container.contains(e.target as Node)) {
      showClients.value = false
      activeIndex.value = -1
    }
  }

  return {
    clientSearch,
    clientResults,
    showClients,
    isLoading,
    activeIndex,
    termoValido,
    onClientInput,
    onClientKeydown,
    selectClient,
    clearClient,
    handleClickOutsideClient,
  }
}
