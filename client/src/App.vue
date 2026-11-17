<template>
  <div id="app">
    <TenantBloqueadoView
      v-if="tenantBloqueio"
      :codigo="tenantBloqueio.codigo"
      :mensagem="tenantBloqueio.mensagem"
    />

    <div v-else-if="authChecking" class="app-loading">
      <div class="spinner"></div>
    </div>

    <RedefinirSenhaView
      v-else-if="emRedefinicaoSenha"
      @concluido="emRedefinicaoSenha = false"
    />

    <LoginView
      v-else-if="!isAuthenticated"
      @login-success="handleLoginSuccess"
    />

    <template v-else>
      <!-- Sidebar -->
      <Sidebar
        :sidebar-open="sidebarOpen"
        :current-view="currentView" 
        :global-stats="globalStats"
        @set-view="setView"
      />

      <!-- Main Wrapper -->
      <div class="main-wrapper" :class="{ 'sidebar-open': sidebarOpen }">
        <!-- Topbar -->
        <Topbar
          :current-view="currentView"
          v-model="searchQuery"
          :global-stats="globalStats"
          :current-user="currentUser"
          @toggle-sidebar="sidebarOpen = !sidebarOpen"
          @clear-search="searchQuery = ''"
          @logout="handleLogout"
          @abrir-documentacao="abrirDocumentacao"
        />

        <!-- Main View Content -->
        <Transition name="fade" mode="out-in">
          <DashboardView
            v-if="currentView === 'dashboard'"
            @set-view="setView"
            @open-detail="openDetail"
            @toast="triggerToast"
          />
          <ProcessList
            v-else-if="currentView === 'list'"
            :search-query="searchQuery"
            @open-detail="openDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ProcessDetail
            v-else-if="currentView === 'detail'"
            :process-id="selectedProcessId"
            @back="setView('list')"
            @toast="triggerToast"
            @process-deleted="handleProcessDeleted"
            @open-detail="openDetail"
            @open-documento-gerado="openDocumentoGerado"
            @open-liquidacao="openLiquidacaoDetail"
            @set-view="setView"
          />
          <LiquidacoesList
            v-else-if="currentView === 'liquidacoes'"
            :search-query="searchQuery"
            @open-detail="openLiquidacaoDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <LiquidacaoDetail
            v-else-if="currentView === 'liquidacao-detail'"
            :liquidacao-id="selectedLiquidacaoId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <ProcessForm
            v-else-if="currentView === 'process-form'"
            @set-view="setView"
            @toast="triggerToast"
          />
          <BuscaAutomatica
            v-else-if="currentView === 'busca-automatica'"
            :search-query="searchQuery"
            @clear-search="searchQuery = ''"
            @set-view="setView"
            @open-detail="openDetail"
            @toast="triggerToast"
          />
          <ImportacaoProcessos
            v-else-if="currentView === 'importacao-processos'"
            @set-view="setView"
            @open-detail="openDetail"
            @toast="triggerToast"
          />
          <ClientList
            v-else-if="currentView === 'clientes'"
            :search-query="searchQuery"
            @open-detail="openClientDetail"
            @open-supplier-detail="openSupplierDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ClientDetail
            v-else-if="currentView === 'cliente-detail'"
            :client-id="selectedClientId"
            @set-view="setView"
            @open-process-detail="openDetail"
            @open-caso-detail="openCasoDetail"
            @open-supplier-detail="openSupplierDetail"
            @open-receita-detail="openReceitaDetail"
            @open-receita-recebida-detail="openReceitaRecebidaDetail"
            @open-intimacao-detail="openSubpoenaDetail"
            @toast="triggerToast"
          />
          <CasoList
            v-else-if="currentView === 'casos'"
            :search-query="searchQuery"
            @open-detail="openCasoDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <CasoDetail
            v-else-if="currentView === 'caso-detail'"
            :caso-id="selectedCasoId"
            @set-view="setView"
            @toast="triggerToast"
            @open-processo-detail="openDetail"
            @open-documento-gerado="openDocumentoGerado"
          />
          <ContractList
            v-else-if="currentView === 'contratos'"
            :search-query="searchQuery"
            @open-detail="openContractDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ContractDetail
            v-else-if="currentView === 'contrato-detail'"
            :contract-id="selectedContractId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <SupplierList
            v-else-if="currentView === 'fornecedores'"
            :search-query="searchQuery"
            @open-detail="openSupplierDetail"
            @open-client-detail="openClientDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <SupplierDetail
            v-else-if="currentView === 'fornecedor-detail'"
            :supplier-id="selectedSupplierId"
            @set-view="setView"
            @open-client-detail="openClientDetail"
            @toast="triggerToast"
          />
          <ObjetosList
            v-else-if="currentView === 'objetos'"
            :search-query="searchQuery"
            @open-detail="openObjetoDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ObjetoDetail
            v-else-if="currentView === 'objeto-detail'"
            :objeto-id="selectedObjetoId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <IntimacoesList
            v-else-if="currentView === 'intimacoes'"
            :search-query="searchQuery"
            @open-detail="openSubpoenaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <IntimacoesDetail
            v-else-if="currentView === 'intimacao-detail'"
            :subpoena-id="selectedSubpoenaId"
            @set-view="setView"
            @open-process-detail="openDetail"
            @toast="triggerToast"
          />
          <DespesasList
            v-else-if="currentView === 'despesas'"
            :search-query="searchQuery"
            @open-detail="openDespesaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ExtratoList
            v-else-if="currentView === 'extrato'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <RelatorioCategorias
            v-else-if="currentView === 'relatorio-categorias'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <BalancoView
            v-else-if="currentView === 'balanco'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <DreView
            v-else-if="currentView === 'dre'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <PrevisaoFluxoCaixaView
            v-else-if="currentView === 'previsao-fluxo-caixa'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ContasList
            v-else-if="currentView === 'contas'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ExtratoImportacaoView
            v-else-if="currentView === 'extrato-importacao'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ExtratoMovimentosView
            v-else-if="currentView === 'extrato-movimentos'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <FechamentoMesView
            v-else-if="currentView === 'fechamento-mes'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <DespesaDetail
            v-else-if="currentView === 'despesa-detail'"
            :despesa-id="selectedDespesaId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <DespesasPagasList
            v-else-if="currentView === 'despesas-pagas'"
            :search-query="searchQuery"
            @open-detail="openDespesaPagaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <DespesasPagasDetail
            v-else-if="currentView === 'despesa-paga-detail'"
            :despesa-id="selectedDespesaPagaId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <ReceitasList
            v-else-if="currentView === 'receitas'"
            :search-query="searchQuery"
            @open-detail="openReceitaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ReceitasRecebidasList
            v-else-if="currentView === 'receitas-recebidas'"
            :search-query="searchQuery"
            @open-detail="openReceitaRecebidaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ReceitasRecebidasDetail
            v-else-if="currentView === 'receita-recebida-detail'"
            :receita-id="selectedReceitaRecebidaId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <ReceitaDetail
            v-else-if="currentView === 'receita-detail'"
            :receita-id="selectedReceitaId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <TransferenciasList
            v-else-if="currentView === 'transferencias'"
            @open-detail="openTransferenciaDetail"
            @toast="triggerToast"
            @set-view="setView"
          />
          <TransferenciaDetail
            v-else-if="currentView === 'transferencia-detail'"
            :transferencia-id="selectedTransferenciaId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <TarefasList
            v-else-if="currentView === 'tarefas'"
            :search-query="searchQuery"
            @toast="triggerToast"
            @open-process="openDetail"
            @open-caso="openCasoDetail"
            @open-cliente="openClientDetail"
          />
          <CrmPipeline
            v-else-if="currentView === 'crm'"
            @toast="triggerToast"
          />
          <CrmLeads
            v-else-if="currentView === 'crm-leads'"
            :search-query="searchQuery"
            @toast="triggerToast"
            @set-view="setView"
          />
          <MensagensList
            v-else-if="currentView === 'mensagens'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <MensagemDetail
            v-else-if="currentView === 'mensagem-detail'"
            :mensagem-id="selectedMensagemId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <EtiquetasList
            v-else-if="currentView === 'etiquetas'"
            :search-query="searchQuery"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ChecklistsList
            v-else-if="currentView === 'checklists'"
            :search-query="searchQuery"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ChecklistDetail
            v-else-if="currentView === 'checklist-detail'"
            :template-id="selectedTemplateId"
            @set-view="setView"
            @toast="triggerToast"
          />
          <BusinessCanvasView
            v-else-if="currentView === 'business-canvas'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <LegalOpsBI
            v-else-if="currentView === 'legal-ops'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <PerfilView
            v-else-if="currentView === 'perfil'"
            @toast="triggerToast"
            @set-view="setView"
            @perfil-atualizado="handlePerfilAtualizado"
          />
          <ConfiguracoesView
            v-else-if="currentView === 'configuracoes'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <NoticiasList
            v-else-if="currentView === 'noticias'"
            :search-query="searchQuery"
            @toast="triggerToast"
            @set-view="setView"
          />
          <NoticiasForm
            v-else-if="currentView === 'noticias-form'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <NoticiasCategoriasView
            v-else-if="currentView === 'noticias-categorias'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ConfiguracoesSiteView
            v-else-if="currentView === 'site-configuracoes'"
            @toast="triggerToast"
          />
          <PaginasSiteView
            v-else-if="currentView === 'site-paginas'"
            @toast="triggerToast"
            @abrir-pagina="abrirSitePagina"
          />
          <PaginaEditorView
            v-else-if="currentView === 'site-pagina-editor'"
            :pagina-id="selectedSitePaginaId"
            @toast="triggerToast"
            @set-view="setView"
          />
          <MenusSiteView
            v-else-if="currentView === 'site-menus'"
            @toast="triggerToast"
          />
          <MidiasSiteView
            v-else-if="currentView === 'site-midias'"
            @toast="triggerToast"
          />
          <AparenciaSiteView
            v-else-if="currentView === 'site-aparencia'"
            @toast="triggerToast"
          />
          <TemplatesSiteView
            v-else-if="currentView === 'site-templates'"
            @toast="triggerToast"
            @abrir-pagina="abrirSitePagina"
          />
          <DocumentacaoView
            v-else-if="currentView === 'documentacao'"
            :slug="selectedDocSlug"
            :ancora="selectedDocAncora"
            @navegar="abrirDocumentacao"
            @abrir-edicao="abrirEdicaoDocumentacao"
            @toast="triggerToast"
          />
          <DocumentacaoAdminView
            v-else-if="currentView === 'documentacao-admin'"
            @abrir-editor="abrirEdicaoDocumentacao"
            @nova="novaPaginaDocumentacao"
            @abrir-documentacao="abrirDocumentacao"
            @toast="triggerToast"
          />
          <DocumentacaoEditorView
            v-else-if="currentView === 'documentacao-editor'"
            :id="selectedDocEdicaoId"
            @voltar="setView('documentacao-admin')"
            @criada="(id) => (selectedDocEdicaoId = id)"
            @excluida="currentView = 'documentacao-admin'"
            @abrir-documentacao="abrirDocumentacao"
            @toast="triggerToast"
          />
          <ConhecimentoView
            v-else-if="currentView === 'conhecimento'"
            @toast="triggerToast"
            @set-view="setView"
            @open-detail="openDetail"
            @open-caso-detail="openCasoDetail"
            @open-modelo-detail="openModeloDetail"
          />
          <ModelosList
            v-else-if="currentView === 'modelos'"
            @toast="triggerToast"
            @set-view="setView"
            @open-modelo-detail="openModeloDetail"
            @open-documento-gerado="openDocumentoGerado"
          />
          <ModeloEditor
            v-else-if="currentView === 'modelo-editor'"
            :modelo-id="selectedModeloId"
            @toast="triggerToast"
            @set-view="setView"
          />
          <GeracaoDocumentoView
            v-else-if="currentView === 'gerar-documento'"
            :documento-gerado-id="selectedDocumentoGeradoId"
            @toast="triggerToast"
            @set-view="setView"
            @open-detail="openDetail"
            @open-caso-detail="openCasoDetail"
          />
          <Timbrado
            v-else-if="currentView === 'timbrado'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ConsultaCpf
            v-else-if="currentView === 'consulta-cpf'"
            @toast="triggerToast"
            @open-client-detail="openClientDetail"
            @open-supplier-detail="openSupplierDetail"
            @set-view="setView"
          />
          <ConsultaProcessos
            v-else-if="currentView === 'consulta-processos'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ConsultaVeiculo
            v-else-if="currentView === 'consulta-veiculo'"
            @toast="triggerToast"
            @set-view="setView"
          />
          <ContabilView
            v-else-if="currentView.startsWith('contabil')"
            :initial-view="currentView"
            @toast="triggerToast"
            @set-view="setView"
          />
        </Transition>
      </div>
    </template>

    <!-- Copiloto Jurídico & Financeiro (painel lateral, Ctrl+K) -->
    <CopilotoDrawer
      v-if="isAuthenticated && !tenantBloqueio && podeCopiloto"
      :contexto="contextoCopiloto"
      @navegar="navegarPeloCopiloto"
    />

    <!-- Toast Notifications overlay -->
    <ToastContainer :toasts="toasts" />

    <!-- PWA: faixa "sem conexão" e aviso de nova versão -->
    <PwaAvisos />
  </div>
