<template>
  <div class="nfse-config">
    <div v-if="carregando" class="text-center text-muted"><div class="spinner spinner-sm"></div> Carregando...</div>
    <p v-else-if="erroCarga" class="nfse-erro" role="alert">{{ erroCarga }}</p>

    <template v-else-if="config">
      <div class="filter-tabs" role="tablist" style="margin-bottom:16px;">
        <button v-for="a in abas" :key="a.id" type="button" role="tab" :aria-selected="aba === a.id" :class="['filter-tab', { active: aba === a.id }]" @click="aba = a.id">
          {{ a.rotulo }}
        </button>
      </div>

      <!-- 1. Emitente -->
      <section v-show="aba === 'emitente'">
        <p v-if="config.emitente.id" class="nfse-ok">
          <i class="bi bi-check-circle-fill"></i> Emitente cadastrado: <strong>{{ config.emitente.razao }}</strong> ({{ formatDocument(config.emitente.cnpj) }}).
          Alterações aqui atualizam o cadastro na API Brasil.
        </p>
        <p v-else class="nfse-aviso">
          O cadastro do emitente na API Brasil custa <strong>R$ 19,00</strong>, uma vez por CNPJ. Se o CNPJ já estiver na sua conta,
          ele é apenas vinculado, sem nova cobrança.
        </p>

        <div class="nfse-grid">
          <div class="form-group span-2"><label for="em-razao">Razão social *</label><input id="em-razao" v-model="emitente.razao" class="form-control" /></div>
          <div class="form-group"><label for="em-nome">Nome fantasia</label><input id="em-nome" v-model="emitente.nome" class="form-control" /></div>
          <div class="form-group"><label for="em-cnpj">CNPJ *</label><input id="em-cnpj" v-model="emitente.cnpj" class="form-control" :disabled="!!config.emitente.id" /></div>
          <div class="form-group"><label for="em-im">Inscrição municipal</label><input id="em-im" v-model="emitente.im" class="form-control" /></div>
          <div class="form-group"><label for="em-cnae">CNAE principal *</label><input id="em-cnae" v-model="emitente.cnae" class="form-control" placeholder="6911701" /></div>
          <div class="form-group span-2">
            <label for="em-crt">Regime tributário (CRT) *</label>
            <select id="em-crt" v-model="emitente.crt" class="form-control">
              <option value="">Selecione...</option>
              <option value="1">1 – Simples Nacional</option>
              <option value="2">2 – Simples Nacional, excesso de sublimite</option>
              <option value="3">3 – Regime Normal</option>
              <option value="4">4 – MEI</option>
            </select>
          </div>
          <div class="form-group"><label for="em-tel">Telefone *</label><input id="em-tel" v-model="emitente.telefone" class="form-control" /></div>
          <div class="form-group span-2"><label for="em-email">E-mail</label><input id="em-email" v-model="emitente.email" type="email" class="form-control" /></div>
          <div class="form-group"><label for="em-cep">CEP *</label><input id="em-cep" v-model="emitente.cep" class="form-control" @blur="completarCep" /></div>
          <div class="form-group span-2"><label for="em-rua">Logradouro *</label><input id="em-rua" v-model="emitente.rua" class="form-control" /></div>
          <div class="form-group"><label for="em-numero">Número *</label><input id="em-numero" v-model="emitente.numero" class="form-control" /></div>
          <div class="form-group"><label for="em-compl">Complemento</label><input id="em-compl" v-model="emitente.complemento" class="form-control" /></div>
          <div class="form-group"><label for="em-bairro">Bairro *</label><input id="em-bairro" v-model="emitente.bairro" class="form-control" /></div>
          <div class="form-group"><label for="em-mun">Município *</label><input id="em-mun" v-model="emitente.municipio" class="form-control" /></div>
          <div class="form-group"><label for="em-cmun">Código IBGE *</label><input id="em-cmun" v-model="emitente.cmun" class="form-control" maxlength="7" /></div>
          <div class="form-group"><label for="em-uf">UF *</label><input id="em-uf" v-model="emitente.uf" class="form-control" maxlength="2" /></div>
        </div>

        <div class="nfse-rodape">
          <button type="button" class="btn btn-primary" :disabled="!!salvando" @click="salvarEmitente">
            {{ salvando === 'emitente' ? 'Salvando...' : config.emitente.id ? 'Atualizar emitente' : 'Cadastrar emitente (R$ 19,00)' }}
          </button>
        </div>
      </section>

      <!-- 2. Certificado -->
      <section v-show="aba === 'certificado'">
        <p v-if="!config.emitente.id" class="nfse-aviso">Cadastre o emitente antes de enviar o certificado.</p>
        <p v-else-if="config.emitente.certificado_validade" :class="certificadoVencido ? 'nfse-aviso' : 'nfse-ok'">
          <i :class="certificadoVencido ? 'bi bi-exclamation-triangle-fill' : 'bi bi-shield-check'"></i>
          Certificado {{ certificadoVencido ? 'vencido em' : 'válido até' }} {{ formatDate(config.emitente.certificado_validade) }}.
          Envie um novo arquivo para renovar.
        </p>
        <p class="text-ink-muted" style="font-size:.85rem;">
          Certificado digital A1 do CNPJ emitente (.pfx ou .p12, até 5 MB). O arquivo e a senha vão direto para a API Brasil;
          o sistema guarda apenas a validade.
        </p>
        <div class="nfse-grid">
          <div class="form-group span-2">
            <label for="cert-arquivo">Arquivo</label>
            <input id="cert-arquivo" type="file" accept=".pfx,.p12" class="form-control" :disabled="!config.emitente.id" @change="escolherCertificado" />
          </div>
          <div class="form-group">
            <label for="cert-senha">Senha</label>
            <input id="cert-senha" v-model="certificado.senha" type="password" autocomplete="new-password" class="form-control" :disabled="!config.emitente.id" />
          </div>
        </div>
        <div class="nfse-rodape">
          <button type="button" class="btn btn-primary" :disabled="!!salvando || !config.emitente.id || !certificado.arquivo || !certificado.senha" @click="salvarCertificado">
            {{ salvando === 'certificado' ? 'Enviando...' : 'Enviar certificado' }}
          </button>
        </div>
      </section>

      <!-- 3. Padrões fiscais -->
      <section v-show="aba === 'padroes'">
        <p class="text-ink-muted" style="font-size:.85rem;">
          Valores usados em toda nota; podem ser ajustados na emissão. Confirme os códigos com a contabilidade — cada prefeitura tem as suas regras.
        </p>
        <div class="nfse-grid">
          <div class="form-group"><label for="pd-codigo">Código do serviço (LC 116) *</label><input id="pd-codigo" v-model="padroes.servico_codigo" class="form-control" /></div>
          <div class="form-group"><label for="pd-nacional">Código de tributação nacional</label><input id="pd-nacional" v-model="padroes.codigo_nacional" class="form-control" /></div>
          <div class="form-group"><label for="pd-nbs">Código NBS * (9 dígitos)</label><input id="pd-nbs" v-model="padroes.codigo_nbs" class="form-control" /></div>
          <div class="form-group"><label for="pd-cnae">CNAE do serviço</label><input id="pd-cnae" v-model="padroes.codigo_cnae" class="form-control" /></div>
          <div class="form-group"><label for="pd-mun">Município da prestação (IBGE) *</label><input id="pd-mun" v-model="padroes.codigo_municipio" class="form-control" maxlength="7" /></div>
          <div class="form-group"><label for="pd-trib">Código de tributação municipal</label><input id="pd-trib" v-model="padroes.codigo_tributacao_municipio" class="form-control" /></div>
          <div class="form-group"><label for="pd-aliq">Alíquota do ISS (%)</label><input id="pd-aliq" v-model="padroes.aliquota_iss" class="form-control" inputmode="decimal" /></div>
          <div class="form-group"><label for="pd-cst">CST (tributos nacionais)</label><input id="pd-cst" v-model="padroes.cst" class="form-control" /></div>
          <div class="form-group"><label for="pd-regime">Regime especial de tributação</label><input id="pd-regime" v-model="padroes.regime_tributacao" class="form-control" placeholder="ex.: 6 (ME/EPP do Simples)" /></div>
          <div class="form-group"><label for="pd-apuracao">Regime de apuração (Simples)</label><input id="pd-apuracao" v-model="padroes.regime_apuracao" class="form-control" /></div>
          <div class="form-group"><label for="pd-simples">% tributos do Simples</label><input id="pd-simples" v-model="padroes.percentual_simples" class="form-control" inputmode="decimal" /></div>
          <div class="form-group span-3">
            <label for="pd-disc">Discriminação padrão</label>
            <textarea id="pd-disc" v-model="padroes.discriminacao" class="form-control" rows="2"></textarea>
            <small class="text-ink-muted">Use {receita}, {cliente}, {vencimento} e {competencia}.</small>
          </div>
        </div>
        <div class="nfse-rodape">
          <button type="button" class="btn btn-primary" :disabled="!!salvando" @click="salvarPadroes">
            {{ salvando === 'padroes' ? 'Salvando...' : 'Salvar padrões' }}
          </button>
        </div>
      </section>

      <p v-if="config.webhook_url" class="nfse-webhook">
        <i class="bi bi-broadcast"></i> Webhook (atualização automática do status): <code>{{ config.webhook_url }}</code>
        <br /><small>A URL precisa ser pública (HTTPS). Sem ela, use o botão de atualizar na nota.</small>
      </p>

      <p v-if="erro" class="nfse-erro" role="alert">{{ erro }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { formatDate, formatDocument } from '../../services/api'
import {
  enviarCertificado, obterConfiguracao, obterEmitente, salvarEmitente as apiSalvarEmitente, salvarPadroes as apiSalvarPadroes,
  type ConfiguracaoNfse, type DadosEmitente, type PadroesFiscais,
} from '../../services/notasFiscaisApi'

const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
  /** Algo foi gravado (emitente, certificado ou padrões): quem usa o formulário pode recarregar o que depende disso. */
  (e: 'alterado'): void
  /** Há uma gravação em andamento (o modal não deve fechar). */
  (e: 'ocupado', ocupado: boolean): void
}>()

