<template>
  <aside :class="['sidebar', { open: sidebarOpen }]">
    <div class="sidebar-brand">
      <div class="logo-mark">
        <img src="../../assets/logo.png" alt="Saúde" class="logo-image" aria-label="Logo Saúde">
      </div>
      <span class="brand-version">Saúde</span>
    </div>

    <template v-if="algumaLiberada(['dashboard', 'crm', 'clientes', 'contratos', 'documentacao'])">
      <div class="sidebar-section" role="heading" aria-level="2">
        <button
          type="button"
          class="sidebar-section-toggle"
          :aria-expanded="secaoAberta('principal')"
          aria-controls="sidebar-secao-principal"
          @click="alternarSecao('principal')"
        >
          Principal
          <span class="sidebar-section-extra">
            <template v-if="!secaoAberta('principal')">
              <span
                v-for="c in contadoresSecao('principal')"
                :key="c.tela"
                class="sidebar-section-badge"
                :style="{ background: c.cor }"
                :title="`${c.rotulo}: ${c.valor}`"
                :aria-label="`${c.rotulo}: ${c.valor}`"
              >{{ c.valor }}</span>
            </template>
            <i :class="['bi', 'bi-chevron-down', 'sidebar-section-chevron', { fechada: !secaoAberta('principal') }]" aria-hidden="true"></i>
          </span>
        </button>
      </div>
      <ul id="sidebar-secao-principal" class="sidebar-nav" v-show="secaoAberta('principal')" :ref="(el) => registrarLista('principal', el)">
        <li v-if="telaLiberada('dashboard')">
          <a
            :class="{ active: currentView === 'dashboard' }"
            @click="$emit('set-view', 'dashboard')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'dashboard' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'dashboard')"
            @keydown.space.prevent="$emit('set-view', 'dashboard')"
          >
            <i class="bi bi-speedometer2 nav-icon"></i> Dashboard
          </a>
        </li>
        <li v-if="telaLiberada('crm')">
          <a
            :class="{ active: currentView === 'crm' }"
            @click="$emit('set-view', 'crm')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'crm' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'crm')"
            @keydown.space.prevent="$emit('set-view', 'crm')"
          >
            <i class="bi bi-funnel nav-icon"></i> CRM / Negócios
          </a>
        </li>
        <li v-if="telaLiberada('clientes')">
          <a
            :class="{ active: currentView === 'clientes' || currentView === 'cliente-detail' }"
            @click="$emit('set-view', 'clientes')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'clientes' || currentView === 'cliente-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'clientes')"
            @keydown.space.prevent="$emit('set-view', 'clientes')"
          >
            <i class="bi bi-people nav-icon"></i> Clientes
          </a>
        </li>
        <li v-if="telaLiberada('contratos')">
          <a
            :class="{ active: currentView === 'contratos' || currentView === 'contrato-detail' }"
            @click="$emit('set-view', 'contratos')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'contratos' || currentView === 'contrato-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contratos')"
            @keydown.space.prevent="$emit('set-view', 'contratos')"
          >
            <i class="bi bi-file-earmark-text nav-icon"></i> Contratos
          </a>
        </li>
        <li v-if="telaLiberada('documentacao')">
          <a
            :class="{ active: currentView === 'documentacao' }"
            @click="$emit('set-view', 'documentacao')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'documentacao' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'documentacao')"
            @keydown.space.prevent="$emit('set-view', 'documentacao')"
          >
            <i class="bi bi-book nav-icon"></i> Documentação
          </a>
        </li>
      </ul>
    </template>

    <template v-if="algumaLiberada(['extrato', 'contas', 'extrato-importacao', 'extrato-movimentos', 'balanco', 'dre', 'previsao-fluxo-caixa', 'receitas', 'receitas-recebidas', 'despesas', 'despesas-pagas', 'transferencias', 'fechamento-mes'])">
      <div class="sidebar-section" role="heading" aria-level="2">
        <button
          type="button"
          class="sidebar-section-toggle"
          :aria-expanded="secaoAberta('financeiro')"
          aria-controls="sidebar-secao-financeiro"
          @click="alternarSecao('financeiro')"
        >
          Financeiro
          <span class="sidebar-section-extra">
            <template v-if="!secaoAberta('financeiro')">
              <span
                v-for="c in contadoresSecao('financeiro')"
                :key="c.tela"
                class="sidebar-section-badge"
                :style="{ background: c.cor }"
                :title="`${c.rotulo}: ${c.valor}`"
                :aria-label="`${c.rotulo}: ${c.valor}`"
              >{{ c.valor }}</span>
            </template>
            <i :class="['bi', 'bi-chevron-down', 'sidebar-section-chevron', { fechada: !secaoAberta('financeiro') }]" aria-hidden="true"></i>
          </span>
        </button>
      </div>
      <ul id="sidebar-secao-financeiro" class="sidebar-nav" v-show="secaoAberta('financeiro')" :ref="(el) => registrarLista('financeiro', el)">
        <li v-if="telaLiberada('extrato')">
          <a
            :class="{ active: currentView === 'extrato' }"
            @click="$emit('set-view', 'extrato')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'extrato' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'extrato')"
            @keydown.space.prevent="$emit('set-view', 'extrato')"
          >
            <i class="bi bi-wallet2 nav-icon"></i> Extrato
          </a>
        </li>
        <li v-if="telaLiberada('contas')">
          <a
            :class="{ active: currentView === 'contas' }"
            @click="$emit('set-view', 'contas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'contas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contas')"
            @keydown.space.prevent="$emit('set-view', 'contas')"
          >
            <i class="bi bi-bank nav-icon"></i> Contas Bancárias
          </a>
        </li>
        <li v-if="telaLiberada('extrato-importacao')">
          <a
            :class="{ active: currentView === 'extrato-importacao' }"
            @click="$emit('set-view', 'extrato-importacao')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'extrato-importacao' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'extrato-importacao')"
            @keydown.space.prevent="$emit('set-view', 'extrato-importacao')"
          >
            <i class="bi bi-file-earmark-arrow-up nav-icon"></i> Importar Extrato
          </a>
        </li>
        <li v-if="telaLiberada('extrato-movimentos')">
          <a
            :class="{ active: currentView === 'extrato-movimentos' }"
            @click="$emit('set-view', 'extrato-movimentos')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'extrato-movimentos' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'extrato-movimentos')"
            @keydown.space.prevent="$emit('set-view', 'extrato-movimentos')"
          >
            <i class="bi bi-bank nav-icon"></i> Movimentos do Extrato
          </a>
        </li>
        
        <li v-if="telaLiberada('balanco')">
          <a
            :class="{ active: currentView === 'balanco' }"
            @click="$emit('set-view', 'balanco')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'balanco' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'balanco')"
            @keydown.space.prevent="$emit('set-view', 'balanco')"
          >
            <i class="bi bi-bar-chart nav-icon"></i> Balanço
          </a>
        </li>
        <li v-if="telaLiberada('dre')">
          <a
            :class="{ active: currentView === 'dre' }"
            @click="$emit('set-view', 'dre')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'dre' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'dre')"
            @keydown.space.prevent="$emit('set-view', 'dre')"
          >
            <i class="bi bi-bar-chart-line nav-icon"></i> DRE
          </a>
        </li>
        <li v-if="telaLiberada('previsao-fluxo-caixa')">
          <a
            :class="{ active: currentView === 'previsao-fluxo-caixa' }"
            @click="$emit('set-view', 'previsao-fluxo-caixa')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'previsao-fluxo-caixa' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'previsao-fluxo-caixa')"
            @keydown.space.prevent="$emit('set-view', 'previsao-fluxo-caixa')"
          >
            <i class="bi bi-graph-up-arrow nav-icon"></i> Previsão de Fluxo de Caixa
          </a>
        </li>

        <li v-if="telaLiberada('receitas')">
          <a
            :class="{ active: currentView === 'receitas' || currentView === 'receita-detail' }"
            @click="$emit('set-view', 'receitas')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'receitas' || currentView === 'receita-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'receitas')"
            @keydown.space.prevent="$emit('set-view', 'receitas')"
          >
            <i class="bi bi-cash-coin nav-icon"></i> Receitas a Receber
            <span :class="['nav-badge', { 'badge-pulse': pulseRevenues }]" style="background:var(--success);" v-if="globalStats.qteReceitasNaoRecebidas > 0">{{ globalStats.qteReceitasNaoRecebidas }}</span>
          </a>
        </li>
        <li v-if="telaLiberada('receitas-recebidas')">
          <a
            :class="{ active: currentView === 'receitas-recebidas' }"
            @click="$emit('set-view', 'receitas-recebidas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'receitas-recebidas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'receitas-recebidas')"
            @keydown.space.prevent="$emit('set-view', 'receitas-recebidas')"
          >
            <i class="bi bi-piggy-bank nav-icon"></i> Receitas Recebidas
          </a>
        </li>
        <li v-if="telaLiberada('despesas')">
          <a
            :class="{ active: currentView === 'despesas' || currentView === 'despesa-detail' }"
            @click="$emit('set-view', 'despesas')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'despesas' || currentView === 'despesa-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'despesas')"
            @keydown.space.prevent="$emit('set-view', 'despesas')"
          >
            <i class="bi bi-arrow-up-circle nav-icon"></i> Despesas a Pagar
            <span :class="['nav-badge', { 'badge-pulse': pulseExpenses }]" style="background:var(--warning);" v-if="globalStats.qteDespesasNaoPagas > 0">{{ globalStats.qteDespesasNaoPagas }}</span>
          </a>
        </li>
        <li v-if="telaLiberada('despesas-pagas')">
          <a
            :class="{ active: currentView === 'despesas-pagas' }"
            @click="$emit('set-view', 'despesas-pagas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'despesas-pagas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'despesas-pagas')"
            @keydown.space.prevent="$emit('set-view', 'despesas-pagas')"
          >
            <i class="bi bi-check2-circle nav-icon"></i> Despesas Pagas
          </a>
        </li>
        <li v-if="telaLiberada('transferencias')">
          <a
            :class="{ active: currentView === 'transferencias' || currentView === 'transferencia-detail' }"
            @click="$emit('set-view', 'transferencias')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'transferencias' || currentView === 'transferencia-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'transferencias')"
            @keydown.space.prevent="$emit('set-view', 'transferencias')"
          >
            <i class="bi bi-arrow-left-right nav-icon"></i> Transferências
          </a>
        </li>
        <li v-if="telaLiberada('fechamento-mes')">
          <a
            :class="{ active: currentView === 'fechamento-mes' }"
            @click="$emit('set-view', 'fechamento-mes')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'fechamento-mes' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'fechamento-mes')"
            @keydown.space.prevent="$emit('set-view', 'fechamento-mes')"
          >
            <i class="bi bi-lock nav-icon"></i> Fechamento do Mês
          </a>
        </li>

      </ul>
    </template>

    <template v-if="algumaLiberada(['contabil', 'contabil-importar', 'contabil-lancamentos', 'contabil-plano-contas', 'contabil-relatorios', 'contabil-periodos'])">
      <div class="sidebar-section" role="heading" aria-level="2">
        <button
          type="button"
          class="sidebar-section-toggle"
          :aria-expanded="secaoAberta('contabil')"
          aria-controls="sidebar-secao-contabil"
          @click="alternarSecao('contabil')"
        >
          Contábil
          <span class="sidebar-section-extra">
            <template v-if="!secaoAberta('contabil')">
              <span
                v-for="c in contadoresSecao('contabil')"
                :key="c.tela"
                class="sidebar-section-badge"
                :style="{ background: c.cor }"
                :title="`${c.rotulo}: ${c.valor}`"
                :aria-label="`${c.rotulo}: ${c.valor}`"
              >{{ c.valor }}</span>
            </template>
            <i :class="['bi', 'bi-chevron-down', 'sidebar-section-chevron', { fechada: !secaoAberta('contabil') }]" aria-hidden="true"></i>
          </span>
        </button>
      </div>
      <ul id="sidebar-secao-contabil" class="sidebar-nav" v-show="secaoAberta('contabil')" :ref="(el) => registrarLista('contabil', el)">
        <li v-if="telaLiberada('contabil-importar')">
          <a
            :class="{ active: currentView === 'contabil' || currentView === 'contabil-importar' }"
            @click="$emit('set-view', 'contabil-importar')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'contabil' || currentView === 'contabil-importar') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contabil-importar')"
            @keydown.space.prevent="$emit('set-view', 'contabil-importar')"
          >
            <i class="bi bi-box-arrow-in-down nav-icon"></i> Importar do Financeiro
          </a>
        </li>
        <li v-if="telaLiberada('contabil-lancamentos')">
          <a
            :class="{ active: currentView === 'contabil-lancamentos' }"
            @click="$emit('set-view', 'contabil-lancamentos')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'contabil-lancamentos' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contabil-lancamentos')"
            @keydown.space.prevent="$emit('set-view', 'contabil-lancamentos')"
          >
            <i class="bi bi-journal-text nav-icon"></i> Lançamentos (Diário)
          </a>
        </li>
        <li v-if="telaLiberada('contabil-plano-contas')">
          <a
            :class="{ active: currentView === 'contabil-plano-contas' }"
            @click="$emit('set-view', 'contabil-plano-contas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'contabil-plano-contas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contabil-plano-contas')"
            @keydown.space.prevent="$emit('set-view', 'contabil-plano-contas')"
          >
            <i class="bi bi-diagram-3 nav-icon"></i> Plano de Contas
          </a>
        </li>
        <li v-if="telaLiberada('contabil-relatorios')">
          <a
            :class="{ active: currentView === 'contabil-relatorios' }"
            @click="$emit('set-view', 'contabil-relatorios')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'contabil-relatorios' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contabil-relatorios')"
            @keydown.space.prevent="$emit('set-view', 'contabil-relatorios')"
          >
            <i class="bi bi-file-earmark-spreadsheet nav-icon"></i> Livros & Relatórios
          </a>
        </li>
        <li v-if="telaLiberada('contabil-periodos')">
          <a
            :class="{ active: currentView === 'contabil-periodos' }"
            @click="$emit('set-view', 'contabil-periodos')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'contabil-periodos' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'contabil-periodos')"
            @keydown.space.prevent="$emit('set-view', 'contabil-periodos')"
          >
            <i class="bi bi-calendar2-check nav-icon"></i> Exercícios Fiscais
          </a>
        </li>
      </ul>
    </template>

    <template v-if="algumaLiberada(['tarefas', 'fornecedores', 'business-canvas', 'configuracoes'])">
      <div class="sidebar-section" role="heading" aria-level="2">
        <button
          type="button"
          class="sidebar-section-toggle"
          :aria-expanded="secaoAberta('gestao')"
          aria-controls="sidebar-secao-gestao"
          @click="alternarSecao('gestao')"
        >
          Gestão
          <span class="sidebar-section-extra">
            <template v-if="!secaoAberta('gestao')">
              <span
                v-for="c in contadoresSecao('gestao')"
                :key="c.tela"
                class="sidebar-section-badge"
                :style="{ background: c.cor }"
                :title="`${c.rotulo}: ${c.valor}`"
                :aria-label="`${c.rotulo}: ${c.valor}`"
              >{{ c.valor }}</span>
            </template>
            <i :class="['bi', 'bi-chevron-down', 'sidebar-section-chevron', { fechada: !secaoAberta('gestao') }]" aria-hidden="true"></i>
          </span>
        </button>
      </div>
      <ul id="sidebar-secao-gestao" class="sidebar-nav" v-show="secaoAberta('gestao')" :ref="(el) => registrarLista('gestao', el)">
        
        <li v-if="telaLiberada('tarefas')">
          <a
            :class="{ active: currentView === 'tarefas' }"
            @click="$emit('set-view', 'tarefas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'tarefas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'tarefas')"
            @keydown.space.prevent="$emit('set-view', 'tarefas')"
          >
            <i class="bi bi-check2-square nav-icon"></i> Tarefas
            <span :class="['nav-badge', { 'badge-pulse': pulseTasks }]" style="background:var(--danger);" v-if="globalStats.qteTarefas > 0">{{ globalStats.qteTarefas }}</span>
          </a>
        </li>
        <li v-if="telaLiberada('fornecedores')">
          <a
            :class="{ active: currentView === 'fornecedores' || currentView === 'fornecedor-detail' }"
            @click="$emit('set-view', 'fornecedores')"
            role="button"
            tabindex="0"
            :aria-current="(currentView === 'fornecedores' || currentView === 'fornecedor-detail') ? 'page' : null"
            @keydown.enter="$emit('set-view', 'fornecedores')"
            @keydown.space.prevent="$emit('set-view', 'fornecedores')"
          >
            <i class="bi bi-truck nav-icon"></i> Fornecedores
          </a>
        </li>
        <li v-if="telaLiberada('business-canvas')">
          <a
            :class="{ active: currentView === 'business-canvas' }"
            @click="$emit('set-view', 'business-canvas')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'business-canvas' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'business-canvas')"
            @keydown.space.prevent="$emit('set-view', 'business-canvas')"
          >
            <i class="bi bi-grid-1x2 nav-icon"></i> Business Canvas
          </a>
        </li>
        <li v-if="telaLiberada('configuracoes')">
          <a
            :class="{ active: currentView === 'configuracoes' }"
            @click="$emit('set-view', 'configuracoes')"
            role="button"
            tabindex="0"
            :aria-current="currentView === 'configuracoes' ? 'page' : null"
            @keydown.enter="$emit('set-view', 'configuracoes')"
            @keydown.space.prevent="$emit('set-view', 'configuracoes')"
          >
            <i class="bi bi-gear nav-icon"></i> Configurações
          </a>
        </li>
      </ul>
    </template>

    <template v-if="algumaLiberada(['noticias', ...TELAS_SITE.map((t) => t.view)])">
      <div class="sidebar-section" role="heading" aria-level="2">
        <button
          type="button"
          class="sidebar-section-toggle"
          :aria-expanded="secaoAberta('site')"
          aria-controls="sidebar-secao-site"
          @click="alternarSecao('site')"
        >
          Site
          <span class="sidebar-section-extra">
            <template v-if="!secaoAberta('site')">
              <span
                v-for="c in contadoresSecao('site')"
                :key="c.tela"
                class="sidebar-section-badge"
                :style="{ background: c.cor }"
                :title="`${c.rotulo}: ${c.valor}`"
                :aria-label="`${c.rotulo}: ${c.valor}`"
              >{{ c.valor }}</span>
            </template>
            <i :class="['bi', 'bi-chevron-down', 'sidebar-section-chevron', { fechada: !secaoAberta('site') }]" aria-hidden="true"></i>
          </span>
        </button>
      </div>
      <ul id="sidebar-secao-site" class="sidebar-nav" v-show="secaoAberta('site')" :ref="(el) => registrarLista('site', el)">
        <li v-if="telaLiberada('noticias')">
          <a
            :class="{ active: ['noticias', 'noticias-form', 'noticias-categorias'].includes(currentView) }"
            @click="$emit('set-view', 'noticias')"
            role="button"
            tabindex="0"
            :aria-current="['noticias', 'noticias-form', 'noticias-categorias'].includes(currentView) ? 'page' : null"
            @keydown.enter="$emit('set-view', 'noticias')"
            @keydown.space.prevent="$emit('set-view', 'noticias')"
          >
            <i class="bi bi-newspaper nav-icon"></i> Notícias (Site)
          </a>
        </li>
        <li v-for="tela in TELAS_SITE" :key="tela.view" v-show="telaLiberada(tela.view)">
          <a
            :class="{ active: currentView === tela.view || (tela.view === 'site-paginas' && currentView === 'site-pagina-editor') }"
            @click="$emit('set-view', tela.view)"
            role="button"
            tabindex="0"
            :aria-current="(currentView === tela.view || (tela.view === 'site-paginas' && currentView === 'site-pagina-editor')) ? 'page' : null"
            @keydown.enter="$emit('set-view', tela.view)"
            @keydown.space.prevent="$emit('set-view', tela.view)"
          >
            <i :class="['bi', tela.icone, 'nav-icon']"></i> {{ tela.rotulo }}
          </a>
        </li>
      </ul>
    </template>
  </aside>
