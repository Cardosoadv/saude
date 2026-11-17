<template>
  <div>
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <h3 style="margin: 0 0 6px 0; display:flex; align-items:center; gap:8px;">
        <i class="bi bi-calendar-check" style="color: var(--accent);"></i> Calendário forense
      </h3>
      <p style="font-size: 0.85rem; color: var(--ink-muted); margin: 0; line-height: 1.4;">
        Os prazos das intimações são contados em dias úteis. Além dos feriados nacionais (já cadastrados), cadastre
        aqui os feriados estaduais e municipais e as suspensões de expediente de cada tribunal. O recesso de
        20/12 a 20/01 já é aplicado automaticamente.
      </p>
    </div>

    <!-- Feriados -->
    <div class="card animate-fade-in" style="margin-bottom: 1.5rem; padding: 20px;">
      <div class="cal-cabecalho">
        <h4 style="margin:0;">Feriados</h4>
        <label class="cal-filtro">Ano
          <input class="form-control" type="number" v-model.number="ano" min="2000" max="2100" @change="carregarFeriados" />
        </label>
      </div>

      <form class="cal-form" @submit.prevent="salvarFeriadoForm" aria-label="Cadastrar feriado">
        <input class="form-control" type="date" v-model="feriado.data" required aria-label="Data do feriado" />
        <input class="form-control" type="text" v-model="feriado.nome" placeholder="Nome" required maxlength="150" aria-label="Nome do feriado" />
        <select class="form-control" v-model="feriado.abrangencia" aria-label="Abrangência">
          <option value="nacional">Nacional</option>
          <option value="estadual">Estadual</option>
          <option value="municipal">Municipal</option>
        </select>
        <input v-if="feriado.abrangencia !== 'nacional'" class="form-control" type="text" v-model="feriado.uf" placeholder="UF" maxlength="2" required aria-label="UF" />
        <input v-if="feriado.abrangencia === 'municipal'" class="form-control" type="text" v-model="feriado.municipio" placeholder="Município/comarca" required aria-label="Município" />
        <input class="form-control" type="text" v-model="feriado.tribunal" placeholder="Tribunal (opcional)" maxlength="20" aria-label="Tribunal" />
        <label class="cal-check"><input type="checkbox" v-model="feriado.recorrente" /> Todo ano</label>
        <button class="btn btn-primary" type="submit" :disabled="salvando">
          <i class="bi bi-plus-circle"></i> {{ feriado.id_feriado ? 'Salvar' : 'Adicionar' }}
        </button>
        <button v-if="feriado.id_feriado" class="btn btn-ghost" type="button" @click="limparFeriado">Cancelar</button>
      </form>

      <div style="overflow-x:auto;">
        <table class="table-premium" style="width:100%; border-collapse:collapse; text-align:left;">
          <thead>
            <tr style="border-bottom:2px solid var(--border);">
              <th style="padding:10px 12px;">Data</th><th style="padding:10px 12px;">Feriado</th>
              <th style="padding:10px 12px;">Abrangência</th><th style="padding:10px 12px;"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in feriados" :key="f.id_feriado" style="border-bottom:1px solid var(--border);">
              <td style="padding:10px 12px;">{{ formatDate(f.data) }}</td>
              <td style="padding:10px 12px;">{{ f.nome }}<span v-if="f.recorrente && Number(f.recorrente)" class="badge badge-muted" style="margin-left:6px;">todo ano</span></td>
              <td style="padding:10px 12px;">{{ abrangenciaTexto(f) }}</td>
              <td style="padding:10px 12px; white-space:nowrap;">
                <button class="btn btn-ghost btn-sm" @click="editarFeriado(f)" :aria-label="'Editar ' + f.nome"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-ghost btn-sm" @click="removerFeriado(f)" :aria-label="'Excluir ' + f.nome"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!feriados.length">
              <td colspan="4" style="padding:16px; text-align:center; color:var(--ink-muted);">Nenhum feriado cadastrado neste ano.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Suspensões -->
    <div class="card animate-fade-in" style="padding: 20px;">
      <h4 style="margin:0 0 12px;">Suspensões de expediente e de prazos</h4>

      <form class="cal-form" @submit.prevent="salvarSuspensaoForm" aria-label="Cadastrar suspensão">
        <input class="form-control" type="text" v-model="suspensao.tribunal" placeholder="Tribunal (vazio = todos)" maxlength="20" aria-label="Tribunal da suspensão" />
        <input class="form-control" type="date" v-model="suspensao.data_inicio" required aria-label="Início da suspensão" />
        <input class="form-control" type="date" v-model="suspensao.data_fim" required aria-label="Fim da suspensão" />
        <input class="form-control" type="text" v-model="suspensao.motivo" placeholder="Motivo (ex.: portaria, instabilidade do sistema)" required maxlength="250" aria-label="Motivo" />
        <button class="btn btn-primary" type="submit" :disabled="salvando">
          <i class="bi bi-plus-circle"></i> {{ suspensao.id_suspensao ? 'Salvar' : 'Adicionar' }}
        </button>
        <button v-if="suspensao.id_suspensao" class="btn btn-ghost" type="button" @click="limparSuspensao">Cancelar</button>
      </form>

      <div style="overflow-x:auto;">
        <table class="table-premium" style="width:100%; border-collapse:collapse; text-align:left;">
          <thead>
            <tr style="border-bottom:2px solid var(--border);">
              <th style="padding:10px 12px;">Tribunal</th><th style="padding:10px 12px;">Período</th>
              <th style="padding:10px 12px;">Motivo</th><th style="padding:10px 12px;"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in suspensoes" :key="s.id_suspensao" style="border-bottom:1px solid var(--border);">
              <td style="padding:10px 12px;">{{ s.tribunal || 'Todos' }}</td>
              <td style="padding:10px 12px;">{{ formatDate(s.data_inicio) }} a {{ formatDate(s.data_fim) }}</td>
              <td style="padding:10px 12px;">{{ s.motivo }}</td>
              <td style="padding:10px 12px; white-space:nowrap;">
                <button class="btn btn-ghost btn-sm" @click="editarSuspensao(s)" :aria-label="'Editar suspensão ' + s.motivo"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-ghost btn-sm" @click="removerSuspensao(s)" :aria-label="'Excluir suspensão ' + s.motivo"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!suspensoes.length">
              <td colspan="4" style="padding:16px; text-align:center; color:var(--ink-muted);">Nenhuma suspensão cadastrada.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * CRUD do calendário forense: feriados (nacional/UF/município) e suspensões de expediente por tribunal.
 * Exige a permissão admin.settings no servidor.
 */
