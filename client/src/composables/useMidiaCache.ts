/**
 * Cache em memória das mídias já vistas (por id), para o seletor mostrar a miniatura da imagem
 * escolhida sem buscar de novo a cada campo. Mídias enviadas ou alteradas na biblioteca entram aqui.
 */
import { obterMidia } from '../services/siteApi'
import type { SiteMidia } from '../types/site'

const cache = new Map<number, SiteMidia>()
const pendentes = new Map<number, Promise<SiteMidia | null>>()

export function registrarMidia(midia: SiteMidia): void {
  cache.set(midia.id, midia)
}

export function esquecerMidia(id: number): void {
  cache.delete(id)
}

export function limparCacheDeMidias(): void {
  cache.clear()
  pendentes.clear()
}

/** A mídia, do cache ou da API; `null` se não existe mais (ou a busca falhou). Chamadas simultâneas dividem a mesma requisição. */
export function buscarMidia(id: number): Promise<SiteMidia | null> {
  const guardada = cache.get(id)
  if (guardada) return Promise.resolve(guardada)

  let pendente = pendentes.get(id)
  if (!pendente) {
    pendente = obterMidia(id)
      .then((r) => {
        registrarMidia(r.data)
        return r.data
      })
      .catch(() => null)
      .finally(() => pendentes.delete(id))
    pendentes.set(id, pendente)
  }
  return pendente
}