</template>

<script setup lang="ts">
/**
 * Componente Raiz - Central de Rotas Leves
 * @version 1.9.20
 */
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { apiFetch, BASE_URL, EVENTO_MODULO_INATIVO, MENSAGEM_MODULO_INATIVO } from './services/api'
import { getCurrentUser } from './services/auth'
import { aplicarAcesso, limparAcesso, recarregarAcesso, pode } from './composables/useAcesso'
import { limpar as limparCopiloto, fechar as fecharCopiloto } from './composables/useCopiloto'
import type { ContextoCopiloto, TipoEntidadeCopiloto } from './services/copilotoApi'
import { telaLiberada } from './utils/acessoTelas'
import { confirmarSaida } from './composables/useSaidaProtegida'
import { sincronizarPush } from './composables/usePush'

// Import layouts
import Sidebar from './components/layout/Sidebar.vue'
import Topbar from './components/layout/Topbar.vue'
import ToastContainer from './components/layout/ToastContainer.vue'
import PwaAvisos from './components/layout/PwaAvisos.vue'
import CopilotoDrawer from './components/copiloto/CopilotoDrawer.vue'

// Import views
import LoginView from './views/LoginView.vue'
import TenantBloqueadoView from './views/TenantBloqueadoView.vue'
import { EVENTO_TENANT_ERRO, sincronizarTenantLocal, definirTenantSessao, type CodigoTenantBloqueio } from './services/tenant'
import RedefinirSenhaView from './views/RedefinirSenhaView.vue'
import PerfilView from './views/Perfil/PerfilView.vue'
import DashboardView from './views/Dashboard/DashboardView.vue'
import ProcessList from './views/Processos/ProcessList.vue'
import ProcessDetail from './views/Processos/ProcessDetail.vue'
import ProcessForm from './views/Processos/ProcessForm.vue'
import BuscaAutomatica from './views/Processos/BuscaAutomatica.vue'
import ImportacaoProcessos from './views/Processos/ImportacaoProcessos.vue'
import ClientList from './views/Clientes/ClientList.vue'
import ClientDetail from './views/Clientes/ClientDetail.vue'
import CasoList from './views/Casos/CasoList.vue'
import CasoDetail from './views/Casos/CasoDetail.vue'
import LiquidacoesList from './views/Liquidacoes/LiquidacoesList.vue'
import LiquidacaoDetail from './views/Liquidacoes/LiquidacaoDetail.vue'
import ContractList from './views/Contratos/ContractList.vue'
import ContractDetail from './views/Contratos/ContractDetail.vue'
import SupplierList from './views/Fornecedores/SupplierList.vue'
import SupplierDetail from './views/Fornecedores/SupplierDetail.vue'
import ObjetosList from './views/Objetos/ObjetosList.vue'
import ObjetoDetail from './views/Objetos/ObjetoDetail.vue'
import IntimacoesList from './views/Intimacoes/IntimacoesList.vue'
import IntimacoesDetail from './views/Intimacoes/IntimacoesDetail.vue'
import ExtratoList from './views/Financeiro/ExtratoList.vue'
import RelatorioCategorias from './views/Financeiro/RelatorioCategorias.vue'
import BalancoView from './views/Financeiro/BalancoView.vue'
import DreView from './views/Financeiro/DreView.vue'
import PrevisaoFluxoCaixaView from './views/Financeiro/PrevisaoFluxoCaixaView.vue'
import ContasList from './views/Financeiro/ContasList.vue'
import ExtratoImportacaoView from './views/Financeiro/ExtratoImportacaoView.vue'
import ExtratoMovimentosView from './views/Financeiro/ExtratoMovimentosView.vue'
import FechamentoMesView from './views/Financeiro/FechamentoMesView.vue'
import DespesasList from './views/Despesas/DespesasList.vue'
import DespesaDetail from './views/Despesas/DespesaDetail.vue'
import DespesasPagasList from './views/Despesas/DespesasPagasList.vue'
import DespesasPagasDetail from './views/Despesas/DespesasPagasDetail.vue'
import ReceitasList from './views/Receitas/ReceitasList.vue'
import ReceitasRecebidasList from './views/Receitas/ReceitasRecebidasList.vue'
import ReceitasRecebidasDetail from './views/Receitas/ReceitasRecebidasDetail.vue'
import ReceitaDetail from './views/Receitas/ReceitaDetail.vue'
import TransferenciasList from './views/Financeiro/TransferenciasList.vue'
import TransferenciaDetail from './views/Financeiro/TransferenciaDetail.vue'
import TarefasList from './views/Tarefas/TarefasList.vue'
import CrmPipeline from './views/CRM/CrmPipeline.vue'
import CrmLeads from './views/CRM/CrmLeads.vue'
import MensagensList from './views/Mensagens/MensagensList.vue'
import MensagemDetail from './views/Mensagens/MensagemDetail.vue'
import EtiquetasList from './views/Etiquetas/EtiquetasList.vue'
import ChecklistsList from './views/Checklists/ChecklistsList.vue'
import ChecklistDetail from './views/Checklists/ChecklistDetail.vue'
import BusinessCanvasView from './views/BusinessCanvas/BusinessCanvasView.vue'
import LegalOpsBI from './views/LegalOps/LegalOpsBI.vue'
import ConfiguracoesView from './views/Configuracoes/ConfiguracoesView.vue'
import NoticiasList from './views/Noticias/NoticiasList.vue'
import NoticiasForm from './views/Noticias/NoticiasForm.vue'
import NoticiasCategoriasView from './views/Noticias/NoticiasCategoriasView.vue'
import ConfiguracoesSiteView from './views/Site/ConfiguracoesSiteView.vue'
import MidiasSiteView from './views/Site/MidiasSiteView.vue'
import PaginasSiteView from './views/Site/PaginasSiteView.vue'
import PaginaEditorView from './views/Site/PaginaEditorView.vue'
import MenusSiteView from './views/Site/MenusSiteView.vue'
import AparenciaSiteView from './views/Site/AparenciaSiteView.vue'
import TemplatesSiteView from './views/Site/TemplatesSiteView.vue'
import DocumentacaoView from './views/Documentacao/DocumentacaoView.vue'
import DocumentacaoAdminView from './views/Documentacao/DocumentacaoAdminView.vue'
import DocumentacaoEditorView from './views/Documentacao/DocumentacaoEditorView.vue'
import { hashDaDocumentacao, hashDaEdicao, lerHashDaDocumentacao, lerHashDaEdicao } from './utils/rotasDocumentacao'
import ConhecimentoView from './views/Conhecimento/Conhecimento.vue'
import ModelosList from './views/Conhecimento/ModelosList.vue'
import ModeloEditor from './views/Conhecimento/ModeloEditor.vue'
import GeracaoDocumentoView from './views/Documentos/GeracaoDocumentoView.vue'
import Timbrado from './components/reports/Timbrado.vue'
import ConsultaCpf from './views/Consultas/ConsultaCpf.vue'
import ConsultaProcessos from './views/Consultas/ConsultaProcessos.vue'
import ConsultaVeiculo from './views/Consultas/ConsultaVeiculo.vue'
import ContabilView from './views/Contabil/ContabilView.vue'

