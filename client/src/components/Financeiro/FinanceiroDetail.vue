<template>
  <div class="page-content animate-fade-in pb-5">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#" @click.prevent="$emit('set-view', config.viewName)">{{ config.listLabel }}</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isEdit ? config.editLabel : config.newLabel }}</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>{{ isEdit ? config.editLabel + ' #' + transactionId : config.newLabel }}</h1>
        <p>{{ isEdit ? config.editSub : config.newSub }}</p>
      </div>
      <div style="display:flex;gap:.5rem;">
        <button class="btn btn-outline-secondary" @click="$emit('set-view', config.viewName)">
          <i class="bi bi-arrow-left-circle"></i> Voltar
        </button>
        <button class="btn btn-outline-warning" @click="openLoadFavoriteModal" title="Carregar Favorito">
          <i class="bi bi-star-fill"></i>
        </button>
        <button class="btn btn-outline-warning" @click="openSaveFavoriteModal" title="Salvar como Favorito">
          <i class="bi bi-star"></i>
        </button>
        <button class="btn btn-primary" @click="saveTransaction" :disabled="saving">
          <i class="bi bi-save"></i> {{ saving ? 'Salvando...' : config.btnSaveLabel }}
        </button>
      </div>
    </div>

    <!-- Modals de Favoritos -->
    <div v-if="showSaveFavoriteModal" class="modal-backdrop" @click.self="showSaveFavoriteModal = false">
      <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
          <h2><i class="bi bi-star"></i> Salvar como Favorito</h2>
          <button class="modal-close" @click="showSaveFavoriteModal = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Nome do Favorito</label>
            <input type="text" class="form-control" v-model="favoriteTitle" :placeholder="config.favoritePlaceholder" @keyup.enter="saveFavorite">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" @click="showSaveFavoriteModal = false">Cancelar</button>
          <button class="btn btn-primary" @click="saveFavorite">Salvar</button>
        </div>
      </div>
    </div>

    <div v-if="showLoadFavoriteModal" class="modal-backdrop" @click.self="showLoadFavoriteModal = false">
      <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
          <h2><i class="bi bi-star-fill"></i> Carregar Favorito</h2>
          <button class="modal-close" @click="showLoadFavoriteModal = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" style="padding: 8px 12px; max-height: 320px; overflow-y: auto;">
          <div v-for="fav in favoritesList" :key="fav.id_favorito" class="favorite-item" @click="loadFavorite(fav)">
            <span class="favorite-title"><i class="bi bi-star"></i> {{ fav.titulo }}</span>
            <button class="btn btn-sm btn-outline-danger" title="Excluir favorito" @click.stop="deleteFavorite(fav.id_favorito)"><i class="bi bi-trash"></i></button>
          </div>
          <div v-if="favoritesList.length === 0" class="empty-state" style="padding: 24px 12px;">Nenhum favorito encontrado.</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" @click="showLoadFavoriteModal = false">Fechar</button>
        </div>
      </div>
    </div>

    <div class="content-grid" style="margin-top: 1.5rem;">

      <!-- Main Form Area -->
      <div>
        <div class="card glass-panel form-card">
          <div class="card-header">
            <h3><i class="bi" :class="config.cardIcon"></i> {{ config.cardTitle }}</h3>
          </div>

          <div class="card-body">

            <div class="form-row col-1">
              <div class="form-group">
                <label :for="config.descField" class="required-field"><i class="bi bi-fonts"></i> {{ config.descLabel }}</label>
                <input type="text" class="form-control" :id="config.descField" v-model="form[config.descField]" :placeholder="config.descPlaceholder" :class="{'is-invalid': errors[config.descField]}">
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors[config.descField]">{{ errors[config.descField] }}</small>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="valor" class="required-field"><i class="bi bi-cash-stack"></i> Valor (R$)</label>
                <div class="input-group">
                  <span class="input-group-addon" style="background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                  <input type="text" class="form-control" id="valor" v-model="displayValor" @blur="formatDisplayValor" @input="recalcRateioAutomatico" placeholder="0,00" :class="{'is-invalid': errors.valor}">
                </div>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.valor">{{ errors.valor }}</small>
              </div>

              <div class="form-group">
                <label for="vencimento_dt" class="required-field"><i class="bi bi-calendar-event"></i> Vencimento</label>
                <input type="date" class="form-control" id="vencimento_dt" v-model="form.vencimento_dt" :class="{'is-invalid': errors.vencimento_dt}">
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.vencimento_dt">{{ errors.vencimento_dt }}</small>
              </div>
            </div>

            <div class="form-row col-1">
              <div class="form-group">
                <label for="party"><i class="bi" :class="config.partyIcon"></i> {{ config.partyLabel }}</label>
                <div class="position-relative">
                  <div class="input-group" style="display: flex;">
                    <input
                      type="text"
                      class="form-control"
                      style="flex: 1; border-top-right-radius: 0; border-bottom-right-radius: 0;"
                      v-model="partyBusca"
                      @input="onPartyInput"
                      @focus="showParty = true"
                      :placeholder="config.partyPlaceholder"
                    >
                    <span class="input-group-text" style="background:var(--input-bg); border-color:var(--border-color); color:var(--ink-muted);" v-if="!(type === 'despesa' ? form.fornecedor : form.cliente)"><i class="bi bi-search"></i></span>
                    <button class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 0 12px;" type="button" v-if="type === 'despesa' ? form.fornecedor : form.cliente" @click="clearParty"><i class="bi bi-x-lg"></i></button>
                  </div>

                  <!-- Dropdown results styled as premium -->
                  <ul v-if="showParty && partyOptions.length" class="autocomplete-list glass-panel shadow z-3 position-absolute w-100">
                    <li
                      v-for="p in partyOptions"
                      :key="p.id || p.id_fornecedor || p.id_cliente"
                      @click="selectParty(p)"
                    >
                      <div class="autocomplete-item-title">
                        <i class="bi" :class="config.partyIcon"></i> {{ p.text || p.nome || p.cliente }}
                      </div>
                      <small class="autocomplete-item-sub">
                        {{ p.documento || p.cpf_cnpj || 'Sem Documento' }}
                      </small>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="contrato"><i class="bi bi-file-earmark-text"></i> Contrato Vinculado</label>
                <div class="position-relative">
                  <div class="input-group" style="display: flex;">
                    <input
                      type="text"
                      class="form-control"
                      style="flex: 1; border-top-right-radius: 0; border-bottom-right-radius: 0;"
                      v-model="contratoBusca"
                      @input="onContratoInput"
                      @focus="showContratos = true"
                      placeholder="Buscar contrato por título ou número..."
                    >
                    <span class="input-group-text" style="background:var(--input-bg); border-color:var(--border-color); color:var(--ink-muted);" v-if="!form.contrato_id"><i class="bi bi-search"></i></span>
                    <button class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 0 12px;" type="button" v-if="form.contrato_id" @click="clearContrato"><i class="bi bi-x-lg"></i></button>
                  </div>

                  <ul v-if="showContratos && contratoOptions.length" class="autocomplete-list glass-panel shadow z-3 position-absolute w-100">
                    <li
                      v-for="c in contratoOptions"
                      :key="c.id_contrato"
                      @click="selectContrato(c)"
                    >
                      <div class="autocomplete-item-title"><i class="bi bi-file-earmark-text"></i> {{ c.titulo || c.numero_contrato || c.numero }}</div>
                      <small class="autocomplete-item-sub">{{ c.cliente_nome || c.nome_cliente || 'Sem Cliente' }}</small>
                    </li>
                  </ul>
                </div>
              </div>

              <div class="form-group">
                <label for="comentario"><i class="bi bi-chat-left-text"></i> Comentário / Observações</label>
                <textarea class="form-control" id="comentario" v-model="form.comentario" rows="3" placeholder="Anotações internas sobre essa receita..."></textarea>
              </div>
            </div>

          </div>
        </div>

        <!-- Rateio de Categorias Module -->
        <div class="card glass-panel form-card" style="margin-top: 24px;">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3><i class="bi bi-tags"></i> Rateio de Categorias</h3>
            <div style="display:flex; gap: 8px;">
              <button class="btn btn-sm btn-outline-primary" @click="dividirCategoriasIgualmente" v-if="form.categorias.length > 0">
                <i class="bi bi-calculator"></i> Dividir Igualmente
              </button>
              <button class="btn btn-sm btn-success" @click="addCategoriaRow">
                <i class="bi bi-plus-circle"></i> Adicionar Categoria
              </button>
            </div>
          </div>

          <div class="card-body">
            <div v-if="!form.categorias.length" class="empty-rateio text-center">
               <i class="bi bi-folder2-open" style="font-size: 2rem; color: var(--ink-muted); margin-bottom: 10px; display: block;"></i>
               <p style="color: var(--ink-muted); margin:0;">{{ config.categoryEmptyText }}</p>
               <small class="text-danger" v-if="errors.categorias">{{ errors.categorias }}</small>
            </div>

            <div class="rateio-list" v-else>
              <div v-for="(c, index) in form.categorias" :key="index" class="rateio-item">
                <div class="form-group" style="flex: 2;">
                  <select class="form-control" v-model="c.categoria_id" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    <option value="">Selecione uma Categoria...</option>
                    <option v-for="cat in categorias" :key="cat.id_categoria" :value="cat.id_categoria">{{ cat.categoria }}</option>
                  </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <div class="input-group">
                      <span class="input-group-addon" style="font-size: 0.9rem; padding: 0.4rem 0.6rem; background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                      <input type="text" class="form-control" v-model="c.valor" @blur="formatCategoriaValor(c)" placeholder="0,00" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    </div>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-danger" @click="removeCategoriaRow(index)" title="Remover Categoria" style="padding: 0.4rem 0.75rem; border-color: transparent;">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>

              <!-- Categorias Summary Footer -->
              <div class="rateio-footer" :class="{'has-error': totalCategoriasError}">
                <div class="rateio-footer-info" v-if="totalCategoriasError">
                   <i class="bi bi-exclamation-triangle-fill"></i> {{ config.categoryFooterDiffLabel }}
                </div>
                <div class="rateio-footer-info" v-else>
                   <i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Divisão completa e equivalente ao total.
                </div>
                <div class="rateio-footer-total">
                   Total Classificado: <strong>{{ formatCurrency(totalCategoriasReal) }}</strong> / <span style="color:var(--ink-muted);">{{ formatCurrency(valorNum) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Rateio Module -->
        <div class="card glass-panel form-card" style="margin-top: 24px;">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3><i class="bi bi-diagram-3"></i> {{ config.rateioLabel }}</h3>
            <div style="display:flex; gap: 8px;">
              <button class="btn btn-sm btn-outline-primary" @click="dividirIgualmente" v-if="form.rateio.length > 0">
                <i class="bi bi-calculator"></i> Dividir Igualmente
              </button>
              <button class="btn btn-sm btn-success" @click="addRateioRow">
                <i class="bi bi-plus-circle"></i> Adicionar Sócio
              </button>
            </div>
          </div>

          <div class="card-body">
            <div v-if="!form.rateio.length" class="empty-rateio text-center">
               <i class="bi bi-people" style="font-size: 2rem; color: var(--ink-muted); margin-bottom: 10px; display: block;"></i>
               <p style="color: var(--ink-muted); margin:0;">{{ config.rateioEmptyText }}</p>
            </div>

            <div class="rateio-list" v-else>
              <div v-for="(r, index) in form.rateio" :key="index" class="rateio-item">
                <div class="form-group" style="flex: 2;">
                  <select class="form-control" v-model="r.id" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    <option value="">Selecione um Sócio...</option>
                    <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.text || u.username || u.nome || u.name }}</option>
                  </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <div class="input-group">
                      <span class="input-group-addon" style="font-size: 0.9rem; padding: 0.4rem 0.6rem; background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                      <input type="text" class="form-control" v-model="r.valor" @blur="formatRateioValor(r)" placeholder="0,00" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    </div>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-danger" @click="removeRateioRow(index)" title="Remover Rateio" style="padding: 0.4rem 0.75rem; border-color: transparent;">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>

              <!-- Rateio Summary Footer -->
              <div class="rateio-footer" :class="{'has-error': totalRateioError}">
                <div class="rateio-footer-info" v-if="totalRateioError">
                   <i class="bi bi-exclamation-triangle-fill"></i> {{ config.rateioFooterDiffLabel }}
                </div>
                <div class="rateio-footer-info" v-else>
                   <i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Divisão completa e equivalente ao total.
                </div>
                <div class="rateio-footer-total">
                   Total Dividido: <strong>{{ formatCurrency(totalRateioReal) }}</strong> / <span style="color:var(--ink-muted);">{{ formatCurrency(valorNum) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Sidebar Area -->
      <div class="sidebar-widgets">

        <!-- Dados do Pagamento/Recebimento Widget (Exibido quando já está paga/recebida) -->
        <div v-if="form[config.widgetPaidListKey] && form[config.widgetPaidListKey].length > 0" class="card premium-widget" style="margin-bottom:16px;" :class="config.widgetBorderClass">
          <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(25, 135, 84, 0.1);">
            <h3 style="display:flex; align-items:center; width:100%; margin:0; color: var(--success);">
              <i class="bi" :class="config.widgetPaidIcon" style="margin-right: 8px;"></i> {{ config.widgetPaidTitle }}
            </h3>
          </div>
          <div class="card-body" style="padding: 16px;">
            <div v-for="(item, idx) in form[config.widgetPaidListKey]" :key="item[config.widgetPaidIdKey]" :style="{ marginBottom: idx < form[config.widgetPaidListKey].length - 1 ? '12px' : '0', borderBottom: idx < form[config.widgetPaidListKey].length - 1 ? '1px dashed rgba(255,255,255,0.1)' : 'none', paddingBottom: idx < form[config.widgetPaidListKey].length - 1 ? '12px' : '0' }">
              <div style="font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 4px;">
                <i class="bi bi-calendar-check"></i> Data: <strong style="color: var(--text-main);">{{ formatDate(item[config.widgetPaidDateKey]) || item[config.widgetPaidDateKey] }}</strong>
              </div>
              <div style="font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 4px;">
                <i class="bi bi-cash"></i> Valor: <strong style="color: var(--text-main);">{{ formatCurrency(item.valor) }}</strong>
              </div>
              <div v-if="item.conta_nome || item.conta" style="font-size: 0.85rem; color: var(--ink-muted);">
                <i class="bi bi-bank"></i> Conta: <strong style="color: var(--text-main);">{{ item.conta_nome || item.conta }}</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Receber/Pagar ao Salvar Widget -->
        <div v-else class="card premium-widget" style="margin-bottom:16px;">
          <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
            <h3 style="display:flex; align-items:center; justify-content:space-between; width:100%; margin:0;">
              <span><i class="bi bi-lightning-charge"></i> {{ config.widgetUnpaidTitle }}</span>
              <div class="form-check form-switch" style="margin:0;">
                <input class="form-check-input" type="checkbox" :id="config.widgetUnpaidToggleId" v-model="immediateAction" style="cursor:pointer;">
              </div>
            </h3>
          </div>
          <div class="card-body" style="padding: 16px;">
            <p style="color: var(--ink-muted); font-size: 0.85rem; margin-bottom: 1rem; line-height: 1.4;">
              {{ config.widgetUnpaidDesc }}
            </p>

            <div v-if="immediateAction" class="animate-fade-in" style="background: rgba(0,0,0,0.1); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
              <div class="form-group" style="margin-bottom: 12px;">
                <label for="conta_id" class="required-field" style="font-size: 0.8rem;"><i class="bi bi-bank"></i> Conta Bancária</label>
                <select class="form-control" id="conta_id" v-model="form.conta_id" :class="{'is-invalid': errors.conta_id}" style="font-size: 0.85rem;">
                  <option value="">Selecione...</option>
                  <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">{{ c.conta }}</option>
                </select>
                <small class="text-danger" style="font-size: 11px;" v-if="errors.conta_id">Informe a conta.</small>
              </div>
              <div class="form-group">
                <label :for="config.widgetUnpaidDateId" class="required-field" style="font-size: 0.8rem;"><i class="bi bi-calendar-check"></i> {{ config.widgetUnpaidDateLabel }}</label>
                <input type="date" class="form-control" :id="config.widgetUnpaidDateId" v-model="form[config.widgetUnpaidDateId]" :class="{'is-invalid': errors[config.widgetUnpaidDateId]}" style="font-size: 0.85rem;">
                <small class="text-danger" style="font-size: 11px;" v-if="errors[config.widgetUnpaidDateId]">{{ config.widgetUnpaidDateErr }}</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Info Widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Metadados</h3>
          </div>
          <ul class="widget-list">
            <li v-if="isEdit" style="cursor:default; display:flex; justify-content:space-between; align-items:center;">
              <span class="process-date" style="font-size:.82rem;"><i class="bi bi-hash"></i> {{ config.metaIdLabel }}</span>
              <span class="process-num" style="font-size:.82rem;">#{{ transactionId }}</span>
            </li>
            <li v-if="isEdit" style="cursor:default; display:flex; justify-content:space-between; align-items:center;">
              <span class="process-date" style="font-size:.82rem;"><i class="bi bi-calendar-plus"></i> Inclusão</span>
              <span class="process-num" style="font-size:.82rem;">{{ formatDate(form.created_at) }}</span>
            </li>
            <li v-if="form.contrato_id" style="cursor:default; display:flex; justify-content:space-between; align-items:center;">
              <span class="process-date" style="font-size:.82rem;"><i class="bi bi-link"></i> Vínculo (Contrato)</span>
              <span class="process-num" style="font-size:.82rem; color:var(--accent);">#{{ form.contrato_id }}</span>
            </li>
            <li v-if="!isEdit" style="cursor:default; color:var(--ink-muted); font-size:0.85rem; padding: 12px 18px;">
              {{ config.metaUnsavedText }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { apiFetch, debounce, formatDate, formatCurrency, formatNumberBr } from '../../services/api'

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (v: string) => ['despesa', 'receita'].includes(v)
  },
  transactionId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['set-view', 'toast'])

const isEdit = computed(() => !!props.transactionId)
const saving = ref(false)

// Config based on despesa/receita
const config = computed(() => {
  if (props.type === 'despesa') {
    return {
      type: 'despesa',
      viewName: 'despesas',
      listLabel: 'Lista de Despesas',
      editLabel: 'Editar Despesa',
      newLabel: 'Nova Despesa',
      editSub: 'Modifique os valores e rateio da despesa',
      newSub: 'Cadastre uma nova despesa e defina o rateio',
      btnSaveLabel: 'Salvar Despesa',
      detailEndpoint: 'financeiro/despesas/detalhes',
      saveEndpoint: 'financeiro/despesas/salvar',
      favoriteType: 'despesa',
      favoritePlaceholder: 'Ex: Aluguel Mensal',

      cardIcon: 'bi-receipt',
      cardTitle: 'Dados da Despesa',
      descLabel: 'Descrição da Despesa',
      descPlaceholder: 'Ex: Aluguel, Internet, Compra de Equipamento',
      descField: 'despesa',

      partyLabel: 'Fornecedor',
      partyIcon: 'bi-truck',
      partyPlaceholder: 'Buscar fornecedor por nome ou CNPJ...',
      partyBuscaEndpoint: 'fornecedores/buscar',

      rateioLabel: 'Rateio entre Profissionais',
      rateioEmptyText: 'Nenhum rateio configurado. Esta despesa será cobrada integralmente de forma global, a menos que adicione sócios acima.',
      rateioFooterDiffLabel: 'O valor do rateio difere do valor total da despesa!',
      rateioToastDiffLabel: 'Valor do rateio difere do valor total da despesa!',

      categoryEmptyText: 'Nenhuma categoria selecionada. Adicione ao menos uma categoria para classificar esta despesa — se necessário, divida o valor entre mais de uma.',
      categoryFooterDiffLabel: 'A soma das categorias precisa ser igual ao valor total da despesa!',
      categoryToastDiffLabel: 'A soma das categorias precisa ser igual ao valor total da despesa!',

      widgetBorderClass: 'border-success',
      widgetPaidHeaderBg: 'rgba(25, 135, 84, 0.1)',
      widgetPaidTitle: 'Despesa Paga',
      widgetPaidIcon: 'bi-check-circle-fill',
      widgetPaidListKey: 'pagamentos',
      widgetPaidIdKey: 'id_pgto_despesa',
      widgetPaidDateKey: 'pagamento_despesa_dt',

      widgetUnpaidTitle: 'Pagar Imediatamente',
      widgetUnpaidDesc: 'Ative para registrar esta despesa como já paga no ato do salvamento. O sistema irá liquidar o valor automaticamente.',
      widgetUnpaidToggleId: 'togglePagar',
      widgetUnpaidDateLabel: 'Data do Pagamento',
      widgetUnpaidDateId: 'pagamento_dt',
      widgetUnpaidDateErr: 'Informe a data.',
      payloadPagarKey: 'pagarDespesa',

      metaIdLabel: 'ID Despesa',
      metaIdField: 'id_despesa',
      metaUnsavedText: 'Preencha os dados e clique em "Salvar Despesa" para registrar no sistema.'
    }
  } else {
    return {
      type: 'receita',
      viewName: 'receitas',
      listLabel: 'Lista de Receitas',
      editLabel: 'Editar Receita',
      newLabel: 'Nova Receita',
      editSub: 'Modifique os valores e repasses da receita',
      newSub: 'Cadastre uma nova receita e defina o repasse',
      btnSaveLabel: 'Salvar Receita',
      detailEndpoint: 'financeiro/receitas/detalhes',
      saveEndpoint: 'financeiro/receitas/salvar',
      favoriteType: 'receita',
      favoritePlaceholder: 'Ex: Mensalidade Cliente X',

      cardIcon: 'bi-cash-coin',
      cardTitle: 'Dados da Receita',
      descLabel: 'Descrição da Receita',
      descPlaceholder: 'Ex: Honorários Iniciais, Alvara, Consultoria',
      descField: 'receita',

      partyLabel: 'Cliente / Pagador',
      partyIcon: 'bi-person',
      partyPlaceholder: 'Buscar cliente por nome ou documento...',
      partyBuscaEndpoint: 'clientes/buscar',

      rateioLabel: 'Repasse entre Profissionais',
      rateioEmptyText: 'Nenhum repasse configurado. Esta receita será integralmente creditada ao caixa global, a menos que adicione sócios acima.',
      rateioFooterDiffLabel: 'O valor do repasse difere do valor total da receita!',
      rateioToastDiffLabel: 'Valor do repasse difere do valor total da receita!',

      categoryEmptyText: 'Nenhuma categoria selecionada. Você pode classificar esta receita em uma ou mais categorias, dividindo o valor entre elas.',
      categoryFooterDiffLabel: 'A soma das categorias precisa ser igual ao valor total da receita!',
      categoryToastDiffLabel: 'A soma das categorias precisa ser igual ao valor total da receita!',

      widgetBorderClass: 'border-success',
      widgetPaidHeaderBg: 'rgba(25, 135, 84, 0.1)',
      widgetPaidTitle: 'Receita Recebida',
      widgetPaidIcon: 'bi-check-circle-fill',
      widgetPaidListKey: 'recebimento',
      widgetPaidIdKey: 'id_pgto_receita',
      widgetPaidDateKey: 'pagamento_receita_dt',

      widgetUnpaidTitle: 'Receber Imediatamente',
      widgetUnpaidDesc: 'Ative para registrar esta receita como já recebida no ato do salvamento. O sistema irá liquidar o valor automaticamente.',
      widgetUnpaidToggleId: 'toggleReceber',
      widgetUnpaidDateLabel: 'Data do Recebimento',
      widgetUnpaidDateId: 'recebimento_dt',
      widgetUnpaidDateErr: 'Informe a data.',
      payloadPagarKey: 'receberReceita',

      metaIdLabel: 'ID Receita',
      metaIdField: 'id_receita',
      metaUnsavedText: 'Preencha os dados e clique em "Salvar Receita" para registrar no sistema.'
    }
  }
})

// Favoritos state
const showSaveFavoriteModal = ref(false)
const showLoadFavoriteModal = ref(false)
const favoriteTitle = ref('')
const favoritesList = ref([])

const categorias = ref([])
const usuarios = ref([])
const contas = ref([])

// Generic Form state
const form = reactive({
  id_despesa: null,
  id_receita: null,
  despesa: '',
  receita: '',
  valor: 0,
  vencimento_dt: '',
  categorias: [],
  fornecedor: '',
  cliente: '',
  cliente_id: '',
  contrato_id: '',
  comentario: '',
  rateio: [],
  conta_id: '',
  pagamentos: [],
  recebimento: [],
  pagamento_dt: new Date().toISOString().split('T')[0],
  recebimento_dt: new Date().toISOString().split('T')[0],
  created_at: null
})

const displayValor = ref('')
const immediateAction = ref(false)
const errors = reactive<Record<string, string>>({})

watch(immediateAction, (newVal) => {
  if (newVal && form.vencimento_dt) {
    if (props.type === 'despesa') {
      form.pagamento_dt = form.vencimento_dt
    } else {
      form.recebimento_dt = form.vencimento_dt
    }
  }
})

watch(() => form.vencimento_dt, (newVal) => {
  if (immediateAction.value && newVal) {
    if (props.type === 'despesa') {
      form.pagamento_dt = newVal
    } else {
      form.recebimento_dt = newVal
    }
  }
})

// Autocomplete Party (Fornecedor or Cliente)
const partyBusca = ref('')
const partyOptions = ref([])
const showParty = ref(false)

// Autocomplete Contrato
const contratoBusca = ref('')
const contratoOptions = ref([])
const showContratos = ref(false)

// Init fetch
onMounted(async () => {
  const hash = window.location.hash
  if (props.type === 'despesa' && hash.includes('?pagar=1')) {
    immediateAction.value = true
  } else if (props.type === 'receita' && hash.includes('?receber=1')) {
    immediateAction.value = true
  }

  // Load select options concurrently
  await Promise.all([
    fetchCategorias(),
    fetchUsuarios(),
    fetchContas()
  ])

  if (isEdit.value) {
    loadTransaction()
  }

  document.addEventListener('click', closeAutocompleteOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeAutocompleteOutside)
})

function closeAutocompleteOutside(e) {
  if (!e.target.closest('.position-relative')) {
    showParty.value = false
    showContratos.value = false
  }
}

async function fetchCategorias() {
  try {
    const res = await apiFetch('financeiro/categorias/listar')
    if (res && res.success) categorias.value = res.data || []
  } catch(e) { console.error('Erro categorias:', e) }
}

async function fetchUsuarios() {
  try {
    const res = await apiFetch('usuarios/participa-rateio')
    if (res) {
      usuarios.value = res.results || res.data || (Array.isArray(res) ? res : [])
    }
  } catch(e) { console.error('Erro usuarios:', e) }
}

async function fetchContas() {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    if (res && res.success) contas.value = res.data || []
  } catch(e) { console.error('Erro contas:', e) }
}

