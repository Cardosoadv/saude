<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#/dashboard" @click.exact.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#/clientes" @click.exact.prevent="$emit('set-view', 'clientes')">Clientes</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isNew ? 'Novo Cliente' : (form.nome || 'Editar Cliente') }}</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>{{ isNew ? 'Novo Cliente' : 'Editar Cliente' }}</h1>
        <p>{{ isNew ? 'Cadastre um novo cliente na sua carteira' : 'Gerencie e visualize dados consolidados do cliente' }}</p>
      </div>
      <div class="header-actions">
        <button
          v-if="podeEnriquecer"
          type="button"
          class="btn btn-ghost"
          @click="showEnriquecerModal = true"
          :title="`Completar endereço e dados cadastrais pelo ${documentoDigitos.length === 14 ? 'CNPJ (Receita Federal)' : 'CPF (API Brasil)'}`"
        >
          <i class="bi bi-stars" style="color:var(--primary);"></i> Enriquecer Dados
        </button>
        <button
          v-if="!isNew && !semAcesso && ehFornecedor && liberado('fornecedores', 'fornecedores.access')"
          type="button"
          class="btn btn-ghost"
          @click="$emit('open-supplier-detail', Number(props.clientId))"
          title="Este cadastro também é fornecedor: abrir a ficha de fornecedor"
        >
          <i class="bi bi-truck" style="color:var(--primary);"></i> Ver ficha de fornecedor
        </button>
        <button
          v-else-if="!isNew && !semAcesso && !ehFornecedor && liberado('fornecedores', 'fornecedores.create')"
          type="button"
          class="btn btn-ghost"
          @click="showTransformModal = true"
          title="Marcar este cadastro também como fornecedor (mesma pessoa, sem cópia)"
        >
          <i class="bi bi-truck" style="color:var(--primary);"></i> Marcar como Fornecedor
        </button>
        <a
          href="#/clientes"
          class="btn btn-ghost"
          @click.exact.prevent="$emit('set-view', 'clientes')"
        >
          <i class="bi bi-arrow-left-circle"></i> Voltar
        </a>
        <button v-if="!semAcesso" type="button" class="btn btn-primary" @click="saveClient" :disabled="saving">
          <i class="bi bi-save"></i> {{ saving ? 'Salvando...' : 'Salvar Alterações' }}
        </button>
      </div>
    </div>

    <!-- Cliente inexistente ou restrito (privacidade granular) -->
    <div v-if="semAcesso" class="card sem-acesso">
      <div class="card-body">
        <i class="bi bi-lock"></i>
        <p><strong>Cliente não encontrado ou sem permissão de acesso.</strong></p>
        <p>Se você precisa acessar este cliente, peça ao responsável ou a um administrador.</p>
      </div>
    </div>

    <!-- Workspace Grid -->
    <div v-else :class="isNew ? '' : 'content-grid'">
      <!-- Main Form Column Left -->
      <div>
        <div class="card form-card">
          <div class="card-header">
            <h3><i class="bi bi-person-fill-gear"></i> Dados do Cadastro</h3>
            <span v-if="ehFornecedor" class="tag-papel" title="Mesmo cadastro em Fornecedores">
              <i class="bi bi-truck"></i> Também é fornecedor
            </span>
          </div>

          <div class="card-body">
            <!-- CRM and Temperature Row -->
            <div class="form-row col-3">
              <div class="form-group">
                <label for="status_crm" class="form-label"><i class="bi bi-funnel"></i> Status CRM</label>
                <select v-model="form.status_crm" id="status_crm" class="form-control form-select">
                  <option value="lead">Lead</option>
                  <option value="prospect">Oportunidade</option>
                  <option value="client">Cliente</option>
                  <option value="inactive">Inativo</option>
                </select>
              </div>

              <div class="form-group">
                <label for="temperatura" class="form-label"><i class="bi bi-thermometer-half"></i> Temperatura</label>
                <select v-model="form.temperatura" id="temperatura" class="form-control form-select">
                  <option value="">Nenhuma</option>
                  <option value="hot">🔥 Quente</option>
                  <option value="warm">⚖️ Morno</option>
                  <option value="cold">❄️ Frio</option>
                </select>
              </div>

              <div class="form-group">
                <label for="origem_id" class="form-label"><i class="bi bi-box-arrow-in-right"></i> Origem</label>
                <select v-model="form.origem_id" id="origem_id" class="form-control form-select">
                  <option value="">Selecione a Origem</option>
                  <option v-for="o in origens" :key="o.id" :value="o.id">{{ o.nome }}</option>
                </select>
              </div>
            </div>

            <!-- Cliente Type and Document Row -->
            <div class="form-row col-3">
              <div class="form-group">
                <label for="tipo_cliente" class="form-label"><i class="bi bi-person-badge"></i> Tipo de Cliente</label>
                <select v-model="form.tipo_cliente" id="tipo_cliente" class="form-control form-select">
                  <option value="F">Pessoa Física</option>
                  <option value="J">Pessoa Jurídica</option>
                </select>
              </div>

              <div class="form-group">
                <label for="nome" class="form-label required-field"><i class="bi bi-person"></i> Nome / Nome Fantasia</label>
                <input type="text" v-model="form.nome" id="nome" class="form-control" required placeholder="Digite o nome completo" />
              </div>

              <div class="form-group">
                <label for="documento" :class="['form-label', { 'required-field': form.status_crm === 'client' }]"><i class="bi bi-file-earmark-person"></i> CPF / CNPJ</label>
                <input
                  type="text"
                  v-model="form.documento"
                  id="documento"
                  @input="formatDocumentInput"
                  class="form-control"
                  :required="form.status_crm === 'client'"
                  placeholder="000.000.000-00"
                />
              </div>
            </div>

            <!-- Razão Social (Only for Pessoa Jurídica) -->
            <div class="form-row col-1" v-if="form.tipo_cliente === 'J'">
              <div class="form-group">
                <label for="razao_social" class="form-label"><i class="bi bi-building"></i> Razão Social</label>
                <input type="text" v-model="form.razao_social" id="razao_social" class="form-control" placeholder="Razão Social completa" />
              </div>
            </div>

            <!-- Additional Client Specs -->
            <div class="form-row col-4">
              <div class="form-group">
                <label for="nacionalidade" class="form-label"><i class="bi bi-flag"></i> Nacionalidade</label>
                <input type="text" v-model="form.nacionalidade" id="nacionalidade" class="form-control" />
              </div>

              <div class="form-group">
                <label for="estado_civil" class="form-label"><i class="bi bi-heart"></i> Estado Civil</label>
                <input type="text" v-model="form.estado_civil" id="estado_civil" class="form-control" />
              </div>

              <div class="form-group">
                <label for="data_nascimento" class="form-label"><i class="bi bi-calendar"></i> Data de Nascimento</label>
                <input type="date" v-model="form.data_nascimento" id="data_nascimento" class="form-control" />
              </div>

              <div class="form-group">
                <label for="profissao" class="form-label"><i class="bi bi-briefcase"></i> Profissão</label>
                <input type="text" v-model="form.profissao" id="profissao" class="form-control" />
              </div>
            </div>

            <!-- Contacts and Cloud Drive -->
            <div class="form-row col-3">
              <div class="form-group">
                <label for="email" class="form-label"><i class="bi bi-envelope"></i> E-mail</label>
                <input type="email" v-model="form.email" id="email" class="form-control" placeholder="cliente@provedor.com" />
              </div>

              <div class="form-group">
                <label for="telefone" class="form-label"><i class="bi bi-telephone"></i> Telefone / Celular</label>
                <input type="text" v-model="form.telefone" id="telefone" @input="formatPhoneInput" class="form-control" placeholder="(00) 00000-0000" />
              </div>

              <div class="form-group">
                <label for="pasta" class="form-label"><i class="bi bi-folder2-open"></i> Link Drive do Cliente</label>
                <div style="display:flex; gap:6px; align-items:center;">
                  <input type="text" v-model="form.pasta" id="pasta" class="form-control" placeholder="https://drive.google.com/..." />
                  <a
                    v-if="form.pasta"
                    :href="form.pasta"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-ghost"
                    style="padding:8px 10px;"
                    title="Abrir pasta do cliente"
                    aria-label="Abrir pasta do cliente"
                  >
                    <i class="bi bi-box-arrow-up-right"></i>
                  </a>
                </div>
              </div>
            </div>

            <!-- Address Group with CEP Lookup -->
            <div class="form-row address-row">
              <div class="form-group">
                <label for="cep" class="form-label"><i class="bi bi-mailbox"></i> CEP</label>
                <input
                  type="text"
                  v-model="form.cep"
                  id="cep"
                  @input="handleCepInput"
                  class="form-control"
                  placeholder="00000-000"
                />
              </div>

              <div class="form-group">
                <label for="endereco" class="form-label"><i class="bi bi-geo-alt"></i> Endereço</label>
                <input type="text" v-model="form.endereco" id="endereco" class="form-control" placeholder="Rua, Av..." />
              </div>

              <div class="form-group">
                <label for="numero" class="form-label"><i class="bi bi-123"></i> Número</label>
                <input type="text" v-model="form.numero" id="numero" class="form-control" placeholder="123" />
              </div>

              <div class="form-group">
                <label for="complemento" class="form-label"><i class="bi bi-info-square"></i> Complemento</label>
                <input type="text" v-model="form.complemento" id="complemento" class="form-control" placeholder="Sala, Apto..." />
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="cidade" class="form-label"><i class="bi bi-pin-map"></i> Cidade</label>
                <input type="text" v-model="form.cidade" id="cidade" class="form-control" placeholder="Nome da cidade" />
              </div>

              <div class="form-group">
                <label for="uf" class="form-label"><i class="bi bi-globe"></i> Estado (UF)</label>
                <input type="text" v-model="form.uf" id="uf" class="form-control" maxlength="2" placeholder="SP" style="text-transform:uppercase;" />
              </div>
            </div>

            <!-- Additional configs -->
            <div class="form-row col-2">
              <div class="form-group">
                <label for="dataAquisicao" class="form-label"><i class="bi bi-calendar-check"></i> Data de Aquisição</label>
                <input type="date" v-model="form.dataAquisicao" id="dataAquisicao" class="form-control" />
              </div>

              <div class="form-group">
                <label for="ativo" class="form-label"><i class="bi bi-toggle-on"></i> Ativo</label>
                <select v-model="form.ativo" id="ativo" class="form-control form-select">
                  <option value="1">Ativo</option>
                  <option value="0">Inativo</option>
                </select>
              </div>
            </div>

            <!-- Privacidade granular -->
            <div class="form-row col-2" style="margin-bottom:0;">
              <div class="form-group" style="margin-bottom:0;">
                <label for="responsavel_id" class="form-label"><i class="bi bi-person-badge"></i> Responsável</label>
                <ResponsavelSelect id="responsavel_id" v-model="form.responsavel_id" :disabled="!isNew && !podeGerenciarAcessos" />
                <small v-if="isNew" class="form-hint">Em branco, você será o responsável.</small>
                <small v-else-if="!podeGerenciarAcessos" class="form-hint">Só o responsável ou um administrador pode trocar.</small>
              </div>
              <div class="form-group" style="margin-bottom:0;" v-if="isNew">
                <label for="visibilidade" class="form-label"><i class="bi bi-lock"></i> Visibilidade</label>
                <select v-model="form.visibilidade" id="visibilidade" class="form-control form-select">
                  <option value="restrito">Restrito: você, o responsável e quem for liberado</option>
                  <option value="publico">Público: todos os advogados</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Dados bancários do cadastro (repasses ao cliente; pagamentos se também for fornecedor) -->
        <div class="card form-card" style="margin-top:1.5rem;">
          <div class="card-header">
            <h3><i class="bi bi-bank"></i> Dados Bancários & PIX</h3>
          </div>
          <div class="card-body">
            <div class="form-row col-3">
              <div class="form-group">
                <label for="banco" class="form-label">Banco</label>
                <input type="text" v-model="form.banco" id="banco" class="form-control" placeholder="Ex: Itaú" />
              </div>
              <div class="form-group">
                <label for="agencia" class="form-label">Agência</label>
                <input type="text" v-model="form.agencia" id="agencia" class="form-control" placeholder="0001" />
              </div>
              <div class="form-group">
                <label for="conta" class="form-label">Conta</label>
                <input type="text" v-model="form.conta" id="conta" class="form-control" placeholder="12345-6" />
              </div>
            </div>
            <div class="form-row col-2" style="margin-bottom:0;">
              <div class="form-group" style="margin-bottom:0;">
                <label for="tipo_conta" class="form-label">Tipo de Conta</label>
                <select v-model="form.tipo_conta" id="tipo_conta" class="form-control form-select">
                  <option value="">Selecione...</option>
                  <option value="corrente">Conta Corrente</option>
                  <option value="poupanca">Poupança</option>
                  <option value="pagamento">Pagamento</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label for="pix" class="form-label">Chave PIX</label>
                <input type="text" v-model="form.pix" id="pix" class="form-control" placeholder="E-mail, CPF/CNPJ ou celular" />
              </div>
            </div>
          </div>
        </div>

        <!-- Dynamic Panels Tabs (Only in edit mode) -->
        <div class="card tabs-card" v-if="!isNew">
          <div class="tabs-header">
            <div class="tabs" role="tablist" aria-label="Vínculos e histórico do cliente">
              <button
                v-for="t in tabs"
                :key="t.id"
                type="button"
                role="tab"
                :aria-selected="activeTab === t.id"
                :class="['tab-btn', { active: activeTab === t.id }]"
                @click="activeTab = t.id"
              >
                <i :class="t.icon"></i>
                <span>{{ t.label }}</span>
                <span v-if="getTabCount(t.id) !== null" class="tab-count">{{ getTabCount(t.id) }}</span>
              </button>
            </div>
          </div>

          <div class="tab-content">
            <!-- Processos Tab -->
            <div v-if="activeTab === 'processos'" class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Número CNJ</th>
                    <th>Título / Ação</th>
                    <th class="center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in processes" :key="p.id_processo">
                    <td class="nowrap">
                      <a
                        :href="'#/processos/detalhes/' + p.id_processo"
                        class="table-link"
                        @click.exact.prevent="$emit('open-process-detail', p.id_processo)"
                      >
                        <i class="bi bi-folder2-open"></i>
                        {{ formatCNJ(p.numeroprocessocommascara || p.numero_processo) }}
                      </a>
                    </td>
                    <td>
                      <a
                        v-if="p.titulo_processo || p.titulo || p.acao"
                        :href="'#/processos/detalhes/' + p.id_processo"
                        class="table-link-secondary"
                        @click.exact.prevent="$emit('open-process-detail', p.id_processo)"
                      >
                        {{ p.titulo_processo || p.titulo || p.acao }}
                      </a>
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td class="center nowrap">
                      <span :class="['badge', getProcessoBadgeClass(p)]">
                        {{ getProcessoStatusLabel(p) }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!processes.length">
                    <td colspan="3" class="table-empty">
                      <i class="bi bi-folder-x"></i>
                      <span>Nenhum processo vinculado a este cliente.</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Casos Tab -->
            <div v-if="activeTab === 'casos'" class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Título do Caso</th>
                    <th>Descrição</th>
                    <th class="center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in cases" :key="c.id_caso">
                    <td>
                      <a
                        :href="'#/casos/detalhes/' + c.id_caso"
                        class="table-link"
                        @click.exact.prevent="$emit('open-caso-detail', c.id_caso)"
                      >
                        <i class="bi bi-briefcase"></i>
                        {{ c.titulo }}
                      </a>
                    </td>
                    <td>{{ c.descricao || c.tipo || '—' }}</td>
                    <td class="center nowrap">
                      <span :class="['badge', getCasoBadgeClass(c.status)]">
                        {{ c.status || 'Ativo' }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!cases.length">
                    <td colspan="3" class="table-empty">
                      <i class="bi bi-briefcase"></i>
                      <span>Nenhum caso extrajudicial cadastrado.</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Financeiro Tab -->
            <div v-if="activeTab === 'financeiro'" class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Descrição</th>
                    <th class="num">Valor</th>
                    <th class="nowrap">Data Movimento</th>
                    <th class="center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="m in financial" :key="m.id_pgto_receita || m.id_receita || m.id_financeiro || m.id">
                    <td>
                      <a
                        v-if="getFinanceiroHref(m)"
                        :href="getFinanceiroHref(m)"
                        class="table-link"
                        @click.exact.prevent="openFinanceiro(m)"
                      >
                        <i class="bi bi-cash-coin"></i>
                        {{ m.descricao }}
                      </a>
                      <span v-else class="strong">{{ m.descricao }}</span>
                    </td>
                    <td class="num nowrap" style="font-weight:600;color:var(--success);">{{ formatCurrency(m.valor) }}</td>
                    <td class="nowrap">{{ formatDate(m.data) }}</td>
                    <td class="center nowrap">
                      <span :class="['badge', Number(m.pago) === 1 ? 'badge-success' : 'badge-warning']">
                        {{ Number(m.pago) === 1 ? 'Pago' : 'Pendente' }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!financial.length">
                    <td colspan="4" class="table-empty">
                      <i class="bi bi-cash-coin"></i>
                      <span>Nenhuma receita ou honorário lançado.</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Intimações Tab -->
            <div v-if="activeTab === 'intimacoes'" class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Publicação / Processo</th>
                    <th class="nowrap">Data Publicação</th>
                    <th>Conteúdo da Publicação</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="i in intimacoes" :key="i.id_intimacao">
                    <td class="nowrap">
                      <a
                        :href="'#/intimacoes/detalhes/' + i.id_intimacao"
                        class="table-link"
                        @click.exact.prevent="$emit('open-intimacao-detail', i.id_intimacao)"
                      >
                        <i class="bi bi-envelope-paper"></i>
                        {{ i.diario || i.tipoComunicacao || ('Intimação #' + i.id_intimacao) }}
                      </a>
                      <div v-if="i.numeroprocessocommascara || i.numero_processo" style="margin-top:2px;">
                        <a
                          v-if="i.id_processo"
                          :href="'#/processos/detalhes/' + i.id_processo"
                          class="table-sublink"
                          @click.exact.prevent="$emit('open-process-detail', i.id_processo)"
                        >
                          Processo: {{ formatCNJ(i.numeroprocessocommascara || i.numero_processo) }}
                        </a>
                        <small v-else class="text-muted">Processo: {{ formatCNJ(i.numeroprocessocommascara || i.numero_processo) }}</small>
                      </div>
                    </td>
                    <td class="nowrap">{{ formatDate(i.data_publicacao || i.data_intimacao || i.data_disponibilizacao) }}</td>
                    <td>
                      <a
                        :href="'#/intimacoes/detalhes/' + i.id_intimacao"
                        class="table-link-secondary intimacao-excerpt"
                        @click.exact.prevent="$emit('open-intimacao-detail', i.id_intimacao)"
                      >
                        {{ i.conteudo || i.texto || 'Visualizar íntegra da publicação' }}
                      </a>
                    </td>
                  </tr>
                  <tr v-if="!intimacoes.length">
                    <td colspan="3" class="table-empty">
                      <i class="bi bi-mailbox"></i>
                      <span>Nenhuma intimação associada cadastrada.</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- CRM Histórico Tab -->
            <div v-if="activeTab === 'historico'" class="tab-body-padded">
              <div class="timeline animate-fade-in" v-if="notes.length">
                <div class="timeline-item" v-for="n in notes" :key="`${n.tipo || 'nota'}-${n.id_anotacao}`">
                  <div class="timeline-badge"><i class="bi bi-chat-square-text-fill"></i></div>
                  <div class="timeline-card">
                    <div class="timeline-header">
                      <div>
                        <strong>{{ n.titulo || 'Nota CRM' }}</strong>
                        <a
                          v-if="n.entidade_id"
                          :href="getNotaHref(n)"
                          class="timeline-ref-link"
                          @click.exact.prevent="openNotaEntidade(n)"
                        >
                          <i :class="n.tipo === 'caso' ? 'bi bi-briefcase' : 'bi bi-folder2-open'"></i>
                          {{ n.referencia || (n.tipo === 'caso' ? `Caso #${n.entidade_id}` : `Processo #${n.entidade_id}`) }}
                        </a>
                      </div>
                      <span class="timeline-date">{{ formatDate(n.created_at) }}</span>
                    </div>
                    <p class="timeline-body">{{ n.conteudo }}</p>
                  </div>
                </div>
              </div>
              <div class="table-empty" v-else>
                <i class="bi bi-chat-left-dots"></i>
                <span>Nenhuma anotação de interação de CRM registrada para este cliente.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets" v-if="!isNew">
        <AcessosCard
          tipo="cliente"
          :entidade-id="props.clientId"
          :responsavel-id="responsavelSalvo"
          @toast="(msg) => $emit('toast', msg)"
          @loaded="a => podeGerenciarAcessos = !!a?.pode_gerenciar"
        />
        <CrmTimelineWidget v-if="liberado('crm')" :clientId="props.clientId" @toast="(msg) => $emit('toast', msg)" />
        <ClientNotesWidget :clientId="props.clientId" :documento="form.documento" @toast="(msg) => $emit('toast', msg)" />

        <!-- Client stats card -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-activity"></i> Indicadores do Cliente</h3>
          </div>
          <ul class="widget-list">
            <li v-if="abaLiberada('processos')" style="padding:0;">
              <a
                :href="`#/clientes/editar/${props.clientId}`"
                class="indicator-link"
                @click.prevent="activeTab = 'processos'"
              >
                <span class="process-date" style="font-size:.82rem;"><i class="bi bi-file-earmark-code"></i> Processos Ativos</span>
                <span class="process-num" style="font-size:.82rem;">{{ clientStats.processos || 0 }}</span>
              </a>
            </li>
            <li v-if="abaLiberada('casos')" style="padding:0;">
              <a
                :href="`#/clientes/editar/${props.clientId}`"
                class="indicator-link"
                @click.prevent="activeTab = 'casos'"
              >
                <span class="process-date" style="font-size:.82rem;"><i class="bi bi-briefcase"></i> Casos Registrados</span>
                <span class="process-num" style="font-size:.82rem;">{{ clientStats.casos || 0 }}</span>
              </a>
            </li>
            <li v-if="abaLiberada('financeiro')" style="padding:0;">
              <a
                :href="`#/clientes/editar/${props.clientId}`"
                class="indicator-link"
                @click.prevent="activeTab = 'financeiro'"
              >
                <span class="process-date" style="font-size:.82rem;"><i class="bi bi-cash-coin"></i> Honorários Totais</span>
                <span class="process-num" style="color:var(--success); font-weight:700; font-size:.82rem;">{{ formatCurrency(clientStats.receitas || 0) }}</span>
              </a>
            </li>
            <li v-if="liberado('tarefas', 'tarefas.access')" style="padding:0;">
              <a
                href="#/tarefas"
                class="indicator-link"
                @click.exact.prevent="$emit('set-view', 'tarefas')"
              >
                <span class="process-date" style="font-size:.82rem;"><i class="bi bi-check2-square"></i> Tarefas Pendentes</span>
                <span class="process-num" style="font-size:.82rem;">{{ clientStats.tarefas || 0 }}</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- CRM temperatures details helper -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Diretrizes do CRM</h3>
          </div>
          <div style="padding:16px 18px; color:var(--ink-muted); font-size:0.82rem; line-height:1.5;">
            <p style="margin-bottom:0.75rem;"><strong style="color:var(--ink);">🔥 Quente</strong>: Negociações ativas de contrato de honorários ou reuniões iminentes.</p>
            <p style="margin-bottom:0.75rem;"><strong style="color:var(--ink);">⚖️ Morno</strong>: Cliente prospectado que está reunindo documentos.</p>
            <p><strong style="color:var(--ink);">❄️ Frio</strong>: Sem contato recente ou arquivado preventivamente.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Transformar em Fornecedor -->
    <ModalTransformarEmFornecedor
      :show="showTransformModal"
      :cliente="form"
      @close="showTransformModal = false"
      @toast="$emit('toast', $event)"
      @success="onTransformSuccess"
    />

    <ModalEnriquecerCadastro
      v-if="showEnriquecerModal && props.clientId"
      :show="showEnriquecerModal"
      tipo="cliente"
      :id="props.clientId"
      :nome="form.nome"
      :documento="form.documento"
      @close="showEnriquecerModal = false"
      @toast="$emit('toast', $event)"
      @aplicado="onEnriquecido"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * View de Detalhes e Edição de Cliente
 * @version 1.2.0
 */
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { apiFetch, ApiError, formatDate, formatCurrency, formatCNJ, formatDocument, formatPhone } from '../../services/api'
import { liberado, pode } from '../../composables/useAcesso'
import ModalEnriquecerCadastro from '../../components/Consultas/ModalEnriquecerCadastro.vue'
import { documentoEnriquecivel, somenteDigitos } from '../../services/consultasApi'
import AcessosCard from '../../components/widgets/Privacidade/AcessosCard.vue'
import ResponsavelSelect from '../../components/widgets/Privacidade/ResponsavelSelect.vue'
import CrmTimelineWidget from '../../components/widgets/CrmTimelineWidget.vue'
import ClientNotesWidget from '../../components/widgets/ClientNotesWidget.vue'
import ModalTransformarEmFornecedor from '../../components/modals/Clientes/ModalTransformarEmFornecedor.vue'

const props = defineProps({
  clientId: {
    type: Number,
    default: null
  }
})

const emit = defineEmits([
  'set-view',
  'toast',
  'open-process-detail',
  'open-caso-detail',
  'open-supplier-detail',
  'open-receita-detail',
  'open-receita-recebida-detail',
  'open-intimacao-detail'
])

const isNew = computed(() => !props.clientId)
const saving = ref(false)
const semAcesso = ref(false)
const podeGerenciarAcessos = ref(false)
const responsavelSalvo = ref('')
const showTransformModal = ref(false)
const showEnriquecerModal = ref(false)
/** Cadastro único: o mesmo registro pode ser cliente/CRM e fornecedor. */
const ehFornecedor = ref(false)

const documentoDigitos = computed(() => somenteDigitos(form.documento || ''))
/** Consultas (módulo gestão) + edição de clientes + CPF/CNPJ completo. */
const podeEnriquecer = computed(() =>
  !isNew.value && !semAcesso.value
  && liberado('gestao', 'consultas.access') && pode('clientes.edit')
  && documentoEnriquecivel(form.documento)
)

/** Campos já gravados pelo enriquecimento: reflete no formulário. */
function onEnriquecido(campos: Record<string, string>) {
  Object.entries(campos).forEach(([k, v]) => {
    if (k in form) form[k] = v
  })
}

function onTransformSuccess(payload: { fornecedorId: number; message: string }) {
  if (payload.fornecedorId) {
    emit('open-supplier-detail', payload.fornecedorId)
  } else {
    emit('set-view', 'fornecedores')
  }
}

// Active Tab for bottom related tables
const activeTab = ref('processos')

/** Cada aba é de um módulo: [slug, permissões aceitas] (mesmas regras da API). */
const REGRAS_ABAS: Record<string, [string, ...string[]]> = {
  processos: ['processos', 'processos.access', 'processos.exclusive'],
  casos: ['casos', 'casos.access'],
  financeiro: ['financeiro', 'financeiro.access'],
  intimacoes: ['intimacoes', 'intimacoes.access'],
  historico: ['crm'],
}
const abaLiberada = (id: string) => liberado(...REGRAS_ABAS[id])

const tabs = computed(() => [
  { id: 'processos', label: 'Processos', icon: 'bi bi-file-earmark-code' },
  { id: 'casos', label: 'Casos Extrajudiciais', icon: 'bi bi-briefcase' },
  { id: 'financeiro', label: 'Financeiro', icon: 'bi bi-cash-coin' },
  { id: 'intimacoes', label: 'Intimações', icon: 'bi bi-mailbox' },
  { id: 'historico', label: 'CRM / Histórico', icon: 'bi bi-chat-left-dots' }
].filter(t => abaLiberada(t.id)))

// Aba ativa sem acesso: passa para a primeira liberada.
watch(tabs, (visiveis) => {
  if (!visiveis.some(t => t.id === activeTab.value) && visiveis.length) {
    activeTab.value = visiveis[0].id
  }
}, { immediate: true })

// Models
const form = reactive({
  id_cliente: '',
  status_crm: 'client',
  temperatura: '',
  origem_id: '',
  tipo_cliente: 'F',
  nome: '',
  documento: '',
  pasta: '',
  nacionalidade: 'Brasileira',
  estado_civil: '',
  data_nascimento: '',
  profissao: '',
  email: '',
  telefone: '',
  cep: '',
  endereco: '',
  numero: '',
  complemento: '',
  cidade: '',
  uf: '',
  razao_social: '',
  dataAquisicao: new Date().toISOString().substring(0, 10),
  ativo: '1',
  responsavel_id: '',
  visibilidade: 'restrito',
  banco: '',
  agencia: '',
  conta: '',
  tipo_conta: '',
  pix: ''
}) as Record<string, any> & { id_cliente: number | string; nome: string }

// Sub-resource lists
const processes = ref<any[]>([])
const cases = ref<any[]>([])
const notes = ref<any[]>([])
const financial = ref<any[]>([])
const intimacoes = ref<any[]>([])

const clientStats = reactive({
  processos: 0,
  casos: 0,
  receitas: 0,
  tarefas: 0
})

const origens = ref<any[]>([])

function getTabCount(tabId: string): number | null {
  if (tabId === 'processos') return processes.value.length
  if (tabId === 'casos') return cases.value.length
  if (tabId === 'financeiro') return financial.value.length
  if (tabId === 'intimacoes') return intimacoes.value.length
  if (tabId === 'historico') return notes.value.length
  return null
}

function getProcessoBadgeClass(p: any): string {
  if (p.encerrado == 1) return 'badge-muted'
  if (p.arquived_at) return 'badge-warning'
  return p.ativo == 1 ? 'badge-success' : 'badge-secondary'
}

function getProcessoStatusLabel(p: any): string {
  if (p.encerrado == 1) return 'Encerrado'
  if (p.arquived_at) return 'Arquivado'
  return p.ativo == 1 ? 'Ativo' : 'Inativo'
}

function getCasoBadgeClass(status: string): string {
  const norm = (status || '').toLowerCase()
  if (norm === 'ativo' || norm === 'concluido' || norm === 'concluído') return 'badge-success'
  if (norm === 'encerrado' || norm === 'arquivado') return 'badge-muted'
  return 'badge-warning'
}

function getFinanceiroHref(m: any): string {
  if (m.id_pgto_receita) return `#/receitas/recebidas/detalhes/${m.id_pgto_receita}`
  const idReceita = m.id_receita || m.id
  if (idReceita) return `#/receitas/detalhes/${idReceita}`
  return ''
}

function openFinanceiro(m: any) {
  if (m.id_pgto_receita) {
    emit('open-receita-recebida-detail', Number(m.id_pgto_receita))
    return
  }
  const idReceita = m.id_receita || m.id
  if (idReceita) {
    emit('open-receita-detail', Number(idReceita))
  }
}

function getNotaHref(n: any): string {
  if (!n.entidade_id) return ''
  return n.tipo === 'caso'
    ? `#/casos/detalhes/${n.entidade_id}`
    : `#/processos/detalhes/${n.entidade_id}`
}

function openNotaEntidade(n: any) {
  if (!n.entidade_id) return
  if (n.tipo === 'caso') {
    emit('open-caso-detail', Number(n.entidade_id))
  } else {
    emit('open-process-detail', Number(n.entidade_id))
  }
}

// Cep Lookup autocompletion via ViaCep API
async function handleCepInput() {
  const clean = form.cep.replace(/\D/g, '')
  if (clean.length === 8) {
    try {
      const response = await fetch(`https://viacep.com.br/ws/${clean}/json/`)
      const data = await response.json()
      if (data && !data.erro) {
        form.endereco = data.logradouro || ''
        form.cidade = data.localidade || ''
        form.uf = data.uf || ''
        emit('toast', { msg: 'CEP autocompletado com sucesso!', type: 'success' })
      } else {
        emit('toast', { msg: 'CEP não encontrado.', type: 'warning' })
      }
    } catch (err) {
      console.error(err)
    }
  }
}

// CPF/CNPJ auto formatter
function formatDocumentInput() {
  const maxLength = form.tipo_cliente === 'F' ? 11 : 14
  const clean = form.documento.replace(/\D/g, '').substring(0, maxLength)
  form.documento = formatDocument(clean, clean)
}

// Phone auto formatter
function formatPhoneInput() {
  const clean = form.telefone.replace(/\D/g, '').substring(0, 11)
  form.telefone = formatPhone(clean, clean)
}

// Load Client Details and subtables
async function loadDetails() {
  const idToLoad = isNew.value ? 0 : props.clientId

  try {
    const res = await apiFetch(`clientes/detalhes/${idToLoad}`)

    if (res && res.success) {
      origens.value = res.origens || []

      if (!isNew.value && res.data) {
        // Map form fields matching exact column names
        Object.keys(form).forEach(k => {
          if (res.data[k] !== undefined && res.data[k] !== null) {
            form[k] = String(res.data[k])
          }
        })
        // Ensure specific typing or mapping
        if (res.data.id_cliente) form.id_cliente = res.data.id_cliente
        ehFornecedor.value = Number(res.data.eh_fornecedor) === 1
        responsavelSalvo.value = form.responsavel_id

        // Sub lists from primary response
        processes.value = res.processos || []
        cases.value = res.casos || []
      }
    }

    if (isNew.value) return // Do not load other stats for new client

    // Load extra subresources in parallel
    const [statsRes, notesRes, financialRes, intimacoesRes] = await Promise.all([
      apiFetch(`clientes/estatisticasCliente/${props.clientId}`),
      apiFetch(`clientes/anotacoes/${props.clientId}`),
      // Endpoints de outros módulos: não chama sem acesso (a API devolveria 403).
      abaLiberada('financeiro') ? apiFetch(`clientes/movimentacoes/${props.clientId}`) : Promise.resolve(null),
      abaLiberada('intimacoes') ? apiFetch(`clientes/intimacoes/${props.clientId}`) : Promise.resolve(null)
    ])

    if (statsRes && statsRes.success) {
      Object.assign(clientStats, statsRes.data)
    }

    if (notesRes && notesRes.success) {
      notes.value = notesRes.data || []
    }

    if (financialRes && financialRes.success) {
      financial.value = financialRes.data || []
    }

    if (intimacoesRes && intimacoesRes.success) {
      intimacoes.value = intimacoesRes.data || []
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      semAcesso.value = true
      return
    }
    console.error(err)
    emit('toast', { msg: 'Erro ao carregar dados do cliente.', type: 'danger' })
  }
}

// Save Client action
async function saveClient() {
  if (!form.nome || form.nome.length < 3) {
    emit('toast', { msg: 'Por favor, insira o nome do cliente (mínimo 3 caracteres).', type: 'warning' })
    return
  }
  if (form.status_crm === 'client' && (!form.documento || form.documento.length < 11)) {
    emit('toast', { msg: 'Por favor, insira um CPF/CNPJ válido para cadastrar o cliente.', type: 'warning' })
    return
  }

  saving.value = true
  try {
    const body = new FormData()
    Object.keys(form).forEach(k => {
      // Depois do cadastro, a visibilidade muda pelo card de acesso; o responsável,
      // só por quem pode gerenciar os acessos.
      if (!isNew.value && k === 'visibilidade') return
      if (!isNew.value && k === 'responsavel_id' && !podeGerenciarAcessos.value) return
      body.append(k, form[k])
    })

    const res = await apiFetch('clientes/salvar', {
      method: 'POST',
      body: body
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Cadastro realizado com sucesso.', type: 'success' })
      emit('set-view', 'clientes')
    } else {
      throw new Error(res.message || 'Erro ao salvar.')
    }
  } catch (err: any) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao processar o cadastro.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

watch(() => props.clientId, () => {
  loadDetails()
})

onMounted(() => {
  loadDetails()
})
</script>

<style scoped>
.form-card > .card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.tag-papel {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--primary) 14%, transparent);
  color: var(--primary);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.sem-acesso .card-body {
  text-align: center;
  padding: 40px 20px;
  color: var(--ink-dim);
}

.sem-acesso .bi-lock {
  font-size: 2rem;
  color: var(--ink-muted);
}

.form-hint {
  display: block;
  margin-top: 4px;
  font-size: 0.75rem;
  color: var(--ink-muted);
}

.form-card {
  margin-bottom: 20px;
}

.form-row {
  margin-bottom: 14px;
}

.form-row.col-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.form-row.col-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.form-row.col-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.form-row.col-1 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

.form-row.address-row {
  display: grid;
  grid-template-columns: 140px 1fr 120px 1fr;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 0;
}

.required-field::after {
  content: ' *';
  color: var(--danger);
  font-weight: 700;
}

/* ─── Tabs Card & Table (Design System Standard) ─────────── */
.tabs-card {
  overflow: hidden;
  margin-top: 20px;
}

.tabs-header {
  padding: 12px 16px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}

.tabs {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  border-radius: 8px;
  font-family: var(--font-body);
  font-size: 0.83rem;
  font-weight: 500;
  border: 1px solid transparent;
  background: transparent;
  color: var(--ink-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--card);
  border-color: var(--border);
}

.tab-btn.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  font-weight: 600;
}

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 18px;
  padding: 0 6px;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 600;
  background: rgba(15, 25, 35, 0.08);
  color: inherit;
}

.tab-btn.active .tab-count {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

.tab-content {
  padding: 0;
}

.tab-body-padded {
  padding: 20px;
}

.table-wrap {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
  color: var(--ink);
}

.data-table th {
  padding: 11px 16px;
  text-align: left;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--ink-muted);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

.data-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background: var(--primary-lt);
}

.center {
  text-align: center !important;
}

.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}

.nowrap {
  white-space: nowrap;
}

.strong {
  font-weight: 600;
}

.table-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: none;
  transition: color 0.15s ease;
}