// Reactive global states
const isAuthenticated = ref(false)
// Acesso bloqueado por causa do escritório da URL (inativo, não encontrado...): troca a tela inteira.
const tenantBloqueio = ref<{ codigo: CodigoTenantBloqueio; mensagem?: string } | null>(null)
const currentUser = ref(null)
const authChecking = ref(true)
// Link "Esqueci minha senha" do e-mail: `#/redefinir-senha?token=…&email=…` (vale com ou sem sessão).
const hashDeRedefinicao = () => window.location.hash.startsWith('#/redefinir-senha')
const emRedefinicaoSenha = ref(hashDeRedefinicao())
const currentView = ref('dashboard')
const selectedProcessId = ref(null)
const selectedClientId = ref(null)
const selectedCasoId = ref(null)
const selectedContractId = ref(null)
const selectedLiquidacaoId = ref(null)
const selectedSupplierId = ref(null)
const selectedObjetoId = ref(null)
const selectedSubpoenaId = ref(null)
const selectedDespesaId = ref(null)
const selectedDespesaPagaId = ref(null)
const selectedReceitaId = ref(null)
const selectedReceitaRecebidaId = ref(null)
const selectedTransferenciaId = ref(null)
const selectedMensagemId = ref(null)
const selectedTemplateId = ref(null)
// Editor do site (ordojuris#641): hash ↔ tela. O editor de uma página usa `#/site/paginas/{id}`.
const ROTAS_SITE = {
  '#/site/configuracoes': 'site-configuracoes',
  '#/site/paginas': 'site-paginas',
  '#/site/menus': 'site-menus',
  '#/site/midias': 'site-midias',
  '#/site/aparencia': 'site-aparencia',
  '#/site/templates': 'site-templates',
}
const hashDoSite = (view) => Object.keys(ROTAS_SITE).find((hash) => ROTAS_SITE[hash] === view) || ''