async function loadTransaction() {
  try {
    const res = await apiFetch(`${config.value.detailEndpoint}/${props.transactionId}`)
    if (res && res.success && res.data) {
      Object.assign(form, res.data)
      displayValor.value = formatNumberBr(form.valor)

      // Load Party (Fornecedor / Cliente)
      if (props.type === 'despesa') {
        if (res.data.nome_fornecedor || res.data.cliente_fornecedor) {
          partyBusca.value = res.data.nome_fornecedor || res.data.cliente_fornecedor
        }
      } else {
        if (res.data.nome_cliente || res.data.cliente_pagador) {
          partyBusca.value = res.data.nome_cliente || res.data.cliente_pagador
        }
      }

      if (res.data.contrato_nome || res.data.numero_contrato) {
        contratoBusca.value = res.data.contrato_nome || res.data.numero_contrato
      }


      if (!form.rateio) {
        form.rateio = []
      } else if (typeof form.rateio === 'string') {
        try {
          form.rateio = JSON.parse(form.rateio) || []
        } catch(e) {
          console.error('Erro ao processar rateio:', e)
          form.rateio = []
        }
      }

      form.rateio.forEach(r => {
        if (r.id) {
          r.id = String(r.id)
          if (!usuarios.value.some(u => String(u.id) === r.id)) {
            usuarios.value.push({ id: r.id, text: `Sócio #${r.id} (Inativo/Ausente)` })
          }
        }
        const val = parseFloat(r.valor)
        if (!isNaN(val)) {
           r.valor = formatNumberBr(val)
        }
      })

      form.categorias = (Array.isArray(res.data.categorias) ? res.data.categorias : []).map(c => ({
        categoria_id: c.categoria_id,
        valor: formatNumberBr(parseFloat(c.valor) || 0)
      }))
    }
  } catch(err) {
    console.error(err)
    emit('toast', { msg: `Erro ao carregar ${props.type === 'despesa' ? 'despesa' : 'receita'}`, type: 'danger' })
  }
}

