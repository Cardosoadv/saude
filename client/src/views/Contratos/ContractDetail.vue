<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'contratos')">Contratos</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isNew ? 'Novo Contrato' : 'Detalhes do Contrato' }}</span>
    </div>

    <div class="page-header">
      <div>
        <h1>{{ isNew ? 'Novo Contrato' : form.objeto || 'Carregando...' }}</h1>
        <p v-if="!isNew">#{{ form.numero_contrato }} — Gerenciamento de vigência e honorários</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-ghost" @click="$emit('set-view', 'contratos')">Cancelar</button>
        <button v-if="!semAcesso" class="btn btn-accent" @click="save" :disabled="saving">
          <i class="bi bi-check-lg"></i> {{ saving ? 'Salvando...' : 'Salvar Contrato' }}
        </button>
      </div>
    </div>

    <!-- Contrato inexistente ou restrito (privacidade granular) -->
    <div v-if="semAcesso" class="form-card glass-panel sem-acesso">
      <i class="bi bi-lock"></i>
      <p><strong>Contrato não encontrado ou sem permissão de acesso.</strong></p>
      <p>Se você precisa acessar este contrato, peça ao responsável ou a um administrador.</p>
    </div>

    <div v-else :class="isNew ? '' : 'content-grid'">
      <div class="main-column">
        <div class="form-card glass-panel mb-3">
          <div class="card-header bg-primary text-white" style="border-radius: 8px 8px 0 0; padding: 1rem;">
            <h3 class="mb-0" style="color: white; margin: 0;"><i class="bi bi-file-earmark-ruled"></i> Informações do Contrato</h3>
          </div>
          <div class="card-body" style="padding: 1.5rem;">
            <!-- Cliente e Número do Contrato -->
            <div class="form-row col-2">
              <div class="form-group" style="flex: 2;">
                <label class="form-label required-field"><i class="bi bi-person"></i> Cliente</label>
                <div class="position-relative">
                  <div class="input-group" style="display: flex;">
                    <input 
                      type="text" 
                      class="form-control" 
                      style="flex: 1; border-top-right-radius: 0; border-bottom-right-radius: 0;"
                      v-model="clienteBusca" 
                      @input="onClienteInput" 
                      @focus="showClientes = true"
                      placeholder="Buscar cliente por nome ou documento..."
                      required
                    >
                    <span class="input-group-text" style="background:var(--input-bg); border-color:var(--border-color); color:var(--ink-muted);" v-if="!form.id_cliente"><i class="bi bi-search"></i></span>
                    <button class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding: 0 12px;" type="button" v-if="form.id_cliente" @click="clearCliente"><i class="bi bi-x-lg"></i></button>
                  </div>
                  
                  <!-- Dropdown results styled as premium -->
                  <ul v-if="showClientes && clienteOptions.length" class="autocomplete-list glass-panel shadow z-3 position-absolute w-100">
                    <li 
                      v-for="c in clienteOptions" 
                      :key="c.id_cliente" 
                      @click="selectCliente(c)"
                      style="cursor: pointer;"
                    >
                      <div class="autocomplete-item-title"><i class="bi bi-person"></i> {{ c.nome || c.cliente }}</div>
                      <small class="autocomplete-item-sub">{{ c.documento || c.cpf_cnpj || 'Sem Documento' }}</small>
                    </li>
                  </ul>
                </div>
                <small class="form-text text-muted"><i class="bi bi-info-circle"></i> Selecione o cliente vinculado</small>
              </div>
              <div class="form-group" style="flex: 1;">
                <label class="form-label"><i class="bi bi-hash"></i> Número do Contrato</label>
                <input type="text" class="form-control" v-model="form.numero" placeholder="Ex: 2025/001">
                <small class="form-text text-muted"><i class="bi bi-info-circle"></i> Gerado automaticamente se vazio</small>
              </div>
            </div>

            <!-- Tipo de Contrato e Objeto -->
            <div class="form-row col-2">
              <div class="form-group" style="flex: 1;">
                <label class="form-label required-field"><i class="bi bi-bookmark"></i> Tipo de Contrato</label>
                <select class="form-control form-select" v-model="form.tipo_contrato" required>
                  <option value="">Selecione o tipo</option>
                  <option value="Honorários">Honorários</option>
                  <option value="Consultoria">Consultoria</option>
                  <option value="Assessoria">Assessoria</option>
                  <option value="Êxito">Êxito</option>
                  <option value="Misto">Misto</option>
                  <option value="Pro Bono">Pro Bono</option>
                </select>
              </div>
              <div class="form-group" style="flex: 2;">
                <label class="form-label required-field"><i class="bi bi-file-text"></i> Objeto do Contrato</label>
                <input type="text" class="form-control" v-model="form.objeto" required placeholder="Descreva brevemente o objeto do contrato">
              </div>
            </div>

            <!-- Datas -->
            <div class="form-row col-3">
              <div class="form-group">
                <label class="form-label required-field"><i class="bi bi-calendar-check"></i> Data de Início</label>
                <input type="date" class="form-control" v-model="form.data_inicio" required>
              </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-calendar-x"></i> Data de Término</label>
                <input type="date" class="form-control" v-model="form.data_fim">
                <small class="form-text text-muted"><i class="bi bi-info-circle"></i> Deixe vazio para indeterminado</small>
              </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-clock-history"></i> Prazo de Vigência</label>
                <div style="display: flex; gap: 0.5rem;">
                  <input type="number" class="form-control" v-model="form.prazo_vigencia" placeholder="12" style="flex: 1;">
                  <select class="form-control form-select" v-model="form.prazo_tipo" style="width: 100px;">
                    <option value="meses">Meses</option>
                    <option value="anos">Anos</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Valores Financeiros -->
            <div class="form-row col-4">
              <div class="form-group">
                <label class="form-label"><i class="bi bi-cash-stack"></i> Valor do Contrato</label>
                <div style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden;">
                  <span style="padding: 0.5rem; background: var(--bg-alt); border-right: 1px solid var(--border-color);">R$</span>
                  <input type="number" step="0.01" class="form-control" v-model="form.valor" style="border: none;" placeholder="0.00">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-percent"></i> Honorários de Êxito</label>
                <div style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden;">
                  <input type="number" step="0.01" class="form-control" v-model="form.honorarios_exito" style="border: none;" placeholder="0.00">
                  <span style="padding: 0.5rem; background: var(--bg-alt); border-left: 1px solid var(--border-color);">%</span>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label required-field"><i class="bi bi-credit-card"></i> Forma de Pgto</label>
                <select class="form-control form-select" v-model="form.forma_pagamento" required>
                  <option value="">Selecione</option>
                  <option value="Mensal">Mensal</option>
                  <option value="Trimestral">Trimestral</option>
                  <option value="Semestral">Semestral</option>
                  <option value="Anual">Anual</option>
                  <option value="Parcela Única">Parcela Única</option>
                  <option value="Por Hora">Por Hora</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-calendar-event"></i> Dia de Vencimento</label>
                <input type="number" class="form-control" v-model="form.dia_vencimento" min="1" max="31" placeholder="10">
                <small class="form-text text-muted"><i class="bi bi-info-circle"></i> Dia do mês</small>
              </div>
            </div>

            <!-- Status e Renovação -->
            <div class="form-row col-3">
              <div class="form-group">
                <label class="form-label required-field"><i class="bi bi-flag"></i> Status</label>
                <select class="form-control form-select" v-model="form.status" required>
                  <option value="Ativo">Ativo</option>
                  <option value="Em negociação">Em negociação</option>
                  <option value="Pendente">Pendente</option>
                  <option value="Suspenso">Suspenso</option>
                  <option value="Encerrado">Encerrado</option>
                  <option value="Cancelado">Cancelado</option>
                </select>
              </div>
                                <div class="form-group">
                    <label class="form-label"><i class="bi bi-briefcase"></i> Área de Atuação</label>
                    <select class="form-control form-select" v-model="form.area_atuacao">
                      <option value="">Selecione o Ramo do Direito...</option>
                      <template v-for="ramo in ramos" :key="ramo.id">
                        <option :value="String(ramo.id)" class="option-parent">{{ ramo.nome }}</option>
                        <template v-if="ramo.filhos && ramo.filhos.length">
                          <template v-for="filho in ramo.filhos" :key="filho.id">
                            <option :value="String(filho.id)" class="option-child">&nbsp;&nbsp;&nbsp;↳ {{ filho.nome }}</option>
                            <template v-if="filho.filhos && filho.filhos.length">
                              <option v-for="neto in filho.filhos" :key="neto.id" :value="String(neto.id)" class="option-grandchild">
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;• {{ neto.nome }}
                              </option>
                            </template>
                          </template>
                        </template>
                      </template>
                    </select>
                  </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-arrow-repeat"></i> Renovação Automática</label>
                <select class="form-control form-select" v-model="form.renovacao_automatica">
                  <option value="Não">Não</option>
                  <option value="Sim">Sim</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><i class="bi bi-bell"></i> Aviso de Vencimento</label>
                <div style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden;">
                  <input type="number" class="form-control" v-model="form.prazo_aviso" style="border: none;" placeholder="30">
                  <span style="padding: 0.5rem; background: var(--bg-alt); border-left: 1px solid var(--border-color); font-size: 0.85rem; white-space: nowrap;">dias antes</span>
                </div>
              </div>
            </div>

            <!-- Responsável e visibilidade (privacidade granular) -->
            <div class="form-row col-2">
              <div class="form-group">
                <label class="form-label" for="contrato_responsavel_id"><i class="bi bi-person-badge"></i> Responsável</label>
                <ResponsavelSelect id="contrato_responsavel_id" v-model="form.responsavel_id" :disabled="!isNew && !podeGerenciarAcessos" />
                <small v-if="!isNew && !podeGerenciarAcessos" class="form-hint">Só o responsável ou um administrador pode trocar.</small>
                <small v-else-if="form.responsavel_interno && !form.responsavel_id" class="form-hint">Registro anterior: {{ form.responsavel_interno }}</small>
              </div>
              <div class="form-group" v-if="isNew">
                <label class="form-label" for="contrato_visibilidade"><i class="bi bi-lock"></i> Visibilidade</label>
                <select id="contrato_visibilidade" v-model="form.visibilidade" class="form-control">
                  <option value="restrito">Restrito: você, o responsável e quem for liberado</option>
                  <option value="publico">Público: todos os advogados</option>
                </select>
              </div>
            </div>

            <!-- Comentários -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-chat-left-text"></i> Comentários</label>
              <textarea class="form-control" rows="4" v-model="form.comentarios" placeholder="Informações adicionais sobre o contrato..."></textarea>
            </div>

            <!-- Informações Complementares (Colapsável simulado) -->
            <div class="accordion-simulate mt-3">
              <div class="accordion-header" @click="showExtraInfo = !showExtraInfo" style="cursor: pointer; padding: 0.75rem; background: rgba(0,0,0,0.03); border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">
                <strong><i class="bi bi-info-square me-2"></i> Informações Complementares (Opcional)</strong>
                <i :class="showExtraInfo ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
              </div>
              <div v-show="showExtraInfo" class="accordion-body" style="padding: 1rem; border: 1px solid rgba(0,0,0,0.05); border-top: none; border-radius: 0 0 4px 4px;">
                <div class="form-row col-2">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-calculator"></i> Número de Parcelas</label>
                    <input type="number" class="form-control" v-model="form.numero_parcelas" placeholder="12">
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-graph-up-arrow"></i> Índice de Reajuste</label>
                    <select class="form-control form-select" v-model="form.indice_reajuste">
                      <option value="">Sem reajuste</option>
                      <option value="IPCA">IPCA</option>
                      <option value="IGP-M">IGP-M</option>
                      <option value="INPC">INPC</option>
                      <option value="Percentual Fixo">Percentual Fixo</option>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label"><i class="bi bi-folder-open"></i> Link da Pasta</label>
                  <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="url" class="form-control" v-model="form.pasta" placeholder="https://drive.google.com/...">
                    <a v-if="form.pasta" :href="form.pasta" target="_blank" class="btn btn-outline-secondary" style="padding: 0.5rem 0.75rem;"><i class="bi bi-folder-symlink"></i></a>
                  </div>
                  <small class="form-text text-muted"><i class="bi bi-info-circle"></i> Link para pasta com documentos do contrato</small>
                </div>

                <div class="form-group">
                  <label class="form-label"><i class="bi bi-file-earmark-text"></i> Cláusulas Especiais</label>
                  <textarea class="form-control" rows="3" v-model="form.clausulas_especiais" placeholder="Descreva cláusulas específicas relevantes..."></textarea>
                </div>
              </div>
            </div>
            
            <!-- Ações Principais (Botões) -->
            <div class="mt-4 pt-3" style="border-top: 1px solid var(--border-color); display: flex; gap: 0.5rem; flex-wrap: wrap;">
              <button class="btn btn-primary" @click="save" :disabled="saving">
                <i class="bi bi-check-circle"></i> {{ isNew ? 'Salvar' : 'Atualizar' }}
              </button>
              
              <template v-if="!isNew">
                <button class="btn btn-outline-info" @click="duplicarContrato">
                  <i class="bi bi-files"></i> Duplicar
                </button>
                <button class="btn btn-outline-success" @click="renovarContrato">
                  <i class="bi bi-arrow-repeat"></i> Renovar Contrato
                </button>
              </template>
              
              <button class="btn btn-outline-secondary" @click="$emit('set-view', 'contratos')">
                <i class="bi bi-x-circle"></i> Cancelar
              </button>
              
              <button v-if="!isNew" class="btn btn-outline-danger" style="margin-left: auto;" @click="excluirContrato">
                <i class="bi bi-trash"></i> Excluir
              </button>
            </div>
          </div>
        </div>

        <!-- Related Tabs -->
        <div class="tabs-container glass-panel" style="margin-top:1.5rem;" v-if="!isNew">
          <div class="tabs">
            <button v-for="t in tabs" :key="t.id"
              :class="['tab-btn', { active: activeTab === t.id }]" @click="activeTab = t.id">
              <i :class="t.icon"></i> {{ t.label }}
            </button>
          </div>
          <div class="tab-content" style="padding:1.5rem 0;">
            <!-- Processos Vinculados -->
            <div v-if="activeTab === 'processos'">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h6 style="margin:0;">Processos Vinculados</h6>
                <button class="btn btn-sm btn-outline-primary" @click="vincularProcesso"><i class="bi bi-plus"></i> Adicionar</button>
              </div>
              <div v-if="form.processos && form.processos.length" class="list-group">
                <div v-for="proc in form.processos" :key="proc.id_processo" class="list-group-item" style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; border:1px solid var(--border-color); border-radius:4px; margin-bottom:0.5rem;">
                  <div>
                    <strong style="color:var(--primary); cursor:pointer;">{{ proc.numero_processo }}</strong>
                    <div style="font-size:0.85rem; color:var(--ink-muted);">{{ proc.titulo_processo || 'Sem título' }}</div>
                  </div>
                  <button class="btn btn-sm btn-ghost" @click="desvincularProcesso(proc.id_processo)" title="Desvincular">
                    <i class="bi bi-x-lg" style="color:var(--danger);"></i>
                  </button>
                </div>
              </div>
              <div v-else class="table-empty">
                <i class="bi bi-link-45deg"></i>
                <p>Nenhum processo vinculado.</p>
              </div>
            </div>
            
            <!-- Casos Vinculados -->
            <div v-if="activeTab === 'casos'">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h6 style="margin:0;">Casos Vinculados</h6>
                <button class="btn btn-sm btn-outline-primary" @click="vincularCaso"><i class="bi bi-plus"></i> Adicionar</button>
              </div>
              <div v-if="form.casos && form.casos.length" class="list-group">
                <div v-for="caso in form.casos" :key="caso.id_caso" class="list-group-item" style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; border:1px solid var(--border-color); border-radius:4px; margin-bottom:0.5rem;">
                  <div>
                    <strong style="color:var(--primary); cursor:pointer;">{{ caso.titulo }}</strong>
                  </div>
                  <button class="btn btn-sm btn-ghost" @click="desvincularCaso(caso.id_caso)" title="Desvincular">
                    <i class="bi bi-x-lg" style="color:var(--danger);"></i>
                  </button>
                </div>
              </div>
              <div v-else class="table-empty">
                <i class="bi bi-briefcase"></i>
                <p>Nenhum caso vinculado.</p>
              </div>
            </div>

            <!-- Financeiro Vinculado -->
            <div v-if="activeTab === 'financeiro'">
              <div class="form-row col-2">
                <!-- Receitas -->
                <div>
                  <h6 style="color:var(--success); margin-bottom:1rem;"><i class="bi bi-arrow-up-circle"></i> Receitas</h6>
                  <div v-if="form.receitas && form.receitas.length" class="list-group">
                    <div v-for="rec in form.receitas" :key="rec.id_receita" class="list-group-item" style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; border:1px solid var(--border-color); border-radius:4px; margin-bottom:0.5rem;">
                      <div>
                        <strong>{{ rec.receita }}</strong>
                        <div style="font-size:0.85rem; color:var(--ink-muted);">{{ formatDate(rec.vencimento_dt) }} - {{ formatCurrency(rec.valor) }}</div>
                      </div>
                      <i :class="rec.status === 'Pago' ? 'bi bi-check-circle-fill text-success' : 'bi bi-clock text-warning'"></i>
                    </div>
                  </div>
                  <p v-else class="text-muted small">Nenhuma receita vinculada.</p>
                </div>
                <!-- Despesas -->
                <div>
                  <h6 style="color:var(--danger); margin-bottom:1rem;"><i class="bi bi-arrow-down-circle"></i> Despesas</h6>
                  <div v-if="form.despesas && form.despesas.length" class="list-group">
                    <div v-for="desp in form.despesas" :key="desp.id_despesa" class="list-group-item" style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem; border:1px solid var(--border-color); border-radius:4px; margin-bottom:0.5rem;">
                      <div>
                        <strong>{{ desp.despesa }}</strong>
                        <div style="font-size:0.85rem; color:var(--ink-muted);">{{ formatDate(desp.vencimento_dt) }} - {{ formatCurrency(desp.valor) }}</div>
                      </div>
                      <i :class="desp.status === 'Pago' ? 'bi bi-check-circle-fill text-success' : 'bi bi-clock text-warning'"></i>
                    </div>
                  </div>
                  <p v-else class="text-muted small">Nenhuma despesa vinculada.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Side Column -->
      <div class="sidebar-widgets" v-if="!isNew">
        <AcessosCard
          tipo="contrato"
          :entidade-id="props.contractId"
          :responsavel-id="responsavelSalvo"
          @toast="(msg) => $emit('toast', msg)"
          @loaded="a => podeGerenciarAcessos = !!a?.pode_gerenciar"
        />
        <ArquivosContrato :contrato-id="props.contractId" @toast="(msg) => $emit('toast', msg)" />
        <!-- Status do Contrato -->
        <div class="card mb-3">
          <div class="card-header bg-light">
            <h3><i class="bi bi-bar-chart"></i> Status do Contrato</h3>
          </div>
          <div class="card-body" style="padding: 1rem;">
            <div style="text-align: center; margin-bottom: 1rem;">
              <span :class="['badge', getStatusBadgeClass(form.status)]" style="font-size: 1rem; padding: 0.5rem 1rem;">
                {{ form.status || 'Ativo' }}
              </span>
            </div>
            
            <div v-if="form.data_inicio && form.data_fim">
              <div style="font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 0.25rem;">Progresso do Contrato</div>
              <div class="progress" style="height: 12px; background: rgba(0,0,0,0.05); border-radius: 6px; overflow: hidden; margin-bottom: 0.5rem;">
                <div :class="['progress-bar', getProgressClass()]" :style="{ width: progressPercent + '%' }" style="height: 100%;"></div>
              </div>
              <div style="font-size: 0.85rem; display: flex; justify-content: space-between;">
                <span>{{ progressPercent.toFixed(0) }}%</span>
                <strong><i class="bi bi-calendar-event"></i> {{ diasRestantes }} dias rest.</strong>
              </div>
            </div>
            <div v-else-if="form.data_inicio && !form.data_fim" class="text-center text-muted small">
              Vigência indeterminada.
            </div>
          </div>
        </div>

        <!-- Resumo Financeiro -->
        <div class="card mb-3" v-if="form.valor">
          <div class="card-header bg-light">
            <h3><i class="bi bi-cash-coin"></i> Resumo Financeiro</h3>
          </div>
          <div class="card-body" style="padding: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
              <span style="color: var(--ink-muted);">Valor:</span>
              <strong style="color: var(--success);">{{ formatCurrency(form.valor) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;" v-if="form.forma_pagamento">
              <span style="color: var(--ink-muted);">Pagamento:</span>
              <span>{{ form.forma_pagamento }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;" v-if="form.dia_vencimento">
              <span style="color: var(--ink-muted);">Vencimento:</span>
              <span>Dia {{ form.dia_vencimento }}</span>
            </div>
          </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="card">
          <div class="card-header bg-light">
            <h3><i class="bi bi-info-square"></i> Informações</h3>
          </div>
          <div class="card-body" style="padding: 1rem; font-size: 0.85rem;">
            <p style="margin-bottom: 0.5rem;"><strong>ID do Contrato:</strong> #{{ form.id_contrato }}</p>
            <p style="margin-bottom: 0.5rem;" v-if="form.numero_contrato"><strong>Número:</strong> {{ form.numero_contrato }}</p>
            <p style="margin-bottom: 0.5rem;" v-if="form.created_at"><strong>Criado em:</strong><br>{{ formatDate(form.created_at, true) }}</p>
            <p style="margin-bottom: 0;" v-if="form.updated_at"><strong>Última atualização:</strong><br>{{ formatDate(form.updated_at, true) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue'
import { apiFetch, ApiError, formatDate, formatCurrency, debounce } from '../../services/api'
import AcessosCard from '../../components/widgets/Privacidade/AcessosCard.vue'
import ArquivosContrato from '../../components/Contratos/ArquivosContrato.vue'
import ResponsavelSelect from '../../components/widgets/Privacidade/ResponsavelSelect.vue'

const props = defineProps({
  contractId: { type: Number, default: null }
})

const emit = defineEmits(['set-view', 'toast'])

const isNew = computed(() => !props.contractId)
const semAcesso = ref(false)
const podeGerenciarAcessos = ref(false)
const responsavelSalvo = ref('')
const saving = ref(false)
const showExtraInfo = ref(false)
const activeTab = ref('processos')

// Autocomplete Cliente
const clienteBusca = ref('')
const clienteOptions = ref([])
const showClientes = ref(false)
const ramos = ref([])

const tabs = [
  { id: 'processos', label: 'Processos', icon: 'bi bi-bezier2' },
  { id: 'casos', label: 'Casos', icon: 'bi bi-briefcase' },
  { id: 'financeiro', label: 'Financeiro', icon: 'bi bi-currency-dollar' }
]

const form = reactive<Record<string, any>>({
  id_contrato: '',
  numero: '',
  numero_contrato: '', // Usado no legado também
  titulo: '',
  id_cliente: '',
  cliente_nome: '',
  tipo_contrato: '',
  objeto: '',
  status: 'Ativo',
  data_inicio: new Date().toISOString().substring(0, 10),
  data_fim: '',
  prazo_vigencia: '',
  prazo_tipo: 'meses',
  valor: '',
  honorarios_exito: '',
  forma_pagamento: '',
  dia_vencimento: '10',
  renovacao_automatica: 'Não',
  prazo_aviso: '30',
  comentarios: '',
  responsavel_interno: '',
  responsavel_id: '',
  visibilidade: 'restrito',
  area_atuacao: '',
  numero_parcelas: '',
  indice_reajuste: '',
  pasta: '',
  clausulas_especiais: '',
  processos: [],
  casos: [],
  receitas: [],
  despesas: []
})

const getStatusBadgeClass = (status) => {
  if (status === 'Ativo') return 'badge-success'
  if (status === 'Pendente' || status === 'Em negociação') return 'badge-warning'
  if (status === 'Encerrado' || status === 'Cancelado') return 'badge-danger'
  if (status === 'Suspenso') return 'badge-muted'
  return 'badge-primary'
}

// Lógica de cálculo de progresso
const progressPercent = computed(() => {
  if (!form.data_inicio || !form.data_fim) return 0
  const inicio = new Date(form.data_inicio).getTime()
  const fim = new Date(form.data_fim).getTime()
  const hoje = new Date().getTime()
  
  if (hoje < inicio) return 0
  if (hoje > fim) return 100
  
  const total = fim - inicio
  const decorrido = hoje - inicio
  return Math.min(100, Math.max(0, (decorrido / total) * 100))
})

const diasRestantes = computed(() => {
  if (!form.data_fim) return 0
  const fim = new Date(form.data_fim).getTime()
  const hoje = new Date().getTime()
  const diff = fim - hoje
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
})

const getProgressClass = () => {
  if (progressPercent.value > 85) return 'bg-danger'
  if (progressPercent.value > 75) return 'bg-warning'
  return 'bg-success'
}

const onClienteInput = debounce(async () => {
  if (!clienteBusca.value || clienteBusca.value.length < 2) {
    clienteOptions.value = []
    showClientes.value = false
    return
  }
  try {
    const res = await apiFetch(`clientes/buscar?term=${clienteBusca.value}`)
    if (res && res.success) {
      clienteOptions.value = res.data
      showClientes.value = true
    }
  } catch (err) {
    console.error(err)
  }
}, 300)

function selectCliente(c) {
  form.id_cliente = c.id_cliente || c.id
  form.cliente_nome = c.nome || c.cliente
  clienteBusca.value = c.nome || c.cliente
  showClientes.value = false
}

function clearCliente() {
  form.id_cliente = ''
  form.cliente_nome = ''
  clienteBusca.value = ''
}

function closeAutocompleteOutside(e) {
  if (!e.target.closest('.position-relative')) {
    showClientes.value = false
  }
}

const fetchDetail = async () => {
  if (isNew.value) return
  try {
    const res = await apiFetch(`contratos/detalhes/${props.contractId}`)
    if (res.success && res.data) {
      Object.assign(form, res.data)
      
      // Mapear cliente_id do backend para o id_cliente esperado pelo frontend
      if (res.data.cliente_id && !form.id_cliente) {
        form.id_cliente = res.data.cliente_id
      }
      
      // Ajustes de datas para input date
      if (form.data_inicio) form.data_inicio = form.data_inicio.substring(0, 10)
      if (form.data_fim) form.data_fim = form.data_fim.substring(0, 10)
      // Se tiver titulo e nao tiver objeto
      if (form.titulo && !form.objeto) form.objeto = form.titulo
      
      // Initialize autocomplete search field
      if (form.cliente_nome) {
        clienteBusca.value = form.cliente_nome
      }

      form.responsavel_id = form.responsavel_id ? String(form.responsavel_id) : ''
      responsavelSalvo.value = form.responsavel_id
    }
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      semAcesso.value = true
      return
    }
    emit('toast', { msg: 'Erro ao carregar detalhes.', type: 'danger' })
  }
}

const save = async () => {
  if (!form.id_cliente || !form.tipo_contrato || !form.objeto || !form.data_inicio) {
    emit('toast', { msg: 'Preencha os campos obrigatórios.', type: 'warning' })
    return
  }
  // Sincronizar titulo com objeto se vazio
  if (!form.titulo) form.titulo = `${form.tipo_contrato} - ${form.objeto}`
  
  // Garantir que cliente_id seja enviado para o backend
  form.cliente_id = form.id_cliente
  
  saving.value = true
  try {
    // Depois do cadastro, a visibilidade muda pelo card de acesso; o responsável,
    // só por quem pode gerenciar os acessos.
    const payload: Record<string, any> = { ...form }
    if (!isNew.value) delete payload.visibilidade
    if (!isNew.value && !podeGerenciarAcessos.value) delete payload.responsavel_id

    const res = await apiFetch('contratos/salvar', { method: 'POST', body: payload })
    if (res.success) {
      emit('toast', { msg: 'Contrato salvo com sucesso!' })
      emit('set-view', 'contratos')
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao salvar.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

// Ações
const duplicarContrato = () => {
  emit('toast', { msg: 'Duplicar Contrato (Em breve)', type: 'info' })
}

const renovarContrato = () => {
  emit('toast', { msg: 'Renovar Contrato (Em breve)', type: 'info' })
}

const excluirContrato = async () => {
  if(confirm('Deseja realmente excluir este contrato?')) {
    try {
      const res = await apiFetch(`contratos/excluir/${props.contractId}`, { method: 'POST' })
      if(res.success) {
        emit('toast', { msg: 'Contrato excluído.', type: 'success' })
        emit('set-view', 'contratos')
      }
    } catch(err) {
      emit('toast', { msg: 'Erro ao excluir.', type: 'danger' })
    }
  }
}

const vincularProcesso = () => emit('toast', { msg: 'Vincular Processo (Em breve)', type: 'info' })
const desvincularProcesso = (id) => emit('toast', { msg: 'Desvincular Processo (Em breve)', type: 'info' })
const vincularCaso = () => emit('toast', { msg: 'Vincular Caso (Em breve)', type: 'info' })
const desvincularCaso = (id) => emit('toast', { msg: 'Desvincular Caso (Em breve)', type: 'info' })

const fetchRamos = async () => {
  try {
    const res = await apiFetch('contratos/ramos')
    if (res) {
      ramos.value = Array.isArray(res) ? res : (res.data || [])
    }
  } catch (err) {
    console.error('Erro ao buscar ramos:', err)
  }
}

onMounted(() => {
  fetchDetail()
  fetchRamos()
  document.addEventListener('click', closeAutocompleteOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeAutocompleteOutside)
})
</script>

<style scoped>
.sem-acesso {
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

.form-card { border-radius: 8px; }
.form-row { margin-bottom: 1.25rem; display: flex; gap: 1rem; }
.form-row.col-2 { grid-template-columns: repeat(2, 1fr); }
.form-row.col-3 { grid-template-columns: repeat(3, 1fr); }
.form-row.col-4 { grid-template-columns: repeat(4, 1fr); }
.form-group { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; }
.required-field::after { content: ' *'; color: var(--danger); font-weight: bold; }
.tabs-container { border-radius: 8px; padding: 1.5rem; }
.tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 0.5rem; }
.tab-btn { background: transparent; border: none; color: var(--ink-muted); cursor: pointer; font-size: 0.9rem; font-weight: 600; padding: 0.5rem 1rem; border-radius: 4px; transition: all 0.2s ease; }
.tab-btn:hover { color: var(--text-main); background-color: rgba(0,0,0,0.05); }
.tab-btn.active { color: var(--accent); background-color: rgba(0,0,0,0.1); border-bottom: 2px solid var(--accent); border-radius: 4px 4px 0 0; }
.table-empty { padding: 2rem 0; text-align: center; color: var(--ink-muted); }
.table-empty i { font-size: 2rem; margin-bottom: 0.5rem; display: inline-block; color: rgba(0,0,0,0.2); }

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
  z-index: 10;
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

/* Ramo tree styling */
.option-parent {
  font-weight: 700;
  color: var(--primary);
}

.option-child {
  font-weight: 500;
  color: var(--ink-dim);
}

.option-grandchild {
  font-size: 0.8rem;
  color: var(--ink-muted);
}
</style>