const selectedModeloId = ref(null)
const selectedSitePaginaId = ref(null)
const selectedDocumentoGeradoId = ref(null)
// Documentação interna (ordojuris#699): `#/documentacao/{slug}#{ancora}`.
const selectedDocSlug = ref(null)
const selectedDocAncora = ref(null)
// Edição da documentação (ordojuris#711): `#/documentacao-admin` e `#/documentacao-admin/{id|nova}`.
const selectedDocEdicaoId = ref(null)
const sidebarOpen = ref(false)
const searchQuery = ref('')
const toasts = ref([])

// Navigation Global Statistics State
const globalStats = reactive({
  qteMensagensNaoLidas: 0,
  notificacoes: 0,
  qteTarefas: 0,
  qteDespesasNaoPagas: 0,
  qteIntimacoesNaoTratadas: 0,
  qteReceitasNaoRecebidas: 0,
  mensagensNaoLidas: []
})

const cacheKey = 'global_stats'

// Hash-routing simple system to synchronize view state with URL address bar
function parseHash() {
  const hash = window.location.hash
  if (hash.startsWith('#/processos/detalhes/')) {
    const id = parseInt(hash.replace('#/processos/detalhes/', ''))
    if (!isNaN(id)) {
      selectedProcessId.value = id
      currentView.value = 'detail'
    } else {
      currentView.value = 'list'
    }
  } else if (hash.startsWith('#/clientes/editar/')) {
    const id = parseInt(hash.replace('#/clientes/editar/', ''))
    if (!isNaN(id)) {
      selectedClientId.value = id
      currentView.value = 'cliente-detail'
    } else {
      currentView.value = 'clientes'
    }
  } else if (hash === '#/clientes/novo') {
    selectedClientId.value = null
    currentView.value = 'cliente-detail'
  } else if (hash === '#/clientes') {
    currentView.value = 'clientes'
  } else if (hash.startsWith('#/fornecedores/editar/')) {
    const id = parseInt(hash.replace('#/fornecedores/editar/', ''))
    if (!isNaN(id)) {
      selectedSupplierId.value = id
      currentView.value = 'fornecedor-detail'
    } else {
      currentView.value = 'fornecedores'
    }
  } else if (hash === '#/fornecedores/novo') {
    selectedSupplierId.value = null
    currentView.value = 'fornecedor-detail'
  } else if (hash === '#/fornecedores') {
    currentView.value = 'fornecedores'
  } else if (hash.startsWith('#/objetos/editar/')) {
    const id = parseInt(hash.replace('#/objetos/editar/', ''))
    if (!isNaN(id)) {
      selectedObjetoId.value = id
      currentView.value = 'objeto-detail'
    } else {
      currentView.value = 'objetos'
    }
  } else if (hash === '#/objetos/novo') {
    selectedObjetoId.value = null
    currentView.value = 'objeto-detail'
  } else if (hash === '#/objetos') {
    currentView.value = 'objetos'
  } else if (hash.startsWith('#/intimacoes/detalhes/')) {
    const id = parseInt(hash.replace('#/intimacoes/detalhes/', ''))
    if (!isNaN(id)) {
      selectedSubpoenaId.value = id
      currentView.value = 'intimacao-detail'
    } else {
      currentView.value = 'intimacoes'
    }
  } else if (hash === '#/intimacoes') {
    currentView.value = 'intimacoes'
  } else if (hash.startsWith('#/casos/detalhes/')) {
    const id = parseInt(hash.replace('#/casos/detalhes/', ''))
    if (!isNaN(id)) {
      selectedCasoId.value = id
      currentView.value = 'caso-detail'
    } else {
      currentView.value = 'casos'
    }
  } else if (hash === '#/casos/novo') {
    selectedCasoId.value = null
    currentView.value = 'caso-detail'
  } else if (hash === '#/casos') {
    currentView.value = 'casos'
  } else if (hash.startsWith('#/liquidacoes/detalhes/')) {
    const id = parseInt(hash.replace('#/liquidacoes/detalhes/', ''))
    if (!isNaN(id)) {
      selectedLiquidacaoId.value = id
      currentView.value = 'liquidacao-detail'
    } else {
      currentView.value = 'liquidacoes'
    }
  } else if (hash === '#/liquidacoes') {
    currentView.value = 'liquidacoes'
  } else if (hash.startsWith('#/contratos/detalhes/')) {
    const id = parseInt(hash.replace('#/contratos/detalhes/', ''))
    if (!isNaN(id)) {
      selectedContractId.value = id
      currentView.value = 'contrato-detail'
    } else {
      currentView.value = 'contratos'
    }
  } else if (hash === '#/contratos/novo') {
    selectedContractId.value = null
    currentView.value = 'contrato-detail'
  } else if (hash === '#/contratos') {
    currentView.value = 'contratos'
  } else if (hash.startsWith('#/despesas/detalhes/')) {
    const id = parseInt(hash.replace('#/despesas/detalhes/', ''))
    if (!isNaN(id)) {
      selectedDespesaId.value = id
      currentView.value = 'despesa-detail'
    } else {
      currentView.value = 'despesas'
    }
  } else if (hash === '#/despesas/nova') {
    selectedDespesaId.value = null
    currentView.value = 'despesa-detail'
  } else if (hash.startsWith('#/despesas/pagas/detalhes/')) {
    const id = parseInt(hash.replace('#/despesas/pagas/detalhes/', ''))
    if (!isNaN(id)) {
      selectedDespesaPagaId.value = id
      currentView.value = 'despesa-paga-detail'
    } else {
      currentView.value = 'despesas-pagas'
    }
  } else if (hash === '#/despesas/pagas') {
    currentView.value = 'despesas-pagas'
  } else if (hash === '#/relatorio-categorias') {
    currentView.value = 'relatorio-categorias'
  } else if (hash === '#/extrato/movimentos') {
    currentView.value = 'extrato-movimentos'
  } else if (hash === '#/contas') {
    currentView.value = 'contas'
  } else if (hash === '#/extrato/importar') {
    currentView.value = 'extrato-importacao'
  } else if (hash === '#/extrato') {
    currentView.value = 'extrato'
  } else if (hash === '#/balanco') {
    currentView.value = 'balanco'
  } else if (hash === '#/dre') {
    currentView.value = 'dre'
  } else if (hash === '#/previsao-fluxo-caixa') {
    currentView.value = 'previsao-fluxo-caixa'
  } else if (hash === '#/fechamento-mes') {
    currentView.value = 'fechamento-mes'
  } else if (hash === '#/despesas') {
    currentView.value = 'despesas'
  } else if (hash.startsWith('#/receitas/detalhes/')) {
    const id = parseInt(hash.replace('#/receitas/detalhes/', ''))
    if (!isNaN(id)) {
      selectedReceitaId.value = id
      currentView.value = 'receita-detail'
    } else {
      currentView.value = 'receitas'
    }
  } else if (hash === '#/receitas/nova') {
    selectedReceitaId.value = null
    currentView.value = 'receita-detail'
  } else if (hash.startsWith('#/receitas/recebidas/detalhes/')) {
    const id = parseInt(hash.replace('#/receitas/recebidas/detalhes/', ''))
    if (!isNaN(id)) {
      selectedReceitaRecebidaId.value = id
      currentView.value = 'receita-recebida-detail'
    } else {
      currentView.value = 'receitas-recebidas'
    }
  } else if (hash === '#/receitas/recebidas') {
    currentView.value = 'receitas-recebidas'
  } else if (hash === '#/receitas') {
    currentView.value = 'receitas'
  } else if (hash.startsWith('#/jurisprudencias') || hash.startsWith('#/jurisprudencia')) {
    triggerToast({ msg: 'O módulo de Jurisprudência foi descontinuado.', type: 'warning' })
    currentView.value = 'list'
    window.location.hash = '#/processos'
  } else if (hash.startsWith('#/mensagens/detalhes/')) {
    const id = parseInt(hash.replace('#/mensagens/detalhes/', ''))
    if (!isNaN(id)) {
      selectedMensagemId.value = id
      currentView.value = 'mensagem-detail'
    } else {
      currentView.value = 'mensagens'
    }
  } else if (hash === '#/mensagens/nova') {
    selectedMensagemId.value = 'nova'
    currentView.value = 'mensagem-detail'
  } else if (hash === '#/mensagens') {
    currentView.value = 'mensagens'
  } else if (hash.startsWith('#/checklists/editar/')) {
    const id = parseInt(hash.replace('#/checklists/editar/', ''))
    if (!isNaN(id)) {
      selectedTemplateId.value = id
      currentView.value = 'checklist-detail'
    } else {
      currentView.value = 'checklists'
    }
  } else if (hash === '#/checklists/novo') {
    selectedTemplateId.value = null
    currentView.value = 'checklist-detail'
  } else if (hash === '#/checklists') {
    currentView.value = 'checklists'
  } else if (hash === '#/etiquetas') {
    currentView.value = 'etiquetas'
  } else if (hash === '#/business-canvas') {
    currentView.value = 'business-canvas'
  } else if (hash === '#/legal-ops') {
    currentView.value = 'legal-ops'
  } else if (hash === '#/perfil') {
    currentView.value = 'perfil'
  } else if (hash.startsWith('#/configuracoes')) {
    currentView.value = 'configuracoes'
  } else if (hash === '#/crm/leads') {
    currentView.value = 'crm-leads'
  } else if (hash === '#/crm') {
    currentView.value = 'crm'
  } else if (hash === '#/tarefas') {
    currentView.value = 'tarefas'
  } else if (hash === '#/processos/novo') {
    selectedProcessId.value = null
    currentView.value = 'process-form'
  } else if (hash === '#/processos/busca-automatica') {
    currentView.value = 'busca-automatica'
  } else if (hash === '#/processos/importacao') {
    currentView.value = 'importacao-processos'
  } else if (hash === '#/processos' || hash === '#/list') {
    currentView.value = 'list'
  } else if (hash === '#/dashboard') {
    currentView.value = 'dashboard'
  } else if (hash.startsWith('#/noticias/editar/') || hash === '#/noticias/nova') {
    currentView.value = 'noticias-form'
  } else if (hash === '#/noticias') {
    currentView.value = 'noticias'
  } else if (hash === '#/noticias/categorias') {
    currentView.value = 'noticias-categorias'
  } else if (hash.startsWith('#/site/paginas/')) {
    const id = parseInt(hash.replace('#/site/paginas/', ''))
    if (!isNaN(id)) {
      selectedSitePaginaId.value = id
      currentView.value = 'site-pagina-editor'
    } else {
      currentView.value = 'site-paginas'
    }
  } else if (hash in ROTAS_SITE) {
    currentView.value = ROTAS_SITE[hash]
  } else if (lerHashDaEdicao(hash)) {
    const rota = lerHashDaEdicao(hash)
    if (rota.tela === 'editor') {
      selectedDocEdicaoId.value = rota.id
      currentView.value = 'documentacao-editor'
    } else {
      currentView.value = 'documentacao-admin'
    }
  } else if (lerHashDaDocumentacao(hash)) {
    const rota = lerHashDaDocumentacao(hash)
    selectedDocSlug.value = rota.slug
    selectedDocAncora.value = rota.ancora
    currentView.value = 'documentacao'
  } else if (hash.startsWith('#/conhecimento/modelos/editar/')) {
    const id = parseInt(hash.replace('#/conhecimento/modelos/editar/', ''))
    if (!isNaN(id)) {
      selectedModeloId.value = id
      currentView.value = 'modelo-editor'
    } else {
      currentView.value = 'modelos'
    }
  } else if (hash === '#/conhecimento/modelos/novo') {
    selectedModeloId.value = null
    currentView.value = 'modelo-editor'
  } else if (hash === '#/conhecimento/modelos') {
    currentView.value = 'modelos'
  } else if (hash.startsWith('#/conhecimento/documentos/')) {
    const id = parseInt(hash.replace('#/conhecimento/documentos/', ''))
    if (!isNaN(id)) {
      selectedDocumentoGeradoId.value = id
      currentView.value = 'gerar-documento'
    } else {
      currentView.value = 'conhecimento'
    }
  } else if (hash === '#/conhecimento') {
    currentView.value = 'conhecimento'
  } else if (hash === '#/timbrado') {
    currentView.value = 'timbrado'
  } else if (hash === '#/consulta-cpf' || hash === '#/pesquisa-endereco') {
    currentView.value = 'consulta-cpf'
  } else if (hash === '#/consulta-processos') {
    currentView.value = 'consulta-processos'
  } else if (hash === '#/consulta-veiculo') {
    currentView.value = 'consulta-veiculo'
  } else if (hash.startsWith('#/transferencias/editar/')) {
    const id = parseInt(hash.replace('#/transferencias/editar/', ''))
    if (!isNaN(id)) {
      selectedTransferenciaId.value = id
      currentView.value = 'transferencia-detail'
    } else {
      currentView.value = 'transferencias'
    }
  } else if (hash === '#/transferencias/nova') {
    selectedTransferenciaId.value = null
    currentView.value = 'transferencia-detail'
  } else if (hash === '#/transferencias') {
    currentView.value = 'transferencias'
  } else if (hash === '#/contabil/importar') {
    currentView.value = 'contabil-importar'
  } else if (hash === '#/contabil/lancamentos') {
    currentView.value = 'contabil-lancamentos'
  } else if (hash === '#/contabil/plano-contas') {
    currentView.value = 'contabil-plano-contas'
  } else if (hash === '#/contabil/relatorios' || hash === '#/contabil/livros') {
    currentView.value = 'contabil-relatorios'
  } else if (hash === '#/contabil/periodos') {
    currentView.value = 'contabil-periodos'
  } else if (hash === '#/contabil') {
    currentView.value = 'contabil'
  } else {
    // Default fallback to keep state or dashboard
    if (!hash || hash === '#/') {
      currentView.value = 'dashboard'
    }
  }
}