</template>

<script setup lang="ts">
/**
 * @version 1.10.0
 */
import { onMounted, ref, watch } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import { telaLiberada } from '../../utils/acessoTelas'

/** Seção do menu só aparece se ao menos uma das telas estiver liberada. */
const algumaLiberada = (telas: string[]) => telas.some(telaLiberada)

/**
 * Acordeon das seções do menu. Guarda as seções FECHADAS no sessionStorage,
 * assim uma seção nova (ou uma sessão nova) começa aberta.
 */
const CHAVE_SECOES_FECHADAS = 'saude_sidebar_secoes_fechadas'

function lerSecoesFechadas(): string[] {
  try {
    const salvo = JSON.parse(sessionStorage.getItem(CHAVE_SECOES_FECHADAS) ?? '[]')
    return Array.isArray(salvo) ? salvo.filter((s) => typeof s === 'string') : []
  } catch {
    return []
  }
}

const secoesFechadas = ref<string[]>(lerSecoesFechadas())

watch(secoesFechadas, (fechadas) => {
  try {
    sessionStorage.setItem(CHAVE_SECOES_FECHADAS, JSON.stringify(fechadas))
  } catch {
    // sessionStorage indisponível (modo privado/bloqueado): o acordeon segue funcionando sem memória
  }
}, { deep: true })