function parseNumberBr(str) {
  if (!str) return 0
  const clean = String(str).replace(/[^\d,-]/g, '').replace(',', '.')
  return parseFloat(clean) || 0
}

const valorNum = computed(() => parseNumberBr(displayValor.value))

function formatDisplayValor() {
  form.valor = valorNum.value
  displayValor.value = formatNumberBr(form.valor)
}

function formatRateioValor(r) {
  r.valor = formatNumberBr(parseNumberBr(r.valor))
}

const totalRateioReal = computed(() => {
  return form.rateio.reduce((acc, r) => acc + parseNumberBr(r.valor), 0)
})

const totalRateioError = computed(() => {
  if (form.rateio.length === 0) return false
  const diff = Math.abs(totalRateioReal.value - valorNum.value)
  return diff > 0.05
})

function formatCategoriaValor(c) {
  c.valor = formatNumberBr(parseNumberBr(c.valor))
}

const totalCategoriasReal = computed(() => {
  return form.categorias.reduce((acc, c) => acc + parseNumberBr(c.valor), 0)
})

const totalCategoriasError = computed(() => {
  if (form.categorias.length === 0) return false
  const diff = Math.abs(totalCategoriasReal.value - valorNum.value)
  return diff > 0.05
})

function addCategoriaRow() {
  form.categorias.push({ categoria_id: '', valor: '' })
  dividirCategoriasIgualmente()
}