function updateHash() {
  let newHash = '#/processos'
  if (currentView.value === 'detail' && selectedProcessId.value) {
    newHash = `#/processos/detalhes/${selectedProcessId.value}`
  } else if (currentView.value === 'cliente-detail') {
    if (selectedClientId.value) {
      newHash = `#/clientes/editar/${selectedClientId.value}`
    } else {
      newHash = '#/clientes/novo'
    }
  } else if (currentView.value === 'clientes') {
    newHash = '#/clientes'
  } else if (currentView.value === 'fornecedor-detail') {
    if (selectedSupplierId.value) {
      newHash = `#/fornecedores/editar/${selectedSupplierId.value}`
    } else {
      newHash = '#/fornecedores'
    }
  } else if (currentView.value === 'fornecedores') {
    newHash = '#/fornecedores'
  } else if (currentView.value === 'objeto-detail') {
    if (selectedObjetoId.value) {
      newHash = `#/objetos/editar/${selectedObjetoId.value}`
    } else {
      newHash = '#/objetos/novo'
    }
  } else if (currentView.value === 'objetos') {
    newHash = '#/objetos'
  } else if (currentView.value === 'intimacao-detail') {
    if (selectedSubpoenaId.value) {
      newHash = `#/intimacoes/detalhes/${selectedSubpoenaId.value}`
    } else {
      newHash = '#/intimacoes'
    }
  } else if (currentView.value === 'intimacoes') {
    newHash = '#/intimacoes'
  } else if (currentView.value === 'caso-detail') {
    if (selectedCasoId.value) {
      newHash = `#/casos/detalhes/${selectedCasoId.value}`
    } else {
      newHash = '#/casos/novo'
    }
  } else if (currentView.value === 'casos') {
    newHash = '#/casos'
  } else if (currentView.value === 'liquidacao-detail' && selectedLiquidacaoId.value) {
    newHash = `#/liquidacoes/detalhes/${selectedLiquidacaoId.value}`
  } else if (currentView.value === 'liquidacoes') {
    newHash = '#/liquidacoes'
  } else if (currentView.value === 'contrato-detail') {
    if (selectedContractId.value) {
      newHash = `#/contratos/detalhes/${selectedContractId.value}`
    } else {
      newHash = '#/contratos/novo'
    }
  } else if (currentView.value === 'contratos') {
    newHash = '#/contratos'
  } else if (currentView.value === 'despesa-detail') {
    if (selectedDespesaId.value) {
      newHash = `#/despesas/detalhes/${selectedDespesaId.value}`
    } else {
      newHash = '#/despesas/nova'
    }
  } else if (currentView.value === 'despesa-paga-detail') {
    if (selectedDespesaPagaId.value) {
      newHash = `#/despesas/pagas/detalhes/${selectedDespesaPagaId.value}`
    } else {
      newHash = '#/despesas/pagas'
    }
  } else if (currentView.value === 'relatorio-categorias') {
    newHash = '#/relatorio-categorias'
  } else if (currentView.value === 'extrato-movimentos') {
    newHash = '#/extrato/movimentos'
  } else if (currentView.value === 'contas') {
    newHash = '#/contas'
  } else if (currentView.value === 'extrato-importacao') {
    newHash = '#/extrato/importar'
  } else if (currentView.value === 'extrato') {
    newHash = '#/extrato'
  } else if (currentView.value === 'balanco') {
    newHash = '#/balanco'
  } else if (currentView.value === 'dre') {
    newHash = '#/dre'
  } else if (currentView.value === 'previsao-fluxo-caixa') {
    newHash = '#/previsao-fluxo-caixa'
  } else if (currentView.value === 'fechamento-mes') {
    newHash = '#/fechamento-mes'
  } else if (currentView.value === 'despesas-pagas') {
    newHash = '#/despesas/pagas'
  } else if (currentView.value === 'despesas') {
    newHash = '#/despesas'
  } else if (currentView.value === 'receita-detail') {
    if (selectedReceitaId.value) {
      newHash = `#/receitas/detalhes/${selectedReceitaId.value}`
    } else {
      newHash = '#/receitas/nova'
    }
  } else if (currentView.value === 'receita-recebida-detail') {
    if (selectedReceitaRecebidaId.value) {
      newHash = `#/receitas/recebidas/detalhes/${selectedReceitaRecebidaId.value}`
    } else {
      newHash = '#/receitas/recebidas'
    }
  } else if (currentView.value === 'receitas-recebidas') {
    newHash = '#/receitas/recebidas'
  } else if (currentView.value === 'receitas') {
    newHash = '#/receitas'
  } else if (currentView.value === 'mensagem-detail') {
    if (selectedMensagemId.value === 'nova') {
      newHash = '#/mensagens/nova'
    } else if (selectedMensagemId.value) {
      newHash = `#/mensagens/detalhes/${selectedMensagemId.value}`
    } else {
      newHash = '#/mensagens'
    }
  } else if (currentView.value === 'mensagens') {
    newHash = '#/mensagens'
  } else if (currentView.value === 'checklist-detail') {
    if (selectedTemplateId.value) {
      newHash = `#/checklists/editar/${selectedTemplateId.value}`
    } else {
      newHash = '#/checklists/novo'
    }
  } else if (currentView.value === 'checklists') {
    newHash = '#/checklists'
  } else if (currentView.value === 'etiquetas') {
    newHash = '#/etiquetas'
  } else if (currentView.value === 'business-canvas') {
    newHash = '#/business-canvas'
  } else if (currentView.value === 'legal-ops') {
    newHash = '#/legal-ops'
  } else if (currentView.value === 'perfil') {
    newHash = '#/perfil'
  } else if (currentView.value === 'configuracoes') {
    newHash = '#/configuracoes'
  } else if (currentView.value === 'crm-leads') {
    newHash = '#/crm/leads'
  } else if (currentView.value === 'crm') {
    newHash = '#/crm'
  } else if (currentView.value === 'tarefas') {
    newHash = '#/tarefas'
  } else if (currentView.value === 'process-form') {
    newHash = '#/processos/novo'
  } else if (currentView.value === 'busca-automatica') {
    newHash = '#/processos/busca-automatica'
  } else if (currentView.value === 'importacao-processos') {
    newHash = '#/processos/importacao'
  } else if (currentView.value === 'list') {
    newHash = '#/processos'
  } else if (currentView.value === 'dashboard') {
    newHash = '#/dashboard'
  } else if (currentView.value === 'noticias') {
    newHash = '#/noticias'
  } else if (currentView.value === 'noticias-categorias') {
    newHash = '#/noticias/categorias'
  } else if (currentView.value === 'site-pagina-editor') {
    newHash = selectedSitePaginaId.value ? `#/site/paginas/${selectedSitePaginaId.value}` : '#/site/paginas'
  } else if (hashDoSite(currentView.value)) {
    newHash = hashDoSite(currentView.value)
  } else if (currentView.value === 'documentacao') {
    newHash = hashDaDocumentacao(selectedDocSlug.value, selectedDocAncora.value)
  } else if (currentView.value === 'documentacao-admin') {
    newHash = hashDaEdicao({ tela: 'lista' })
  } else if (currentView.value === 'documentacao-editor') {
    newHash = hashDaEdicao({ tela: 'editor', id: selectedDocEdicaoId.value })
  } else if (currentView.value === 'noticias-form') {
    if (window.location.hash.startsWith('#/noticias/editar/')) {
      newHash = window.location.hash
    } else {
      newHash = '#/noticias/nova'
    }
  } else if (currentView.value === 'modelo-editor') {
    if (selectedModeloId.value) {
      newHash = `#/conhecimento/modelos/editar/${selectedModeloId.value}`
    } else {
      newHash = '#/conhecimento/modelos/novo'
    }
  } else if (currentView.value === 'modelos') {
    newHash = '#/conhecimento/modelos'
  } else if (currentView.value === 'gerar-documento') {
    if (selectedDocumentoGeradoId.value) {
      newHash = `#/conhecimento/documentos/${selectedDocumentoGeradoId.value}`
    }
  } else if (currentView.value === 'conhecimento') {
    newHash = '#/conhecimento'
  } else if (currentView.value === 'timbrado') {
    newHash = '#/timbrado'
  } else if (currentView.value === 'consulta-cpf') {
    newHash = '#/consulta-cpf'
  } else if (currentView.value === 'consulta-processos') {
    newHash = '#/consulta-processos'
  } else if (currentView.value === 'consulta-veiculo') {
    newHash = '#/consulta-veiculo'
  } else if (currentView.value === 'transferencia-detail') {
    if (selectedTransferenciaId.value) {
      newHash = `#/transferencias/editar/${selectedTransferenciaId.value}`
    } else {
      newHash = '#/transferencias/nova'
    }
  } else if (currentView.value === 'transferencias') {
    newHash = '#/transferencias'
  } else if (currentView.value === 'contabil-importar') {
    newHash = '#/contabil/importar'
  } else if (currentView.value === 'contabil-lancamentos') {
    newHash = '#/contabil/lancamentos'
  } else if (currentView.value === 'contabil-plano-contas') {
    newHash = '#/contabil/plano-contas'
  } else if (currentView.value === 'contabil-relatorios' || currentView.value === 'contabil-livros') {
    newHash = '#/contabil/relatorios'
  } else if (currentView.value === 'contabil-periodos') {
    newHash = '#/contabil/periodos'
  } else if (currentView.value === 'contabil') {
    newHash = '#/contabil'
  }
  
  if (window.location.hash !== newHash) {
    history.pushState(null, '', newHash)
  }
}