import { ref, reactive, onMounted } from 'vue'
import { formatDate } from '../../services/api'
import {
  listarFeriados, salvarFeriado, excluirFeriado, listarSuspensoes, salvarSuspensao, excluirSuspensao,
  type Feriado, type Suspensao,
} from '../../services/calendarioForenseApi'

const emit = defineEmits<{ (e: 'toast', payload: { msg: string; type: string }): void }>()

const feriados = ref<Feriado[]>([])
const suspensoes = ref<Suspensao[]>([])
const ano = ref(new Date().getFullYear())
const salvando = ref(false)

const novoFeriado = (): Feriado & { recorrente: boolean } =>
  ({ data: '', nome: '', abrangencia: 'nacional', uf: '', municipio: '', tribunal: '', recorrente: false })
const novaSuspensao = (): Suspensao => ({ tribunal: '', data_inicio: '', data_fim: '', motivo: '' })

const feriado = reactive<Feriado & { recorrente: boolean }>(novoFeriado())
const suspensao = reactive<Suspensao>(novaSuspensao())

const toast = (msg: string, type = 'success') => emit('toast', { msg, type })
const mensagemErro = (e: any, padrao: string) => e?.data?.messages ? Object.values(e.data.messages).join(' ') : (e?.message || padrao)

function abrangenciaTexto(f: Feriado): string {
  if (f.abrangencia === 'municipal') return `Municipal: ${f.municipio}/${f.uf}`
  if (f.abrangencia === 'estadual') return `Estadual: ${f.uf}`
  return 'Nacional'
}

async function carregarFeriados() {
  try {
    feriados.value = await listarFeriados(ano.value)
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao carregar feriados.'), 'error')
  }
}

async function carregarSuspensoes() {
  try {
    suspensoes.value = await listarSuspensoes()
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao carregar suspensões.'), 'error')
  }
}

function limparFeriado() { Object.assign(feriado, novoFeriado(), { id_feriado: undefined }) }
function limparSuspensao() { Object.assign(suspensao, novaSuspensao(), { id_suspensao: undefined }) }

function editarFeriado(f: Feriado) {
  Object.assign(feriado, novoFeriado(), f, { recorrente: !!Number(f.recorrente) })
}
function editarSuspensao(s: Suspensao) { Object.assign(suspensao, novaSuspensao(), s) }

async function salvarFeriadoForm() {
  salvando.value = true
  try {
    await salvarFeriado({ ...feriado })
    toast('Feriado salvo.')
    limparFeriado()
    await carregarFeriados()
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao salvar feriado.'), 'error')
  } finally {
    salvando.value = false
  }
}

async function salvarSuspensaoForm() {
  salvando.value = true
  try {
    await salvarSuspensao({ ...suspensao })
    toast('Suspensão salva.')
    limparSuspensao()
    await carregarSuspensoes()
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao salvar suspensão.'), 'error')
  } finally {
    salvando.value = false
  }
}

async function removerFeriado(f: Feriado) {
  if (!f.id_feriado || !window.confirm(`Excluir o feriado "${f.nome}"? Os prazos novos deixam de considerá-lo.`)) return
  try {
    await excluirFeriado(f.id_feriado)
    toast('Feriado excluído.')
    await carregarFeriados()
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao excluir feriado.'), 'error')
  }
}

async function removerSuspensao(s: Suspensao) {
  if (!s.id_suspensao || !window.confirm('Excluir esta suspensão? Os prazos novos deixam de considerá-la.')) return
  try {
    await excluirSuspensao(s.id_suspensao)
    toast('Suspensão excluída.')
    await carregarSuspensoes()
  } catch (e: any) {
    toast(mensagemErro(e, 'Erro ao excluir suspensão.'), 'error')
  }
}

onMounted(() => {
  carregarFeriados()
  carregarSuspensoes()
})
</script>

<style scoped>
.cal-cabecalho { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px; }
.cal-filtro { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; }
.cal-filtro input { width: 90px; }
.cal-form { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; align-items: center; }
.cal-form .form-control { flex: 1 1 150px; min-width: 120px; }
.cal-check { display: flex; align-items: center; gap: 4px; font-size: 0.8rem; white-space: nowrap; }
</style>