.table-link:hover {
  color: var(--accent);
  text-decoration: underline;
}

.table-link-secondary {
  color: var(--ink-dim);
  text-decoration: none;
  transition: color 0.15s ease;
}

.table-link-secondary:hover {
  color: var(--primary);
  text-decoration: underline;
}

.table-sublink {
  display: inline-block;
  font-size: 0.75rem;
  color: var(--ink-muted);
  text-decoration: none;
}

.table-sublink:hover {
  color: var(--primary);
  text-decoration: underline;
}

.intimacao-excerpt {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.45;
  font-size: 0.82rem;
}

.table-empty {
  padding: 36px 20px !important;
  text-align: center;
  color: var(--ink-muted);
  font-size: 0.85rem;
}

.table-empty i {
  display: block;
  font-size: 1.75rem;
  opacity: 0.35;
  margin-bottom: 6px;
}

/* ─── Timeline (CRM / Histórico) ─────────────────────────── */
.timeline {
  display: flex;
  flex-direction: column;
  gap: 14px;
  position: relative;
  padding-left: 28px;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 11px;
  top: 4px;
  bottom: 4px;
  width: 2px;
  background-color: var(--border);
}

.timeline-item {
  position: relative;
}

.timeline-badge {
  position: absolute;
  left: -28px;
  top: 10px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background-color: var(--primary);
  display: flex;
  justify-content: center;
  align-items: center;
  color: #fff;
  font-size: 0.7rem;
}

.timeline-card {
  padding: 12px 16px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  font-size: 0.84rem;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.timeline-ref-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
  font-size: 0.76rem;
  font-weight: 500;
  color: var(--primary);
  text-decoration: none;
}

.timeline-ref-link:hover {
  color: var(--accent);
  text-decoration: underline;
}

.timeline-date {
  color: var(--ink-muted);
  font-size: 0.76rem;
}

.timeline-body {
  margin: 0;
  font-size: 0.84rem;
  color: var(--ink-dim);
  line-height: 1.5;
}

.indicator-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 11px 16px;
  text-decoration: none;
  color: inherit;
}

@media (max-width: 900px) {
  .form-row.col-3,
  .form-row.col-4,
  .form-row.address-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .form-row.col-2,
  .form-row.col-3,
  .form-row.col-4,
  .form-row.address-row {
    grid-template-columns: 1fr;
  }
}
</style>
