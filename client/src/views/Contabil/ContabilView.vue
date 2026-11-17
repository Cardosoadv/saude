<template>
  <div class="page-content animate-fade-in contabil-page">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Módulo Contábil</span>
    </div>

    <!-- Cabeçalho -->
    <div class="page-header">
      <div>
        <h1>Módulo Contábil</h1>
        <p>Escrituração em partidas dobradas, conciliação e integração financeira direta.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a
          href="#/documentacao/contabil"
          class="btn btn-ghost btn-sm doc-link-btn"
          title="Ver documentação e manual do módulo contábil"
          data-doc-contabil
        >
          <i class="bi bi-book"></i> Manual Contábil
        </a>
        <span class="badge badge-info entidade-badge">
          <i class="bi bi-building"></i> Cardoso &amp; Bruno Sociedade de Advogados (BRL)
        </span>
      </div>
    </div>

    <!-- Navegação em abas -->
    <div class="filter-tabs contabil-tabs mb-4" role="tablist">
      <button
        v-for="aba in abas"
        :key="aba.id"
        class="filter-tab"
        :class="{ active: abaAtiva === aba.id }"
        type="button"
        role="tab"
        @click="mudarAba(aba.id)"
      >
        <i class="bi" :class="aba.icone"></i> {{ aba.rotulo }}
        <span v-if="aba.id === 'importar' && kpis.total_pendentes > 0" class="tab-count">
          {{ kpis.total_pendentes }}
        </span>
      </button>
    </div>

    <!-- ABA 1: IMPORTAR DO FINANCEIRO -->
    <div v-if="abaAtiva === 'importar'">
      <div class="kpi-grid">
        <div class="card kpi">
          <span class="kpi-label">Total de transações</span>
          <strong class="kpi-value">{{ kpis.total_geral }}</strong>
          <span class="kpi-sub">Período selecionado</span>
        </div>
        <div class="card kpi kpi-warning">
          <span class="kpi-label">Pendentes de importação</span>
          <strong class="kpi-value">{{ kpis.total_pendentes }}</strong>
          <span class="kpi-sub">{{ formatCurrency(kpis.valor_pendente) }}</span>
        </div>
        <div class="card kpi kpi-success">
          <span class="kpi-label">Importados / conciliados</span>
          <strong class="kpi-value">{{ kpis.total_importados }}</strong>
          <span class="kpi-sub">{{ formatCurrency(kpis.valor_importado) }}</span>
        </div>
        <div class="card kpi kpi-brand">
          <span class="kpi-label">Integração contábil</span>
          <strong class="kpi-value kpi-value-text">Partidas Dobradas</strong>
          <span class="kpi-sub">cognitiotechny/contabil</span>
        </div>
      </div>

      <div class="toolbar toolbar-card">
        <div class="toolbar-left">
          <div class="field">
            <label class="form-label">Data início</label>
            <input type="date" class="form-control" v-model="filtroImportar.data_inicio" />
          </div>
          <div class="field">
            <label class="form-label">Data fim</label>
            <input type="date" class="form-control" v-model="filtroImportar.data_fim" />
          </div>
          <div class="field">
            <label class="form-label">Tipo</label>
            <select class="form-control form-select" v-model="filtroImportar.tipo">
              <option value="">Todos</option>
              <option value="receita">Receitas recebidas</option>
              <option value="despesa">Despesas pagas</option>
              <option value="transferencia">Transferências</option>
            </select>
          </div>
          <div class="field">
            <label class="form-label">Status</label>
            <select class="form-control form-select" v-model="filtroImportar.status">
              <option value="">Todos</option>
              <option value="pendente">Apenas pendentes</option>
              <option value="importado">Já importados</option>
            </select>
          </div>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-ghost" @click="carregarTransacoesIntegracao" :disabled="carregando">
            <i class="bi bi-arrow-clockwise" :class="{ spin: carregando }"></i> Atualizar
          </button>
          <button
            class="btn btn-primary"
            @click="executarImportacaoSelecionados"
            :disabled="importando || itensSelecionados.length === 0"
          >
            <i class="bi bi-cloud-arrow-up"></i> Importar ({{ itensSelecionados.length }})
          </button>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-check">
                  <input
                    type="checkbox"
                    :checked="todosPendentesSelecionados"
                    :disabled="transacoesPendentes.length === 0"
                    @change="alternarTodosPendentes"
                  />
                </th>
                <th>Data</th>
                <th>Tipo</th>
                <th>Descrição / Documento</th>
                <th>Conta financeira</th>
                <th class="num">Valor (R$)</th>
                <th>Débito (aplicação)</th>
                <th>Crédito (origem)</th>
                <th class="center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="carregando">
                <td colspan="9" class="state-cell">
                  <span class="spinner"></span> Carregando transações financeiras...
                </td>
              </tr>
              <tr v-else-if="transacoes.length === 0">
                <td colspan="9" class="state-cell">
                  <i class="bi bi-inbox"></i>
                  Nenhuma transação financeira encontrada para os filtros selecionados.
                </td>
              </tr>
              <tr
                v-else
                v-for="t in transacoes"
                :key="t.uid"
                :class="{ 'row-done': t.status_contabil === 'importado' }"
              >
                <td class="col-check">
                  <input
                    type="checkbox"
                    :value="t.uid"
                    v-model="selecionadosUids"
                    :disabled="t.status_contabil === 'importado'"
                  />
                </td>
                <td class="nowrap">{{ formatDate(t.data) }}</td>
                <td>
                  <span v-if="t.tipo === 'receita'" class="badge badge-success">Receita</span>
                  <span v-else-if="t.tipo === 'despesa'" class="badge badge-danger">Despesa</span>
                  <span v-else class="badge badge-info">Transferência</span>
                </td>
                <td class="strong">
                  {{ t.descricao }}
                  <div v-if="t.categorias?.length" class="categorias-linha small muted">
                    <i class="bi bi-tags"></i>
                    <span v-for="c in t.categorias" :key="c.categoria_id ?? c.categoria" class="categoria-chip">
                      {{ c.categoria }}<template v-if="t.categorias.length > 1"> · {{ formatCurrency(c.valor) }}</template>
                    </span>
                    <span v-if="t.status_contabil === 'pendente' && origemSugestao(t) === 'dicionario'" class="badge badge-info" title="Conta sugerida pelo dicionário de categorias">
                      dicionário
                    </span>
                  </div>
                </td>
                <td>{{ t.conta_origem }}</td>
                <td class="num strong nowrap">{{ formatCurrency(t.valor) }}</td>
                <td>
                  <select
                    v-if="t.status_contabil === 'pendente'"
                    class="form-control form-select select-conta"
                    v-model="t.debito_id"
                    :disabled="!!t.divisoes?.length"
                    :title="t.divisoes?.length ? 'Contas definidas na divisão' : undefined"
                    @change="aoAlterarContaLinha(t)"
                  >
                    <option v-for="c in contasFolhas" :key="c.id" :value="c.id">{{ c.rotulo }}</option>
                  </select>
                  <span v-else class="small">{{ t.debito_rotulo }}</span>
                </td>
                <td>
                  <select
                    v-if="t.status_contabil === 'pendente'"
                    class="form-control form-select select-conta"
                    v-model="t.credito_id"
                    :disabled="!!t.divisoes?.length"
                    :title="t.divisoes?.length ? 'Contas definidas na divisão' : undefined"
                    @change="aoAlterarContaLinha(t)"
                  >
                    <option v-for="c in contasFolhas" :key="c.id" :value="c.id">{{ c.rotulo }}</option>
                  </select>
                  <span v-else class="small">{{ t.credito_rotulo }}</span>
                </td>
                <td class="center nowrap">
                  <span v-if="t.status_contabil === 'importado'" class="badge badge-success">
                    <i class="bi bi-check2-circle"></i>
                    Importado #{{ t.entry_numbers?.length ? t.entry_numbers.join(', #') : t.entry_number || t.entry_id }}
                  </span>
                  <template v-else>
                    <span class="badge badge-warning"><i class="bi bi-clock"></i> Pendente</span>
                    <button
                      class="btn btn-ghost btn-sm btn-dividir"
                      :title="t.divisoes?.length ? 'Editar divisão' : 'Dividir em dois ou mais lançamentos'"
                      @click="abrirModalDivisao(t)"
                    >
                      <i class="bi bi-diagram-2"></i>
                      {{ t.divisoes?.length ? `Dividido em ${t.divisoes.length}` : 'Dividir' }}
                    </button>
                    <span
                      v-if="t.divisao_sugerida && t.divisoes?.length"
                      class="badge badge-info"
                      title="Divisão sugerida pelo rateio de categorias do financeiro; revise antes de importar"
                    >
                      por categoria
                    </span>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ABA 2: LANÇAMENTOS (LIVRO DIÁRIO) -->
    <div v-if="abaAtiva === 'lancamentos'">
      <div class="toolbar toolbar-card">
        <div class="toolbar-left">
          <div class="field">
            <label class="form-label">De</label>
            <input type="date" class="form-control" v-model="filtroLancamentos.date_from" />
          </div>
          <div class="field">
            <label class="form-label">Até</label>
            <input type="date" class="form-control" v-model="filtroLancamentos.date_to" />
          </div>
          <button class="btn btn-ghost" @click="carregarLancamentos">
            <i class="bi bi-funnel"></i> Filtrar
          </button>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-ghost" @click="abrirModalAbertura">
            <i class="bi bi-flag"></i> Abertura contábil
          </button>
          <button class="btn btn-primary" @click="abrirModalNovoLancamento">
            <i class="bi bi-plus-circle"></i> Novo lançamento manual
          </button>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nº</th>
                <th>Data competência</th>
                <th>Descrição</th>
                <th class="num">Valor</th>
                <th class="center">Status</th>
                <th class="center">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="lancamentos.length === 0">
                <td colspan="6" class="state-cell">
                  <i class="bi bi-journal-x"></i>
                  Nenhum lançamento contábil registrado no período.
                </td>
              </tr>
              <tr v-for="l in lancamentos" :key="l.id">
                <td class="strong">#{{ l.number || l.id }}</td>
                <td class="nowrap">{{ formatDate(l.accrual_date) }}</td>
                <td>
                  <div class="strong">{{ l.description }}</div>
                  <small v-if="l.source_type" class="muted">Origem: {{ l.source_type }} #{{ l.source_id }}</small>
                </td>
                <td class="num strong nowrap">
                  {{ formatCurrency((l.total ?? l.total_amount ?? 0) / 100) }}
                </td>
                <td class="center">
                  <span v-if="l.status === 'POSTED'" class="badge badge-success">Efetivado</span>
                  <span v-else-if="l.status === 'REVERSED'" class="badge badge-danger">Estornado</span>
                  <span v-else class="badge badge-muted">Rascunho</span>
                </td>
                <td class="center">
                  <button
                    v-if="l.status === 'POSTED'"
                    class="btn btn-danger btn-sm"
                    title="Estornar lançamento"
                    @click="solicitarEstorno(l)"
                  >
                    <i class="bi bi-arrow-counterclockwise"></i> Estornar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lancamentos.length > 0 || paginaLancamentos > 1" class="paginacao no-print">
          <div class="paginacao-info muted">
            Página {{ paginaLancamentos }} · {{ lancamentos.length }} lançamento(s)
            <label class="paginacao-tamanho">
              Por página
              <select
                class="form-control form-select"
                v-model.number="tamanhoPaginaLancamentos"
                :disabled="carregandoLancamentos"
                @change="carregarLancamentos"
              >
                <option v-for="t in tamanhosPaginaLancamentos" :key="t" :value="t">{{ t }}</option>
              </select>
            </label>
          </div>
          <div class="paginacao-botoes">
            <button
              class="btn btn-ghost btn-sm"
              title="Primeira página"
              :disabled="paginaLancamentos === 1 || carregandoLancamentos"
              @click="irParaPaginaLancamentos(1)"
            >
              <i class="bi bi-chevron-double-left"></i>
            </button>
            <button
              class="btn btn-ghost btn-sm"
              title="Página anterior"
              :disabled="paginaLancamentos === 1 || carregandoLancamentos"
              @click="irParaPaginaLancamentos(paginaLancamentos - 1)"
            >
              <i class="bi bi-chevron-left"></i>
            </button>
            <button
              v-for="p in paginasVisiveisLancamentos"
              :key="p"
              class="btn btn-sm"
              :class="p === paginaLancamentos ? 'btn-primary' : 'btn-ghost'"
              :disabled="carregandoLancamentos"
              @click="irParaPaginaLancamentos(p)"
            >
              {{ p }}
            </button>
            <button
              class="btn btn-ghost btn-sm"
              title="Próxima página"
              :disabled="!temProximaPaginaLancamentos || carregandoLancamentos"
              @click="irParaPaginaLancamentos(paginaLancamentos + 1)"
            >
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ABA 3: PLANO DE CONTAS -->
    <div v-if="abaAtiva === 'contas'">
      <div class="toolbar toolbar-card">
        <div class="toolbar-left">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input
              type="text"
              class="form-control"
              placeholder="Buscar conta por código ou nome..."
              v-model="termoBuscaConta"
            />
          </div>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-secondary" :disabled="contasFiltradas.length === 0" @click="exportarPlanoContas">
            <i class="bi bi-download"></i> Exportar
          </button>
          <button class="btn btn-secondary" :disabled="contasFiltradas.length === 0" @click="imprimirPlanoContas">
            <i class="bi bi-printer"></i> Imprimir
          </button>
          <button class="btn btn-secondary" @click="abrirModalImportarPlano">
            <i class="bi bi-upload"></i> Importar plano de contas
          </button>
          <button class="btn btn-primary" @click="abrirModalNovaConta">
            <i class="bi bi-plus-circle"></i> Nova conta contábil
          </button>
        </div>
      </div>

      <div class="card table-card">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-code">Código</th>
                <th>Nome da conta</th>
                <th>Tipo</th>
                <th class="center">Aceita lançamento</th>
                <th class="center">Caixa / Banco</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="contasFiltradas.length === 0">
                <td colspan="5" class="state-cell">
                  <i class="bi bi-diagram-3"></i>
                  Nenhuma conta encontrada.
                </td>
              </tr>
              <tr v-for="c in contasFiltradas" :key="c.id" :class="{ 'row-group': !c.accepts_entries }">
                <td class="mono">{{ c.code }}</td>
                <td>
                  <span :style="{ paddingLeft: `${((c.level || 1) - 1) * 1.5}rem` }">
                    <i
                      class="bi conta-icon"
                      :class="c.accepts_entries ? 'bi-file-earmark-text' : 'bi-folder2-open conta-pasta'"
                    ></i>
                    {{ c.name }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="badgeClasseTipo(c.type)">{{ c.type }}</span>
                </td>
                <td class="center">
                  <span v-if="c.accepts_entries" class="badge badge-success">Sim</span>
                  <span v-else class="muted small">Sintética</span>
                </td>
                <td class="center">
                  <i v-if="c.is_cash_account" class="bi bi-check-circle-fill ok-icon" title="Conta de Caixa / Banco"></i>
                  <span v-else class="muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ABA 4: LIVROS & DEMONSTRAÇÕES CONTÁBEIS -->
    <div v-if="abaAtiva === 'relatorios'">
      <div class="toolbar toolbar-card no-print">
        <div class="toolbar-left">
          <div class="field field-wide">
            <label class="form-label">Demonstração / livro</label>
            <select class="form-control form-select" v-model="tipoRelatorio" @change="carregarRelatorioAtual">
              <option value="trial-balance">Balancete de Verificação</option>
              <option value="income-statement">DRE (Demonstração do Resultado)</option>
              <option value="balance-sheet">Balanço Patrimonial</option>
              <option value="journal">Livro Diário Completo</option>
              <option value="ledger">Livro Razão</option>
            </select>
          </div>
          <div v-if="relatorioAceitaCentroCusto" class="field field-wide">
            <CentroCustoFiltro v-model="centroCusto" />
          </div>
          <div v-if="tipoRelatorio === 'ledger'" class="field field-wide">
            <label class="form-label">Conta contábil</label>
            <select class="form-control form-select" v-model.number="contaRazaoId" @change="carregarRelatorioAtual">
              <option :value="0">Todas as contas (com movimento)</option>
              <option v-for="c in contas" :key="c.id" :value="c.id">
                {{ c.code }} - {{ c.name }}{{ c.accepts_entries === false ? ' (Sintética)' : '' }}
              </option>
            </select>
          </div>
          <div class="field">
            <label class="form-label">Data inicial</label>
            <input type="date" class="form-control" v-model="filtroRelatorios.data_inicio" />
          </div>
          <div class="field">
            <label class="form-label">Data final</label>
            <input type="date" class="form-control" v-model="filtroRelatorios.data_fim" />
          </div>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-ghost" @click="carregarRelatorioAtual">
            <i class="bi bi-arrow-repeat"></i> Gerar demonstração
          </button>
          <button class="btn btn-primary" @click="imprimirRelatorio">
            <i class="bi bi-printer"></i> Imprimir / PDF
          </button>
        </div>
      </div>

      <div class="card relatorio-card" id="area-relatorio-contabil">
        <div class="relatorio-head">
          <h2>Cardoso &amp; Bruno Advogados</h2>
          <h3>{{ tituloRelatorio }}</h3>
          <p>
            Período de {{ formatDate(filtroRelatorios.data_inicio) }} até
            {{ formatDate(filtroRelatorios.data_fim) }}
          </p>
          <p v-if="relatorioAceitaCentroCusto && centroCustoAplicado">Centro de custo: {{ centroCustoAplicado }}</p>
        </div>

        <div v-if="relatorioCarregando" class="state-cell">
          <span class="spinner"></span> Processando demonstração contábil...
        </div>
        <div v-else-if="linhasRelatorio.length === 0" class="state-cell">
          <i class="bi bi-file-earmark-x"></i>
          Nenhuma movimentação para o período selecionado.
        </div>
        <div v-else-if="tipoRelatorio === 'journal'" class="table-wrap">
          <table class="data-table diario-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Nº</th>
                <th>Histórico / conta</th>
                <th class="num">Débito</th>
                <th class="num">Crédito</th>
              </tr>
            </thead>
            <tbody v-for="e in lancamentosDiario" :key="e.id">
              <tr class="diario-head">
                <td class="nowrap">{{ formatDate(e.accrual_date) }}</td>
                <td class="mono">{{ e.number }}</td>
                <td class="strong" colspan="3">{{ e.description }}</td>
              </tr>
              <tr v-for="(l, i) in e.lines || []" :key="l.id ?? i">
                <td></td>
                <td></td>
                <td :style="{ paddingLeft: l.side === 'C' ? '2.5rem' : '1rem' }">
                  {{ rotuloContaDiario(l.account_id) }}
                </td>
                <td class="num nowrap val-debito">{{ l.side === 'D' ? formatCurrency(l.amount / 100) : '' }}</td>
                <td class="num nowrap val-credito">{{ l.side === 'C' ? formatCurrency(l.amount / 100) : '' }}</td>
              </tr>
            </tbody>
          </table>
          <p class="diario-rodape">
            {{ lancamentosDiario.length }} lançamento(s) · Total movimentado:
            <strong>{{ formatCurrency(totalDiario / 100) }}</strong>
          </p>
        </div>
        <div v-else-if="tipoRelatorio === 'ledger'" class="table-wrap">
          <table class="data-table diario-table razao-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Nº</th>
                <th>Histórico</th>
                <th class="num">Débito</th>
                <th class="num">Crédito</th>
                <th class="num">Saldo</th>
              </tr>
            </thead>
            <tbody v-for="s in secoesRazao" :key="s.account.id">
              <tr class="diario-head razao-head">
                <td colspan="3" class="strong">
                  <span class="mono">{{ s.account.code }}</span> — {{ s.account.name }}
                </td>
                <td colspan="3" class="num muted small">
                  Natureza: {{ s.account.nature === 'CREDIT' ? 'Credora (C)' : 'Devedora (D)' }}
                </td>
              </tr>
              <tr class="razao-saldo-anterior">
                <td colspan="3" class="muted">Saldo anterior</td>
                <td class="num"></td>
                <td class="num"></td>
                <td class="num strong nowrap">{{ formatarSaldoRazao(s.opening, s.account.nature) }}</td>
              </tr>
              <tr v-for="(l, idx) in s.rows || []" :key="`${l.entry_id}-${idx}`">
                <td class="nowrap">{{ formatDate(l.date) }}</td>
                <td class="mono">#{{ l.entry_number }}</td>
                <td>
                  <div>{{ l.description }}</div>
                  <small v-if="l.account_code && l.account_code !== s.account.code" class="muted">
                    {{ rotuloContaDiario(l.account_id) }}
                  </small>
                </td>
                <td class="num nowrap val-debito">{{ l.debit ? formatCurrency(l.debit / 100) : '' }}</td>
                <td class="num nowrap val-credito">{{ l.credit ? formatCurrency(l.credit / 100) : '' }}</td>
                <td class="num nowrap">{{ formatarSaldoRazao(l.balance, s.account.nature) }}</td>
              </tr>
              <tr class="razao-fechamento">
                <td colspan="3" class="strong">Totais do período / Saldo final</td>
                <td class="num nowrap strong val-debito">{{ formatCurrency((s.debits || 0) / 100) }}</td>
                <td class="num nowrap strong val-credito">{{ formatCurrency((s.credits || 0) / 100) }}</td>
                <td class="num nowrap strong">{{ formatarSaldoRazao(s.closing, s.account.nature) }}</td>
              </tr>
            </tbody>
          </table>
          <p class="diario-rodape">
            {{ secoesRazao.length }} conta(s) · {{ totalMovimentacoesRazao }} movimentação(ões) no período
          </p>
        </div>
        <div v-else class="table-wrap">
          <table class="data-table striped">
            <thead>
              <tr>
                <th>Código</th>
                <th>Conta</th>
                <template v-if="!relatorioEhDemonstracao">
                  <th class="num">Saldo anterior</th>
                  <th class="num">Débito</th>
                  <th class="num">Crédito</th>
                  <th class="num">Saldo atual</th>
                </template>
                <th v-else class="num">Valor</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in linhasRelatorio" :key="idx">
                <td class="mono">{{ r.code || '-' }}</td>
                <td :class="{ strong: r.destaque || r.is_group }" :style="{ paddingLeft: `${0.75 + (r.nivel || 0) * 1.25}rem` }">
                  {{ r.name || r.conta }}
                </td>
                <template v-if="!relatorioEhDemonstracao">
                  <td class="num nowrap">{{ formatCurrency((r.opening || 0) / 100) }}</td>
                  <td class="num nowrap val-debito">{{ formatCurrency((r.debits || 0) / 100) }}</td>
                  <td class="num nowrap val-credito">{{ formatCurrency((r.credits || 0) / 100) }}</td>
                  <td class="num nowrap strong">{{ formatCurrency((r.closing || 0) / 100) }}</td>
                </template>
                <td v-else class="num nowrap" :class="{ strong: r.destaque || r.is_group }">
                  {{ formatCurrency((r.amount || 0) / 100) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ABA 5: EXERCÍCIOS FISCAIS -->
    <div v-if="abaAtiva === 'periodos'">
      <div class="card table-card">
        <div class="card-header">
          <h3><i class="bi bi-calendar2-check"></i> Exercício fiscal corrente (2026)</h3>
          <span class="badge badge-success">Aberto</span>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Período</th>
                <th>Início</th>
                <th>Término</th>
                <th class="center">Status</th>
                <th class="center">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in periodosSimulados" :key="p.id">
                <td class="strong">{{ p.rotulo }}</td>
                <td class="nowrap">{{ formatDate(p.start_date) }}</td>
                <td class="nowrap">{{ formatDate(p.end_date) }}</td>
                <td class="center">
                  <span class="badge" :class="p.status === 'OPEN' ? 'badge-success' : 'badge-muted'">
                    {{ p.status === 'OPEN' ? 'Aberto' : 'Fechado' }}
                  </span>
                </td>
                <td class="center">
                  <button
                    v-if="p.status === 'OPEN'"
                    class="btn btn-ghost btn-sm"
                    @click="alternarStatusPeriodo(p)"
                  >
                    <i class="bi bi-lock"></i> Fechar período
                  </button>
                  <button v-else class="btn btn-success-soft btn-sm" @click="alternarStatusPeriodo(p)">
                    <i class="bi bi-unlock"></i> Reabrir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Dividir Operação -->
    <div v-if="transacaoDividindo" class="modal-backdrop" @click.self="fecharModalDivisao">
      <div class="modal-box modal-lancamento">
        <div class="modal-header">
          <h2><i class="bi bi-diagram-2"></i> Dividir em lançamentos contábeis</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="fecharModalDivisao">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="small">
            {{ transacaoDividindo.descricao }} — total <strong>{{ formatCurrency(transacaoDividindo.valor) }}</strong>.
            Cada parte gera um lançamento próprio no diário.
            <template v-if="transacaoDividindo.divisao_sugerida">
              A divisão foi sugerida pelas categorias do financeiro, na proporção do rateio.
            </template>
          </p>
          <div class="table-wrap partidas-wrap">
            <table class="data-table compact">
              <thead>
                <tr>
                  <th v-if="divisaoTemCategorias">Categoria</th>
                  <th class="col-valor">Valor (R$)</th>
                  <th>Débito</th>
                  <th>Crédito</th>
                  <th class="col-remove"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(parte, idx) in partesDivisao" :key="idx">
                  <td v-if="divisaoTemCategorias" class="small">{{ parte.categoria || '—' }}</td>
                  <td><input type="number" step="0.01" min="0" class="form-control num" v-model.number="parte.valor" /></td>
                  <td>
                    <select class="form-control form-select" v-model="parte.debito_id">
                      <option v-for="c in contasFolhas" :key="c.id" :value="c.id">{{ c.rotulo }}</option>
                    </select>
                  </td>
                  <td>
                    <select class="form-control form-select" v-model="parte.credito_id">
                      <option v-for="c in contasFolhas" :key="c.id" :value="c.id">{{ c.rotulo }}</option>
                    </select>
                  </td>
                  <td class="center">
                    <button
                      class="btn btn-danger btn-sm btn-remove"
                      title="Remover parte"
                      :disabled="partesDivisao.length <= 2"
                      @click="partesDivisao.splice(idx, 1)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="partidas-footer">
            <button class="btn btn-ghost btn-sm" @click="adicionarParteDivisao">
              <i class="bi bi-plus"></i> Adicionar parte
            </button>
            <div class="totais">
              <span>Soma: <strong>{{ formatCurrency(somaDivisao) }}</strong></span>
              <span class="badge" :class="divisaoFecha ? 'badge-success' : 'badge-danger'">
                <i class="bi" :class="divisaoFecha ? 'bi-check-circle' : 'bi-x-circle'"></i>
                {{ divisaoFecha ? 'Fecha com o total' : `Diferença ${formatCurrency(transacaoDividindo.valor - somaDivisao)}` }}
              </span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button v-if="transacaoDividindo.divisoes?.length" type="button" class="btn btn-ghost" @click="removerDivisao">
            Desfazer divisão
          </button>
          <button type="button" class="btn btn-ghost" @click="fecharModalDivisao">Cancelar</button>
          <button type="button" class="btn btn-primary" :disabled="!divisaoValida" @click="aplicarDivisao">
            <i class="bi bi-check2"></i> Aplicar divisão
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Atualizar Dicionário de Categorias -->
    <div v-if="alteracoesDicionarioPendentes.length" class="modal-backdrop" @click.self="descartarAlteracoesDicionario">
      <div class="modal-box modal-dicionario">
        <div class="modal-header">
          <h2><i class="bi bi-journal-bookmark"></i> Atualizar o dicionário de categorias?</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="descartarAlteracoesDicionario">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="small">
            Você trocou a conta sugerida. Gravando no dicionário, as próximas importações dessas categorias
            já virão com a nova conta, e as transações pendentes desta lista que ainda estão com a sugestão
            antiga também serão ajustadas.
          </p>
          <ul class="lista-dicionario">
            <li v-for="a in alteracoesDicionarioPendentes" :key="`${a.tipo}:${a.categoria_id}`">
              <label>
                <input type="checkbox" :value="`${a.tipo}:${a.categoria_id}`" v-model="alteracoesDicionarioMarcadas" />
                <strong>{{ a.categoria }}</strong>
                <span class="badge" :class="a.tipo === 'receita' ? 'badge-success' : 'badge-danger'">{{ a.tipo }}</span>
                <span class="small">
                  <template v-if="a.anterior_id">{{ rotuloContaId(a.anterior_id) }} → </template>
                  <strong>{{ rotuloContaId(a.account_id) }}</strong>
                </span>
              </label>
            </li>
          </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="descartarAlteracoesDicionario">Agora não</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="salvandoDicionario || alteracoesDicionarioMarcadas.length === 0"
            @click="confirmarAlteracoesDicionario"
          >
            <i class="bi bi-check2"></i> Atualizar dicionário
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Novo Lançamento Manual -->
    <div v-if="modalNovoLancamento" class="modal-backdrop" @click.self="modalNovoLancamento = false">
      <div class="modal-box modal-lancamento">
        <div class="modal-header">
          <h2><i class="bi bi-journal-plus"></i> Novo lançamento contábil</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="modalNovoLancamento = false">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Data competência</label>
              <input type="date" class="form-control" v-model="novoLancamento.accrual_date" />
            </div>
            <div class="form-group">
              <label class="form-label">Histórico / descrição</label>
              <input
                type="text"
                class="form-control"
                placeholder="Ex: Pagamento de aluguel da sede"
                v-model="novoLancamento.description"
              />
            </div>
          </div>

          <h4 class="modal-section-title">Partidas contábeis (débito e crédito)</h4>
          <div class="table-wrap partidas-wrap">
            <table class="data-table compact">
              <thead>
                <tr>
                  <th>Conta</th>
                  <th class="col-lado">Lado</th>
                  <th class="col-valor">Valor (R$)</th>
                  <th>Histórico da linha</th>
                  <th class="col-remove"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(linha, idx) in novoLancamento.lines" :key="idx">
                  <td>
                    <select class="form-control form-select" v-model="linha.account_id">
                      <option v-for="c in contasFolhas" :key="c.id" :value="c.id">{{ c.rotulo }}</option>
                    </select>
                  </td>
                  <td>
                    <select class="form-control form-select" v-model="linha.side">
                      <option value="D">Débito (D)</option>
                      <option value="C">Crédito (C)</option>
                    </select>
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" class="form-control num" v-model.number="linha.amount" />
                  </td>
                  <td>
                    <input type="text" class="form-control" placeholder="Opcional" v-model="linha.description" />
                  </td>
                  <td class="center">
                    <button
                      class="btn btn-danger btn-sm btn-remove"
                      title="Remover linha"
                      @click="removerLinhaLancamento(idx)"
                      :disabled="novoLancamento.lines.length <= 2"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="partidas-footer">
            <button class="btn btn-ghost btn-sm" @click="adicionarLinhaLancamento">
              <i class="bi bi-plus"></i> Adicionar linha
            </button>
            <div class="totais">
              <span>Débitos: <strong>{{ formatCurrency(totalDebitos) }}</strong></span>
              <span>Créditos: <strong>{{ formatCurrency(totalCreditos) }}</strong></span>
              <span class="badge" :class="balancoEquilibrado ? 'badge-success' : 'badge-danger'">
                <i class="bi" :class="balancoEquilibrado ? 'bi-check-circle' : 'bi-x-circle'"></i>
                {{ balancoEquilibrado ? 'Equilibrado' : 'Divergente' }}
              </span>
            </div>
          </div>

          <div v-if="!balancoEquilibrado" class="aviso-balanco">
            <i class="bi bi-exclamation-triangle"></i>
            <span v-if="diferencaLancamento !== 0">
              O total de débitos deve ser igual ao de créditos: faltam
              <strong>{{ formatCurrency(Math.abs(diferencaLancamento)) }}</strong>
              a {{ diferencaLancamento > 0 ? 'crédito' : 'débito' }}.
            </span>
            <span v-else>Informe conta e valor maior que zero em todas as linhas.</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="modalNovoLancamento = false">Cancelar</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!podeSalvarLancamento || salvandoLancamento"
            @click="salvarNovoLancamento"
          >
            <i class="bi bi-check2"></i> Salvar lançamento
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Abertura Contábil -->
    <div v-if="modalAbertura" class="modal-backdrop" @click.self="modalAbertura = false">
      <div class="modal-box modal-lancamento modal-abertura">
        <div class="modal-header">
          <h2><i class="bi bi-flag"></i> Abertura contábil</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="modalAbertura = false">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="abertura-ajuda">
            Lança os saldos iniciais em <strong>um único lançamento</strong>, usando apenas contas patrimoniais
            (ativo, passivo e patrimônio líquido). O resultado do exercício anterior já deve estar em
            Lucros/Prejuízos Acumulados; receitas e despesas começam zeradas.
          </p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Data da abertura</label>
              <input type="date" class="form-control" v-model="abertura.data" />
            </div>
            <div class="form-group">
              <label class="form-label">Histórico (opcional)</label>
              <input
                type="text"
                class="form-control"
                placeholder="Abertura contábil - saldos iniciais"
                v-model="abertura.descricao"
              />
            </div>
          </div>

          <div class="abertura-acoes">
            <button class="btn btn-ghost btn-sm" @click="preencherBalancete2025">
              <i class="bi bi-magic"></i> Preencher com o balancete de 31/12/2025
            </button>
            <button class="btn btn-ghost btn-sm" @click="limparAbertura">Limpar valores</button>
          </div>

          <div class="table-wrap partidas-wrap abertura-wrap">
            <table class="data-table compact">
              <thead>
                <tr>
                  <th>Conta</th>
                  <th class="col-valor">Débito (R$)</th>
                  <th class="col-valor">Crédito (R$)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="l in abertura.linhas" :key="l.code">
                  <td>{{ rotuloConta(l.code) }}</td>
                  <td>
                    <input type="number" step="0.01" min="0" class="form-control num" v-model.number="l.debito" />
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" class="form-control num" v-model.number="l.credito" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="partidas-footer">
            <div class="totais">
              <span>Débitos: <strong>{{ formatCurrency(totaisAberturaAtual.debitos) }}</strong></span>
              <span>Créditos: <strong>{{ formatCurrency(totaisAberturaAtual.creditos) }}</strong></span>
              <span class="badge" :class="totaisAberturaAtual.fecha ? 'badge-success' : 'badge-danger'">
                <i class="bi" :class="totaisAberturaAtual.fecha ? 'bi-check-circle' : 'bi-x-circle'"></i>
                {{ totaisAberturaAtual.fecha ? 'Equilibrado' : 'Diferença ' + formatCurrency(Math.abs(totaisAberturaAtual.diferenca)) }}
              </span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="modalAbertura = false">Cancelar</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!totaisAberturaAtual.fecha || !abertura.data || salvandoAbertura"
            @click="confirmarAbertura"
          >
            <i class="bi bi-check2"></i> Lançar abertura
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Importar Plano de Contas -->
    <div v-if="modalImportarPlano" class="modal-backdrop" @click.self="fecharModalImportarPlano">
      <div class="modal-box">
        <div class="modal-header">
          <h2><i class="bi bi-upload"></i> Importar plano de contas</h2>
          <button type="button" class="modal-close" aria-label="Fechar" @click="fecharModalImportarPlano">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="text-muted">
            Contas cujo código já existe são mantidas; repetir a importação não duplica nada.
            Revise o plano com a contabilidade antes de tratá-lo como oficial.
          </p>

          <h4 class="modal-section-title">Usar um plano modelo</h4>
          <div class="form-group">
            <select class="form-control form-select" v-model="modeloEscolhido" :disabled="importandoPlano">
              <option value="" disabled>Selecione um modelo...</option>
              <option v-for="m in modelosPlano" :key="m.id" :value="m.id">{{ m.rotulo }}</option>
            </select>
          </div>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!modeloEscolhido || importandoPlano"
            @click="importarModelo"
          >
            <i class="bi bi-check2"></i> Importar modelo
          </button>

          <h4 class="modal-section-title mt-4">Ou enviar um arquivo</h4>
          <div class="form-group">
            <input
              type="file"
              class="form-control"
              accept=".csv,.json,text/csv,application/json"
              :disabled="importandoPlano"
              @change="escolherArquivoPlano"
            />
            <small class="text-muted">
              CSV com cabeçalho (code, name, type, parent_code, contra, accepts_entries, is_cash_account)
              ou JSON. Se uma linha for rejeitada, nada é importado.
            </small>
          </div>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!arquivoPlano || importandoPlano"
            @click="importarArquivo"
          >
            <i class="bi bi-file-earmark-arrow-up"></i> Importar arquivo
          </button>

          <div v-if="erroImportacaoPlano" class="alert alert-danger mt-3" role="alert">{{ erroImportacaoPlano }}</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="fecharModalImportarPlano">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * View principal do Módulo Contábil do OrdoJuris.
 * Integração direta com financeiro e o componente cognitiotechny/contabil.
 *
 * @version 1.0.0
 */
import { ref, computed, onMounted, watch } from 'vue'
import {
  listarTransacoesIntegracao,
  listarContasIntegracao,
  importarTransacoesFinanceiras,
  listarDicionarioCategorias,
  salvarDicionarioCategorias,
  listarLancamentos,
  criarLancamento,
  lancarAbertura,
  estornarLancamento,
  listarContas,
  listarModelosPlano,
  importarPlanoPadrao,
  importarPlanoArquivo,
  mensagemErroImportacao,
  type ModeloPlano,
  obterBalancete,
  obterDreContabil,
  obterBalancoPatrimonial,
  obterLivroDiario,
  obterLivroRazao,
  type LancamentoDiario,
  type SecaoLivroRazao,
  type TransacaoIntegracao,
  type DivisaoIntegracao,
  type KpisIntegracao,
  type ContaContabil,
  type LancamentoContabil,
} from '../../services/contabil'
import { formatCurrency, formatDate } from '../../services/api'
import CentroCustoFiltro, { type CentroCusto } from '../../components/Contabil/CentroCustoFiltro.vue'
import {
  BALANCETE_2025_CARDOSO_BRUNO,
  DATA_ABERTURA_2026,
  TIPOS_ABERTURA,
  saldosDasLinhas,
  totaisAbertura,
  type LinhaAbertura,
} from '../../utils/abertura'
import {
  alteracoesDicionario,
  aplicarDicionario,
  chaveDicionario,
  mapaDicionario,
  registrarSugestoes,
  type AlteracaoDicionario,
} from '../../utils/dicionarioCategorias'

const props = defineProps<{
  initialView?: string
}>()

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type?: 'success' | 'danger' | 'warning' }): void
  (e: 'set-view', view: string): void
}>()

