export interface EnderecoCep {
  logradouro: string
  bairro: string
  cidade: string
  uf: string
}

/** Aplica a máscara 00000-000 enquanto o usuário digita. */
export function formatarCep(valor: string): string {
  const digitos = (valor || '').replace(/\D/g, '').slice(0, 8)
  return digitos.length > 5 ? `${digitos.slice(0, 5)}-${digitos.slice(5)}` : digitos
}

/**
 * Consulta o CEP no ViaCEP.
 * Retorna null quando o CEP não tem 8 dígitos ou não existe; lança em falha de rede.
 */
export async function buscarCep(cep: string): Promise<EnderecoCep | null> {
  const digitos = (cep || '').replace(/\D/g, '')
  if (digitos.length !== 8) return null

  const response = await fetch(`https://viacep.com.br/ws/${digitos}/json/`, { signal: AbortSignal.timeout(8000) })
  if (!response.ok) return null
  const data = await response.json()
  if (!data || data.erro) return null

  return {
    logradouro: data.logradouro || '',
    bairro: data.bairro || '',
    cidade: data.localidade || '',
    uf: data.uf || ''
  }
}