/** Momento do último aviso de módulo desativado vindo da API (ms). */
let avisoModuloInativo = 0

// Tela sem acesso (módulo desativado ou sem permissão), inclusive aberta por
// link direto ou quando o acesso muda depois: volta ao dashboard.
watch(
  () => isAuthenticated.value && !telaLiberada(currentView.value),
  (bloqueada) => {
    if (!bloqueada) return
    currentView.value = 'dashboard'
    // O aviso de módulo desativado acabou de aparecer: não repete.
    if (Date.now() - avisoModuloInativo < 3000) return
    triggerToast({ msg: 'Você não tem acesso a esta tela ou o módulo está desativado.', type: 'warning' })
  },
  { immediate: true }
)

watch([currentView, selectedProcessId, selectedClientId, selectedCasoId, selectedContractId, selectedLiquidacaoId, selectedSupplierId, selectedSubpoenaId, selectedDespesaId, selectedDespesaPagaId, selectedReceitaId, selectedReceitaRecebidaId, selectedMensagemId, selectedTemplateId, selectedModeloId, selectedSitePaginaId, selectedDocumentoGeradoId, selectedTransferenciaId, selectedDocSlug, selectedDocAncora, selectedDocEdicaoId], () => {
  updateHash()
})

watch(currentView, () => {
  searchQuery.value = ''
})