function dispararToast(msg: string, type: 'success' | 'danger' | 'warning' = 'success') {
  emit('toast', { msg, type })
}

// Estado das abas
type AbaContabil = 'importar' | 'lancamentos' | 'contas' | 'relatorios' | 'periodos'

const abas: { id: AbaContabil; rotulo: string; icone: string }[] = [
  { id: 'importar', rotulo: 'Importar do Financeiro', icone: 'bi-box-arrow-in-down' },
  { id: 'lancamentos', rotulo: 'Lançamentos (Diário)', icone: 'bi-journal-text' },
  { id: 'contas', rotulo: 'Plano de Contas', icone: 'bi-diagram-3' },
  { id: 'relatorios', rotulo: 'Livros & Demonstrações', icone: 'bi-file-earmark-spreadsheet' },
  { id: 'periodos', rotulo: 'Exercícios Fiscais', icone: 'bi-calendar2-check' },
]

const abaAtiva = ref<AbaContabil>('importar')

function mudarAba(aba: AbaContabil) {
  abaAtiva.value = aba
  const viewMap: Record<AbaContabil, string> = {
    importar: 'contabil-importar',
    lancamentos: 'contabil-lancamentos',
    contas: 'contabil-plano-contas',
    relatorios: 'contabil-relatorios',
    periodos: 'contabil-periodos',
  }
  emit('set-view', viewMap[aba])

  if (aba === 'importar') carregarTransacoesIntegracao()
  else if (aba === 'lancamentos') carregarLancamentos()
  else if (aba === 'contas') carregarPlanoContas()
  else if (aba === 'relatorios') carregarRelatorioAtual()
}

