/**
 * Arquivos de contratos. Ficam no Google Drive, na subpasta "Contratos" da pasta do cliente;
 * a API só guarda o vínculo com o contrato.
 */
import { apiFetch } from './api'
import type { ContratoArquivo, Id } from '../types/domain'

export interface RespostaEnvioContrato {
  success: boolean
  message?: string
  data: ContratoArquivo[]
  /** Falhas por arquivo (envio parcial). */
  erros?: Array<{ arquivo: string; mensagem: string }>
}

export async function listarArquivosContrato(contratoId: Id): Promise<ContratoArquivo[]> {
  const res = await apiFetch<{ data?: ContratoArquivo[] }>(`contratos/${contratoId}/arquivos`)
  return res?.data ?? []
}

/** Envia um ou mais arquivos (campo `arquivos[]`). Envio de arquivo demora mais que o padrão da API. */
export async function enviarArquivosContrato(contratoId: Id, arquivos: File[], categoria: string): Promise<RespostaEnvioContrato> {
  const corpo = new FormData()
  corpo.append('categoria', categoria)
  arquivos.forEach((arquivo) => corpo.append('arquivos[]', arquivo))

  return apiFetch<RespostaEnvioContrato>(`contratos/${contratoId}/arquivos`, {
    method: 'POST',
    body: corpo,
    timeout: 120000,
  })
}

/** Move o arquivo para a lixeira do Drive e remove-o da lista. */
export async function removerArquivoContrato(id: Id): Promise<void> {
  await apiFetch(`contratos/arquivos/${id}`, { method: 'DELETE' })
}