const abas = [
  { id: 'emitente', rotulo: '1. Emitente' },
  { id: 'certificado', rotulo: '2. Certificado A1' },
  { id: 'padroes', rotulo: '3. Padrões fiscais' },
] as const

const aba = ref<'emitente' | 'certificado' | 'padroes'>('emitente')
const config = ref<ConfiguracaoNfse | null>(null)
const carregando = ref(false)
const erroCarga = ref('')
const erro = ref('')
const salvando = ref<'' | 'emitente' | 'certificado' | 'padroes'>('')
// Só para escolher a aba inicial na primeira carga; depois de gravar, a aba atual é mantida.
const alterou = ref(false)

watch(salvando, (valor) => emit('ocupado', !!valor))

const emitente = reactive<Record<keyof DadosEmitente, string>>({
  razao: '', nome: '', cnpj: '', cnae: '', crt: '', im: '', telefone: '', email: '',
  rua: '', numero: '', complemento: '', bairro: '', municipio: '', cmun: '', uf: '', cep: '',
})
const certificado = reactive<{ arquivo: File | null; senha: string }>({ arquivo: null, senha: '' })
const padroes = reactive<Record<keyof PadroesFiscais, any>>({
  codigo_municipio: '', servico_codigo: '', codigo_nacional: '', codigo_nbs: '', codigo_cnae: '',
  codigo_tributacao_municipio: '', aliquota_iss: '', regime_tributacao: '', regime_apuracao: '',
  percentual_simples: '', cst: '', discriminacao: '',
})

