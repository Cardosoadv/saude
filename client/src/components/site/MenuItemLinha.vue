<template>
  <div class="menu-item" :class="{ 'menu-item--sub': subitem, 'menu-item--erro': temErros }" :data-item="id">
    <span class="alca" draggable="true" title="Arraste para reordenar" aria-hidden="true" @dragstart="emit('arrastar')" @dragend="emit('soltar-fim')">
      <i class="bi bi-grip-vertical"></i>
    </span>

    <div class="campos">
      <div class="linha">
        <div class="campo" :class="{ 'campo--erro': erros.rotulo }" data-campo="rotulo">
          <input class="form-control" :value="item.rotulo" placeholder="Rótulo" aria-label="Rótulo" @input="alterar({ rotulo: ($event.target as HTMLInputElement).value })" />
          <p v-if="erros.rotulo" class="erro" role="alert">{{ erros.rotulo }}</p>
        </div>

        <select class="form-control tipo" :value="item.tipo" aria-label="Tipo de destino" @change="alterar({ tipo: ($event.target as HTMLSelectElement).value as ItemMenu['tipo'] })">
          <option value="pagina">Página do site</option>
          <option value="ancora">Âncora</option>
          <option value="url">URL externa</option>
        </select>

        <div v-if="item.tipo === 'pagina'" class="campo" :class="{ 'campo--erro': erros.pagina_id }" data-campo="pagina_id">
          <select class="form-control" :value="item.pagina_id ?? ''" aria-label="Página de destino" @change="alterar({ pagina_id: ($event.target as HTMLSelectElement).value === '' ? null : Number(($event.target as HTMLSelectElement).value) })">
            <option value="">Escolha a página…</option>
            <option v-for="p in paginas" :key="p.id" :value="p.id">{{ p.titulo }}{{ p.status !== 'publicado' ? ' (rascunho)' : '' }}</option>
          </select>
          <p v-if="erros.pagina_id" class="erro" role="alert">{{ erros.pagina_id }}</p>
        </div>

        <div v-else-if="item.tipo === 'ancora'" class="campo" :class="{ 'campo--erro': erros.ancora }" data-campo="ancora">
          <input class="form-control" :value="item.ancora" :list="listaId" placeholder="expertise" aria-label="Âncora" @input="alterar({ ancora: ($event.target as HTMLInputElement).value })" />
          <datalist :id="listaId"><option v-for="a in ancoras" :key="a" :value="a"></option></datalist>
          <p v-if="erros.ancora" class="erro" role="alert">{{ erros.ancora }}</p>
        </div>

        <div v-else class="campo" :class="{ 'campo--erro': erros.url }" data-campo="url">
          <input class="form-control" :value="item.url" placeholder="https://… , mailto:… , tel:… ou /caminho" aria-label="Endereço" @input="alterar({ url: ($event.target as HTMLInputElement).value })" />
          <p v-if="erros.url" class="erro" role="alert">{{ erros.url }}</p>
        </div>
      </div>

      <p v-if="aviso" class="aviso" role="status" data-aviso-destino><i class="bi bi-exclamation-triangle"></i> {{ aviso }}</p>
      <p v-for="m in errosApi" :key="m" class="erro" role="alert">{{ m }}</p>

      <label class="destaque">
        <input type="checkbox" :checked="item.destaque" @change="alterar({ destaque: ($event.target as HTMLInputElement).checked })" />
        Destacar como botão
      </label>
    </div>

    <div class="acoes">
      <button type="button" class="btn btn-ghost btn-sm" title="Subir" data-subir :disabled="primeiro" @click="emit('subir')"><i class="bi bi-arrow-up"></i></button>
      <button type="button" class="btn btn-ghost btn-sm" title="Descer" data-descer :disabled="ultimo" @click="emit('descer')"><i class="bi bi-arrow-down"></i></button>
      <button v-if="!subitem" type="button" class="btn btn-ghost btn-sm" title="Tornar subitem do item anterior" data-subitem :disabled="!podeSubitem" @click="emit('tornar-subitem')"><i class="bi bi-arrow-return-right"></i></button>
      <button v-else type="button" class="btn btn-ghost btn-sm" title="Tirar do submenu" data-sair-submenu @click="emit('tirar-do-submenu')"><i class="bi bi-arrow-return-left"></i></button>
      <button type="button" class="btn btn-danger btn-sm" title="Remover" data-remover @click="emit('remover')"><i class="bi bi-trash"></i></button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import type { ItemMenu, PaginaResumo } from '../../utils/menusSite'

const props = withDefaults(defineProps<{
  item: ItemMenu
  id: string
  paginas: PaginaResumo[]
  ancoras?: string[]
  /** Erros de campo deste item (`rotulo`, `pagina_id`, `url`, `ancora`). */
  erros?: Record<string, string>
  errosApi?: string[]
  aviso?: string | null
  subitem?: boolean
  primeiro?: boolean
  ultimo?: boolean
  podeSubitem?: boolean
}>(), { ancoras: () => [], erros: () => ({}), errosApi: () => [], aviso: null, subitem: false, primeiro: false, ultimo: false, podeSubitem: false })

const emit = defineEmits<{
  (e: 'update:item', item: ItemMenu): void
  (e: 'subir'): void
  (e: 'descer'): void
  (e: 'remover'): void
  (e: 'tornar-subitem'): void
  (e: 'tirar-do-submenu'): void
  (e: 'arrastar'): void
  (e: 'soltar-fim'): void
}>()

const listaId = computed(() => `ancoras-${props.id}`)
const temErros = computed(() => Object.keys(props.erros).length > 0)

// Parte do último valor emitido: dois eventos seguidos não perdem o primeiro.
let ultimoItem = props.item
const alterar = (parcial: Partial<ItemMenu>) => {
  ultimoItem = { ...ultimoItem, ...parcial }
  emit('update:item', ultimoItem)
}
watch(() => props.item, (v) => { ultimoItem = v })
</script>

<style scoped>
.menu-item { display: flex; gap: 8px; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; background: var(--surface); }
.menu-item--sub { margin-left: 32px; background: #faf9f6; }
.menu-item--erro { border-color: var(--danger); }
.alca { cursor: grab; color: var(--ink-dim); padding-top: 8px; }
.campos { flex: 1; min-width: 0; }
.linha { display: flex; flex-wrap: wrap; gap: 8px; }
.campo { flex: 1 1 200px; min-width: 0; }
.tipo { flex: 0 0 150px; }
.campo--erro .form-control { border-color: var(--danger); }
.erro { margin: 4px 0 0; font-size: .8rem; color: var(--danger); }
.aviso { margin: 6px 0 0; font-size: .82rem; color: var(--accent); }
.destaque { display: inline-flex; gap: 6px; align-items: center; margin-top: 6px; font-size: .85rem; cursor: pointer; }
.acoes { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; max-width: 150px; }
</style>
