<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/area-cliente.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="area-cliente-view">
  <!-- Header Decorativo (Hero minimalista) -->
  <header class="page-header py-5">
    <div class="container px-3 px-md-5 mt-4">
      <h1 class="page-title text-center animate-fade-in"><?= esc(site_texto('cliente.titulo')) ?></h1>
      <p class="page-subtitle text-center mx-auto animate-slide-up">
        <?= esc(site_texto('cliente.subtitulo')) ?>
      </p>
    </div>
  </header>

  <!-- Formulário de Magic Link -->
  <section class="magic-link-section py-5">
    <div class="container px-3 px-md-5">
      <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
          <div class="login-card premium-card p-4 p-md-5 animate-slide-up-delay">
            <h2 class="card-title text-center mb-4"><?= esc(site_texto('cliente.acesso_titulo')) ?></h2>
            <p class="text-center text-muted mb-4" id="form-description">
              <?= esc(site_texto('cliente.acesso_descricao')) ?>
            </p>

            <form id="magic-link-form">
              <div class="mb-4 form-floating-custom">
                <input 
                  type="email" 
                  id="email" 
                  name="email"
                  class="form-control premium-input" 
                  placeholder="<?= esc(site_texto('cliente.email_placeholder')) ?>" 
                  required
                />
                <label for="email"><?= esc(site_texto('cliente.email_rotulo')) ?></label>
              </div>

              <div id="error-message" class="alert alert-danger custom-alert d-none" role="alert"></div>

              <button type="submit" id="btn-submit" class="btn-premium w-100 mt-2">
                <span id="spinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                <span id="btn-text"><?= esc(site_texto('cliente.botao')) ?></span>
              </button>
            </form>

            <!-- Mensagem de Sucesso -->
            <div id="success-message" class="success-message text-center py-4 d-none">
              <div class="icon-success mb-3">
                <i class="bi bi-envelope-check-fill text-gold fs-1"></i>
              </div>
              <h3 class="mb-3 text-white"><?= esc(site_texto('cliente.enviado_titulo')) ?></h3>
              <p class="text-light">
                <?= esc(site_texto('cliente.enviado_antes')) ?> <strong id="success-email" class="text-gold"></strong>. <br>
                <?= esc(site_texto('cliente.enviado_depois')) ?>
              </p>
              <button type="button" id="btn-reset" class="btn btn-outline-gold mt-3">
                <?= esc(site_texto('cliente.outro_email')) ?>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const T = <?= json_encode([
    'enviando' => site_texto('cliente.enviando'),
    'erro'     => site_texto('cliente.erro'),
    'botao'    => site_texto('cliente.botao'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('magic-link-form');
  const emailInput = document.getElementById('email');
  const btnSubmit = document.getElementById('btn-submit');
  const spinner = document.getElementById('spinner');
  const btnText = document.getElementById('btn-text');
  const errorMessage = document.getElementById('error-message');
  const successMessage = document.getElementById('success-message');
  const successEmail = document.getElementById('success-email');
  const btnReset = document.getElementById('btn-reset');
  const formDescription = document.getElementById('form-description');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = emailInput.value.trim();
    if (!email) return;

    // Loading state
    emailInput.disabled = true;
    btnSubmit.disabled = true;
    spinner.classList.remove('d-none');
    btnText.textContent = T.enviando;
    errorMessage.classList.add('d-none');
    errorMessage.textContent = '';

    try {
      const baseUrl = window.__APP_CONFIG__?.apiUrl || '<?= base_url('api/v2/site/') ?>';
      const response = await fetch(`${baseUrl}cliente/auth/solicitar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message || T.erro);
      }

      // Success state
      form.classList.add('d-none');
      successEmail.textContent = email;
      successMessage.classList.remove('d-none');
      formDescription.classList.add('d-none');
      
    } catch (error) {
      errorMessage.textContent = error.message;
      errorMessage.classList.remove('d-none');
    } finally {
      // Restore loading state
      emailInput.disabled = false;
      btnSubmit.disabled = false;
      spinner.classList.add('d-none');
      btnText.textContent = T.botao;
    }
  });

  btnReset.addEventListener('click', () => {
    successMessage.classList.add('d-none');
    form.classList.remove('d-none');
    formDescription.classList.remove('d-none');
    emailInput.value = '';
    emailInput.focus();
  });
});
</script>
<?= $this->endSection() ?>