function removeCategoriaRow(index) {
  form.categorias.splice(index, 1)
  dividirCategoriasIgualmente()
}

function dividirCategoriasIgualmente() {
  if (!form.categorias.length || !valorNum.value) return
  const part = valorNum.value / form.categorias.length
  form.categorias.forEach(c => {
    c.valor = formatNumberBr(part)
  })
}

// Autocomplete Party (Fornecedor or Cliente)
const onPartyInput = debounce(async () => {
  if (!partyBusca.value || partyBusca.value.length < 2) {
    partyOptions.value = []
    showParty.value = false
    return
  }
  try {
    const res = await apiFetch(`${config.value.partyBuscaEndpoint}?term=${encodeURIComponent(partyBusca.value)}`)
    if (res && res.success) {
      partyOptions.value = res.data
      showParty.value = true
    }
  } catch (err) {
    console.error(err)
  }
}, 300)

function selectParty(p) {
  if (props.type === 'despesa') {
    form.fornecedor = p.id || p.id_fornecedor
  } else {
    form.cliente = p.id_cliente || p.id
    form.cliente_id = p.id_cliente || p.id
  }
  partyBusca.value = p.text || p.nome || p.cliente
  showParty.value = false
}

function clearParty() {
  if (props.type === 'despesa') {
    form.fornecedor = ''
  } else {
    form.cliente = ''
    form.cliente_id = ''
  }
  partyBusca.value = ''
  partyOptions.value = []
  showParty.value = false
}