// ---------------------------------------------------------------------------
// ABA 1: IMPORTAR DO FINANCEIRO
// ---------------------------------------------------------------------------
const carregando = ref(false)
const importando = ref(false)
const transacoes = ref<TransacaoIntegracao[]>([])
const contasFolhas = ref<ContaContabil[]>([])
const selecionadosUids = ref<string[]>([])

const anoAtual = new Date().getFullYear()
const filtroImportar = ref({
  data_inicio: `${anoAtual}-01-01`,
  data_fim: new Date().toISOString().substring(0, 10),
  tipo: '',
  status: '',
})

const kpis = ref<KpisIntegracao>({
  total_geral: 0,
  total_pendentes: 0,
  total_importados: 0,
  valor_pendente: 0,
  valor_importado: 0,
})

const transacoesPendentes = computed(() => transacoes.value.filter((t) => t.status_contabil === 'pendente'))
const itensSelecionados = computed(() => transacoes.value.filter((t) => selecionadosUids.value.includes(t.uid)))
const todosPendentesSelecionados = computed(() => {
  return transacoesPendentes.value.length > 0 && transacoesPendentes.value.every((t) => selecionadosUids.value.includes(t.uid))
})

function alternarTodosPendentes() {
  if (todosPendentesSelecionados.value) {
    selecionadosUids.value = []
  } else {
    selecionadosUids.value = transacoesPendentes.value.map((t) => t.uid)
  }
}