async function checkAuthentication() {
  authChecking.value = true
  try {
    const user = await getCurrentUser()
    if (user) {
      isAuthenticated.value = true
      currentUser.value = user
      aplicarAcesso(user)
      syncGlobalStats()
      sincronizarPush()
    } else {
      isAuthenticated.value = false
      currentUser.value = null
    }
  } catch (error) {
    isAuthenticated.value = false
  } finally {
    authChecking.value = false
  }
}

async function handleLoginSuccess(userData) {
  // Conversa do Copiloto nunca passa de uma sessão (ou escritório) para outra.
  limparCopiloto()
  if (userData?.tenant?.slug) {
    definirTenantSessao(userData.tenant.slug)
  }
  // Limpa o parâmetro de tenant da URL pós-login, já que a partir de agora fica mantido na sessão
  if (typeof window !== 'undefined' && window.location?.search && (window.location.search.includes('tenant=') || window.location.search.includes('tenant_slug='))) {
    try {
      const url = new URL(window.location.href)
      url.searchParams.delete('tenant')
      url.searchParams.delete('tenant_slug')
      window.history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash)
    } catch {
      // ignore
    }
  }

  // A resposta do login não traz permissões/módulos: só o user/info traz.
  // Carrega antes de exibir a SPA para o menu não aparecer completo e encolher.
  if (userData && Array.isArray(userData.permissoes)) {
    aplicarAcesso(userData)
  } else {
    await recarregarAcesso()
  }
  isAuthenticated.value = true
  // userData agora é o objeto completo do usuário, nunca `true`
  currentUser.value = userData || null
  syncGlobalStats()
  sincronizarPush()
}

/** Reflete no topo (nome e foto) as mudanças feitas na tela de perfil. */
function handlePerfilAtualizado({ username, email, foto }) {
  if (!currentUser.value) return
  currentUser.value = {
    ...currentUser.value,
    username,
    email,
    ...(foto ? { avatar: `${BASE_URL}usuarios/exibirFoto/${currentUser.value.id}?t=${foto}` } : {})
  }
}

function handleLogout() {
  isAuthenticated.value = false
  currentUser.value = null
  limparAcesso()
  limparCopiloto()
  fecharCopiloto()
  window.location.hash = '#/'
}

async function syncGlobalStats() {
  // 1. Try legacy CacheManager if available in window context
  if (window.CacheManager) {
    const cached = window.CacheManager.get(cacheKey)
    if (cached) {
      applyGlobalStats(cached)
      return
    }
  } else {
    // 2. Local fallback using sessionStorage directly
    try {
      const cachedStr = sessionStorage.getItem('ordo_system_' + cacheKey)
      if (cachedStr) {
        const item = JSON.parse(cachedStr)
        if (Date.now() - item.timestamp < item.expiration) {
          applyGlobalStats(item.data)
          return
        }
      }
    } catch (e) {
      console.warn('Erro ao recuperar cache de estatísticas:', e)
    }
  }

  // 3. Fetch from backend API
  try {
    const res = await apiFetch('template/notifications')
    if (res) {
      applyGlobalStats(res)
      if (window.CacheManager) {
        window.CacheManager.set(cacheKey, res, 5) // 5 minutes
      } else {
        const item = {
          data: res,
          timestamp: Date.now(),
          expiration: 5 * 60 * 1000,
          version: '2.6.4'
        }
        sessionStorage.setItem('ordo_system_' + cacheKey, JSON.stringify(item))
      }
    }
  } catch (error) {
    console.warn('[App.Utils] Falha ao sincronizar estatísticas globais:', error)
  }
}