// ---- Autocomplete Contratos ----
const onContratoInput = debounce(async () => {
  if (!contratoBusca.value || contratoBusca.value.length < 2) {
    contratoOptions.value = []
    showContratos.value = false
    return
  }
  try {
    const res = await apiFetch(`contratos/listar?s=${contratoBusca.value}`)
    if (res && res.success) {
      contratoOptions.value = res.data || []
      showContratos.value = true
    }
  } catch(e) { console.error('Erro buscar contrato:', e) }
}, 300)

function selectContrato(c) {
  form.contrato_id = c.id_contrato
  contratoBusca.value = c.titulo || c.numero_contrato || c.numero
  showContratos.value = false
}

function clearContrato() {
  form.contrato_id = ''
  contratoBusca.value = ''
  contratoOptions.value = []
}

function addRateioRow() {
  form.rateio.push({ id: '', valor: '' })
  dividirIgualmente()
}

function removeRateioRow(index) {
  form.rateio.splice(index, 1)
  dividirIgualmente()
}

function dividirIgualmente() {
  if (!form.rateio.length || !valorNum.value) return
  const part = valorNum.value / form.rateio.length
  form.rateio.forEach(r => {
    r.valor = formatNumberBr(part)
  })
}

function recalcRateioAutomatico() {
  // only manual button triggers recalculation
}