const certificadoVencido = computed(() => {
  const v = config.value?.emitente.certificado_validade
  return !!v && new Date(v + 'T23:59:59') < new Date()
})

const mensagem = (e: unknown, padrao: string) => (e instanceof Error ? e.message : padrao)

async function carregar() {
  carregando.value = true
  erroCarga.value = ''
  try {
    const cfg = await obterConfiguracao()
    config.value = cfg
    for (const k of Object.keys(padroes) as (keyof PadroesFiscais)[]) padroes[k] = cfg.padroes[k] ?? ''
    if (cfg.emitente.id) {
      emitente.razao = cfg.emitente.razao ?? ''
      emitente.cnpj = cfg.emitente.cnpj ?? ''
      emitente.crt = cfg.emitente.crt ?? ''
      if (!alterou.value) aba.value = cfg.emitente.certificado_validade ? 'padroes' : 'certificado'
      try {
        const atual = await obterEmitente()
        if (atual) Object.assign(emitente, atual)
      } catch { /* formulário fica com o que o sistema já guarda */ }
    }
  } catch (e) {
    erroCarga.value = mensagem(e, 'Não foi possível carregar a configuração.')
  } finally {
    carregando.value = false
  }
}

async function completarCep() {
  const cep = emitente.cep.replace(/\D/g, '')
  if (cep.length !== 8) return
  try {
    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: AbortSignal.timeout(8000) })
    const d = await res.json()
    if (d.erro) return
    emitente.rua ||= d.logradouro || ''
    emitente.bairro ||= d.bairro || ''
    emitente.municipio ||= d.localidade || ''
    emitente.uf ||= d.uf || ''
    emitente.cmun ||= d.ibge || ''
  } catch { /* preenchimento manual */ }
}