function applyGlobalStats(data) {
  globalStats.qteMensagensNaoLidas = parseInt(data.qteMensagensNaoLidas, 10) || 0
  globalStats.notificacoes = parseInt(data.notificacoes, 10) || 0
  globalStats.qteTarefas = parseInt(data.qteTarefas, 10) || 0
  globalStats.qteDespesasNaoPagas = parseInt(data.qteDespesasNaoPagas, 10) || 0
  globalStats.qteIntimacoesNaoTratadas = parseInt(data.qteIntimacoesNaoTratadas, 10) || 0
  globalStats.qteReceitasNaoRecebidas = parseInt(data.qteReceitasNaoRecebidas, 10) || 0
  globalStats.mensagensNaoLidas = data.mensagensNaoLidas || []
}

function setView(view) {
  if (view !== currentView.value && !confirmarSaida()) return
  if (view === 'documentacao') {
    // O item do menu abre o índice da documentação.
    selectedDocSlug.value = null
    selectedDocAncora.value = null
  }
  currentView.value = view
  sidebarOpen.value = false // close sidebar on mobile navigation
}

/** Lista de edição (id null) ou editor de uma página. */
function abrirEdicaoDocumentacao(id) {
  if (!confirmarSaida()) return
  if (id === null || id === undefined) {
    currentView.value = 'documentacao-admin'
    return
  }
  selectedDocEdicaoId.value = id
  currentView.value = 'documentacao-editor'
}

function novaPaginaDocumentacao() {
  selectedDocEdicaoId.value = null
  currentView.value = 'documentacao-editor'
}

function abrirDocumentacao({ slug, ancora }) {
  // Vindo de outra tela (ajuda contextual), respeita o aviso de alterações não salvas.
  if (currentView.value !== 'documentacao' && !confirmarSaida()) return
  selectedDocSlug.value = slug
  selectedDocAncora.value = ancora
  currentView.value = 'documentacao'
}

function abrirSitePagina(id) {
  selectedSitePaginaId.value = id
  currentView.value = 'site-pagina-editor'
}

function openDetail(id) {
  selectedProcessId.value = id
  currentView.value = 'detail'
}

function openClientDetail(id) {
  selectedClientId.value = id
  currentView.value = 'cliente-detail'
}

function openSupplierDetail(id) {
  selectedSupplierId.value = id
  currentView.value = 'fornecedor-detail'
}

function openObjetoDetail(id) {
  selectedObjetoId.value = id
  currentView.value = 'objeto-detail'
}

function openSubpoenaDetail(id) {
  selectedSubpoenaId.value = id
  currentView.value = 'intimacao-detail'
}

function openDespesaDetail(id) {
  selectedDespesaId.value = id
  currentView.value = 'despesa-detail'
}

function openDespesaPagaDetail(id) {
  if (!id) {
    selectedDespesaId.value = null
    currentView.value = 'despesa-detail'
    return
  }
  selectedDespesaPagaId.value = id
  currentView.value = 'despesa-paga-detail'
}

function openReceitaRecebidaDetail(id) {
  if (!id) {
    selectedReceitaId.value = null
    currentView.value = 'receita-detail'
    return
  }
  selectedReceitaRecebidaId.value = id
  currentView.value = 'receita-recebida-detail'
}

function openTransferenciaDetail(id) {
  selectedTransferenciaId.value = id
  currentView.value = 'transferencia-detail'
}

function openReceitaDetail(id) {
  selectedReceitaId.value = id
  currentView.value = 'receita-detail'
}

function openCasoDetail(id) {
  selectedCasoId.value = id
  currentView.value = 'caso-detail'
}

function openLiquidacaoDetail(id) {
  selectedLiquidacaoId.value = id
  currentView.value = 'liquidacao-detail'
}

function openContractDetail(id) {
  selectedContractId.value = id
  currentView.value = 'contrato-detail'
}

function openModeloDetail(id) {
  selectedModeloId.value = id
  currentView.value = 'modelo-editor'
}

function openDocumentoGerado(id) {
  selectedDocumentoGeradoId.value = id
  currentView.value = 'gerar-documento'
}

// ─── Copiloto ───────────────────────────────────────────────
const podeCopiloto = computed(() => pode('copiloto.access'))

/** Registro aberto na tela, enviado como contexto a cada pergunta ao Copiloto. */
const contextoCopiloto = computed<ContextoCopiloto>(() => {
  const entidades: Record<string, [TipoEntidadeCopiloto, unknown]> = {
    'detail': ['processo', selectedProcessId.value],
    'cliente-detail': ['cliente', selectedClientId.value],
    'caso-detail': ['caso', selectedCasoId.value],
    'contrato-detail': ['contrato', selectedContractId.value],
    'liquidacao-detail': ['liquidacao', selectedLiquidacaoId.value],
    'intimacao-detail': ['intimacao', selectedSubpoenaId.value],
  }
  const entidade = entidades[currentView.value]
  const id = Number(entidade?.[1])
  return entidade && Number.isInteger(id) && id > 0
    ? { entidade_tipo: entidade[0], entidade_id: id, tela: currentView.value }
    : { tela: currentView.value }
})

/** Links das respostas do Copiloto (`ordo://tipo/id`) abrem a tela correspondente. */
function navegarPeloCopiloto({ tipo, id }: { tipo: TipoEntidadeCopiloto; id: number }) {
  if (!confirmarSaida()) return
  const abrir: Record<TipoEntidadeCopiloto, (id: number) => void> = {
    processo: openDetail,
    cliente: openClientDetail,
    caso: openCasoDetail,
    contrato: openContractDetail,
    liquidacao: openLiquidacaoDetail,
    intimacao: openSubpoenaDetail,
    tarefa: () => { currentView.value = 'tarefas' },
  }
  abrir[tipo]?.(id)
  // Em telas estreitas o painel cobre a tela: fecha para mostrar o registro.
  if (window.innerWidth < 768) fecharCopiloto()
}

function handleProcessDeleted(id) {
  setView('list')
}

// Toast notification service
function triggerToast({ msg, type = 'success', duration = 3500 }) {
  const id = Date.now()
  toasts.value.push({ id, msg, type })
  setTimeout(() => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }, duration)
}

onMounted(async () => {
  // Antes de qualquer requisição: o listener precisa existir para a primeira resposta `tenant_*`, e o
  // armazenamento local de outro escritório (mesmo navegador) não pode vazar para este.
  window.addEventListener(EVENTO_TENANT_ERRO, (evento) => {
    tenantBloqueio.value = (evento as CustomEvent).detail
  })
  sincronizarTenantLocal()

  await checkAuthentication()

  // Synchronize periodically every 2.5 minutes
  setInterval(syncGlobalStats, 150000)

  // Hash routing setup
  parseHash()
  window.addEventListener('hashchange', () => {
    emRedefinicaoSenha.value = hashDeRedefinicao()
    parseHash()
  })

  // Módulo desligado com a sessão aberta: recarrega o acesso (menu e tela
  // atual se ajustam pelo watch de telaLiberada) e avisa uma vez só, mesmo
  // que várias requisições falhem juntas.
  window.addEventListener(EVENTO_MODULO_INATIVO, () => {
    const agora = Date.now()
    if (agora - avisoModuloInativo < 3000) return
    avisoModuloInativo = agora
    triggerToast({ msg: MENSAGEM_MODULO_INATIVO, type: 'warning' })
    recarregarAcesso()
  })

  // Listen to treatment events to clear cache and refresh stats immediately
  window.addEventListener('sync-global-stats', () => {
    sessionStorage.removeItem('ordo_system_' + cacheKey)
    syncGlobalStats()
  })
})
</script>