function validate() {
  Object.keys(errors).forEach(k => delete errors[k])
  let valid = true

  const descField = config.value.descField
  if (!form[descField]) { errors[descField] = 'Descrição obrigatória.'; valid = false }
  if (!valorNum.value) { errors.valor = 'Valor inválido ou zerado.'; valid = false }
  if (!form.vencimento_dt) { errors.vencimento_dt = 'Vencimento obrigatório.'; valid = false }

  if (immediateAction.value) {
    const dateField = config.value.widgetUnpaidDateId
    if (!form.conta_id) { errors.conta_id = 'Informe a conta bancária.'; valid = false }
    if (!form[dateField]) { errors[dateField] = config.value.widgetUnpaidDateErr; valid = false }
  }

  if (totalRateioError.value) {
    emit('toast', { msg: config.value.rateioToastDiffLabel, type: 'danger' })
    valid = false
  }

  const categoriasPreenchidas = form.categorias.filter(c => c.categoria_id)

  if (props.type === 'despesa') {
    if (categoriasPreenchidas.length === 0) {
      errors.categorias = 'Selecione ao menos uma categoria.'
      valid = false
    } else if (totalCategoriasError.value) {
      emit('toast', { msg: config.value.categoryToastDiffLabel, type: 'danger' })
      valid = false
    }
  } else {
    if (totalCategoriasError.value) {
      emit('toast', { msg: config.value.categoryToastDiffLabel, type: 'danger' })
      valid = false
    }
  }

  return valid
}