async function carregarTransacoesIntegracao() {
  carregando.value = true
  try {
    const [dadosTransacoes, contas, entradasDicionario] = await Promise.all([
      listarTransacoesIntegracao(filtroImportar.value),
      listarContasIntegracao(),
      // Sem o dicionário a importação segue com as sugestões do servidor; só não oferece atualizá-lo.
      listarDicionarioCategorias().catch(() => []),
    ])
    registrarSugestoes(dadosTransacoes.transacoes)
    transacoes.value = dadosTransacoes.transacoes
    kpis.value = dadosTransacoes.kpis
    contasFolhas.value = contas
    dicionario.value = mapaDicionario(entradasDicionario)
  } catch (e: any) {
    dispararToast(e?.message || 'Erro ao carregar transações financeiras.', 'danger')
  } finally {
    carregando.value = false
  }
}

// Dicionário categoria -> conta: ao trocar a conta sugerida, oferece gravar a troca
const dicionario = ref<Record<string, number>>({})
const alteracoesDicionarioPendentes = ref<AlteracaoDicionario[]>([])
const alteracoesDicionarioMarcadas = ref<string[]>([])
const salvandoDicionario = ref(false)

function rotuloContaId(id: number | null | undefined) {
  return contasFolhas.value.find((c) => c.id === id)?.rotulo || `Conta #${id}`
}

