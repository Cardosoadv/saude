<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/painel-cliente.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="cliente-panel-view">
  <!-- Header -->
  <header class="page-header py-4">
    <div class="container px-3 px-md-5 mt-4 text-center">
      <h1 class="page-title text-gold animate-fade-in"><?= esc(site_texto('painel.titulo')) ?></h1>
      <p id="welcome-message" class="page-subtitle mx-auto text-muted animate-slide-up d-none"></p>
    </div>
  </header>

  <div class="container px-3 px-md-5 py-5">
    
    <!-- Loading State -->
    <div id="loading-state" class="text-center py-5">
      <div class="spinner-border text-gold" role="status">
        <span class="visually-hidden"><?= esc(site_texto('painel.carregando')) ?></span>
      </div>
      <p class="mt-3 text-muted"><?= esc(site_texto('painel.acessando')) ?></p>
    </div>

    <!-- Erro -->
    <div id="error-state" class="alert alert-danger custom-alert text-center py-4 d-none" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
      <span id="error-text"></span>
      <div class="mt-3">
        <a href="<?= site_url_publica('area-cliente') ?>" class="btn btn-outline-gold"><?= esc(site_texto('painel.voltar_login')) ?></a>
      </div>
    </div>

    <!-- Main Content (Autenticado) -->
    <div id="auth-state" class="d-none">
      
      <!-- Lista de Processos -->
      <div id="lista-processos-view" class="row justify-content-center">
        <div class="col-lg-10">
          <h3 class="text-gold mb-4"><i class="bi bi-briefcase me-2"></i><?= esc(site_texto('painel.meus_processos')) ?></h3>
          
          <div id="empty-processos" class="premium-card p-5 text-center d-none">
            <i class="bi bi-inbox text-muted fs-1 mb-3"></i>
            <p class="text-muted mb-0"><?= esc(site_texto('painel.sem_processos')) ?></p>
          </div>

          <div id="processos-container" class="list-group gap-3">
            <!-- Process Cards serão injetados aqui -->
          </div>
        </div>
      </div>

      <!-- Detalhes do Processo Selecionado -->
      <div id="detalhes-processo-view" class="row justify-content-center d-none">
        <div class="col-lg-10">
          <button id="btn-voltar" class="btn btn-outline-gold mb-4">
            <i class="bi bi-arrow-left me-2"></i><?= esc(site_texto('painel.voltar_processos')) ?>
          </button>

          <!-- Card Principal -->
          <div class="premium-card p-4 p-md-5 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap">
              <div>
                <h3 id="det-numero" class="text-gold mb-1"></h3>
                <h5 id="det-titulo" class="text-white"></h5>
              </div>
              <span id="det-status" class="badge mt-2 mt-md-0"></span>
            </div>

            <!-- Relatório Final -->
            <div id="det-relatorio-card" class="relatorio-card p-4 mt-4 mb-4 d-none">
              <h4 class="text-gold mb-3"><i class="bi bi-file-earmark-text me-2"></i><?= esc(site_texto('painel.relatorio_final')) ?></h4>
              <div id="det-relatorio-text" class="relatorio-content text-white"></div>
            </div>

            <!-- Dados Resumo -->
            <div class="row g-4 mt-2">
              <div class="col-md-6">
                <div class="info-item">
                  <span class="info-label"><?= esc(site_texto('painel.ramo')) ?></span>
                  <span id="det-ramo" class="info-value"></span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-item">
                  <span class="info-label"><?= esc(site_texto('painel.responsavel')) ?></span>
                  <span id="det-responsavel" class="info-value"></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Abas: Linha do Tempo / Mensagens -->
          <div class="premium-card p-0 mb-4 overflow-hidden">
            <div class="tabs-nav" role="tablist">
              <button class="tab-btn active" data-tab="timeline" role="tab" aria-selected="true">
                <i class="bi bi-clock-history me-2"></i><?= esc(site_texto('painel.linha_tempo')) ?>
              </button>
              <button class="tab-btn" data-tab="mensagens" role="tab" aria-selected="false">
                <i class="bi bi-chat-dots me-2"></i><?= esc(site_texto('painel.mensagens')) ?>
              </button>
            </div>

            <!-- Painel: Linha do Tempo -->
            <div id="tab-timeline" class="tab-pane p-4 p-md-5">
              <div id="timeline-loading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-gold" role="status"></div>
                <p class="mt-2 text-muted small"><?= esc(site_texto('painel.carregando_andamentos')) ?></p>
              </div>
              <div id="timeline-empty" class="text-center py-4 d-none">
                <i class="bi bi-hourglass-split text-muted fs-2 mb-2 d-block"></i>
                <p class="text-muted mb-0"><?= esc(site_texto('painel.sem_movimentacoes')) ?></p>
              </div>
              <div id="timeline-container" class="timeline-list"></div>
            </div>

            <!-- Painel: Mensagens -->
            <div id="tab-mensagens" class="tab-pane p-4 p-md-5 d-none">
              <div id="mensagens-loading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-gold" role="status"></div>
                <p class="mt-2 text-muted small"><?= esc(site_texto('painel.carregando_mensagens')) ?></p>
              </div>
              <div id="mensagens-empty" class="text-center py-4 d-none">
                <i class="bi bi-chat-square-text text-muted fs-2 mb-2 d-block"></i>
                <p class="text-muted mb-0"><?= esc(site_texto('painel.sem_mensagens')) ?></p>
              </div>
              <div id="mensagens-container" class="chat-list mb-4"></div>

              <form id="form-nova-mensagem" class="mensagem-form pt-3">
                <div class="mb-3">
                  <label for="input-assunto" class="form-label small text-muted"><?= esc(site_texto('painel.assunto')) ?></label>
                  <input type="text" id="input-assunto" class="form-control form-control-dark" maxlength="255" required>
                </div>
                <div class="mb-3">
                  <label for="input-mensagem" class="form-label small text-muted"><?= esc(site_texto('painel.sua_mensagem')) ?></label>
                  <textarea id="input-mensagem" class="form-control form-control-dark" rows="3" maxlength="5000" placeholder="<?= esc(site_texto('painel.mensagem_placeholder')) ?>" required></textarea>
                </div>
                <div id="mensagem-erro" class="text-danger small mb-2 d-none"></div>
                <button type="submit" id="btn-enviar-mensagem" class="btn btn-outline-gold">
                  <i class="bi bi-send me-2"></i><?= esc(site_texto('painel.enviar')) ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const T = <?= json_encode([
    'bemvindo' => site_texto('painel.js_bemvindo'),
    'erro_requisicao' => site_texto('painel.js_erro_requisicao'),
    'sem_numero' => site_texto('painel.js_sem_numero'),
    'sem_titulo' => site_texto('painel.js_sem_titulo'),
    'responsavel' => site_texto('painel.js_responsavel'),
    'nao_atribuido' => site_texto('painel.js_nao_atribuido'),
    'erro_detalhes' => site_texto('painel.js_erro_detalhes'),
    'erro_timeline' => site_texto('painel.js_erro_timeline'),
    'erro_mensagens' => site_texto('painel.js_erro_mensagens'),
    'assunto_padrao' => site_texto('painel.js_assunto_padrao'),
    'equipe' => site_texto('painel.js_equipe'),
    'voce' => site_texto('painel.js_voce'),
    'preencha' => site_texto('painel.js_preencha'),
    'enviando' => site_texto('painel.js_enviando'),
    'enviar' => site_texto('painel.js_enviar'),
    'erro_envio' => site_texto('painel.js_erro_envio'),
    'na' => site_texto('painel.js_na'),
    'link_invalido' => site_texto('painel.js_link_invalido'),
    'sessao_expirada' => site_texto('painel.js_sessao_expirada'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  const loadingState = document.getElementById('loading-state');
  const errorState = document.getElementById('error-state');
  const authState = document.getElementById('auth-state');
  const errorText = document.getElementById('error-text');
  const welcomeMessage = document.getElementById('welcome-message');
  
  const listaProcessosView = document.getElementById('lista-processos-view');
  const detalhesProcessoView = document.getElementById('detalhes-processo-view');
  const processosContainer = document.getElementById('processos-container');
  const emptyProcessos = document.getElementById('empty-processos');
  const btnVoltar = document.getElementById('btn-voltar');

  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabTimeline = document.getElementById('tab-timeline');
  const tabMensagens = document.getElementById('tab-mensagens');
  const timelineLoading = document.getElementById('timeline-loading');
  const timelineEmpty = document.getElementById('timeline-empty');
  const timelineContainer = document.getElementById('timeline-container');
  const mensagensLoading = document.getElementById('mensagens-loading');
  const mensagensEmpty = document.getElementById('mensagens-empty');
  const mensagensContainer = document.getElementById('mensagens-container');
  const formNovaMensagem = document.getElementById('form-nova-mensagem');
  const inputAssunto = document.getElementById('input-assunto');
  const inputMensagem = document.getElementById('input-mensagem');
  const btnEnviarMensagem = document.getElementById('btn-enviar-mensagem');
  const mensagemErro = document.getElementById('mensagem-erro');

  let clienteData = null;
  let processoAtual = null;
  let timelineCarregada = false;
  let mensagensCarregadas = false;
  const baseUrl = window.__APP_CONFIG__?.apiUrl || '<?= base_url('api/v2/site/') ?>';

  const showError = (msg) => {
    loadingState.classList.add('d-none');
    errorText.textContent = msg;
    errorState.classList.remove('d-none');
  };

  const showAuth = () => {
    loadingState.classList.add('d-none');
    welcomeMessage.textContent = T.bemvindo.replace('{nome}', clienteData.nome);
    welcomeMessage.classList.remove('d-none');
    authState.classList.remove('d-none');
  };

  const apiFetch = async (endpoint, options = {}) => {
    const config = {
      ...options,
      headers: { 
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...options.headers 
      },
      credentials: 'include'
    };

    const response = await fetch(`${baseUrl}${endpoint}`, config);
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.message || T.erro_requisicao);
    }
    return data;
  };

  const carregarProcessos = async () => {
    try {
      const response = await apiFetch('cliente/processos');
      if (response.success) {
        renderizarProcessos(response.data || []);
      }
    } catch (err) {
      console.error('Erro ao carregar processos:', err);
      // Podemos deixar vazio ou exibir um sub-erro
    }
  };

  const renderizarProcessos = (processos) => {
    if (processos.length === 0) {
      emptyProcessos.classList.remove('d-none');
      processosContainer.innerHTML = '';
      return;
    }
    
    emptyProcessos.classList.add('d-none');
    let html = '';
    processos.forEach(proc => {
      let badgeClass = 'bg-success';
      if(proc.status === 'Encerrado') badgeClass = 'bg-danger';
      else if(proc.status === 'Arquivado') badgeClass = 'bg-secondary';

      html += `
        <div class="premium-card p-4 process-card cursor-pointer" onclick="selecionarProcesso(${proc.id_processo})">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <h5 class="text-white mb-1">${proc.numero_processo || T.sem_numero}</h5>
              <p class="text-gold mb-2 small">${proc.titulo_processo || T.sem_titulo}</p>
            </div>
            <span class="badge ${badgeClass}">${proc.status}</span>
          </div>
          <div class="text-muted small mt-2">
            <i class="bi bi-person me-1"></i> ${T.responsavel.replace('{nome}', proc.responsavel_nome || T.nao_atribuido)}
          </div>
        </div>
      `;
    });
    processosContainer.innerHTML = html;
  };

  window.selecionarProcesso = async (id) => {
    try {
      listaProcessosView.classList.add('d-none');
      detalhesProcessoView.classList.add('d-none');
      loadingState.classList.remove('d-none'); // reutiliza o loading

      const response = await apiFetch(`cliente/processos/${id}`);
      if (response.success) {
        processoAtual = response.data.processo || response.data;
        preencherDetalhes(processoAtual);

        // Reseta o estado das abas para o novo processo selecionado
        timelineCarregada = false;
        mensagensCarregadas = false;
        timelineContainer.innerHTML = '';
        mensagensContainer.innerHTML = '';
        mudarTab('timeline');

        loadingState.classList.add('d-none');
        detalhesProcessoView.classList.remove('d-none');
      }
    } catch (err) {
      alert(err.message || T.erro_detalhes);
      loadingState.classList.add('d-none');
      listaProcessosView.classList.remove('d-none');
    }
  };

  // ==========================================================================
  // ABAS (Linha do Tempo / Mensagens)
  // ==========================================================================

  const mudarTab = (nome) => {
    tabButtons.forEach(btn => {
      const ativo = btn.dataset.tab === nome;
      btn.classList.toggle('active', ativo);
      btn.setAttribute('aria-selected', ativo ? 'true' : 'false');
    });
    tabTimeline.classList.toggle('d-none', nome !== 'timeline');
    tabMensagens.classList.toggle('d-none', nome !== 'mensagens');

    if (nome === 'timeline' && !timelineCarregada) {
      carregarTimeline();
    } else if (nome === 'mensagens' && !mensagensCarregadas) {
      carregarMensagens();
    }
  };

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => mudarTab(btn.dataset.tab));
  });

  // ==========================================================================
  // LINHA DO TEMPO SIMPLIFICADA
  // ==========================================================================

  const ICONES_CATEGORIA = {
    inicio: 'bi-flag',
    citacao: 'bi-envelope-open',
    intimacao: 'bi-bell',
    audiencia: 'bi-mic',
    peticao: 'bi-file-earmark-text',
    decisao: 'bi-gavel',
    recurso: 'bi-arrow-up-circle',
    acordo: 'bi-handshake',
    pericia: 'bi-clipboard-pulse',
    arquivamento: 'bi-archive',
    encerramento: 'bi-check-circle',
    movimentacao: 'bi-arrow-repeat'
  };

  const SENTIMENTO_CLASSE = {
    positivo: 'sentimento-positivo',
    negativo: 'sentimento-negativo',
    neutro: 'sentimento-neutro'
  };

  const formatarData = (dataStr) => {
    if (!dataStr) return '';
    const d = new Date(dataStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dataStr;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
  };

  const carregarTimeline = async () => {
    if (!processoAtual) return;
    timelineLoading.classList.remove('d-none');
    timelineEmpty.classList.add('d-none');
    timelineContainer.innerHTML = '';

    try {
      const response = await apiFetch(`cliente/processos/${processoAtual.id_processo}/timeline`);
      timelineLoading.classList.add('d-none');

      if (response.success) {
        timelineCarregada = true;
        renderizarTimeline(response.data.eventos || []);
      }
    } catch (err) {
      timelineLoading.classList.add('d-none');
      timelineContainer.innerHTML = `<p class="text-danger small text-center">${err.message || T.erro_timeline}</p>`;
    }
  };

  const renderizarTimeline = (eventos) => {
    if (!eventos.length) {
      timelineEmpty.classList.remove('d-none');
      return;
    }

    // Exibe do mais recente para o mais antigo
    const ordenados = [...eventos].reverse();

    timelineContainer.innerHTML = ordenados.map(ev => {
      const icone = ICONES_CATEGORIA[ev.categoria] || 'bi-arrow-repeat';
      const sentimentoClasse = ev.sentimento ? (SENTIMENTO_CLASSE[ev.sentimento] || '') : '';
      return `
        <div class="timeline-item ${ev.marco ? 'timeline-item-marco' : ''}">
          <div class="timeline-dot ${sentimentoClasse}"><i class="bi ${icone}"></i></div>
          <div class="timeline-content">
            <div class="timeline-date">${formatarData(ev.data)}</div>
            <div class="timeline-title">${escapeHtml(ev.titulo)}</div>
            ${ev.descricao ? `<div class="timeline-desc">${escapeHtml(ev.descricao)}</div>` : ''}
          </div>
        </div>
      `;
    }).join('');
  };

  // ==========================================================================
  // MENSAGENS
  // ==========================================================================

  const carregarMensagens = async () => {
    if (!processoAtual) return;
    mensagensLoading.classList.remove('d-none');
    mensagensEmpty.classList.add('d-none');
    mensagensContainer.innerHTML = '';

    try {
      const response = await apiFetch(`cliente/processos/${processoAtual.id_processo}/mensagens`);
      mensagensLoading.classList.add('d-none');

      if (response.success) {
        mensagensCarregadas = true;
        renderizarMensagens(response.data || []);

        if (!inputAssunto.value) {
          inputAssunto.value = T.assunto_padrao.replace('{numero}', processoAtual.numero_processo || '').trim();
        }
      }
    } catch (err) {
      mensagensLoading.classList.add('d-none');
      mensagensContainer.innerHTML = `<p class="text-danger small text-center">${err.message || T.erro_mensagens}</p>`;
    }
  };

  const renderizarMensagens = (mensagens) => {
    if (!mensagens.length) {
      mensagensEmpty.classList.remove('d-none');
      return;
    }

    mensagensContainer.innerHTML = mensagens.map(msg => {
      const doAdvogado = !msg.is_cliente;
      return `
        <div class="chat-bubble-row ${doAdvogado ? 'from-advogado' : 'from-cliente'}">
          <div class="chat-bubble">
            <div class="chat-bubble-autor">${doAdvogado ? (msg.remetente_nome || T.equipe) : T.voce}</div>
            ${msg.assunto ? `<div class="chat-bubble-assunto">${escapeHtml(msg.assunto)}</div>` : ''}
            <div class="chat-bubble-texto">${escapeHtml(msg.conteudo || '').replace(/\n/g, '<br>')}</div>
            <div class="chat-bubble-data">${formatarDataHora(msg.data_envio)}</div>
          </div>
        </div>
      `;
    }).join('');

    mensagensContainer.scrollTop = mensagensContainer.scrollHeight;
  };

  const formatarDataHora = (dataStr) => {
    if (!dataStr) return '';
    const d = new Date(dataStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dataStr;
    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  };

  formNovaMensagem.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!processoAtual) return;

    const assunto = inputAssunto.value.trim();
    const mensagem = inputMensagem.value.trim();
    mensagemErro.classList.add('d-none');

    if (!assunto || !mensagem) {
      mensagemErro.textContent = T.preencha;
      mensagemErro.classList.remove('d-none');
      return;
    }

    btnEnviarMensagem.disabled = true;
    btnEnviarMensagem.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${T.enviando}`;

    try {
      const response = await apiFetch(`cliente/processos/${processoAtual.id_processo}/mensagens`, {
        method: 'POST',
        body: JSON.stringify({ assunto, mensagem })
      });

      if (response.success) {
        inputMensagem.value = '';
        await carregarMensagens();
      }
    } catch (err) {
      mensagemErro.textContent = err.message || T.erro_envio;
      mensagemErro.classList.remove('d-none');
    } finally {
      btnEnviarMensagem.disabled = false;
      btnEnviarMensagem.innerHTML = `<i class="bi bi-send me-2"></i>${T.enviar}`;
    }
  });

  const formatarRelatorio = (texto) => {
    if (!texto) return '';
    return texto.replace(/\n/g, '<br>');
  };

  const preencherDetalhes = (proc) => {
    document.getElementById('det-numero').textContent = proc.numero_processo || '';
    document.getElementById('det-titulo').textContent = proc.titulo_processo || '';
    document.getElementById('det-ramo').textContent = proc.ramo_direito_nome || T.na;
    document.getElementById('det-responsavel').textContent = proc.responsavel_nome || T.na;
    
    const statusBadge = document.getElementById('det-status');
    statusBadge.textContent = proc.status;
    statusBadge.className = 'badge mt-2 mt-md-0'; // reset
    if(proc.status === 'Encerrado') statusBadge.classList.add('bg-danger');
    else if(proc.status === 'Arquivado') statusBadge.classList.add('bg-secondary');
    else statusBadge.classList.add('bg-success');

    const relatorioCard = document.getElementById('det-relatorio-card');
    if (proc.relatorio_final && parseInt(proc.relatorio_final_publicado) === 1) {
      document.getElementById('det-relatorio-text').innerHTML = formatarRelatorio(proc.relatorio_final);
      relatorioCard.classList.remove('d-none');
    } else {
      relatorioCard.classList.add('d-none');
    }
  };

  btnVoltar.addEventListener('click', () => {
    detalhesProcessoView.classList.add('d-none');
    listaProcessosView.classList.remove('d-none');
  });

  // Inicialização (Checando Token na URL ou Sessão Existente)
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');
  const devMode = urlParams.get('dev');

  if (devMode === 'true') {
    clienteData = { nome: 'Cliente Validador (Modo Dev)' };
    showAuth();
    renderizarProcessos([
      {
        id_processo: 517,
        numero_processo: '0001234-56.2026.8.26.0000',
        titulo_processo: 'Ação de Indenização por Danos Morais',
        status: 'Em Andamento',
        responsavel_nome: 'Dr. Advogado Validador',
        ramo_direito_nome: 'Direito Civil',
        relatorio_final: null,
        relatorio_final_publicado: 0
      }
    ]);
    return;
  }

  if (token) {
    try {
      const response = await apiFetch('cliente/auth/verificar', {
        method: 'POST',
        body: JSON.stringify({ token })
      });
      
      if (response.success) {
        clienteData = response.cliente;
        // Limpar URL
        window.history.replaceState({}, document.title, window.location.pathname);
        showAuth();
        await carregarProcessos();
      }
    } catch (err) {
      showError(err.message || T.link_invalido);
    }
  } else {
    try {
      const response = await apiFetch('cliente/perfil');
      if (response.success) {
        clienteData = response.data;
        showAuth();
        await carregarProcessos();
      }
    } catch (err) {
      showError(T.sessao_expirada);
    }
  }

});
</script>
<?= $this->endSection() ?>