async function saveTransaction() {
  if (!validate()) return

  saving.value = true
  try {
    const payload = { ...form }
    payload.valor = valorNum.value
    payload[config.value.payloadPagarKey] = immediateAction.value ? '1' : '0'

    const cleanRateio = form.rateio.filter(r => r.id).map(r => ({
      id: r.id,
      valor: parseNumberBr(r.valor)
    }))
    payload.rateio = cleanRateio

    payload.categorias = form.categorias
      .filter(c => c.categoria_id)
      .map(c => ({
        categoria_id: c.categoria_id,
        valor: parseNumberBr(c.valor)
      }))

    const res = await apiFetch(config.value.saveEndpoint, {
      method: 'POST',
      body: payload
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || `${props.type === 'despesa' ? 'Despesa' : 'Receita'} salva com sucesso`, type: 'success' })
      window.dispatchEvent(new Event('sync-global-stats'))
      emit('set-view', config.value.viewName)
    } else {
      throw new Error(res.message || 'Erro ao salvar')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha na comunicação com o servidor', type: 'danger' })
  } finally {
    saving.value = false
  }
}

// Favoritos logic
async function fetchFavorites() {
  try {
    const res = await apiFetch(`financeiro/favoritos?tipo=${config.value.favoriteType}`)
    favoritesList.value = res.data || []
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar favoritos', type: 'danger' })
  }
}

function openSaveFavoriteModal() {
  favoriteTitle.value = ''
  showSaveFavoriteModal.value = true
}