const secaoAberta = (secao: string) => !secoesFechadas.value.includes(secao)

function alternarSecao(secao: string) {
  secoesFechadas.value = secaoAberta(secao)
    ? [...secoesFechadas.value, secao]
    : secoesFechadas.value.filter((s) => s !== secao)
}

/** Listas renderizadas de cada seção, para achar onde está o item ativo. */
const listasSecoes = new Map<string, HTMLElement>()

function registrarLista(secao: string, el: Element | ComponentPublicInstance | null) {
  if (el instanceof HTMLElement) listasSecoes.set(secao, el)
  else listasSecoes.delete(secao)
}

/** Ao navegar para uma tela de seção fechada, abre essa seção para o item ativo ficar visível. */
function abrirSecaoDoItemAtivo() {
  for (const [secao, lista] of listasSecoes) {
    if (!secaoAberta(secao) && lista.querySelector('a.active')) {
      secoesFechadas.value = secoesFechadas.value.filter((s) => s !== secao)
    }
  }
}

/** Editor do site público; cada item só aparece com o módulo `site` e a permissão da tela. */
const TELAS_SITE = [
  { view: 'site-paginas', icone: 'bi-file-earmark-richtext', rotulo: 'Páginas do Site' },
  { view: 'site-menus', icone: 'bi-list-nested', rotulo: 'Menus do Site' },
  { view: 'site-midias', icone: 'bi-images', rotulo: 'Mídias do Site' },
  { view: 'site-configuracoes', icone: 'bi-sliders', rotulo: 'Configurações do Site' },
  { view: 'site-aparencia', icone: 'bi-palette', rotulo: 'Aparência do Site' },
  { view: 'site-templates', icone: 'bi-box-seam', rotulo: 'Templates do Site' },
]