function origemSugestao(t: TransacaoIntegracao) {
  return t.divisoes?.length ? t.divisoes.find((d) => d.sugestao_origem === 'dicionario')?.sugestao_origem : t.sugestao_origem
}

function oferecerAtualizacaoDicionario(t: TransacaoIntegracao) {
  const alteracoes = alteracoesDicionario(t, dicionario.value)
  if (!alteracoes.length) return
  alteracoesDicionarioPendentes.value = alteracoes
  alteracoesDicionarioMarcadas.value = alteracoes.map((a) => chaveDicionario(a.tipo, a.categoria_id))
}

function aoAlterarContaLinha(t: TransacaoIntegracao) {
  oferecerAtualizacaoDicionario(t)
}

function descartarAlteracoesDicionario() {
  alteracoesDicionarioPendentes.value = []
  alteracoesDicionarioMarcadas.value = []
}

async function confirmarAlteracoesDicionario() {
  const escolhidas = alteracoesDicionarioPendentes.value.filter((a) =>
    alteracoesDicionarioMarcadas.value.includes(chaveDicionario(a.tipo, a.categoria_id)),
  )
  if (!escolhidas.length) return

  salvandoDicionario.value = true
  try {
    const entradas = await salvarDicionarioCategorias(escolhidas)
    dicionario.value = mapaDicionario(entradas)
    const outras = aplicarDicionario(transacoes.value, escolhidas)
    dispararToast(
      outras > 0
        ? `Dicionário atualizado; ${outras} outra(s) transação(ões) pendente(s) ajustada(s).`
        : 'Dicionário de categorias atualizado.',
    )
    descartarAlteracoesDicionario()
  } catch (e: any) {
    dispararToast(e?.message || 'Falha ao atualizar o dicionário de categorias.', 'danger')
  } finally {
    salvandoDicionario.value = false
  }
}

// Divisão de uma operação financeira em vários lançamentos
const transacaoDividindo = ref<TransacaoIntegracao | null>(null)
const partesDivisao = ref<DivisaoIntegracao[]>([])
const divisaoTemCategorias = computed(() => partesDivisao.value.some((p) => p.categoria))

const somaDivisao = computed(() => partesDivisao.value.reduce((acc, p) => acc + (Number(p.valor) || 0), 0))
const divisaoFecha = computed(
  () => !!transacaoDividindo.value && Math.round(somaDivisao.value * 100) === Math.round(transacaoDividindo.value.valor * 100),
)
const divisaoValida = computed(
  () =>
    divisaoFecha.value &&
    partesDivisao.value.length >= 2 &&
    partesDivisao.value.every((p) => p.valor > 0 && p.debito_id && p.credito_id),
)

function abrirModalDivisao(t: TransacaoIntegracao) {
  transacaoDividindo.value = t
  if (t.divisoes?.length) {
    partesDivisao.value = t.divisoes.map((d) => ({ ...d }))
    return
  }
  const metade = Math.floor((t.valor * 100) / 2) / 100
  partesDivisao.value = [
    { valor: metade, debito_id: t.debito_id, credito_id: t.credito_id },
    { valor: Math.round((t.valor - metade) * 100) / 100, debito_id: t.debito_id, credito_id: t.credito_id },
  ]
}

function adicionarParteDivisao() {
  const t = transacaoDividindo.value
  if (!t) return
  const resto = Math.max(0, Math.round((t.valor - somaDivisao.value) * 100) / 100)
  partesDivisao.value.push({ valor: resto, debito_id: t.debito_id, credito_id: t.credito_id })
}

function aplicarDivisao() {
  const t = transacaoDividindo.value
  if (!t || !divisaoValida.value) return
  t.divisoes = partesDivisao.value.map((p) => ({ ...p }))
  if (!selecionadosUids.value.includes(t.uid)) selecionadosUids.value.push(t.uid)
  fecharModalDivisao()
  oferecerAtualizacaoDicionario(t)
}

function removerDivisao() {
  if (transacaoDividindo.value) transacaoDividindo.value.divisoes = undefined
  fecharModalDivisao()
}

function fecharModalDivisao() {
  transacaoDividindo.value = null
  partesDivisao.value = []
}

async function executarImportacaoSelecionados() {
  if (itensSelecionados.value.length === 0) return

  importando.value = true
  try {
    const res = await importarTransacoesFinanceiras(itensSelecionados.value, true, 1)
    dispararToast(res.message || `${res.sucesso} lançamento(s) importado(s) com sucesso!`, 'success')
    selecionadosUids.value = []
    await carregarTransacoesIntegracao()
  } catch (e: any) {
    dispararToast(e?.message || 'Falha na importação contábil.', 'danger')
  } finally {
    importando.value = false
  }
}

// ---------------------------------------------------------------------------
// ABA 2: LANÇAMENTOS (LIVRO DIÁRIO)
// ---------------------------------------------------------------------------
const lancamentos = ref<LancamentoContabil[]>([])
const filtroLancamentos = ref({
  date_from: `${anoAtual}-01-01`,
  date_to: new Date().toISOString().substring(0, 10),
})

// A API pagina por cursor (before_id), sem total: guardamos o cursor de início de cada página
// visitada e pedimos um item a mais para saber se existe a próxima.
const tamanhosPaginaLancamentos = [25, 50, 100]
const tamanhoPaginaLancamentos = ref(50)
const paginaLancamentos = ref(1)
const cursoresLancamentos = ref<(number | null)[]>([null])
const temProximaPaginaLancamentos = ref(false)
const carregandoLancamentos = ref(false)

async function buscarPaginaLancamentos(pagina: number) {
  carregandoLancamentos.value = true
  try {
    const itens = await listarLancamentos({
      ...filtroLancamentos.value,
      before_id: cursoresLancamentos.value[pagina - 1],
      limit: tamanhoPaginaLancamentos.value + 1,
    })
    temProximaPaginaLancamentos.value = itens.length > tamanhoPaginaLancamentos.value
    lancamentos.value = itens.slice(0, tamanhoPaginaLancamentos.value)
    paginaLancamentos.value = pagina

    const ultimo = lancamentos.value[lancamentos.value.length - 1]
    if (temProximaPaginaLancamentos.value && ultimo) {
      cursoresLancamentos.value[pagina] = ultimo.id
    } else {
      cursoresLancamentos.value.length = pagina
    }
  } catch (e: any) {
    dispararToast('Erro ao carregar livro diário.', 'danger')
  } finally {
    carregandoLancamentos.value = false
  }
}

/** Recarrega a partir da primeira página (filtro novo, lançamento criado ou estornado). */
async function carregarLancamentos() {
  cursoresLancamentos.value = [null]
  await buscarPaginaLancamentos(1)
}

function irParaPaginaLancamentos(pagina: number) {
  if (pagina < 1 || pagina > cursoresLancamentos.value.length || carregandoLancamentos.value) return
  buscarPaginaLancamentos(pagina)
}

// Só se conhecem as páginas já alcançadas (cada uma com seu cursor) e a seguinte.
const paginasVisiveisLancamentos = computed(() => {
  const total = cursoresLancamentos.value.length
  const inicio = Math.max(1, Math.min(paginaLancamentos.value - 2, total - 4))
  const fim = Math.min(total, inicio + 4)
  return Array.from({ length: fim - inicio + 1 }, (_, i) => inicio + i)
})

