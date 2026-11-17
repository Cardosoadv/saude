<?php /** @var array $dados @var array $secao */
$unidade  = ((array) site_config('contato.enderecos', []))[0] ?? [];
$endereco = $unidade['resumo'] ?? trim(($unidade['linha1'] ?? '') . ' ' . ($unidade['linha2'] ?? ''));
$telefone = (string) site_config('contato.telefone', '');
$email    = (string) site_config('contato.email', '');
$areas    = $dados['areas'] ?? [];
$politica = '<a href="' . esc(site_url_publica('pagina/politica-de-privacidade')) . '" target="_blank" rel="noopener">'
    . esc(site_texto('contato.privacidade_link')) . '</a>';
?>

    <div class="gold-line"<?= $secao['ancora'] ? ' id="' . esc($secao['ancora'], 'attr') . '"' : '' ?>></div>

    <!-- FORMULÁRIO DE CONTATO PREMIUM -->
    <section class="contact-section py-5">
        <div class="container px-3 px-md-5">
            <div class="row g-5">
                <!-- Informações de Contato -->
                <div class="col-lg-5">
<?php if (!empty($dados['sobretitulo'])): ?>
                    <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
                    <h2 class="section-title"><?= esc($dados['titulo'] ?? '') ?></h2>
                    <p class="contact-description">
                        <?= esc($dados['descricao'] ?? '') ?>
                    </p>

                    <div class="contact-methods mt-5">
<?php if ($endereco !== ''): ?>
                        <div class="contact-item d-flex gap-3 mb-4">
                            <div class="contact-icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></div>
                            <div>
                                <span class="contact-label"><?= esc(site_texto('contato.local_rotulo')) ?></span>
                                <p class="contact-value"><?= esc($endereco) ?></p>
                            </div>
                        </div>
<?php endif ?>
<?php if ($telefone !== ''): ?>
                        <div class="contact-item d-flex gap-3 mb-4">
                            <div class="contact-icon" aria-hidden="true"><i class="bi bi-telephone"></i></div>
                            <div>
                                <span class="contact-label"><?= esc(site_texto('contato.telefone_rotulo')) ?></span>
                                <p class="contact-value"><?= site_telefone($telefone) ?></p>
                            </div>
                        </div>
<?php endif ?>
<?php if ($email !== ''): ?>
                        <div class="contact-item d-flex gap-3">
                            <div class="contact-icon" aria-hidden="true"><i class="bi bi-envelope"></i></div>
                            <div>
                                <span class="contact-label"><?= esc(site_texto('contato.email_rotulo')) ?></span>
                                <p class="contact-value"><?= esc($email) ?></p>
                            </div>
                        </div>
<?php endif ?>
                    </div>
                </div>

                <!-- Formulário -->
                <div class="col-lg-7">
                    <div class="contact-form-container">

                        <div id="contact-success" class="success-card text-center p-5 animate-fade-in" style="display: none;" role="status" aria-live="polite">
                            <div class="success-icon" aria-hidden="true"><i class="bi bi-check-circle"></i></div>
                            <h3 class="mt-4 mb-3"><?= esc(site_texto('contato.sucesso_titulo')) ?></h3>
                            <p id="success-message"></p>
                            <button type="button" id="btn-reset-form" class="btn-gold mt-3" aria-label="<?= esc(site_texto('contato.sucesso_outra_aria')) ?>"><?= esc(site_texto('contato.sucesso_outra')) ?></button>
                        </div>

                        <form id="contact-form" class="contact-form" action="<?= base_url("api/v2/site/contact") ?>" method="post">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nome" class="form-label-custom"><?= esc(site_texto('contato.nome')) ?></label>
                                    <input
                                        type="text"
                                        id="nome"
                                        name="nome"
                                        required
                                        autocomplete="name"
                                        placeholder="<?= esc(site_texto('contato.nome_placeholder'), 'attr') ?>"
                                        class="form-control-custom"
                                    />
                                </div>
                                <div class="col-md-6">
                                    <label for="telefone" class="form-label-custom"><?= esc(site_texto('contato.telefone')) ?></label>
                                    <input
                                        type="tel"
                                        id="telefone"
                                        name="telefone"
                                        autocomplete="tel"
                                        placeholder="<?= esc(site_texto('contato.telefone_placeholder'), 'attr') ?>"
                                        class="form-control-custom"
                                    />
                                </div>
                                <div class="col-12">
                                    <label for="email" class="form-label-custom"><?= esc(site_texto('contato.email')) ?></label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        required
                                        autocomplete="email"
                                        placeholder="<?= esc(site_texto('contato.email_placeholder'), 'attr') ?>"
                                        class="form-control-custom"
                                    />
                                </div>
                                <div class="col-12">
                                    <label for="area" class="form-label-custom"><?= esc(site_texto('contato.area')) ?></label>
                                    <select id="area" name="area" class="form-select-custom">
                                        <option value=""><?= esc(site_texto('contato.area_placeholder')) ?></option>