async function openLoadFavoriteModal() {
  await fetchFavorites()
  showLoadFavoriteModal.value = true
}

async function saveFavorite() {
  if (!favoriteTitle.value) {
    emit('toast', { msg: 'O título do favorito é obrigatório.', type: 'danger' })
    return
  }
  try {
    const cleanRateio = form.rateio.filter(r => r.id).map(r => ({
      id: r.id,
      valor: parseNumberBr(r.valor)
    }))

    const primeiraCategoria = form.categorias.find(c => c.categoria_id)

    const payload: Record<string, any> = {
      titulo: favoriteTitle.value,
      tipo: config.value.favoriteType,
      valor: valorNum.value || 0,
      categoria_id: primeiraCategoria ? primeiraCategoria.categoria_id : null,
      comentario: form.comentario || '',
      rateio: cleanRateio
    }

    if (props.type === 'despesa') {
      payload.fornecedor_id = form.fornecedor || null
    } else {
      payload.cliente_id = form.cliente_id || null
    }

    await apiFetch('financeiro/favoritos', {
      method: 'POST',
      body: payload
    })
    emit('toast', { msg: 'Favorito salvo com sucesso!', type: 'success' })
    showSaveFavoriteModal.value = false
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao salvar favorito', type: 'danger' })
  }
}

function loadFavorite(fav) {
  if (props.type === 'despesa') {
    form.despesa = fav.titulo
  } else {
    form.receita = fav.titulo
  }

  if (fav.valor) {
    displayValor.value = formatNumberBr(fav.valor)
  }
  if (fav.categoria_id) {
    form.categorias = [{ categoria_id: fav.categoria_id, valor: displayValor.value }]
  }

  if (props.type === 'despesa') {
    if (fav.fornecedor_id) {
      form.fornecedor = fav.fornecedor_id
    }
  } else {
    if (fav.cliente_id) {
      form.cliente_id = fav.cliente_id
      form.cliente = fav.cliente_id
    }
  }

  if (fav.comentario) form.comentario = fav.comentario

  if (fav.rateio && Array.isArray(fav.rateio)) {
    form.rateio = fav.rateio.map(r => ({
      id: r.id,
      valor: formatNumberBr(r.valor)
    }))
  }

  showLoadFavoriteModal.value = false
  emit('toast', { msg: 'Favorito carregado!', type: 'success' })
}

async function deleteFavorite(id) {
  if (!confirm('Deseja excluir este favorito?')) return
  try {
    await apiFetch('financeiro/favoritos/' + id, { method: 'DELETE' })
    emit('toast', { msg: 'Favorito excluído', type: 'success' })
    await fetchFavorites()
  } catch (err) {
    emit('toast', { msg: 'Erro ao excluir favorito', type: 'danger' })
  }
}

</script>

<style scoped>
/* Grid Framework */
.form-card {
  border-radius: 8px;
}
.form-row {
  margin-bottom: 1.25rem;
}
.form-row.col-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}
.form-row.col-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}
.form-row.col-1 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.required-field::after {
  content: ' *';
  color: var(--danger);
  font-weight: bold;
}

/* Rateio Module */
.rateio-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.rateio-item {
  display: flex;
  gap: 12px;
  align-items: center;
  background: rgba(255, 255, 255, 0.02);
  padding: 10px 14px;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}
.rateio-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  padding: 12px 14px;
  background: rgba(255,255,255,0.03);
  border-radius: 6px;
  font-size: 0.9rem;
  border: 1px solid rgba(255,255,255,0.05);
}
.rateio-footer.has-error {
  border-color: var(--danger);
  background: rgba(220, 38, 38, 0.05);
}
.rateio-footer-info {
  color: var(--danger);
  font-weight: 500;
}
.rateio-footer-info .bi-check-circle-fill {
  color: var(--success);
}
.rateio-footer-total {
  font-size: 1rem;
}

/* Premium Autocomplete */
.autocomplete-list {
  list-style: none;
  padding: 0;
  margin: 0;
  border-radius: 6px;
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid rgba(255,255,255,0.1);
  top: calc(100% + 4px);
}
.autocomplete-list li {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  cursor: pointer;
  transition: background 0.2s ease;
}
.autocomplete-list li:last-child {
  border-bottom: none;
}
.autocomplete-list li:hover {
  background-color: rgba(255,255,255,0.08);
}
.autocomplete-item-title {
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 4px;
}
.autocomplete-item-sub {
  color: var(--ink-muted);
  font-size: 0.8rem;
}

.input-group {
  display: flex;
  position: relative;
  width: 100%;
}

.input-group-addon {
  background: var(--surface);
  border: 1px solid var(--border);
  border-right: none;
  padding: 8px 12px;
  font-size: 0.85rem;
  color: var(--ink-dim);
  border-radius: 8px 0 0 8px;
  display: flex;
  align-items: center;
  font-weight: 600;
}

.input-group-addon+.form-control {
  border-radius: 0 8px 8px 0;
}

.favorite-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  color: var(--ink);
  transition: background .12s;
}
.favorite-item + .favorite-item { border-top: 1px solid var(--border); }
.favorite-item:hover { background: var(--primary-lt); }
.favorite-title { display: flex; align-items: center; gap: 8px; font-weight: 500; }
.favorite-title i { color: var(--accent); }
</style>