async function solicitarEstorno(lancamento: LancamentoContabil) {
  const motivo = window.prompt(`Informe o motivo do estorno para o lançamento #${lancamento.number || lancamento.id}:`)
  if (!motivo) return

  try {
    await estornarLancamento(lancamento.id, motivo)
    dispararToast(`Lançamento #${lancamento.number || lancamento.id} estornado com sucesso.`, 'success')
    await carregarLancamentos()
  } catch (e: any) {
    dispararToast(e?.message || 'Falha ao estornar lançamento.', 'danger')
  }
}

// Modal Novo Lançamento
const modalNovoLancamento = ref(false)
const salvandoLancamento = ref(false)
const novoLancamento = ref({
  accrual_date: new Date().toISOString().substring(0, 10),
  description: '',
  lines: [
    { account_id: 0, side: 'D' as 'D' | 'C', amount: 0, description: '' },
    { account_id: 0, side: 'C' as 'D' | 'C', amount: 0, description: '' },
  ],
})

async function abrirModalNovoLancamento() {
  if (contasFolhas.value.length === 0) {
    try {
      contasFolhas.value = await listarContasIntegracao(1)
    } catch (e: any) {
      dispararToast(e?.message || 'Erro ao carregar contas contábeis.', 'danger')
    }
  }
  const padraoDebito = contasFolhas.value[0]?.id || 0
  const padraoCredito = contasFolhas.value[1]?.id || 0
  novoLancamento.value = {
    accrual_date: new Date().toISOString().substring(0, 10),
    description: '',
    lines: [
      { account_id: padraoDebito, side: 'D', amount: 0, description: '' },
      { account_id: padraoCredito, side: 'C', amount: 0, description: '' },
    ],
  }
  modalNovoLancamento.value = true
}

/** Nova linha já no lado e no valor que faltam para fechar o lançamento. */
function adicionarLinhaLancamento() {
  const falta = diferencaLancamento.value
  novoLancamento.value.lines.push({
    account_id: 0,
    side: falta > 0 ? 'C' : 'D',
    amount: Math.abs(falta),
    description: '',
  })
}

function removerLinhaLancamento(idx: number) {
  if (novoLancamento.value.lines.length > 2) {
    novoLancamento.value.lines.splice(idx, 1)
  }
}

const centavosLinha = (valor: unknown) => Math.round((Number(valor) || 0) * 100)

const totalDebitos = computed(() => {
  return novoLancamento.value.lines
    .filter((l) => l.side === 'D')
    .reduce((acc, curr) => acc + centavosLinha(curr.amount), 0) / 100
})

const totalCreditos = computed(() => {
  return novoLancamento.value.lines
    .filter((l) => l.side === 'C')
    .reduce((acc, curr) => acc + centavosLinha(curr.amount), 0) / 100
})

/** Débitos menos créditos, em reais (positivo = faltam créditos). */
const diferencaLancamento = computed(
  () => Math.round((totalDebitos.value - totalCreditos.value) * 100) / 100,
)

const balancoEquilibrado = computed(() => {
  return totalDebitos.value > 0 && diferencaLancamento.value === 0
})

const podeSalvarLancamento = computed(
  () =>
    balancoEquilibrado.value &&
    novoLancamento.value.lines.length >= 2 &&
    novoLancamento.value.lines.every((l) => l.account_id > 0 && centavosLinha(l.amount) > 0),
)

async function salvarNovoLancamento() {
  if (!podeSalvarLancamento.value) return
  salvandoLancamento.value = true
  try {
    await criarLancamento({
      accrual_date: novoLancamento.value.accrual_date,
      description: novoLancamento.value.description,
      lines: novoLancamento.value.lines.map((l) => ({
        account_id: l.account_id,
        side: l.side,
        amount: centavosLinha(l.amount),
        ...(l.description?.trim() ? { description: l.description.trim() } : {}),
      })),
      post: true,
    })
    dispararToast('Lançamento registrado com sucesso!', 'success')
    modalNovoLancamento.value = false
    await carregarLancamentos()
  } catch (e: any) {
    dispararToast(e?.message || 'Erro ao registrar lançamento.', 'danger')
  } finally {
    salvandoLancamento.value = false
  }
}

// Modal Abertura Contábil
const modalAbertura = ref(false)
const salvandoAbertura = ref(false)
const abertura = ref<{ data: string; descricao: string; linhas: LinhaAbertura[] }>({
  data: DATA_ABERTURA_2026,
  descricao: '',
  linhas: [],
})

const totaisAberturaAtual = computed(() => totaisAbertura(abertura.value.linhas))

function rotuloConta(code: string) {
  return contasFolhas.value.find((c) => c.code === code)?.rotulo || code
}

async function abrirModalAbertura() {
  if (contasFolhas.value.length === 0) {
    try {
      contasFolhas.value = await listarContasIntegracao(1)
    } catch (e: any) {
      dispararToast(e?.message || 'Erro ao carregar contas contábeis.', 'danger')
      return
    }
  }
  abertura.value = {
    data: DATA_ABERTURA_2026,
    descricao: '',
    linhas: contasFolhas.value
      .filter((c) => TIPOS_ABERTURA.includes(String(c.type).toUpperCase()))
      .map((c) => ({ code: c.code, debito: null, credito: null })),
  }
  modalAbertura.value = true
}

function limparAbertura() {
  abertura.value.linhas.forEach((l) => {
    l.debito = null
    l.credito = null
  })
}

function preencherBalancete2025() {
  limparAbertura()
  const ausentes: string[] = []
  for (const saldo of BALANCETE_2025_CARDOSO_BRUNO) {
    const linha = abertura.value.linhas.find((l) => l.code === saldo.code)
    if (!linha) {
      ausentes.push(saldo.code)
      continue
    }
    if (saldo.side === 'D') linha.debito = saldo.amount
    else linha.credito = saldo.amount
  }
  abertura.value.data = DATA_ABERTURA_2026
  if (ausentes.length) {
    dispararToast(`Contas não encontradas no plano: ${ausentes.join(', ')}. Importe o plano oficial antes.`, 'warning')
  }
}

async function confirmarAbertura() {
  if (!totaisAberturaAtual.value.fecha) return
  salvandoAbertura.value = true
  try {
    const res = await lancarAbertura({
      data: abertura.value.data,
      descricao: abertura.value.descricao,
      saldos: saldosDasLinhas(abertura.value.linhas),
    })
    dispararToast(res.message || 'Abertura contábil lançada.', res.created ? 'success' : 'warning')
    modalAbertura.value = false
    await carregarLancamentos()
  } catch (e: any) {
    dispararToast(e?.data?.message || e?.message || 'Erro ao lançar a abertura contábil.', 'danger')
  } finally {
    salvandoAbertura.value = false
  }
}

// ---------------------------------------------------------------------------
// ABA 3: PLANO DE CONTAS
// ---------------------------------------------------------------------------
const contas = ref<ContaContabil[]>([])
const termoBuscaConta = ref('')

async function carregarPlanoContas() {
  try {
    contas.value = await listarContas(1)
  } catch (e: any) {
    dispararToast('Erro ao carregar plano de contas.', 'danger')
  }
}

const contasFiltradas = computed(() => {
  if (!termoBuscaConta.value) return contas.value
  const t = termoBuscaConta.value.toLowerCase()
  return contas.value.filter((c) => c.code.toLowerCase().includes(t) || c.name.toLowerCase().includes(t))
})

const ROTULOS_TIPO: Record<string, string> = {
  ASSET: 'Ativo',
  LIABILITY: 'Passivo',
  EQUITY: 'Patrimônio líquido',
  REVENUE: 'Receita',
  EXPENSE: 'Despesa',
}