const props = defineProps({
  sidebarOpen: Boolean,
  currentView: String,
  globalStats: {
    type: Object,
    default: () => ({
      notificacoes: 0,
      qteTarefas: 0,
      qteDespesasNaoPagas: 0,
      qteReceitasNaoRecebidas: 0
    })
  }
})

defineEmits(['set-view'])

onMounted(abrirSecaoDoItemAtivo)
watch(() => props.currentView, abrirSecaoDoItemAtivo, { flush: 'post' })

/** Contadores dos itens de cada seção, repetidos no título quando a seção está recolhida. */
const CONTADORES_SECOES: Record<string, { tela: string, campo: string, cor: string, rotulo: string }[]> = {
  financeiro: [
    { tela: 'receitas', campo: 'qteReceitasNaoRecebidas', cor: 'var(--success)', rotulo: 'Receitas a Receber' },
    { tela: 'despesas', campo: 'qteDespesasNaoPagas', cor: 'var(--warning)', rotulo: 'Despesas a Pagar' },
  ],
  gestao: [
    { tela: 'tarefas', campo: 'qteTarefas', cor: 'var(--danger)', rotulo: 'Tarefas' },
  ],
}

const contadoresSecao = (secao: string) =>
  (CONTADORES_SECOES[secao] ?? [])
    .filter((c) => telaLiberada(c.tela) && props.globalStats[c.campo] > 0)
    .map((c) => ({ ...c, valor: props.globalStats[c.campo] }))

// Badge Pulse triggers
const pulseTasks = ref(false)
const pulseExpenses = ref(false)
const pulseRevenues = ref(false)

watch(() => props.globalStats.qteTarefas, (newVal, oldVal) => {
  if (oldVal !== undefined && newVal !== oldVal) {
    pulseTasks.value = true
    setTimeout(() => { pulseTasks.value = false }, 1800)
  }
})

watch(() => props.globalStats.qteDespesasNaoPagas, (newVal, oldVal) => {
  if (oldVal !== undefined && newVal !== oldVal) {
    pulseExpenses.value = true
    setTimeout(() => { pulseExpenses.value = false }, 1800)
  }
})

watch(() => props.globalStats.qteReceitasNaoRecebidas, (newVal, oldVal) => {
  if (oldVal !== undefined && newVal !== oldVal) {
    pulseRevenues.value = true
    setTimeout(() => { pulseRevenues.value = false }, 1800)
  }
})
</script>
