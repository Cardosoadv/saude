<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/painel-cliente.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="cliente-panel-view">
  <header class="page-header py-4">
    <div class="container px-3 px-md-5 mt-4 text-center">
      <h1 class="page-title text-gold animate-fade-in"><?= esc(site_texto('painel.titulo')) ?></h1>
      <p id="welcome-message" class="page-subtitle mx-auto text-muted animate-slide-up d-none"></p>
    </div>
  </header>

  <div class="container px-3 px-md-5 py-5">

    <div id="loading-state" class="text-center py-5">
      <div class="spinner-border text-gold" role="status">
        <span class="visually-hidden"><?= esc(site_texto('painel.carregando')) ?></span>
      </div>
      <p class="mt-3 text-muted"><?= esc(site_texto('painel.acessando')) ?></p>
    </div>

    <div id="error-state" class="alert alert-danger custom-alert text-center py-4 d-none" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
      <span id="error-text"></span>
      <div class="mt-3">
        <a href="<?= site_url_publica('area-cliente') ?>" class="btn btn-outline-gold"><?= esc(site_texto('painel.voltar_login')) ?></a>
      </div>
    </div>

    <div id="auth-state" class="d-none">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <h3 class="text-gold mb-4"><i class="bi bi-person-vcard me-2"></i><?= esc(site_texto('painel.meus_dados')) ?></h3>
          <div class="premium-card p-4 p-md-5">
            <dl class="row mb-0" id="dados-cliente"></dl>
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
    'na' => site_texto('painel.js_na'),
    'link_invalido' => site_texto('painel.js_link_invalido'),
    'sessao_expirada' => site_texto('painel.js_sessao_expirada'),
    'rotulos' => [
        'nome' => site_texto('painel.campo_nome'),
        'email' => site_texto('painel.campo_email'),
        'telefone' => site_texto('painel.campo_telefone'),
        'documento' => site_texto('painel.campo_documento'),
        'cidade' => site_texto('painel.campo_cidade'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  const loadingState = document.getElementById('loading-state');
  const errorState = document.getElementById('error-state');
  const authState = document.getElementById('auth-state');
  const errorText = document.getElementById('error-text');
  const welcomeMessage = document.getElementById('welcome-message');
  const dadosCliente = document.getElementById('dados-cliente');
  const baseUrl = window.__APP_CONFIG__?.apiUrl || '<?= base_url('api/v2/site/') ?>';

  const showError = (msg) => {
    loadingState.classList.add('d-none');
    errorText.textContent = msg;
    errorState.classList.remove('d-none');
  };

  const apiFetch = async (endpoint, options = {}) => {
    const response = await fetch(`${baseUrl}${endpoint}`, {
      ...options,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...options.headers },
      credentials: 'include'
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || T.erro_requisicao);
    }
    return data;
  };

  const showAuth = (cliente) => {
    loadingState.classList.add('d-none');
    welcomeMessage.textContent = T.bemvindo.replace('{nome}', cliente.nome || '');
    welcomeMessage.classList.remove('d-none');

    const cidade = [cliente.cidade, cliente.uf].filter(Boolean).join(' / ');
    const valores = { nome: cliente.nome, email: cliente.email, telefone: cliente.telefone, documento: cliente.documento, cidade };
    dadosCliente.innerHTML = Object.entries(T.rotulos).map(([chave, rotulo]) => {
      const valor = document.createElement('div');
      valor.textContent = valores[chave] || T.na;
      return `<dt class="col-sm-4 text-gold">${rotulo}</dt><dd class="col-sm-8">${valor.innerHTML}</dd>`;
    }).join('');
    authState.classList.remove('d-none');
  };

  const token = new URLSearchParams(window.location.search).get('token');

  if (token) {
    try {
      const response = await apiFetch('cliente/auth/verificar', { method: 'POST', body: JSON.stringify({ token }) });
      if (response.success) {
        window.history.replaceState({}, document.title, window.location.pathname);
        showAuth(response.cliente);
      }
    } catch (err) {
      showError(err.message || T.link_invalido);
    }
  } else {
    try {
      const response = await apiFetch('cliente/perfil');
      if (response.success) {
        showAuth(response.data);
      }
    } catch (err) {
      showError(T.sessao_expirada);
    }
  }
});
</script>
<?= $this->endSection() ?>