function exportarPlanoContas() {
  const porId = new Map(contas.value.map((c) => [c.id, c]))
  const celula = (v: string | number | boolean) => `"${String(v).replace(/"/g, '""')}"`
  const linhas = [['code', 'name', 'type', 'parent_code', 'accepts_entries', 'is_cash_account']]
  for (const c of contasFiltradas.value) {
    linhas.push([
      c.code,
      c.name,
      c.type,
      (c.parent_id && porId.get(c.parent_id)?.code) || '',
      c.accepts_entries ? '1' : '0',
      c.is_cash_account ? '1' : '0',
    ])
  }
  const csv = '\uFEFF' + linhas.map((l) => l.map(celula).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = `plano-de-contas-${new Date().toISOString().substring(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function imprimirPlanoContas() {
  const esc = (v: string) => v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const linhas = contasFiltradas.value
    .map((c) => {
      const recuo = ((c.level || 1) - 1) * 16
      const estilo = c.accepts_entries ? '' : ' style="font-weight:bold"'
      return `<tr${estilo}><td>${esc(c.code)}</td><td style="padding-left:${recuo + 6}px">${esc(c.name)}</td>` +
        `<td>${ROTULOS_TIPO[c.type] || esc(c.type)}</td><td>${c.accepts_entries ? 'Analítica' : 'Sintética'}</td>` +
        `<td>${c.is_cash_account ? 'Sim' : ''}</td></tr>`
    })
    .join('')
  const w = window.open('', '_blank')
  if (!w) {
    dispararToast('Permita pop-ups para imprimir o plano de contas.', 'warning')
    return
  }
  w.document.write(`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Plano de contas</title>
<style>body{font-family:Arial,sans-serif;font-size:12px;margin:24px}h1{font-size:16px}
table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #ccc;padding:4px 6px;text-align:left}
th{background:#f0f0f0}</style></head><body><h1>Plano de contas</h1>
<p>Emitido em ${new Date().toLocaleDateString('pt-BR')}</p>
<table><thead><tr><th>Código</th><th>Conta</th><th>Tipo</th><th>Natureza</th><th>Caixa / Banco</th></tr></thead>
<tbody>${linhas}</tbody></table></body></html>`)
  w.document.close()
  w.focus()
  w.print()
}

function badgeClasseTipo(tipo: string) {
  switch (tipo) {
    case 'ASSET': return 'badge-info'
    case 'LIABILITY': return 'badge-warning'
    case 'EQUITY': return 'badge-dark'
    case 'REVENUE': return 'badge-success'
    case 'EXPENSE': return 'badge-danger'
    default: return 'badge-muted'
  }
}

// Importação do plano de contas (modelo do pacote ou arquivo CSV/JSON)
const modalImportarPlano = ref(false)
const importandoPlano = ref(false)
const modelosPlano = ref<ModeloPlano[]>([])
const modeloEscolhido = ref('')
const arquivoPlano = ref<File | null>(null)
const erroImportacaoPlano = ref('')

async function abrirModalImportarPlano() {
  modalImportarPlano.value = true
  erroImportacaoPlano.value = ''
  arquivoPlano.value = null
  try {
    modelosPlano.value = await listarModelosPlano()
  } catch {
    modelosPlano.value = []
  }
}

function fecharModalImportarPlano() {
  if (importandoPlano.value) return
  modalImportarPlano.value = false
}

function escolherArquivoPlano(ev: Event) {
  const input = ev.target as HTMLInputElement
  arquivoPlano.value = input.files?.[0] ?? null
}

async function concluirImportacaoPlano(executar: () => Promise<{ created: number; skipped: number }>) {
  importandoPlano.value = true
  erroImportacaoPlano.value = ''
  try {
    const r = await executar()
    dispararToast(`${r.created} conta(s) criada(s); ${r.skipped} já existiam.`, 'success')
    modalImportarPlano.value = false
    await carregarPlanoContas()
  } catch (e: any) {
    erroImportacaoPlano.value = mensagemErroImportacao(e)
  } finally {
    importandoPlano.value = false
  }
}

function importarModelo() {
  return concluirImportacaoPlano(() => importarPlanoPadrao(modeloEscolhido.value, 1))
}

async function importarArquivo() {
  const arquivo = arquivoPlano.value
  if (!arquivo) return
  const nome = arquivo.name.toLowerCase()
  const formato = nome.endsWith('.csv') ? 'csv' : nome.endsWith('.json') ? 'json' : null
  if (!formato) {
    erroImportacaoPlano.value = 'Envie um arquivo .csv ou .json.'
    return
  }
  const conteudo = await arquivo.text()
  await concluirImportacaoPlano(() => importarPlanoArquivo(conteudo, formato, 1))
}

function abrirModalNovaConta() {
  dispararToast('Para adicionar ou revisar contas, utilize o plano oficial aprovado pela contabilidade.', 'warning')
}

// ---------------------------------------------------------------------------
// ABA 4: LIVROS & DEMONSTRAÇÕES
// ---------------------------------------------------------------------------
const tipoRelatorio = ref<'trial-balance' | 'income-statement' | 'balance-sheet' | 'journal' | 'ledger'>('trial-balance')
const contaRazaoId = ref<number>(0)
const filtroRelatorios = ref({
  data_inicio: `${anoAtual}-01-01`,
  data_fim: `${anoAtual}-12-31`,
})
const relatorioCarregando = ref(false)

// Centro de custo (processo, caso, cliente, ramo) — só no balancete e na DRE.
const centroCusto = ref<CentroCusto | null>(null)
const centroCustoAplicado = ref('')
const relatorioAceitaCentroCusto = computed(() => ['trial-balance', 'income-statement'].includes(tipoRelatorio.value))
const ROTULOS_CENTRO_CUSTO: Record<string, string> = { processo: 'Processo', caso: 'Caso', cliente: 'Cliente', ramo_direito: 'Ramo do direito' }
watch(centroCusto, (c) => {
  // Recalcula ao escolher um centro de custo ou limpar o filtro; só o tipo, sem id, espera o usuário escolher.
  if (relatorioAceitaCentroCusto.value && (!c || c.id)) carregarRelatorioAtual()
})
function rotuloCentroCusto(c: CentroCusto | null): string {
  if (!c) return ''
  return `${ROTULOS_CENTRO_CUSTO[c.tipo]}${c.id ? ': ' + (c.nome || '#' + c.id) : ' (todos)'}`
}
const linhasRelatorio = ref<any[]>([])
const lancamentosDiario = ref<LancamentoDiario[]>([])
const totalDiario = ref(0)
const secoesRazao = ref<SecaoLivroRazao[]>([])

const totalMovimentacoesRazao = computed(() =>
  secoesRazao.value.reduce((acc, s) => acc + (s.rows?.length || 0), 0),
)

function rotuloContaDiario(id: number) {
  const c = contas.value.find((x) => x.id === id)
  return c ? `${c.code} - ${c.name}` : `Conta #${id}`
}

/**
 * O pacote contábil entrega os saldos do razão no sinal da própria natureza da conta:
 * positivo está no lado normal (D para DEBIT, C para CREDIT) e negativo está invertido.
 */
function formatarSaldoRazao(centavos: number, nature?: string) {
  const valor = formatCurrency(Math.abs(centavos || 0) / 100)
  if (!centavos) return valor
  const normalDebito = nature !== 'CREDIT'
  const lado = centavos > 0 ? (normalDebito ? 'D' : 'C') : (normalDebito ? 'C' : 'D')
  return `${valor} ${lado}`
}

const tituloRelatorio = computed(() => {
  switch (tipoRelatorio.value) {
    case 'trial-balance': return 'Balancete de Verificação'
    case 'income-statement': return 'DRE - Demonstração do Resultado do Exercício'
    case 'balance-sheet': return 'Balanço Patrimonial'
    case 'journal': return 'Livro Diário'
    case 'ledger': return 'Livro Razão'
  }
})

const relatorioEhDemonstracao = computed(
  () => tipoRelatorio.value === 'income-statement' || tipoRelatorio.value === 'balance-sheet',
)

const ROTULOS_DEMONSTRACAO: Record<string, string> = {
  assets: 'Ativo',
  liabilities: 'Passivo',
  equity: 'Patrimônio líquido',
  revenue: 'Receitas',
  expenses: 'Despesas',
  net_result: 'Resultado líquido',
  current_result: 'Resultado do exercício (não encerrado)',
  liabilities_and_equity: 'Passivo + patrimônio líquido',
}

/**
 * DRE e balanço vêm do pacote como seções (árvores do plano, com `amount` e
 * `children`); a tabela mostra cada seção com o total e as contas recuadas.
 */
function achatarDemonstracao(res: { sections: Record<string, any[]>; totals?: Record<string, unknown> }): any[] {
  const linhas: any[] = []
  const percorrer = (nos: any[], nivel: number) => {
    for (const no of nos) {
      linhas.push({ ...no, nivel })
      percorrer(no.children || [], nivel + 1)
    }
  }

  for (const [secao, nos] of Object.entries(res.sections)) {
    const total = res.totals?.[secao]
    linhas.push({ name: ROTULOS_DEMONSTRACAO[secao] || secao, amount: typeof total === 'number' ? total : 0, destaque: true, nivel: 0 })
    percorrer(nos, 1)
  }

  for (const chave of ['current_result', 'net_result', 'liabilities_and_equity']) {
    const valor = res.totals?.[chave]
    if (typeof valor === 'number') {
      linhas.push({ name: ROTULOS_DEMONSTRACAO[chave], amount: valor, destaque: true, nivel: 0 })
    }
  }

  return linhas
}

async function carregarRelatorioAtual() {
  relatorioCarregando.value = true
  try {
    let res: any
    if (tipoRelatorio.value === 'trial-balance') {
      res = await obterBalancete(filtroRelatorios.value.data_inicio, filtroRelatorios.value.data_fim, 1, centroCusto.value)
    } else if (tipoRelatorio.value === 'income-statement') {
      res = await obterDreContabil(filtroRelatorios.value.data_inicio, filtroRelatorios.value.data_fim, 1, centroCusto.value)
    } else if (tipoRelatorio.value === 'balance-sheet') {
      res = await obterBalancoPatrimonial(filtroRelatorios.value.data_fim)
    } else if (tipoRelatorio.value === 'journal') {
      if (!contas.value.length) await carregarPlanoContas()
      const diario = await obterLivroDiario(filtroRelatorios.value.data_inicio, filtroRelatorios.value.data_fim)
      lancamentosDiario.value = diario.entries
      totalDiario.value = diario.total
      secoesRazao.value = []
      linhasRelatorio.value = diario.entries as any[]
      return
    } else {
      if (!contas.value.length) await carregarPlanoContas()
      const razao = await obterLivroRazao(
        contaRazaoId.value || null,
        filtroRelatorios.value.data_inicio,
        filtroRelatorios.value.data_fim,
      )
      const ativas = (razao.sections || []).filter(
        (s) => (s.rows && s.rows.length > 0) || s.opening !== 0 || s.debits !== 0 || s.credits !== 0,
      )
      secoesRazao.value = ativas
      lancamentosDiario.value = []
      linhasRelatorio.value = ativas as any[]
      return
    }

    lancamentosDiario.value = []
    secoesRazao.value = []
    centroCustoAplicado.value = rotuloCentroCusto(centroCusto.value)
    if (centroCusto.value && res && !Array.isArray(res) && !res.dimension) {
      dispararToast('O módulo contábil instalado ainda não filtra por centro de custo; o relatório mostra todos os lançamentos.', 'warning')
      centroCustoAplicado.value = ''
    }
    if (Array.isArray(res)) {
      linhasRelatorio.value = res
    } else if (res?.sections && typeof res.sections === 'object') {
      linhasRelatorio.value = achatarDemonstracao(res)
    } else if (res?.rows && Array.isArray(res.rows)) {
      linhasRelatorio.value = res.rows
    } else if (res?.accounts && Array.isArray(res.accounts)) {
      linhasRelatorio.value = res.accounts
    } else {
      linhasRelatorio.value = []
    }
  } catch (e: any) {
    linhasRelatorio.value = []
    lancamentosDiario.value = []
    secoesRazao.value = []
    dispararToast('Erro ao processar relatório contábil.', 'danger')
  } finally {
    relatorioCarregando.value = false
  }
}

function imprimirRelatorio() {
  // Libera o layout (altura fixa/rolagem interna) para imprimir o relatório inteiro.
  const limpar = () => {
    document.body.classList.remove('imprimindo-relatorio')
    window.removeEventListener('afterprint', limpar)
  }
  document.body.classList.add('imprimindo-relatorio')
  window.addEventListener('afterprint', limpar)
  window.print()
}

// ---------------------------------------------------------------------------
// ABA 5: EXERCÍCIOS FISCAIS
// ---------------------------------------------------------------------------
const periodosSimulados = ref([
  { id: 1, rotulo: 'Janeiro/2026', start_date: '2026-01-01', end_date: '2026-01-31', status: 'OPEN' },
  { id: 2, rotulo: 'Fevereiro/2026', start_date: '2026-02-01', end_date: '2026-02-28', status: 'OPEN' },
  { id: 3, rotulo: 'Março/2026', start_date: '2026-03-01', end_date: '2026-03-31', status: 'OPEN' },
  { id: 4, rotulo: 'Abril/2026', start_date: '2026-04-01', end_date: '2026-04-30', status: 'OPEN' },
  { id: 5, rotulo: 'Maio/2026', start_date: '2026-05-01', end_date: '2026-05-31', status: 'OPEN' },
  { id: 6, rotulo: 'Junho/2026', start_date: '2026-06-01', end_date: '2026-06-30', status: 'OPEN' },
  { id: 7, rotulo: 'Julho/2026', start_date: '2026-07-01', end_date: '2026-07-31', status: 'OPEN' },
  { id: 8, rotulo: 'Agosto/2026', start_date: '2026-08-01', end_date: '2026-08-31', status: 'OPEN' },
  { id: 9, rotulo: 'Setembro/2026', start_date: '2026-09-01', end_date: '2026-09-30', status: 'OPEN' },
  { id: 10, rotulo: 'Outubro/2026', start_date: '2026-10-01', end_date: '2026-10-31', status: 'OPEN' },
  { id: 11, rotulo: 'Novembro/2026', start_date: '2026-11-01', end_date: '2026-11-30', status: 'OPEN' },
  { id: 12, rotulo: 'Dezembro/2026', start_date: '2026-12-01', end_date: '2026-12-31', status: 'OPEN' },
])

function alternarStatusPeriodo(p: any) {
  p.status = p.status === 'OPEN' ? 'CLOSED' : 'OPEN'
  dispararToast(`Período ${p.rotulo} ${p.status === 'OPEN' ? 'reaberto' : 'fechado'}.`, 'success')
}

function sincronizarComView(view?: string) {
  if (view === 'contabil-lancamentos') {
    abaAtiva.value = 'lancamentos'
    carregarLancamentos()
  } else if (view === 'contabil-plano-contas') {
    abaAtiva.value = 'contas'
    carregarPlanoContas()
  } else if (view === 'contabil-relatorios' || view === 'contabil-livros') {
    abaAtiva.value = 'relatorios'
    carregarRelatorioAtual()
  } else if (view === 'contabil-periodos') {
    abaAtiva.value = 'periodos'
  } else {
    abaAtiva.value = 'importar'
    carregarTransacoesIntegracao()
  }
}

onMounted(() => {
  sincronizarComView(props.initialView)
})

watch(
  () => props.initialView,
  (novaView) => {
    sincronizarComView(novaView)
  }
)
</script>

<style scoped>
.entidade-badge {
  padding: 6px 12px;
  font-size: .78rem;
}

/* Abas */
.contabil-tabs {
  max-width: 100%;
  overflow-x: auto;
}
.contabil-tabs .filter-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.tab-count {
  background: var(--accent);
  color: #fff;
  border-radius: 20px;
  padding: 0 7px;
  font-size: .7rem;
  font-weight: 700;
  line-height: 1.5;
}

/* KPIs */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 16px 18px;
  border-left: 4px solid var(--border);
}
.kpi-label { font-size: .78rem; font-weight: 500; color: var(--ink-muted); }
.kpi-value { font-size: 1.6rem; line-height: 1.2; color: var(--ink); }
.kpi-value-text { font-size: 1.15rem; padding: 4px 0; font-family: var(--font-head); }
.kpi-sub { font-size: .78rem; color: var(--ink-muted); }
.kpi-warning { border-left-color: var(--warning); }
.kpi-warning .kpi-value, .kpi-warning .kpi-label { color: var(--warning); }
.kpi-success { border-left-color: var(--success); }
.kpi-success .kpi-value, .kpi-success .kpi-label { color: var(--success); }
.kpi-brand { background: var(--primary); border-color: var(--primary); }
.kpi-brand .kpi-label, .kpi-brand .kpi-sub { color: rgba(255, 255, 255, .7); }
.kpi-brand .kpi-value { color: #fff; }

/* Barra de filtros */
.toolbar-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 14px 18px;
  margin-bottom: 18px;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 12px;
}
.toolbar-card .toolbar-left { align-items: flex-end; gap: 12px; }
.field { display: flex; flex-direction: column; min-width: 150px; }
.field-wide { min-width: 250px; }
.field .form-label { margin-bottom: 4px; }
.search-box { position: relative; width: min(360px, 100%); }
.search-box i {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--ink-muted); pointer-events: none;
}
.search-box .form-control { padding-left: 34px; }