async function executar(qual: 'emitente' | 'certificado' | 'padroes', acao: () => Promise<{ message?: string }>) {
  salvando.value = qual
  erro.value = ''
  try {
    const res = await acao()
    emit('toast', { msg: res.message || 'Salvo.', type: 'success' })
    alterou.value = true
    emit('alterado')
    await carregar()
  } catch (e) {
    erro.value = mensagem(e, 'Não foi possível salvar.')
  } finally {
    salvando.value = ''
  }
}

function salvarEmitente() {
  if (!config.value?.emitente.id && !confirm('Cadastrar o emitente na API Brasil? A operação custa R$ 19,00 (cobrada uma vez por CNPJ).')) return
  const dados = Object.fromEntries(Object.entries(emitente).filter(([, v]) => v !== '')) as unknown as DadosEmitente
  void executar('emitente', () => apiSalvarEmitente(dados))
}

function escolherCertificado(evento: Event) {
  certificado.arquivo = (evento.target as HTMLInputElement).files?.[0] ?? null
}

function salvarCertificado() {
  const { arquivo, senha } = certificado
  if (!arquivo) return
  void executar('certificado', async () => {
    const res = await enviarCertificado(arquivo, senha)
    certificado.senha = ''
    return res
  })
}

function salvarPadroes() {
  void executar('padroes', () => apiSalvarPadroes({ ...padroes }))
}

onMounted(carregar)
</script>

<style scoped>
.nfse-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.span-2 { grid-column: span 2; }
.span-3 { grid-column: 1 / -1; }
@media (max-width: 640px) {
  .nfse-grid { grid-template-columns: 1fr; }
  .span-2 { grid-column: auto; }
}
.nfse-rodape { display: flex; justify-content: flex-end; margin-top: 14px; }
.nfse-aviso { font-size: .85rem; background: rgba(176, 142, 53, .08); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; }
.nfse-ok { font-size: .85rem; color: var(--success); }
.nfse-erro { color: var(--danger); font-size: .85rem; margin: 12px 0 0; }
.nfse-webhook { font-size: .78rem; color: var(--ink-muted); margin-top: 18px; word-break: break-all; }
</style>