<?php foreach ($areas as $area): ?>
                                        <option value="<?= esc($area['id'], 'attr') ?>"><?= esc($area['nome'] !== '' ? $area['nome'] : 'Sem título') ?></option>
<?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="mensagem" class="form-label-custom"><?= esc(site_texto('contato.mensagem')) ?></label>
                                    <textarea
                                        id="mensagem"
                                        name="mensagem"
                                        required
                                        placeholder="<?= esc(site_texto('contato.mensagem_placeholder'), 'attr') ?>"
                                        rows="4"
                                        class="form-control-custom"
                                    ></textarea>
                                </div>

                                <div class="col-12">
                                    <div class="form-check-custom">
                                        <input type="checkbox" id="aceite_privacidade" name="aceite_privacidade" value="1" required />
                                        <label for="aceite_privacidade"><?= str_replace('{politica}', $politica, esc(site_texto('contato.privacidade_aceite'))) ?></label>
                                    </div>
                                </div>

                                <div id="contact-errors" class="col-12 alert-error" style="display: none;" role="alert" aria-live="assertive" tabindex="-1">
                                    <ul id="error-list"></ul>
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" id="btn-submit" class="btn-submit">
                                        <span id="submit-spinner" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display: none;"></span>
                                        <span id="submit-text"><?= esc(site_texto('contato.botao')) ?></span>
                                    </button>
                                    <p class="form-privacy-note mt-3 mb-0"><?= esc(site_texto('contato.privacidade_aviso')) ?></p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

<script>
const T = <?= json_encode([
    'enviando' => site_texto('contato.enviando'),
    'botao'    => site_texto('contato.botao'),
    'sucesso'  => site_texto('contato.sucesso_padrao'),
    'erro'     => site_texto('contato.erro_padrao'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
document.addEventListener('DOMContentLoaded', () => {
    const contactForm = document.getElementById('contact-form');
    const contactSuccess = document.getElementById('contact-success');
    const successMessage = document.getElementById('success-message');
    const contactErrors = document.getElementById('contact-errors');
    const errorList = document.getElementById('error-list');
    const btnSubmit = document.getElementById('btn-submit');
    const submitSpinner = document.getElementById('submit-spinner');
    const submitText = document.getElementById('submit-text');
    const btnResetForm = document.getElementById('btn-reset-form');

    if (contactForm) {
        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            contactErrors.style.display = 'none';
            errorList.innerHTML = '';

            btnSubmit.disabled = true;
            if (submitSpinner) submitSpinner.style.display = 'inline-block';
            if (submitText) submitText.textContent = T.enviando;

            const formData = new FormData(contactForm);
            const dataObj = {};
            formData.forEach((value, key) => {
                dataObj[key] = value;
            });

            try {
                const response = await fetch(contactForm.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(dataObj)
                });

                const result = await response.json();

                if (!response.ok) {
                    if (result.errors) {
                        throw { type: 'validation', errors: result.errors };
                    } else {
                        throw { type: 'general', message: result.message || T.erro };
                    }
                }

                contactForm.style.display = 'none';
                if (successMessage) {
                    successMessage.textContent = result.message || T.sucesso;
                }
                contactSuccess.style.display = 'block';
                if (window.siteConversao) { window.siteConversao('formulario'); }

            } catch (err) {
                if (err.type === 'validation') {
                    Object.values(err.errors).forEach(errorMsg => {
                        const li = document.createElement('li');
                        li.textContent = errorMsg;
                        errorList.appendChild(li);
                    });
                } else {
                    const li = document.createElement('li');
                    li.textContent = err.message || T.erro;
                    errorList.appendChild(li);
                }
                contactErrors.style.display = 'block';
                contactErrors.focus();
            } finally {
                btnSubmit.disabled = false;
                if (submitSpinner) submitSpinner.style.display = 'none';
                if (submitText) submitText.textContent = T.botao;
            }
        });
    }

    if (btnResetForm) {
        btnResetForm.addEventListener('click', () => {
            contactSuccess.style.display = 'none';
            if (contactForm) {
                contactForm.reset();
                contactForm.style.display = 'block';
                const firstInput = contactForm.querySelector('input');
                if (firstInput) firstInput.focus();
            }
        });
    }
});
</script>