/* Tabelas */
.table-card, .relatorio-card { overflow: hidden; }
.table-wrap { overflow-x: auto; }
.paginacao {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  border-top: 1px solid var(--border, #e2e8f0);
  font-size: .82rem;
}
.paginacao-info { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.paginacao-tamanho { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
.paginacao-tamanho .form-control { width: auto; padding-top: 2px; padding-bottom: 2px; }
.paginacao-botoes { display: flex; gap: 4px; }
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .84rem;
  color: var(--ink);
}
.data-table th {
  padding: 11px 14px;
  text-align: left;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--ink-muted);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.data-table td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--primary-lt); }
.data-table.striped tbody tr:nth-child(even) { background: var(--surface); }
.data-table.striped tbody tr:nth-child(even):hover { background: var(--primary-lt); }
.data-table.compact td { padding: 8px 10px; }
.data-table.compact th { padding: 8px 10px; }
.num { text-align: right !important; font-variant-numeric: tabular-nums; }
.center { text-align: center !important; }
.nowrap { white-space: nowrap; }
.strong { font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; }
.muted { color: var(--ink-muted); }
.small { font-size: .8rem; }
.col-check { width: 40px; text-align: center !important; }
.col-code { width: 150px; }
.row-done { color: var(--ink-muted); background: var(--surface); }
.row-group td { font-weight: 600; background: var(--surface); }
.select-conta { min-width: 190px; max-width: 240px; padding: 6px 28px 6px 10px; }
.val-debito { color: var(--success); }
.val-credito { color: var(--danger); }
.conta-icon { margin-right: 6px; color: var(--ink-muted); }
.conta-pasta { color: var(--primary); }
.ok-icon { color: var(--success); }
input[type='checkbox'] { accent-color: var(--primary); width: 15px; height: 15px; cursor: pointer; }

.state-cell {
  text-align: center;
  padding: 40px 20px !important;
  color: var(--ink-muted);
  font-size: .88rem;
}
.state-cell i { display: block; font-size: 2rem; opacity: .35; margin-bottom: 8px; }
.state-cell .spinner { display: inline-block; vertical-align: middle; margin-right: 8px; }

/* Relatório */
.relatorio-head {
  text-align: center;
  padding: 24px 20px 18px;
  border-bottom: 1px solid var(--border);
}
.relatorio-head h2 { font-family: var(--font-head); font-size: 1.25rem; color: var(--ink); }
.diario-head td { background: var(--surface-2, rgba(0, 0, 0, .04)); border-top: 1px solid var(--border, #ddd); }
.razao-fechamento td { background: var(--surface, rgba(0, 0, 0, .02)); border-bottom: 2px solid var(--border, #ddd); }
.diario-rodape { padding: 12px 16px; font-size: .85rem; color: var(--ink-muted); text-align: right; }
.relatorio-head h3 { font-size: 1rem; font-weight: 600; color: var(--primary); margin: 4px 0; }
.relatorio-head p { font-size: .8rem; color: var(--ink-muted); }

/* Modal */
.btn-dividir { margin-left: 6px; }
.categorias-linha { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-top: 4px; font-weight: 400; }
.categoria-chip { padding: 1px 6px; border-radius: 999px; background: var(--bg-muted, rgba(127, 127, 127, .12)); }
.modal-box.modal-dicionario { max-width: 620px; }
.lista-dicionario { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.lista-dicionario label { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; cursor: pointer; }
.modal-box.modal-lancamento { max-width: 760px; }
.modal-box.modal-abertura { max-width: 820px; }
.abertura-ajuda { font-size: .82rem; color: var(--ink-dim); margin-bottom: 12px; }
.abertura-acoes { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0 8px; }
.abertura-wrap { max-height: 340px; overflow-y: auto; }
.form-grid {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 14px;
}
.modal-section-title {
  font-size: .85rem;
  font-weight: 600;
  color: var(--ink);
  margin: 6px 0 8px;
}
.partidas-wrap { border: 1px solid var(--border); border-radius: 10px; }
.col-lado { width: 140px; }
.col-valor { width: 150px; }
.col-remove { width: 48px; }
.btn-remove { padding: 5px 8px; }
.partidas-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 12px;
}
.totais { display: flex; align-items: center; gap: 14px; font-size: .82rem; color: var(--ink-dim); }
.totais strong { color: var(--ink); }
.aviso-balanco {
  margin-top: 12px;
  padding: 9px 12px;
  border-radius: 8px;
  background: #fef9c3;
  color: #92400e;
  font-size: .8rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.doc-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  font-size: .82rem;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--border-color, #e2e8f0);
  color: var(--ink-muted, #64748b);
  background: var(--surface-card, #ffffff);
  transition: all .2s ease;
}
.doc-link-btn:hover {
  color: var(--primary, #0f172a);
  border-color: var(--primary, #0f172a);
  background: var(--surface-hover, #f8fafc);
}

.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
  .toolbar-card .toolbar-right { margin-left: 0; width: 100%; }
  .field, .field-wide { min-width: 100%; }
  .toolbar-card .toolbar-left { width: 100%; }
}

@media print {
  .contabil-tabs,
  .page-header,
  .breadcrumb,
  .no-print {
    display: none !important;
  }

  #area-relatorio-contabil {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
