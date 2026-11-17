<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-plus-circle"></i> Cadastrar e vincular</h2>
        <button class="modal-close" type="button" aria-label="Fechar" @click="emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding: 1.5rem;">
        <div v-if="abas.length > 1" class="tabs" style="margin-bottom: 1rem;">
          <button
            v-for="a in abas"
            :key="a.id"
            type="button"
            :class="['tab-btn', { active: aba === a.id }]"
            @click="aba = a.id"
          >
            <i :class="a.icon"></i> {{ a.label }}
          </button>
        </div>

        <p v-if="aba === 'processo'" class="cadastro-dica">
          Processo exige o número CNJ. Assunto ainda não distribuído entra como caso.
        </p>

        <div class="form-group">
          <label class="required-field" for="cr-titulo">Título</label>
          <input id="cr-titulo" type="text" class="form-control" v-model="form.titulo" :placeholder="aba === 'caso' ? 'Ex.: Consultoria trabalhista' : 'Ex.: Reclamação trabalhista - Fulano'">
        </div>

        <div v-if="aba === 'caso'" class="form-group">
          <label for="cr-tipo">Tipo</label>
          <select id="cr-tipo" class="form-control form-select" v-model="form.tipo">
            <option value="Extrajudicial">Extrajudicial</option>
            <option value="Judicial">Judicial</option>
          </select>
        </div>

        <div v-else class="form-group">
          <label class="required-field" for="cr-cnj">Número CNJ</label>
          <input id="cr-cnj" type="text" class="form-control" :value="form.cnj" @input="onCnj" placeholder="0000000-00.0000.0.00.0000" inputmode="numeric">
        </div>

        <div class="form-group">
          <label>Cliente</label>
          <div v-if="cliente" class="cadastro-cliente">
            <span>{{ cliente.nome }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="cliente = null"><i class="bi bi-x-lg"></i></button>
          </div>
          <ClienteAutocomplete v-else @select="c => cliente = { id: Number(c.id), nome: c.nome || c.text }" />
        </div>

        <div class="form-group">
          <label for="cr-ramo">Ramo do direito</label>
          <select id="cr-ramo" class="form-control form-select" v-model="form.ramo">
            <option value="">Selecione...</option>
            <option v-for="r in ramosPlanos" :key="r.id" :value="String(r.id)">{{ r.rotulo }}</option>
          </select>
        </div>

        <small v-if="erro" class="text-danger" style="display:block; margin-top: 8px;">{{ erro }}</small>
      </div>

      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
        <button class="btn btn-outline-secondary" type="button" @click="emit('close')">Cancelar</button>
        <button class="btn btn-primary" type="button" :disabled="salvando || !abas.length" @click="salvar">
          {{ salvando ? 'Salvando...' : (aba === 'caso' ? 'Cadastrar caso' : 'Cadastrar processo') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, toFormData } from '../../services/api'
import { pode } from '../../composables/useAcesso'
import { applyCNJMask } from '../../utils/formatters'
import ClienteAutocomplete from '../ClienteAutocomplete.vue'
import type { OpcaoVinculo } from './VinculoJuridicoCampo.vue'

/**
 * Cadastro rápido de caso ou processo a partir de uma receita/despesa (issue #892). Usa os endpoints de cada
 * módulo, então vale a permissão de criação de cada um. Emite "criado" com o item pronto para vincular.
 */
const props = withDefaults(defineProps<{
  clienteId?: number | string | null
  clienteNome?: string | null
}>(), { clienteId: null, clienteNome: null })

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'toast', t: { msg: string, type: string }): void
  (e: 'criado', o: OpcaoVinculo): void
}>()

interface Ramo { id: number | string, nome: string, filhos?: Ramo[] }

const abas = computed(() => [
  { id: 'caso' as const, label: 'Caso', icon: 'bi bi-briefcase', perm: 'casos.create' },
  { id: 'processo' as const, label: 'Processo', icon: 'bi bi-bank', perm: 'processos.create' },
].filter(a => pode(a.perm)))

const aba = ref<'caso' | 'processo'>(abas.value[0]?.id ?? 'caso')
const form = reactive({ titulo: '', tipo: 'Extrajudicial', cnj: '', ramo: '' })
const cliente = ref<{ id: number, nome: string } | null>(
  props.clienteId ? { id: Number(props.clienteId), nome: props.clienteNome || `Cliente #${props.clienteId}` } : null,
)
const ramos = ref<Ramo[]>([])
const salvando = ref(false)
const erro = ref('')

const ramosPlanos = computed(() => {
  const saida: { id: number | string, rotulo: string }[] = []
  const percorrer = (lista: Ramo[], nivel: number) => {
    for (const r of lista) {
      saida.push({ id: r.id, rotulo: `${'   '.repeat(nivel)}${nivel ? '↳ ' : ''}${r.nome}` })
      if (r.filhos?.length) percorrer(r.filhos, nivel + 1)
    }
  }
  percorrer(ramos.value, 0)
  return saida
})

function onCnj(e: Event) {
  form.cnj = applyCNJMask((e.target as HTMLInputElement).value)
  ;(e.target as HTMLInputElement).value = form.cnj
}

function validar(): string {
  if (form.titulo.trim().length < 3) return 'Informe um título com ao menos 3 caracteres.'
  if (aba.value === 'processo' && form.cnj.replace(/\D/g, '').length !== 20) return 'Informe o número CNJ completo (20 dígitos).'
  return ''
}

async function salvar() {
  erro.value = validar()
  if (erro.value) return

  salvando.value = true
  try {
    let id: number
    if (aba.value === 'caso') {
      const res = await apiFetch('casos/salvar', {
        method: 'POST',
        body: {
          titulo: form.titulo.trim(),
          tipo: form.tipo,
          status: 'Ativo',
          cliente_id: cliente.value?.id ?? '',
          area_do_direito: form.ramo,
        },
      })
      if (!res?.success || !res.data?.id) throw new Error(res?.message || 'Erro ao cadastrar o caso.')
      id = Number(res.data.id)
    } else {
      const res = await apiFetch('processos/salvar', {
        method: 'POST',
        body: toFormData({
          titulo_processo: form.titulo.trim(),
          numeroprocessocommascara: form.cnj,
          risco: 'Possível',
          cliente_id: cliente.value?.id ? String(cliente.value.id) : '',
          ramo_direito: form.ramo,
        }),
      })
      if (!res?.success || !res.id_processo) throw new Error(res?.message || 'Erro ao cadastrar o processo.')
      id = Number(res.id_processo)
    }

    emit('toast', { msg: aba.value === 'caso' ? 'Caso cadastrado e vinculado.' : 'Processo cadastrado e vinculado.', type: 'success' })
    emit('criado', {
      tipo: aba.value,
      id,
      rotulo: aba.value === 'processo' ? `${form.cnj} - ${form.titulo.trim()}` : form.titulo.trim(),
      cliente_id: cliente.value?.id ?? null,
      cliente_nome: cliente.value?.nome ?? null,
      processo_id: aba.value === 'processo' ? id : null,
    })
  } catch (e: any) {
    erro.value = e?.message || 'Falha ao cadastrar.'
  } finally {
    salvando.value = false
  }
}

onMounted(async () => {
  try {
    const res = await apiFetch('processos/ramos')
    ramos.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (e) {
    console.error('Erro ao buscar ramos:', e)
  }
})
</script>

<style scoped>
.cadastro-dica { font-size: .8rem; color: var(--ink-muted); margin: 0 0 12px; }
.cadastro-cliente { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--input-bg); }
.form-group + .form-group { margin-top: 12px; }
</style>
